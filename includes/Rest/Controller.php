<?php
namespace Ybs\Rest;

use Ybs\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Controller {

	const NAMESPACE_ = 'ybs/v1';

	/**
	 * Every gated route uses this rather than trusting the React UI to hide
	 * a button - per spec section 5, capability checks must happen server-side.
	 */
	public static function require_capability( $area ) {
		if ( Capabilities::can( $area ) ) {
			return true;
		}

		return new WP_Error(
			'ybs_forbidden',
			__( 'You do not have permission to do this.', 'yacht-booking-system' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public static function can_manage_bookings() {
		return self::require_capability( 'bookings' );
	}

	public static function can_manage_settings() {
		return self::require_capability( 'settings' );
	}
}
