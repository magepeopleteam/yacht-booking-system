<?php
namespace MageYaBo\Frontend;

use MageYaBo\PostTypes\Yacht;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the plugin's own full-page single-yacht template, overridable by the
 * active theme at `yourtheme/magepeople-yacht-booking-system/single-yacht.php` (child
 * theme wins over parent theme). Falls back to the copy shipped inside
 * `plugins/magepeople-yacht-booking-system/templates/`.
 *
 * While a YBS template is in play the legacy `the_content` renderer is
 * removed so the page isn't rendered twice.
 */
class Templates {

	const THEME_DIR = 'magepeople-yacht-booking-system';

	/**
	 * Theme blocks that duplicate something Shortcode::append_to_single_yacht()
	 * already renders itself, further down in the same block template.
	 */
	const DUPLICATE_THEME_BLOCKS = array(
		// Duplicates the gallery's own hero image/thumbnail strip.
		'core/post-featured-image',
		// Duplicates `.ybs-yp-title` in the gallery's header section.
		'core/post-title',
	);

	public static function register() {
		add_filter( 'template_include', array( __CLASS__, 'load_single_yacht_template' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'render_block', array( __CLASS__, 'suppress_duplicate_theme_blocks' ), 10, 2 );
	}

	/**
	 * On a block theme, the single-yacht page renders through WordPress's own
	 * singular block template (see load_single_yacht_template() below) so its
	 * header/footer always match the rest of the site - but that template's
	 * own title/featured-image blocks then duplicate what
	 * Shortcode::append_to_single_yacht() already renders as part of its
	 * richer gallery/header section. Drop the theme's copies rather than the
	 * plugin's - the plugin's versions carry the styling (class badge,
	 * stats row, thumbnail strip) the bare theme blocks don't.
	 */
	public static function suppress_duplicate_theme_blocks( $block_content, $block ) {
		if ( in_array( $block['blockName'] ?? '', self::DUPLICATE_THEME_BLOCKS, true ) && is_singular( Yacht::POST_TYPE ) ) {
			return '';
		}

		return $block_content;
	}

	public static function register_assets() {
		wp_register_style( 'mageyabo-single', MAGEYABO_PLUGIN_URL . 'assets/frontend/yacht-single.css', array( 'mageyabo-frontend' ), MAGEYABO_VERSION );
		wp_register_script( 'mageyabo-single', MAGEYABO_PLUGIN_URL . 'assets/frontend/yacht-single.js', array(), MAGEYABO_VERSION, true );

		// Enqueued here (rather than only inside load_single_yacht_template())
		// so a block theme - which never takes the custom-template branch
		// below - still gets the single-yacht styles/behaviour when the page
		// renders through Shortcode::append_to_single_yacht() instead.
		if ( is_singular( Yacht::POST_TYPE ) ) {
			wp_enqueue_style( 'mageyabo-frontend' );
			wp_enqueue_script( 'mageyabo-frontend' );
			wp_enqueue_style( 'mageyabo-single' );
			wp_enqueue_script( 'mageyabo-single' );
		}
	}

	/**
	 * @param string $template Template path chosen by WordPress so far.
	 * @return string Possibly-replaced template path.
	 */
	public static function load_single_yacht_template( $template ) {
		if ( ! is_singular( Yacht::POST_TYPE ) ) {
			return $template;
		}

		// Block themes have no header.php/footer.php for document_start()/
		// document_end() to call get_header()/get_footer() through; the
		// hand-built page shell those fall back to (replicating core's
		// template-canvas markup) doesn't reliably match every theme's real
		// header/footer template parts - global-styles wrappers, layout
		// constraints, etc. can end up looking subtly different from the
		// rest of the site. Rather than risk that mismatch, block themes
		// keep WordPress's own singular template here; the exact same
		// design still renders, through Shortcode::append_to_single_yacht()
		// on `the_content`, just inside the theme's real header/footer.
		if ( self::is_block_theme() ) {
			return $template;
		}

		$override = locate_template( array( self::THEME_DIR . '/single-yacht.php' ) );

		if ( ! $override ) {
			$override = MAGEYABO_PLUGIN_DIR . 'templates/single-yacht.php';
		}

		if ( ! file_exists( $override ) ) {
			return $template;
		}

		// The custom template renders everything itself - stop the
		// content-filter renderer from replacing post content as well.
		if ( has_filter( 'the_content', array( Shortcode::class, 'append_to_single_yacht' ) ) ) {
			remove_filter( 'the_content', array( Shortcode::class, 'append_to_single_yacht' ), 10 );
		}

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
			__( 'Skip to %s content', 'magepeople-yacht-booking-system' ),
			get_the_title()
		);

		echo '<!DOCTYPE html>
<html ';
		language_attributes();
		echo '>
<head>
<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '" />
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
