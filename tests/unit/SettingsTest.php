<?php

use Kiscore\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	public function test_reads_site_id_and_secret_from_one_connection_key(): void {
		$this->assertSame(
			array(
				'site_id'         => '622e7ea0728b43cc2a87a4b793c1ac28',
				'tracking_secret' => 'a1b2c3',
			),
			Settings::parse_connection_key( " 622e7ea0728b43cc2a87a4b793c1ac28.a1b2c3 \n" )
		);
	}

	public function test_rejects_anything_but_site_id_dot_secret(): void {
		foreach ( array( '', 'only-site', '.secret', 'site.', 'site.sec.ret', 'site id.secret' ) as $key ) {
			$this->assertNull( Settings::parse_connection_key( $key ), $key );
		}
	}
}
