<?php
/**
 * Database-backed logger.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Logger {
	public const TABLE_SUFFIX = 'tves_logs';
	private const RETENTION_DAYS = 30;
	private const MAX_ROWS = 20000;
	private const PRUNE_LOCK_KEY = 'tves_log_prune_lock';

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

		self::maybe_prune();
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
	 * Return a Persian admin-facing version of known log messages.
	 *
	 * Log records remain stored with their stable technical text so integrations and
	 * diagnostics are not affected. Unknown exception messages are returned unchanged.
	 */
	public static function display_message( string $message ): string {
		$messages = array(
			'Torob Product API v3 request completed.' => 'درخواست API نسخه ۳ ترب با موفقیت پاسخ داده شد.',
			'Torob Product API v3 lookup completed with products missing from the active catalog.' => 'درخواست ترب پاسخ داده شد، اما یک یا چند محصول در کاتالوگ فعال پیدا نشد.',
			'The Torob API token is expired or not active yet.' => 'توکن امنیتی ترب منقضی شده یا زمان فعال‌شدن آن هنوز نرسیده است.',
			'The Torob API token audience does not match this shop host.' => 'دامنه ثبت‌شده در توکن ترب با دامنه این فروشگاه مطابقت ندارد.',
			'The Torob API token is invalid.' => 'توکن امنیتی ارسال‌شده از ترب معتبر نیست.',
			'The PHP Sodium extension is required to validate Torob API tokens.' => 'برای اعتبارسنجی توکن ترب، افزونه PHP Sodium باید روی سرور فعال باشد.',
			'Feed synchronization started.' => 'بازسازی کاتالوگ ترب شروع شد.',
			'Feed synchronization completed.' => 'بازسازی کاتالوگ ترب با موفقیت کامل شد.',
			'Simple product synchronized.' => 'محصول ساده به کاتالوگ ترب اضافه شد.',
			'Variation synchronized.' => 'این انتخاب از محصول متغیر به کاتالوگ ترب اضافه شد.',
			'Product exported as unavailable because it has no price.' => 'این محصول قیمت ندارد و به‌صورت ناموجود برای ترب ارسال شد.',
			'Variation exported as unavailable because it has no price.' => 'این انتخاب قیمت ندارد و به‌صورت ناموجود برای ترب ارسال شد.',
			'Product skipped because it has no price.' => 'این محصول به‌دلیل نداشتن قیمت از خروجی کنار گذاشته شد.',
			'Variation skipped because it has no price.' => 'این انتخاب به‌دلیل نداشتن قیمت از خروجی کنار گذاشته شد.',
			'Product has stock management enabled but no stock quantity.' => 'مدیریت موجودی محصول فعال است، اما تعداد موجودی برای آن ثبت نشده است.',
			'Variation has stock management enabled but no stock quantity.' => 'مدیریت موجودی این انتخاب فعال است، اما تعداد موجودی برای آن ثبت نشده است.',
			'A variation ID did not resolve to a valid WooCommerce variation.' => 'شناسه یکی از انتخاب‌های محصول به یک انتخاب معتبر ووکامرس مربوط نیست.',
			'A synchronized item could not be mapped to the Torob API v3 schema.' => 'اطلاعات یکی از محصولات با ساختار موردنیاز API نسخه ۳ ترب سازگار نبود.',
			'A Torob API v3 catalog item could not be saved to the database.' => 'ذخیره یکی از محصولات کاتالوگ ترب در پایگاه‌داده انجام نشد.',
			'The next synchronization batch could not be scheduled. The watchdog will retry it automatically.' => 'زمان‌بندی بخش بعدی پردازش انجام نشد؛ افزونه به‌صورت خودکار دوباره تلاش می‌کند.',
			'A stalled feed synchronization was detected and automatically resumed.' => 'توقف پردازش تشخیص داده شد و افزونه آن را به‌صورت خودکار ادامه داد.',
			'A synchronization batch failed and was scheduled for an automatic retry.' => 'پردازش این بخش ناموفق بود و برای تلاش مجدد خودکار زمان‌بندی شد.',
		);

		return $messages[ $message ] ?? $message;
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

		$boundary_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::MAX_ROWS - 1
			)
		);
		if ( $boundary_id > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE id < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$boundary_id
				)
			);
		}
	}

	/**
	 * Delete every log record and return the affected-row count.
	 *
	 * A negative result means the database operation failed.
	 */
	public static function clear_all(): int {
		global $wpdb;

		$table   = self::table_name();
		$deleted = $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

		return false === $deleted ? -1 : (int) $deleted;
	}

	/**
	 * Run age-based cleanup at most once per day, even without a completed sync.
	 */
	private static function maybe_prune(): void {
		if ( get_transient( self::PRUNE_LOCK_KEY ) ) {
			return;
		}

		set_transient( self::PRUNE_LOCK_KEY, 1, DAY_IN_SECONDS );
		self::prune( self::RETENTION_DAYS );
	}

	/**
	 * Get the fully prefixed table name.
	 */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}
}
