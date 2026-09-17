<?php
namespace MageYaBo\Rest;

use MageYaBo\Booking\BookingRepository;
use MageYaBo\PostTypes\Yacht;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard summary: booking counts, revenue taken, and the size of the
 * published fleet. Extend the payload with the `..._reports_summary` filter.
 */
class ReportsController extends Controller {

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/reports/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'summary' ),
				'permission_callback' => array( __CLASS__, 'can_manage_bookings' ),
			)
		);
	}

	public static function summary() {
		$counts = BookingRepository::counts_for_dashboard();

		$counts['active_yachts']   = (int) wp_count_posts( Yacht::POST_TYPE )->publish;
		$counts['currency_symbol'] = \MageYaBo\Settings::get( 'currency_symbol', '$' );
		$counts['upcoming']        = array_map(
			static function ( $booking ) {
				return array(
					'id'              => (int) $booking['id'],
					'yacht_id'        => (int) $booking['yacht_id'],
					'yacht_name'      => $booking['yacht_name'] ?: __( 'Untitled yacht', 'magepeople-yacht-booking-system' ),
					'guest_name'      => $booking['guest_name'] ?: __( 'Guest', 'magepeople-yacht-booking-system' ),
					'guest_count'     => (int) $booking['guest_count'],
					'start_datetime'  => $booking['start_datetime'],
					'start_formatted' => mageyabo_format_datetime( $booking['start_datetime'] ),
					'duration'        => mageyabo_format_duration( $booking['start_datetime'], $booking['end_datetime'] ),
					'total_price'     => (float) $booking['total_price'],
					'currency'        => $booking['currency'],
					'status'          => sanitize_key( $booking['status'] ),
				);
			},
			BookingRepository::upcoming_for_dashboard()
		);

		/**
		 * Filters the dashboard summary payload.
		 *
		 * @param array $counts Summary values keyed by metric.
		 */
		return rest_ensure_response( apply_filters( 'mageyabo_reports_summary', $counts ) );
	}
}
