<?php
namespace Ybs\Rest;

use Ybs\Notifications\BookingEmailer;
use Ybs\Settings;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsController extends Controller {

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'index' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/settings/email/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'test_email' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/settings/woocommerce/install',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'install_woocommerce' ),
				'permission_callback' => array( __CLASS__, 'can_manage_payments' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/settings/woocommerce/gateways',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_woocommerce_gateways' ),
				'permission_callback' => array( __CLASS__, 'can_manage_payments' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/settings/woocommerce/gateways/(?P<id>[\w-]+)/toggle',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'toggle_woocommerce_gateway' ),
				'permission_callback' => array( __CLASS__, 'can_manage_payments' ),
			)
		);
	}

	public static function index() {
		$settings = Settings::all();

		// Secrets go out masked - the Settings screen only ever needs to know one is set.
		foreach ( array( 'paypal_secret', 'stripe_secret_key', 'stripe_webhook_secret' ) as $secret_key ) {
			$settings[ $secret_key . '_set' ] = ! empty( $settings[ $secret_key ] );
			unset( $settings[ $secret_key ] );
		}

		$settings['woocommerce_active'] = class_exists( 'WooCommerce' );

		return rest_ensure_response( $settings );
	}

	public static function update( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		// Never overwrite a stored secret with an empty string sent because the field was left masked.
		foreach ( array( 'paypal_secret', 'stripe_secret_key', 'stripe_webhook_secret' ) as $secret_key ) {
			if ( array_key_exists( $secret_key, $data ) && '' === $data[ $secret_key ] ) {
				unset( $data[ $secret_key ] );
			}
		}

		return rest_ensure_response( Settings::update( (array) $data ) );
	}

	/**
	 * Sends a one-off preview using whatever subject/body the client's form
	 * currently holds - possibly unsaved - rather than only what's already
	 * in the database, so an admin can proof-read an edit before saving it.
	 * Falls back to the saved global template for any field left blank.
	 */
	public static function test_email( WP_REST_Request $request ) {
		$to = sanitize_email( (string) $request->get_param( 'to' ) );

		if ( ! is_email( $to ) ) {
			return new WP_Error( 'ybs_invalid_email', __( 'Please enter a valid email address.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		// Restricted to addresses that already belong to this site, so the
		// settings capability can't be used to send arbitrary HTML to any
		// address on the internet from the site's own mail server.
		$allowed = array_filter( array( get_option( 'admin_email' ), wp_get_current_user()->user_email ) );

		if ( ! in_array( strtolower( $to ), array_map( 'strtolower', $allowed ), true ) ) {
			return new WP_Error(
				'ybs_email_not_allowed',
				__( 'Test emails can only be sent to your own account address or the site admin address.', 'magepeople-yacht-booking-system' ),
				array( 'status' => 403 )
			);
		}

		$subject      = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$body         = wp_kses_post( (string) $request->get_param( 'body' ) );
		$from_name    = sanitize_text_field( (string) $request->get_param( 'from_name' ) );
		$from_address = sanitize_email( (string) $request->get_param( 'from_email' ) );

		if ( '' === $subject ) {
			$subject = Settings::get( 'email_subject', '' );
		}

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			$body = Settings::get( 'email_body', '' );
		}

		if ( '' === $from_name ) {
			$from_name = Settings::get( 'email_from_name', '' ) ?: get_bloginfo( 'name' );
		}

		if ( ! is_email( $from_address ) ) {
			$from_address = Settings::get( 'email_from_address', '' ) ?: get_option( 'admin_email' );
		}

		$tags = BookingEmailer::sample_tags( $to );

		$subject = '[' . __( 'TEST', 'magepeople-yacht-booking-system' ) . '] ' . strtr( $subject, $tags );
		$body    = strtr( $body, $tags );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		);

		$sent = wp_mail( $to, $subject, wpautop( $body ), $headers );

		if ( ! $sent ) {
			return new WP_Error( 'ybs_test_email_failed', __( 'Failed to send test email. Check your mail configuration.', 'magepeople-yacht-booking-system' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'sent'    => true,
				/* translators: %s: email address the test was sent to. */
				'message' => sprintf( __( 'Test email sent to %s.', 'magepeople-yacht-booking-system' ), $to ),
			)
		);
	}

	/**
	 * Installs (if needed) and activates WooCommerce, then turns on its
	 * built-in Cash on Delivery and Direct Bank Transfer gateways so a
	 * freshly-enabled WooCommerce mode already has *something* usable
	 * rather than the "no payment method enabled" state WooCommerce and
	 * this plugin both ship with by default.
	 */
	public static function install_woocommerce() {
		if ( class_exists( 'WooCommerce' ) ) {
			return self::finish_woocommerce_setup( true );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_file = 'woocommerce/woocommerce.php';

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return new WP_Error( 'ybs_cannot_install', __( 'You do not have permission to install plugins.', 'magepeople-yacht-booking-system' ), array( 'status' => 403 ) );
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => 'woocommerce',
					'fields' => array( 'sections' => false ),
				)
			);

			if ( is_wp_error( $api ) ) {
				return new WP_Error( 'ybs_wc_lookup_failed', $api->get_error_message() );
			}

			$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->install( $api->download_link );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! $result ) {
				return new WP_Error( 'ybs_wc_install_failed', __( 'Could not download or extract WooCommerce.', 'magepeople-yacht-booking-system' ) );
			}
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'ybs_cannot_activate', __( 'You do not have permission to activate plugins.', 'magepeople-yacht-booking-system' ), array( 'status' => 403 ) );
		}

		$activated = self::activate_without_loading( $plugin_file );

		if ( is_wp_error( $activated ) ) {
			return new WP_Error( 'ybs_cannot_activate', __( 'WooCommerce could not be activated.', 'magepeople-yacht-booking-system' ), array( 'status' => 500 ) );
		}

		return self::finish_woocommerce_setup( true );
	}

	/**
	 * Marks a plugin active the same way `activate_plugin()` does, but
	 * without `include`-ing its main file into this process.
	 *
	 * Some other active plugin (e.g. one that integrates with WooCommerce
	 * when present) may define its own fallback `WC()` - and every other
	 * WooCommerce global helper - for when WooCommerce isn't installed, so
	 * its own code has something to call. That's harmless on its own: those
	 * plugins correctly skip defining the fallbacks once WooCommerce is
	 * genuinely active, because that check runs on the *next* full page
	 * load, before their fallback-defining code even gets a chance to run.
	 * It only becomes a real "Cannot redeclare WC()" fatal if WooCommerce's
	 * real, unguarded declarations get `include`d into a process that
	 * already ran this request's `plugins_loaded` with WooCommerce still
	 * inactive - i.e. exactly what `activate_plugin()` would do if we called
	 * it directly from this REST request. Flipping the `active_plugins`
	 * option ourselves sidesteps that entirely: WooCommerce loads cleanly,
	 * fallback-free, starting from the very next request.
	 */
	private static function activate_without_loading( $plugin_file ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// The one check `activate_plugin()` does that matters here: never write
		// a path into `active_plugins` that isn't a real, readable plugin.
		$valid = validate_plugin( $plugin_file );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( in_array( $plugin_file, $active_plugins, true ) ) {
			return;
		}

		$active_plugins[] = $plugin_file;
		update_option( 'active_plugins', $active_plugins );

		do_action( "activate_{$plugin_file}", false );
		do_action( 'activated_plugin', $plugin_file, false );
	}

	/**
	 * @param bool $just_activated True when this call just flipped
	 *   WooCommerce active in the database in *this* request - `class_exists`
	 *   still reports false until the next request loads it for real, so the
	 *   response would otherwise wrongly claim WooCommerce isn't active yet.
	 */
	private static function finish_woocommerce_setup( $just_activated = false ) {
		// Only Cash on Delivery comes pre-enabled - everything else (Bank
		// Transfer, PayPal, Stripe, ...) the admin turns on deliberately from
		// the gateway list below.
		self::set_gateway_enabled( 'cod', true );

		$settings                       = Settings::update( array( 'woocommerce_enabled' => true ) );
		$settings['woocommerce_active'] = $just_activated || class_exists( 'WooCommerce' );

		foreach ( array( 'paypal_secret', 'stripe_secret_key', 'stripe_webhook_secret' ) as $secret_key ) {
			$settings[ $secret_key . '_set' ] = ! empty( $settings[ $secret_key ] );
			unset( $settings[ $secret_key ] );
		}

		return rest_ensure_response( $settings );
	}

	/**
	 * Every gateway WooCommerce (or a WooCommerce extension) has registered,
	 * regardless of enabled state - the same list `WC_Payment_Gateways`
	 * shows on its own Settings > Payments screen, so a fresh WooCommerce
	 * install already shows COD/BACS/etc. here without needing this
	 * plugin to know their ids in advance.
	 */
	public static function list_woocommerce_gateways() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return new WP_Error( 'ybs_woocommerce_inactive', __( 'WooCommerce is not active.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$gateways = array();

		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			// `$gateway->enabled` was cached in memory when WooCommerce
			// constructed this object earlier in the request (or an earlier
			// request under a persistent cache) - if we just wrote a new
			// `enabled` value to its settings option ourselves, that stale
			// property wouldn't reflect it yet. The option itself is the
			// source of truth WooCommerce's own settings screen reads too.
			$stored = get_option( "woocommerce_{$gateway->id}_settings", array() );
			$enabled = is_array( $stored ) && array_key_exists( 'enabled', $stored )
				? 'yes' === $stored['enabled']
				: 'yes' === $gateway->enabled;

			$gateways[] = array(
				'id'          => $gateway->id,
				'title'       => wp_strip_all_tags( $gateway->get_method_title() ?: $gateway->title ),
				'description' => wp_strip_all_tags( $gateway->get_method_description() ?? '' ),
				'enabled'     => $enabled,
			);
		}

		return rest_ensure_response( $gateways );
	}

	public static function toggle_woocommerce_gateway( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return new WP_Error( 'ybs_woocommerce_inactive', __( 'WooCommerce is not active.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$gateway_id = sanitize_key( $request['id'] );
		$gateways   = WC()->payment_gateways()->payment_gateways();

		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return new WP_Error( 'ybs_unknown_gateway', __( 'Unknown WooCommerce payment gateway.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		self::set_gateway_enabled( $gateway_id, (bool) $request->get_param( 'enabled' ) );

		return self::list_woocommerce_gateways();
	}

	private static function set_gateway_enabled( $gateway_id, $enabled ) {
		$option_key = "woocommerce_{$gateway_id}_settings";
		$settings   = get_option( $option_key, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['enabled'] = $enabled ? 'yes' : 'no';
		update_option( $option_key, $settings );
	}
}
