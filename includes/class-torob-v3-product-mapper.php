<?php
/**
 * Map legacy feed items to the official Torob Product API v3 schema.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Torob_V3_Product_Mapper {
	/** Map one synchronized item. Internal keys are removed before storage. */
	public function map( array $item ): ?array {
		$product_id = absint( $item['id'] ?? 0 );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$parent_id = absint( $item['parent_id'] ?? 0 );
		$parent    = $parent_id ? wc_get_product( $parent_id ) : $product;
		if ( ! $parent instanceof WC_Product ) {
			$parent = $product;
		}

		$available     = $product->is_purchasable() && $product->is_in_stock();
		$current_price = $available ? $this->price_to_toman( $product->get_price() ) : 0;
		$regular_price = $this->price_to_toman( $product->get_regular_price() );
		$date_added    = $product->get_date_created() ?: $parent->get_date_created();
		$date_updated  = $product->get_date_modified() ?: $parent->get_date_modified() ?: $date_added;

		$output = array(
			'page_unique' => substr( (string) $product_id, 0, 200 ),
			'page_url'    => $this->truncate( esc_url_raw( (string) ( $item['url'] ?? $product->get_permalink() ) ), 1500 ),
			'title'       => $this->truncate( wp_strip_all_tags( (string) ( $item['title'] ?? $product->get_name() ) ), 500 ),
			'current_price' => max( 0, $current_price ),
			'availability'  => (bool) $available,
			'image_links'   => $this->get_images( $product, $parent ),
			'spec'          => $this->get_spec( (array) ( $item['attributes'] ?? array() ), $parent ),
			'date_added'    => $this->iso_date( $date_added ),
			'date_updated'  => $this->iso_date( $date_updated ),
			'_product_id'   => $product_id,
			'_parent_id'    => $parent_id,
		);

		if ( $parent_id ) {
			$output['product_group_id'] = (string) $parent_id;
		}
		if ( $regular_price > $current_price && $current_price > 0 ) {
			$output['old_price'] = $regular_price;
		}

		$category = $this->get_category( $parent );
		if ( '' !== $category ) {
			$output['category_name'] = $this->truncate( $category, 200 );
		}
		$short_description = trim( wp_strip_all_tags( (string) $parent->get_short_description() ) );
		if ( '' !== $short_description ) {
			$output['short_desc'] = $this->truncate( $short_description, 500 );
		}

		$output = apply_filters( 'tves_torob_v3_product', $output, $product, $parent, $item );
		if ( is_array( $output ) && isset( $output['spec'] ) && is_array( $output['spec'] ) ) {
			$output['spec'] = (object) $output['spec'];
		}
		return $this->validate( is_array( $output ) ? $output : array() ) ? $output : null;
	}

	private function price_to_toman( $price ): int {
		if ( '' === $price || null === $price || ! is_numeric( $price ) ) {
			return 0;
		}
		$value    = (float) $price;
		$currency = strtoupper( (string) get_woocommerce_currency() );
		if ( 'IRR' === $currency ) {
			$value /= 10;
		}
		return max( 0, (int) round( (float) apply_filters( 'tves_torob_v3_price_toman', $value, $price, $currency ) ) );
	}

	private function get_images( WC_Product $product, WC_Product $parent ): array {
		$ids = array_filter( array_unique( array_merge(
			array( $product->get_image_id(), $parent->get_image_id() ),
			(array) $parent->get_gallery_image_ids()
		) ) );
		$urls = array();
		foreach ( $ids as $id ) {
			$url = wp_get_attachment_image_url( absint( $id ), 'full' );
			if ( $url ) {
				$urls[] = $this->truncate( esc_url_raw( $url ), 1000 );
			}
		}
		return array_values( array_unique( $urls ) );
	}

	private function get_spec( array $attributes, WC_Product $parent ): object {
		$spec = array();
		foreach ( $attributes as $slug => $value ) {
			$label = wc_attribute_label( (string) $slug, $parent );
			$label = $label ?: (string) $slug;
			$spec[ $this->truncate( wp_strip_all_tags( $label ), 200 ) ] = $this->truncate( wp_strip_all_tags( (string) $value ), 500 );
		}
		return (object) $spec;
	}

	private function get_category( WC_Product $product ): string {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( ! is_array( $terms ) || ! $terms ) {
			return '';
		}
		usort( $terms, static fn( WP_Term $a, WP_Term $b ): int => count( get_ancestors( $b->term_id, 'product_cat' ) ) <=> count( get_ancestors( $a->term_id, 'product_cat' ) ) );
		return (string) $terms[0]->name;
	}

	private function iso_date( $date ): string {
		if ( $date instanceof WC_DateTime || $date instanceof DateTimeInterface ) {
			return $date->format( DATE_ATOM );
		}
		return wp_date( DATE_ATOM );
	}

	private function validate( array $product ): bool {
		return '' !== (string) ( $product['page_unique'] ?? '' )
			&& '' !== (string) ( $product['page_url'] ?? '' )
			&& '' !== (string) ( $product['title'] ?? '' )
			&& isset( $product['current_price'], $product['availability'], $product['image_links'], $product['spec'], $product['date_added'], $product['date_updated'] )
			&& is_array( $product['image_links'] )
			&& is_object( $product['spec'] );
	}

	private function truncate( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
