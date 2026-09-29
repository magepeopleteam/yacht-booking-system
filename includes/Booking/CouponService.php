<?php
namespace MageYaBo\Booking;

use MageYaBo\Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built-in discount codes, wired through the same seams the Pro add-on uses:
 * the code arrives as `coupon_code` in the quote/booking options, the
 * discount lands in `discount_total` via `mageyabo_booking_price_components`,
 * the outcome is reported on the quote as `coupon` / `coupon_error`, and the
 * accepted code is written onto the booking row.
 *
 * Steps aside entirely when the Pro add-on provides its own coupons (it
 * registers a `coupons` admin screen), so a site never has two coupon
 * systems discounting the same booking.
 */
class CouponService {

	private static $enabled = null;

	public static function register() {
		add_filter( 'mageyabo_booking_price_components', array( __CLASS__, 'apply_discount' ), 20, 2 );
		add_filter( 'mageyabo_booking_quote', array( __CLASS__, 'report_on_quote' ), 20, 2 );
		add_filter( 'mageyabo_booking_insert_fields', array( __CLASS__, 'record_on_booking' ), 20, 2 );
		add_action( 'mageyabo_after_booking_created', array( __CLASS__, 'count_usage' ), 20, 2 );
	}

	/**
	 * Whether the built-in codes are in charge on this site.
	 */
	public static function enabled() {
		if ( null === self::$enabled ) {
			$routes      = (array) apply_filters( 'mageyabo_admin_react_routes', array() );
			$pro_coupons = in_array( 'coupons', array_map( 'strval', wp_list_pluck( $routes, 'id' ) ), true );

			/**
			 * Turn the built-in discount codes on or off.
			 *
			 * @param bool $enabled Off by default only when another add-on
			 *                      already provides a `coupons` screen.
			 */
			self::$enabled = (bool) apply_filters( 'mageyabo_builtin_coupons_enabled', ! $pro_coupons );
		}

		return self::$enabled;
	}

	/**
	 * Why a code cannot be used on this booking, or the code itself when it
	 * can.
	 *
	 * @param string $code         Code as the guest typed it.
	 * @param int    $yacht_id     Yacht being booked.
	 * @param float  $discountable Charter plus extras, before any discount.
	 * @return array|WP_Error
	 */
	public static function validate( $code, $yacht_id, $discountable ) {
		$coupon = CouponRepository::find_by_code( $code );

		if ( ! $coupon || ! $coupon['active'] ) {
			return new WP_Error( 'mageyabo_coupon_invalid', __( 'This coupon code is not valid.', 'magepeople-yacht-booking-system' ) );
		}

		$today = current_time( 'Y-m-d' );

		if ( $coupon['starts_on'] && $coupon['starts_on'] > $today ) {
			return new WP_Error( 'mageyabo_coupon_not_started', __( 'This coupon is not active yet.', 'magepeople-yacht-booking-system' ) );
		}

		if ( $coupon['expires_on'] && $coupon['expires_on'] < $today ) {
			return new WP_Error( 'mageyabo_coupon_expired', __( 'This coupon has expired.', 'magepeople-yacht-booking-system' ) );
		}

		if ( $coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit'] ) {
			return new WP_Error( 'mageyabo_coupon_used_up', __( 'This coupon has reached its usage limit.', 'magepeople-yacht-booking-system' ) );
		}

		if ( $coupon['yacht_ids'] && ! in_array( (int) $yacht_id, $coupon['yacht_ids'], true ) ) {
			return new WP_Error( 'mageyabo_coupon_wrong_yacht', __( 'This coupon cannot be used for this yacht.', 'magepeople-yacht-booking-system' ) );
		}

		if ( $coupon['min_spend'] > 0 && (float) $discountable < $coupon['min_spend'] ) {
			return new WP_Error(
				'mageyabo_coupon_min_spend',
				sprintf(
					/* translators: %s: minimum spend, e.g. "$500.00". */
					__( 'Spend at least %s to use this coupon.', 'magepeople-yacht-booking-system' ),
					Settings::get( 'currency_symbol', '$' ) . number_format_i18n( $coupon['min_spend'], 2 )
				)
			);
		}

		return $coupon;
	}

	/**
	 * The amount a valid code takes off, never more than what it applies to.
	 */
	public static function discount_for( array $coupon, $discountable ) {
		$discountable = max( 0, (float) $discountable );

		$discount = 'percent' === $coupon['discount_type']
			? $discountable * min( 100, $coupon['amount'] ) / 100
			: $coupon['amount'];

		return round( min( $discountable, $discount ), 2 );
	}

	private static function requested_code( array $options ) {
		return CouponRepository::normalize_code( $options['coupon_code'] ?? '' );
	}

	public static function apply_discount( $components, $context ) {
		$code = self::requested_code( (array) ( $context['options'] ?? array() ) );

		if ( '' === $code || ! self::enabled() ) {
			return $components;
		}

		$coupon = self::validate( $code, $context['yacht_id'] ?? 0, $context['discountable'] ?? 0 );

		if ( is_wp_error( $coupon ) ) {
			return $components;
		}

		// Something else may already have discounted this booking; the code
		// only ever takes off what is left.
		$room = max( 0, (float) $context['discountable'] - (float) $components['discount_total'] );

		$components['discount_total'] = (float) $components['discount_total'] + self::discount_for( $coupon, $room );

		return $components;
	}

	/**
	 * Tells the booking form which code was accepted and what it saved, or
	 * why it was turned down.
	 */
	public static function report_on_quote( $quote, $context ) {
		$code = self::requested_code( (array) ( $context['options'] ?? array() ) );

		if ( '' === $code || ! self::enabled() ) {
			return $quote;
		}

		$coupon = self::validate( $code, $context['yacht_id'] ?? 0, $context['discountable'] ?? 0 );

		if ( is_wp_error( $coupon ) ) {
			$quote['coupon_error'] = $coupon->get_error_message();
			return $quote;
		}

		$quote['coupon'] = array(
			'code'          => $coupon['code'],
			'description'   => $coupon['description'],
			'discount_type' => $coupon['discount_type'],
			'amount'        => $coupon['amount'],
			'discount'      => (float) $quote['discount_total'],
		);

		return $quote;
	}

	public static function record_on_booking( $fields, $data ) {
		$code = self::requested_code( (array) ( $data['options'] ?? array() ) );

		if ( '' !== $code && self::enabled() && (float) ( $data['discount_total'] ?? 0 ) > 0 ) {
			$fields['coupon_code'] = $code;
		}

		return $fields;
	}

	public static function count_usage( $booking_id, $fields ) {
		if ( ! empty( $fields['coupon_code'] ) && self::enabled() ) {
			CouponRepository::increment_usage( $fields['coupon_code'] );
		}
	}
}
