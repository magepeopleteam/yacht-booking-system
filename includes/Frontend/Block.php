<?php
namespace Ybs\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A dynamic block that is a thin wrapper around the shortcode - one source
 * of truth for the booking form markup, per the plan's "block calls the
 * shortcode renderer internally" decision.
 */
class Block {

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$asset_file = YBS_PLUGIN_DIR . 'assets/build/booking-block.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script(
			'ybs-booking-block-editor',
			YBS_PLUGIN_URL . 'assets/build/booking-block.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'ybs-booking-block-editor', 'magepeople-yacht-booking-system', YBS_PLUGIN_DIR . 'languages' );

		register_block_type(
			'magepeople-yacht-booking-system/booking-form',
			array(
				'editor_script'   => 'ybs-booking-block-editor',
				'attributes'      => array(
					'yachtId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	public static function render( $attributes ) {
		return Shortcode::render_booking_form( array( 'yacht_id' => (int) ( $attributes['yachtId'] ?? 0 ) ) );
	}
}
