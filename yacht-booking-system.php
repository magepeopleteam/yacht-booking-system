<?php
/**
 * Plugin Name: Yacht Booking System
 * Plugin URI: https://magepeople.com/
 * Description: Yacht and boat charter booking - yacht management, a full booking engine, and built-in payments (Offline, PayPal, Stripe, WooCommerce).
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Author: MagePeople
 * Text Domain: yacht-booking-system
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YBS_VERSION', '1.0.0' );
define( 'YBS_DB_VERSION', '2' );
define( 'YBS_PLUGIN_FILE', __FILE__ );
define( 'YBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YBS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$ybs_autoload = YBS_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $ybs_autoload ) ) {
	require_once $ybs_autoload;
} else {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Yacht Booking System: the Composer autoloader is missing. Run "composer install" (or "composer dump-autoload") in the plugin directory.', 'yacht-booking-system' ) .
				'</p></div>';
		}
	);
	return;
}

register_activation_hook( __FILE__, array( '\Ybs\Install\Migrator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\Ybs\Install\Migrator', 'deactivate' ) );

\Ybs\Plugin::instance()->boot();
