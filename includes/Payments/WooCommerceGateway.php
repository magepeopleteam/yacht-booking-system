<?php
namespace Ybs\Payments;

use Ybs\Booking\BookingRepository;
use Ybs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full-parity WooCommerce mode (spec 4.3): a booking is added to the real WC
 * cart as a hidden, dynamically-priced product and checked out through
 * WooCommerce's own flow, so Woo coupons/tax/shipping/gateways all apply
 * exactly as they would to any other product - no shortcut through a
 * directly-created order that would skip the cart/coupon step.
 */
class WooCommerceGateway {

	const ID = 'woocommerce';

	public static function register() {
		add_filter( 'ybs_payment_gateways', array( __CLASS__, 'declare_self' ) );
		add_filter( 'ybs_payment_start', array( __CLASS__, 'start' ), 10, 3 );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_dynamic_price' ) );
		add_action( 'woocommerce_get_item_data', array( __CLASS__, 'show_booking_in_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'store_booking_on_line_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'link_order_to_booking' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'sync_booking_status' ), 10, 3 );
	}

	public static function declare_self( $gateways ) {
		$gateways[ self::ID ] = array(
			'label'   => __( 'WooCommerce Checkout', 'yacht-booking-system' ),
			'enabled' => class_exists( 'WooCommerce' ) && Settings::get( 'woocommerce_enabled' ),
		);

		return $gateways;
	}

	public static function start( $result, $gateway_id, $booking_id ) {
		if ( self::ID !== $gateway_id ) {
			return $result;
		}

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return new \WP_Error( 'ybs_woocommerce_inactive', __( 'WooCommerce is not active.', 'yacht-booking-system' ) );
		}

		$booking = BookingRepository::find( $booking_id );

		if ( ! $booking ) {
			return new \WP_Error( 'ybs_booking_missing', __( 'Booking could not be found.', 'yacht-booking-system' ) );
		}

		$product_id = self::get_or_create_product( (int) $booking['yacht_id'] );

		if ( ! $product_id ) {
			return new \WP_Error( 'ybs_woocommerce_product_error', __( 'Could not prepare the WooCommerce product for this yacht.', 'yacht-booking-system' ) );
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

	public static function apply_dynamic_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['ybs_booking_id'] ) ) {
				$cart_item['data']->set_price( (float) $cart_item['ybs_booking_price'] );
			}
		}
	}

	public static function show_booking_in_cart( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['ybs_booking_id'] ) ) {
			$booking = BookingRepository::find( $cart_item['ybs_booking_id'] );

			if ( $booking ) {
				$item_data[] = array(
					'name'  => __( 'Charter Date', 'yacht-booking-system' ),
					'value' => mysql2date( get_option( 'date_format' ), $booking['start_datetime'] ),
				);
			}
		}

		return $item_data;
	}

	public static function store_booking_on_line_item( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['ybs_booking_id'] ) ) {
			$item->add_meta_data( '_ybs_booking_id', $values['ybs_booking_id'], true );
		}
	}

	public static function link_order_to_booking( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$booking_id = $item->get_meta( '_ybs_booking_id' );

			if ( $booking_id ) {
				BookingRepository::update_payment( (int) $booking_id, 'unpaid', array( 'woo_order_id' => $order_id ) );
			}
		}
	}

	public static function sync_booking_status( $order_id, $old_status, $new_status ) {
		global $wpdb;

		$booking_ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM ' . BookingRepository::table() . ' WHERE woo_order_id = %d', $order_id )
		);

		if ( ! $booking_ids ) {
			return;
		}

		$map = array(
			'processing' => array( 'confirmed', 'paid' ),
			'completed'  => array( 'completed', 'paid' ),
			'on-hold'    => array( 'pending', 'unpaid' ),
			'pending'    => array( 'pending', 'unpaid' ),
			'cancelled'  => array( 'cancelled', 'unpaid' ),
			'refunded'   => array( 'cancelled', 'refunded' ),
			'failed'     => array( 'pending', 'failed' ),
		);

		if ( ! isset( $map[ $new_status ] ) ) {
			return;
		}

		[ $booking_status, $payment_status ] = $map[ $new_status ];

		foreach ( $booking_ids as $booking_id ) {
			BookingRepository::update_payment( (int) $booking_id, $payment_status );
			BookingRepository::update_status( (int) $booking_id, $booking_status );
		}
	}

	private static function get_or_create_product( $yacht_id ) {
		$product_id = (int) get_post_meta( $yacht_id, '_ybs_wc_product_id', true );

		if ( $product_id && get_post( $product_id ) ) {
			return $product_id;
		}

		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return 0;
		}

		$product = new \WC_Product_Simple();
		$product->set_name( get_the_title( $yacht_id ) ?: __( 'Yacht Charter', 'yacht-booking-system' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product_id = $product->save();

		if ( $product_id ) {
			update_post_meta( $yacht_id, '_ybs_wc_product_id', $product_id );
		}

		return $product_id;
	}
}
