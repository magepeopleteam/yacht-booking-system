<?php
/**
 * Small global helpers. Kept deliberately tiny - anything with real logic
 * belongs in a class under includes/, not here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free always returns false. The Pro add-on plugin overrides this (its own
 * loader redefines the function only if Pro's loader class is present), so
 * Free's code can gate Pro-only behaviour without ever knowing Pro's
 * internals.
 */
if ( ! function_exists( 'ybs_is_pro_active' ) ) {
	function ybs_is_pro_active() {
		return (bool) apply_filters( 'ybs_is_pro_active', false );
	}
}

/**
 * Formats a price using the plugin's configured currency (display only - no
 * live conversion).
 */
function ybs_format_price( $amount, $currency_symbol = null ) {
	if ( null === $currency_symbol ) {
		$currency_symbol = \Ybs\Settings::get( 'currency_symbol', '$' );
	}

	return $currency_symbol . number_format_i18n( (float) $amount, 2 );
}

/**
 * Generates a random, URL-safe token (used for guest confirmation links and
 * Pro's QR check-in tokens).
 */
function ybs_generate_token( $length = 32 ) {
	return substr( bin2hex( random_bytes( (int) ceil( $length / 2 ) ) ), 0, $length );
}

/**
 * The site-wide date format from Settings > General - every date display
 * in the plugin should go through ybs_format_datetime() rather than using
 * a hardcoded format.
 */
if ( ! function_exists( 'ybs_date_format' ) ) {
	function ybs_date_format() {
		return get_option( 'date_format', 'F j, Y' );
	}
}

/**
 * The site-wide time format from Settings > General.
 */
if ( ! function_exists( 'ybs_time_format' ) ) {
	function ybs_time_format() {
		return get_option( 'time_format', 'g:i a' );
	}
}

/**
 * Formats a MySQL datetime string with WordPress's own date/time format
 * settings (and translated month/day names via date_i18n) - the single
 * reused formatter for every date/time shown anywhere in the plugin.
 *
 * @param string $mysql_datetime MySQL datetime (Y-m-d H:i:s).
 * @param bool   $with_time      Append the time portion.
 */
if ( ! function_exists( 'ybs_format_datetime' ) ) {
	function ybs_format_datetime( $mysql_datetime, $with_time = true ) {
		if ( empty( $mysql_datetime ) ) {
			return '';
		}

		$format = $with_time ? ybs_date_format() . ' ' . ybs_time_format() : ybs_date_format();

		return mysql2date( $format, $mysql_datetime, true );
	}
}

/**
 * Human-readable length of a charter window, e.g. "2 hours", "1 night 6 hours".
 * Reused by the bookings list and any other screen that shows how long a
 * booking runs.
 *
 * @param string $start_mysql MySQL start datetime.
 * @param string $end_mysql   MySQL end datetime.
 */
if ( ! function_exists( 'ybs_format_duration' ) ) {
	function ybs_format_duration( $start_mysql, $end_mysql ) {
		$start = strtotime( (string) $start_mysql );
		$end   = strtotime( (string) $end_mysql );

		if ( ! $start || ! $end || $end <= $start ) {
			return '';
		}

		$minutes = (int) round( ( $end - $start ) / 60 );
		$days    = (int) floor( $minutes / 1440 );
		$hours   = (int) floor( ( $minutes % 1440 ) / 60 );
		$minutes = $minutes % 60;

		$parts = array();

		if ( $days > 0 ) {
			/* translators: %d: number of days */
			$parts[] = sprintf( _n( '%d day', '%d days', $days, 'magepeople-yacht-booking-system' ), $days );
		}

		if ( $hours > 0 ) {
			/* translators: %d: number of hours */
			$parts[] = sprintf( _n( '%d hour', '%d hours', $hours, 'magepeople-yacht-booking-system' ), $hours );
		}

		if ( $minutes > 0 ) {
			/* translators: %d: number of minutes */
			$parts[] = sprintf( _n( '%d minute', '%d minutes', $minutes, 'magepeople-yacht-booking-system' ), $minutes );
		}

		return implode( ' ', $parts );
	}
}
