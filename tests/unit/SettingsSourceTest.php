<?php

use Kiscore\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsSourceTest extends TestCase {

	/**
	 * An empty constant counts as unset, so an environment variable of the
	 * same name must still apply and be named as the source.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_an_empty_constant_leaves_the_value_to_the_environment(): void {
		define( 'KISCORE_SITE_ID', '' );
		putenv( 'KISCORE_SITE_ID=env-site' );

		$this->assertSame( 'env-site', Settings::get( 'site_id' ) );
		$this->assertSame( 'environment', Settings::source( 'site_id' ) );
	}
}
