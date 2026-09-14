<?php
/**
 * Persistent, generation-based product catalog for the official Torob API v3.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Torob_V3_Catalog {
	public const TABLE_SUFFIX = 'tves_torob_v3_products';
	private const ACTIVE_GENERATION = 'tves_v3_active_generation';
	private const PAGE_SIZE = 100;

	/** Create or update the catalog table. */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_unique varchar(200) NOT NULL,
			page_url text NOT NULL,
			url_hash char(64) NOT NULL,
			lookup_hash char(64) NOT NULL DEFAULT '',
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			generation varchar(64) NOT NULL,
			date_added datetime NOT NULL,
			date_updated datetime NOT NULL,
			payload longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY page_unique_generation (page_unique,generation),
			KEY url_hash_generation (url_hash,generation),
			KEY lookup_hash_generation (lookup_hash,generation),
			KEY generation_added (generation,date_added),
			KEY generation_updated (generation,date_updated)
		) {$charset_collate};";

		dbDelta( $sql );
		self::backfill_lookup_hashes();
	}

	/** Store one mapped v3 item in an inactive generation. */
	public function upsert( array $product, string $generation ): bool {
		global $wpdb;

		$page_unique = substr( (string) ( $product['page_unique'] ?? '' ), 0, 200 );
		$page_url    = (string) ( $product['page_url'] ?? '' );
		if ( '' === $page_unique || '' === $page_url ) {
			return false;
		}

		$product_id = absint( $product['_product_id'] ?? 0 );
		$parent_id  = absint( $product['_parent_id'] ?? 0 );
		$date_added = self::mysql_date( (string) ( $product['date_added'] ?? '' ) );
		$date_updated = self::mysql_date( (string) ( $product['date_updated'] ?? $product['date_added'] ?? '' ) );
		unset( $product['_product_id'], $product['_parent_id'] );
		$payload = wp_json_encode( $product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $payload ) || '' === $payload ) {
			return false;
		}

		$table = self::table_name();
		$sql   = "INSERT INTO {$table}
			(page_unique,page_url,url_hash,lookup_hash,product_id,parent_id,generation,date_added,date_updated,payload)
			VALUES (%s,%s,%s,%s,%d,%d,%s,%s,%s,%s)
			ON DUPLICATE KEY UPDATE page_url=VALUES(page_url),url_hash=VALUES(url_hash),lookup_hash=VALUES(lookup_hash),product_id=VALUES(product_id),parent_id=VALUES(parent_id),date_added=VALUES(date_added),date_updated=VALUES(date_updated),payload=VALUES(payload)";

		return false !== $wpdb->query(
			$wpdb->prepare(
				$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$page_unique,
				$page_url,
				hash( 'sha256', self::normalize_url_for_lookup( $page_url ) ),
				hash( 'sha256', self::normalize_url_for_lookup( $page_url, true ) ),
				$product_id,
				$parent_id,
				sanitize_key( $generation ),
				$date_added,
				$date_updated,
				$payload
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/** Promote a completed generation atomically and remove older snapshots. */
	public function activate_generation( string $generation ): void {
		global $wpdb;

		$generation = sanitize_key( $generation );
		update_option( self::ACTIVE_GENERATION, $generation, false );
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::table_name() . ' WHERE generation <> %s',
				$generation
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/** Remove an incomplete snapshot after a failed synchronization. */
	public function discard_generation( string $generation ): void {
		global $wpdb;
		$generation = sanitize_key( $generation );
		if ( '' === $generation || $generation === self::active_generation() ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE generation = %s', $generation )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/** Return an exact 100-item official API page (except the final page). */
	public function get_page( int $page, string $sort ): array {
		global $wpdb;

		$generation = self::active_generation();
		if ( '' === $generation ) {
			return array( 'products' => array(), 'total' => 0, 'max_pages' => 0 );
		}

		$table   = self::table_name();
		$order   = 'date_updated_desc' === $sort ? 'date_updated' : 'date_added';
		$total   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE generation = %s", $generation ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$offset  = ( max( 1, $page ) - 1 ) * self::PAGE_SIZE;
		$payloads = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT payload FROM {$table} WHERE generation = %s ORDER BY {$order} DESC, page_unique DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$generation,
				self::PAGE_SIZE,
				$offset
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return array(
			'products'  => self::decode_payloads( $payloads ),
			'total'     => $total,
			'max_pages' => $total > 0 ? (int) ceil( $total / self::PAGE_SIZE ) : 0,
		);
	}

	/** Find products by stable identifiers while retaining request order. */
	public function find_by_uniques( array $uniques ): array {
		return $this->find_many( 'page_unique', array_values( $uniques ) );
	}

	/** Find products by normalized canonical URLs while retaining request order. */
	public function find_by_urls( array $urls ): array {
		$urls            = array_values( array_map( 'strval', $urls ) );
		$normalized_urls = array_map( array( __CLASS__, 'normalize_url_for_lookup' ), array_values( $urls ) );
		$stable_urls     = array_map( static fn( string $url ): string => self::normalize_url_for_lookup( $url, true ), $urls );
		$hashes          = array();
		foreach ( $urls as $index => $url ) {
			// The raw hash keeps exact lookups compatible with existing catalogs;
			// the normalized hash is used by newly generated catalogs.
			$hashes[] = hash( 'sha256', (string) $url );
			$hashes[] = hash( 'sha256', $normalized_urls[ $index ] );
		}
		$products = $this->find_many( 'url_hash', $hashes );
		$by_url   = array();
		foreach ( $products as $product ) {
			$by_url[ self::normalize_url_for_lookup( (string) $product['page_url'] ) ] = $product;
		}

		$missing_stable_urls = array();
		foreach ( $normalized_urls as $index => $url ) {
			if ( ! isset( $by_url[ $url ] ) ) {
				$missing_stable_urls[] = $stable_urls[ $index ];
			}
		}

		$by_stable_url = array();
		if ( $missing_stable_urls ) {
			$stable_hashes  = array_map( static fn( string $url ): string => hash( 'sha256', $url ), array_values( array_unique( $missing_stable_urls ) ) );
			$stable_matches = $this->find_many( 'lookup_hash', $stable_hashes );
			foreach ( $stable_matches as $product ) {
				$key = self::normalize_url_for_lookup( (string) $product['page_url'], true );
				$by_stable_url[ $key ][] = $product;
			}
		}

		$output = array();
		foreach ( $normalized_urls as $index => $url ) {
			if ( isset( $by_url[ $url ] ) ) {
				$output[] = $by_url[ $url ];
				continue;
			}

			$candidates = $by_stable_url[ $stable_urls[ $index ] ] ?? array();
			if ( 1 === count( $candidates ) ) {
				$output[] = $candidates[0];
				continue;
			}

			// If a catalog has an unusual duplicate attribute combination, prefer
			// the exact variation identifier rather than returning an arbitrary item.
			$requested_variation = self::variation_id_from_url( $urls[ $index ] );
			foreach ( $candidates as $candidate ) {
				if ( $requested_variation > 0 && (string) $requested_variation === (string) ( $candidate['page_unique'] ?? '' ) ) {
					$output[] = $candidate;
					break;
				}
			}
		}
		return $output;
	}

	/** Determine whether a requested URL identifies the same current catalog item. */
	public static function urls_match( string $requested_url, string $catalog_url ): bool {
		return self::normalize_url_for_lookup( $requested_url ) === self::normalize_url_for_lookup( $catalog_url )
			|| self::normalize_url_for_lookup( $requested_url, true ) === self::normalize_url_for_lookup( $catalog_url, true );
	}

	/** Catalog readiness information for the admin UI. */
	public static function get_stats(): array {
		global $wpdb;
		$generation = self::active_generation();
		$total      = 0;
		if ( '' !== $generation ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE generation = %s', $generation ) );
		}
		return array( 'ready' => '' !== $generation, 'total' => $total, 'generation' => $generation );
	}

	private function find_many( string $column, array $values ): array {
		global $wpdb;
		$generation = self::active_generation();
		$values     = array_values( array_unique( array_filter( array_map( 'strval', $values ) ) ) );
		if ( '' === $generation || ! $values || ! in_array( $column, array( 'page_unique', 'url_hash', 'lookup_hash' ), true ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
		$params       = array_merge( array( $generation ), $values );
		$sql          = 'SELECT payload FROM ' . self::table_name() . " WHERE generation = %s AND {$column} IN ({$placeholders})";
		$payloads     = $wpdb->get_col( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$decoded      = self::decode_payloads( $payloads );

		if ( 'page_unique' === $column ) {
			$map = array();
			foreach ( $decoded as $product ) {
				$map[ (string) $product['page_unique'] ] = $product;
			}
			$decoded = array_values( array_filter( array_map( static fn( string $value ) => $map[ $value ] ?? null, $values ) ) );
		}
		return $decoded;
	}

	private static function decode_payloads( array $payloads ): array {
		$output = array();
		foreach ( $payloads as $payload ) {
			$item = json_decode( (string) $payload, true );
			if ( is_array( $item ) ) {
				// Associative decoding turns an empty JSON object into an empty PHP
				// array. Restore the object type required by Torob before WordPress
				// encodes the REST response, including for payloads stored by older
				// plugin versions.
				$item['spec'] = isset( $item['spec'] ) && is_array( $item['spec'] )
					? (object) $item['spec']
					: (object) array();
				$output[] = $item;
			}
		}
		return $output;
	}

	/**
	 * Normalize harmless URL differences used by Torob single-product lookups.
	 */
	private static function normalize_url_for_lookup( string $url, bool $ignore_variation_id = false ): string {
		$url   = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $url;
		}

		$host = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = rawurldecode( (string) ( $parts['path'] ?? '/' ) );
		$path = '/' . ltrim( (string) preg_replace( '#/+#', '/', $path ), '/' );
		$path = '/' === $path ? '/' : rtrim( $path, '/' ) . '/';

		$query = '';
		if ( ! empty( $parts['query'] ) ) {
			$query_args = array();
			parse_str( (string) $parts['query'], $query_args );
			if ( $ignore_variation_id ) {
				unset( $query_args['variation_id'] );
			}
			self::sort_query_args( $query_args );
			$query = http_build_query( $query_args, '', '&', PHP_QUERY_RFC3986 );
		}

		return $host . $port . $path . ( '' !== $query ? '?' . $query : '' );
	}

	/** Read a variation ID from a requested product URL when one is present. */
	private static function variation_id_from_url( string $url ): int {
		$query = (string) wp_parse_url( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), PHP_URL_QUERY );
		if ( '' === $query ) {
			return 0;
		}
		$args = array();
		parse_str( $query, $args );
		return absint( $args['variation_id'] ?? 0 );
	}

	/** Populate the stable URL index for catalogs created by earlier versions. */
	private static function backfill_lookup_hashes(): void {
		global $wpdb;

		$table   = self::table_name();
		$last_id = 0;
		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id,page_url FROM {$table} WHERE id > %d AND (lookup_hash = '' OR lookup_hash IS NULL) ORDER BY id ASC LIMIT 500", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$last_id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			foreach ( (array) $rows as $row ) {
				$last_id = max( $last_id, absint( $row->id ?? 0 ) );
				$wpdb->update(
					$table,
					array( 'lookup_hash' => hash( 'sha256', self::normalize_url_for_lookup( (string) ( $row->page_url ?? '' ), true ) ) ),
					array( 'id' => $last_id ),
					array( '%s' ),
					array( '%d' )
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			}
		} while ( 500 === count( (array) $rows ) );
	}

	/** Recursively sort query parameters to make their order irrelevant. */
	private static function sort_query_args( array &$args ): void {
		ksort( $args, SORT_STRING );
		foreach ( $args as &$value ) {
			if ( is_array( $value ) ) {
				self::sort_query_args( $value );
			}
		}
		unset( $value );
	}

	private static function mysql_date( string $iso_date ): string {
		$timestamp = strtotime( $iso_date );
		return gmdate( 'Y-m-d H:i:s', false === $timestamp ? time() : $timestamp );
	}

	private static function active_generation(): string {
		return sanitize_key( (string) get_option( self::ACTIVE_GENERATION, '' ) );
	}

	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}
}
