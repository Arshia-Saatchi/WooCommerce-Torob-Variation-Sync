<?php
/**
 * Paginated product feed generation and caching.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Feed_Generator {
	private TVES_Product_Handler $product_handler;
	private TVES_Variation_Handler $variation_handler;
	private TVES_Exclusion_Manager $exclusions;

	public function __construct( TVES_Product_Handler $product_handler, TVES_Variation_Handler $variation_handler, TVES_Exclusion_Manager $exclusions ) {
		$this->product_handler   = $product_handler;
		$this->variation_handler = $variation_handler;
		$this->exclusions        = $exclusions;
	}

	/**
	 * Generate one source-product page. Variable parents expand to variation items.
	 *
	 * Pagination is intentionally based on source products, which keeps database
	 * queries bounded even when a parent has many variations.
	 *
	 * @return array<string, mixed>
	 */
	public function get_page( int $page = 1, int $per_page = 25, bool $use_cache = true, bool $write_log = false, string $cache_version = '' ): array {
		$page          = max( 1, $page );
		$per_page      = min( 100, max( 1, $per_page ) );
		$cache_version = $cache_version ?: self::get_cache_generation();
		$cache_key     = self::cache_key( $cache_version, $page, $per_page );

		if ( $use_cache && ! $write_log ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				$cached['cache'] = 'hit';
				return $cached;
			}
		}

		$query = wc_get_products(
			array(
				'status'   => 'publish',
				'type'     => array( 'simple', 'variable' ),
				'limit'    => $per_page,
				'page'     => $page,
				'paginate' => true,
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'return'   => 'objects',
			)
		);

		$items              = array();
		$variation_export   = 'yes' === (string) TVES_Admin_Settings::get_setting( 'enabled', 'yes' );
		$source_products    = is_object( $query ) && isset( $query->products ) ? $query->products : array();
		$total              = is_object( $query ) && isset( $query->total ) ? (int) $query->total : count( $source_products );
		$total_pages        = is_object( $query ) && isset( $query->max_num_pages ) ? (int) $query->max_num_pages : 1;

		foreach ( $source_products as $product ) {
			if ( ! $product instanceof WC_Product || $this->exclusions->is_product_excluded( $product ) ) {
				continue;
			}

			try {
				if ( $product->is_type( 'simple' ) ) {
					$item = $this->product_handler->transform( $product, $write_log );
					if ( $item ) {
						$items[] = $item;
					}
					continue;
				}

				if ( $product->is_type( 'variable' ) && $variation_export ) {
					foreach ( $product->get_children() as $variation_id ) {
						$variation = wc_get_product( $variation_id );
						if ( ! $variation instanceof WC_Product_Variation ) {
							if ( $write_log ) {
								TVES_Logger::log( 'invalid', __( 'A variation ID did not resolve to a valid WooCommerce variation.', 'torob-variable-exporter' ), $product->get_id(), (int) $variation_id );
							}
							continue;
						}
						if ( $this->exclusions->is_variation_excluded( $variation ) ) {
							continue;
						}
						try {
							$item = $this->variation_handler->transform( $variation, $product, $write_log );
							if ( $item ) {
								$items[] = $item;
							}
						} catch ( Throwable $exception ) {
							TVES_Logger::log( 'error', $exception->getMessage(), $product->get_id(), $variation->get_id(), array( 'exception' => get_class( $exception ) ) );
						}
					}
				}
			} catch ( Throwable $exception ) {
				TVES_Logger::log( 'error', $exception->getMessage(), $product->get_id(), 0, array( 'exception' => get_class( $exception ) ) );
			}
		}

		$result = array(
			'products'   => array_values( $items ),
			'pagination' => array(
				'page'                  => $page,
				'per_page'              => $per_page,
				'total_source_products' => $total,
				'total_pages'           => $total_pages,
				'has_more'              => $page < $total_pages,
			),
			'generated_at' => gmdate( 'c' ),
			'currency'     => get_woocommerce_currency(),
			'cache'        => 'miss',
		);

		set_transient( $cache_key, $result, 2 * DAY_IN_SECONDS );
		return $result;
	}

	/**
	 * Change the active cache namespace, instantly invalidating prior pages.
	 */
	public static function bump_cache_generation(): string {
		$generation = wp_generate_uuid4();
		update_option( 'tves_cache_generation', $generation, false );
		return $generation;
	}

	/**
	 * Get the active cache namespace.
	 */
	public static function get_cache_generation(): string {
		$generation = (string) get_option( 'tves_cache_generation', '' );
		if ( '' === $generation ) {
			$generation = self::bump_cache_generation();
		}
		return $generation;
	}

	/**
	 * Atomically promote a completed sync cache namespace.
	 */
	public static function activate_cache_generation( string $generation ): void {
		update_option( 'tves_cache_generation', sanitize_key( $generation ), false );
	}

	private static function cache_key( string $version, int $page, int $per_page ): string {
		return 'tves_f_' . substr( md5( $version . '|' . $page . '|' . $per_page ), 0, 24 );
	}
}
