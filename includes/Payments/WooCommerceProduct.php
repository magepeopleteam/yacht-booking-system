<?php
namespace Ybs\Payments;

use Ybs\PostTypes\Yacht;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hidden WooCommerce product per yacht, mirroring the mage-eventpress
 * pattern: when a yacht is published a linked simple virtual product is
 * created with the same name; the product title is re-synced on every
 * yacht save; and the product is hidden from the shop, search, direct
 * URLs and the admin products list - it exists only as the cart/checkout
 * carrier for yacht bookings.
 *
 * Meta links (mage-eventpress compatible naming):
 *   yacht  -> _ybs_wc_product_id   (kept for backwards compatibility)
 *   yacht  -> link_wc_product      (canonical, MEP-style)
 *   product-> link_ybs_yacht
 */
class WooCommerceProduct {

	const PRODUCT_META_KEY = 'link_ybs_yacht';

	public static function register() {
		add_action( 'save_post_yacht', array( __CLASS__, 'sync_on_save' ), 99 );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_linked_product' ) );

		// This plugin loads before WooCommerce, so these are registered
		// unconditionally and only fire when WooCommerce is active.
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'force_purchasable' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_from_search' ) );
		add_action( 'parse_query', array( __CLASS__, 'hide_from_admin_list' ) );
		add_action( 'wp', array( __CLASS__, 'block_direct_access' ) );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'exclude_from_sitemap' ) );
	}

	/**
	 * Product id linked to a yacht, creating it lazily if WooCommerce is
	 * active but the sync hooks have not run yet (e.g. yachts published
	 * before this integration was enabled).
	 */
	public static function get_product_id( $yacht_id ) {
		$product_id = (int) get_post_meta( $yacht_id, '_ybs_wc_product_id', true );

		if ( ! $product_id ) {
			$product_id = (int) get_post_meta( $yacht_id, 'link_wc_product', true );
		}

		if ( $product_id && get_post( $product_id ) ) {
			// Backfill the link meta for products created by older versions.
			if ( (int) get_post_meta( $product_id, self::PRODUCT_META_KEY, true ) !== (int) $yacht_id ) {
				update_post_meta( $product_id, self::PRODUCT_META_KEY, (int) $yacht_id );
			}

			return $product_id;
		}

		return self::create_product( $yacht_id );
	}

	public static function get_yacht_id( $product_id ) {
		$yacht_id = (int) get_post_meta( $product_id, self::PRODUCT_META_KEY, true );

		if ( $yacht_id ) {
			return $yacht_id;
		}

		// Products created before the link meta existed - resolve through
		// the yacht-side pointer instead.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reverse meta_value lookup, which get_post_meta() cannot do; runs only on the legacy fallback path.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_ybs_wc_product_id','link_wc_product') AND meta_value = %s AND post_id != %d LIMIT 1",
				(string) (int) $product_id,
				(int) $product_id
			)
		);
	}

	/**
	 * Runs on every yacht save: creates the hidden product on first
	 * publish and keeps its name/status in sync afterwards.
	 */
	public static function sync_on_save( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post || Yacht::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		$product_id = (int) get_post_meta( $post_id, 'link_wc_product', true );

		if ( ! $product_id ) {
			$product_id = self::get_product_id( $post_id );

			if ( ! $product_id ) {
				return;
			}
		}

		// Keep the product alive and identically named, like MEP does.
		remove_action( 'save_post_yacht', array( __CLASS__, 'sync_on_save' ), 99 );
		wp_publish_post( $product_id );
		wp_update_post(
			array(
				'ID'         => $product_id,
				'post_title' => get_the_title( $post_id ),
				'post_name'  => uniqid( 'ybs-' ),
			)
		);
		set_post_thumbnail( $product_id, get_post_thumbnail_id( $post_id ) );
		add_action( 'save_post_yacht', array( __CLASS__, 'sync_on_save' ), 99 );
	}

	private static function create_product( $yacht_id ) {
		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			return 0;
		}

		$product = new \WC_Product_Simple();
		$product->set_name( get_the_title( $yacht_id ) ?: __( 'Yacht Charter', 'magepeople-yacht-booking-system' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product_id = $product->save();

		if ( ! $product_id ) {
			return 0;
		}

		update_post_meta( $product_id, self::PRODUCT_META_KEY, (int) $yacht_id );
		update_post_meta( $yacht_id, '_ybs_wc_product_id', $product_id );
		update_post_meta( $yacht_id, 'link_wc_product', $product_id );

		return $product_id;
	}

	/**
	 * Hidden catalog visibility would normally block add-to-cart; keep the
	 * linked product purchasable while its yacht is live.
	 */
	public static function force_purchasable( $purchasable, $product ) {
		if ( ! $purchasable && $product instanceof \WC_Product ) {
			$yacht_id = self::get_yacht_id( $product->get_id() );

			if ( $yacht_id && 'publish' === get_post_status( $yacht_id ) ) {
				return true;
			}
		}

		return $purchasable;
	}

	/**
	 * Keep hidden yacht products out of front-end search results.
	 */
	public static function hide_from_search( $query ) {
		if ( is_admin() || ! $query->is_search ) {
			return;
		}

		$tax_query = (array) $query->get( 'tax_query' );

		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => 'exclude-from-search',
			'operator' => 'NOT IN',
		);

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Hide them from the WP admin Products list too.
	 */
	public static function hide_from_admin_list( $query ) {
		if ( is_admin() && $query->is_main_query() && isset( $query->query_vars['post_type'] ) && 'product' === $query->query_vars['post_type'] ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => 'exclude-from-catalog',
						'operator' => 'NOT IN',
					),
				)
			);
		}
	}

	/**
	 * Anyone hitting a hidden product URL directly gets a 404.
	 */
	public static function block_direct_access() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = get_queried_object_id();

		if ( $product_id && self::get_yacht_id( $product_id ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	public static function exclude_from_sitemap( $excluded ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- collects every linked product id for sitemap exclusion; no core API returns this set.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::PRODUCT_META_KEY
			)
		);

		return array_merge( (array) $excluded, array_map( 'intval', $ids ) );
	}

	/**
	 * Delete the hidden product along with its yacht.
	 */
	public static function delete_linked_product( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || Yacht::POST_TYPE !== $post->post_type ) {
			return;
		}

		$product_id = (int) get_post_meta( $post_id, 'link_wc_product', true );

		if ( $product_id && 'product' === get_post_type( $product_id ) ) {
			wp_delete_post( $product_id, true );
		}
	}
}
