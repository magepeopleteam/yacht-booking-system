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

		$result['items'] = array_map(
			static function ( $guest ) {
				$row = array(
					'id'    => (int) $guest['id'],
					'name'  => $guest['name'],
					'email' => $guest['email'],
					'phone' => $guest['phone'],
					'last_booking_id' => (int) $guest['last_booking_id'],
				);

				/**
				 * Lets Pro attach extra per-row data (e.g. checked-in status)
				 * matching the columns it added via `ybs_guest_list_columns`.
				 */
				return apply_filters( 'ybs_guest_list_row', $row, $guest );
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
