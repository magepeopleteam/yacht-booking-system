<?php
namespace Ybs\Rest;

use Ybs\Booking\BookingRepository;
use Ybs\PostTypes\Yacht;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts only - no revenue here, per spec 4.4.1. Revenue/analytics is a Pro
 * screen extending this via its own `/reports/revenue` route.
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

		$counts['active_yachts'] = (int) wp_count_posts( Yacht::POST_TYPE )->publish;

		return rest_ensure_response( $counts );
	}
}
