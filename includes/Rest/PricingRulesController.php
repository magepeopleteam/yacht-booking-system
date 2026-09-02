<?php
namespace Ybs\Rest;

use Ybs\Booking\PricingRuleRepository;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free's UI only ever sends `off_day`/basic weekday-weekend/date-range rules
 * through here (spec 4.6/4.4.6), but the endpoint itself is generic over the
 * full `rule_type`/`adjustment_type` set the table supports, so Pro's
 * seasonal/peak rule screens (spec 4.7) need no new route.
 */
class PricingRulesController extends Controller {

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/pricing-rules',
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
			'/pricing-rules/(?P<id>\d+)',
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

	public static function index( WP_REST_Request $request ) {
		return rest_ensure_response( PricingRuleRepository::list( $request->get_param( 'yacht_id' ) ?: null ) );
	}

	public static function create( WP_REST_Request $request ) {
		$id = PricingRuleRepository::create( (array) $request->get_json_params() );

		return rest_ensure_response( PricingRuleRepository::find( $id ) );
	}

	public static function update( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! PricingRuleRepository::find( $id ) ) {
			return new WP_Error( 'ybs_not_found', __( 'Pricing rule not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		PricingRuleRepository::update( $id, (array) $request->get_json_params() );

		return rest_ensure_response( PricingRuleRepository::find( $id ) );
	}

	public static function delete( WP_REST_Request $request ) {
		PricingRuleRepository::delete( (int) $request['id'] );

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
