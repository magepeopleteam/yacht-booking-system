<?php
namespace Ybs\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No gateway class hierarchy - each gateway (including a future Pro one)
 * only needs to add itself to the `ybs_payment_gateways` list and answer the
 * `ybs_payment_start` filter for its own id. This is the same seam the
 * sibling shuttle plugin uses to keep its Free/Pro gateways decoupled.
 */
class Gateways {

	public static function register() {
		OfflineGateway::register();
		PayPalGateway::register();
		StripeGateway::register();
		WooCommerceGateway::register();
		WooCommerceProduct::register();
	}

	/**
	 * @return array<string, array{label:string, enabled:bool}>
	 */
	public static function available() {
		return apply_filters( 'ybs_payment_gateways', array() );
	}

	/**
	 * Dispatches to whichever gateway matches $gateway_id.
	 *
	 * @return array{redirect:string}|null|\WP_Error
	 */
	public static function start( $gateway_id, $booking_id ) {
		return apply_filters( 'ybs_payment_start', null, $gateway_id, $booking_id );
	}
}
