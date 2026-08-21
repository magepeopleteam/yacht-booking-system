<?php
namespace Ybs\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wp_ybs_guests`. A guest is found-or-created by email so returning
 * customers accumulate one profile rather than a duplicate row per booking.
 */
class GuestRepository {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ybs_guests';
	}

	public static function find_or_create( array $data ) {
		global $wpdb;

		$email = sanitize_email( $data['email'] ?? '' );
		$now   = current_time( 'mysql' );

		$existing_id = $email ? (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE email = %s LIMIT 1', $email )
		) : 0;

		$fields = array(
			'name'  => sanitize_text_field( $data['name'] ?? '' ),
			'email' => $email,
			'phone' => sanitize_text_field( $data['phone'] ?? '' ),
		);

		if ( $existing_id ) {
			$fields['updated_at'] = $now;

			if ( ! empty( $data['terms_accepted'] ) ) {
				$fields['terms_accepted_at'] = $now;
			}

			$wpdb->update( self::table(), $fields, array( 'id' => $existing_id ) );

			/** This action is documented below. */
			do_action( 'ybs_after_guest_created', $existing_id, false );

			return $existing_id;
		}

		$fields['created_at'] = $now;
		$fields['updated_at'] = $now;

		if ( ! empty( $data['terms_accepted'] ) ) {
			$fields['terms_accepted_at'] = $now;
		}

		$wpdb->insert( self::table(), $fields );
		$guest_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a guest record is created (not on find-only). Pro's
		 * custom guest-field storage hooks in here.
		 *
		 * @param int  $guest_id
		 * @param bool $is_new
		 */
		do_action( 'ybs_after_guest_created', $guest_id, true );

		return $guest_id;
	}

	public static function find( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
	}

	/**
	 * View-only list for the admin Guests screen. A guest with no booking
	 * left in a non-cancelled state is soft-filtered out entirely, per spec
	 * 4.4.5 - never hard-deleted, just excluded here.
	 */
	public static function list_active( array $args = array() ) {
		global $wpdb;

		$bookings = $wpdb->prefix . 'ybs_bookings';
		$guests   = self::table();

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$sql = "SELECT g.*, MAX(b.id) AS last_booking_id
			FROM {$guests} g
			INNER JOIN {$bookings} b ON b.guest_id = g.id AND b.status != 'cancelled'
			GROUP BY g.id
			ORDER BY g.id DESC
			LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT g.id) FROM {$guests} g INNER JOIN {$bookings} b ON b.guest_id = g.id AND b.status != 'cancelled'" // phpcs:ignore
		);

		return array(
			'items' => $rows,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * GDPR retention: anonymizes guests whose most recent booking is older
	 * than the configured retention window. Runs from the daily cron.
	 */
	public static function anonymize_expired( $months ) {
		global $wpdb;

		if ( $months <= 0 ) {
			return 0;
		}

		$guests   = self::table();
		$bookings = $wpdb->prefix . 'ybs_bookings';
		$cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );

		$candidate_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT g.id FROM {$guests} g
				WHERE g.anonymized_at IS NULL
				AND NOT EXISTS (
					SELECT 1 FROM {$bookings} b WHERE b.guest_id = g.id AND b.created_at > %s
				)",
				$cutoff
			)
		);

		if ( ! $candidate_ids ) {
			return 0;
		}

		foreach ( $candidate_ids as $id ) {
			$wpdb->update(
				$guests,
				array(
					'name'          => __( 'Anonymized Guest', 'yacht-booking-system' ),
					'email'         => '',
					'phone'         => '',
					'anonymized_at' => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => $id )
			);
		}

		return count( $candidate_ids );
	}
}
