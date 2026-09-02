<?php
namespace Ybs\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YachtOccasion extends AbstractTaxonomy {

	public static function slug(): string {
		return 'yacht_occasion';
	}

	public static function labels(): array {
		return array(
			'name'          => __( 'Occasions', 'magepeople-yacht-booking-system' ),
			'singular_name' => __( 'Occasion', 'magepeople-yacht-booking-system' ),
			'search_items'  => __( 'Search Occasions', 'magepeople-yacht-booking-system' ),
			'all_items'     => __( 'All Occasions', 'magepeople-yacht-booking-system' ),
			'edit_item'     => __( 'Edit Occasion', 'magepeople-yacht-booking-system' ),
			'add_new_item'  => __( 'Add New Occasion', 'magepeople-yacht-booking-system' ),
			'menu_name'     => __( 'Occasions', 'magepeople-yacht-booking-system' ),
		);
	}

	public static function default_terms(): array {
		return array(
			__( 'Birthday', 'magepeople-yacht-booking-system' ),
			__( 'Anniversary / Proposal', 'magepeople-yacht-booking-system' ),
			__( 'Corporate', 'magepeople-yacht-booking-system' ),
			__( 'Bachelorette', 'magepeople-yacht-booking-system' ),
			__( 'Wedding', 'magepeople-yacht-booking-system' ),
			__( 'Sunset Cocktail', 'magepeople-yacht-booking-system' ),
		);
	}
}
