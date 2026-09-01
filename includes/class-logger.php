<?php
/**
 * Database-backed logger.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Logger {
	public const TABLE_SUFFIX = 'tves_logs';

	/**
	 * Create or update the log table.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL,
			message text NOT NULL,
			context longtext NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status),
			KEY product_id (product_id),
			KEY variation_id (variation_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Write one structured log record.
	 *
	 * @param string $status       success, warning, error, api_error, or invalid.
	 * @param string $message      Human-readable message.
	 * @param int    $product_id   Parent/simple product ID.
	 * @param int    $variation_id Variation ID.
	 * @param array  $context      Optional structured diagnostic context.
	 */
	public static function log( string $status, string $message, int $product_id = 0, int $variation_id = 0, array $context = array() ): void {
		global $wpdb;

		$allowed_statuses = array( 'success', 'warning', 'error', 'api_error', 'invalid' );
		$status           = in_array( $status, $allowed_statuses, true ) ? $status : 'error';

		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'   => current_time( 'mysql', true ),
				'product_id'   => absint( $product_id ),
				'variation_id' => absint( $variation_id ),
				'status'       => $status,
				'message'      => sanitize_textarea_field( $message ),
				'context'      => $context ? wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : null,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Retrieve a paginated log list.
	 *
	 * @return array{items: array, total: int}
	 */
	public static function get_logs( int $page = 1, int $per_page = 30, string $status = '' ): array {
		global $wpdb;

		$page      = max( 1, $page );
		$per_page  = min( 100, max( 1, $per_page ) );
		$offset    = ( $page - 1 ) * $per_page;
		$table     = self::table_name();
		$where_sql = '';
		$params    = array();

		if ( in_array( $status, array( 'success', 'warning', 'error', 'api_error', 'invalid' ), true ) ) {
			$where_sql = ' WHERE status = %s';
			$params[]  = $status;
		}

		$count_sql = "SELECT COUNT(*) FROM {$table}{$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$list_sql  = "SELECT * FROM {$table}{$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$list_args = array_merge( $params, array( $per_page, $offset ) );

		$total = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'total' => $total,
		);
	}

	/**
	 * Get totals for the log overview cards in one query.
	 *
	 * @return array<string, int>
	 */
	public static function get_status_counts(): array {
		global $wpdb;

		$counts = array(
			'all'       => 0,
			'success'   => 0,
			'warning'   => 0,
			'error'     => 0,
			'api_error' => 0,
			'invalid'   => 0,
		);
		$table  = self::table_name();
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

		foreach ( (array) $rows as $row ) {
			$key = sanitize_key( $row->status );
			if ( array_key_exists( $key, $counts ) ) {
				$counts[ $key ] = (int) $row->total;
				$counts['all'] += (int) $row->total;
			}
		}

		return $counts;
	}

	/**
	 * Remove old logs while retaining recent diagnostics.
	 */
	public static function prune( int $days = 30 ): void {
		global $wpdb;

		$days  = min( 365, max( 1, $days ) );
		$table = self::table_name();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$days
			)
		);
	}

	/**
	 * Get the fully prefixed table name.
	 */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}
}
