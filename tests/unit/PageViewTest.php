<?php

use Kiscore\Page_View;
use PHPUnit\Framework\TestCase;

final class PageViewTest extends TestCase {

	private function server( array $overrides = array() ): array {
		return array_merge(
			array(
				'REQUEST_METHOD'  => 'GET',
				'REQUEST_URI'     => '/beratung/?utm_source=chatgpt.com&utm_campaign=test&token=reset-token',
				'HTTP_ACCEPT'     => 'text/html,application/xhtml+xml,*/*;q=0.8',
				'HTTP_USER_AGENT' => 'Mozilla/5.0',
				'HTTP_REFERER'    => 'https://chatgpt.com/',
				'REMOTE_ADDR'     => '203.0.113.7',
			),
			$overrides
		);
	}

	public function test_payload_has_path_utm_parameters_user_agent_referer_and_ip(): void {
		$this->assertSame(
			array(
				'url'        => '/beratung/?utm_source=chatgpt.com&utm_campaign=test',
				'user_agent' => 'Mozilla/5.0',
				'referer'    => 'https://chatgpt.com/',
				'ip'         => '203.0.113.7',
			),
			Page_View::payload( $this->server() )
		);
	}

	public function test_query_parameters_keep_their_encoding_and_utm_keys_match_case_insensitively(): void {
		$payload = Page_View::payload( $this->server( array( 'REQUEST_URI' => '/?q=x&UTM_Source=chat%20gpt&utm_term=a+b' ) ) );
		$this->assertSame( '/?UTM_Source=chat%20gpt&utm_term=a+b', $payload['url'] );

		$this->assertSame( '/kontakt/', Page_View::payload( $this->server( array( 'REQUEST_URI' => '/kontakt/?email=a@b.de' ) ) )['url'] );
	}

	public function test_reads_the_visitor_ip_from_the_configured_header(): void {
		$server = $this->server(
			array(
				'REMOTE_ADDR'           => '10.0.0.5',
				'HTTP_CF_CONNECTING_IP' => '2001:db8::1',
			)
		);
		$this->assertSame( '2001:db8::1', Page_View::payload( $server, 'CF-Connecting-IP' )['ip'] );
		$this->assertSame( '10.0.0.5', Page_View::payload( $server )['ip'] );
	}

	// Hosting docs often name the header as a $_SERVER key. Settings or a
	// constant in that spelling must not switch tracking off.
	public function test_accepts_the_header_in_server_variable_spelling(): void {
		$server = $this->server(
			array(
				'REMOTE_ADDR'    => '10.0.0.5',
				'HTTP_X_REAL_IP' => '198.51.100.23',
			)
		);
		foreach ( array( 'HTTP_X_REAL_IP', 'X_Real_IP', ' x-real-ip ' ) as $header ) {
			$this->assertSame( '198.51.100.23', Page_View::payload( $server, $header )['ip'], $header );
		}
		$this->assertSame( 'X-Real-IP', Page_View::header_name( 'HTTP_X_Real_IP' ) );
	}

	// Before 1.0.1 saving stripped underscores: "HTTP_X_REAL_IP" was stored as
	// "HTTPXREALIP". Known proxy headers are recognised in that form.
	public function test_recognises_a_header_saved_without_underscores(): void {
		$this->assertSame( 'X-Real-IP', Page_View::header_name( 'HTTPXREALIP' ) );
		$this->assertSame( 'CF-Connecting-IP', Page_View::header_name( 'CFCONNECTINGIP' ) );
		$this->assertSame( 'XMYHEADER', Page_View::header_name( 'XMYHEADER' ) );

		$server = $this->server( array( 'HTTP_X_REAL_IP' => '198.51.100.23' ) );
		$this->assertSame( '198.51.100.23', Page_View::payload( $server, 'HTTPXREALIP' )['ip'] );
	}

	// Without the visitor's own IP, KIscore would count every visit as one
	// visitor with the IP of the proxy.
	public function test_skips_requests_without_exactly_one_valid_ip(): void {
		foreach ( array( '', 'unknown', '203.0.113.7, 198.51.100.2', '999.1.1.1' ) as $ip ) {
			$this->assertNull( Page_View::payload( $this->server( array( 'HTTP_X_REAL_IP' => $ip ) ), 'X-Real-IP' ), $ip );
		}
		$this->assertNull( Page_View::payload( $this->server( array( 'REMOTE_ADDR' => '' ) ) ) );
	}

	public function test_skips_requests_that_are_no_full_page_view(): void {
		$cases = array(
			'post'           => array( 'REQUEST_METHOD' => 'POST' ),
			'head'           => array( 'REQUEST_METHOD' => 'HEAD' ),
			'fetch'          => array(
				'HTTP_SEC_FETCH_MODE' => 'cors',
				'HTTP_SEC_FETCH_DEST' => 'empty',
			),
			'prefetch'       => array( 'HTTP_SEC_PURPOSE' => 'prefetch' ),
			'prerender'      => array( 'HTTP_SEC_PURPOSE' => 'prefetch;prerender' ),
			'legacy purpose' => array( 'HTTP_PURPOSE' => 'prefetch' ),
			'json'           => array( 'HTTP_ACCEPT' => 'application/json' ),
			'image'          => array(
				'REQUEST_URI' => '/wp-content/uploads/hero.webp',
				'HTTP_ACCEPT' => '*/*',
			),
			'robots'         => array(
				'REQUEST_URI' => '/robots.txt',
				'HTTP_ACCEPT' => '*/*',
			),
			'relative uri'   => array( 'REQUEST_URI' => 'beratung' ),
			'scheme uri'     => array( 'REQUEST_URI' => '//evil.example/' ),
		);
		foreach ( $cases as $name => $overrides ) {
			$this->assertNull( Page_View::payload( $this->server( $overrides ) ), $name );
		}
	}

	public function test_tracks_page_loads_and_crawlers_without_accept_header(): void {
		$navigation = $this->server(
			array(
				'HTTP_SEC_FETCH_MODE' => 'navigate',
				'HTTP_SEC_FETCH_DEST' => 'document',
			)
		);
		$this->assertNotNull( Page_View::payload( $navigation ) );

		$crawler = $this->server( array( 'HTTP_USER_AGENT' => 'GPTBot/1.2' ) );
		unset( $crawler['HTTP_ACCEPT'], $crawler['HTTP_REFERER'] );
		$this->assertSame( '', Page_View::payload( $crawler )['referer'] );
	}

	public function test_truncates_fields_to_the_api_limits_without_splitting_characters(): void {
		$payload = Page_View::payload(
			$this->server(
				array(
					'REQUEST_URI'     => '/' . str_repeat( '%C3%A4', 400 ),
					'HTTP_USER_AGENT' => 'GPTBot ' . str_repeat( 'ä', 1000 ),
					'HTTP_REFERER'    => 'https://chatgpt.com/' . str_repeat( 'y', 3000 ),
				)
			)
		);

		$this->assertLessThanOrEqual( 2048, strlen( $payload['url'] ) );
		$this->assertDoesNotMatchRegularExpression( '/%[0-9A-F]?$/i', $payload['url'] );
		$this->assertLessThanOrEqual( 1024, strlen( $payload['user_agent'] ) );
		$this->assertGreaterThan( 1020, strlen( $payload['user_agent'] ) );
		$this->assertTrue( mb_check_encoding( $payload['user_agent'], 'UTF-8' ) );
		$this->assertSame( 2048, strlen( $payload['referer'] ) );
	}
}
