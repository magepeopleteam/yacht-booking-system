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
	 * The `yacht` post type declares `capability_type => array( 'yacht',
	 * 'yachts' )` with `map_meta_cap`, so WordPress maps every yacht meta cap
	 * onto these names. They must be granted explicitly - otherwise nothing,
	 * not even an administrator, can edit a yacht through any core code path.
	 *
	 * @return string[]
	 */
	public static function yacht_post_caps() {
		return array(
			'edit_yacht',
			'read_yacht',
			'delete_yacht',
			'edit_yachts',
			'edit_others_yachts',
			'publish_yachts',
			'read_private_yachts',
			'delete_yachts',
			'delete_private_yachts',
			'delete_published_yachts',
			'delete_others_yachts',
			'edit_private_yachts',
			'edit_published_yachts',
		);
	}

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
		// A yacht manager runs the whole operation, including the fleet, so it
		// needs the mapped post caps as well as the plugin's own.
		$manager_caps = array_merge(
			array( 'read' => true ),
			array(
				self::CAP_BOOKINGS => true,
				self::CAP_SETTINGS => true,
				self::CAP_PAYMENTS => true,
			),
			array_fill_keys( self::yacht_post_caps(), true )
		);

		// Staff work the booking desk only - no fleet or settings access.
		$staff_caps = array(
			'read'             => true,
			self::CAP_BOOKINGS => true,
		);

		self::ensure_role( self::ROLE_MANAGER, __( 'Yacht Manager', 'magepeople-yacht-booking-system' ), $manager_caps );
		self::ensure_role( self::ROLE_STAFF, __( 'Yacht Staff', 'magepeople-yacht-booking-system' ), $staff_caps );

		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			$administrator->add_cap( self::CAP_BOOKINGS );
			$administrator->add_cap( self::CAP_SETTINGS );
			$administrator->add_cap( self::CAP_PAYMENTS );

			foreach ( self::yacht_post_caps() as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
	}

	/**
	 * `add_role()` is a no-op when the role already exists, which would leave
	 * an upgraded site missing any capability added since it first activated.
	 * Create the role if it is new, then top up its caps either way.
	 *
	 * @param string              $role  Role slug.
	 * @param string              $label Translated display name.
	 * @param array<string, bool> $caps  Capabilities to grant.
	 */
	private static function ensure_role( $role, $label, array $caps ) {
		$existing = get_role( $role );

		if ( ! $existing ) {
			add_role( $role, $label, $caps );
			return;
		}

		foreach ( array_keys( $caps ) as $cap ) {
			if ( ! $existing->has_cap( $cap ) ) {
				$existing->add_cap( $cap );
			}
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

			foreach ( self::yacht_post_caps() as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}
	}
}
