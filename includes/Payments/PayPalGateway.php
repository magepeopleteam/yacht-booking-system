<?php
namespace Ybs\Payments;

use Ybs\Booking\BookingRepository;
use Ybs\Settings;
use WP_REST_Server;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic PayPal Standard hosted redirect + IPN - dependency-free (no SDK),
 * matches the spec's own wording ("standard checkout redirect or button
 * integration"). No OAuth/REST-v2 token exchange needed.
 */
class PayPalGateway {

	const ID = 'paypal';

	public static function register() {
		add_filter( 'ybs_payment_gateways', array( __CLASS__, 'declare_self' ) );
		add_filter( 'ybs_payment_start', array( __CLASS__, 'start' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_ipn_route' ) );
	}

	public static function declare_self( $gateways ) {
		$gateways['paypal'] = array(
			'label'   => __( 'PayPal', 'yacht-booking-system' ),
			'enabled' => in_array( 'paypal', (array) Settings::get( 'payment_methods', array() ), true ) && Settings::get( 'paypal_email' ),
		);

		return $gateways;
	}

	public static function start( $result, $gateway_id, $booking_id ) {
		if ( 'paypal' !== $gateway_id ) {
			return $result;
		}

		$booking = BookingRepository::find( $booking_id );

		if ( ! $booking ) {
			return new \WP_Error( 'ybs_booking_missing', __( 'Booking could not be found.', 'yacht-booking-system' ) );
		}

		$sandbox = 'sandbox' === Settings::get( 'paypal_mode', 'sandbox' );
		$base    = $sandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

		$args = array(
			'cmd'           => '_xclick',
			'business'      => Settings::get( 'paypal_email' ),
			'item_name'     => sprintf( /* translators: %d: booking id */ __( 'Yacht Booking #%d', 'yacht-booking-system' ), $booking_id ),
			'amount'        => number_format( (float) $booking['total_price'], 2, '.', '' ),
			'currency_code' => $booking['currency'],
			'custom'        => $booking_id,
			'no_shipping'   => 1,
			'notify_url'    => rest_url( 'ybs/v1/payments/paypal/ipn' ),
			'return'        => add_query_arg( array( 'ybs_booking' => $booking_id, 'ybs_payment' => 'success' ), home_url( '/' ) ),
			'cancel_return' => add_query_arg( array( 'ybs_booking' => $booking_id, 'ybs_payment' => 'cancelled' ), home_url( '/' ) ),
		);

		return array( 'redirect' => $base . '?' . http_build_query( $args ) );
	}

	public static function register_ipn_route() {
		register_rest_route(
			'ybs/v1',
			'/payments/paypal/ipn',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_ipn' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle_ipn( WP_REST_Request $request ) {
		$payload = $request->get_body_params();

		if ( empty( $payload ) ) {
			return new \WP_Error( 'ybs_ipn_empty', 'Empty IPN payload.', array( 'status' => 400 ) );
		}

		$sandbox = 'sandbox' === Settings::get( 'paypal_mode', 'sandbox' );
		$base    = $sandbox ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 'https://ipnpb.paypal.com/cgi-bin/webscr';

		$verify_body = array_merge( array( 'cmd' => '_notify-validate' ), $payload );

		$response = wp_remote_post(
			$base,
			array(
				'body'    => $verify_body,
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) || 'VERIFIED' !== trim( wp_remote_retrieve_body( $response ) ) ) {
			return new \WP_Error( 'ybs_ipn_unverified', 'Could not verify IPN with PayPal.', array( 'status' => 400 ) );
		}

		$booking_id = (int) ( $payload['custom'] ?? 0 );
		$status     = $payload['payment_status'] ?? '';

		if ( $booking_id && 'Completed' === $status ) {
			BookingRepository::update_payment( $booking_id, 'paid', array( 'transaction_ref' => sanitize_text_field( $payload['txn_id'] ?? '' ) ) );
			BookingRepository::update_status( $booking_id, 'confirmed' );
		} elseif ( $booking_id && in_array( $status, array( 'Denied', 'Failed', 'Refunded' ), true ) ) {
			BookingRepository::update_payment( $booking_id, 'failed' );
		}

		return rest_ensure_response( array( 'received' => true ) );
	}
}
