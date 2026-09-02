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
			'label'   => __( 'PayPal', 'magepeople-yacht-booking-system' ),
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
			return new \WP_Error( 'ybs_booking_missing', __( 'Booking could not be found.', 'magepeople-yacht-booking-system' ) );
		}

		$sandbox = 'sandbox' === Settings::get( 'paypal_mode', 'sandbox' );
		$base    = $sandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

		$args = array(
			'cmd'           => '_xclick',
			'business'      => Settings::get( 'paypal_email' ),
			'item_name'     => sprintf( /* translators: %d: booking id */ __( 'Yacht Booking #%d', 'magepeople-yacht-booking-system' ), $booking_id ),
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
		// Unslashed before use: the verification POST below has to echo the
		// payload back to PayPal byte-for-byte, and WordPress's added slashes
		// would make any IPN containing a quote fail `_notify-validate`.
		$payload = wp_unslash( $request->get_body_params() );

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
		$status     = sanitize_text_field( $payload['payment_status'] ?? '' );
		$booking    = $booking_id ? BookingRepository::find( $booking_id ) : null;

		if ( ! $booking ) {
			return new \WP_Error( 'ybs_ipn_unknown_booking', 'IPN references an unknown booking.', array( 'status' => 400 ) );
		}

		// A VERIFIED response only proves the IPN really came from PayPal - it
		// says nothing about *which* payment it describes. Without the checks
		// below, anyone could send a genuine 1-cent payment (or replay an old
		// IPN) carrying someone else's booking id in `custom` and have that
		// booking marked paid.
		$receiver  = sanitize_email( $payload['receiver_email'] ?? '' );
		$expected  = sanitize_email( Settings::get( 'paypal_email', '' ) );
		$currency  = sanitize_text_field( $payload['mc_currency'] ?? '' );
		$gross     = (float) ( $payload['mc_gross'] ?? 0 );
		$txn_id    = sanitize_text_field( $payload['txn_id'] ?? '' );

		if ( '' === $expected || 0 !== strcasecmp( $receiver, $expected ) ) {
			return new \WP_Error( 'ybs_ipn_receiver_mismatch', 'IPN receiver does not match the configured PayPal account.', array( 'status' => 400 ) );
		}

		if ( 0 !== strcasecmp( $currency, (string) $booking['currency'] ) ) {
			return new \WP_Error( 'ybs_ipn_currency_mismatch', 'IPN currency does not match the booking.', array( 'status' => 400 ) );
		}

		if ( abs( $gross - (float) $booking['total_price'] ) > 0.01 ) {
			return new \WP_Error( 'ybs_ipn_amount_mismatch', 'IPN amount does not match the booking total.', array( 'status' => 400 ) );
		}

		// Replay guard: a transaction id already recorded against this booking
		// has been processed once already.
		if ( '' !== $txn_id && $txn_id === (string) $booking['transaction_ref'] ) {
			return rest_ensure_response( array( 'received' => true, 'duplicate' => true ) );
		}

		if ( 'Completed' === $status ) {
			BookingRepository::update_payment( $booking_id, 'paid', array( 'transaction_ref' => $txn_id ) );
			BookingRepository::update_status( $booking_id, 'processing' );
		} elseif ( in_array( $status, array( 'Denied', 'Failed', 'Refunded' ), true ) ) {
			BookingRepository::update_payment( $booking_id, 'failed' );
		}

		return rest_ensure_response( array( 'received' => true ) );
	}
}
