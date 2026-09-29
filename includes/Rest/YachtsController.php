<?php
namespace MageYaBo\Rest;

use MageYaBo\Booking\AvailabilityService;
use MageYaBo\Booking\PricingEngine;
use MageYaBo\PostTypes\Yacht;
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

	/**
	 * Maps the search bar's "Weekly charter / Day charter / Hourly charter"
	 * toggle to the booking-type price fields each one covers. "Day charter"
	 * spans every same-day booking type (half day, morning/evening slot,
	 * full day) rather than just `daily`, since a yacht priced only for a
	 * morning slot is still a same-day charter, not an hourly or multi-day one.
	 */
	const CHARTER_TYPE_PRICE_KEYS = array(
		'hourly' => array( 'mageyabo_base_price_hourly' ),
		'day'    => array( 'mageyabo_base_price_halfday', 'mageyabo_base_price_morning_slot', 'mageyabo_base_price_evening_slot', 'mageyabo_base_price_daily' ),
		'weekly' => array( 'mageyabo_base_price_multiday' ),
	);

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

		// The wizard has to load the operator's own configuration
		// (confirmation-email subject and body) to edit it, and the public
		// read route above deliberately withholds those fields. They are
		// served only through this capability-gated route.
		register_rest_route(
			self::NAMESPACE_,
			'/yachts/(?P<id>\d+)/edit',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'edit_fields' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
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
			'/yachts/(?P<id>\d+)/next-available',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'next_available' ),
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

	/**
	 * The first window from `from` onwards that this yacht can actually take
	 * for the given charter type, duration, guests and mode - so the booking
	 * form can default to a slot that is free instead of one that is booked,
	 * inside another booking's buffer, on an off day or inside the notice
	 * period. Runs the same AvailabilityService check and PricingEngine
	 * blocking rules the booking itself will, so it cannot disagree with them.
	 *
	 * Hourly starts are tried every 30 minutes across the yacht's daily
	 * window; slot types use their fixed window; multi-day starts at the
	 * daily window's start. Looks up to 60 days ahead.
	 */
	public static function next_available( WP_REST_Request $request ) {
		$yacht_id = (int) $request['id'];

		if ( Yacht::POST_TYPE !== get_post_type( $yacht_id ) || 'publish' !== get_post_status( $yacht_id ) ) {
			return new WP_Error( 'mageyabo_invalid_yacht', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		$types        = array( 'hourly', 'half_day', 'morning_slot', 'evening_slot', 'daily', 'multiday' );
		$booking_type = in_array( $request->get_param( 'booking_type' ), $types, true ) ? $request->get_param( 'booking_type' ) : 'hourly';
		$booking_mode = 'shared' === $request->get_param( 'booking_mode' ) ? 'shared' : 'full';
		$guest_count  = max( 1, (int) $request->get_param( 'guest_count' ) );
		$hours        = max( 0.5, min( 24, (float) ( $request->get_param( 'duration' ) ?: 2 ) ) );
		$nights       = max( 1, min( 30, (int) ( $request->get_param( 'nights' ) ?: 2 ) ) );
		$from         = (string) $request->get_param( 'from' );
		$today        = gmdate( 'Y-m-d' );
		$from         = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && $from > $today ? $from : $today;

		$windows = Yacht::time_windows( $yacht_id );
		list( $day_start, $day_end ) = $windows['daily'];

		$to_minutes = static function ( $hhmm ) {
			list( $h, $m ) = array_map( 'intval', explode( ':', $hhmm ) + array( 0, 0 ) );
			return $h * 60 + $m;
		};

		// What to try on each day, as [start, end] minute offsets from midnight.
		$candidates = array();

		if ( 'hourly' === $booking_type ) {
			$length = (int) round( $hours * 60 );
			for ( $start = $to_minutes( $day_start ); $start + $length <= $to_minutes( $day_end ); $start += 30 ) {
				$candidates[] = array( $start, $start + $length );
			}
		} elseif ( 'multiday' === $booking_type ) {
			$candidates[] = array( $to_minutes( $day_start ), $to_minutes( $day_start ) + $nights * 1440 );
		} else {
			list( $slot_start, $slot_end ) = $windows[ $booking_type ] ?? $windows['daily'];
			$candidates[] = array( $to_minutes( $slot_start ), $to_minutes( $slot_end ) );
		}

		if ( ! $candidates ) {
			return new WP_Error( 'mageyabo_not_available', __( 'No available time found for this charter.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		$day    = strtotime( $from . ' 00:00:00 UTC' );
		$checks = 0;

		for ( $d = 0; $d < 60 && $checks < 600; $d++, $day += DAY_IN_SECONDS ) {
			foreach ( $candidates as $window ) {
				$checks++;
				$start = gmdate( 'Y-m-d H:i:s', $day + $window[0] * 60 );
				$end   = gmdate( 'Y-m-d H:i:s', $day + $window[1] * 60 );

				$availability = AvailabilityService::check( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode );

				if ( ! $availability['available'] ) {
					continue;
				}

				// Off days and blocking pricing rules live in the pricing
				// engine, not the availability check.
				if ( is_wp_error( PricingEngine::calculate( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode ) ) ) {
					continue;
				}

				return rest_ensure_response(
					array(
						'start_datetime' => $start,
						'end_datetime'   => $end,
					)
				);
			}
		}

		return new WP_Error( 'mageyabo_not_available', __( 'No available time found in the next 60 days.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
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
		$booking_mode = sanitize_key( $request->get_param( 'booking_mode' ) ) ?: ( get_post_meta( $yacht_id, 'mageyabo_booking_mode', true ) ?: 'full' );

		if ( ! $start || ! $end ) {
			return new WP_Error( 'mageyabo_invalid_dates', __( 'Please choose a date and time.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$availability = AvailabilityService::check( $yacht_id, $booking_type, $start, $end, $guest_count, $booking_mode );

		if ( ! $availability['available'] ) {
			return new WP_Error( 'mageyabo_not_available', $availability['reason'], array( 'status' => 409 ) );
		}

		$pricing = \MageYaBo\Booking\PricingEngine::calculate(
			$yacht_id,
			$booking_type,
			$start,
			$end,
			$guest_count,
			$booking_mode,
			array(
				'addons'      => self::parse_addons_param( $request->get_param( 'addons' ) ),
				'coupon_code' => sanitize_text_field( (string) $request->get_param( 'coupon_code' ) ),
			)
		);

		if ( is_wp_error( $pricing ) ) {
			return $pricing;
		}

		return rest_ensure_response(
			array(
				'pricing'      => $pricing,
				'availability' => $availability,
				'currency'     => \MageYaBo\Settings::get( 'currency_symbol', '$' ),
			)
		);
	}

	/**
	 * The quote route is a GET, so the add-on selection arrives either as a
	 * bracketed array (`addons[3]=2`) or, when the caller is building a plain
	 * query string, as a compact `3:2,7:1` list. Both mean the same thing;
	 * the prices are looked up server-side either way.
	 *
	 * @return array<int, int> addon id => quantity.
	 */
	private static function parse_addons_param( $raw ) {
		if ( is_array( $raw ) ) {
			$parsed = array();

			foreach ( $raw as $addon_id => $quantity ) {
				$parsed[ (int) $addon_id ] = max( 0, (int) $quantity );
			}

			return $parsed;
		}

		$raw = trim( (string) $raw );

		if ( '' === $raw ) {
			return array();
		}

		$parsed = array();

		foreach ( explode( ',', $raw ) as $pair ) {
			list( $addon_id, $quantity ) = array_pad( explode( ':', $pair, 2 ), 2, 1 );

			$addon_id = (int) $addon_id;

			if ( $addon_id > 0 ) {
				$parsed[ $addon_id ] = max( 0, (int) $quantity );
			}
		}

		return $parsed;
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
			return new WP_Error( 'mageyabo_invalid_month', __( 'Please provide a month as YYYY-MM.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$booking_mode = get_post_meta( $yacht_id, 'mageyabo_booking_mode', true ) ?: 'full';

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
		$can_manage = \MageYaBo\Capabilities::can( 'settings' );
		$date       = sanitize_text_field( $request->get_param( 'date' ) );

		if ( $date ) {
			$parsed_date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
			if ( ! $parsed_date || $parsed_date->format( 'Y-m-d' ) !== $date ) {
				return new WP_Error( 'mageyabo_invalid_date', __( 'Please provide a valid date as YYYY-MM-DD.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
			}
			if ( $parsed_date < current_datetime()->setTime( 0, 0, 0 ) ) {
				return new WP_Error( 'mageyabo_past_date', __( 'Please choose today or a future date.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
			}
		}

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
				'taxonomy' => 'mageyabo_yacht_class',
				'field'    => 'slug',
				'terms'    => sanitize_title( $request->get_param( 'class' ) ),
			);
		}

		if ( $request->get_param( 'occasion' ) ) {
			$tax_query[] = array(
				'taxonomy' => 'mageyabo_yacht_occasion',
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
				'key'     => 'mageyabo_capacity',
				'value'   => (int) $request->get_param( 'guests' ),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		if ( $request->get_param( 'location' ) ) {
			$meta_query[] = array(
				'key'     => 'mageyabo_location_name',
				'value'   => sanitize_text_field( $request->get_param( 'location' ) ),
				'compare' => 'LIKE',
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

		// "Weekly/day/hourly charter" toggle on the search bar: re-price each
		// item off just that charter type's rate (instead of the cheapest rate
		// across all of them) and drop yachts that don't offer it at all.
		$charter_type = sanitize_key( $request->get_param( 'charter_type' ) );

		if ( isset( self::CHARTER_TYPE_PRICE_KEYS[ $charter_type ] ) ) {
			foreach ( $items as &$item ) {
				$item['from_price'] = self::from_price( $item['id'], self::CHARTER_TYPE_PRICE_KEYS[ $charter_type ] );
			}
			unset( $item );

			$items = array_values( array_filter( $items, static fn( $item ) => $item['from_price'] > 0 ) );
		}

		if ( $date ) {
			$guest_count = max( 1, absint( $request->get_param( 'guests' ) ) );
			$items       = array_values(
				array_filter(
					$items,
					static fn( $item ) => self::is_available_on_date( $item['id'], $date, $charter_type ?: 'day', $guest_count )
				)
			);
		}

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
				'dummy_seeded' => (bool) get_option( 'mageyabo_dummy_seeded' ),
			)
		);
	}

	/**
	 * Whether a yacht has at least one bookable slot on a search date.
	 *
	 * @param int    $yacht_id    Yacht post ID.
	 * @param string $date        Date in Y-m-d format.
	 * @param string $charter_type Search family: day, hourly, or weekly.
	 * @param int    $guest_count Number of guests requested.
	 * @return bool
	 */
	private static function is_available_on_date( $yacht_id, $date, $charter_type, $guest_count ) {
		$configured_mode = get_post_meta( $yacht_id, 'mageyabo_booking_mode', true ) ?: 'full';
		$modes           = 'both' === $configured_mode ? array( 'full', 'shared' ) : array( $configured_mode );
		$now             = current_datetime();

		if ( 'hourly' === $charter_type ) {
			$start = new \DateTimeImmutable( $date . ' 10:00:00', wp_timezone() );
			if ( $date === $now->format( 'Y-m-d' ) && $start <= $now ) {
				$notice_hours = max( 1, (int) get_post_meta( $yacht_id, 'mageyabo_min_notice_hours', true ) );
				$candidate    = $now->modify( '+' . $notice_hours . ' hours' );
				$start        = $candidate->setTime( (int) $candidate->format( 'H' ), 0, 0 );
				if ( $start < $candidate ) {
					$start = $start->modify( '+1 hour' );
				}
			}

			$duration = max( 120, (int) get_post_meta( $yacht_id, 'mageyabo_min_duration', true ) );
			$end      = $start->modify( '+' . $duration . ' minutes' );
			if ( $end->format( 'Y-m-d' ) !== $date ) {
				return false;
			}

			return self::slot_is_available( $yacht_id, 'hourly', 'hourly', $start, $end, $guest_count, $modes );
		}

		if ( 'weekly' === $charter_type ) {
			$start = new \DateTimeImmutable( $date . ' 08:00:00', wp_timezone() );
			$end   = $start->modify( '+7 days' );
			return self::slot_is_available( $yacht_id, 'multiday', 'multiday', $start, $end, $guest_count, $modes );
		}

		$windows = Yacht::time_windows( $yacht_id );
		$slots   = array(
			array( 'half_day', 'halfday', $windows['half_day'] ),
			array( 'morning_slot', 'morning_slot', $windows['morning_slot'] ),
			array( 'evening_slot', 'evening_slot', $windows['evening_slot'] ),
			array( 'daily', 'daily', $windows['daily'] ),
		);

		foreach ( $slots as $slot ) {
			list( $booking_type, $rate_key, $window ) = $slot;
			$start = new \DateTimeImmutable( $date . ' ' . $window[0] . ':00', wp_timezone() );
			$end   = new \DateTimeImmutable( $date . ' ' . $window[1] . ':00', wp_timezone() );

			if ( $date === $now->format( 'Y-m-d' ) && $start <= $now ) {
				continue;
			}
			if ( self::slot_is_available( $yacht_id, $booking_type, $rate_key, $start, $end, $guest_count, $modes ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check a priced slot against each booking mode supported by the yacht.
	 *
	 * @param int                $yacht_id    Yacht post ID.
	 * @param string             $booking_type Booking type passed to availability.
	 * @param string             $rate_key    Suffix used by the stored rate meta.
	 * @param \DateTimeImmutable $start       Slot start.
	 * @param \DateTimeImmutable $end         Slot end.
	 * @param int                $guest_count Requested capacity.
	 * @param string[]           $modes       Supported booking modes.
	 * @return bool
	 */
	private static function slot_is_available( $yacht_id, $booking_type, $rate_key, $start, $end, $guest_count, $modes ) {
		foreach ( $modes as $mode ) {
			$price_key = 'shared' === $mode ? 'mageyabo_base_price_shared_' . $rate_key : 'mageyabo_base_price_' . $rate_key;
			if ( (float) get_post_meta( $yacht_id, $price_key, true ) <= 0 ) {
				continue;
			}

			$availability = AvailabilityService::check(
				$yacht_id,
				$booking_type,
				$start->format( 'Y-m-d H:i:s' ),
				$end->format( 'Y-m-d H:i:s' ),
				$guest_count,
				$mode
			);
			if ( $availability['available'] ) {
				return true;
			}
		}

		return false;
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
			return new WP_Error( 'mageyabo_not_found', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		if ( 'publish' !== $post->post_status && ! \MageYaBo\Capabilities::can( 'settings' ) ) {
			return new WP_Error( 'mageyabo_not_found', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	public static function show( WP_REST_Request $request ) {
		$post = self::get_readable_yacht( (int) $request['id'] );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Public route: always strip admin-only fields regardless of who is
		// asking. Admin-only data (confirmation-email subject/body) is only
		// returned through the capability-gated edit/create/update routes.
		return rest_ensure_response( self::public_full( $post ) );
	}

	/**
	 * Capability-gated read for the yacht wizard: the same payload as show()
	 * plus the admin-only fields the wizard edits.
	 */
	public static function edit_fields( WP_REST_Request $request ) {
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
			return new WP_Error( 'mageyabo_not_found', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
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
			return new WP_Error( 'mageyabo_not_found', __( 'Yacht not found.', 'magepeople-yacht-booking-system' ), array( 'status' => 404 ) );
		}

		wp_delete_post( $post->ID, true );

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * One-time sample-fleet seeder so a fresh install has something to look
	 * at immediately. The `mageyabo_dummy_seeded` option makes this permanently
	 * unavailable once it has run - both the guard below and the admin UI's
	 * button (hidden once `dummy_seeded` comes back true) rely on it.
	 */
	public static function dummy_import() {
		if ( get_option( 'mageyabo_dummy_seeded' ) ) {
			return new WP_Error( 'mageyabo_already_seeded', __( 'Sample yachts have already been imported.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$imported    = 0;
		$created_ids = array();

		foreach ( self::dummy_yacht_samples() as $sample ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => Yacht::POST_TYPE,
					'post_title'   => $sample['title'],
					'post_content' => $sample['description'],
					'post_excerpt' => $sample['excerpt'] ?? '',
					'post_status'  => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				self::rollback_dummy_yachts( $created_ids );
				return new WP_Error( 'mageyabo_dummy_import_failed', __( 'The sample fleet could not be imported. Please try again.', 'magepeople-yacht-booking-system' ), array( 'status' => 500 ) );
			}
			$created_ids[] = (int) $post_id;

			self::save_meta( $post_id, $sample['meta'] );

			$gallery_ids = self::import_dummy_gallery( $post_id, $sample['gallery'], $sample['title'] );
			if ( count( $gallery_ids ) < 5 ) {
				self::rollback_dummy_yachts( $created_ids );
				return new WP_Error( 'mageyabo_dummy_media_failed', __( 'The sample yacht photos could not be imported. Check that WordPress can write to the uploads directory, then try again.', 'magepeople-yacht-booking-system' ), array( 'status' => 500 ) );
			}

			$class_id = self::dummy_term_id( $sample['class'], 'mageyabo_yacht_class' );

			if ( $class_id ) {
				wp_set_object_terms( $post_id, array( $class_id ), 'mageyabo_yacht_class' );
			}

			$occasion_ids = array();

			foreach ( $sample['occasions'] as $occasion_name ) {
				$term_id = self::dummy_term_id( $occasion_name, 'mageyabo_yacht_occasion' );

				if ( $term_id ) {
					$occasion_ids[] = $term_id;
				}
			}

			if ( $occasion_ids ) {
				wp_set_object_terms( $post_id, $occasion_ids, 'mageyabo_yacht_occasion' );
			}

			$imported++;
		}

		// Cross-link every sample to the others so "You might also like"
		// has something to show immediately - the same-class fallback alone
		// often comes up short here, since the 6 samples span 5 classes.
		foreach ( $created_ids as $post_id ) {
			$others = array_values( array_diff( $created_ids, array( $post_id ) ) );
			self::save_meta( $post_id, array( 'related_yachts' => $others ) );
		}

		update_option( 'mageyabo_dummy_seeded', 1 );

		// A site that activated the plugin before this page existed (or
		// whose copy was since deleted) still ends up with somewhere to
		// see the fleet it just seeded.
		$yacht_list_page_id = \MageYaBo\Install\Migrator::create_yacht_list_page();

		return rest_ensure_response(
			array(
				'imported'           => $imported,
				'dummy_seeded'       => true,
				'yacht_list_page_id' => $yacht_list_page_id,
			)
		);
	}

	/**
	 * A sample yacht's class or occasion term, created when a site has
	 * deleted it or never had it ("Party" is not a default occasion).
	 *
	 * @param string $name     Term name.
	 * @param string $taxonomy Taxonomy.
	 * @return int Term ID, or 0 on failure.
	 */
	private static function dummy_term_id( $name, $taxonomy ) {
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term ) {
			return (int) $term->term_id;
		}

		$created = wp_insert_term( $name, $taxonomy );

		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	/**
	 * The sample fleet: the same six yachts, copy, rates and booking rules as
	 * the Yachtiva theme's demo content, so both import paths produce an
	 * identical, fully described fleet.
	 *
	 * @return array[]
	 */
	private static function dummy_yacht_samples() {
		return array(
			array(
				'title'       => __( 'Ocean Breeze', 'magepeople-yacht-booking-system' ),
				'photo'       => 'ocean-breeze.jpg',
				'gallery'     => array( 'ocean-breeze.jpg', 'ocean-breeze-02.jpg', 'ocean-breeze-03.jpg', 'ocean-breeze-04.jpg', 'ocean-breeze-05.jpg' ),
				'excerpt'     => __( 'A sleek 42ft motor yacht perfect for sunset cruises and small celebrations along the coast.', 'magepeople-yacht-booking-system' ),
				'description' => __( '<p>Ocean Breeze is a 42-foot motor yacht built in 2018 and based at Miami Marina, a short walk from the restaurants and rooftop bars of downtown Miami. She is sized for the kind of afternoon most people picture when they think of a day on the water: a small group of friends or family, a cooler of drinks, music on the deck speakers and nothing on the schedule except the next swim stop. With room for up to twelve guests and a crew of two, she feels relaxed rather than crowded, and she is just as easy to book for a couple of hours as for a full day.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>On board</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>The layout keeps everyone together. A wide cockpit with shaded bench seating opens onto the swim platform at the stern, while the forward sun pad is the place to stretch out once the yacht is underway. Below deck there are two private cabins and a head with a freshwater shower, so guests can change after a swim or take a quiet break out of the sun. The air-conditioned saloon has a Bluetooth sound system and a galley with a fridge and ice maker that keeps drinks cold through the longest afternoon.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Where you will go</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Most charters head out across Biscayne Bay for skyline views off Brickell and a slow pass along the waterfront homes of Star Island, then anchor at one of the bay’s sandbars where the water turns clear and shallow. On a full-day charter the captain can run further south toward Key Biscayne and its quieter anchorages. Sunset cruises are timed so the city lights come on as you glide back into the marina.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Best for</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Ocean Breeze is a favourite for birthdays, small celebrations and sunset cocktails. She sits in the Comfort class, which makes her one of the most approachable yachts in the fleet, with hourly, half-day, full-day and shared-seat options. Your captain and crew handle the navigation, anchoring and safety briefing, so all you need to bring is sunscreen, a swimsuit and the people you want to spend the day with.</p>', 'magepeople-yacht-booking-system' ),
				'class'       => 'Comfort',
				'occasions'   => array( 'Birthday', 'Sunset Cocktail' ),
				'meta'        => array(
					'capacity'                       => '12',
					'cabins'                         => '2',
					'crew_size'                      => '2',
					'length'                         => '42',
					'build_year'                     => '2018',
					'location_name'                  => 'Miami Marina, FL',
					'location_lat'                   => '25.7781',
					'location_lng'                   => '-80.1867',
					'base_price_hourly'              => '150',
					'base_price_halfday'             => '350',
					'base_price_morning_slot'        => '390',
					'base_price_evening_slot'        => '440',
					'base_price_daily'               => '500',
					'base_price_multiday'            => '450',
					'base_price_shared_hourly'       => '20',
					'base_price_shared_halfday'      => '45',
					'base_price_shared_morning_slot' => '50',
					'base_price_shared_evening_slot' => '55',
					'base_price_shared_daily'        => '65',
					'base_price_shared_multiday'     => '55',
					'booking_mode'                   => 'both',
					'min_notice_hours'               => '24',
					'buffer_minutes'                 => '60',
					'min_duration'                   => '120',
					'max_duration'                   => '480',
					'daily_start_time'               => '08:00',
					'daily_end_time'                 => '20:00',
					'halfday_start_time'             => '08:00',
					'halfday_end_time'               => '12:00',
					'morning_slot_start'             => '08:00',
					'morning_slot_end'               => '13:00',
					'evening_slot_start'             => '15:00',
					'evening_slot_end'               => '20:00',
					'included_items'                 => array(
						array( 'text' => __( 'Professional captain and deckhand', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Life jackets and safety equipment for every guest', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bottled water, ice and soft drinks', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Beach towels', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bluetooth sound system', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Swim platform with boarding ladder', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Freshwater shower', 'magepeople-yacht-booking-system' ) ),
					),
					'faq'                            => array(
						array(
							'question' => __( 'Can we bring our own food and drinks?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Yes. You are welcome to bring snacks, a picnic or drinks; the galley fridge and ice maker keep everything cold. Please avoid red wine and glass bottles on deck, and let us know when you book if you would like catering arranged for you.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Is Ocean Breeze a good fit for children?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'She is. Children’s life jackets are on board, the cockpit has high sides and the crew keeps swim stops to calm, shallow water. Just include the children in your guest count when you book.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Which slot is best for sunset?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Choose the evening slot. It leaves the marina mid-afternoon, anchors for a swim, and times the return across Biscayne Bay for the sunset over the Miami skyline.', 'magepeople-yacht-booking-system' ),
						),
					),
				),
			),
			array(
				'title'       => __( 'Sapphire Horizon', 'magepeople-yacht-booking-system' ),
				'photo'       => 'sapphire-horizon.jpg',
				'gallery'     => array( 'sapphire-horizon.jpg', 'sapphire-horizon-02.jpg', 'sapphire-horizon-03.jpg', 'sapphire-horizon-04.jpg', 'sapphire-horizon-05.jpg' ),
				'excerpt'     => __( 'A 68ft flagship with three decks, a certified crew of five, and range for full-week charters.', 'magepeople-yacht-booking-system' ),
				'description' => __( '<p>Sapphire Horizon is the flagship of the fleet: a 68-foot, three-deck motor yacht built in 2021 and berthed in Port Hercule, the deep-water harbour at the foot of Monaco’s old town. Everything about her is designed for occasions that need to go right the first time, from client hospitality during the Grand Prix season to milestone celebrations and full-week cruises along the Riviera. She welcomes up to thirty guests for day charters and is run by a certified crew of five, including a captain, a chef-steward and dedicated deck and interior crew.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>On board</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>The main deck saloon is laid out like a private lounge, with sofas, a formal dining table and floor-to-ceiling windows that frame the coastline. Up top, the flybridge has a second helm, a wet bar and a large sun deck with shaded seating, while the foredeck offers a quieter spot with sun pads. Four en-suite cabins give guests somewhere to rest or change, and the swim platform lowers to water level for easy access to the sea toys.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Where you will go</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>From Monaco the obvious first stop is the calm bay beneath Cap-d’Ail, but Sapphire Horizon has the range to go much further. Day charters often cruise west past Cap Ferrat and Villefranche for lunch at anchor, or east toward the Italian border and Menton. On multi-day charters the crew can plan routes to Saint-Tropez, Cannes and the Îles de Lérins, adjusting each day to the weather and your plans.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Best for</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>This First Class yacht is built for corporate events, executive retreats and celebrations where service matters as much as the view. Tell us about your guests and your schedule when you book, and the crew will prepare the yacht, the route and the menu around them.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Good to know</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Because the crew prepares the yacht and provisions in advance, Sapphire Horizon needs at least 48 hours’ notice. Hourly charters run for a minimum of three hours, which is the shortest time that does the coastline justice. Soft-soled shoes are best on the teak decks, and a light layer is useful on the flybridge once the sun goes down.</p>', 'magepeople-yacht-booking-system' ),
				'class'       => 'First Class',
				'occasions'   => array( 'Corporate' ),
				'meta'        => array(
					'capacity'                       => '30',
					'cabins'                         => '4',
					'crew_size'                      => '5',
					'length'                         => '68',
					'build_year'                     => '2021',
					'location_name'                  => 'Port Hercule, Monaco',
					'location_lat'                   => '43.7347',
					'location_lng'                   => '7.4215',
					'base_price_hourly'              => '450',
					'base_price_halfday'             => '1100',
					'base_price_morning_slot'        => '1210',
					'base_price_evening_slot'        => '1380',
					'base_price_daily'               => '1800',
					'base_price_multiday'            => '1620',
					'base_price_shared_hourly'       => '25',
					'base_price_shared_halfday'      => '55',
					'base_price_shared_morning_slot' => '60',
					'base_price_shared_evening_slot' => '70',
					'base_price_shared_daily'        => '90',
					'base_price_shared_multiday'     => '80',
					'booking_mode'                   => 'both',
					'min_notice_hours'               => '48',
					'buffer_minutes'                 => '90',
					'min_duration'                   => '180',
					'max_duration'                   => '480',
					'daily_start_time'               => '08:00',
					'daily_end_time'                 => '20:00',
					'halfday_start_time'             => '08:00',
					'halfday_end_time'               => '12:00',
					'morning_slot_start'             => '08:00',
					'morning_slot_end'               => '13:00',
					'evening_slot_start'             => '15:00',
					'evening_slot_end'               => '20:00',
					'included_items'                 => array(
						array( 'text' => __( 'Captain and certified crew of five, including a chef-steward', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Welcome champagne and canapés', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bottled water, soft drinks and fresh fruit', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Life jackets and full safety equipment', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bath and beach towels', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Snorkelling equipment and paddleboards', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Premium sound system on every deck', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Onboard Wi-Fi', 'magepeople-yacht-booking-system' ) ),
					),
					'faq'                            => array(
						array(
							'question' => __( 'Can we host a presentation or meeting on board?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Yes. The main saloon seats a working group comfortably and has a screen and Wi-Fi for presentations. Mention it when you book so the crew can set the room up before you board.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Is a chef included?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Your crew includes a chef-steward who prepares light meals and canapés. For a plated lunch or dinner, share your menu preferences and any dietary needs when you book and the crew will confirm the options.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Can Sapphire Horizon be chartered for several days?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'She can. Choose the multi-day option in the booking form; the crew then plans the route and overnight anchorages with you before departure.', 'magepeople-yacht-booking-system' ),
						),
					),
				),
			),
			array(
				'title'       => __( 'Island Serenade', 'magepeople-yacht-booking-system' ),
				'photo'       => 'island-serenade.jpg',
				'gallery'     => array( 'island-serenade.jpg', 'island-serenade-02.jpg', 'island-serenade-03.jpg', 'island-serenade-04.jpg', 'island-serenade-05.jpg' ),
				'excerpt'     => __( 'An easy-going 36ft catamaran built for Balearic afternoons — swim platform, sun deck, cold drinks.', 'magepeople-yacht-booking-system' ),
				'description' => __( '<p>Island Serenade is a 36-foot sailing catamaran built in 2019 and moored at Marina Ibiza, just across the water from the old town of Dalt Vila. Catamarans are made for the Balearic summer: two hulls keep her stable and level at anchor, the wide deck gives everyone space to spread out, and her shallow draught lets the captain take you closer to the beaches than most yachts can manage. She carries up to ten guests with a crew of two, which keeps the day relaxed and personal.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>On board</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>At the stern, a shaded cockpit with a dining table is the natural place to gather for lunch or cold drinks. Forward, the trampoline nets between the hulls are the best seat on the boat, hanging just above the water as you sail. Two cabins and a bathroom sit in the hulls below, and twin swim platforms with ladders make getting in and out of the sea easy for every age. Snorkel masks and a stand-up paddleboard are on board for the swim stops.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Where you will go</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>The classic Island Serenade day crosses to Formentera, anchoring off the white sand and turquoise water of Ses Illetes before sailing home in the afternoon breeze. Shorter charters explore the coves along Ibiza’s south coast, from Cala Jondal to Es Cavallet. Sunset sails head west toward Es Vedrà, the rocky islet that turns gold as the sun goes down.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Best for</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>In the Comfort Plus class, Island Serenade suits couples, families and small groups who want a slower, more natural day at sea, with the sails up when the wind allows. She is especially popular for sunset cocktails, so book the evening slot early in high season.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Good to know</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Hourly charters run for between two and six hours, which covers everything from a quick swim to a long lazy afternoon. The deck is barefoot, so leave your shoes in the basket at the stern when you board. Bring a hat and reef-safe sunscreen, as there is plenty of open deck and the Mediterranean sun is strong.</p>', 'magepeople-yacht-booking-system' ),
				'class'       => 'Comfort Plus',
				'occasions'   => array( 'Sunset Cocktail' ),
				'meta'        => array(
					'capacity'                       => '10',
					'cabins'                         => '2',
					'crew_size'                      => '2',
					'length'                         => '36',
					'build_year'                     => '2019',
					'location_name'                  => 'Marina Ibiza, Spain',
					'location_lat'                   => '38.9163',
					'location_lng'                   => '1.4431',
					'base_price_hourly'              => '120',
					'base_price_halfday'             => '280',
					'base_price_morning_slot'        => '310',
					'base_price_evening_slot'        => '350',
					'base_price_daily'               => '400',
					'base_price_multiday'            => '360',
					'base_price_shared_hourly'       => '20',
					'base_price_shared_halfday'      => '40',
					'base_price_shared_morning_slot' => '45',
					'base_price_shared_evening_slot' => '55',
					'base_price_shared_daily'        => '60',
					'base_price_shared_multiday'     => '55',
					'booking_mode'                   => 'both',
					'min_notice_hours'               => '24',
					'buffer_minutes'                 => '60',
					'min_duration'                   => '120',
					'max_duration'                   => '360',
					'daily_start_time'               => '08:00',
					'daily_end_time'                 => '20:00',
					'halfday_start_time'             => '08:00',
					'halfday_end_time'               => '12:00',
					'morning_slot_start'             => '08:00',
					'morning_slot_end'               => '13:00',
					'evening_slot_start'             => '15:00',
					'evening_slot_end'               => '20:00',
					'included_items'                 => array(
						array( 'text' => __( 'Skipper and deckhand', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Life jackets and safety equipment for every guest', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bottled water, ice and soft drinks', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Snorkel masks and fins', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Stand-up paddleboard', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Beach towels', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bluetooth speaker', 'magepeople-yacht-booking-system' ) ),
					),
					'faq'                            => array(
						array(
							'question' => __( 'Can we sail to Formentera?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Yes, on a full-day charter. The crossing takes about an hour each way, which leaves plenty of time at anchor off Ses Illetes. Half-day and slot charters stay along the Ibiza coast.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Will we actually sail?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Whenever the wind allows, yes. On calm days the catamaran motors between stops, and your skipper will hoist the sails as soon as there is a useful breeze.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Is a catamaran better if someone gets seasick?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Usually, yes. Two hulls roll far less than a single hull, especially at anchor, so a catamaran is one of the most comfortable options for guests who are new to the sea.', 'magepeople-yacht-booking-system' ),
						),
					),
				),
			),
			array(
				'title'       => __( 'Golden Mirage', 'magepeople-yacht-booking-system' ),
				'photo'       => 'golden-mirage.jpg',
				'gallery'     => array( 'golden-mirage.jpg', 'golden-mirage-02.jpg', 'golden-mirage-03.jpg', 'golden-mirage-04.jpg', 'golden-mirage-05.jpg' ),
				'excerpt'     => __( 'A corporate-grade 55ft sport yacht with conference lounge, Wi-Fi, and valet marina pickup.', 'magepeople-yacht-booking-system' ),
				'description' => __( '<p>Golden Mirage is a 55-foot sport yacht built in 2020 and based in Dubai Marina, designed for guests who want to mix business with the best views in the city. She pairs the clean lines and speed of a sport yacht with an interior set up for working: a conference lounge with a screen, fast Wi-Fi throughout and quiet air-conditioned cabins. Up to twenty guests can come aboard, looked after by a crew of three, and valet pickup at the marina means your group can arrive straight from the office.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>On board</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>The main saloon doubles as a meeting room, with a table that seats a working group and a display for presentations or video calls. Outside, the aft deck is shaded for daytime cruising and becomes a lounge for drinks in the evening, while the bow sun pads offer the best seats on the way past the skyline. Three cabins below give guests privacy to change or step away for a call.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Where you will go</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Most cruises leave Dubai Marina and run along the fronds of Palm Jumeirah to the Atlantis hotel, then turn back toward the sail-shaped Burj Al Arab for photographs from the water. Longer charters continue to the Ain Dubai observation wheel at Bluewaters Island or anchor off Jumeirah Beach for a swim. After dark, the marina skyline lights up and makes a striking backdrop for client dinners.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Best for</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Golden Mirage belongs to the Business class and is the fleet’s first choice for corporate hospitality, team outings, product launches and client entertaining. Share your agenda when you book and the crew will time the route around your meeting, then switch the yacht over to hospitality mode when the work is done.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Good to know</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Golden Mirage needs 48 hours’ notice so the crew can set up the conference lounge, arrange valet parking and confirm any catering. Hourly charters start at two hours. Smart-casual dress works well for most corporate events, and the air-conditioned saloon stays comfortable even in the height of the Dubai summer.</p>', 'magepeople-yacht-booking-system' ),
				'class'       => 'Business',
				'occasions'   => array( 'Corporate' ),
				'meta'        => array(
					'capacity'                       => '20',
					'cabins'                         => '3',
					'crew_size'                      => '3',
					'length'                         => '55',
					'build_year'                     => '2020',
					'location_name'                  => 'Dubai Marina, UAE',
					'location_lat'                   => '25.0805',
					'location_lng'                   => '55.1403',
					'base_price_hourly'              => '320',
					'base_price_halfday'             => '750',
					'base_price_morning_slot'        => '830',
					'base_price_evening_slot'        => '940',
					'base_price_daily'               => '1200',
					'base_price_multiday'            => '1080',
					'base_price_shared_hourly'       => '25',
					'base_price_shared_halfday'      => '55',
					'base_price_shared_morning_slot' => '60',
					'base_price_shared_evening_slot' => '70',
					'base_price_shared_daily'        => '90',
					'base_price_shared_multiday'     => '80',
					'booking_mode'                   => 'both',
					'min_notice_hours'               => '48',
					'buffer_minutes'                 => '60',
					'min_duration'                   => '120',
					'max_duration'                   => '480',
					'daily_start_time'               => '08:00',
					'daily_end_time'                 => '20:00',
					'halfday_start_time'             => '08:00',
					'halfday_end_time'               => '12:00',
					'morning_slot_start'             => '08:00',
					'morning_slot_end'               => '13:00',
					'evening_slot_start'             => '15:00',
					'evening_slot_end'               => '20:00',
					'included_items'                 => array(
						array( 'text' => __( 'Captain and crew of three', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Valet pickup at Dubai Marina', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'High-speed onboard Wi-Fi', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Conference lounge with presentation screen', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bottled water, soft drinks and coffee service', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Life jackets and safety equipment', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Towels and air-conditioned cabins', 'magepeople-yacht-booking-system' ) ),
					),
					'faq'                            => array(
						array(
							'question' => __( 'Is the Wi-Fi fast enough for video calls?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'The yacht carries a marine internet system that handles video calls well near the coast. Coverage can drop further offshore, so let the crew know about any important call and they will keep the route close to shore at that time.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'How does valet pickup work?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Give your arrival time when you book. A crew member meets your group at the marina entrance, takes care of your cars and luggage, and walks you to the berth.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Can we arrange catering for a client event?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Yes. Add catering while you book or mention your requirements, and the crew will confirm menus that suit your guests, including halal and vegetarian options.', 'magepeople-yacht-booking-system' ),
						),
					),
				),
			),
			array(
				'title'       => __( 'Aegean Muse', 'magepeople-yacht-booking-system' ),
				'photo'       => 'aegean-muse.jpg',
				'gallery'     => array( 'aegean-muse.jpg', 'aegean-muse-02.jpg', 'aegean-muse-03.jpg', 'aegean-muse-04.jpg', 'aegean-muse-05.jpg' ),
				'excerpt'     => __( 'A Cyclades classic for island-hopping days — shaded aft deck, freshwater swim shower, snorkel kit.', 'magepeople-yacht-booking-system' ),
				'description' => __( '<p>Aegean Muse is a 48-foot motor yacht built in 2017 and based at Vlychada Marina on the quiet south coast of Santorini, away from the crowds of the caldera ports. She has the classic lines of a Cycladic cruiser and the practical touches that matter on a long island day: a generously shaded aft deck, a freshwater swim shower and a full set of snorkelling gear. Up to fourteen guests can join her, with a captain and deckhand who know every cove on the island.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>On board</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>The aft deck is the heart of the yacht, with a large shaded table where lunch is served at anchor and cushioned seating for the passages between stops. The foredeck sun pads are ideal for taking in the cliffs as you cruise. Below deck there are three cabins and a bathroom, so guests can change and rest out of the midday heat, and the swim platform with its freshwater shower makes rinsing off the salt effortless.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Where you will go</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>A typical day follows Santorini’s southern shore, stopping at the Red Beach and the White Beach, which are best seen from the water, before crossing the caldera to swim in the warm volcanic springs off Palea Kameni. Full-day charters can include a walk on the Nea Kameni volcano or a lunch stop at Thirassia. The evening slot is timed so that you watch the famous sunset from the water beneath Oia, then cruise home in the dusk.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Best for</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Aegean Muse sits in the Comfort class and is a natural choice for birthdays, family days and sunset cocktails with friends. Tell us what kind of day you have in mind, whether that is long swims, lazy lunches or chasing the sunset, and the crew will shape the route around it.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Good to know</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Hourly charters on Aegean Muse run for at least two hours, enough to reach the Red Beach and swim. Bring swimwear, a hat and sunscreen, and water shoes if you plan to walk on Nea Kameni. The yacht leaves from Vlychada rather than the busy caldera ports, and there is free parking a short walk from the berth.</p>', 'magepeople-yacht-booking-system' ),
				'class'       => 'Comfort',
				'occasions'   => array( 'Birthday', 'Sunset Cocktail' ),
				'meta'        => array(
					'capacity'                       => '14',
					'cabins'                         => '3',
					'crew_size'                      => '2',
					'length'                         => '48',
					'build_year'                     => '2017',
					'location_name'                  => 'Vlychada Marina, Santorini',
					'location_lat'                   => '36.3374',
					'location_lng'                   => '25.4336',
					'base_price_hourly'              => '180',
					'base_price_halfday'             => '420',
					'base_price_morning_slot'        => '460',
					'base_price_evening_slot'        => '530',
					'base_price_daily'               => '600',
					'base_price_multiday'            => '540',
					'base_price_shared_hourly'       => '20',
					'base_price_shared_halfday'      => '45',
					'base_price_shared_morning_slot' => '50',
					'base_price_shared_evening_slot' => '55',
					'base_price_shared_daily'        => '65',
					'base_price_shared_multiday'     => '60',
					'booking_mode'                   => 'both',
					'min_notice_hours'               => '24',
					'buffer_minutes'                 => '60',
					'min_duration'                   => '120',
					'max_duration'                   => '480',
					'daily_start_time'               => '08:00',
					'daily_end_time'                 => '20:00',
					'halfday_start_time'             => '08:00',
					'halfday_end_time'               => '12:00',
					'morning_slot_start'             => '08:00',
					'morning_slot_end'               => '13:00',
					'evening_slot_start'             => '15:00',
					'evening_slot_end'               => '20:00',
					'included_items'                 => array(
						array( 'text' => __( 'Captain and deckhand', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Greek lunch at anchor on full-day charters', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bottled water, soft drinks and local wine', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Snorkelling equipment', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Freshwater swim shower', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Life jackets and safety equipment', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Beach towels', 'magepeople-yacht-booking-system' ) ),
					),
					'faq'                            => array(
						array(
							'question' => __( 'Will we see the Oia sunset?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Yes, on the evening slot. The captain positions the yacht beneath Oia in time for the sunset, then cruises back to Vlychada after dark.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Can we swim in the hot springs?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Most itineraries stop at the volcanic springs off Palea Kameni. The water is warm and iron-rich, so bring a swimsuit you don’t mind staining slightly orange.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'What happens if the meltemi wind is strong?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'In summer the meltemi can blow hard. When it does, your captain adjusts the route to the sheltered side of the island so the day stays comfortable, and will contact you in advance if conditions are not safe to sail.', 'magepeople-yacht-booking-system' ),
						),
					),
				),
			),
			array(
				'title'       => __( 'Southern Star', 'magepeople-yacht-booking-system' ),
				'photo'       => 'southern-star.jpg',
				'gallery'     => array( 'southern-star.jpg', 'southern-star-02.jpg', 'southern-star-03.jpg', 'southern-star-04.jpg', 'southern-star-05.jpg' ),
				'excerpt'     => __( 'A 60ft party platform with DJ booth, dance deck, and bar service for up to forty guests.', 'magepeople-yacht-booking-system' ),
				'description' => __( '<p>Southern Star is a 60-foot party yacht built in 2022 and based on Sydney Harbour, created for celebrations that need room to move. She takes up to forty guests, with a crew of four running the helm, the bar and the deck. A built-in DJ booth, a dedicated dance deck and a full bar make her the most social yacht in the fleet, and the harbour itself provides one of the most spectacular backdrops in the world.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>On board</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>The main deck is laid out for a party, with a bar at the centre, lounge seating along the sides and an open dance floor that flows onto the aft deck. The DJ booth connects to a professional sound and lighting system, and you are welcome to bring your own DJ or plug in a playlist. Up top, the open sun deck gives the best views of the city, while four cabins and two bathrooms below offer a quieter space away from the music.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Where you will go</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Cruises leave the inner harbour and pass beneath the Sydney Harbour Bridge and in front of the Opera House, the photos every guest wants. The route then heads east toward Shark Island, Watsons Bay and the harbour beaches, with a stop at anchor in a sheltered bay for a swim on warm days. Evening charters return to the inner harbour to see the city light up after dark.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Best for</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>In the Party class, Southern Star is built for birthdays, engagement parties, end-of-year celebrations and big nights out with friends. Tell us about your group and the atmosphere you want when you book, and the crew will set up the yacht, the music and the bar service to match.</p>', 'magepeople-yacht-booking-system' )
					. __( '<h3>Good to know</h3>', 'magepeople-yacht-booking-system' )
					. __( '<p>Hourly charters run for at least three hours, and the crew needs 90 minutes between groups to reset the bar and the decks. Please include every guest in your booking, since the yacht is licensed for no more than forty people on board. Flat shoes are safest on deck, especially once the dancing starts.</p>', 'magepeople-yacht-booking-system' ),
				'class'       => 'Party',
				'occasions'   => array( 'Birthday', 'Party' ),
				'meta'        => array(
					'capacity'                       => '40',
					'cabins'                         => '4',
					'crew_size'                      => '4',
					'length'                         => '60',
					'build_year'                     => '2022',
					'location_name'                  => 'Sydney Harbour, Australia',
					'location_lat'                   => '-33.8568',
					'location_lng'                   => '151.2153',
					'base_price_hourly'              => '280',
					'base_price_halfday'             => '640',
					'base_price_morning_slot'        => '700',
					'base_price_evening_slot'        => '800',
					'base_price_daily'               => '950',
					'base_price_multiday'            => '860',
					'base_price_shared_hourly'       => '10',
					'base_price_shared_halfday'      => '25',
					'base_price_shared_morning_slot' => '25',
					'base_price_shared_evening_slot' => '30',
					'base_price_shared_daily'        => '35',
					'base_price_shared_multiday'     => '30',
					'booking_mode'                   => 'both',
					'min_notice_hours'               => '24',
					'buffer_minutes'                 => '90',
					'min_duration'                   => '180',
					'max_duration'                   => '480',
					'daily_start_time'               => '08:00',
					'daily_end_time'                 => '20:00',
					'halfday_start_time'             => '08:00',
					'halfday_end_time'               => '12:00',
					'morning_slot_start'             => '08:00',
					'morning_slot_end'               => '13:00',
					'evening_slot_start'             => '15:00',
					'evening_slot_end'               => '20:00',
					'included_items'                 => array(
						array( 'text' => __( 'Captain and crew of four', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'DJ booth with professional sound and lighting', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bar service with glassware and ice', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Bottled water and soft drinks', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Life jackets and safety equipment', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Swim platform with boarding ladder', 'magepeople-yacht-booking-system' ) ),
						array( 'text' => __( 'Towels', 'magepeople-yacht-booking-system' ) ),
					),
					'faq'                            => array(
						array(
							'question' => __( 'Can we bring our own DJ?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Absolutely. The DJ booth has standard connections for decks and laptops. Let us know when you book and the crew will arrange a sound check before your guests arrive.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Can we bring our own drinks?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Bar service is included, and you can either supply your own drinks or choose a drinks package when you book. The crew serves responsibly and will stop service for anyone who has had too much.', 'magepeople-yacht-booking-system' ),
						),
						array(
							'question' => __( 'Is there a minimum booking?', 'magepeople-yacht-booking-system' ),
							'answer'   => __( 'Hourly charters on Southern Star run for at least three hours, which gives you time to cruise the harbour, anchor for a swim and still enjoy the party.', 'magepeople-yacht-booking-system' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Import a five-photo gallery for one sample yacht.
	 *
	 * Each yacht receives its own bundled gallery. Attachments are reused on
	 * retries for the same yacht image, but never shared between sample yachts.
	 *
	 * @param int      $post_id Yacht post ID.
	 * @param string[] $files   Yacht-specific bundled photo filenames.
	 * @param string   $title   Yacht title used for attachment metadata.
	 * @return int[] Attachment IDs.
	 */
	private static function import_dummy_gallery( $post_id, $files, $title ) {
		$files = is_array( $files ) ? array_map( 'sanitize_file_name', $files ) : array();
		$files = array_values( array_unique( array_filter( $files ) ) );
		$ids   = array();

		foreach ( $files as $filename ) {
			$attachment_id = self::import_dummy_attachment( $filename, $title );
			if ( $attachment_id ) {
				$ids[] = $attachment_id;
			}
			if ( count( $ids ) >= 5 ) {
				break;
			}
		}

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( count( $ids ) < 5 ) {
			return array();
		}

		set_post_thumbnail( $post_id, $ids[0] );
		update_post_meta( $post_id, Yacht::meta_key( 'gallery' ), $ids );

		return $ids;
	}

	/**
	 * Copy one bundled sample photo into the WordPress Media Library.
	 *
	 * @param string $filename Bundled filename.
	 * @param string $title    Attachment title and alt-text basis.
	 * @return int Attachment ID, or zero when the image cannot be imported.
	 */
	private static function import_dummy_attachment( $filename, $title ) {
		$filename = sanitize_file_name( $filename );
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_mageyabo_demo_asset',
				'meta_value'     => $filename,
			)
		);

		if ( $existing ) {
			$file = get_attached_file( $existing[0] );
			if ( $file && file_exists( $file ) ) {
				return (int) $existing[0];
			}
		}

		$source = MAGEYABO_PLUGIN_DIR . 'assets/demo/' . basename( $filename );
		if ( ! is_readable( $source ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_file = wp_tempnam( $filename );
		if ( ! $tmp_file ) {
			return 0;
		}
		if ( ! copy( $source, $tmp_file ) ) {
			wp_delete_file( $tmp_file );
			return 0;
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => $tmp_file,
			),
			0,
			$title
		);

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}
			return 0;
		}

		update_post_meta( $attachment_id, '_mageyabo_demo_asset', $filename );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );

		return (int) $attachment_id;
	}

	/**
	 * Remove sample yachts created by a failed all-or-nothing import attempt.
	 *
	 * @param int[] $post_ids Yacht post IDs created during this request.
	 * @return void
	 */
	private static function rollback_dummy_yachts( $post_ids ) {
		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	public static function availability( WP_REST_Request $request ) {
		$yacht_id = (int) $request['id'];
		$yacht    = self::get_readable_yacht( $yacht_id );

		if ( is_wp_error( $yacht ) ) {
			return $yacht;
		}

		$date = sanitize_text_field( $request->get_param( 'date' ) ?: current_time( 'Y-m-d' ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'mageyabo_invalid_date', __( 'Please provide a date as YYYY-MM-DD.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		$slots        = Yacht::time_windows( $yacht_id );
		$results      = array();
		$booking_mode = get_post_meta( $yacht_id, 'mageyabo_booking_mode', true ) ?: 'full';

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
		foreach ( \MageYaBo\PostTypes\Yacht::META_KEYS as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$value     = $data[ $key ];
			$meta_key  = \MageYaBo\PostTypes\Yacht::meta_key( $key );

			if ( 'gallery' === $key ) {
				$ids = array_map(
					static function ( $item ) {
						return is_array( $item ) ? (int) ( $item['id'] ?? 0 ) : (int) $item;
					},
					is_array( $value ) ? $value : array()
				);

				update_post_meta( $post_id, $meta_key, array_values( array_filter( $ids ) ) );
			} elseif ( 'related_yachts' === $key ) {
				// Same shape as gallery - the wizard's picker keeps rich
				// {id, title, thumbnail} objects for display, but only the
				// id is ever stored. Excludes the yacht linking to itself,
				// which the picker's own option list already filters out,
				// but a stale payload shouldn't be trusted to enforce that.
				$ids = array_map(
					static function ( $item ) {
						return is_array( $item ) ? (int) ( $item['id'] ?? 0 ) : (int) $item;
					},
					is_array( $value ) ? $value : array()
				);
				$ids = array_filter(
					$ids,
					static function ( $id ) use ( $post_id ) {
						return $id > 0 && $id !== (int) $post_id;
					}
				);

				update_post_meta( $post_id, $meta_key, array_values( array_unique( $ids ) ) );
			} elseif ( in_array( $key, array( 'faq', 'included_items', 'off_days' ), true ) ) {
				update_post_meta( $post_id, $meta_key, self::sanitize_meta_rows( $key, $value ) );
			} elseif ( 'confirmation_email_body' === $key ) {
				// Rich text from the wizard's classic editor - sanitize_text_field()
				// would strip it down to plain text.
				update_post_meta( $post_id, $meta_key, wp_kses_post( (string) $value ) );
			} else {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $value ) );
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
		if ( isset( $data['mageyabo_yacht_class'] ) ) {
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $data['mageyabo_yacht_class'] ), 'mageyabo_yacht_class' );
		}

		if ( isset( $data['mageyabo_yacht_occasion'] ) ) {
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $data['mageyabo_yacht_occasion'] ), 'mageyabo_yacht_occasion' );
		}
	}

	private static function summarize( $post ) {
		$gallery_ids = get_post_meta( $post->ID, 'mageyabo_gallery', true );
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
			'capacity'    => (int) get_post_meta( $post->ID, 'mageyabo_capacity', true ),
			'length'      => get_post_meta( $post->ID, 'mageyabo_length', true ),
			'cabins'      => (int) get_post_meta( $post->ID, 'mageyabo_cabins', true ),
			'classes'     => wp_get_post_terms( $post->ID, 'mageyabo_yacht_class', array( 'fields' => 'names' ) ),
			'occasions'   => wp_get_post_terms( $post->ID, 'mageyabo_yacht_occasion', array( 'fields' => 'names' ) ),
			'location'    => array(
				'name' => get_post_meta( $post->ID, 'mageyabo_location_name', true ),
				'lat'  => get_post_meta( $post->ID, 'mageyabo_location_lat', true ),
				'lng'  => get_post_meta( $post->ID, 'mageyabo_location_lng', true ),
			),
			'from_price' => self::from_price( $post->ID ),
			'status'     => $post->post_status,
			'slug'       => $post->post_name,
			'permalink'  => get_permalink( $post ),
		);
	}

	/**
	 * Every field of a yacht, including the operator's own configuration.
	 * Only reachable from capability-gated routes (edit/create/update).
	 */
	private static function full( $post ) {
		return self::fields( $post, array() );
	}

	/**
	 * Public-safe payload for the unauthenticated read route. Admin-only
	 * fields (confirmation-email subject and body) are withheld
	 * unconditionally, so no capability check stands between the public and
	 * the operator's configuration.
	 */
	private static function public_full( $post ) {
		return self::fields( $post, \MageYaBo\PostTypes\Yacht::admin_only_meta_keys() );
	}

	/**
	 * @param \WP_Post $post     Yacht.
	 * @param array    $withheld Logical meta keys to leave out of the response.
	 */
	private static function fields( $post, array $withheld ) {
		$data = self::summarize( $post );
		$data['description'] = $post->post_content;

		foreach ( \MageYaBo\PostTypes\Yacht::META_KEYS as $key ) {
			if ( in_array( $key, $withheld, true ) ) {
				continue;
			}

			$value = get_post_meta( $post->ID, \MageYaBo\PostTypes\Yacht::meta_key( $key ), true );
			$data[ $key ] = in_array( $key, array( 'gallery', 'faq', 'included_items', 'off_days', 'related_yachts' ), true )
				? ( is_array( $value ) ? $value : array() )
				: $value;
		}

		// Cast explicitly: wp_get_post_terms() can hand back numeric strings,
		// which would silently fail to match the JS side's real numbers
		// (e.g. `["3"].includes(3)` is false) and make selections look empty.
		$data['mageyabo_yacht_class']    = array_map( 'intval', wp_get_post_terms( $post->ID, 'mageyabo_yacht_class', array( 'fields' => 'ids' ) ) );
		$data['mageyabo_yacht_occasion'] = array_map( 'intval', wp_get_post_terms( $post->ID, 'mageyabo_yacht_occasion', array( 'fields' => 'ids' ) ) );

		$thumbnail_id           = get_post_thumbnail_id( $post->ID );
		$data['featured_media'] = $thumbnail_id ? (int) $thumbnail_id : 0;

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

		// Hydrated for the wizard's picker (title/thumbnail to render each
		// pill) - a related yacht that's since been trashed/deleted is
		// silently dropped rather than shown as a broken entry.
		$data['related_yachts'] = array_values(
			array_filter(
				array_map(
					static function ( $related_id ) {
						$related_id   = (int) $related_id;
						$related_post = get_post( $related_id );

						if ( ! $related_post || \MageYaBo\PostTypes\Yacht::POST_TYPE !== $related_post->post_type ) {
							return null;
						}

						return array(
							'id'        => $related_id,
							'title'     => get_the_title( $related_post ),
							'thumbnail' => get_the_post_thumbnail_url( $related_post, 'thumbnail' ) ?: '',
						);
					},
					$data['related_yachts']
				)
			)
		);

		return $data;
	}

	private static function from_price( $yacht_id, array $keys = array( 'mageyabo_base_price_hourly', 'mageyabo_base_price_halfday', 'mageyabo_base_price_morning_slot', 'mageyabo_base_price_evening_slot', 'mageyabo_base_price_daily' ) ) {
		$prices = array_filter(
			array_map(
				static fn( $key ) => (float) get_post_meta( $yacht_id, $key, true ),
				$keys
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
