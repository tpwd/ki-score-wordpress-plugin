<?php
/**
 * Settings page under Settings → KIscore.
 *
 * @package Kiscore
 */

namespace Kiscore;

defined( 'ABSPATH' ) || exit;

/**
 * Settings form, connection check and setup hints.
 */
final class Admin {

	const PAGE = 'kiscore';

	const CHECK_ACTION = 'kiscore_check_connection';

	const DASHBOARD_URL = 'https://toolkit.kiscore.ai/dashboard/integration';

	/**
	 * Hooks the page, the setting, the notice and the connection check into the admin.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
		add_action( 'admin_post_' . self::CHECK_ACTION, array( __CLASS__, 'check_connection' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KISCORE_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds Settings → KIscore.
	 */
	public static function add_page(): void {
		add_options_page( 'KIscore', 'KIscore', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Registers the option behind the settings form.
	 */
	public static function register_setting(): void {
		register_setting(
			self::PAGE,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Adds a settings link to the plugin list.
	 *
	 * @param string[] $links Links below the plugin name.
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Settings', 'kiscore' ) ) );
		return $links;
	}

	/**
	 * Reminds administrators on the dashboard and the plugin list to connect the site.
	 */
	public static function setup_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true )
			|| ! current_user_can( 'manage_options' ) || Settings::is_configured() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'KIscore is active but not connected yet.', 'kiscore' ),
			esc_url( self::page_url() ),
			esc_html__( 'Enter the connection key', 'kiscore' )
		);
	}

	/**
	 * Handles the "Check connection" button and returns to the settings page.
	 */
	public static function check_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'kiscore' ), 403 );
		}
		check_admin_referer( self::CHECK_ACTION );

		set_transient( self::check_transient(), Tracker::check_connection(), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Renders the settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$check = get_transient( self::check_transient() );
		delete_transient( self::check_transient() );
		?>
		<div class="wrap">
			<h1>KIscore</h1>
			<p><?php esc_html_e( 'KIscore shows which AI crawlers visit your site and which visitors come from ChatGPT, Perplexity and other AI assistants. The plugin reports page views from your server. It adds no script and no cookie to your pages.', 'kiscore' ); ?></p>

			<?php
			if ( is_array( $check ) ) {
				self::render_check_result( $check );
			}
			self::render_proxy_hint();
			?>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<?php self::render_connection_key_field(); ?>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Status', 'kiscore' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Last report', 'kiscore' ); ?></th>
					<td><?php self::render_last_delivery(); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection', 'kiscore' ); ?></th>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::CHECK_ACTION ); ?>">
							<?php wp_nonce_field( self::CHECK_ACTION ); ?>
							<?php submit_button( __( 'Check connection', 'kiscore' ), 'secondary', 'submit', false, Settings::is_configured() ? array() : array( 'disabled' => 'disabled' ) ); ?>
						</form>
						<p class="description"><?php esc_html_e( 'Checks the connection key without sending visitor data.', 'kiscore' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Test visit', 'kiscore' ); ?></th>
					<td>
						<p>
							<?php
							printf(
								/* translators: %s: query string to append to a page URL */
								esc_html__( 'Open any page of your site with %s. The visit then appears in KIscore under KI-Verweise.', 'kiscore' ),
								'<code>?utm_source=chatgpt.com&amp;utm_campaign=kiscore-integration-test</code>'
							);
							?>
						</p>
						<?php if ( defined( 'WP_CACHE' ) && WP_CACHE ) : ?>
							<p class="description"><?php esc_html_e( 'A page cache is active. Pages served from the cache do not reach WordPress and are not reported, so AI crawlers that only get cached pages can be missing in KIscore.', 'kiscore' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * The connection key field. The key holds the tracking secret, so the page
	 * never prints it; it shows the site ID it belongs to instead. A key set in
	 * wp-config.php or the environment locks the field.
	 */
	private static function render_connection_key_field(): void {
		$source = Settings::source( 'tracking_secret' );
		$locked = in_array( $source, array( 'constant', 'environment' ), true );
		$site   = Settings::get( 'site_id' );
		?>
		<tr>
			<th scope="row"><label for="kiscore-connection-key"><?php esc_html_e( 'Connection key', 'kiscore' ); ?></label></th>
			<td>
				<input
					type="password"
					id="kiscore-connection-key"
					class="regular-text code"
					autocomplete="off"
					spellcheck="false"
					<?php if ( $locked ) : ?>
						readonly
					<?php else : ?>
						name="<?php echo esc_attr( Settings::OPTION . '[connection_key]' ); ?>"
					<?php endif; ?>
					value=""
					placeholder="<?php echo esc_attr( Settings::is_configured() ? '••••••••' : '' ); ?>"
				>
				<p class="description">
					<?php
					if ( $locked ) {
						echo esc_html(
							'constant' === $source
								? __( 'Set in wp-config.php.', 'kiscore' )
								: __( 'Set as environment variable.', 'kiscore' )
						);
					} else {
						printf(
							/* translators: %s: link to the KIscore dashboard */
							esc_html__( 'Copy it from %s in the KIscore dashboard. It stays on your server and is not displayed here again.', 'kiscore' ),
							'<a href="' . esc_url( self::DASHBOARD_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Settings → Traffic tracking → WordPress', 'kiscore' ) . '</a>'
						);
					}
					?>
				</p>
				<?php if ( '' !== $site ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: site ID */
							esc_html__( 'Connected to site ID %s.', 'kiscore' ),
							'<code>' . esc_html( $site ) . '</code>'
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Shows the result of the connection check.
	 *
	 * @param array $check Result of Tracker::check_connection().
	 */
	private static function render_check_result( array $check ): void {
		printf(
			'<div class="notice notice-%s"><p>%s</p></div>',
			$check['ok'] ? 'success' : 'error',
			esc_html( self::describe( (int) $check['status'], (string) $check['error'] ) )
		);
	}

	/**
	 * Shows when KIscore last answered a page view report and how.
	 */
	private static function render_last_delivery(): void {
		$last = get_option( Tracker::LAST_DELIVERY_OPTION );
		if ( ! is_array( $last ) ) {
			esc_html_e( 'No page view reported yet.', 'kiscore' );
			return;
		}

		printf(
			/* translators: 1: time span such as "5 mins", 2: result of the report */
			esc_html__( '%1$s ago: %2$s', 'kiscore' ),
			esc_html( human_time_diff( (int) $last['time'] ) ),
			esc_html( self::describe( (int) $last['status'], '' ) )
		);
	}

	/**
	 * Explains an answer of the tracking API.
	 *
	 * @param int    $status HTTP status, 0 when KIscore was unreachable.
	 * @param string $error  Transport error, if any.
	 */
	private static function describe( int $status, string $error ): string {
		if ( Tracker::accepted( $status ) ) {
			return __( 'KIscore accepts data from this site.', 'kiscore' );
		}

		switch ( $status ) {
			case 401:
				return __( 'KIscore rejected the connection key (HTTP 401). Copy it again from the KIscore dashboard.', 'kiscore' );
			case 422:
				return __( 'KIscore did not accept the data (HTTP 422). Check that the subscription for this website is active.', 'kiscore' );
			case 429:
				return __( 'KIscore is limiting requests from this server right now (HTTP 429).', 'kiscore' );
			case 0:
				/* translators: %s: error message of the HTTP request */
				return sprintf( __( 'KIscore could not be reached: %s', 'kiscore' ), '' === $error ? __( 'no answer', 'kiscore' ) : $error );
			default:
				/* translators: %d: HTTP status code */
				return sprintf( __( 'KIscore answered with HTTP %d.', 'kiscore' ), $status );
		}
	}

	/**
	 * Warns when WordPress only sees a private address, which means a proxy
	 * stands in front of it, and names the headers that carry a visitor IP.
	 * Cloudflare's header is used without configuration.
	 */
	private static function render_proxy_hint(): void {
		$remote = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below
		if ( '' !== Settings::get( 'client_ip_header' )
			|| isset( $_SERVER['HTTP_CF_CONNECTING_IP'] )
			|| false === filter_var( $remote, FILTER_VALIDATE_IP )
			|| false !== filter_var( $remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return;
		}

		$server    = wp_unslash( $_SERVER );
		$available = array_filter(
			Page_View::KNOWN_IP_HEADERS,
			static function ( string $header ) use ( $server ): bool {
				return null !== Page_View::client_ip( $server, $header );
			}
		);

		printf(
			'<div class="notice notice-warning"><p>%s %s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: IP address */
					__( 'WordPress receives requests from %s, a private address. Your site probably runs behind a proxy or CDN. Name the header with the visitor IP in wp-config.php, e.g. define( \'KISCORE_CLIENT_IP_HEADER\', \'X-Real-IP\' );', 'kiscore' ),
					$remote
				)
			),
			$available
				? esc_html(
					sprintf(
						/* translators: %s: list of header names */
						__( 'Your current request carries: %s.', 'kiscore' ),
						implode( ', ', $available )
					)
				)
				: ''
		);
	}

	/**
	 * URL of the settings page.
	 */
	private static function page_url(): string {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Transient holding the connection check result of the current user.
	 */
	private static function check_transient(): string {
		return 'kiscore_check_' . get_current_user_id();
	}
}
