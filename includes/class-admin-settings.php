<?php
/**
 * WooCommerce admin settings and log screens.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Admin_Settings {
	private TVES_Sync_Manager $sync_manager;

	/** @var array<string, string> */
	private static array $pending_attributes = array();

	private static bool $attribute_shutdown_registered = false;

	public function __construct( TVES_Sync_Manager $sync_manager ) {
		$this->sync_manager = $sync_manager;
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ), 60 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_tves_manual_sync', array( $this, 'handle_manual_sync' ) );
		add_action( 'admin_post_tves_export_logs', array( $this, 'handle_export_logs' ) );
		add_action( 'wp_ajax_tves_sync_status', array( $this, 'ajax_sync_status' ) );
		add_action( 'wp_ajax_tves_load_logs', array( $this, 'ajax_load_logs' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TVES_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Read one setting with a safe fallback.
	 *
	 * @return mixed
	 */
	public static function get_setting( string $key, $default = null ) {
		$settings = (array) get_option( 'tves_settings', array() );
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Get the supported log filters and their labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_log_statuses(): array {
		return array(
			''          => __( 'All statuses', 'torob-variable-exporter' ),
			'success'   => __( 'Success', 'torob-variable-exporter' ),
			'warning'   => __( 'Warning', 'torob-variable-exporter' ),
			'error'     => __( 'Error', 'torob-variable-exporter' ),
			'api_error' => __( 'API error', 'torob-variable-exporter' ),
			'invalid'   => __( 'Invalid', 'torob-variable-exporter' ),
		);
	}

	/**
	 * Queue a discovered local/global attribute for one write at shutdown.
	 */
	public static function remember_attribute( string $slug, string $label ): void {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return;
		}
		self::$pending_attributes[ $slug ] = sanitize_text_field( $label ?: $slug );

		if ( ! self::$attribute_shutdown_registered ) {
			self::$attribute_shutdown_registered = true;
			register_shutdown_function( array( __CLASS__, 'persist_discovered_attributes' ) );
		}
	}

	/**
	 * Persist discovered attributes in a single non-autoloaded option update.
	 */
	public static function persist_discovered_attributes(): void {
		if ( ! self::$pending_attributes ) {
			return;
		}
		$existing = (array) get_option( 'tves_detected_attributes', array() );
		$merged   = array_merge( $existing, self::$pending_attributes );
		asort( $merged, SORT_NATURAL | SORT_FLAG_CASE );
		update_option( 'tves_detected_attributes', $merged, false );
	}

	/**
	 * Return registered WooCommerce attributes plus attributes seen during sync.
	 *
	 * @return array<string, string>
	 */
	public static function get_detected_attributes(): array {
		$attributes = (array) get_option( 'tves_detected_attributes', array() );
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$slug                = sanitize_title( wc_attribute_taxonomy_name( $attribute->attribute_name ) );
			$attributes[ $slug ] = (string) $attribute->attribute_label;
		}
		asort( $attributes, SORT_NATURAL | SORT_FLAG_CASE );
		return $attributes;
	}

	public function add_menu_pages(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Torob Variable Sync', 'torob-variable-exporter' ),
			__( 'Torob Variable Sync', 'torob-variable-exporter' ),
			'manage_woocommerce',
			'tves-settings',
			array( $this, 'render_settings_page' )
		);
		add_submenu_page(
			'woocommerce',
			__( 'Torob Logs', 'torob-variable-exporter' ),
			__( 'Torob Logs', 'torob-variable-exporter' ),
			'manage_woocommerce',
			'tves-logs',
			array( $this, 'render_logs_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'tves_settings_group',
			'tves_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize every setting and apply scheduling changes.
	 */
	public function sanitize_settings( $input ): array {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return (array) get_option( 'tves_settings', array() );
		}

		$input          = is_array( $input ) ? $input : array();
		$title_formats  = array( 'parent_attributes', 'parent', 'custom' );
		$sync_intervals = array( 'manual', 'hourly', 'six_hours', 'daily' );
		$output         = array(
			'enabled'              => ! empty( $input['enabled'] ) ? 'yes' : 'no',
			'title_format'         => in_array( $input['title_format'] ?? '', $title_formats, true ) ? $input['title_format'] : 'parent_attributes',
			'title_template'       => sanitize_text_field( $input['title_template'] ?? '{parent} - {attributes}' ),
			'title_attributes'     => array_values( array_unique( array_map( 'sanitize_title', (array) ( $input['title_attributes'] ?? array() ) ) ) ),
			'export_attributes'    => array_values( array_unique( array_map( 'sanitize_title', (array) ( $input['export_attributes'] ?? array() ) ) ) ),
			'sync_interval'        => in_array( $input['sync_interval'] ?? '', $sync_intervals, true ) ? $input['sync_interval'] : 'manual',
			'excluded_products'    => array_values( array_filter( array_unique( array_map( 'absint', (array) ( $input['excluded_products'] ?? array() ) ) ) ) ),
			'excluded_variations'  => array_values( array_filter( array_unique( array_map( 'absint', (array) ( $input['excluded_variations'] ?? array() ) ) ) ) ),
			'excluded_categories'  => array_values( array_filter( array_unique( array_map( 'absint', (array) ( $input['excluded_categories'] ?? array() ) ) ) ) ),
			'api_token'            => sanitize_text_field( $input['api_token'] ?? '' ),
			'v3_enabled'           => ! empty( $input['v3_enabled'] ) ? 'yes' : 'no',
		);

		TVES_Sync_Manager::reschedule( $output['sync_interval'] );
		TVES_Feed_Generator::bump_cache_generation();
		return $output;
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'torob-variable-exporter' ) );
		}

		$settings   = (array) get_option( 'tves_settings', array() );
		$attributes = self::get_detected_attributes();
		$status     = TVES_Sync_Manager::get_status();
		$feed_url   = rest_url( 'torob/v1/products' );
		$v3_feed_url = rest_url( 'torob/v3/products' );
		$v3_stats    = TVES_Torob_V3_Catalog::get_stats();
		$v3_audiences = TVES_Torob_JWT_Validator::accepted_audiences();
		$v3_last_access = (int) get_option( 'tves_v3_last_access', 0 );
		$v3_crypto_ready = function_exists( 'sodium_crypto_sign_verify_detached' );
		$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		include TVES_PATH . 'admin/settings-page.php';
	}

	public function render_logs_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'torob-variable-exporter' ) );
		}

		$page       = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status     = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$statuses   = self::get_log_statuses();
		$status     = array_key_exists( $status, $statuses ) ? $status : '';
		$log_result = TVES_Logger::get_logs( $page, 30, $status );
		$total_pages = max( 1, (int) ceil( $log_result['total'] / 30 ) );
		if ( $page > $total_pages ) {
			$page       = $total_pages;
			$log_result = TVES_Logger::get_logs( $page, 30, $status );
		}
		$counts     = TVES_Logger::get_status_counts();
		include TVES_PATH . 'admin/logs-page.php';
	}

	/**
	 * Start a fresh manual sync after capability and nonce checks.
	 */
	public function handle_manual_sync(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to synchronize the feed.', 'torob-variable-exporter' ) );
		}
		check_admin_referer( 'tves_manual_sync' );
		$result = $this->sync_manager->start_sync( true );
		$notice = is_wp_error( $result ) ? 'sync-error' : 'sync-started';
		wp_safe_redirect( add_query_arg( 'tves_notice', $notice, admin_url( 'admin.php?page=tves-settings' ) ) );
		exit;
	}

	/**
	 * Download all matching log records as a UTF-8 CSV or tab-separated TXT file.
	 */
	public function handle_export_logs(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export Torob logs.', 'torob-variable-exporter' ) );
		}
		check_admin_referer( 'tves_export_logs' );

		$format = sanitize_key( wp_unslash( $_POST['format'] ?? 'csv' ) );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$format = in_array( $format, array( 'csv', 'txt' ), true ) ? $format : 'csv';
		$status = in_array( $status, array( 'success', 'warning', 'error', 'api_error', 'invalid' ), true ) ? $status : '';
		$name   = 'torob-logs-' . gmdate( 'Y-m-d-His' ) . '.' . $format;

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . ( 'csv' === $format ? 'text/csv' : 'text/plain' ) . '; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$output = fopen( 'php://output', 'wb' );
		if ( false === $output ) {
			wp_die( esc_html__( 'The export stream could not be opened.', 'torob-variable-exporter' ) );
		}

		$columns = array( 'Date', 'Product ID', 'Product', 'Variation ID', 'Status', 'Message', 'Context' );
		if ( 'csv' === $format ) {
			fwrite( $output, "\xEF\xBB\xBF" );
			fputcsv( $output, $columns, ',', '"', '' );
		} else {
			fwrite( $output, "\xEF\xBB\xBF" . implode( "\t", $columns ) . "\r\n" );
		}

		$page          = 1;
		$product_names = array();
		do {
			$logs = TVES_Logger::get_logs( $page, 100, $status );
			foreach ( $logs['items'] as $log ) {
				$product_id = (int) $log->product_id;
				if ( $product_id && ! array_key_exists( $product_id, $product_names ) ) {
					$product                      = wc_get_product( $product_id );
					$product_names[ $product_id ] = $product ? $product->get_name() : '';
				}
				$row     = array(
					get_date_from_gmt( $log->created_at, 'Y-m-d H:i:s' ),
					(string) $log->product_id,
					$product_names[ $product_id ] ?? '',
					(string) $log->variation_id,
					(string) $log->status,
					(string) $log->message,
					(string) $log->context,
				);
				$row = array_map( array( __CLASS__, 'sanitize_export_cell' ), $row );
				if ( 'csv' === $format ) {
					fputcsv( $output, $row, ',', '"', '' );
				} else {
					fwrite( $output, implode( "\t", $row ) . "\r\n" );
				}
			}
			++$page;
		} while ( ( $page - 1 ) * 100 < (int) $logs['total'] );

		fclose( $output );
		exit;
	}

	/**
	 * Return current sync progress to the authenticated admin screen.
	 */
	public function ajax_sync_status(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'torob-variable-exporter' ) ), 403 );
		}
		check_ajax_referer( 'tves_sync_status', 'nonce' );

		$status = TVES_Sync_Manager::get_status();
		$v3_stats = TVES_Torob_V3_Catalog::get_stats();
		$v3_last_access = (int) get_option( 'tves_v3_last_access', 0 );
		wp_send_json_success(
			array(
				'running'        => $status['running'],
				'status_label'   => $status['running'] ? __( 'running', 'torob-variable-exporter' ) : __( 'idle', 'torob-variable-exporter' ),
				'last_sync'      => $status['last'] ? wp_date( 'Y-m-d H:i', $status['last'] ) : __( 'Never', 'torob-variable-exporter' ),
				'next_sync'      => $status['next'] ? wp_date( 'Y-m-d H:i', $status['next'] ) : __( 'Manual only', 'torob-variable-exporter' ),
				'processed'      => $status['processed'],
				'total'          => $status['total'],
				'exported_items' => $status['exported_items'],
				'percent'        => $status['percent'],
				'progress_label' => sprintf(
					/* translators: 1: processed source products, 2: total source products. */
					__( '%1$d of %2$d source products checked', 'torob-variable-exporter' ),
					$status['processed'],
					$status['total']
				),
				'v3_catalog'     => $v3_stats['ready']
					? sprintf( /* translators: %d: item count. */ __( 'ready — %d items', 'torob-variable-exporter' ), $v3_stats['total'] )
					: __( 'not generated yet', 'torob-variable-exporter' ),
				'v3_last_access' => $v3_last_access ? wp_date( 'Y-m-d H:i:s', $v3_last_access ) : __( 'Never', 'torob-variable-exporter' ),
			)
		);
	}

	/**
	 * Load a filtered, paginated log result fragment without reloading the screen.
	 */
	public function ajax_load_logs(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'torob-variable-exporter' ) ), 403 );
		}
		check_ajax_referer( 'tves_load_logs', 'nonce' );

		$statuses = self::get_log_statuses();
		$status   = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$status   = array_key_exists( $status, $statuses ) ? $status : '';
		$page     = max( 1, absint( $_POST['paged'] ?? 1 ) );
		$per_page = 30;

		$log_result  = TVES_Logger::get_logs( $page, $per_page, $status );
		$total_pages = max( 1, (int) ceil( $log_result['total'] / $per_page ) );
		if ( $page > $total_pages ) {
			$page       = $total_pages;
			$log_result = TVES_Logger::get_logs( $page, $per_page, $status );
		}

		ob_start();
		include TVES_PATH . 'admin/logs-results.php';
		$html   = (string) ob_get_clean();
		$counts = TVES_Logger::get_status_counts();

		wp_send_json_success(
			array(
				'html'   => $html,
				'status' => $status,
				'paged'  => $page,
				'total'  => (int) $log_result['total'],
				'counts' => $counts,
			)
		);
	}

	/**
	 * Remove line breaks and neutralize spreadsheet formulas in exported values.
	 */
	public static function sanitize_export_cell( $value ): string {
		$value = str_replace( array( "\r", "\n", "\t" ), ' ', (string) $value );
		if ( preg_match( '/^[=+\-@]/', $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'woocommerce_page_tves-settings', 'woocommerce_page_tves-logs' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'tves-admin', TVES_URL . 'assets/css/admin.css', array(), TVES_VERSION );
		wp_enqueue_script( 'tves-admin', TVES_URL . 'assets/js/admin.js', array( 'jquery' ), TVES_VERSION, true );
		wp_localize_script(
			'tves-admin',
			'tvesAdmin',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'syncNonce'       => wp_create_nonce( 'tves_sync_status' ),
				'logsNonce'       => wp_create_nonce( 'tves_load_logs' ),
				'pollInterval'    => 3000,
				'confirmSync'     => __( 'Start a complete Torob feed regeneration now?', 'torob-variable-exporter' ),
				'progressError'   => __( 'Live progress is temporarily unavailable.', 'torob-variable-exporter' ),
				'loadingLogs'     => __( 'Loading logs…', 'torob-variable-exporter' ),
				'logsError'       => __( 'Logs could not be loaded. Please try again.', 'torob-variable-exporter' ),
			)
		);

		if ( 'woocommerce_page_tves-settings' === $hook_suffix ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}

	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=tves-settings' ) ) . '">' . esc_html__( 'Settings', 'torob-variable-exporter' ) . '</a>' );
		return $links;
	}
}
