<?php
/**
 * Plugin Name: MagePeople Yacht Booking System
 * Plugin URI: https://wordpress.org/plugins/magepeople-yacht-booking-system
 * Description: Yacht and boat charter booking - yacht management, a full booking engine, and built-in payments (Offline, PayPal, Stripe, WooCommerce).
 * Version: 1.2.0
 * Requires at least: 5.9
 * Requires PHP: 8.0
 * Author: MagePeople
 * Author URI: https://magepeople.com/
 * Text Domain: magepeople-yacht-booking-system
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package magepeople-yacht-booking-system
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAGEYABO_VERSION', '1.2.0' );
define( 'MAGEYABO_DB_VERSION', '5' );
define( 'MAGEYABO_PLUGIN_FILE', __FILE__ );
define( 'MAGEYABO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAGEYABO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MAGEYABO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$mageyabo_autoload = MAGEYABO_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $mageyabo_autoload ) ) {
	require_once $mageyabo_autoload;
} else {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Yacht Booking System: the Composer autoloader is missing. Run "composer install" (or "composer dump-autoload") in the plugin directory.', 'magepeople-yacht-booking-system' ) .
				'</p></div>';
		}
	);
	return;
}

register_activation_hook( __FILE__, array( '\MageYaBo\Install\Migrator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\MageYaBo\Install\Migrator', 'deactivate' ) );

\MageYaBo\Plugin::instance()->boot();
