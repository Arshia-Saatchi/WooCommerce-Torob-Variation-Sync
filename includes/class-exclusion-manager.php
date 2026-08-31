<?php
/**
 * Product, variation, and category exclusion rules.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Exclusion_Manager {
	/** @var array<int, int> */
	private array $product_ids;

	/** @var array<int, int> */
	private array $variation_ids;

	/** @var array<int, int> */
	private array $category_ids;

	public function __construct() {
		$settings            = (array) get_option( 'tves_settings', array() );
		$this->product_ids   = array_map( 'absint', (array) ( $settings['excluded_products'] ?? array() ) );
		$this->variation_ids = array_map( 'absint', (array) ( $settings['excluded_variations'] ?? array() ) );
		$this->category_ids  = array_map( 'absint', (array) ( $settings['excluded_categories'] ?? array() ) );
	}

	/**
	 * Determine whether a simple or parent product is excluded.
	 */
	public function is_product_excluded( WC_Product $product ): bool {
		if ( in_array( $product->get_id(), $this->product_ids, true ) ) {
			return true;
		}

		$product_categories = wc_get_product_cat_ids( $product->get_id() );
		foreach ( $product_categories as $category_id ) {
			$product_categories = array_merge( $product_categories, get_ancestors( $category_id, 'product_cat', 'taxonomy' ) );
		}

		return (bool) array_intersect( $this->category_ids, array_unique( array_map( 'absint', $product_categories ) ) );
	}

	/**
	 * Determine whether an individual variation is excluded.
	 */
	public function is_variation_excluded( WC_Product_Variation $variation ): bool {
		return in_array( $variation->get_id(), $this->variation_ids, true )
			|| in_array( $variation->get_parent_id(), $this->product_ids, true );
	}
}
