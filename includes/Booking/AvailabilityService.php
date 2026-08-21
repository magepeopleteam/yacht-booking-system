<?php
namespace Ybs\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One choke-point every caller (frontend search, booking submission, the
 * admin calendar) goes through - `ybs_yacht_available_capacity` is
 * filterable so off-day/buffer/min-notice checks (and, later, Pro rules)
 * plug in via `add_filter` instead of being scattered across call sites.
 */
class AvailabilityService {

	public static function register() {
		add_filter( 'ybs_yacht_available_capacity', array( __CLASS__, 'apply_off_day_block' ), 10, 2 );
		add_filter( 'ybs_yacht_available_capacity', array( __CLASS__, 'apply_min_notice' ), 10, 2 );
		add_filter( 'ybs_yacht_available_capacity', array( __CLASS__, 'apply_duration_limits' ), 10, 2 );
		add_filter( 'ybs_yacht_available_capacity', array( __CLASS__, 'apply_buffer_time' ), 10, 2 );
	}

	/**
	 * @param int    $yacht_id
	 * @param string $booking_type
	 * @param string $start_datetime MySQL datetime.
	 * @param string $end_datetime   MySQL datetime.
	 * @param int    $guest_count
	 * @param string $booking_mode   'full' or 'shared'.
	 * @param int    $exclude_booking_id
	 *
	 * @return array { available: bool, remaining_capacity: int|null, reason: string }
	 */
	public static function check( $yacht_id, $booking_type, $start_datetime, $end_datetime, $guest_count = 1, $booking_mode = 'full', $exclude_booking_id = 0 ) {
		$context = compact( 'yacht_id', 'booking_type', 'start_datetime', 'end_datetime', 'guest_count', 'booking_mode', 'exclude_booking_id' );

		$result = array(
			'available'          => true,
			'remaining_capacity' => null,
			'reason'             => '',
		);

		/**
		 * Off-days, min-notice, buffer time and duration limits are applied
		 * here via the filters registered in self::register(). Each filter
		 * receives (and must return) the same $result shape.
		 */
		$result = apply_filters( 'ybs_yacht_available_capacity', $result, $context );

		if ( ! $result['available'] ) {
			return $result;
		}

		$capacity  = (int) get_post_meta( $yacht_id, 'capacity', true );
		$overlaps  = BookingRepository::overlapping( $yacht_id, $start_datetime, $end_datetime, $exclude_booking_id );

		if ( 'shared' === $booking_mode ) {
			$booked = array_sum( array_map( static fn( $row ) => (int) $row['guest_count'], $overlaps ) );
			$remaining = max( 0, $capacity - $booked );

			$result['remaining_capacity'] = $remaining;

			if ( $remaining < $guest_count ) {
				$result['available'] = false;
				$result['reason']    = __( 'Not enough remaining capacity for this time slot.', 'yacht-booking-system' );
			}
		} else {
			if ( ! empty( $overlaps ) ) {
				$result['available'] = false;
				$result['reason']    = __( 'This yacht is already booked for the selected time.', 'yacht-booking-system' );
			}

			$result['remaining_capacity'] = $overlaps ? 0 : $capacity;
		}

		return $result;
	}

	public static function apply_off_day_block( $result, $context ) {
		if ( ! $result['available'] ) {
			return $result;
		}

		$date      = substr( $context['start_datetime'], 0, 10 );
		$off_days  = (array) get_post_meta( $context['yacht_id'], 'off_days', true );

		if ( in_array( $date, $off_days, true ) ) {
			$result['available'] = false;
			$result['reason']    = __( 'This date is not available for booking.', 'yacht-booking-system' );

			return $result;
		}

		$rule = PricingRuleRepository::best_match( $context['yacht_id'], $context['start_datetime'] );

		if ( $rule && ( 'block' === $rule['adjustment_type'] || 'off_day' === $rule['rule_type'] ) ) {
			$result['available'] = false;
			$result['reason']    = __( 'This date is not available for booking.', 'yacht-booking-system' );
		}

		return $result;
	}

	public static function apply_min_notice( $result, $context ) {
		if ( ! $result['available'] ) {
			return $result;
		}

		$min_notice_hours = (int) get_post_meta( $context['yacht_id'], 'min_notice_hours', true );

		if ( $min_notice_hours > 0 ) {
			$earliest = time() + ( $min_notice_hours * HOUR_IN_SECONDS );

			if ( strtotime( $context['start_datetime'] ) < $earliest ) {
				$result['available'] = false;
				$result['reason']    = __( 'This booking does not meet the minimum notice period required.', 'yacht-booking-system' );
			}
		}

		return $result;
	}

	public static function apply_duration_limits( $result, $context ) {
		if ( ! $result['available'] || 'hourly' !== $context['booking_type'] ) {
			return $result;
		}

		$minutes     = ( strtotime( $context['end_datetime'] ) - strtotime( $context['start_datetime'] ) ) / MINUTE_IN_SECONDS;
		$min_duration = (int) get_post_meta( $context['yacht_id'], 'min_duration', true );
		$max_duration = (int) get_post_meta( $context['yacht_id'], 'max_duration', true );

		if ( $min_duration && $minutes < $min_duration ) {
			$result['available'] = false;
			$result['reason']    = __( 'This booking is shorter than the minimum allowed duration.', 'yacht-booking-system' );
		} elseif ( $max_duration && $minutes > $max_duration ) {
			$result['available'] = false;
			$result['reason']    = __( 'This booking is longer than the maximum allowed duration.', 'yacht-booking-system' );
		}

		return $result;
	}

	public static function apply_buffer_time( $result, $context ) {
		if ( ! $result['available'] ) {
			return $result;
		}

		$buffer_minutes = (int) get_post_meta( $context['yacht_id'], 'buffer_minutes', true );

		if ( $buffer_minutes <= 0 ) {
			return $result;
		}

		$buffered_start = gmdate( 'Y-m-d H:i:s', strtotime( $context['start_datetime'] ) - ( $buffer_minutes * MINUTE_IN_SECONDS ) );
		$buffered_end   = gmdate( 'Y-m-d H:i:s', strtotime( $context['end_datetime'] ) + ( $buffer_minutes * MINUTE_IN_SECONDS ) );

		$overlaps = BookingRepository::overlapping( $context['yacht_id'], $buffered_start, $buffered_end, $context['exclude_booking_id'] );

		if ( $overlaps ) {
			$result['available'] = false;
			$result['reason']    = __( 'This time is too close to another booking for this yacht.', 'yacht-booking-system' );
		}

		return $result;
	}
}
