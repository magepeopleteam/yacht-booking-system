<?php
namespace MageYaBo\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The data layer for `wp_mageyabo_bookings`, one of the plugin's own tables, so
 * every call below is necessarily a direct query - core has no API for it.
 * Nothing here is cached on purpose: these rows back the availability and
 * seat-count checks, and serving a stale count would oversell a charter.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
class BookingRepository {

	/** WooCommerce order-status slugs - booking and order statuses are kept
	 *  1:1 so either side can drive the other. */
	const STATUSES = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );

	/** Statuses that count against capacity - everything except cancelled/refunded/failed. */
	const ACTIVE_STATUSES = array( 'pending', 'processing', 'on-hold', 'completed' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'mageyabo_bookings';
	}

	public static function create( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$fields = array(
			'yacht_id'        => (int) $data['yacht_id'],
			'guest_id'        => (int) $data['guest_id'],
			'booking_type'    => sanitize_key( $data['booking_type'] ),
			'booking_mode'    => sanitize_key( $data['booking_mode'] ),
			'start_datetime'  => $data['start_datetime'],
			'end_datetime'    => $data['end_datetime'],
			'guest_count'     => (int) $data['guest_count'],
			'base_price'      => (float) $data['base_price'],
			'addons_total'    => (float) ( $data['addons_total'] ?? 0 ),
			'tax_total'       => (float) ( $data['tax_total'] ?? 0 ),
			'discount_total'  => (float) ( $data['discount_total'] ?? 0 ),
			'deposit_amount'  => (float) ( $data['deposit_amount'] ?? 0 ),
			'total_price'     => (float) $data['total_price'],
			'currency'        => sanitize_text_field( $data['currency'] ?? 'USD' ),
			'status'          => 'pending',
			'payment_method'  => sanitize_key( $data['payment_method'] ?? '' ),
			'payment_status'  => 'unpaid',
			'notes'           => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			'qr_token'        => function_exists( 'mageyabo_generate_token' ) ? mageyabo_generate_token( 24 ) : wp_generate_password( 24, false ),
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		/**
		 * Columns an add-on owns on this table, added to the same INSERT.
		 *
		 * Deliberately not a follow-up UPDATE on `mageyabo_after_booking_created`:
		 * a booking would then exist, however briefly, missing a value that is
		 * part of what it is - the Pro add-on writes `coupon_code` here, and a
		 * booking that had taken a discount without recording which code did it
		 * is not a state worth allowing.
		 *
		 * @param array $fields Column => value, as passed to $wpdb->insert().
		 * @param array $data   The caller's original booking data.
		 */
		$fields = (array) apply_filters( 'mageyabo_booking_insert_fields', $fields, $data );

		$wpdb->insert( self::table(), $fields );
		$booking_id = (int) $wpdb->insert_id;

		/**
		 * @param int   $booking_id
		 * @param array $fields
		 */
		do_action( 'mageyabo_after_booking_created', $booking_id, $fields );

		return $booking_id;
	}

	/**
	 * Permanently removes a booking row (admin "delete" action).
	 */
	public static function delete( $id ) {
		global $wpdb;

		$deleted = (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );

		if ( $deleted ) {
			// The add-on lines are meaningless without the booking they
			// belong to - leaving them would orphan rows that nothing can ever
			// read or clean up again.
			AddonRepository::delete_for_booking( (int) $id );

			/**
			 * Anything else holding rows against this booking clears them here
			 * - the Pro add-on's mail log does.
			 *
			 * @param int $booking_id
			 */
			do_action( 'mageyabo_after_booking_deleted', (int) $id );
		}

		return $deleted;
	}

	public static function find( $id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a prefixed identifier; $id goes through prepare().
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	/**
	 * Booking statuses that say something definite about the money, mapped to
	 * the payment status that goes with them. The same mapping WooCommerce's
	 * own order sync uses, so a booking taken through the built-in gateways
	 * and one taken through WooCommerce end up in the same state.
	 *
	 * Deliberately partial. `pending`, `on-hold` and `cancelled` say nothing:
	 * cancelling a booking someone already paid for must not erase the record
	 * that they paid - refunding is a separate, explicit decision.
	 */
	const STATUS_PAYMENT_MAP = array(
		'processing' => 'paid',
		'completed'  => 'paid',
		'refunded'   => 'refunded',
		'failed'     => 'failed',
	);

	public static function update_status( $id, $status ) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		global $wpdb;

		$before = self::find( $id );

		if ( ! $before ) {
			return false;
		}

		$fields = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);

		// Without this, marking an offline booking "Completed" from the admin
		// left payment_status at 'unpaid' forever, and every screen that works
		// out what has been paid from that field went on reporting a balance
		// due on a booking the operator had already closed out.
		$implied_payment = self::STATUS_PAYMENT_MAP[ $status ] ?? '';
		$payment_changed = $implied_payment && $implied_payment !== $before['payment_status'];

		if ( $payment_changed ) {
			$fields['payment_status'] = $implied_payment;
		}

		$wpdb->update( self::table(), $fields, array( 'id' => $id ) );

		if ( $payment_changed ) {
			/** Fires for the same reason a gateway's own call does - see update_payment(). */
			do_action( 'mageyabo_after_booking_payment_updated', (int) $id, $implied_payment, (string) $before['payment_status'] );
		}

		/**
		 * @param int    $booking_id
		 * @param string $new_status
		 * @param string $old_status
		 */
		do_action( 'mageyabo_after_booking_status_changed', $id, $status, $before['status'] );

		return true;
	}

	public static function update_payment( $id, $payment_status, $extra = array() ) {
		global $wpdb;

		$before = self::find( $id );

		$fields = array_merge(
			array(
				'payment_status' => $payment_status,
				'updated_at'     => current_time( 'mysql' ),
			),
			$extra
		);

		$wpdb->update( self::table(), $fields, array( 'id' => $id ) );

		/**
		 * Fires after a booking's payment state is written, including when it
		 * did not actually change - listeners that only care about
		 * transitions compare against $old_payment_status themselves.
		 *
		 * @param int    $booking_id
		 * @param string $payment_status
		 * @param string $old_payment_status
		 */
		do_action( 'mageyabo_after_booking_payment_updated', (int) $id, $payment_status, $before['payment_status'] ?? '' );
	}

	/**
	 * Looks a booking up by the token printed on its ticket. Used by the
	 * check-in desk, which has a scanned/typed code and no booking id.
	 */
	public static function find_by_token( $token ) {
		global $wpdb;

		$token = sanitize_text_field( (string) $token );

		if ( '' === $token ) {
			return null;
		}

		$table = self::table();

		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a prefixed identifier; $token goes through prepare().
			$wpdb->prepare( "SELECT * FROM {$table} WHERE qr_token = %s", $token ),
			ARRAY_A
		);
	}

	/**
	 * The operator's private notes on a booking. Never shown to the guest and
	 * never included in any email.
	 */
	public static function update_notes( $id, $notes ) {
		global $wpdb;

		return false !== $wpdb->update(
			self::table(),
			array(
				'notes'      => sanitize_textarea_field( (string) $notes ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Corrects the money on an already-created booking, for the case where
	 * something downstream of the quote is the real authority on what was
	 * charged - a WooCommerce coupon applied at checkout, say, which lands
	 * after PricingEngine has already run.
	 *
	 * Deliberately narrow: only the two figures that can legitimately move
	 * after the fact. `base_price` and `addons_total` stay as quoted, so the
	 * booking keeps its list-price breakdown and `discount_total` explains
	 * the difference.
	 */
	public static function update_totals( $id, array $totals ) {
		global $wpdb;

		$allowed = array( 'discount_total', 'total_price' );
		$data    = array();

		foreach ( $allowed as $column ) {
			if ( isset( $totals[ $column ] ) ) {
				$data[ $column ] = round( max( 0, (float) $totals[ $column ] ), 2 );
			}
		}

		if ( ! $data ) {
			return false;
		}

		$formats              = array_fill( 0, count( $data ), '%f' );
		$data['updated_at']   = current_time( 'mysql' );
		$formats[]            = '%s';

		return false !== $wpdb->update(
			self::table(),
			$data,
			array( 'id' => (int) $id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Which column a listing may be dated and ordered by. A whitelist, not a
	 * passthrough: these names are interpolated into SQL, where prepare()
	 * cannot parameterise an identifier.
	 */
	const SORTABLE_COLUMNS = array( 'start_datetime', 'created_at' );

	/**
	 * The whitelist, plus any column an add-on has added to this table.
	 *
	 * An add-on's column is only safe to order by when that add-on is actually
	 * installed - the Pro add-on's `checked_in_at` does not exist without it,
	 * and ordering by it would be a SQL error rather than an empty result. So
	 * the add-on declares its own, and this stays the floor.
	 *
	 * @return string[]
	 */
	public static function sortable_columns() {
		/** @param string[] $columns */
		$columns = (array) apply_filters( 'mageyabo_booking_sortable_columns', self::SORTABLE_COLUMNS );

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $columns ) ) ) );
	}

	/**
	 * @param array $args {
	 *     @type int    $page
	 *     @type int    $per_page
	 *     @type string $status      Booking status slug.
	 *     @type int    $yacht_id
	 *     @type string $date_from   Y-m-d, inclusive.
	 *     @type string $date_to     Y-m-d, inclusive.
	 *     @type string $date_column Which date the range applies to; one of
	 *                               sortable_columns(). Defaults to the charter
	 *                               start, so existing callers are unchanged.
	 *     @type string $search      Matches guest name/email/phone, yacht title,
	 *                               ticket code, or a YB-000123 reference.
	 *     @type string $orderby     One of sortable_columns().
	 *     @type string $order       'ASC' or 'DESC'.
	 * }
	 */
	/**
	 * Turns a filter set into the WHERE clause and JOINs that go with it.
	 *
	 * Public because add-ons build their own statements over the same filter
	 * set - the Pro add-on's "clear check-in history" reuses this so that what
	 * its History tab shows and what its Clear button wipes can never be two
	 * different sets of rows.
	 *
	 * @return array{where: string, params: array, from: string, date_column: string}
	 */
	public static function build_filters( array $args ) {
		global $wpdb;

		$date_column = in_array( $args['date_column'] ?? '', self::sortable_columns(), true )
			? $args['date_column']
			: 'start_datetime';

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'b.status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['yacht_id'] ) ) {
			$where[]  = 'b.yacht_id = %d';
			$params[] = (int) $args['yacht_id'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = "b.{$date_column} >= %s";
			$params[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = "b.{$date_column} <= %s";
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $args['search'] ) ) {
			$search = trim( (string) $args['search'] );
			$like   = '%' . $wpdb->esc_like( $search ) . '%';

			$clauses = array( 'g.name LIKE %s', 'g.email LIKE %s', 'g.phone LIKE %s', 'p.post_title LIKE %s', 'b.qr_token LIKE %s' );
			$params  = array_merge( $params, array( $like, $like, $like, $like, $like ) );

			// "YB-000123" is what the guest is holding; the column stores 123.
			if ( preg_match( '/(\d+)/', $search, $matches ) ) {
				$clauses[] = 'b.id = %d';
				$params[]  = (int) $matches[1];
			}

			$where[] = '( ' . implode( ' OR ', $clauses ) . ' )';
		}

		/**
		 * Extra WHERE clauses from add-ons that own columns on this table.
		 *
		 * Clauses only - anything carrying a value must add its own `%s`/`%d`
		 * placeholder and a matching entry via `mageyabo_booking_list_params`,
		 * so nothing reaches SQL without going through prepare(). The Pro
		 * add-on's attendance views are all fixed IS NULL / IS NOT NULL tests
		 * and need no parameters at all.
		 *
		 * @param string[] $where
		 * @param array    $args
		 */
		$where = (array) apply_filters( 'mageyabo_booking_list_where', $where, $args );

		/** @param array $params */
		$params = (array) apply_filters( 'mageyabo_booking_list_params', $params, $args );

		$table  = self::table();
		$guests = $wpdb->prefix . 'mageyabo_guests';

		// Joined unconditionally so the same WHERE clause works whether or not
		// a search term was given; only b.* is ever selected, so every existing
		// caller sees exactly the columns it always did.
		$from = "FROM {$table} b
			LEFT JOIN {$guests} g ON g.id = b.guest_id
			LEFT JOIN {$wpdb->posts} p ON p.ID = b.yacht_id";

		return array(
			'where'       => implode( ' AND ', $where ),
			'params'      => $params,
			'from'        => $from,
			'date_column' => $date_column,
		);
	}

	public static function list( array $args = array() ) {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$orderby = in_array( $args['orderby'] ?? '', self::sortable_columns(), true )
			? $args['orderby']
			: 'start_datetime';

		$order = 'ASC' === strtoupper( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';

		$filters   = self::build_filters( $args );
		$where_sql = $filters['where'];
		$params    = $filters['params'];
		$from      = $filters['from'];

		// A NULL check-in sorts last rather than first when ordering by it -
		// "never boarded" is not the most recent thing that happened.
		$order_sql = 'start_datetime' === $orderby
			? "b.{$orderby} {$order}"
			: "b.{$orderby} IS NULL, b.{$orderby} {$order}";

		$sql       = "SELECT b.* {$from} WHERE {$where_sql} ORDER BY {$order_sql}, b.id DESC LIMIT %d OFFSET %d";
		$count_sql = "SELECT COUNT(*) {$from} WHERE {$where_sql}";

		$list_params = array_merge( $params, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- assembled from prefixed identifiers and a column whitelist; every caller value goes through prepare().
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $list_params ), ARRAY_A );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- same assembled $count_sql.
		$total = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Overlapping, capacity-relevant bookings for a yacht in a datetime
	 * window - the read side of the availability choke point.
	 */
	public static function overlapping( $yacht_id, $start_datetime, $end_datetime, $exclude_booking_id = 0 ) {
		global $wpdb;

		$table    = self::table();
		$statuses = "'" . implode( "','", array_map( 'esc_sql', self::ACTIVE_STATUSES ) ) . "'";

		$sql = "SELECT * FROM {$table}
			WHERE yacht_id = %d
			AND status IN ({$statuses})
			AND start_datetime < %s
			AND end_datetime > %s";

		$params = array( $yacht_id, $end_datetime, $start_datetime );

		if ( $exclude_booking_id ) {
			$sql     .= ' AND id != %d';
			$params[] = $exclude_booking_id;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $statuses is an esc_sql'd whitelist from a class constant; every caller value is a placeholder.
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	public static function counts_for_dashboard() {
		global $wpdb;

		$table = self::table();
		$today = current_time( 'Y-m-d' );

		$today_bookings = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a prefixed identifier; $today is a placeholder.
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE DATE(start_datetime) = %s AND status != 'cancelled'", $today )
		);

		$upcoming_bookings = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a prefixed identifier; the time is a placeholder.
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE start_datetime > %s AND status != 'cancelled'", current_time( 'mysql' ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input; $table is a prefixed identifier.
		$cancelled_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'cancelled'" );

		// Revenue counts only statuses that represent money actually taken.
		$paid = "'" . implode( "','", array_map( 'esc_sql', array( 'processing', 'completed' ) ) ) . "'";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a prefixed identifier and $paid an esc_sql'd literal whitelist.
		$revenue_total = (float) $wpdb->get_var( "SELECT COALESCE(SUM(total_price),0) FROM {$table} WHERE status IN ({$paid})" );

		$revenue_month = (float) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as above; the month is a placeholder.
			$wpdb->prepare( "SELECT COALESCE(SUM(total_price),0) FROM {$table} WHERE status IN ({$paid}) AND DATE_FORMAT(start_datetime, '%%Y-%%m') = %s", current_time( 'Y-m' ) )
		);

		$status_counts = array_fill_keys( self::STATUSES, 0 );
		$status_rows   = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input; $table is a prefixed identifier.
			"SELECT status, COUNT(*) AS booking_count FROM {$table} GROUP BY status",
			ARRAY_A
		);

		foreach ( $status_rows as $row ) {
			$status = sanitize_key( $row['status'] );

			if ( array_key_exists( $status, $status_counts ) ) {
				$status_counts[ $status ] = (int) $row['booking_count'];
			}
		}

		return array(
			'today_bookings'     => $today_bookings,
			'upcoming_bookings'  => $upcoming_bookings,
			'cancelled_bookings' => $cancelled_bookings,
			'revenue_total'      => round( $revenue_total, 2 ),
			'revenue_this_month' => round( $revenue_month, 2 ),
			'total_bookings'     => array_sum( $status_counts ),
			'status_counts'      => $status_counts,
		);
	}

	/**
	 * The next active charters, already joined with the display data needed by
	 * the dashboard. Keeping this as one query avoids an N+1 guest/yacht lookup.
	 */
	public static function upcoming_for_dashboard( $limit = 5 ) {
		global $wpdb;

		$bookings = self::table();
		$guests   = $wpdb->prefix . 'mageyabo_guests';
		$statuses = "'" . implode( "','", array_map( 'esc_sql', self::ACTIVE_STATUSES ) ) . "'";
		$limit    = min( 10, max( 1, (int) $limit ) );

		$sql = "SELECT b.id, b.yacht_id, b.start_datetime, b.end_datetime,
				b.guest_count, b.total_price, b.currency, b.status,
				g.name AS guest_name, p.post_title AS yacht_name
			FROM {$bookings} b
			LEFT JOIN {$guests} g ON g.id = b.guest_id
			LEFT JOIN {$wpdb->posts} p ON p.ID = b.yacht_id
			WHERE b.status IN ({$statuses}) AND b.start_datetime >= %s
			ORDER BY b.start_datetime ASC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers and statuses are trusted; dynamic values use placeholders.
		return $wpdb->get_results( $wpdb->prepare( $sql, current_time( 'mysql' ), $limit ), ARRAY_A );
	}

	/**
	 * Runs $callback inside a MySQL named lock scoped to this yacht, so two
	 * near-simultaneous submissions for the same yacht never both pass the
	 * availability check before either has inserted its row.
	 */
	public static function with_yacht_lock( $yacht_id, callable $callback ) {
		global $wpdb;

		$name = substr( 'mageyabo_yacht_' . sha1( (string) $yacht_id ), 0, 48 );
		$got  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 5 ) );

		if ( 1 !== $got ) {
			return new \WP_Error(
				'mageyabo_booking_busy',
				__( 'Another booking for this yacht is being processed. Please try again.', 'magepeople-yacht-booking-system' )
			);
		}

		try {
			return $callback();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
