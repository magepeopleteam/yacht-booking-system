<?php
namespace Ybs\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a yacht's base rate for a booking type, applies the single
 * best-matching pricing rule (specificity, then priority - same resolution
 * order as the sibling shuttle plugin's passenger price calculator), then
 * lets `ybs_booking_price_components` (spec section 7's required Pro seam)
 * layer add-ons/deposits on top before tax.
 */
class PricingEngine {

	/**
	 * @return array|\WP_Error {
	 *     @type float  base_price
	 *     @type float  adjustment_total
	 *     @type float  addons_total
	 *     @type float  discount_total
	 *     @type float  tax_total
	 *     @type float  total
	 *     @type bool   blocked
	 * }
	 */
	/**
	 * @param string $booking_mode 'full' | 'shared' | 'both' - when a yacht
	 *               allows both, callers resolve which mode this specific
	 *               booking uses; 'shared' reads the shared per-seat rates
	 *               (falling back to the full rate when none are set).
	 */
	public static function calculate( $yacht_id, $booking_type, $start_datetime, $end_datetime, $guest_count = 1, $booking_mode = 'full' ) {
		$base = self::base_rate( $yacht_id, $booking_type, $start_datetime, $end_datetime, $booking_mode );

		if ( is_wp_error( $base ) ) {
			return $base;
		}

		// Shared/per-seat charters are priced per guest - each guest books
		// their own seat. A full charter is a flat whole-yacht rate that
		// doesn't change with how many guests come aboard.
		if ( 'shared' === $booking_mode ) {
			$base *= max( 1, (int) $guest_count );
		}

		$off_days = (array) get_post_meta( $yacht_id, 'off_days', true );
		$rule     = PricingRuleRepository::best_match( $yacht_id, $start_datetime );

		$adjustment_total = 0.0;
		$blocked          = in_array( substr( $start_datetime, 0, 10 ), $off_days, true );

		if ( ! $blocked && $rule ) {
			if ( 'block' === $rule['adjustment_type'] || 'off_day' === $rule['rule_type'] ) {
				$blocked = true;
			} elseif ( 'percent' === $rule['adjustment_type'] ) {
				$adjustment_total = $base * ( (float) $rule['adjustment_value'] / 100 );
			} elseif ( 'fixed' === $rule['adjustment_type'] ) {
				$adjustment_total = (float) $rule['adjustment_value'];
			}
		}

		if ( $blocked ) {
			return new \WP_Error( 'ybs_date_blocked', __( 'This date is not available for booking.', 'yacht-booking-system' ) );
		}

		$components = array(
			'base_price'       => $base,
			'adjustment_total' => $adjustment_total,
			'addons_total'     => 0.0,
			'discount_total'   => 0.0,
		);

		/**
		 * Pro injects add-ons/deposits here (spec section 7).
		 *
		 * @param array $components
		 * @param array $context
		 */
		$components = apply_filters(
			'ybs_booking_price_components',
			$components,
			compact( 'yacht_id', 'booking_type', 'start_datetime', 'end_datetime', 'guest_count' )
		);

		$subtotal  = max( 0, $components['base_price'] + $components['adjustment_total'] + $components['addons_total'] - $components['discount_total'] );
		$tax_rate  = (float) \Ybs\Settings::get( 'tax_rate', 0 );
		$tax_total = round( $subtotal * ( $tax_rate / 100 ), 2 );

		return array(
			'base_price'      => round( $components['base_price'], 2 ),
			'adjustment_total' => round( $components['adjustment_total'], 2 ),
			'addons_total'    => round( $components['addons_total'], 2 ),
			'discount_total'  => round( $components['discount_total'], 2 ),
			'tax_total'       => $tax_total,
			'total'           => round( $subtotal + $tax_total, 2 ),
			'blocked'         => false,
		);
	}

	private static function base_rate( $yacht_id, $booking_type, $start_datetime, $end_datetime, $booking_mode = 'full' ) {
		$hours  = max( 0, ( strtotime( $end_datetime ) - strtotime( $start_datetime ) ) / HOUR_IN_SECONDS );
		$days   = max( 1, (int) ceil( $hours / 24 ) );
		$prefix = 'shared' === $booking_mode ? 'base_price_shared_' : 'base_price_';

		switch ( $booking_type ) {
			case 'hourly':
				$total = self::mode_rate( $yacht_id, $prefix, 'hourly', $booking_mode ) * max( 1, $hours );
				break;

			case 'half_day':
				$total = self::mode_rate( $yacht_id, $prefix, 'halfday', $booking_mode );
				break;

			case 'morning_slot':
				$total = self::mode_rate( $yacht_id, $prefix, 'morning_slot', $booking_mode );
				break;

			case 'evening_slot':
				$total = self::mode_rate( $yacht_id, $prefix, 'evening_slot', $booking_mode );
				break;

			case 'daily':
				$total = self::mode_rate( $yacht_id, $prefix, 'daily', $booking_mode );
				break;

			case 'multiday':
				$total = self::mode_rate( $yacht_id, $prefix, 'multiday', $booking_mode ) * $days;
				break;

			default:
				return new \WP_Error( 'ybs_invalid_booking_type', __( 'Unknown booking type.', 'yacht-booking-system' ) );
		}

		// A blank rate means the admin deliberately left this booking type
		// disabled for this yacht (per the wizard's own "leave blank to
		// disable" hint) - quoting $0 for it would be worse than an error.
		if ( $total <= 0 ) {
			return new \WP_Error( 'ybs_booking_type_unavailable', __( 'This booking type is not available for this yacht.', 'yacht-booking-system' ) );
		}

		return $total;
	}

	/**
	 * Shared-mode lookup with graceful fallback: yachts that only filled in
	 * full-charter prices keep working in shared mode instead of quoting 0.
	 */
	private static function mode_rate( $yacht_id, $prefix, $key, $booking_mode ) {
		$rate = (float) get_post_meta( $yacht_id, $prefix . $key, true );

		if ( 0 >= $rate && 'shared' === $booking_mode ) {
			$rate = (float) get_post_meta( $yacht_id, 'base_price_' . $key, true );
		}

		return $rate;
	}

}
