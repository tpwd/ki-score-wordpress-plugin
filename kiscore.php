<?php
/**
 * Plugin Name:       KIscore
 * Plugin URI:        https://kiscore.ai
 * Description:       Shows in KIscore which AI crawlers visit your site and which visitors come from ChatGPT, Perplexity and other AI assistants. Server-side, without script or cookie.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            KIscore
 * Author URI:        https://kiscore.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kiscore
 * Domain Path:       /languages
 *
 * @package Kiscore
 */

defined( 'ABSPATH' ) || exit;

define( 'KISCORE_VERSION', '1.1.1' );
define( 'KISCORE_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-page-view.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-tracker.php';
require_once __DIR__ . '/includes/class-admin.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'kiscore', false, dirname( plugin_basename( KISCORE_FILE ) ) . '/languages' );
	}
);

Kiscore\Tracker::register();
if ( is_admin() ) {
	Kiscore\Admin::register();
}
