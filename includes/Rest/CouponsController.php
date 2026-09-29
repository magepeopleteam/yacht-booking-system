<?php
namespace MageYaBo\Rest;

use MageYaBo\Booking\CouponRepository;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin management of the built-in discount codes. Every route is gated on
 * the settings capability - a code is money off a charter.
 *
 * Guests never call these: a code is checked through the public quote route
 * (`coupon_code` param), which only ever reports on the one code asked about,
 * so the list of codes cannot be read or guessed from the front end.
 */
class CouponsController extends Controller {

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/coupons',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'index' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/coupons/(?P<id>\d+)',
			array(
				array(
					'methods'             => array( 'PUT', 'POST' ),
					'callback'            => array( __CLASS__, 'update' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				),
			)
		);
	}

	public static function index() {
		return rest_ensure_response( CouponRepository::all() );
	}

	public static function create( WP_REST_Request $request ) {
		$data  = (array) $request->get_json_params();
		$error = self::check( $data );

		if ( $error ) {
			return $error;
		}

		$id = CouponRepository::create( $data );

		return rest_ensure_response( CouponRepository::find( $id ) );
	}

	public static function update( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! CouponRepository::find( $id ) ) {
			return new WP_Error( 'mageyabo_not_found', __( 'Coupon not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		$data  = (array) $request->get_json_params();
		$error = self::check( $data, $id );

		if ( $error ) {
			return $error;
		}

		CouponRepository::update( $id, $data );

		return rest_ensure_response( CouponRepository::find( $id ) );
	}

	public static function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! CouponRepository::find( $id ) ) {
			return new WP_Error( 'mageyabo_not_found', __( 'Coupon not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		CouponRepository::delete( $id );

		return rest_ensure_response(
			array(
				'id'      => $id,
				'deleted' => true,
			)
		);
	}

	/**
	 * Rules for a code the operator is saving. A partial update (just
	 * toggling `active`) only checks the fields it actually carries.
	 *
	 * @param array $data       Submitted fields.
	 * @param int   $current_id The code being edited, 0 when creating.
	 * @return WP_Error|null
	 */
	private static function check( array $data, $current_id = 0 ) {
		$creating = 0 === (int) $current_id;

		if ( $creating || array_key_exists( 'code', $data ) ) {
			$code = CouponRepository::normalize_code( $data['code'] ?? '' );

			if ( '' === $code ) {
				return new WP_Error( 'mageyabo_coupon_code_required', __( 'Please enter a coupon code.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
			}

			if ( ! preg_match( '/^[A-Z0-9_-]{3,40}$/', $code ) ) {
				return new WP_Error( 'mageyabo_coupon_code_format', __( 'Use 3 to 40 letters, numbers, dashes or underscores for the code.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
			}

			$existing = CouponRepository::find_by_code( $code );

			if ( $existing && (int) $existing['id'] !== (int) $current_id ) {
				return new WP_Error( 'mageyabo_coupon_code_taken', __( 'Another coupon already uses this code.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
			}
		}

		if ( $creating || array_key_exists( 'amount', $data ) ) {
			$amount = (float) ( $data['amount'] ?? 0 );
			$type   = $data['discount_type'] ?? 'percent';

			if ( $amount <= 0 ) {
				return new WP_Error( 'mageyabo_coupon_amount', __( 'The discount must be more than zero.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
			}

			if ( 'percent' === $type && $amount > 100 ) {
				return new WP_Error( 'mageyabo_coupon_amount', __( 'A percentage discount cannot be more than 100%.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
			}
		}

		$starts  = (string) ( $data['starts_on'] ?? '' );
		$expires = (string) ( $data['expires_on'] ?? '' );

		if ( $starts && $expires && $expires < $starts ) {
			return new WP_Error( 'mageyabo_coupon_dates', __( 'The expiry date must be on or after the start date.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		return null;
	}
}
