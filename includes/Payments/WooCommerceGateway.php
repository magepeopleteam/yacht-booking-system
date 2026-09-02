<?php
namespace Ybs\Payments;

use Ybs\Booking\AvailabilityService;
use Ybs\Booking\BookingRepository;
use Ybs\Booking\GuestRepository;
use Ybs\Booking\PricingEngine;
use Ybs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * mage-eventpress-style WooCommerce payment flow: the yacht details page
 * posts the booking straight into the WooCommerce cart through the yacht's
 * hidden linked product (`add-to-cart=<product id>`), the customer checks
 * out through WooCommerce's own gateways/coupons/tax, and only after the
 * order is processed is the actual booking row created from the cart item
 * data and linked to the order.
 */
class WooCommerceGateway {

	const ID = 'woocommerce';

	/** Computed quote for this request, staged by validation for the cart hook. */
	private static $staged = array();

	public static function register() {
		add_filter( 'ybs_payment_gateways', array( __CLASS__, 'declare_self' ) );
		add_filter( 'ybs_payment_start', array( __CLASS__, 'start' ), 10, 3 );

		// NOTE: this plugin loads before WooCommerce, so class_exists()
		// checks cannot gate hook registration here - the WooCommerce hooks
		// below are simply registered unconditionally and only fire when
		// WooCommerce itself loaded them.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_booking_request' ), 10, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'attach_booking_data' ), 90, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_dynamic_price' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'show_booking_in_cart' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect_to_checkout' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'store_booking_on_line_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'process_classic_checkout' ), 90, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'process_block_checkout' ), 90 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'sync_booking_status' ), 10, 3 );

		// Reverse direction: a status change made from the bookings admin
		// list is pushed back onto the linked WooCommerce order.
		add_action( 'ybs_after_booking_status_changed', array( __CLASS__, 'push_status_to_order' ), 10, 2 );
	}

	public static function is_active() {
		return class_exists( 'WooCommerce' ) && (bool) Settings::get( 'woocommerce_enabled' );
	}

	public static function declare_self( $gateways ) {
		$gateways[ self::ID ] = array(
			'label'   => __( 'WooCommerce Checkout', 'magepeople-yacht-booking-system' ),
			'enabled' => self::is_active(),
		);

		return $gateways;
	}

	/**
	 * Legacy server-side path (multi-yacht shortcode form still books over
	 * REST): a booking row already exists, so drop it into the cart with a
	 * reference rather than raw booking data.
	 */
	public static function start( $result, $gateway_id, $booking_id ) {
		if ( self::ID !== $gateway_id ) {
			return $result;
		}

		if ( ! self::is_active() || ! function_exists( 'WC' ) ) {
			return new \WP_Error( 'ybs_woocommerce_inactive', __( 'WooCommerce is not active.', 'magepeople-yacht-booking-system' ) );
		}

		$booking = BookingRepository::find( $booking_id );

		if ( ! $booking ) {
			return new \WP_Error( 'ybs_booking_missing', __( 'Booking could not be found.', 'magepeople-yacht-booking-system' ) );
		}

		$product_id = WooCommerceProduct::get_product_id( (int) $booking['yacht_id'] );

		if ( ! $product_id ) {
			return new \WP_Error( 'ybs_woocommerce_product_error', __( 'Could not prepare the WooCommerce product for this yacht.', 'magepeople-yacht-booking-system' ) );
		}

		WC()->cart->add_to_cart(
			$product_id,
			1,
			0,
			array(),
			array(
				'ybs_booking_id'    => $booking_id,
				'ybs_booking_price' => (float) $booking['total_price'],
			)
		);

		return array( 'redirect' => wc_get_checkout_url() );
	}

	/**
	 * Add-to-cart time validation: mirrors what the REST create endpoint
	 * checks, then prices the charter so the exact number rides along in
	 * the cart item data.
	 */
	public static function validate_booking_request( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		if ( ! $passed ) {
			return $passed;
		}

		$yacht_id = WooCommerceProduct::get_yacht_id( $product_id );

		if ( ! $yacht_id ) {
			return $passed;
		}

		$parsed = self::parse_request( $yacht_id );

		if ( is_wp_error( $parsed ) ) {
			wc_add_notice( $parsed->get_error_message(), 'error' );
			return false;
		}

		$result = BookingRepository::with_yacht_lock(
			$yacht_id,
			static function () use ( $parsed ) {
				$availability = AvailabilityService::check(
					$parsed['yacht_id'],
					$parsed['booking_type'],
					$parsed['start_datetime'],
					$parsed['end_datetime'],
					$parsed['guest_count'],
					$parsed['booking_mode']
				);

				if ( ! $availability['available'] ) {
					return new \WP_Error( 'ybs_not_available', $availability['reason'] ?: __( 'This slot is not available.', 'magepeople-yacht-booking-system' ) );
				}

				$pricing = PricingEngine::calculate(
					$parsed['yacht_id'],
					$parsed['booking_type'],
					$parsed['start_datetime'],
					$parsed['end_datetime'],
					$parsed['guest_count'],
					$parsed['booking_mode']
				);

				if ( is_wp_error( $pricing ) ) {
					return $pricing;
				}

				$parsed['pricing'] = $pricing;

				return $parsed;
			}
		);

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return false;
		}

		self::$staged[ $product_id ] = $result;

		return true;
	}

	/**
	 * Stash the validated booking payload onto the cart item.
	 */
	public static function attach_booking_data( $cart_item_data, $product_id, $variation_id ) {
		$yacht_id = WooCommerceProduct::get_yacht_id( $product_id );

		if ( ! $yacht_id || empty( self::$staged[ $product_id ] ) ) {
			return $cart_item_data;
		}

		$data = self::$staged[ $product_id ];

		$total = (float) apply_filters(
			'ybs_before_booking_total_calculated',
			(float) $data['pricing']['total'],
			array(
				'yacht_id'     => $data['yacht_id'],
				'booking_type' => $data['booking_type'],
				'start'        => $data['start_datetime'],
				'end'          => $data['end_datetime'],
				'guest_count'  => $data['guest_count'],
			)
		);

		$data['pricing']['total'] = $total;

		$cart_item_data['ybs_booking']      = $data;
		$cart_item_data['ybs_booking_price'] = $total;
		$cart_item_data['unique_key']        = md5( microtime() . wp_json_encode( $data ) );

		unset( self::$staged[ $product_id ] );

		return $cart_item_data;
	}

	public static function apply_dynamic_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['ybs_booking_price'] ) ) {
				$cart_item['data']->set_price( (float) $cart_item['ybs_booking_price'] );
			}
		}
	}

	public static function show_booking_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item['ybs_booking'] ) ) {
			return $item_data;
		}

		$data = $cart_item['ybs_booking'];

		$item_data[] = array(
			'name'  => __( 'Charter Start', 'magepeople-yacht-booking-system' ),
			'value' => ybs_format_datetime( $data['start_datetime'] ),
		);
		$item_data[] = array(
			'name'  => __( 'Booking Type', 'magepeople-yacht-booking-system' ),
			'value' => self::type_label( $data['booking_type'], $data['booking_mode'] ),
		);
		$item_data[] = array(
			'name'  => __( 'Guests', 'magepeople-yacht-booking-system' ),
			'value' => (int) $data['guest_count'],
		);

		return $item_data;
	}

	/**
	 * Straight to checkout once a yacht booking hits the cart, exactly like
	 * mage-eventpress does for events.
	 */
	public static function redirect_to_checkout( $url ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $url;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['ybs_booking'] ) ) {
				return wc_get_checkout_url();
			}
		}

		return $url;
	}

	public static function store_booking_on_line_item( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['ybs_booking_id'] ) ) {
			$item->add_meta_data( '_ybs_booking_id', $values['ybs_booking_id'], true );
			return;
		}

		if ( ! empty( $values['ybs_booking'] ) ) {
			$data = $values['ybs_booking'];

			// Visible line-item meta (no underscore prefix) so the booking
			// details show on the thank-you page, order view and emails -
			// the underscore-prefixed copy below is the machine-readable one.
			$item->add_meta_data( __( 'Charter Start', 'magepeople-yacht-booking-system' ), ybs_format_datetime( $data['start_datetime'] ), true );
			$item->add_meta_data( __( 'Booking Type', 'magepeople-yacht-booking-system' ), self::type_label( $data['booking_type'], $data['booking_mode'] ), true );
			/* translators: %d: number of guests */
			$item->add_meta_data( __( 'Guests', 'magepeople-yacht-booking-system' ), sprintf( __( '%d guests', 'magepeople-yacht-booking-system' ), (int) $data['guest_count'] ), true );

			$item->add_meta_data( '_ybs_booking_data', $values['ybs_booking'], true );
		}
	}

	public static function process_classic_checkout( $order_id, $posted_data, $order ) {
		self::create_bookings_for_order( $order );
	}

	/**
	 * Gutenberg / block checkout parity, same as mage-eventpress.
	 */
	public static function process_block_checkout( $order ) {
		self::create_bookings_for_order( $order );
	}

	/**
	 * The heart of the flow: only once the order has been processed do we
	 * persist the booking (and its guest profile), then link both ways.
	 * Idempotent per order, and it also links legacy pre-created bookings.
	 */
	private static function create_bookings_for_order( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( $order->get_meta( '_ybs_bookings_created' ) ) {
			return;
		}

		$created = array();

		foreach ( $order->get_items() as $item ) {
			$existing_id = (int) $item->get_meta( '_ybs_booking_id' );

			if ( $existing_id ) {
				BookingRepository::update_payment( $existing_id, 'unpaid', array( 'woo_order_id' => $order->get_id() ) );
				$created[] = $existing_id;
				continue;
			}

			$data = $item->get_meta( '_ybs_booking_data' );

			if ( ! is_array( $data ) || empty( $data['yacht_id'] ) ) {
				continue;
			}

			$pricing = isset( $data['pricing'] ) && is_array( $data['pricing'] ) ? $data['pricing'] : array();

			// Guest identity: taken from the cart item when the built-in
			// gateways collected it on the booking form, otherwise from the
			// WooCommerce checkout's billing form.
			$guest_data = isset( $data['guest'] ) ? array_filter( (array) $data['guest'] ) : array();

			if ( empty( $guest_data['email'] ) && $order->get_billing_email() ) {
				$guest_data = array(
					'name'           => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'email'          => $order->get_billing_email(),
					'phone'          => $order->get_billing_phone(),
					'terms_accepted' => true,
				);
			}

			$guest_data['terms_accepted'] = true;

			$guest_id   = GuestRepository::find_or_create( $guest_data );

			$booking_id = BookingRepository::create(
				array(
					'yacht_id'       => (int) $data['yacht_id'],
					'guest_id'       => $guest_id,
					'booking_type'   => $data['booking_type'],
					'booking_mode'   => $data['booking_mode'],
					'start_datetime' => $data['start_datetime'],
					'end_datetime'   => $data['end_datetime'],
					'guest_count'    => (int) $data['guest_count'],
					'base_price'     => (float) ( $pricing['base_price'] ?? 0 ) + (float) ( $pricing['adjustment_total'] ?? 0 ),
					'tax_total'      => (float) ( $pricing['tax_total'] ?? 0 ),
					'discount_total' => (float) ( $pricing['discount_total'] ?? 0 ),
					'total_price'    => (float) ( $pricing['total'] ?? 0 ),
					'currency'       => Settings::get( 'currency_code', 'USD' ),
					'payment_method' => self::ID,
				)
			);

			BookingRepository::update_payment( $booking_id, 'unpaid', array( 'woo_order_id' => $order->get_id() ) );

			$item->add_meta_data( '_ybs_booking_id', $booking_id, true );
			$item->save();

			$created[] = $booking_id;
		}

		if ( $created ) {
			$order->update_meta_data( '_ybs_booking_ids', $created );
			$order->update_meta_data( '_ybs_bookings_created', current_time( 'mysql' ) );
			$order->save();
		}
	}

	/**
	 * WooCommerce order status -> booking status. Booking statuses use the
	 * WooCommerce slugs 1:1, so only the payment flag needs mapping.
	 */
	public static function sync_booking_status( $order_id, $old_status, $new_status ) {
		global $wpdb;

		if ( ! in_array( $new_status, BookingRepository::STATUSES, true ) ) {
			return;
		}

		$bookings_table = BookingRepository::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table; the mapping must be read fresh when an order status changes.
		$booking_ids = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $bookings_table is a prefixed identifier; $order_id goes through prepare().
			$wpdb->prepare( "SELECT id FROM {$bookings_table} WHERE woo_order_id = %d", $order_id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $booking_ids ) {
			return;
		}

		$payment_map = array(
			'processing' => 'paid',
			'completed'  => 'paid',
			'on-hold'    => 'unpaid',
			'pending'    => 'unpaid',
			'cancelled'  => 'unpaid',
			'refunded'   => 'refunded',
			'failed'     => 'failed',
		);

		foreach ( $booking_ids as $booking_id ) {
			BookingRepository::update_payment( (int) $booking_id, $payment_map[ $new_status ] );
			BookingRepository::update_status( (int) $booking_id, $new_status );
		}
	}

	/**
	 * Booking admin -> WooCommerce order: keep the linked order in the same
	 * status the booking was just moved to.
	 */
	public static function push_status_to_order( $booking_id, $new_status ) {
		if ( ! function_exists( 'wc_get_order' ) || ! in_array( $new_status, BookingRepository::STATUSES, true ) ) {
			return;
		}

		$booking = BookingRepository::find( $booking_id );

		if ( empty( $booking['woo_order_id'] ) ) {
			return;
		}

		$order = wc_get_order( (int) $booking['woo_order_id'] );

		if ( ! $order || $order->get_status() === $new_status ) {
			return;
		}

		// This fires woocommerce_order_status_changed, which loops back into
		// sync_booking_status(); that is a no-op because the booking already
		// holds this exact status.
		$order->update_status( $new_status );
	}

	/**
	 * Shared parsing for the posted booking form fields (same rules as the
	 * REST endpoint in BookingsController::create).
	 */
	private static function parse_request( $yacht_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- validated by WooCommerce core add-to-cart handling.
		$post = wp_unslash( $_POST );
		// phpcs:enable

		$yacht = get_post( $yacht_id );

		if ( ! $yacht || 'publish' !== $yacht->post_status ) {
			return new \WP_Error( 'ybs_invalid_yacht', __( 'Please choose a valid yacht.', 'magepeople-yacht-booking-system' ) );
		}

		$booking_type = sanitize_key( $post['ybs_booking_type'] ?? '' );
		$start        = sanitize_text_field( $post['ybs_start_datetime'] ?? '' );
		$end          = sanitize_text_field( $post['ybs_end_datetime'] ?? '' );
		$guest_count  = max( 1, (int) ( $post['ybs_guest_count'] ?? 1 ) );

		if ( ! $booking_type || ! $start || ! $end ) {
			return new \WP_Error( 'ybs_invalid_dates', __( 'Please choose a valid date and time.', 'magepeople-yacht-booking-system' ) );
		}

		// The mode picks which price table applies, so it can never be taken
		// from the request as-is: a full-charter-only yacht would otherwise be
		// bookable at a single shared seat's rate.
		$yacht_mode    = get_post_meta( $yacht_id, 'booking_mode', true ) ?: 'full';
		$allowed_modes = 'both' === $yacht_mode ? array( 'full', 'shared' ) : array( $yacht_mode );
		$booking_mode  = sanitize_key( $post['ybs_booking_mode'] ?? $allowed_modes[0] );

		if ( ! in_array( $booking_mode, $allowed_modes, true ) ) {
			$booking_mode = $allowed_modes[0];
		}

		// Normalize before anything compares or stores these: an unparseable
		// datetime would reach the DB as-is and silently defeat the
		// double-booking check.
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );

		if ( ! $start_ts || ! $end_ts || $end_ts <= $start_ts ) {
			return new \WP_Error( 'ybs_invalid_dates', __( 'Please choose a valid date and time.', 'magepeople-yacht-booking-system' ) );
		}

		$start = gmdate( 'Y-m-d H:i:s', $start_ts );
		$end   = gmdate( 'Y-m-d H:i:s', $end_ts );

		// Guest identity is optional here: the built-in gateways collect it
		// on the booking form, while the WooCommerce checkout collects it in
		// its billing form - create_bookings_for_order() falls back to the
		// order's billing name/email/phone when the cart item has none.
		$guest = array_filter(
			array(
				'name'  => sanitize_text_field( $post['ybs_name'] ?? '' ),
				'email' => sanitize_email( $post['ybs_email'] ?? '' ),
				'phone' => sanitize_text_field( $post['ybs_phone'] ?? '' ),
			)
		);

		return array(
			'yacht_id'       => (int) $yacht_id,
			'booking_type'   => $booking_type,
			'booking_mode'   => $booking_mode,
			'start_datetime' => $start,
			'end_datetime'   => $end,
			'guest_count'    => $guest_count,
			'guest'          => $guest,
		);
	}

	private static function type_label( $type, $mode ) {
		$labels = array(
			'hourly'       => __( 'Hourly', 'magepeople-yacht-booking-system' ),
			'half_day'     => __( 'Half-Day', 'magepeople-yacht-booking-system' ),
			'morning_slot' => __( 'Morning Slot', 'magepeople-yacht-booking-system' ),
			'evening_slot' => __( 'Evening Slot', 'magepeople-yacht-booking-system' ),
			'daily'        => __( 'Full Day', 'magepeople-yacht-booking-system' ),
			'multiday'     => __( 'Multi-Day', 'magepeople-yacht-booking-system' ),
		);

		$label = $labels[ $type ] ?? $type;

		if ( 'shared' === $mode ) {
			$label .= ' · ' . __( 'Shared', 'magepeople-yacht-booking-system' );
		}

		return $label;
	}
}
