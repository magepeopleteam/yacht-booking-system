<?php
namespace Ybs\Rest;

use Ybs\Booking\GuestRepository;
use WP_REST_Server;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View-only in Free (spec 4.4.5) - no edit/delete routes exist here on
 * purpose; Pro adds those via its own routes registered through the
 * `ybs_rest_namespace_routes` filter rather than this controller growing them.
 */
class GuestsController extends Controller {

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/guests',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'index' ),
				'permission_callback' => array( __CLASS__, 'can_manage_bookings' ),
			)
		);
	}

	public static function index( WP_REST_Request $request ) {
		$result = GuestRepository::list_active(
			array(
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			)
		);

		// One flat row per booking, attendee-list style: every detail the
		// admin needs is right on the row - guest identity, which yacht,
		// when, what type, the total, and the WooCommerce order + status.
		$result['items'] = array_map(
			static function ( $row ) {
				$item = array(
					'id'             => (int) $row['id'],
					'guest_id'       => (int) $row['guest_id'],
					'name'           => $row['guest_name'],
					'email'          => $row['guest_email'],
					'phone'          => $row['guest_phone'],
					'yacht_name'     => $row['yacht_name'],
					'start_datetime' => $row['start_datetime'],
					'end_datetime'   => $row['end_datetime'],
					'start_formatted' => ybs_format_datetime( $row['start_datetime'] ),
					'booking_type'   => $row['booking_type'],
					'booking_mode'   => $row['booking_mode'],
					'guest_count'    => (int) $row['guest_count'],
					'total_price'    => (float) $row['total_price'],
					'currency'       => $row['currency'],
					'status'         => $row['status'],
					'order_id'       => (int) $row['woo_order_id'],
					'order_url'      => BookingsController::order_edit_url( (int) $row['woo_order_id'] ),
				);

				/**
				 * Lets Pro attach extra per-row data matching the columns it
				 * added via `ybs_guest_list_columns`.
				 */
				return apply_filters( 'ybs_guest_list_row', $item, $row );
			},
			$result['items']
		);

		/**
		 * Free's columns are fixed; Pro appends its own (spec section 7).
		 */
		$result['columns'] = apply_filters(
			'ybs_guest_list_columns',
			array(
				array( 'key' => 'name', 'label' => __( 'Name', 'yacht-booking-system' ) ),
				array( 'key' => 'email', 'label' => __( 'Email', 'yacht-booking-system' ) ),
				array( 'key' => 'phone', 'label' => __( 'Phone', 'yacht-booking-system' ) ),
			)
		);

		return rest_ensure_response( $result );
	}
}
