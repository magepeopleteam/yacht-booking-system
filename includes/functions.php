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
