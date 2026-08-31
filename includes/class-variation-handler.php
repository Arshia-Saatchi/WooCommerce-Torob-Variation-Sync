<?php
/**
 * Variation normalization, titles, attributes, and deep links.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Variation_Handler {
	/**
	 * Convert one variation into an independent product item.
	 *
	 * @return array<string, mixed>|null
	 */
	public function transform( WC_Product_Variation $variation, WC_Product $parent, bool $write_log = false ): ?array {
		if ( 'publish' !== $variation->get_status() ) {
			return null;
		}

		$price = $variation->get_price();
		if ( '' === $price ) {
			if ( $write_log ) {
				TVES_Logger::log( 'warning', __( 'Variation skipped because it has no price.', 'torob-variable-exporter' ), $parent->get_id(), $variation->get_id() );
			}
			return null;
		}
		if ( $write_log && $variation->managing_stock() && null === $variation->get_stock_quantity() ) {
			TVES_Logger::log( 'warning', __( 'Variation has stock management enabled but no stock quantity.', 'torob-variable-exporter' ), $parent->get_id(), $variation->get_id() );
		}

		$attributes = $this->get_attributes( $variation, $parent );
		$image_id   = $variation->get_image_id() ?: $parent->get_image_id();
		$item       = array(
			'id'              => (string) $variation->get_id(),
			'parent_id'       => (string) $parent->get_id(),
			'type'            => 'variation',
			'title'           => $this->build_title( $parent, $attributes ),
			'variation_title' => implode( ' - ', array_values( $attributes ) ),
			'attributes'      => $attributes,
			'sku'             => (string) $variation->get_sku(),
			'regular_price'   => TVES_Product_Handler::price_or_null( $variation->get_regular_price() ),
			'sale_price'      => TVES_Product_Handler::price_or_null( $variation->get_sale_price() ),
			'price'           => (float) $price,
			'availability'    => $variation->is_in_stock() ? 'in_stock' : 'out_of_stock',
			'stock_quantity'  => $variation->managing_stock() ? $variation->get_stock_quantity() : null,
			'image'           => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'full' ) : '',
			'url'             => $this->get_variation_url( $variation, $parent ),
		);

		if ( $write_log ) {
			TVES_Logger::log( 'success', __( 'Variation synchronized.', 'torob-variable-exporter' ), $parent->get_id(), $variation->get_id() );
		}

		return apply_filters( 'tves_variation_item', $item, $variation, $parent );
	}

	/**
	 * Get selected, human-readable attributes, respecting export settings.
	 *
	 * @return array<string, string>
	 */
	public function get_attributes( WC_Product_Variation $variation, WC_Product $parent ): array {
		$settings        = (array) get_option( 'tves_settings', array() );
		$selected        = array_map( 'sanitize_title', (array) ( $settings['export_attributes'] ?? array() ) );
		$raw_attributes  = $variation->get_attributes();
		$parent_attrs    = $parent->get_attributes();
		$output          = array();

		foreach ( $raw_attributes as $name => $value ) {
			$normalized_name = sanitize_title( $name );
			if ( $selected && ! in_array( $normalized_name, $selected, true ) ) {
				continue;
			}

			$label = wc_attribute_label( $name, $parent );
			if ( taxonomy_exists( $name ) ) {
				$term = get_term_by( 'slug', $value, $name );
				$value = $term && ! is_wp_error( $term ) ? $term->name : $value;
			} elseif ( isset( $parent_attrs[ $name ] ) && '' === (string) $value ) {
				$value = implode( ', ', $parent_attrs[ $name ]->get_options() );
			}

			if ( '' !== (string) $value ) {
				$output[ $normalized_name ] = wp_strip_all_tags( (string) $value );
				TVES_Admin_Settings::remember_attribute( $normalized_name, $label );
			}
		}

		return $output;
	}

	/**
	 * Build a title using the configured strategy.
	 *
	 * Supported custom placeholders: {parent}, {attributes}, and {attribute:slug}.
	 */
	private function build_title( WC_Product $parent, array $attributes ): string {
		$settings       = (array) get_option( 'tves_settings', array() );
		$format         = (string) ( $settings['title_format'] ?? 'parent_attributes' );
		$title_selected = array_map( 'sanitize_title', (array) ( $settings['title_attributes'] ?? array() ) );
		$title_attrs    = $title_selected ? array_intersect_key( $attributes, array_flip( $title_selected ) ) : $attributes;
		$parent_name    = wp_strip_all_tags( $parent->get_name() );
		$values         = implode( ' - ', array_values( $title_attrs ) );

		if ( 'parent' === $format ) {
			return $parent_name;
		}

		if ( 'custom' === $format ) {
			$template = (string) ( $settings['title_template'] ?? '{parent} - {attributes}' );
			$replace  = array(
				'{parent}'     => $parent_name,
				'{attributes}' => $values,
			);
			foreach ( $attributes as $slug => $value ) {
				$replace[ '{attribute:' . $slug . '}' ] = $value;
			}
			return trim( preg_replace( '/\s+-\s+$/', '', strtr( $template, $replace ) ) );
		}

		return $values ? $parent_name . ' - ' . $values : $parent_name;
	}

	/**
	 * Generate a direct URL that preselects this exact variation.
	 */
	private function get_variation_url( WC_Product_Variation $variation, WC_Product $parent ): string {
		$query = array();
		foreach ( $variation->get_variation_attributes() as $key => $value ) {
			if ( '' !== (string) $value ) {
				$query[ $key ] = $value;
			}
		}
		$query['variation_id'] = $variation->get_id();

		return add_query_arg( $query, $parent->get_permalink() );
	}
}
