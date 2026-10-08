<?php
/**
 * Reports page views to KIscore.
 *
 * @package Kiscore
 */

namespace Kiscore;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the frontend and sends each page view after the response.
 */
final class Tracker {

	const LAST_DELIVERY_OPTION = 'kiscore_last_delivery';

	/**
	 * Hooks the tracking into the frontend.
	 */
	public static function register(): void {
		// After redirect_canonical (priority 10): redirected requests never get here.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_track' ), 20 );
	}

	/**
	 * Schedules the report of the current request if it is a page view.
	 */
	public static function maybe_track(): void {
		if ( ! self::is_frontend_page() || ! Settings::is_configured() ) {
			return;
		}

		$payload = Page_View::payload( wp_unslash( $_SERVER ), Settings::get( 'client_ip_header' ) );
		if ( null === $payload ) {
			return;
		}

		add_action(
			'shutdown',
			static function () use ( $payload ) {
				self::send_after_response( $payload );
			},
			PHP_INT_MAX
		);
	}

	/**
	 * Sends a request without visitor data. KIscore answers 200 when the site
	 * ID and secret match and stores nothing, because it is neither a crawler
	 * nor an AI referral.
	 *
	 * @return array{ok: bool, status: int, error: string}
	 */
	public static function check_connection(): array {
		$response = self::post(
			array(
				'url'        => '/',
				'user_agent' => 'KIscore-WordPress-Connection-Check/' . KISCORE_VERSION,
				'referer'    => '',
			),
			5
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'status' => 0,
				'error'  => $response->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		return array(
			'ok'     => 200 === $status,
			'status' => $status,
			'error'  => '',
		);
	}

	/**
	 * Frontend pages only: no admin, AJAX, cron, REST, XML-RPC, feeds,
	 * robots.txt, favicon, trackbacks, previews or embeds.
	 */
	private static function is_frontend_page(): bool {
		return ! is_admin()
			&& ! wp_doing_ajax()
			&& ! wp_doing_cron()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
			&& ! is_feed()
			&& ! is_robots()
			&& ! is_favicon()
			&& ! is_trackback()
			&& ! is_preview()
			&& ! is_customize_preview()
			&& ! is_embed();
	}

	/**
	 * On PHP-FPM and LiteSpeed the visitor gets the page first and the request
	 * runs afterwards. Elsewhere it runs before PHP ends, with a shorter timeout.
	 *
	 * @param array $payload Page view from Page_View::payload().
	 */
	private static function send_after_response( array $payload ): void {
		$status = http_response_code();
		if ( $status >= 300 && $status < 400 ) {
			return;
		}

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			$finished = fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			$finished = litespeed_finish_request();
		} else {
			$finished = false;
		}

		$response = self::post( $payload, $finished ? 2 : 1 );
		self::remember_delivery( is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * Posts to the tracking API of this site.
	 *
	 * @param array $payload Request body.
	 * @param float $timeout Seconds.
	 * @return array|\WP_Error
	 */
	private static function post( array $payload, float $timeout ) {
		return wp_remote_post(
			Settings::tracking_url(),
			array(
				'headers'     => array(
					'Content-Type'              => 'application/json',
					'X-Kiscore-Tracking-Secret' => Settings::get( 'tracking_secret' ),
				),
				'body'        => wp_json_encode( $payload ),
				'timeout'     => $timeout,
				// A redirect would forward the secret to another host.
				'redirection' => 0,
				'user-agent'  => 'KIscore-WordPress/' . KISCORE_VERSION,
			)
		);
	}

	/**
	 * Keeps the last API answer for the settings page. Written when the answer
	 * changes and at most hourly otherwise, not on every page view.
	 *
	 * @param int $status HTTP status, 0 when KIscore was unreachable.
	 */
	private static function remember_delivery( int $status ): void {
		$last = get_option( self::LAST_DELIVERY_OPTION );
		if ( is_array( $last ) && ( $last['status'] ?? null ) === $status && time() - (int) ( $last['time'] ?? 0 ) < HOUR_IN_SECONDS ) {
			return;
		}
		update_option(
			self::LAST_DELIVERY_OPTION,
			array(
				'status' => $status,
				'time'   => time(),
			)
		);
	}
}
