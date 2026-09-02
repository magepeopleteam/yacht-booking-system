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
			'name'          => __( 'Yacht Classes', 'magepeople-yacht-booking-system' ),
			'singular_name' => __( 'Yacht Class', 'magepeople-yacht-booking-system' ),
			'search_items'  => __( 'Search Classes', 'magepeople-yacht-booking-system' ),
			'all_items'     => __( 'All Classes', 'magepeople-yacht-booking-system' ),
			'edit_item'     => __( 'Edit Class', 'magepeople-yacht-booking-system' ),
			'add_new_item'  => __( 'Add New Class', 'magepeople-yacht-booking-system' ),
			'menu_name'     => __( 'Classes', 'magepeople-yacht-booking-system' ),
		);
	}

	public static function default_terms(): array {
		return array(
			__( 'Comfort', 'magepeople-yacht-booking-system' ),
			__( 'Comfort Plus', 'magepeople-yacht-booking-system' ),
			__( 'Business', 'magepeople-yacht-booking-system' ),
			__( 'First Class', 'magepeople-yacht-booking-system' ),
			__( 'Party', 'magepeople-yacht-booking-system' ),
		);
	}
}
