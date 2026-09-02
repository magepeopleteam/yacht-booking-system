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
 * Stripe Checkout Sessions via raw REST calls (wp_remote_post) rather than
 * the `stripe-php` SDK, so Free carries zero Composer runtime dependencies.
 * The webhook signature is verified by hand (HMAC-SHA256 over
 * "{timestamp}.{payload}"), which is all `stripe-php`'s verifier does anyway.
 */
class StripeGateway {

	const ID = 'stripe';
	const API_BASE = 'https://api.stripe.com/v1';

	public static function register() {
		add_filter( 'ybs_payment_gateways', array( __CLASS__, 'declare_self' ) );
		add_filter( 'ybs_payment_start', array( __CLASS__, 'start' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_webhook_route' ) );
	}

	public static function declare_self( $gateways ) {
		$gateways[ self::ID ] = array(
			'label'   => __( 'Stripe', 'magepeople-yacht-booking-system' ),
			'enabled' => in_array( self::ID, (array) Settings::get( 'payment_methods', array() ), true ) && Settings::get( 'stripe_secret_key' ),
		);

		return $gateways;
	}

	public static function start( $result, $gateway_id, $booking_id ) {
		if ( self::ID !== $gateway_id ) {
			return $result;
		}

		$booking = BookingRepository::find( $booking_id );

		if ( ! $booking ) {
			return new \WP_Error( 'ybs_booking_missing', __( 'Booking could not be found.', 'magepeople-yacht-booking-system' ) );
		}

		$secret_key = Settings::get( 'stripe_secret_key' );

		if ( ! $secret_key ) {
			return new \WP_Error( 'ybs_stripe_not_configured', __( 'Stripe is not configured.', 'magepeople-yacht-booking-system' ) );
		}

		$body = array(
			'mode'                              => 'payment',
			'success_url'                       => add_query_arg( array( 'ybs_booking' => $booking_id, 'ybs_payment' => 'success' ), home_url( '/' ) ),
			'cancel_url'                        => add_query_arg( array( 'ybs_booking' => $booking_id, 'ybs_payment' => 'cancelled' ), home_url( '/' ) ),
			'line_items' => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => strtolower( $booking['currency'] ),
						'unit_amount'  => (int) round( (float) $booking['total_price'] * 100 ),
						'product_data' => array(
							/* translators: %d: booking id */
							'name' => sprintf( __( 'Yacht Booking #%d', 'magepeople-yacht-booking-system' ), $booking_id ),
						),
					),
				),
			),
			'metadata' => array( 'booking_id' => $booking_id ),
		);

		$response = wp_remote_post(
			self::API_BASE . '/checkout/sessions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => self::to_stripe_form( $body ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ybs_stripe_error', $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['url'] ) ) {
			$message = $data['error']['message'] ?? __( 'Stripe did not return a checkout URL.', 'magepeople-yacht-booking-system' );
			return new \WP_Error( 'ybs_stripe_error', $message );
		}

		BookingRepository::update_payment( $booking_id, 'unpaid', array( 'transaction_ref' => sanitize_text_field( $data['id'] ) ) );

		return array( 'redirect' => $data['url'] );
	}

	public static function register_webhook_route() {
		register_rest_route(
			'ybs/v1',
			'/payments/stripe/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle_webhook( WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe_signature' );
		$secret    = Settings::get( 'stripe_webhook_secret' );

		if ( ! $secret || ! $signature || ! self::verify_signature( $payload, $signature, $secret ) ) {
			return new \WP_Error( 'ybs_stripe_invalid_signature', 'Invalid Stripe signature.', array( 'status' => 400 ) );
		}

		$event = json_decode( $payload, true );

		if ( 'checkout.session.completed' === ( $event['type'] ?? '' ) ) {
			$session    = $event['data']['object'] ?? array();
			$booking_id = (int) ( $session['metadata']['booking_id'] ?? 0 );

			if ( $booking_id && 'paid' === ( $session['payment_status'] ?? '' ) ) {
				BookingRepository::update_payment( $booking_id, 'paid', array( 'transaction_ref' => sanitize_text_field( $session['payment_intent'] ?? '' ) ) );
				BookingRepository::update_status( $booking_id, 'processing' );
			}
		}

		return rest_ensure_response( array( 'received' => true ) );
	}

	/**
	 * Stripe's tolerance window, in seconds - a correctly signed payload older
	 * than this is rejected so a captured webhook can't be replayed forever.
	 */
	const SIGNATURE_TOLERANCE = 300;

	private static function verify_signature( $payload, $signature_header, $secret ) {
		$timestamp  = '';
		$signatures = array();

		// A header can carry several `v1=` signatures during a secret roll, so
		// collect them all rather than letting the last one overwrite the rest.
		foreach ( explode( ',', $signature_header ) as $part ) {
			[ $key, $value ] = array_pad( explode( '=', trim( $part ), 2 ), 2, '' );

			if ( 't' === $key ) {
				$timestamp = $value;
			} elseif ( 'v1' === $key ) {
				$signatures[] = $value;
			}
		}

		if ( '' === $timestamp || ! $signatures ) {
			return false;
		}

		if ( abs( time() - (int) $timestamp ) > self::SIGNATURE_TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Stripe's API expects classic PHP-style bracket-encoded form bodies for
	 * nested arrays (`line_items[0][price_data][currency]=usd`), not JSON.
	 */
	private static function to_stripe_form( array $data ) {
		$pairs = array();

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$pairs = array_merge( $pairs, self::flatten_to_pairs( $value, $key ) );
			} else {
				$pairs[ $key ] = $value;
			}
		}

		return $pairs;
	}

	private static function flatten_to_pairs( array $data, $prefix ) {
		$pairs = array();

		foreach ( $data as $key => $value ) {
			$field = "{$prefix}[{$key}]";

			if ( is_array( $value ) ) {
				$pairs = array_merge( $pairs, self::flatten_to_pairs( $value, $field ) );
			} else {
				$pairs[ $field ] = $value;
			}
		}

		return $pairs;
	}
}
