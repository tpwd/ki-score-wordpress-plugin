<?php
/**
 * Connection key and visitor IP header.
 *
 * @package Kiscore
 */

namespace Kiscore;

defined( 'ABSPATH' ) || exit;

/**
 * Site ID and tracking secret come from one connection key "<site-id>.<secret>",
 * set in wp-config.php, as environment variable or on the settings page, in
 * that order. The option keeps both parts apart, as plugin versions before
 * 1.1 stored them.
 */
final class Settings {

	const OPTION = 'kiscore_settings';

	const DEFAULT_API_ENDPOINT = 'https://api.kiscore.ai/track';

	/**
	 * Constant and environment variable with the whole connection key.
	 */
	const CONNECTION_KEY = 'KISCORE_CONNECTION_KEY';

	/**
	 * Setting => name of its own constant and environment variable.
	 */
	const FIELDS = array(
		'site_id'          => 'KISCORE_SITE_ID',
		'tracking_secret'  => 'KISCORE_TRACKING_SECRET',
		'client_ip_header' => 'KISCORE_CLIENT_IP_HEADER',
	);

	/**
	 * Current value of a setting, '' when unset. The visitor IP header has no
	 * field on the settings page and comes from wp-config.php or the environment only.
	 *
	 * @param string $field Key of self::FIELDS.
	 */
	public static function get( string $field ): string {
		$name = self::external_name( $field );
		if ( self::CONNECTION_KEY === $name ) {
			return self::parse_connection_key( (string) self::external( $name ) )[ $field ];
		}
		if ( null !== $name ) {
			return (string) self::external( $name );
		}
		if ( 'client_ip_header' === $field ) {
			return '';
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
		$name = self::external_name( $field );
		if ( null !== $name ) {
			return defined( $name ) && '' !== trim( (string) constant( $name ) ) ? 'constant' : 'environment';
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
	 * Splits a connection key as the KIscore dashboard shows it.
	 *
	 * @param string $key "<site-id>.<secret>".
	 * @return array{site_id: string, tracking_secret: string}|null Null for anything else.
	 */
	public static function parse_connection_key( string $key ): ?array {
		if ( ! preg_match( '/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', trim( $key ), $parts ) ) {
			return null;
		}
		return array(
			'site_id'         => $parts[1],
			'tracking_secret' => $parts[2],
		);
	}

	/**
	 * Sanitize callback of the settings page. The page never prints the key,
	 * so an empty field keeps the stored connection, and so does a key that
	 * cannot be read.
	 *
	 * @param mixed $input Submitted values.
	 */
	public static function sanitize( $input ): array {
		$stored = get_option( self::OPTION, array() );
		$stored = array(
			'site_id'         => is_array( $stored ) ? (string) ( $stored['site_id'] ?? '' ) : '',
			'tracking_secret' => is_array( $stored ) ? (string) ( $stored['tracking_secret'] ?? '' ) : '',
		);

		$key = is_array( $input ) ? trim( sanitize_text_field( (string) ( $input['connection_key'] ?? '' ) ) ) : '';
		if ( '' === $key ) {
			return $stored;
		}

		$parsed = self::parse_connection_key( $key );
		if ( null === $parsed ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( self::OPTION, 'kiscore_connection_key', __( 'This is not a connection key. Copy it again from the KIscore dashboard; it has the form site-id.secret.', 'kiscore' ) );
			}
			return $stored;
		}
		return $parsed;
	}

	/**
	 * Constant or environment variable that sets a field, null when none does.
	 * A connection key there wins over the separate site ID and secret.
	 *
	 * @param string $field Key of self::FIELDS.
	 */
	private static function external_name( string $field ): ?string {
		if ( 'client_ip_header' !== $field
			&& null !== self::parse_connection_key( (string) self::external( self::CONNECTION_KEY ) ) ) {
			return self::CONNECTION_KEY;
		}
		return null === self::external( self::FIELDS[ $field ] ) ? null : self::FIELDS[ $field ];
	}

	/**
	 * Value of a constant or environment variable, null when neither is set.
	 * An empty value counts as unset: an empty constant leaves the value to
	 * the environment variable, and without either the settings page applies.
	 *
	 * @param string $name Constant and environment variable name.
	 */
	private static function external( string $name ): ?string {
		$value = defined( $name ) ? trim( (string) constant( $name ) ) : '';
		if ( '' === $value ) {
			$value = trim( (string) getenv( $name ) );
		}
		return '' === $value ? null : $value;
	}
}
