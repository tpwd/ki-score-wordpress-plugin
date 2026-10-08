<?php
/**
 * Runs against the stack from tests/e2e/docker-compose.yml, see tests/e2e/run.sh.
 */

use PHPUnit\Framework\TestCase;

final class TrackingTest extends TestCase {

	const SETTINGS = array(
		'site_id'          => 'e2e-site',
		'tracking_secret'  => 'e2e-secret',
		'client_ip_header' => '',
	);

	private static function port( string $name, int $fallback ): int {
		return (int) ( getenv( $name ) ?: $fallback );
	}

	public static function servers(): array {
		return array(
			'apache with mod_php' => array( 'http://localhost:' . self::port( 'KISCORE_E2E_APACHE_PORT', 8091 ), false ),
			'nginx with php-fpm'  => array( 'http://localhost:' . self::port( 'KISCORE_E2E_FPM_PORT', 8092 ), true ),
		);
	}

	protected function setUp(): void {
		self::configure( self::SETTINGS );
		self::collector( 'POST', '/delay?seconds=0' );
		self::collector( 'DELETE', '/hits' );
	}

	/**
	 * @dataProvider servers
	 */
	public function test_reports_a_page_view_with_path_and_utm_parameters( string $base ): void {
		$agent    = 'KIscore-E2E-Page/' . uniqid();
		$response = self::get(
			$base,
			'/?utm_source=chatgpt.com&utm_campaign=e2e&token=reset-token',
			array(
				'User-Agent: ' . $agent,
				'Referer: https://chatgpt.com/',
				'Sec-Fetch-Mode: navigate',
			)
		);
		$this->assertSame( 200, $response['status'] );

		$hit = self::hit_from( $agent );
		$this->assertSame( '/track/e2e-site', $hit['path'] );
		$this->assertSame( 'e2e-secret', $hit['secret'] );
		$this->assertStringStartsWith( 'KIscore-WordPress/', $hit['agent'] );
		$this->assertSame( '/?utm_source=chatgpt.com&utm_campaign=e2e', $hit['body']['url'] );
		$this->assertSame( 'https://chatgpt.com/', $hit['body']['referer'] );
		$this->assertNotFalse( filter_var( $hit['body']['ip'], FILTER_VALIDATE_IP ) );
	}

	/**
	 * @dataProvider servers
	 */
	public function test_does_not_report_requests_that_are_no_page_view( string $base ): void {
		$agent = 'KIscore-E2E-Skipped/' . uniqid();
		$skip  = array( 'User-Agent: ' . $agent );

		self::get( $base, '/wp-json/', $skip );
		self::get( $base, '/feed/', $skip );
		self::get( $base, '/robots.txt', $skip );
		self::get( $base, '/wp-admin/', $skip );
		self::get( $base, '/wp-login.php', $skip );
		self::get( $base, '/', $skip, 'POST' );
		self::get( $base, '/', array_merge( $skip, array( 'Sec-Fetch-Mode: cors', 'Sec-Fetch-Dest: empty' ) ) );
		self::get( $base, '/', array_merge( $skip, array( 'Sec-Purpose: prefetch;prerender' ) ) );
		$redirect = self::get( $base, '/sample-page', $skip );
		$this->assertSame( 301, $redirect['status'] );

		// Hits are sent after the response: wait for a later page view first.
		$sentinel = 'KIscore-E2E-Sentinel/' . uniqid();
		self::get( $base, '/sample-page/', array( 'User-Agent: ' . $sentinel ) );
		self::hit_from( $sentinel );
		usleep( 300000 );
		$this->assertSame( array(), self::hits_from( $agent ) );
	}

	/**
	 * @dataProvider servers
	 */
	public function test_a_slow_api_does_not_hold_back_the_page( string $base, bool $finishes_request_early ): void {
		self::collector( 'POST', '/delay?seconds=3' );
		self::get( $base, '/', array() );

		$response = self::get( $base, '/', array( 'User-Agent: KIscore-E2E-Slow/' . uniqid() ) );
		$this->assertSame( 200, $response['status'] );
		// PHP-FPM hands the page over before sending. mod_php cannot, there the
		// timeout of one second bounds the delay.
		$this->assertLessThan( $finishes_request_early ? 0.8 : 2.0, $response['seconds'] );
	}

	/**
	 * @dataProvider servers
	 */
	public function test_reads_the_visitor_ip_from_the_configured_header( string $base ): void {
		self::configure( array( 'client_ip_header' => 'X-Real-IP' ) + self::SETTINGS );

		$without = 'KIscore-E2E-NoIp/' . uniqid();
		self::get( $base, '/', array( 'User-Agent: ' . $without ) );
		$agent = 'KIscore-E2E-Ip/' . uniqid();
		self::get( $base, '/', array( 'User-Agent: ' . $agent, 'X-Real-IP: 198.51.100.23' ) );

		$this->assertSame( '198.51.100.23', self::hit_from( $agent )['body']['ip'] );
		$this->assertSame( array(), self::hits_from( $without ) );
	}

	public function test_connection_check_reports_whether_the_credentials_are_accepted(): void {
		$check = json_decode( self::wp( 'eval', 'echo wp_json_encode( Kiscore\Tracker::check_connection() );' ), true );
		$this->assertSame( array( true, 200 ), array( $check['ok'], $check['status'] ) );

		// No referrer, no utm parameters, no crawler: KIscore stores nothing.
		$hit = self::hit_from( 'KIscore-WordPress-Connection-Check' );
		$this->assertSame( array( 'url' => '/', 'user_agent' => $hit['body']['user_agent'], 'referer' => '' ), $hit['body'] );

		self::configure( array( 'tracking_secret' => 'wrong-secret' ) + self::SETTINGS );
		$check = json_decode( self::wp( 'eval', 'echo wp_json_encode( Kiscore\Tracker::check_connection() );' ), true );
		$this->assertSame( array( false, 401 ), array( $check['ok'], $check['status'] ) );
	}

	public function test_remembers_the_last_answer_of_the_api(): void {
		list( $base ) = self::servers()['apache with mod_php'];
		self::configure( array( 'tracking_secret' => 'wrong-secret' ) + self::SETTINGS );
		$agent = 'KIscore-E2E-Delivery/' . uniqid();
		self::get( $base, '/', array( 'User-Agent: ' . $agent ) );
		self::hit_from( $agent );

		// The answer is stored right after the collector has seen the hit.
		usleep( 500000 );
		$delivery = json_decode( self::wp( 'option', 'get', 'kiscore_last_delivery', '--format=json' ), true );
		$this->assertSame( 401, $delivery['status'] );
	}

	public function test_settings_page_saves_the_site_id_and_keeps_the_secret_out_of_the_page(): void {
		list( $base ) = self::servers()['apache with mod_php'];
		$cookies      = tempnam( sys_get_temp_dir(), 'kiscore-e2e' );
		self::get( $base, '/wp-login.php', array(), 'GET', null, $cookies );
		self::get( $base, '/wp-login.php', array(), 'POST', 'log=e2e-admin&pwd=e2e-admin-password&testcookie=1', $cookies );

		// The admin sees the page in German ("Sie"), loaded from languages/.
		self::wp( 'user', 'meta', 'update', 'e2e-admin', 'locale', 'de_DE_formal' );
		try {
			$page = self::get( $base, '/wp-admin/options-general.php?page=kiscore', array(), 'GET', null, $cookies );
		} finally {
			self::wp( 'user', 'meta', 'delete', 'e2e-admin', 'locale' );
		}
		$this->assertSame( 200, $page['status'] );
		$this->assertStringContainsString( 'Verbindung prüfen', $page['body'] );
		$this->assertStringContainsString( 'value="e2e-site"', $page['body'] );
		$this->assertStringNotContainsString( 'e2e-secret', $page['body'] );
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="([^"]+)"/', $page['body'] );
		preg_match( '/name="_wpnonce" value="([^"]+)"/', $page['body'], $nonce );

		$form = http_build_query(
			array(
				'option_page'      => 'kiscore',
				'action'           => 'update',
				'_wpnonce'         => $nonce[1],
				'kiscore_settings' => array(
					'site_id'          => ' new-site ',
					'tracking_secret'  => '',
					'client_ip_header' => 'X-Real-IP; drop table',
				),
			)
		);
		self::get( $base, '/wp-admin/options.php', array( 'Referer: ' . $base . '/wp-admin/options-general.php?page=kiscore' ), 'POST', $form, $cookies );
		unlink( $cookies );

		$this->assertSame(
			array(
				'site_id'          => 'new-site',
				'tracking_secret'  => 'e2e-secret',
				'client_ip_header' => 'X-Real-IPdroptable',
			),
			json_decode( self::wp( 'option', 'get', 'kiscore_settings', '--format=json' ), true )
		);
	}

	private static function configure( array $settings ): void {
		self::wp( 'option', 'update', 'kiscore_settings', json_encode( $settings ), '--format=json' );
	}

	private static function wp( string ...$args ): string {
		$command = array_merge( array( 'docker', 'compose', '-f', __DIR__ . '/docker-compose.yml', 'run', '--rm', '-T', 'cli', 'wp' ), $args );
		$process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$output  = stream_get_contents( $pipes[1] );
		$errors  = stream_get_contents( $pipes[2] );
		if ( 0 !== proc_close( $process ) ) {
			throw new RuntimeException( "wp {$args[0]} failed: {$output}{$errors}" );
		}
		return trim( $output );
	}

	private static function get( string $base, string $path, array $headers, string $method = 'GET', ?string $body = null, ?string $cookies = null ): array {
		$curl = curl_init( $base . $path );
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_HTTPHEADER     => array_merge( array( 'Accept: text/html' ), $headers ),
				CURLOPT_TIMEOUT        => 10,
			)
		);
		if ( null !== $body ) {
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
		}
		if ( null !== $cookies ) {
			curl_setopt( $curl, CURLOPT_COOKIEJAR, $cookies );
			curl_setopt( $curl, CURLOPT_COOKIEFILE, $cookies );
		}
		$response = curl_exec( $curl );
		return array(
			'status'  => curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ),
			'seconds' => curl_getinfo( $curl, CURLINFO_TOTAL_TIME ),
			'body'    => (string) $response,
		);
	}

	private static function collector( string $method, string $path ): array {
		$response = self::get( 'http://localhost:' . self::port( 'KISCORE_E2E_COLLECTOR_PORT', 8090 ), $path, array(), $method );
		return json_decode( $response['body'], true ) ?: array();
	}

	private static function hits_from( string $agent_prefix ): array {
		return array_values(
			array_filter(
				self::collector( 'GET', '/hits' ),
				function ( array $hit ) use ( $agent_prefix ): bool {
					return 0 === strpos( (string) ( $hit['body']['user_agent'] ?? '' ), $agent_prefix );
				}
			)
		);
	}

	private static function hit_from( string $agent ): array {
		for ( $attempt = 0; $attempt < 50; $attempt++ ) {
			$hits = self::hits_from( $agent );
			if ( $hits ) {
				return $hits[0];
			}
			usleep( 100000 );
		}
		throw new RuntimeException( "No hit from {$agent} reached the collector" );
	}
}
