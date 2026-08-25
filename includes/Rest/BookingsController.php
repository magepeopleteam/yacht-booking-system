<?php
namespace Ybs\Rest;

use Ybs\Booking\AvailabilityService;
use Ybs\Booking\BookingRepository;
use Ybs\Booking\GuestRepository;
use Ybs\Booking\PricingEngine;
use Ybs\Payments\Gateways;
use Ybs\Settings;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingsController extends Controller {

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/bookings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'index' ),
					'permission_callback' => array( __CLASS__, 'can_manage_bookings' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/bookings/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'show' ),
				'permission_callback' => array( __CLASS__, 'can_manage_bookings' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/bookings/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage_bookings' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/bookings/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete' ),
				'permission_callback' => array( __CLASS__, 'can_manage_bookings' ),
			)
		);
	}

	public static function index( WP_REST_Request $request ) {
		$result = BookingRepository::list(
			array(
				'page'      => $request->get_param( 'page' ),
				'per_page'  => $request->get_param( 'per_page' ),
				'status'    => $request->get_param( 'status' ),
				'yacht_id'  => $request->get_param( 'yacht_id' ),
				'date_from' => $request->get_param( 'date_from' ),
				'date_to'   => $request->get_param( 'date_to' ),
			)
		);

		$result['items'] = array_map( array( __CLASS__, 'decorate' ), $result['items'] );

		return rest_ensure_response( $result );
	}

	/**
	 * Free intentionally exposes only enough here for the list screen to
	 * show a friendly upsell in place of a details page - see spec 4.4.3.
	 */
	public static function show( WP_REST_Request $request ) {
		$booking = BookingRepository::find( (int) $request['id'] );

		if ( ! $booking ) {
			return new WP_Error( 'ybs_not_found', __( 'Booking not found.', 'yacht-booking-system' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'id'               => (int) $booking['id'],
				'status'           => $booking['status'],
				'pro_required'     => ! ybs_is_pro_active(),
				'upgrade_message'  => __( 'Upgrade to Pro to view full booking details.', 'yacht-booking-system' ),
			)
		);
	}

	public static function create( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		$yacht_id = (int) ( $data['yacht_id'] ?? 0 );
		$yacht    = get_post( $yacht_id );

		if ( ! $yacht_id || ! $yacht || 'yacht' !== $yacht->post_type || 'publish' !== $yacht->post_status ) {
			return new WP_Error( 'ybs_invalid_yacht', __( 'Please choose a valid yacht.', 'yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$booking_type = sanitize_key( $data['booking_type'] ?? '' );
		$start        = sanitize_text_field( $data['start_datetime'] ?? '' );
		$end          = sanitize_text_field( $data['end_datetime'] ?? '' );
		$guest_count  = max( 1, (int) ( $data['guest_count'] ?? 1 ) );
		$yacht_mode   = get_post_meta( $yacht_id, 'booking_mode', true ) ?: 'full';
		$booking_mode = sanitize_key( $data['booking_mode'] ?? ( 'both' === $yacht_mode ? 'full' : $yacht_mode ) );

		if ( ! $start || ! $end ) {
			return new WP_Error( 'ybs_invalid_dates', __( 'Please choose a valid date and time.', 'yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$guest = $data['guest'] ?? array();

		if ( empty( $guest['name'] ) || empty( $guest['email'] ) || empty( $guest['phone'] ) ) {
			return new WP_Error( 'ybs_missing_guest_info', __( 'Please provide your name, email and phone number.', 'yacht-booking-system' ), array( 'status' => 400 ) );
		}

		if ( empty( $data['terms_accepted'] ) ) {
			return new WP_Error( 'ybs_terms_required', __( 'Please accept the terms and conditions.', 'yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$payment_method = sanitize_key( $data['payment_method'] ?? Settings::get( 'default_payment_method', 'offline' ) );

		$result = BookingRepository::with_yacht_lock(
			$yacht_id,
			static function () use ( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode, $guest, $payment_method ) {
				$availability = AvailabilityService::check( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode );

				if ( ! $availability['available'] ) {
					return new WP_Error( 'ybs_not_available', $availability['reason'] ?: __( 'This slot is not available.', 'yacht-booking-system' ), array( 'status' => 409 ) );
				}

				$pricing = PricingEngine::calculate( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode );

				if ( is_wp_error( $pricing ) ) {
					return $pricing;
				}

				/**
				 * Last chance to adjust the final total before it is persisted.
				 *
				 * @param float $total
				 * @param array $context
				 */
				$pricing['total'] = (float) apply_filters(
					'ybs_before_booking_total_calculated',
					$pricing['total'],
					compact( 'yacht_id', 'booking_type', 'start', 'end', 'guest_count' )
				);

				$guest_id   = GuestRepository::find_or_create( $guest );
				$booking_id = BookingRepository::create(
					array(
						'yacht_id'       => $yacht_id,
						'guest_id'       => $guest_id,
						'booking_type'   => $booking_type,
						'booking_mode'   => $booking_mode,
						'start_datetime' => $start,
						'end_datetime'   => $end,
						'guest_count'    => $guest_count,
						'base_price'     => $pricing['base_price'] + $pricing['adjustment_total'],
						'tax_total'      => $pricing['tax_total'],
						'total_price'    => $pricing['total'],
						'currency'       => Settings::get( 'currency_code', 'USD' ),
						'payment_method' => $payment_method,
					)
				);

				return array(
					'booking_id' => $booking_id,
					'pricing'    => $pricing,
				);
			}
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$payment_start = Gateways::start( $payment_method, $result['booking_id'] );

		if ( is_wp_error( $payment_start ) ) {
			return $payment_start;
		}

		return rest_ensure_response(
			array(
				'booking_id' => $result['booking_id'],
				'pricing'    => $result['pricing'],
				'payment'    => $payment_start,
			)
		);
	}

	public static function update_status( WP_REST_Request $request ) {
		$status = sanitize_key( $request->get_param( 'status' ) );

		if ( ! BookingRepository::update_status( (int) $request['id'], $status ) ) {
			return new WP_Error( 'ybs_invalid_status', __( 'Invalid booking or status.', 'yacht-booking-system' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'id' => (int) $request['id'], 'status' => $status ) );
	}

	public static function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! BookingRepository::find( $id ) ) {
			return new WP_Error( 'ybs_not_found', __( 'Booking not found.', 'yacht-booking-system' ), array( 'status' => 404 ) );
		}

		BookingRepository::delete( $id );

		return rest_ensure_response(
			array(
				'id'      => $id,
				'deleted' => true,
			)
		);
	}

	public static function order_edit_url( $order_id ) {
		if ( ! $order_id ) {
			return '';
		}

		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		return $hpos
			? admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id )
			: admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}

	private static function decorate( $booking ) {
		$guest = GuestRepository::find( $booking['guest_id'] );
		$yacht = get_post( $booking['yacht_id'] );

		return array(
			'id'             => (int) $booking['id'],
			'yacht_id'       => (int) $booking['yacht_id'],
			'yacht_name'     => $yacht ? $yacht->post_title : '',
			'guest_name'     => $guest['name'] ?? '',
			'guest_email'    => $guest['email'] ?? '',
			'booking_type'   => $booking['booking_type'],
			'start_datetime' => $booking['start_datetime'],
			'end_datetime'   => $booking['end_datetime'],
			'start_formatted' => ybs_format_datetime( $booking['start_datetime'] ),
			'end_formatted'  => ybs_format_datetime( $booking['end_datetime'] ),
			'duration'       => ybs_format_duration( $booking['start_datetime'], $booking['end_datetime'] ),
			'guest_count'    => (int) $booking['guest_count'],
			'total_price'    => (float) $booking['total_price'],
			'currency'       => $booking['currency'],
			'status'         => $booking['status'],
			'payment_method' => $booking['payment_method'],
			'payment_status' => $booking['payment_status'],
			'woo_order_id'   => (int) $booking['woo_order_id'],
			'woo_order_url'  => self::order_edit_url( (int) $booking['woo_order_id'] ),
		);
	}
}
