<?php
namespace Ybs\Rest;

use Ybs\Booking\BookingRepository;
use Ybs\PostTypes\Yacht;
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
		$counts['currency_symbol'] = \Ybs\Settings::get( 'currency_symbol', '$' );

		/**
		 * Filters the dashboard summary payload.
		 *
		 * @param array $counts Summary values keyed by metric.
		 */
		return rest_ensure_response( apply_filters( 'ybs_reports_summary', $counts ) );
	}
}
