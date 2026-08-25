<?php
namespace Ybs\Frontend;

use Ybs\PostTypes\Yacht;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the plugin's own full-page single-yacht template, overridable by the
 * active theme at `yourtheme/yacht-booking-system/single-yacht.php` (child
 * theme wins over parent theme). Falls back to the copy shipped inside
 * `plugins/yacht-booking-system/templates/`.
 *
 * While a YBS template is in play the legacy `the_content` renderer is
 * removed so the page isn't rendered twice.
 */
class Templates {

	const THEME_DIR = 'yacht-booking-system';

	public static function register() {
		add_filter( 'template_include', array( __CLASS__, 'load_single_yacht_template' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'ybs-single', YBS_PLUGIN_URL . 'assets/frontend/yacht-single.css', array( 'ybs-frontend' ), YBS_VERSION );
		wp_register_script( 'ybs-single', YBS_PLUGIN_URL . 'assets/frontend/yacht-single.js', array(), YBS_VERSION, true );
	}

	/**
	 * @param string $template Template path chosen by WordPress so far.
	 * @return string Possibly-replaced template path.
	 */
	public static function load_single_yacht_template( $template ) {
		if ( ! is_singular( Yacht::POST_TYPE ) ) {
			return $template;
		}

		$override = locate_template( array( self::THEME_DIR . '/single-yacht.php' ) );

		if ( ! $override ) {
			$override = YBS_PLUGIN_DIR . 'templates/single-yacht.php';
		}

		if ( ! file_exists( $override ) ) {
			return $template;
		}

		// The custom template renders everything itself - stop the
		// content-filter renderer from replacing post content as well.
		if ( has_filter( 'the_content', array( Shortcode::class, 'append_to_single_yacht' ) ) ) {
			remove_filter( 'the_content', array( Shortcode::class, 'append_to_single_yacht' ), 10 );
		}

		wp_enqueue_style( 'ybs-frontend' );
		wp_enqueue_script( 'ybs-frontend' );
		wp_enqueue_style( 'ybs-single' );
		wp_enqueue_script( 'ybs-single' );

		return $override;
	}

	public static function is_block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Opens the document. Block themes have no header.php, so we replicate
	 * core's template-canvas shell and print the FSE "Header" template part;
	 * classic themes keep working through get_header().
	 */
	public static function document_start() {
		if ( ! self::is_block_theme() ) {
			get_header();
			return;
		}

		$skip_link = sprintf(
			/* translators: %s: yacht name. */
			__( 'Skip to %s content', 'yacht-booking-system' ),
			get_the_title()
		);

		echo '<!DOCTYPE html>
<html ';
		language_attributes();
		echo '>
<head>
<meta charset="';
		bloginfo( 'charset' );
		echo '" />
';
		wp_head();
		echo '</head>
<body ';
		body_class();
		echo '>
';
		wp_body_open();
		printf(
			'<a class="skip-link screen-reader-text" href="#ybs-single">%s</a>',
			esc_html( $skip_link )
		);
		echo '<div class="wp-site-blocks">
';
		block_header_area();
	}

	/**
	 * Closes the document - FSE "Footer" template part on block themes,
	 * get_footer() everywhere else.
	 */
	public static function document_end() {
		if ( ! self::is_block_theme() ) {
			get_footer();
			return;
		}

		block_footer_area();
		echo '
</div><!-- .wp-site-blocks -->
';
		wp_footer();
		echo '</body>
</html>';
	}
}
