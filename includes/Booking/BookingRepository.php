<?php
namespace Ybs\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingRepository {

	const STATUSES = array( 'pending', 'confirmed', 'paid', 'completed', 'cancelled', 'no_show' );

	/** Statuses that count against capacity - everything except cancelled/no_show. */
	const ACTIVE_STATUSES = array( 'pending', 'confirmed', 'paid', 'completed' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ybs_bookings';
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
			'qr_token'        => function_exists( 'ybs_generate_token' ) ? ybs_generate_token( 24 ) : wp_generate_password( 24, false ),
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		$wpdb->insert( self::table(), $fields );
		$booking_id = (int) $wpdb->insert_id;

		/**
		 * @param int   $booking_id
		 * @param array $fields
		 */
		do_action( 'ybs_after_booking_created', $booking_id, $fields );

		return $booking_id;
	}

	public static function find( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
	}

	public static function update_status( $id, $status ) {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		global $wpdb;

		$before = self::find( $id );

		if ( ! $before ) {
			return false;
		}

		$wpdb->update(
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		/**
		 * @param int    $booking_id
		 * @param string $new_status
		 * @param string $old_status
		 */
		do_action( 'ybs_after_booking_status_changed', $id, $status, $before['status'] );

		return true;
	}

	public static function update_payment( $id, $payment_status, $extra = array() ) {
		global $wpdb;

		$fields = array_merge(
			array(
				'payment_status' => $payment_status,
				'updated_at'     => current_time( 'mysql' ),
			),
			$extra
		);

		$wpdb->update( self::table(), $fields, array( 'id' => $id ) );
	}

	public static function list( array $args = array() ) {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['yacht_id'] ) ) {
			$where[]  = 'yacht_id = %d';
			$params[] = (int) $args['yacht_id'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'start_datetime >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'start_datetime <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$table     = self::table();

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY start_datetime DESC LIMIT %d OFFSET %d";
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		$list_params  = array_merge( $params, array( $per_page, $offset ) );

		$items = $wpdb->get_results( $wpdb->prepare( $sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore

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

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function counts_for_dashboard() {
		global $wpdb;

		$table = self::table();
		$today = current_time( 'Y-m-d' );

		$today_bookings = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE DATE(start_datetime) = %s AND status != 'cancelled'", $today ) // phpcs:ignore
		);

		$upcoming_bookings = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE start_datetime > %s AND status != 'cancelled'", current_time( 'mysql' ) ) // phpcs:ignore
		);

		$cancelled_bookings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'cancelled'" ); // phpcs:ignore

		return array(
			'today_bookings'     => $today_bookings,
			'upcoming_bookings'  => $upcoming_bookings,
			'cancelled_bookings' => $cancelled_bookings,
		);
	}

	/**
	 * Runs $callback inside a MySQL named lock scoped to this yacht, so two
	 * near-simultaneous submissions for the same yacht never both pass the
	 * availability check before either has inserted its row.
	 */
	public static function with_yacht_lock( $yacht_id, callable $callback ) {
		global $wpdb;

		$name = substr( 'ybs_yacht_' . sha1( (string) $yacht_id ), 0, 48 );
		$got  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 5 ) );

		if ( 1 !== $got ) {
			return new \WP_Error(
				'ybs_booking_busy',
				__( 'Another booking for this yacht is being processed. Please try again.', 'yacht-booking-system' )
			);
		}

		try {
			return $callback();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}
}
