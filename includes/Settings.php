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
