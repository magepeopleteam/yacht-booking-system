<?php
/**
 * Fires only on "Delete" from the Plugins screen, never on deactivation.
 * Data removal is opt-in via Settings -> "Remove all data on uninstall" -
 * mirrors the checkbox-gated pattern used by the sibling shuttle-booking
 * plugin, but this one actually drops every table it created.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ybs_settings = get_option( 'ybs_settings', array() );

if ( empty( $ybs_settings['remove_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Scheduled events.
wp_clear_scheduled_hook( 'ybs_daily_maintenance' );

// Yachts and their postmeta/terms relationships.
$yacht_ids = get_posts(
	array(
		'post_type'      => 'yacht',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'suppress_filters' => true,
	)
);

foreach ( $yacht_ids as $yacht_id ) {
	wp_delete_post( $yacht_id, true );
}

// Taxonomy terms (registered on `init`, which uninstall.php never fires, so
// term_taxonomy rows are removed directly rather than via the term API).
foreach ( array( 'yacht_class', 'yacht_occasion' ) as $taxonomy ) {
	$term_taxonomy_ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy )
	);

	if ( $term_taxonomy_ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$placeholders})", $term_taxonomy_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ({$placeholders})", $term_taxonomy_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->terms} WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->term_taxonomy})" ) ); // phpcs:ignore
}

// Custom tables - every one the migrator creates.
$tables = array(
	'ybs_bookings',
	'ybs_guests',
	'ybs_pricing_rules',
	'ybs_addons',
	'ybs_yacht_addons',
	'ybs_booking_addons',
	'ybs_email_templates',
	'ybs_email_logs',
	'ybs_newsletter_subscribers',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

// Options.
$options = array(
	'ybs_settings',
	'ybs_db_version',
	'ybs_flush_rewrite_rules',
	'ybs_yacht_class_terms_seeded',
	'ybs_yacht_occasion_terms_seeded',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ybs_' ) . '%' ) );

// Roles/capabilities.
require_once __DIR__ . '/includes/Capabilities.php';
\Ybs\Capabilities::uninstall();
