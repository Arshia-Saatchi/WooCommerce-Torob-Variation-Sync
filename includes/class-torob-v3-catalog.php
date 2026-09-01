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
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			generation varchar(64) NOT NULL,
			date_added datetime NOT NULL,
			date_updated datetime NOT NULL,
			payload longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY page_unique_generation (page_unique,generation),
			KEY url_hash_generation (url_hash,generation),
			KEY generation_added (generation,date_added),
			KEY generation_updated (generation,date_updated)
		) {$charset_collate};";

		dbDelta( $sql );
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
			(page_unique,page_url,url_hash,product_id,parent_id,generation,date_added,date_updated,payload)
			VALUES (%s,%s,%s,%d,%d,%s,%s,%s,%s)
			ON DUPLICATE KEY UPDATE page_url=VALUES(page_url),url_hash=VALUES(url_hash),product_id=VALUES(product_id),parent_id=VALUES(parent_id),date_added=VALUES(date_added),date_updated=VALUES(date_updated),payload=VALUES(payload)";

		return false !== $wpdb->query(
			$wpdb->prepare(
				$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$page_unique,
				$page_url,
				hash( 'sha256', $page_url ),
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

	/** Find products by exact canonical URLs while retaining request order. */
	public function find_by_urls( array $urls ): array {
		$hashes   = array_map( static fn( string $url ): string => hash( 'sha256', $url ), array_values( $urls ) );
		$products = $this->find_many( 'url_hash', $hashes );
		$by_url   = array();
		foreach ( $products as $product ) {
			$by_url[ (string) $product['page_url'] ] = $product;
		}
		$output = array();
		foreach ( $urls as $url ) {
			if ( isset( $by_url[ $url ] ) ) {
				$output[] = $by_url[ $url ];
			}
		}
		return $output;
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
		if ( '' === $generation || ! $values || ! in_array( $column, array( 'page_unique', 'url_hash' ), true ) ) {
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
				$output[] = $item;
			}
		}
		return $output;
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
