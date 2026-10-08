<?php
/**
 * Removes the stored settings when the plugin is deleted.
 *
 * @package Kiscore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'kiscore_settings' );
delete_option( 'kiscore_last_delivery' );
