<?php
/**
 * Simple product normalization.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Product_Handler {
	/**
	 * Convert a simple product into a feed item.
	 *
	 * @return array<string, mixed>|null
	 */
	public function transform( WC_Product $product, bool $write_log = false ): ?array {
		if ( ! $product->is_type( 'simple' ) || 'publish' !== $product->get_status() ) {
			return null;
		}

		$price = $product->get_price();
		if ( '' === $price ) {
			if ( $write_log ) {
				TVES_Logger::log( 'warning', __( 'Product skipped because it has no price.', 'torob-variable-exporter' ), $product->get_id() );
			}
			return null;
		}
		if ( $write_log && $product->managing_stock() && null === $product->get_stock_quantity() ) {
			TVES_Logger::log( 'warning', __( 'Product has stock management enabled but no stock quantity.', 'torob-variable-exporter' ), $product->get_id() );
		}

		$image_id = $product->get_image_id();
		$item     = array(
			'id'              => (string) $product->get_id(),
			'parent_id'       => null,
			'type'            => 'simple',
			'title'           => wp_strip_all_tags( $product->get_name() ),
			'variation_title' => '',
			'attributes'      => array(),
			'sku'             => (string) $product->get_sku(),
			'regular_price'   => self::price_or_null( $product->get_regular_price() ),
			'sale_price'      => self::price_or_null( $product->get_sale_price() ),
			'price'           => (float) $price,
			'availability'    => $product->is_in_stock() ? 'in_stock' : 'out_of_stock',
			'stock_quantity'  => $product->managing_stock() ? $product->get_stock_quantity() : null,
			'image'           => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'full' ) : '',
			'url'             => (string) $product->get_permalink(),
		);

		if ( $write_log ) {
			TVES_Logger::log( 'success', __( 'Simple product synchronized.', 'torob-variable-exporter' ), $product->get_id() );
		}

		return apply_filters( 'tves_simple_product_item', $item, $product );
	}

	/**
	 * Convert a WooCommerce price string to a float or null.
	 */
	public static function price_or_null( $price ): ?float {
		return '' === $price || null === $price ? null : (float) $price;
	}
}
