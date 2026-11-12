<?php

namespace Kiscore {
	// WordPress functions the sanitize callback calls, resolved in this
	// namespace first. Only loaded in the separate process of this test.
	if ( ! function_exists( __NAMESPACE__ . '\get_option' ) ) {
		function get_option( $name, $fallback = false ) {
			return $GLOBALS['kiscore_test_options'][ $name ] ?? $fallback;
		}
		function sanitize_text_field( $value ) {
			return trim( (string) $value );
		}
		function add_settings_error() {
			$GLOBALS['kiscore_test_errors'][] = func_get_args();
		}
		function __( $text ) {
			return $text;
		}
	}
}

namespace {

	use Kiscore\Settings;
	use PHPUnit\Framework\TestCase;

	/**
	 * @runTestsInSeparateProcesses
	 * @preserveGlobalState disabled
	 */
	final class SettingsFirstSaveTest extends TestCase {

		protected function setUp(): void {
			$GLOBALS['kiscore_test_options'] = array();
			$GLOBALS['kiscore_test_errors']  = array();
		}

		/**
		 * On a fresh install the option does not exist yet. update_option()
		 * sanitises the form value and hands the result to add_option(), which
		 * sanitises it again: the second pass gets site ID and secret, no
		 * connection key. It used to answer with the still empty stored value,
		 * so the first save stored nothing while WordPress said "saved".
		 */
		public function test_the_first_save_survives_wordpress_sanitising_twice(): void {
			$first = Settings::sanitize( array( 'connection_key' => ' site-1.secret_2 ' ) );
			$this->assertSame(
				array(
					'site_id'         => 'site-1',
					'tracking_secret' => 'secret_2',
				),
				$first
			);

			$this->assertSame( $first, Settings::sanitize( $first ) );
			$this->assertSame( array(), $GLOBALS['kiscore_test_errors'] );
		}

		public function test_an_empty_field_keeps_the_stored_connection(): void {
			$stored                          = array(
				'site_id'         => 'old-site',
				'tracking_secret' => 'old-secret',
			);
			$GLOBALS['kiscore_test_options'] = array( Settings::OPTION => $stored );

			$this->assertSame( $stored, Settings::sanitize( array( 'connection_key' => '' ) ) );
			$this->assertSame( $stored, Settings::sanitize( 'not an array' ) );
		}

		public function test_parts_that_cannot_come_from_a_connection_key_are_not_stored(): void {
			$kept = array(
				'site_id'         => '',
				'tracking_secret' => '',
			);

			$this->assertSame( $kept, Settings::sanitize( array( 'site_id' => 'site 1', 'tracking_secret' => 'x' ) ) );
			$this->assertSame( $kept, Settings::sanitize( array( 'site_id' => 'site-1', 'tracking_secret' => array( 'x' ) ) ) );
			$this->assertSame( $kept, Settings::sanitize( array( 'site_id' => 'site-1' ) ) );
		}
	}
}
