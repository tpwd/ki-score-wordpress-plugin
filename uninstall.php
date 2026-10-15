<?php
/**
 * Removes the stored settings when the plugin is deleted. On a multisite
 * network every site keeps its own options, the tracking secret included.
 *
 * @package Kiscore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$kiscore_delete_options = static function () {
	delete_option( 'kiscore_settings' );
	delete_option( 'kiscore_last_delivery' );
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $kiscore_site_id ) {
		switch_to_blog( $kiscore_site_id );
		$kiscore_delete_options();
		restore_current_blog();
	}
} else {
	$kiscore_delete_options();
}
