<?php
namespace Ybs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A single grouped option (`ybs_settings`) rather than one option per field -
 * cheap to read as a whole (Settings screen, price calc, gateway dispatch all
 * want the full set) while still being one `update_option()` call to save.
 */
class Settings {

	const OPTION = 'ybs_settings';

	public static function defaults(): array {
		return array(
			'currency_code'            => 'USD',
			'currency_symbol'          => '$',
			'tax_rate'                 => 0.0,
			'payment_methods'          => array( 'offline' ),
			'default_payment_method'   => 'offline',
			'offline_instructions'     => '',
			'paypal_mode'              => 'sandbox',
			'paypal_email'             => '',
			'paypal_client_id'         => '',
			'paypal_secret'            => '',
			'stripe_mode'              => 'test',
			'stripe_publishable_key'   => '',
			'stripe_secret_key'        => '',
			'stripe_webhook_secret'    => '',
			'woocommerce_enabled'      => false,
			'retention_months'         => 0,
			'remove_data_on_uninstall' => false,
			'email_enabled'            => true,
			'email_from_name'          => '',
			'email_from_address'       => '',
			'email_subject'            => 'Your booking for {yacht_name} is confirmed',
			'email_body'               => "<p>Hi {guest_name},</p><p>Thank you for booking <strong>{yacht_name}</strong>. Here are your booking details:</p><ul><li>Booking ID: {booking_id}</li><li>Date: {start_date}</li><li>Time: {start_time} - {end_time}</li><li>Guests: {guest_count}</li><li>Total: {total_price}</li></ul><p>We look forward to welcoming you aboard.</p><p>{site_name}</p>",
			'email_trigger_statuses'   => array( 'pending' ),
			// Translated because they are rendered on the front end as-is until
			// an admin overrides them. Safe to call __() here: nothing reads
			// settings before `init` (see Plugin::boot()).
			'cta_eyebrow'              => __( 'Ready when you are', 'magepeople-yacht-booking-system' ),
			'cta_heading'              => __( 'Book the {yacht_name} today', 'magepeople-yacht-booking-system' ),
			'cta_text'                 => __( 'Dates fill fast - lock in your preferred slot with an instant booking request.', 'magepeople-yacht-booking-system' ),
			'cta_button_label'         => __( 'Book Now', 'magepeople-yacht-booking-system' ),
			'cta_button2_label'        => __( 'Browse all yachts', 'magepeople-yacht-booking-system' ),
		);
	}

	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	public static function get( string $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function update( array $data ): array {
		$sanitized = self::sanitize( $data );
		$merged    = wp_parse_args( $sanitized, self::all() );

		update_option( self::OPTION, $merged );

		return $merged;
	}

	private static function sanitize( array $data ): array {
		$clean    = array();
		$defaults = self::defaults();

		foreach ( $data as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			// The confirmation email body is edited as rich text (classic
			// editor) - sanitize_text_field() would strip it down to plain text.
			if ( 'email_body' === $key ) {
				$clean[ $key ] = wp_kses_post( (string) $value );
				continue;
			}

			if ( is_bool( $defaults[ $key ] ) ) {
				$clean[ $key ] = (bool) $value;
			} elseif ( is_float( $defaults[ $key ] ) ) {
				$clean[ $key ] = (float) $value;
			} elseif ( is_int( $defaults[ $key ] ) ) {
				$clean[ $key ] = (int) $value;
			} elseif ( is_array( $defaults[ $key ] ) ) {
				$clean[ $key ] = array_map( 'sanitize_text_field', (array) $value );
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}
}
