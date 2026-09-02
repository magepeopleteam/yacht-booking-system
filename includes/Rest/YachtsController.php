<?php
namespace Ybs\Rest;

use Ybs\Booking\AvailabilityService;
use Ybs\PostTypes\Yacht;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Yacht CRUD is gated behind the 'settings' capability (mirrors the sibling
 * shuttle plugin's model, where managing the fleet sits with the same people
 * who manage settings, distinct from day-to-day booking staff). Reading a
 * single yacht or the list is public - the frontend search/listing pages need it.
 */
class YachtsController extends Controller {

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/yachts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'index' ),
					'permission_callback' => '__return_true',
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
			'/yachts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'show' ),
					'permission_callback' => '__return_true',
				),
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
			'/yachts/(?P<id>\d+)/availability',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'availability' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/yachts/(?P<id>\d+)/quote',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'quote' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/yachts/(?P<id>\d+)/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'calendar' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/yachts/dummy-import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'dummy_import' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			)
		);
	}

	public static function quote( WP_REST_Request $request ) {
		$yacht_id = (int) $request['id'];
		$yacht    = self::get_readable_yacht( $yacht_id );

		if ( is_wp_error( $yacht ) ) {
			return $yacht;
		}

		$booking_type = sanitize_key( $request->get_param( 'booking_type' ) );
		$start        = sanitize_text_field( $request->get_param( 'start_datetime' ) );
		$end          = sanitize_text_field( $request->get_param( 'end_datetime' ) );
		$guest_count  = max( 1, (int) $request->get_param( 'guest_count' ) ?: 1 );
		$booking_mode = sanitize_key( $request->get_param( 'booking_mode' ) ) ?: ( get_post_meta( $yacht_id, 'booking_mode', true ) ?: 'full' );

		if ( ! $start || ! $end ) {
			return new WP_Error( 'ybs_invalid_dates', __( 'Please choose a date and time.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$availability = AvailabilityService::check( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode );

		if ( ! $availability['available'] ) {
			return new WP_Error( 'ybs_not_available', $availability['reason'], array( 'status' => 409 ) );
		}

		$pricing = \Ybs\Booking\PricingEngine::calculate( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode );

		if ( is_wp_error( $pricing ) ) {
			return $pricing;
		}

		return rest_ensure_response(
			array(
				'pricing'      => $pricing,
				'availability' => $availability,
				'currency'     => \Ybs\Settings::get( 'currency_symbol', '$' ),
			)
		);
	}

	public static function calendar( WP_REST_Request $request ) {
		$yacht_id = (int) $request['id'];
		$yacht    = self::get_readable_yacht( $yacht_id );

		if ( is_wp_error( $yacht ) ) {
			return $yacht;
		}

		$month = sanitize_text_field( $request->get_param( 'month' ) ?: current_time( 'Y-m' ) );

		// Validated rather than trusted: an unparseable month makes strtotime()
		// return false, and gmdate('t', false) then reports 31 - running a
		// month's worth of availability checks on an unauthenticated route.
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			return new WP_Error( 'ybs_invalid_month', __( 'Please provide a month as YYYY-MM.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$booking_mode = get_post_meta( $yacht_id, 'booking_mode', true ) ?: 'full';

		$timestamp  = strtotime( $month . '-01' );
		$days_count = (int) gmdate( 't', $timestamp );
		$days       = array();

		for ( $day = 1; $day <= $days_count; $day++ ) {
			$date  = sprintf( '%s-%02d', $month, $day );
			$check = AvailabilityService::check( $yacht_id, 'daily', $date . ' 08:00:00', $date . ' 20:00:00', 1, $booking_mode );

			$days[ $date ] = $check['available'];
		}

		return rest_ensure_response( array( 'month' => $month, 'days' => $days ) );
	}

	public static function index( WP_REST_Request $request ) {
		$can_manage = \Ybs\Capabilities::can( 'settings' );

		$args = array(
			'post_type'      => Yacht::POST_TYPE,
			'post_status'    => $can_manage ? 'any' : 'publish',
			// Capped: this route is public, so an unbounded per_page would let
			// anyone ask for the entire fleet (and its meta) in one query.
			'posts_per_page' => min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 20 ) ),
			'paged'          => max( 1, absint( $request->get_param( 'page' ) ) ?: 1 ),
		);

		$tax_query = array();

		if ( $request->get_param( 'class' ) ) {
			$tax_query[] = array(
				'taxonomy' => 'yacht_class',
				'field'    => 'slug',
				'terms'    => sanitize_title( $request->get_param( 'class' ) ),
			);
		}

		if ( $request->get_param( 'occasion' ) ) {
			$tax_query[] = array(
				'taxonomy' => 'yacht_occasion',
				'field'    => 'slug',
				'terms'    => sanitize_title( $request->get_param( 'occasion' ) ),
			);
		}

		if ( $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$meta_query = array();

		if ( $request->get_param( 'guests' ) ) {
			$meta_query[] = array(
				'key'     => 'capacity',
				'value'   => (int) $request->get_param( 'guests' ),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( $request->get_param( 'search' ) ) {
			$args['s'] = sanitize_text_field( $request->get_param( 'search' ) );
		}

		$query = new \WP_Query( $args );
		$items = array_map( array( __CLASS__, 'summarize' ), $query->posts );

		// Price range and "near me" filters need computed values, applied post-query.
		$price_min = $request->get_param( 'price_min' );
		$price_max = $request->get_param( 'price_max' );

		if ( '' !== $price_min && null !== $price_min ) {
			$items = array_values( array_filter( $items, static fn( $item ) => $item['from_price'] >= (float) $price_min ) );
		}

		if ( '' !== $price_max && null !== $price_max ) {
			$items = array_values( array_filter( $items, static fn( $item ) => $item['from_price'] <= (float) $price_max ) );
		}

		$lat    = $request->get_param( 'lat' );
		$lng    = $request->get_param( 'lng' );
		$radius = (float) ( $request->get_param( 'radius_km' ) ?: 0 );

		if ( '' !== $lat && '' !== $lng && null !== $lat && null !== $lng ) {
			foreach ( $items as &$item ) {
				$item['distance_km'] = self::haversine( (float) $lat, (float) $lng, (float) $item['location']['lat'], (float) $item['location']['lng'] );
			}
			unset( $item );

			if ( $radius > 0 ) {
				$items = array_values( array_filter( $items, static fn( $item ) => null === $item['distance_km'] || $item['distance_km'] <= $radius ) );
			}

			usort( $items, static fn( $a, $b ) => ( $a['distance_km'] ?? PHP_FLOAT_MAX ) <=> ( $b['distance_km'] ?? PHP_FLOAT_MAX ) );
		}

		return rest_ensure_response(
			array(
				'items'        => $items,
				'total'        => (int) $query->found_posts,
				'pages'        => (int) $query->max_num_pages,
				'dummy_seeded' => (bool) get_option( 'ybs_dummy_seeded' ),
			)
		);
	}

	/**
	 * Resolves a yacht for one of the public (`__return_true`) read routes.
	 *
	 * Anything not published is treated as non-existent unless the caller can
	 * manage yachts - without this, a draft yacht's full record (including its
	 * unpublished rates and confirmation email body) is readable by anyone who
	 * can guess a post id.
	 *
	 * @param int $yacht_id Yacht post id.
	 * @return \WP_Post|WP_Error
	 */
	private static function get_readable_yacht( $yacht_id ) {
		$post = get_post( $yacht_id );

		if ( ! $post || Yacht::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ybs_not_found', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		if ( 'publish' !== $post->post_status && ! \Ybs\Capabilities::can( 'settings' ) ) {
			return new WP_Error( 'ybs_not_found', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	public static function show( WP_REST_Request $request ) {
		$post = self::get_readable_yacht( (int) $request['id'] );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return rest_ensure_response( self::full( $post ) );
	}

	public static function create( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		$post_id = wp_insert_post(
			array(
				'post_type'    => Yacht::POST_TYPE,
				'post_title'   => sanitize_text_field( $data['title'] ?? __( 'Untitled Yacht', 'magepeople-yacht-booking-system' ) ),
				'post_content' => wp_kses_post( $data['description'] ?? '' ),
				'post_status'  => sanitize_key( $data['status'] ?? 'draft' ),
				'post_name'    => ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_meta( $post_id, $data );
		self::save_taxonomies( $post_id, $data );
		self::save_featured_media( $post_id, $data );

		return rest_ensure_response( self::full( get_post( $post_id ) ) );
	}

	public static function update( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post || Yacht::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ybs_not_found', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		$data   = $request->get_json_params();
		$update = array( 'ID' => $post->ID );

		if ( isset( $data['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['description'] ) ) {
			$update['post_content'] = wp_kses_post( $data['description'] );
		}

		if ( isset( $data['status'] ) ) {
			$update['post_status'] = sanitize_key( $data['status'] );
		}

		if ( ! empty( $data['slug'] ) ) {
			$update['post_name'] = sanitize_title( $data['slug'] );
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		self::save_meta( $post->ID, $data );
		self::save_taxonomies( $post->ID, $data );
		self::save_featured_media( $post->ID, $data );

		return rest_ensure_response( self::full( get_post( $post->ID ) ) );
	}

	public static function delete( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post || Yacht::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ybs_not_found', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		wp_delete_post( $post->ID, true );

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * One-time sample-fleet seeder so a fresh install has something to look
	 * at immediately. The `ybs_dummy_seeded` option makes this permanently
	 * unavailable once it has run - both the guard below and the admin UI's
	 * button (hidden once `dummy_seeded` comes back true) rely on it.
	 */
	public static function dummy_import() {
		if ( get_option( 'ybs_dummy_seeded' ) ) {
			return new WP_Error( 'ybs_already_seeded', __( 'Sample yachts have already been imported.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$imported = 0;

		foreach ( self::dummy_yacht_samples() as $sample ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => Yacht::POST_TYPE,
					'post_title'   => $sample['title'],
					'post_content' => $sample['description'],
					'post_status'  => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			self::save_meta( $post_id, $sample['meta'] );

			$class_term = get_term_by( 'name', $sample['class'], 'yacht_class' );

			if ( $class_term ) {
				wp_set_object_terms( $post_id, array( $class_term->term_id ), 'yacht_class' );
			}

			$occasion_ids = array();

			foreach ( $sample['occasions'] as $occasion_name ) {
				$term = get_term_by( 'name', $occasion_name, 'yacht_occasion' );

				if ( $term ) {
					$occasion_ids[] = $term->term_id;
				}
			}

			if ( $occasion_ids ) {
				wp_set_object_terms( $post_id, $occasion_ids, 'yacht_occasion' );
			}

			$imported++;
		}

		update_option( 'ybs_dummy_seeded', 1 );

		return rest_ensure_response(
			array(
				'imported'     => $imported,
				'dummy_seeded' => true,
			)
		);
	}

	private static function dummy_yacht_samples() {
		return array(
			array(
				'title'       => __( 'Ocean Breeze', 'magepeople-yacht-booking-system' ),
				'description' => __( 'A sleek 42ft motor yacht perfect for sunset cruises and small celebrations along the coast.', 'magepeople-yacht-booking-system' ),
				'class'       => 'Comfort',
				'occasions'   => array( 'Birthday', 'Sunset Cocktail' ),
				'meta'        => array(
					'capacity'                => '12',
					'cabins'                  => '2',
					'crew_size'               => '2',
					'length'                  => '42',
					'build_year'              => '2018',
					'location_name'           => 'Miami Marina, FL',
					'location_lat'            => '25.7743',
					'location_lng'            => '-80.1937',
					'base_price_hourly'       => '150',
					'base_price_halfday'      => '650',
					'base_price_daily'        => '1200',
					'base_price_morning_slot' => '500',
					'base_price_evening_slot' => '700',
					'min_notice_hours'        => '12',
					'buffer_minutes'          => '30',
					'min_duration'            => '120',
					'max_duration'            => '480',
					'booking_mode'            => 'full',
				),
			),
			array(
				'title'       => __( 'Sapphire Horizon', 'magepeople-yacht-booking-system' ),
				'description' => __( 'A spacious 68ft luxury cruiser with a sundeck lounge, ideal for corporate charters and weddings.', 'magepeople-yacht-booking-system' ),
				'class'       => 'First Class',
				'occasions'   => array( 'Wedding', 'Corporate' ),
				'meta'        => array(
					'capacity'                => '30',
					'cabins'                  => '4',
					'crew_size'               => '5',
					'length'                  => '68',
					'build_year'              => '2021',
					'location_name'           => 'Port Hercule, Monaco',
					'location_lat'            => '43.7325',
					'location_lng'            => '7.4256',
					'base_price_hourly'       => '450',
					'base_price_halfday'      => '2200',
					'base_price_daily'        => '4200',
					'base_price_morning_slot' => '1800',
					'base_price_evening_slot' => '2400',
					'min_notice_hours'        => '24',
					'buffer_minutes'          => '60',
					'min_duration'            => '180',
					'max_duration'            => '600',
					'booking_mode'            => 'full',
				),
			),
			array(
				'title'       => __( 'Island Serenade', 'magepeople-yacht-booking-system' ),
				'description' => __( 'A breezy 36ft catamaran built for laid-back island hopping and small bachelorette groups.', 'magepeople-yacht-booking-system' ),
				'class'       => 'Comfort Plus',
				'occasions'   => array( 'Bachelorette', 'Anniversary / Proposal' ),
				'meta'        => array(
					'capacity'                => '10',
					'cabins'                  => '2',
					'crew_size'               => '1',
					'length'                  => '36',
					'build_year'              => '2016',
					'location_name'           => 'Marina Ibiza, Spain',
					'location_lat'            => '38.9067',
					'location_lng'            => '1.4206',
					'base_price_hourly'       => '120',
					'base_price_halfday'      => '520',
					'base_price_daily'        => '980',
					'base_price_morning_slot' => '400',
					'base_price_evening_slot' => '560',
					'min_notice_hours'        => '8',
					'buffer_minutes'          => '30',
					'min_duration'            => '120',
					'max_duration'            => '360',
					'booking_mode'            => 'full',
				),
			),
			array(
				'title'       => __( 'Golden Mirage', 'magepeople-yacht-booking-system' ),
				'description' => __( 'A striking 55ft superyacht with a jacuzzi deck, built for high-end business entertaining.', 'magepeople-yacht-booking-system' ),
				'class'       => 'Business',
				'occasions'   => array( 'Corporate', 'Birthday' ),
				'meta'        => array(
					'capacity'                => '20',
					'cabins'                  => '3',
					'crew_size'               => '4',
					'length'                  => '55',
					'build_year'              => '2019',
					'location_name'           => 'Dubai Marina, UAE',
					'location_lat'            => '25.0805',
					'location_lng'            => '55.1403',
					'base_price_hourly'       => '320',
					'base_price_halfday'      => '1500',
					'base_price_daily'        => '2800',
					'base_price_morning_slot' => '1200',
					'base_price_evening_slot' => '1600',
					'min_notice_hours'        => '24',
					'buffer_minutes'          => '45',
					'min_duration'            => '120',
					'max_duration'            => '480',
					'booking_mode'            => 'full',
				),
			),
			array(
				'title'       => __( 'Aegean Muse', 'magepeople-yacht-booking-system' ),
				'description' => __( 'A whitewashed 48ft sailing yacht drifting past the caldera - built for sunset proposals.', 'magepeople-yacht-booking-system' ),
				'class'       => 'Comfort',
				'occasions'   => array( 'Anniversary / Proposal', 'Sunset Cocktail' ),
				'meta'        => array(
					'capacity'                => '14',
					'cabins'                  => '2',
					'crew_size'               => '2',
					'length'                  => '48',
					'build_year'              => '2017',
					'location_name'           => 'Vlychada Marina, Santorini',
					'location_lat'            => '36.3492',
					'location_lng'            => '25.4615',
					'base_price_hourly'       => '180',
					'base_price_halfday'      => '780',
					'base_price_daily'        => '1400',
					'base_price_morning_slot' => '600',
					'base_price_evening_slot' => '820',
					'min_notice_hours'        => '12',
					'buffer_minutes'          => '30',
					'min_duration'            => '120',
					'max_duration'            => '480',
					'booking_mode'            => 'full',
				),
			),
			array(
				'title'       => __( 'Southern Star', 'magepeople-yacht-booking-system' ),
				'description' => __( 'A lively 60ft party yacht with a sound system and open deck, built for big celebrations - bookable as a full charter or by the seat.', 'magepeople-yacht-booking-system' ),
				'class'       => 'Party',
				'occasions'   => array( 'Bachelorette', 'Birthday' ),
				'meta'        => array(
					'capacity'                       => '40',
					'cabins'                         => '3',
					'crew_size'                      => '4',
					'length'                         => '60',
					'build_year'                     => '2020',
					'location_name'                  => 'Sydney Harbour, Australia',
					'location_lat'                   => '-33.8523',
					'location_lng'                   => '151.2108',
					'base_price_hourly'              => '280',
					'base_price_halfday'             => '1250',
					'base_price_daily'               => '2300',
					'base_price_morning_slot'        => '950',
					'base_price_evening_slot'        => '1350',
					'base_price_shared_hourly'       => '35',
					'base_price_shared_halfday'      => '140',
					'base_price_shared_daily'        => '260',
					'base_price_shared_morning_slot' => '110',
					'base_price_shared_evening_slot' => '160',
					'min_notice_hours'               => '12',
					'buffer_minutes'                 => '45',
					'min_duration'                   => '120',
					'max_duration'                   => '480',
					'booking_mode'                   => 'both',
				),
			),
		);
	}

	public static function availability( WP_REST_Request $request ) {
		$yacht_id = (int) $request['id'];
		$yacht    = self::get_readable_yacht( $yacht_id );

		if ( is_wp_error( $yacht ) ) {
			return $yacht;
		}

		$date = sanitize_text_field( $request->get_param( 'date' ) ?: current_time( 'Y-m-d' ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'ybs_invalid_date', __( 'Please provide a date as YYYY-MM-DD.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$slots        = Yacht::time_windows( $yacht_id );
		$results      = array();
		$booking_mode = get_post_meta( $yacht_id, 'booking_mode', true ) ?: 'full';

		foreach ( $slots as $type => $window ) {
			$start = $date . ' ' . $window[0] . ':00';
			$end   = $date . ' ' . $window[1] . ':00';

			$results[ $type ] = AvailabilityService::check( $yacht_id, $type, $start, $end, 1, $booking_mode );
		}

		return rest_ensure_response(
			array(
				'date'         => $date,
				'booking_mode' => $booking_mode,
				'slots'        => $results,
			)
		);
	}

	/**
	 * Sanitizes the repeatable list metas before they are stored.
	 *
	 * FAQ answers are the only field here allowed to keep markup (they are
	 * authored in the classic editor and rendered with wp_kses_post()); every
	 * other cell is plain text.
	 *
	 * @param string $key   Meta key being saved.
	 * @param mixed  $value Raw value from the request.
	 * @return array
	 */
	private static function sanitize_meta_rows( $key, $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		if ( 'faq' === $key ) {
			return array_values(
				array_map(
					static function ( $row ) {
						return array(
							'question' => sanitize_text_field( (string) ( $row['question'] ?? '' ) ),
							'answer'   => wp_kses_post( (string) ( $row['answer'] ?? '' ) ),
						);
					},
					array_filter( $value, 'is_array' )
				)
			);
		}

		return array_values(
			array_map(
				static function ( $row ) {
					return is_array( $row )
						? array_map( 'sanitize_text_field', array_map( 'strval', $row ) )
						: sanitize_text_field( (string) $row );
				},
				$value
			)
		);
	}

	private static function save_meta( $post_id, array $data ) {
		foreach ( \Ybs\PostTypes\Yacht::META_KEYS as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$value = $data[ $key ];

			if ( 'gallery' === $key ) {
				$ids = array_map(
					static function ( $item ) {
						return is_array( $item ) ? (int) ( $item['id'] ?? 0 ) : (int) $item;
					},
					is_array( $value ) ? $value : array()
				);

				update_post_meta( $post_id, $key, array_values( array_filter( $ids ) ) );
			} elseif ( in_array( $key, array( 'faq', 'included_items', 'off_days' ), true ) ) {
				update_post_meta( $post_id, $key, self::sanitize_meta_rows( $key, $value ) );
			} elseif ( 'confirmation_email_body' === $key ) {
				// Rich text from the wizard's classic editor - sanitize_text_field()
				// would strip it down to plain text.
				update_post_meta( $post_id, $key, wp_kses_post( (string) $value ) );
			} else {
				update_post_meta( $post_id, $key, sanitize_text_field( (string) $value ) );
			}
		}
	}

	private static function save_featured_media( $post_id, array $data ) {
		if ( ! array_key_exists( 'featured_media', $data ) ) {
			return;
		}

		$attachment_id = (int) $data['featured_media'];

		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		} else {
			delete_post_thumbnail( $post_id );
		}
	}

	private static function save_taxonomies( $post_id, array $data ) {
		if ( isset( $data['yacht_class'] ) ) {
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $data['yacht_class'] ), 'yacht_class' );
		}

		if ( isset( $data['yacht_occasion'] ) ) {
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $data['yacht_occasion'] ), 'yacht_occasion' );
		}
	}

	private static function summarize( $post ) {
		$gallery_ids = get_post_meta( $post->ID, 'gallery', true );
		$gallery_ids = is_array( $gallery_ids ) ? array_filter( array_map( 'intval', $gallery_ids ) ) : array();
		$thumb_id    = get_post_thumbnail_id( $post->ID );

		if ( $thumb_id && ! in_array( $thumb_id, $gallery_ids, true ) ) {
			array_unshift( $gallery_ids, $thumb_id );
		}

		$photos = array_values(
			array_filter( array_map( static fn( $id ) => wp_get_attachment_image_url( $id, 'medium' ), $gallery_ids ) )
		);

		return array(
			'id'          => $post->ID,
			'title'       => get_the_title( $post ),
			'thumbnail'   => get_the_post_thumbnail_url( $post, 'medium' ),
			'photos'      => $photos,
			'photo_count' => count( $photos ),
			'capacity'    => (int) get_post_meta( $post->ID, 'capacity', true ),
			'length'      => get_post_meta( $post->ID, 'length', true ),
			'cabins'      => (int) get_post_meta( $post->ID, 'cabins', true ),
			'classes'     => wp_get_post_terms( $post->ID, 'yacht_class', array( 'fields' => 'names' ) ),
			'occasions'   => wp_get_post_terms( $post->ID, 'yacht_occasion', array( 'fields' => 'names' ) ),
			'location'    => array(
				'name' => get_post_meta( $post->ID, 'location_name', true ),
				'lat'  => get_post_meta( $post->ID, 'location_lat', true ),
				'lng'  => get_post_meta( $post->ID, 'location_lng', true ),
			),
			'from_price' => self::from_price( $post->ID ),
			'status'     => $post->post_status,
			'slug'       => $post->post_name,
			'permalink'  => get_permalink( $post ),
		);
	}

	private static function full( $post ) {
		$data = self::summarize( $post );
		$data['description'] = $post->post_content;

		foreach ( \Ybs\PostTypes\Yacht::META_KEYS as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			$data[ $key ] = in_array( $key, array( 'gallery', 'faq', 'included_items', 'off_days' ), true )
				? ( is_array( $value ) ? $value : array() )
				: $value;
		}

		// Cast explicitly: wp_get_post_terms() can hand back numeric strings,
		// which would silently fail to match the JS side's real numbers
		// (e.g. `["3"].includes(3)` is false) and make selections look empty.
		$data['yacht_class']    = array_map( 'intval', wp_get_post_terms( $post->ID, 'yacht_class', array( 'fields' => 'ids' ) ) );
		$data['yacht_occasion'] = array_map( 'intval', wp_get_post_terms( $post->ID, 'yacht_occasion', array( 'fields' => 'ids' ) ) );

		$thumbnail_id             = get_post_thumbnail_id( $post->ID );
		$data['featured_media']   = $thumbnail_id ? (int) $thumbnail_id : 0;

		$data['gallery'] = array_values(
			array_filter(
				array_map(
					static function ( $attachment_id ) {
						$url = wp_get_attachment_image_url( $attachment_id, 'medium' );

						return $url ? array( 'id' => (int) $attachment_id, 'url' => $url ) : null;
					},
					$data['gallery']
				)
			)
		);

		return $data;
	}

	private static function from_price( $yacht_id ) {
		$prices = array_filter(
			array_map(
				static fn( $key ) => (float) get_post_meta( $yacht_id, $key, true ),
				array( 'base_price_hourly', 'base_price_halfday', 'base_price_morning_slot', 'base_price_evening_slot', 'base_price_daily' )
			)
		);

		return $prices ? min( $prices ) : 0.0;
	}

	private static function haversine( $lat1, $lng1, $lat2, $lng2 ) {
		if ( ! $lat2 || ! $lng2 ) {
			return null;
		}

		$earth_radius = 6371;
		$d_lat        = deg2rad( $lat2 - $lat1 );
		$d_lng        = deg2rad( $lng2 - $lng1 );

		$a = sin( $d_lat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lng / 2 ) ** 2;
		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return round( $earth_radius * $c, 1 );
	}
}
