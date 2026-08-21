<?php
namespace Ybs\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YachtClass extends AbstractTaxonomy {

	public static function slug(): string {
		return 'yacht_class';
	}

	public static function labels(): array {
		return array(
			'name'          => __( 'Yacht Classes', 'yacht-booking-system' ),
			'singular_name' => __( 'Yacht Class', 'yacht-booking-system' ),
			'search_items'  => __( 'Search Classes', 'yacht-booking-system' ),
			'all_items'     => __( 'All Classes', 'yacht-booking-system' ),
			'edit_item'     => __( 'Edit Class', 'yacht-booking-system' ),
			'add_new_item'  => __( 'Add New Class', 'yacht-booking-system' ),
			'menu_name'     => __( 'Classes', 'yacht-booking-system' ),
		);
	}

	public static function default_terms(): array {
		return array(
			__( 'Comfort', 'yacht-booking-system' ),
			__( 'Comfort Plus', 'yacht-booking-system' ),
			__( 'Business', 'yacht-booking-system' ),
			__( 'First Class', 'yacht-booking-system' ),
			__( 'Party', 'yacht-booking-system' ),
		);
	}
}
