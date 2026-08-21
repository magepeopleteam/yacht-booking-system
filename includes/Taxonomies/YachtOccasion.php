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
			'name'          => __( 'Occasions', 'yacht-booking-system' ),
			'singular_name' => __( 'Occasion', 'yacht-booking-system' ),
			'search_items'  => __( 'Search Occasions', 'yacht-booking-system' ),
			'all_items'     => __( 'All Occasions', 'yacht-booking-system' ),
			'edit_item'     => __( 'Edit Occasion', 'yacht-booking-system' ),
			'add_new_item'  => __( 'Add New Occasion', 'yacht-booking-system' ),
			'menu_name'     => __( 'Occasions', 'yacht-booking-system' ),
		);
	}

	public static function default_terms(): array {
		return array(
			__( 'Birthday', 'yacht-booking-system' ),
			__( 'Anniversary / Proposal', 'yacht-booking-system' ),
			__( 'Corporate', 'yacht-booking-system' ),
			__( 'Bachelorette', 'yacht-booking-system' ),
			__( 'Wedding', 'yacht-booking-system' ),
			__( 'Sunset Cocktail', 'yacht-booking-system' ),
		);
	}
}
