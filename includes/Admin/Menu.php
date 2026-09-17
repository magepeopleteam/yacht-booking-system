<?php
namespace MageYaBo\Admin;

use MageYaBo\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One top-level admin page hosting the whole React SPA. Visually mirrors the
 * sibling "easy-shuttle" plugin's admin shell (dark rail, sticky topbar) -
 * here that chrome is rendered by React (assets/admin/src/components/Shell.js)
 * rather than printed by PHP, since the whole admin is a single page app.
 */
class Menu {

	const PAGE_SLUG = 'magepeople-yacht-booking-system';
	const HOOK      = 'toplevel_page_' . self::PAGE_SLUG;

	public static function register() {
		$hook = add_menu_page(
			__( 'Yacht Booking', 'magepeople-yacht-booking-system' ),
			__( 'Yacht Booking', 'magepeople-yacht-booking-system' ),
			Capabilities::CAP_BOOKINGS,
			self::PAGE_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-palmtree',
			26
		);

		add_action( 'load-' . $hook, array( __CLASS__, 'on_load' ) );

		/**
		 * Fires once the top-level page is registered, so add-ons can hook
		 * their own `admin_menu` registration after this one.
		 */
		do_action( 'mageyabo_admin_menu_registered', $hook );
	}

	public static function on_load() {
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function body_class( $classes ) {
		return $classes . ' ybs-shell ';
	}

	public static function enqueue() {
		$asset_file = MAGEYABO_PLUGIN_DIR . 'assets/build/admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_build_notice' ) );
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style( 'mageyabo-leaflet', MAGEYABO_PLUGIN_URL . 'assets/frontend/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_enqueue_script( 'mageyabo-leaflet', MAGEYABO_PLUGIN_URL . 'assets/frontend/vendor/leaflet/leaflet.js', array(), '1.9.4', true );

		// The classic (TinyMCE) editor for the Description field, and the
		// media library modal for the featured image / gallery pickers.
		wp_enqueue_media();
		wp_enqueue_editor();

		wp_enqueue_script(
			'mageyabo-admin',
			MAGEYABO_PLUGIN_URL . 'assets/build/admin.js',
			array_merge( $asset['dependencies'], array( 'mageyabo-leaflet', 'media-editor', 'wp-editor' ) ),
			$asset['version'],
			true
		);

		// Without this the admin app's @wordpress/i18n strings stay English no
		// matter what translations are installed.
		wp_set_script_translations( 'mageyabo-admin', 'magepeople-yacht-booking-system', MAGEYABO_PLUGIN_DIR . 'languages' );

		wp_enqueue_style(
			'mageyabo-admin',
			MAGEYABO_PLUGIN_URL . 'assets/build/style-admin.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_add_inline_script(
			'wp-api-fetch',
			sprintf(
				'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( %s ) ); wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( %s ) );',
				wp_json_encode( wp_create_nonce( 'wp_rest' ) ),
				wp_json_encode( esc_url_raw( rest_url() ) )
			),
			'after'
		);

		/**
		 * Lets add-ons register extra nav entries in the rail. The matching
		 * React component is supplied separately by the add-on's script calling
		 * `window.mageyaboAdmin.registerRoute( id, Component )`.
		 */
		$extra_routes = apply_filters( 'mageyabo_admin_react_routes', array() );

		wp_localize_script(
			'mageyabo-admin',
			'mageyaboAdminConfig',
			array(
				'adminUrl'    => admin_url(),
				'pluginUrl'   => MAGEYABO_PLUGIN_URL,
				'extraRoutes' => array_values( $extra_routes ),
				'currency'    => \MageYaBo\Settings::get( 'currency_symbol', '$' ),
				'adminEmail'  => get_option( 'admin_email' ),
				// When WooCommerce checkout is live it handles the whole
				// payment step, discounts included, so this plugin's own
				// coupon codes never come into play. The Coupons screen says
				// so rather than letting an operator build codes that quietly
				// do nothing.
				'wooCheckout' => \MageYaBo\Payments\WooCommerceGateway::is_active(),
			)
		);
	}

	public static function missing_build_notice() {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'Yacht Booking System: run "npm install && npm run build" in the plugin directory to compile the admin app.', 'magepeople-yacht-booking-system' ) .
			'</p></div>';
	}

	public static function render() {
		echo '<div id="ybs-admin-root"></div>';
	}
}
