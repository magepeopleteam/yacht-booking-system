<?php
namespace Ybs\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The `yacht` CPT. All structured data (capacity, pricing, location, FAQ...)
 * lives in postmeta rather than post content - the React wizard reads/writes
 * it wholesale through the REST controller, never through the classic editor.
 */
class Yacht {

	const POST_TYPE = 'yacht';

	/**
	 * Every postmeta key the wizard's four steps read/write, per spec section 3.
	 */
	const META_KEYS = array(
		'capacity',
		'cabins',
		'crew_size',
		'length',
		'build_year',
		'location_name',
		'location_lat',
		'location_lng',
		'base_price_hourly',
		'base_price_daily',
		'base_price_multiday',
		'base_price_halfday',
		'base_price_morning_slot',
		'base_price_evening_slot',
		'min_notice_hours',
		'buffer_minutes',
		'min_duration',
		'max_duration',
		'booking_mode',
		'daily_start_time',
		'daily_end_time',
		'halfday_start_time',
		'halfday_end_time',
		'morning_slot_start',
		'morning_slot_end',
		'evening_slot_start',
		'evening_slot_end',
		'gallery',
		'faq',
		'included_items',
		'off_days',
	);

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Yachts', 'yacht-booking-system' ),
					'singular_name'      => __( 'Yacht', 'yacht-booking-system' ),
					'add_new_item'       => __( 'Add New Yacht', 'yacht-booking-system' ),
					'edit_item'          => __( 'Edit Yacht', 'yacht-booking-system' ),
					'view_item'          => __( 'View Yacht', 'yacht-booking-system' ),
					'search_items'       => __( 'Search Yachts', 'yacht-booking-system' ),
					'not_found'          => __( 'No yachts found', 'yacht-booking-system' ),
					'not_found_in_trash' => __( 'No yachts found in Trash', 'yacht-booking-system' ),
				),
				'public'        => true,
				'show_ui'       => false, // Managed entirely by the React admin app, not the classic editor.
				'show_in_menu'  => false,
				'show_in_rest'  => true,
				'rest_base'     => 'yacht-posts',
				'has_archive'   => true,
				'rewrite'       => array( 'slug' => 'yachts' ),
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'menu_icon'     => 'dashicons-palmtree',
				'capability_type' => array( 'yacht', 'yachts' ),
				'map_meta_cap'  => true,
			)
		);

		$object_list_keys  = array( 'faq', 'included_items' );
		$string_list_keys  = array( 'off_days' );
		$integer_list_keys = array( 'gallery' ); // Attachment IDs from the WP media library, not URLs.

		foreach ( self::META_KEYS as $key ) {
			$is_object_list  = in_array( $key, $object_list_keys, true );
			$is_string_list  = in_array( $key, $string_list_keys, true );
			$is_integer_list = in_array( $key, $integer_list_keys, true );
			$is_list         = $is_object_list || $is_string_list || $is_integer_list;

			$show_in_rest = true;

			if ( $is_object_list ) {
				$show_in_rest = array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) );
			} elseif ( $is_string_list ) {
				$show_in_rest = array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) );
			} elseif ( $is_integer_list ) {
				$show_in_rest = array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) );
			}

			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'single'        => true,
					'show_in_rest'  => $show_in_rest,
					'type'          => $is_list ? 'array' : 'string',
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * The configured [start, end] clock-time window for each fixed-window
	 * booking type (half-day, morning/evening slot, daily), falling back to
	 * the same defaults the availability check has always used so an
	 * un-configured yacht keeps working exactly as before.
	 *
	 * @return array<string, array{0:string,1:string}> Keyed by booking type, "HH:MM" pairs.
	 */
	public static function time_windows( $yacht_id ) {
		$defaults = array(
			'half_day'     => array( '08:00', '12:00' ),
			'morning_slot' => array( '08:00', '13:00' ),
			'evening_slot' => array( '15:00', '20:00' ),
			'daily'        => array( '08:00', '20:00' ),
		);

		$meta_keys = array(
			'half_day'     => array( 'halfday_start_time', 'halfday_end_time' ),
			'morning_slot' => array( 'morning_slot_start', 'morning_slot_end' ),
			'evening_slot' => array( 'evening_slot_start', 'evening_slot_end' ),
			'daily'        => array( 'daily_start_time', 'daily_end_time' ),
		);

		$windows = array();

		foreach ( $defaults as $type => $default ) {
			list( $start_key, $end_key ) = $meta_keys[ $type ];

			$start = get_post_meta( $yacht_id, $start_key, true );
			$end   = get_post_meta( $yacht_id, $end_key, true );

			$windows[ $type ] = array(
				$start ?: $default[0],
				$end ?: $default[1],
			);
		}

		return $windows;
	}
}
