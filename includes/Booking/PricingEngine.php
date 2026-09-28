<?php
namespace MageYaBo\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a yacht's base rate for a booking type, applies the single
 * best-matching pricing rule (specificity, then priority - same resolution
 * order as the sibling shuttle plugin's passenger price calculator), adds the
 * selected add-ons, takes off any discount an add-on has applied, then lets
 * `mageyabo_booking_price_components` layer anything else on before tax.
 *
 * Every figure that reaches the total comes from the database. The caller
 * supplies *which* add-ons were chosen, never what they cost
 * worth - see AddonRepository::resolve_selection() and
 * the add-on that owns them.
 */
class PricingEngine {

	/**
	 * Booking types that have a usable price for one yacht and sales mode.
	 *
	 * The frontend uses this to avoid offering choices that base_rate() would
	 * necessarily reject. Shared bookings deliberately retain the same
	 * full-charter fallback used while calculating the final price.
	 *
	 * @param int    $yacht_id     Yacht post ID.
	 * @param string $booking_mode Full or shared.
	 * @return string[]
	 */
	public static function available_booking_types( $yacht_id, $booking_mode = 'full' ) {
		$rate_keys = array(
			'hourly'      => 'hourly',
			'half_day'    => 'halfday',
			'morning_slot' => 'morning_slot',
			'evening_slot' => 'evening_slot',
			'daily'       => 'daily',
			'multiday'    => 'multiday',
		);
		$booking_mode = 'shared' === $booking_mode ? 'shared' : 'full';
		$prefix       = 'shared' === $booking_mode ? 'mageyabo_base_price_shared_' : 'mageyabo_base_price_';
		$available    = array();

		foreach ( $rate_keys as $booking_type => $rate_key ) {
			if ( self::mode_rate( $yacht_id, $prefix, $rate_key, $booking_mode ) > 0 ) {
				$available[] = $booking_type;
			}
		}

		return $available;
	}

	/**
	 * @param int    $yacht_id
	 * @param string $booking_type
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param int    $guest_count
	 * @param string $booking_mode 'full' | 'shared' - when a yacht allows
	 *               both, callers resolve which mode this specific booking
	 *               uses; 'shared' reads the shared per-seat rates (falling
	 *               back to the full rate when none are set).
	 * @param array  $options {
	 *     @type array  $addons      Add-on selection, in any shape
	 *                               AddonRepository::resolve_selection() takes.
	 * }
	 *
	 * Anything else in `$options` is passed through untouched to the
	 * `mageyabo_booking_price_components` filter, which is how an add-on
	 * receives its own selections - the Pro add-on reads a `coupon_code` here.
	 *
	 * @return array|\WP_Error {
	 *     @type float  base_price
	 *     @type float  adjustment_total
	 *     @type float  addons_total
	 *     @type float  discount_total
	 *     @type float  subtotal
	 *     @type float  tax_total
	 *     @type float  total
	 *     @type float  deposit_amount
	 *     @type float  balance_due
	 *     @type array  addon_lines
	 *     @type bool   blocked
	 * }
	 */
	public static function calculate( $yacht_id, $booking_type, $start_datetime, $end_datetime, $guest_count = 1, $booking_mode = 'full', array $options = array() ) {
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

		$off_days = (array) get_post_meta( $yacht_id, 'mageyabo_off_days', true );
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
			return new \WP_Error( 'mageyabo_date_blocked', __( 'This date is not available for booking.', 'magepeople-yacht-booking-system' ) );
		}

		$addons = self::resolve_addons( $yacht_id, $options['addons'] ?? array() );

		// What a discount would be worked out against: charter plus add-ons, so
		// a percentage code takes something off the extras too and a
		// minimum-spend threshold is measured against what the guest is
		// actually paying.
		$discountable = round( $base + $adjustment_total + $addons['total'], 2 );

		$components = array(
			'base_price'       => $base,
			'adjustment_total' => $adjustment_total,
			'addons_total'     => $addons['total'],
			// Nothing in this plugin discounts a booking. The slot exists so
			// that something else can - the Pro add-on's coupons arrive here.
			'discount_total'   => 0.0,
		);

		$context = compact( 'yacht_id', 'booking_type', 'start_datetime', 'end_datetime', 'guest_count', 'booking_mode' ) + array(
			'discountable' => $discountable,
			'options'      => $options,
		);

		/**
		 * Add-ons, discounts and any other line items can be injected here.
		 *
		 * `$context['options']` carries the request's own selections - the
		 * submitted coupon code among them - and `$context['discountable']` is
		 * the figure a percentage discount should be taken off.
		 *
		 * @param array $components
		 * @param array $context
		 */
		$components = apply_filters(
			'mageyabo_booking_price_components',
			$components,
			$context
		);

		$subtotal  = max( 0, $components['base_price'] + $components['adjustment_total'] + $components['addons_total'] - $components['discount_total'] );
		$tax_rate  = (float) \MageYaBo\Settings::get( 'tax_rate', 0 );
		$tax_total = round( $subtotal * ( $tax_rate / 100 ), 2 );
		$total     = round( $subtotal + $tax_total, 2 );
		$deposit   = self::deposit_for( $yacht_id, $total );

		$quote = array(
			'base_price'       => round( $components['base_price'], 2 ),
			'adjustment_total' => round( $components['adjustment_total'], 2 ),
			'addons_total'     => round( $components['addons_total'], 2 ),
			'discount_total'   => round( $components['discount_total'], 2 ),
			'subtotal'         => round( $subtotal, 2 ),
			'tax_total'        => $tax_total,
			'total'            => $total,
			'deposit_amount'   => $deposit,
			'balance_due'      => round( $total - $deposit, 2 ),
			'addon_lines'      => $addons['lines'],
			'blocked'          => false,
		);

		/**
		 * The finished quote, before it goes back to the booking form.
		 *
		 * Whatever added a line above reports on it here - the Pro add-on
		 * attaches `coupon` and `coupon_error` this way, so the form can say
		 * which code was accepted, or why one was not, without this plugin
		 * knowing what a coupon is.
		 *
		 * @param array $quote
		 * @param array $context
		 * @param array $components
		 */
		return (array) apply_filters( 'mageyabo_booking_quote', $quote, $context, $components );
	}

	/**
	 * What the gateway should actually ask for now: the deposit when one is
	 * configured and smaller than the total, otherwise the whole amount.
	 * Every gateway routes its charge through this so "pay a deposit" cannot
	 * be true on one payment method and not another.
	 */
	public static function amount_due_now( array $booking ) {
		$total   = (float) $booking['total_price'];
		$deposit = (float) ( $booking['deposit_amount'] ?? 0 );

		return $deposit > 0 && $deposit < $total ? round( $deposit, 2 ) : round( $total, 2 );
	}

	/**
	 * How much of a booking has actually been settled.
	 *
	 * Two fields decide this, not one. `payment_status` records what a
	 * gateway collected - which is only the deposit on a deposit booking -
	 * while a `completed` booking means the charter ran and was paid for,
	 * balance included. Reading `payment_status` alone reports a balance
	 * still due on a booking the operator has already closed out.
	 *
	 * Every screen and email goes through here so they cannot disagree.
	 */
	public static function amount_paid( array $booking ) {
		$total   = round( (float) $booking['total_price'], 2 );
		$deposit = round( (float) ( $booking['deposit_amount'] ?? 0 ), 2 );
		$status  = (string) ( $booking['status'] ?? '' );

		if ( 'refunded' === $status || 'refunded' === ( $booking['payment_status'] ?? '' ) ) {
			return 0.0;
		}

		// Closed out by the operator: whatever was outstanding was settled,
		// on the day, in cash, or by a transfer this plugin never saw.
		if ( 'completed' === $status ) {
			return $total;
		}

		if ( 'paid' !== ( $booking['payment_status'] ?? '' ) ) {
			return 0.0;
		}

		return $deposit > 0 && $deposit < $total ? $deposit : $total;
	}

	/**
	 * What is still owed. Never negative, and rounded once so a long chain of
	 * subtractions cannot leave a stray fraction of a cent showing as due.
	 */
	public static function balance_due( array $booking ) {
		return round( max( 0, (float) $booking['total_price'] - self::amount_paid( $booking ) ), 2 );
	}

	/**
	 * Per-yacht deposit settings win over the global ones; `mageyabo_deposit_mode`
	 * is '' (inherit the global setting), 'off', 'percent' or 'fixed'.
	 *
	 * @return float Always >= 0 and never more than the total.
	 */
	public static function deposit_for( $yacht_id, $total ) {
		$total = round( (float) $total, 2 );

		if ( $total <= 0 ) {
			return 0.0;
		}

		$mode  = (string) get_post_meta( $yacht_id, 'mageyabo_deposit_mode', true );
		$value = (float) get_post_meta( $yacht_id, 'mageyabo_deposit_value', true );

		if ( 'off' === $mode ) {
			return 0.0;
		}

		if ( 'percent' !== $mode && 'fixed' !== $mode ) {
			// Inherit: the yacht has no opinion, so use the global setting.
			if ( ! \MageYaBo\Settings::get( 'deposit_enabled', false ) ) {
				return 0.0;
			}

			$mode  = (string) \MageYaBo\Settings::get( 'deposit_type', 'percent' );
			$value = (float) \MageYaBo\Settings::get( 'deposit_value', 0 );
		}

		if ( $value <= 0 ) {
			return 0.0;
		}

		$deposit = 'percent' === $mode ? $total * ( $value / 100 ) : $value;

		// A deposit at or above the total is just "pay in full" - collapse it
		// so nothing downstream has to special-case a zero balance.
		$deposit = round( min( $total, max( 0, $deposit ) ), 2 );

		return $deposit >= $total ? 0.0 : $deposit;
	}

	private static function resolve_addons( $yacht_id, $selection ) {
		if ( ! \MageYaBo\Settings::get( 'addons_enabled', true ) ) {
			return array(
				'lines' => array(),
				'total' => 0.0,
			);
		}

		return AddonRepository::resolve_selection( $yacht_id, $selection );
	}

	private static function base_rate( $yacht_id, $booking_type, $start_datetime, $end_datetime, $booking_mode = 'full' ) {
		$hours  = max( 0, ( strtotime( $end_datetime ) - strtotime( $start_datetime ) ) / HOUR_IN_SECONDS );
		$days   = max( 1, (int) ceil( $hours / 24 ) );
		$prefix = 'shared' === $booking_mode ? 'mageyabo_base_price_shared_' : 'mageyabo_base_price_';

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
				return new \WP_Error( 'mageyabo_invalid_booking_type', __( 'Unknown booking type.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
		}

		// A blank rate means the admin deliberately left this booking type
		// disabled for this yacht (per the wizard's own "leave blank to
		// disable" hint) - quoting $0 for it would be worse than an error.
		if ( $total <= 0 ) {
			return new \WP_Error( 'mageyabo_booking_type_unavailable', __( 'This booking type is not available for this yacht.', 'magepeople-yacht-booking-system' ), array( 'status' => 400 ) );
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
			$rate = (float) get_post_meta( $yacht_id, 'mageyabo_base_price_' . $key, true );
		}

		return $rate;
	}

}
