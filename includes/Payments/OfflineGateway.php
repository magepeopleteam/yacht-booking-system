<?php
namespace MageYaBo\Payments;

use MageYaBo\Settings;

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
		add_filter( 'mageyabo_payment_gateways', array( __CLASS__, 'declare_self' ) );
		add_filter( 'mageyabo_payment_start', array( __CLASS__, 'start' ), 10, 3 );
	}

	public static function declare_self( $gateways ) {
		$enabled = in_array( self::ID, (array) Settings::get( 'payment_methods', array() ), true );

		$gateways[ self::ID ] = array(
			'label'   => __( 'Offline / Manual Payment', 'magepeople-yacht-booking-system' ),
			'enabled' => $enabled,
		);

		// This list is localised into every front-end page that renders a
		// plugin shortcode, and the instructions usually carry bank or IBAN
		// details. Send them only when offline payment is actually on, so an
		// operator who fills the field in and later switches the method off
		// does not keep publishing them.
		if ( $enabled ) {
			$gateways[ self::ID ]['instructions'] = Settings::get( 'offline_instructions', '' );
		}

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
