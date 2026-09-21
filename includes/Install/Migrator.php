<?php
namespace MageYaBo\Install;

use MageYaBo\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versioned schema installer. All tables are created up front at activation,
 * including the ones the bundled UI does not surface yet (email templates and
 * logs), so the schema is stable for any add-on that reads it.
 */
final class Migrator {

	const VERSION_OPTION = 'mageyabo_db_version';
	const LOCK_OPTION    = 'mageyabo_db_migrating';

	public static function activate() {
		self::install();
		Capabilities::install();
		self::create_pages();

		if ( ! wp_next_scheduled( 'mageyabo_daily_maintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'mageyabo_daily_maintenance' );
		}

		// CPT/taxonomies are registered on `init`, which has not fired yet
		// during an activation hook - defer the rewrite flush to the next
		// normal page load instead of flushing against an empty rewrite set.
		update_option( 'mageyabo_flush_rewrite_rules', 1 );
	}

	/**
	 * Creates the front-end pages the plugin needs, if they don't already
	 * exist. The page ID is cached in an option so we can find it again on
	 * a later activation (e.g. after the page was renamed) without matching
	 * on title.
	 */
	private static function create_pages() {
		self::create_page_once(
			'mageyabo_search_yacht_page_id',
			__( 'Search Yacht', 'magepeople-yacht-booking-system' ),
			'[mageyabo_yacht_search]'
		);
		self::create_yacht_list_page();
		self::create_confirmation_page();
	}

	/**
	 * Where a guest lands after paying. Both hosted gateways send the browser
	 * here rather than to the site root - without it a guest who completes a
	 * PayPal or Stripe payment is dropped on the home page with no
	 * acknowledgement that anything happened.
	 */
	public static function create_confirmation_page() {
		return self::create_page_once(
			'mageyabo_confirmation_page_id',
			__( 'Booking Confirmation', 'magepeople-yacht-booking-system' ),
			'[mageyabo_booking_confirmation]'
		);
	}

	/**
	 * Public so the dummy-fleet importer (YachtsController::dummy_import())
	 * can also create this page on demand - a site that installed the
	 * plugin before this page existed, then later imports the sample
	 * fleet, still ends up with somewhere to see it.
	 */
	public static function create_yacht_list_page() {
		return self::create_page_once(
			'mageyabo_yacht_list_page_id',
			__( 'Yacht List', 'magepeople-yacht-booking-system' ),
			'[mageyabo_yacht_list]'
		);
	}

	private static function create_page_once( $option_name, $title, $content ) {
		$page_id = (int) get_option( $option_name );

		// Page still exists (and hasn't been trashed) - nothing to do.
		if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
			return $page_id;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'     => $title,
				'post_content'   => $content,
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return 0;
		}

		update_option( $option_name, $page_id );

		return $page_id;
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'mageyabo_daily_maintenance' );
	}

	/**
	 * Hooked to `admin_init` (priority 5) on every request; cheap no-op once
	 * the stored version matches MAGEYABO_DB_VERSION.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) === MAGEYABO_DB_VERSION ) {
			return;
		}

		// Guard against two concurrent admin requests both trying to migrate.
		if ( get_transient( self::LOCK_OPTION ) ) {
			return;
		}

		set_transient( self::LOCK_OPTION, 1, MINUTE_IN_SECONDS );
		self::install();
		// New in v3: a site upgrading from 1/2 never ran create_pages(), so
		// the gateways would still have nowhere to return the guest to.
		self::create_confirmation_page();
		// Roles/caps too, not just the schema: a site that activated an
		// earlier version would otherwise never receive capabilities added
		// since. `Capabilities::install()` is idempotent.
		Capabilities::install();
		delete_transient( self::LOCK_OPTION );
	}

	private static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}mageyabo_guests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(50) NOT NULL DEFAULT '',
			terms_accepted_at DATETIME NULL DEFAULT NULL,
			anonymized_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}mageyabo_bookings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			yacht_id BIGINT UNSIGNED NOT NULL,
			guest_id BIGINT UNSIGNED NOT NULL,
			booking_type VARCHAR(20) NOT NULL DEFAULT 'hourly',
			booking_mode VARCHAR(10) NOT NULL DEFAULT 'full',
			start_datetime DATETIME NOT NULL,
			end_datetime DATETIME NOT NULL,
			guest_count INT UNSIGNED NOT NULL DEFAULT 1,
			base_price DECIMAL(12,2) NOT NULL DEFAULT 0,
			addons_total DECIMAL(12,2) NOT NULL DEFAULT 0,
			tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
			discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
			deposit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
			currency VARCHAR(10) NOT NULL DEFAULT 'USD',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			payment_method VARCHAR(20) NOT NULL DEFAULT '',
			payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
			woo_order_id BIGINT UNSIGNED NULL DEFAULT NULL,
			transaction_ref VARCHAR(191) NOT NULL DEFAULT '',
			qr_token VARCHAR(64) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY yacht_id (yacht_id),
			KEY guest_id (guest_id),
			KEY status (status),
			KEY qr_token (qr_token),
			KEY start_datetime (start_datetime)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}mageyabo_pricing_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			yacht_id BIGINT UNSIGNED NULL DEFAULT NULL,
			rule_type VARCHAR(20) NOT NULL DEFAULT 'off_day',
			label VARCHAR(191) NOT NULL DEFAULT '',
			date_from DATE NULL DEFAULT NULL,
			date_to DATE NULL DEFAULT NULL,
			days_of_week VARCHAR(20) NOT NULL DEFAULT '',
			adjustment_type VARCHAR(20) NOT NULL DEFAULT 'block',
			adjustment_value DECIMAL(12,2) NOT NULL DEFAULT 0,
			priority INT NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY yacht_id (yacht_id),
			KEY rule_type (rule_type)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}mageyabo_addons (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			description LONGTEXT NULL,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}mageyabo_yacht_addons (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			yacht_id BIGINT UNSIGNED NOT NULL,
			addon_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY yacht_addon (yacht_id, addon_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}mageyabo_booking_addons (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			addon_id BIGINT UNSIGNED NOT NULL,
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id)
		) {$charset_collate};";




		$sql[] = "CREATE TABLE {$prefix}mageyabo_newsletter_subscribers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(191) NOT NULL DEFAULT '',
			subscribed_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::migrate_statuses();
		self::backfill_payment_statuses();
		update_option( self::VERSION_OPTION, MAGEYABO_DB_VERSION );
	}

	/**
	 * v2: booking statuses adopted the WooCommerce order-status slugs
	 * (pending / processing / on-hold / completed / cancelled / refunded /
	 * failed) so the two can be synced 1:1 in both directions.
	 */
	private static function migrate_statuses() {
		global $wpdb;

		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), '2', '>=' ) ) {
			return;
		}

		$table = $wpdb->prefix . 'mageyabo_bookings';

		// One-time status rename on the plugin's own table. No user input in
		// either statement; $table is a prefixed identifier, which prepare()
		// cannot parameterise. Caching is irrelevant to a schema migration.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "UPDATE {$table} SET status = 'processing' WHERE status IN ('confirmed', 'paid')" );
		$wpdb->query( "UPDATE {$table} SET status = 'completed' WHERE status = 'no_show'" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Repairs bookings whose payment status drifted away from their booking
	 * status.
	 *
	 * Until 1.1.0, `update_status()` wrote only the status column, so marking
	 * an offline booking "Completed" or "Processing" from the admin left
	 * `payment_status` at its 'unpaid' default forever - and every screen that
	 * works out what has been paid from that field went on reporting a balance
	 * due on a booking the operator had already closed out.
	 *
	 * Deliberately narrow: only rows still holding the untouched 'unpaid'
	 * default, and only for the two statuses that unambiguously mean the money
	 * arrived. A booking someone explicitly marked refunded or failed is left
	 * exactly as it is.
	 *
	 * Guarded by its own option rather than the schema version, so a site that
	 * had already reached the current version still gets the repair once.
	 */
	private static function backfill_payment_statuses() {
		global $wpdb;

		if ( get_option( 'mageyabo_payment_status_backfilled' ) ) {
			return;
		}

		$table = $wpdb->prefix . 'mageyabo_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the plugin's own table during a one-time data repair; no user input in the statement.
		$wpdb->query( "UPDATE {$table} SET payment_status = 'paid' WHERE payment_status = 'unpaid' AND status IN ('processing','completed')" );

		update_option( 'mageyabo_payment_status_backfilled', 1 );
	}

}
