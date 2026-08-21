<?php
namespace Ybs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central capability gate. Every REST route and admin screen checks through
 * here rather than hardcoding `manage_options`, so a site can reassign who
 * runs the booking desk without touching code (`ybs_admin_capability` /
 * `ybs_user_can` filters).
 */
class Capabilities {

	const CAP_BOOKINGS = 'manage_ybs_bookings';
	const CAP_SETTINGS = 'manage_ybs_settings';
	const CAP_PAYMENTS = 'manage_ybs_payments';

	const ROLE_MANAGER = 'ybs_yacht_manager';
	const ROLE_STAFF   = 'ybs_yacht_staff';

	/**
	 * The capability that always grants access, overridable per site.
	 */
	public static function admin_capability() {
		return (string) apply_filters( 'ybs_admin_capability', 'manage_options' );
	}

	/**
	 * @param string $area One of 'bookings', 'settings', 'payments'.
	 */
	public static function can( $area ) {
		if ( current_user_can( self::admin_capability() ) ) {
			return true;
		}

		$capability = self::CAP_BOOKINGS;

		if ( 'settings' === $area ) {
			$capability = self::CAP_SETTINGS;
		} elseif ( 'payments' === $area ) {
			$capability = self::CAP_PAYMENTS;
		}

		return (bool) apply_filters( 'ybs_user_can', current_user_can( $capability ), $area );
	}

	/**
	 * Registers the plugin's roles and capabilities. Idempotent, safe to call
	 * on every activation.
	 */
	public static function install() {
		add_role(
			self::ROLE_MANAGER,
			__( 'Yacht Manager', 'yacht-booking-system' ),
			array(
				'read'              => true,
				self::CAP_BOOKINGS  => true,
				self::CAP_SETTINGS  => true,
				self::CAP_PAYMENTS  => true,
			)
		);

		add_role(
			self::ROLE_STAFF,
			__( 'Yacht Staff', 'yacht-booking-system' ),
			array(
				'read'             => true,
				self::CAP_BOOKINGS => true,
			)
		);

		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			$administrator->add_cap( self::CAP_BOOKINGS );
			$administrator->add_cap( self::CAP_SETTINGS );
			$administrator->add_cap( self::CAP_PAYMENTS );
		}
	}

	public static function uninstall() {
		remove_role( self::ROLE_MANAGER );
		remove_role( self::ROLE_STAFF );

		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			$administrator->remove_cap( self::CAP_BOOKINGS );
			$administrator->remove_cap( self::CAP_SETTINGS );
			$administrator->remove_cap( self::CAP_PAYMENTS );
		}
	}
}
