<?php
namespace MageYaBo\Rest;

use MageYaBo\Booking\AddonRepository;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The add-on catalogue (catering, skipper, water toys...) and its per-yacht
 * assignment.
 *
 * Writing is gated on the settings capability - an add-on's price is part of
 * what a charter costs. Reading the *catalogue* is gated too, but the yacht
 * route is public, because the booking form has to show a guest what extras
 * the yacht they are looking at offers.
 */
class AddonsController extends Controller {

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/addons',
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
			'/addons/(?P<id>\d+)',
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

		register_rest_route(
			self::NAMESPACE_,
			'/yachts/(?P<id>\d+)/addons',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'for_yacht' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'assign_to_yacht' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
				),
			)
		);
	}

	public static function index() {
		return rest_ensure_response( AddonRepository::all() );
	}

	public static function create( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();

		if ( '' === trim( (string) ( $data['name'] ?? '' ) ) ) {
			return new WP_Error( 'mageyabo_addon_name_required', __( 'Please give the add-on a name.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$id = AddonRepository::create( $data );

		return rest_ensure_response( AddonRepository::find( $id ) );
	}

	public static function update( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! AddonRepository::find( $id ) ) {
			return new WP_Error( 'mageyabo_not_found', __( 'Add-on not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		AddonRepository::update( $id, (array) $request->get_json_params() );

		return rest_ensure_response( AddonRepository::find( $id ) );
	}

	public static function delete( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! AddonRepository::find( $id ) ) {
			return new WP_Error( 'mageyabo_not_found', __( 'Add-on not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		AddonRepository::delete( $id );

		return rest_ensure_response(
			array(
				'id'      => $id,
				'deleted' => true,
			)
		);
	}

	/**
	 * Public. An operator editing the yacht asks for `all=1` to see disabled
	 * add-ons still ticked; the booking form gets only the live ones.
	 */
	public static function for_yacht( WP_REST_Request $request ) {
		$yacht_id   = (int) $request['id'];
		$can_manage = \MageYaBo\Capabilities::can( 'settings' );
		$all        = (bool) $request->get_param( 'all' ) && $can_manage;

		$response = array(
			'items' => AddonRepository::for_yacht( $yacht_id, ! $all ),
		);

		// The booking form only needs what it can offer today. `assigned`
		// also names add-ons that are switched off, which is the wizard's
		// business and nobody else's.
		if ( $can_manage ) {
			$response['assigned'] = AddonRepository::assigned_ids( $yacht_id );
		}

		return rest_ensure_response( $response );
	}

	public static function assign_to_yacht( WP_REST_Request $request ) {
		$yacht_id = (int) $request['id'];
		$data     = (array) $request->get_json_params();

		$assigned = AddonRepository::set_for_yacht( $yacht_id, (array) ( $data['addon_ids'] ?? array() ) );

		return rest_ensure_response(
			array(
				'assigned' => $assigned,
				'items'    => AddonRepository::for_yacht( $yacht_id, false ),
			)
		);
	}
}
