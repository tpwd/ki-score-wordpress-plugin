<?php
/**
 * Site ID, tracking secret and visitor IP header.
 *
 * @package Kiscore
 */

namespace Kiscore;

defined( 'ABSPATH' ) || exit;

/**
 * Each value comes from a constant in wp-config.php, an environment variable
 * or the settings page, in that order.
 */
final class Settings {

	const OPTION = 'kiscore_settings';

	const DEFAULT_API_ENDPOINT = 'https://api.kiscore.ai/track';

	/**
	 * Setting => name of the constant and environment variable.
	 */
	const FIELDS = array(
		'site_id'          => 'KISCORE_SITE_ID',
		'tracking_secret'  => 'KISCORE_TRACKING_SECRET',
		'client_ip_header' => 'KISCORE_CLIENT_IP_HEADER',
	);

	/**
	 * Current value of a setting, '' when unset.
	 *
	 * @param string $field Key of self::FIELDS.
	 */
	public static function get( string $field ): string {
		$external = self::external( self::FIELDS[ $field ] );
		if ( null !== $external ) {
			return $external;
		}

		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? trim( (string) ( $stored[ $field ] ?? '' ) ) : '';
	}

	/**
	 * Where a setting comes from: 'constant', 'environment', 'option' or '' when unset.
	 *
	 * @param string $field Key of self::FIELDS.
	 */
	public static function source( string $field ): string {
		$name = self::FIELDS[ $field ];
		if ( null !== self::external( $name ) ) {
			return defined( $name ) ? 'constant' : 'environment';
		}
		return '' === self::get( $field ) ? '' : 'option';
	}

	/**
	 * Whether site ID and tracking secret are set. Without both the API rejects every report.
	 */
	public static function is_configured(): bool {
		return '' !== self::get( 'site_id' ) && '' !== self::get( 'tracking_secret' );
	}

	/**
	 * Tracking URL of this site. KISCORE_API_ENDPOINT overrides the API, e.g. for tests.
	 */
	public static function tracking_url(): string {
		$endpoint = self::external( 'KISCORE_API_ENDPOINT' ) ?? self::DEFAULT_API_ENDPOINT;
		return rtrim( $endpoint, '/' ) . '/' . rawurlencode( self::get( 'site_id' ) );
	}

	/**
	 * Sanitize callback of the settings page. An empty secret keeps the stored
	 * one, because the page never prints it.
	 *
	 * @param mixed $input Submitted values.
	 */
	public static function sanitize( $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$secret = trim( sanitize_text_field( (string) ( $input['tracking_secret'] ?? '' ) ) );

		return array(
			'site_id'          => trim( sanitize_text_field( (string) ( $input['site_id'] ?? '' ) ) ),
			'tracking_secret'  => '' === $secret ? (string) ( $stored['tracking_secret'] ?? '' ) : $secret,
			'client_ip_header' => Page_View::header_name( (string) ( $input['client_ip_header'] ?? '' ) ),
		);
	}

	/**
	 * Value of a constant or environment variable, null when neither is set.
	 * An empty value counts as unset, so the settings page stays editable.
	 *
	 * @param string $name Constant and environment variable name.
	 */
	private static function external( string $name ): ?string {
		$value = defined( $name ) ? (string) constant( $name ) : getenv( $name );
		return false === $value || '' === trim( $value ) ? null : trim( $value );
	}
}
