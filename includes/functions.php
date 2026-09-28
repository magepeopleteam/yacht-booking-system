<?php
/**
 * Small global helpers. Kept deliberately tiny - anything with real logic
 * belongs in a class under includes/, not here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats a price using the plugin's configured currency (display only - no
 * live conversion).
 */
function mageyabo_format_price( $amount, $currency_symbol = null ) {
	if ( null === $currency_symbol ) {
		$currency_symbol = \MageYaBo\Settings::get( 'currency_symbol', '$' );
	}

	return $currency_symbol . number_format_i18n( (float) $amount, 2 );
}

/**
 * Generates a random, URL-safe token (used for guest confirmation links).
 */
function mageyabo_generate_token( $length = 32 ) {
	return substr( bin2hex( random_bytes( (int) ceil( $length / 2 ) ) ), 0, $length );
}

/**
 * The site-wide date format from Settings > General - every date display
 * in the plugin should go through mageyabo_format_datetime() rather than using
 * a hardcoded format.
 */
if ( ! function_exists( 'mageyabo_date_format' ) ) {
	function mageyabo_date_format() {
		return get_option( 'date_format', 'F j, Y' );
	}
}

/**
 * The site-wide time format from Settings > General.
 */
if ( ! function_exists( 'mageyabo_time_format' ) ) {
	function mageyabo_time_format() {
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
if ( ! function_exists( 'mageyabo_format_datetime' ) ) {
	function mageyabo_format_datetime( $mysql_datetime, $with_time = true ) {
		if ( empty( $mysql_datetime ) ) {
			return '';
		}

		$format = $with_time ? mageyabo_date_format() . ' ' . mageyabo_time_format() : mageyabo_date_format();

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
if ( ! function_exists( 'mageyabo_format_duration' ) ) {
	function mageyabo_format_duration( $start_mysql, $end_mysql ) {
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

/**
 * The public "Booking Confirmation" page - where both hosted gateways send a
 * guest once they are done paying, and the page a confirmation email links
 * to. Falls back to the site root if the page was deleted, so a return URL
 * is never empty.
 *
 * The token is what authorises the lookup: a booking id on its own is
 * guessable, and this page shows a guest's name, phone and charter details.
 *
 * @param int    $booking_id Optional booking to deep-link to.
 * @param string $token      That booking's `qr_token`.
 */
if ( ! function_exists( 'mageyabo_confirmation_url' ) ) {
	function mageyabo_confirmation_url( $booking_id = 0, $token = '' ) {
		$page_id = (int) get_option( 'mageyabo_confirmation_page_id' );
		$url     = $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/' );

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		if ( ! $booking_id ) {
			return $url;
		}

		return add_query_arg(
			array(
				'mageyabo_booking' => (int) $booking_id,
				'mageyabo_key'     => rawurlencode( (string) $token ),
			),
			$url
		);
	}
}

/**
 * Human-friendly booking reference ("YB-000123"). The numeric id is what the
 * database uses; this is what goes on a ticket and into an email.
 */
if ( ! function_exists( 'mageyabo_booking_reference' ) ) {
	function mageyabo_booking_reference( $booking_id ) {
		return sprintf( 'YB-%06d', (int) $booking_id );
	}
}

/**
 * Files a guest can download for their booking - a ticket, an invoice - as
 * `array( 'id', 'label', 'url' )` rows. The plugin generates no documents
 * itself, so this is empty unless an add-on (Pro's Documents) supplies them,
 * and every place that shows the buttons shows nothing at all when it is.
 *
 * @param array $booking The booking row.
 * @return array[]
 */
if ( ! function_exists( 'mageyabo_booking_guest_documents' ) ) {
	function mageyabo_booking_guest_documents( $booking ) {
		if ( empty( $booking['id'] ) || in_array( $booking['status'] ?? '', array( 'cancelled', 'refunded' ), true ) ) {
			return array();
		}

		/**
		 * @param array[] $documents Rows of `id`, `label`, `url`.
		 * @param array   $booking   The booking row.
		 */
		$documents = (array) apply_filters( 'mageyabo_booking_guest_documents', array(), $booking );

		return array_values(
			array_filter(
				$documents,
				static function ( $doc ) {
					return is_array( $doc ) && ! empty( $doc['url'] ) && ! empty( $doc['label'] );
				}
			)
		);
	}
}

/**
 * The download buttons for a booking's documents, or '' when there are none.
 *
 * @param array $booking The booking row.
 */
if ( ! function_exists( 'mageyabo_booking_documents_html' ) ) {
	function mageyabo_booking_documents_html( $booking ) {
		$documents = mageyabo_booking_guest_documents( $booking );

		if ( ! $documents ) {
			return '';
		}

		$icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
		$html = '<div class="ybs-docs"><span class="ybs-docs__label">' . esc_html__( 'Your documents', 'magepeople-yacht-booking-system' ) . '</span><div class="ybs-docs__buttons">';

		foreach ( $documents as $doc ) {
			$html .= sprintf(
				'<a class="ybs-docs__button is-%1$s" href="%2$s" target="_blank" rel="noopener">%3$s<span>%4$s</span></a>',
				esc_attr( sanitize_key( $doc['id'] ?? '' ) ),
				esc_url( $doc['url'] ),
				$icon,
				esc_html( $doc['label'] )
			);
		}

		return $html . '</div></div>';
	}
}
