<?php
namespace MageYaBo\Taxonomies;

use MageYaBo\PostTypes\Yacht;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared shape for the two yacht taxonomies: both are admin-editable,
 * frontend-filterable, and ship with a starter term list seeded once.
 */
abstract class AbstractTaxonomy {

	abstract public static function slug(): string;

	/**
	 * Public URL segment for term archives. Kept human-readable and separate
	 * from the registered taxonomy name, which carries the plugin prefix.
	 */
	public static function rewrite_slug(): string {
		return str_replace( array( 'mageyabo_', '_' ), array( '', '-' ), static::slug() );
	}

	abstract public static function labels(): array;

	/**
	 * @return string[] Default term names to seed on first activation.
	 */
	abstract public static function default_terms(): array;

	public static function register() {
		register_taxonomy(
			static::slug(),
			Yacht::POST_TYPE,
			array(
				'labels'            => static::labels(),
				'public'            => true,
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => static::rewrite_slug() ),
			)
		);
	}

	public static function maybe_seed_default_terms() {
		$flag = 'mageyabo_' . static::slug() . '_terms_seeded';

		if ( get_option( $flag ) ) {
			return;
		}

		foreach ( static::default_terms() as $term ) {
			if ( ! term_exists( $term, static::slug() ) ) {
				wp_insert_term( $term, static::slug() );
			}
		}

		update_option( $flag, 1 );
	}
}
