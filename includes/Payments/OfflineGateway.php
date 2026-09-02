<?php
namespace Ybs\Payments;

use Ybs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The booking is simply left `pending` / `unpaid` - an admin marks it paid
 * from the Bookings list once payment is received by other means.
 */
class OfflineGateway {

	const ID = 'offline';

	public static function register() {
		add_filter( 'ybs_payment_gateways', array( __CLASS__, 'declare_self' ) );
		add_filter( 'ybs_payment_start', array( __CLASS__, 'start' ), 10, 3 );
	}

	public static function declare_self( $gateways ) {
		$enabled = in_array( self::ID, (array) Settings::get( 'payment_methods', array() ), true );

		$gateways[ self::ID ] = array(
			'label'       => __( 'Offline / Manual Payment', 'magepeople-yacht-booking-system' ),
			'enabled'     => $enabled,
			'instructions' => Settings::get( 'offline_instructions', '' ),
		);

		return $gateways;
	}

	public static function start( $result, $gateway_id, $booking_id ) {
		if ( self::ID !== $gateway_id ) {
			return $result;
		}

		// Nothing to redirect to - the booking stays pending until an admin confirms payment.
		return null;
	}
}
