<?php
namespace MageYaBo\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Data layer for the three add-on tables (`mageyabo_addons`, the
 * `mageyabo_yacht_addons` join, and `mageyabo_booking_addons` line items).
 * These are the plugin's own tables, so every call is necessarily a direct
 * query - and none of it is cached, because an add-on's price is quoted live
 * and a stale row would charge a price the operator no longer offers.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
class AddonRepository {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'mageyabo_addons';
	}

	public static function yacht_table() {
		global $wpdb;
		return $wpdb->prefix . 'mageyabo_yacht_addons';
	}

	public static function booking_table() {
		global $wpdb;
		return $wpdb->prefix . 'mageyabo_booking_addons';
	}

	/**
	 * @param bool $active_only Only add-ons the operator has switched on.
	 */
	public static function all( $active_only = false ) {
		global $wpdb;

		$table = self::table();
		$sql   = "SELECT * FROM {$table}";

		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}

		$sql .= ' ORDER BY name ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input; $table is a prefixed identifier and the WHERE clause is a literal.
		return array_map( array( __CLASS__, 'cast' ), (array) $wpdb->get_results( $sql, ARRAY_A ) );
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

	public static function create( array $data ) {
		global $wpdb;

		$now    = current_time( 'mysql' );
		$fields = self::sanitize( $data );

		$fields = wp_parse_args(
			$fields,
			array(
				'name'        => '',
				'description' => '',
				'price'       => 0,
				'active'      => 1,
			)
		);

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
	 * Removing an add-on also drops its yacht assignments. Rows already
	 * written against a *booking* are deliberately left alone: they record
	 * what was actually sold and at what price, and that history must not
	 * change because the catalogue did.
	 */
	public static function delete( $id ) {
		global $wpdb;

		$wpdb->delete( self::yacht_table(), array( 'addon_id' => (int) $id ), array( '%d' ) );

		return false !== $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * The add-ons offered on one yacht. Only active ones by default - the
	 * admin wizard asks for the full set so an assigned-but-disabled add-on
	 * still shows as ticked.
	 */
	public static function for_yacht( $yacht_id, $active_only = true ) {
		global $wpdb;

		$table       = self::table();
		$yacht_table = self::yacht_table();

		$sql = "SELECT a.* FROM {$table} a
			INNER JOIN {$yacht_table} ya ON ya.addon_id = a.id
			WHERE ya.yacht_id = %d";

		if ( $active_only ) {
			$sql .= ' AND a.active = 1';
		}

		$sql .= ' ORDER BY a.name ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are prefixed constants; $yacht_id is a placeholder.
		return array_map( array( __CLASS__, 'cast' ), (array) $wpdb->get_results( $wpdb->prepare( $sql, (int) $yacht_id ), ARRAY_A ) );
	}

	public static function assigned_ids( $yacht_id ) {
		global $wpdb;

		$yacht_table = self::yacht_table();

		$ids = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $yacht_table is a prefixed identifier; $yacht_id goes through prepare().
			$wpdb->prepare( "SELECT addon_id FROM {$yacht_table} WHERE yacht_id = %d", (int) $yacht_id )
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Replaces a yacht's whole assignment set in one call - the wizard posts
	 * the complete list of ticked boxes, not a diff.
	 */
	public static function set_for_yacht( $yacht_id, array $addon_ids ) {
		global $wpdb;

		$yacht_id  = (int) $yacht_id;
		$addon_ids = array_values( array_unique( array_filter( array_map( 'intval', $addon_ids ) ) ) );

		$wpdb->delete( self::yacht_table(), array( 'yacht_id' => $yacht_id ), array( '%d' ) );

		foreach ( $addon_ids as $addon_id ) {
			$wpdb->insert(
				self::yacht_table(),
				array(
					'yacht_id' => $yacht_id,
					'addon_id' => $addon_id,
				),
				array( '%d', '%d' )
			);
		}

		return $addon_ids;
	}

	/**
	 * Resolves a client-supplied add-on selection against what the yacht
	 * actually offers. Prices come from the database, never from the
	 * request - otherwise a visitor could post their own price for the
	 * champagne package.
	 *
	 * @param int   $yacht_id
	 * @param array $selection [ addon_id => quantity ] or a list of ids.
	 *
	 * @return array{lines: array<int, array>, total: float}
	 */
	public static function resolve_selection( $yacht_id, $selection ) {
		$lines = array();
		$total = 0.0;

		if ( ! is_array( $selection ) || ! $selection ) {
			return array(
				'lines' => $lines,
				'total' => 0.0,
			);
		}

		$offered = array();

		foreach ( self::for_yacht( $yacht_id, true ) as $addon ) {
			$offered[ (int) $addon['id'] ] = $addon;
		}

		foreach ( $selection as $key => $value ) {
			// Accepts both [ 3 => 2 ] (id => qty) and [ 3, 7 ] (ids, qty 1),
			// so the booking form can post whichever shape suits it.
			if ( is_array( $value ) ) {
				$addon_id = (int) ( $value['addon_id'] ?? $value['id'] ?? 0 );
				$quantity = (int) ( $value['quantity'] ?? 1 );
			} elseif ( is_numeric( $key ) && is_numeric( $value ) && (int) $key > 0 ) {
				$addon_id = (int) $key;
				$quantity = (int) $value;
			} else {
				$addon_id = (int) $value;
				$quantity = 1;
			}

			$quantity = max( 0, $quantity );

			if ( ! $addon_id || ! $quantity || ! isset( $offered[ $addon_id ] ) ) {
				continue;
			}

			$addon = $offered[ $addon_id ];
			$line  = round( (float) $addon['price'] * $quantity, 2 );

			$lines[] = array(
				'addon_id' => $addon_id,
				'name'     => $addon['name'],
				'quantity' => $quantity,
				'price'    => (float) $addon['price'],
				'subtotal' => $line,
			);

			$total += $line;
		}

		return array(
			'lines' => $lines,
			'total' => round( $total, 2 ),
		);
	}

	/**
	 * Writes the resolved lines against a booking. The unit price is copied
	 * in rather than referenced, so the booking keeps what was charged even
	 * after the catalogue price changes.
	 */
	public static function save_for_booking( $booking_id, array $lines ) {
		global $wpdb;

		$booking_id = (int) $booking_id;

		$wpdb->delete( self::booking_table(), array( 'booking_id' => $booking_id ), array( '%d' ) );

		foreach ( $lines as $line ) {
			$wpdb->insert(
				self::booking_table(),
				array(
					'booking_id' => $booking_id,
					'addon_id'   => (int) $line['addon_id'],
					'quantity'   => max( 1, (int) $line['quantity'] ),
					'price'      => (float) $line['price'],
				),
				array( '%d', '%d', '%d', '%f' )
			);
		}
	}

	/**
	 * The add-on lines sold with a booking, joined to their current names
	 * for display. A line whose add-on was since deleted still shows, with
	 * an empty name rather than vanishing from the order.
	 */
	public static function for_booking( $booking_id ) {
		global $wpdb;

		$table         = self::table();
		$booking_table = self::booking_table();

		$sql = "SELECT ba.addon_id, ba.quantity, ba.price, a.name
			FROM {$booking_table} ba
			LEFT JOIN {$table} a ON a.id = ba.addon_id
			WHERE ba.booking_id = %d
			ORDER BY ba.id ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are prefixed constants; $booking_id is a placeholder.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, (int) $booking_id ), ARRAY_A );

		return array_map(
			static function ( $row ) {
				return array(
					'addon_id' => (int) $row['addon_id'],
					'name'     => (string) $row['name'],
					'quantity' => (int) $row['quantity'],
					'price'    => (float) $row['price'],
					'subtotal' => round( (float) $row['price'] * (int) $row['quantity'], 2 ),
				);
			},
			$rows
		);
	}

	public static function delete_for_booking( $booking_id ) {
		global $wpdb;

		$wpdb->delete( self::booking_table(), array( 'booking_id' => (int) $booking_id ), array( '%d' ) );
	}

	private static function sanitize( array $data ) {
		$fields = array();

		if ( isset( $data['name'] ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['description'] ) ) {
			$fields['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['price'] ) ) {
			$fields['price'] = max( 0, (float) $data['price'] );
		}

		if ( isset( $data['active'] ) ) {
			$fields['active'] = $data['active'] ? 1 : 0;
		}

		return $fields;
	}

	private static function cast( array $row ) {
		return array(
			'id'          => (int) $row['id'],
			'name'        => (string) $row['name'],
			'description' => (string) $row['description'],
			'price'       => (float) $row['price'],
			'active'      => (bool) $row['active'],
		);
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
