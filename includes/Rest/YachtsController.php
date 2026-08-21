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
	}

	public static function quote( WP_REST_Request $request ) {
		$yacht_id     = (int) $request['id'];
		$booking_type = sanitize_key( $request->get_param( 'booking_type' ) );
		$start        = sanitize_text_field( $request->get_param( 'start_datetime' ) );
		$end          = sanitize_text_field( $request->get_param( 'end_datetime' ) );
		$guest_count  = max( 1, (int) $request->get_param( 'guest_count' ) ?: 1 );
		$booking_mode = sanitize_key( $request->get_param( 'booking_mode' ) ) ?: ( get_post_meta( $yacht_id, 'booking_mode', true ) ?: 'full' );

		if ( ! $start || ! $end ) {
			return new WP_Error( 'ybs_invalid_dates', __( 'Please choose a date and time.', 'yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$availability = AvailabilityService::check( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode );

		if ( ! $availability['available'] ) {
			return new WP_Error( 'ybs_not_available', $availability['reason'], array( 'status' => 409 ) );
		}

		$pricing = \Ybs\Booking\PricingEngine::calculate( $yacht_id, $booking_type, $start, $end, $guest_count );

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
		$month    = sanitize_text_field( $request->get_param( 'month' ) ?: current_time( 'Y-m' ) );
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
			'posts_per_page' => (int) $request->get_param( 'per_page' ) ?: 20,
			'paged'          => (int) $request->get_param( 'page' ) ?: 1,
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
				'items' => $items,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
			)
		);
	}

	public static function show( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post || Yacht::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ybs_not_found', __( 'Yacht not found.', 'yacht-booking-system' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( self::full( $post ) );
	}

	public static function create( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		$post_id = wp_insert_post(
			array(
				'post_type'    => Yacht::POST_TYPE,
				'post_title'   => sanitize_text_field( $data['title'] ?? __( 'Untitled Yacht', 'yacht-booking-system' ) ),
				'post_content' => wp_kses_post( $data['description'] ?? '' ),
				'post_status'  => sanitize_key( $data['status'] ?? 'draft' ),
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
			return new WP_Error( 'ybs_not_found', __( 'Yacht not found.', 'yacht-booking-system' ), array( 'status' => 404 ) );
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
			return new WP_Error( 'ybs_not_found', __( 'Yacht not found.', 'yacht-booking-system' ), array( 'status' => 404 ) );
		}

		wp_delete_post( $post->ID, true );

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function availability( WP_REST_Request $request ) {
		$yacht_id = (int) $request['id'];
		$date     = sanitize_text_field( $request->get_param( 'date' ) ?: current_time( 'Y-m-d' ) );

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
				update_post_meta( $post_id, $key, is_array( $value ) ? $value : array() );
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
		return array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'thumbnail'  => get_the_post_thumbnail_url( $post, 'medium' ),
			'capacity'   => (int) get_post_meta( $post->ID, 'capacity', true ),
			'classes'    => wp_get_post_terms( $post->ID, 'yacht_class', array( 'fields' => 'names' ) ),
			'occasions'  => wp_get_post_terms( $post->ID, 'yacht_occasion', array( 'fields' => 'names' ) ),
			'location'   => array(
				'name' => get_post_meta( $post->ID, 'location_name', true ),
				'lat'  => get_post_meta( $post->ID, 'location_lat', true ),
				'lng'  => get_post_meta( $post->ID, 'location_lng', true ),
			),
			'from_price' => self::from_price( $post->ID ),
			'status'     => $post->post_status,
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
