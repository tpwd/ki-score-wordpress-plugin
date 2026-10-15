<?php
/**
 * Decides whether a request is a page view and builds what is sent to KIscore.
 *
 * @package Kiscore
 */

namespace Kiscore;

defined( 'ABSPATH' ) || exit;

/**
 * Works on $_SERVER-style arrays only, without WordPress functions.
 */
final class Page_View {

	/**
	 * Byte limits of the KIscore tracking API. Longer values are rejected with 413.
	 */
	const URL_BYTES        = 2048;
	const USER_AGENT_BYTES = 1024;
	const REFERER_BYTES    = 2048;

	const STATIC_FILE = '/\.(?:ico|png|jpe?g|webp|avif|gif|svg|css|js|mjs|map|json|webmanifest|woff2?|ttf|otf|eot|pdf|xml|txt|mp4|webm|mp3)$/i';

	/**
	 * Body of the tracking request, or null when the request is no full page
	 * view or carries no usable visitor IP.
	 *
	 * @param array  $server    $_SERVER or an equivalent array.
	 * @param string $ip_header Header with the visitor IP, e.g. "X-Real-IP". Empty: REMOTE_ADDR.
	 * @return array{url: string, user_agent: string, referer: string, ip: string}|null
	 */
	public static function payload( array $server, string $ip_header = '' ): ?array {
		if ( ! self::is_page_view( $server ) ) {
			return null;
		}

		$url = self::tracked_url( (string) ( $server['REQUEST_URI'] ?? '' ) );
		$ip  = self::client_ip( $server, $ip_header );
		if ( null === $url || null === $ip ) {
			return null;
		}

		return array(
			'url'        => $url,
			'user_agent' => self::truncate( (string) ( $server['HTTP_USER_AGENT'] ?? '' ), self::USER_AGENT_BYTES ),
			'referer'    => self::truncate( (string) ( $server['HTTP_REFERER'] ?? '' ), self::REFERER_BYTES ),
			'ip'         => $ip,
		);
	}

	/**
	 * A GET for an HTML document. Fetches, prefetches and prerenders are not
	 * page views: browsers mark them with Sec-Fetch-Mode and Sec-Purpose.
	 *
	 * @param array $server $_SERVER or an equivalent array.
	 */
	public static function is_page_view( array $server ): bool {
		if ( 'GET' !== ( $server['REQUEST_METHOD'] ?? '' ) ) {
			return false;
		}

		$fetch_mode = (string) ( $server['HTTP_SEC_FETCH_MODE'] ?? '' );
		if ( '' !== $fetch_mode && 'navigate' !== $fetch_mode ) {
			return false;
		}
		if ( false !== stripos( (string) ( $server['HTTP_SEC_PURPOSE'] ?? '' ), 'prefetch' )
			|| 'prefetch' === ( $server['HTTP_PURPOSE'] ?? '' ) ) {
			return false;
		}

		$accept = (string) ( $server['HTTP_ACCEPT'] ?? '' );
		return '' === $accept || false !== strpos( $accept, 'text/html' ) || false !== strpos( $accept, '*/*' );
	}

	/**
	 * Path plus utm_* parameters in their original encoding. KIscore
	 * recognises AI referrals by utm_source; other query parameters can carry
	 * tokens or email addresses and stay on this server.
	 *
	 * @param string $request_uri REQUEST_URI of the request.
	 */
	public static function tracked_url( string $request_uri ): ?string {
		if ( '' === $request_uri || '/' !== $request_uri[0] || 0 === strpos( $request_uri, '//' ) ) {
			return null;
		}

		$parts = explode( '?', $request_uri, 2 );
		$path  = $parts[0];
		if ( preg_match( self::STATIC_FILE, $path ) ) {
			return null;
		}

		$utm = array();
		foreach ( explode( '&', $parts[1] ?? '' ) as $pair ) {
			$key = urldecode( explode( '=', $pair, 2 )[0] );
			if ( 0 === stripos( $key, 'utm_' ) ) {
				$utm[] = $pair;
			}
		}

		$url = self::truncate( $utm ? $path . '?' . implode( '&', $utm ) : $path, self::URL_BYTES );
		// Never end on a cut percent escape such as "%C3%A".
		return preg_replace( '/%[0-9a-f]?$/i', '', $url );
	}

	/**
	 * A header name as HTTP spells it. Hosting docs often give the $_SERVER
	 * key instead ("HTTP_X_REAL_IP"), which names the same header.
	 *
	 * @param string $name "X-Real-IP", "HTTP_X_REAL_IP" or "X_Real_IP".
	 */
	public static function header_name( string $name ): string {
		$name = str_replace( '_', '-', (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $name ) );
		return (string) preg_replace( '/^HTTP-/i', '', $name );
	}

	/**
	 * The visitor IP from REMOTE_ADDR or from a header that the hosting proxy
	 * overwrites. Lists and anything but a single IP are rejected.
	 *
	 * @param array  $server    $_SERVER or an equivalent array.
	 * @param string $ip_header Header name, e.g. "X-Real-IP". Empty: REMOTE_ADDR.
	 */
	public static function client_ip( array $server, string $ip_header ): ?string {
		$header = self::header_name( $ip_header );
		$key    = '' === $header ? 'REMOTE_ADDR' : 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) );
		$value  = trim( (string) ( $server[ $key ] ?? '' ) );

		return false === filter_var( $value, FILTER_VALIDATE_IP ) ? null : $value;
	}

	/**
	 * Cuts a string to a byte length without splitting a UTF-8 character.
	 *
	 * @param string $value     Any string.
	 * @param int    $max_bytes Maximum length in bytes.
	 */
	public static function truncate( string $value, int $max_bytes ): string {
		if ( strlen( $value ) <= $max_bytes ) {
			return $value;
		}

		$end = $max_bytes;
		while ( $end > 0 && ( ord( $value[ $end ] ) & 0xC0 ) === 0x80 ) {
			--$end;
		}
		return substr( $value, 0, $end );
	}
}
