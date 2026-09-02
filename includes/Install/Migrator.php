<?php
namespace Ybs\Install;

use Ybs\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versioned schema installer. All tables are created up front at activation,
 * even the ones Free's own UI never touches (email templates/logs, and the
 * columns on `bookings` that only Pro reads) - so Pro never has to run a
 * migration of its own against a site that installed Free first.
 */
final class Migrator {

	const VERSION_OPTION = 'ybs_db_version';
	const LOCK_OPTION    = 'ybs_db_migrating';

	public static function activate() {
		self::install();
		Capabilities::install();

		if ( ! wp_next_scheduled( 'ybs_daily_maintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'ybs_daily_maintenance' );
		}

		// CPT/taxonomies are registered on `init`, which has not fired yet
		// during an activation hook - defer the rewrite flush to the next
		// normal page load instead of flushing against an empty rewrite set.
		update_option( 'ybs_flush_rewrite_rules', 1 );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'ybs_daily_maintenance' );
	}

	/**
	 * Hooked to `admin_init` (priority 5) on every request; cheap no-op once
	 * the stored version matches YBS_DB_VERSION.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) === YBS_DB_VERSION ) {
			return;
		}

		// Guard against two concurrent admin requests both trying to migrate.
		if ( get_transient( self::LOCK_OPTION ) ) {
			return;
		}

		set_transient( self::LOCK_OPTION, 1, MINUTE_IN_SECONDS );
		self::install();
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

		$sql[] = "CREATE TABLE {$prefix}ybs_guests (
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

		$sql[] = "CREATE TABLE {$prefix}ybs_bookings (
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
			checked_in_at DATETIME NULL DEFAULT NULL,
			checked_out_at DATETIME NULL DEFAULT NULL,
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

		$sql[] = "CREATE TABLE {$prefix}ybs_pricing_rules (
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

		$sql[] = "CREATE TABLE {$prefix}ybs_addons (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			description LONGTEXT NULL,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ybs_yacht_addons (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			yacht_id BIGINT UNSIGNED NOT NULL,
			addon_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY yacht_addon (yacht_id, addon_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ybs_booking_addons (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			addon_id BIGINT UNSIGNED NOT NULL,
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ybs_email_templates (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			template_type VARCHAR(30) NOT NULL DEFAULT '',
			subject VARCHAR(191) NOT NULL DEFAULT '',
			body LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ybs_email_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NULL DEFAULT NULL,
			guest_id BIGINT UNSIGNED NULL DEFAULT NULL,
			template_type VARCHAR(30) NOT NULL DEFAULT '',
			recipient VARCHAR(191) NOT NULL DEFAULT '',
			subject VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT '',
			sent_at DATETIME NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ybs_newsletter_subscribers (
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

		update_option( self::VERSION_OPTION, YBS_DB_VERSION );
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

		$table = $wpdb->prefix . 'ybs_bookings';

		// One-time status rename on the plugin's own table. No user input in
		// either statement; $table is a prefixed identifier, which prepare()
		// cannot parameterise. Caching is irrelevant to a schema migration.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "UPDATE {$table} SET status = 'processing' WHERE status IN ('confirmed', 'paid')" );
		$wpdb->query( "UPDATE {$table} SET status = 'completed' WHERE status = 'no_show'" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}
}
