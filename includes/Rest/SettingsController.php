<?php
namespace Ybs\Rest;

use Ybs\Settings;
use WP_REST_Server;
use WP_REST_Request;

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
	}

	public static function index() {
		$settings = Settings::all();

		// Secrets go out masked - the Settings screen only ever needs to know one is set.
		foreach ( array( 'paypal_secret', 'stripe_secret_key', 'stripe_webhook_secret' ) as $secret_key ) {
			$settings[ $secret_key . '_set' ] = ! empty( $settings[ $secret_key ] );
			unset( $settings[ $secret_key ] );
		}

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
}
