<?php
namespace MageYaBo\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Data layer for the built-in discount codes (`mageyabo_discount_codes`).
 *
 * Deliberately not `mageyabo_coupons`: that name belongs to the Pro add-on's
 * own table, and sharing it would let either plugin's migrator rewrite the
 * other's schema. Uncached for the same reason as the add-on tables - a code
 * is checked live at quote and booking time, and a stale usage count would
 * let one more guest through than the operator allowed.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
class CouponRepository {

	const TYPES = array( 'percent', 'fixed' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'mageyabo_discount_codes';
	}

	/**
	 * Codes are matched case-insensitively and stored upper-case, so
	 * "summer10" typed on a phone finds "SUMMER10".
	 */
	public static function normalize_code( $code ) {
		return strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( (string) $code ) ) );
	}

	public static function all() {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input; $table is a prefixed identifier.
		return array_map( array( __CLASS__, 'cast' ), (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC", ARRAY_A ) );
	}

	public static function find( $id ) {
		global $wpdb;

		$table = self::table();

		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a prefixed identifier; $id goes through prepare().
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? self::cast( $row ) : null;
	}

	public static function find_by_code( $code ) {
		global $wpdb;

		$code = self::normalize_code( $code );

		if ( '' === $code ) {
			return null;
		}

		$table = self::table();

		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a prefixed identifier; $code goes through prepare().
			$wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", $code ),
			ARRAY_A
		);

		return $row ? self::cast( $row ) : null;
	}

	/**
	 * Whether the booking form should offer a coupon field at all - there is
	 * no point asking for a code no guest could ever have.
	 */
	public static function has_active() {
		global $wpdb;

		// Until the v6 migration has run there is no table to ask.
		if ( version_compare( (string) get_option( 'mageyabo_db_version', '0' ), '6', '<' ) ) {
			return false;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input; $table is a prefixed identifier.
		return (bool) $wpdb->get_var( "SELECT id FROM {$table} WHERE active = 1 LIMIT 1" );
	}

	public static function create( array $data ) {
		global $wpdb;

		$now    = current_time( 'mysql' );
		$fields = wp_parse_args(
			self::sanitize( $data ),
			array(
				'code'          => '',
				'description'   => '',
				'discount_type' => 'percent',
				'amount'        => 0,
				'min_spend'     => 0,
				'usage_limit'   => 0,
				'starts_on'     => null,
				'expires_on'    => null,
				'yacht_ids'     => '',
				'active'        => 1,
			)
		);

		$fields['used_count'] = 0;
		$fields['created_at'] = $now;
		$fields['updated_at'] = $now;

		$wpdb->insert( self::table(), $fields );

		return (int) $wpdb->insert_id;
	}

	public static function update( $id, array $data ) {
		global $wpdb;

		$fields = self::sanitize( $data );

		if ( ! $fields ) {
			return false;
		}

		$fields['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) );
	}

	/**
	 * Bookings keep the code they were made with (it is a column on the
	 * booking), so deleting the code does not rewrite history.
	 */
	public static function delete( $id ) {
		global $wpdb;

		return false !== $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Counted as one statement so two bookings made at the same moment both
	 * register, rather than each reading N and writing N + 1.
	 */
	public static function increment_usage( $code ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a prefixed identifier; $code goes through prepare().
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET used_count = used_count + 1 WHERE code = %s", self::normalize_code( $code ) ) );
	}

	/**
	 * Only the keys present in `$data` are returned, so a partial update
	 * (toggling `active` from the list) leaves every other column alone.
	 */
	private static function sanitize( array $data ) {
		$fields = array();

		if ( array_key_exists( 'code', $data ) ) {
			$fields['code'] = self::normalize_code( $data['code'] );
		}

		if ( array_key_exists( 'description', $data ) ) {
			$fields['description'] = sanitize_text_field( (string) $data['description'] );
		}

		if ( array_key_exists( 'discount_type', $data ) ) {
			$fields['discount_type'] = in_array( $data['discount_type'], self::TYPES, true ) ? $data['discount_type'] : 'percent';
		}

		foreach ( array( 'amount', 'min_spend' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$fields[ $key ] = max( 0, round( (float) $data[ $key ], 2 ) );
			}
		}

		if ( array_key_exists( 'usage_limit', $data ) ) {
			$fields['usage_limit'] = max( 0, (int) $data['usage_limit'] );
		}

		foreach ( array( 'starts_on', 'expires_on' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$date           = sanitize_text_field( (string) $data[ $key ] );
				$fields[ $key ] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
			}
		}

		if ( array_key_exists( 'yacht_ids', $data ) ) {
			$ids                 = is_array( $data['yacht_ids'] ) ? $data['yacht_ids'] : explode( ',', (string) $data['yacht_ids'] );
			$ids                 = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
			$fields['yacht_ids'] = implode( ',', $ids );
		}

		if ( array_key_exists( 'active', $data ) ) {
			$fields['active'] = empty( $data['active'] ) ? 0 : 1;
		}

		return $fields;
	}

	private static function cast( array $row ) {
		$yacht_ids = '' === (string) $row['yacht_ids'] ? array() : array_map( 'intval', explode( ',', $row['yacht_ids'] ) );

		return array(
			'id'            => (int) $row['id'],
			'code'          => (string) $row['code'],
			'description'   => (string) $row['description'],
			'discount_type' => (string) $row['discount_type'],
			'amount'        => (float) $row['amount'],
			'min_spend'     => (float) $row['min_spend'],
			'usage_limit'   => (int) $row['usage_limit'],
			'used_count'    => (int) $row['used_count'],
			'starts_on'     => $row['starts_on'] ? (string) $row['starts_on'] : '',
			'expires_on'    => $row['expires_on'] ? (string) $row['expires_on'] : '',
			'yacht_ids'     => $yacht_ids,
			'active'        => (bool) (int) $row['active'],
			'created_at'    => (string) $row['created_at'],
		);
	}
}
