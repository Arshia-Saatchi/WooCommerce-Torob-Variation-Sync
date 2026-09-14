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
		add_action( 'wp_ajax_tves_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_tves_load_dashboard_tab', array( $this, 'ajax_load_dashboard_tab' ) );
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
			''          => __( 'همه وضعیت‌ها', 'torob-variable-exporter' ),
			'success'   => __( 'موفق', 'torob-variable-exporter' ),
			'warning'   => __( 'هشدار', 'torob-variable-exporter' ),
			'error'     => __( 'خطا', 'torob-variable-exporter' ),
			'api_error' => __( 'خطای ارتباط API', 'torob-variable-exporter' ),
			'invalid'   => __( 'محصول نامعتبر', 'torob-variable-exporter' ),
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
		add_menu_page(
			__( 'رسا | مدیریت اتصال ترب', 'torob-variable-exporter' ),
			__( 'رسا', 'torob-variable-exporter' ),
			'manage_woocommerce',
			'tves-settings',
			array( $this, 'render_settings_page' ),
			add_query_arg( 'ver', TVES_VERSION, TVES_URL . 'assets/images/resa-icon.png' ),
			58
		);

		// Keep the old URL working without adding a second sidebar item.
		add_submenu_page(
			null,
			__( 'گزارش‌های رسا', 'torob-variable-exporter' ),
			__( 'گزارش‌های رسا', 'torob-variable-exporter' ),
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
		$current        = (array) get_option( 'tves_settings', array() );
		$section        = sanitize_key( $input['settings_section'] ?? '' );
		$title_formats  = array( 'parent_attributes', 'parent', 'custom' );
		$sync_intervals = array( 'manual', 'hourly', 'six_hours', 'daily' );
		$sanitized_output = array(
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
		if ( 'output' === $section ) {
			$output = array_merge( $current, array_intersect_key( $sanitized_output, array_flip( array( 'enabled', 'title_format', 'title_template', 'title_attributes', 'export_attributes', 'sync_interval', 'api_token', 'v3_enabled' ) ) ) );
		} elseif ( 'exclusions' === $section ) {
			$output = array_merge( $current, array_intersect_key( $sanitized_output, array_flip( array( 'excluded_products', 'excluded_variations', 'excluded_categories' ) ) ) );
		} else {
			$output = $sanitized_output;
		}
		$output['sync_interval'] = $output['sync_interval'] ?? 'manual';

		TVES_Sync_Manager::reschedule( $output['sync_interval'] );
		TVES_Feed_Generator::bump_cache_generation();
		return $output;
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'torob-variable-exporter' ) );
		}

		$active_tab = $this->get_dashboard_tab( sanitize_key( wp_unslash( $_GET['tab'] ?? 'overview' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$context    = $this->get_dashboard_context( $active_tab );
		extract( $context, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include TVES_PATH . 'admin/settings-page.php';
	}

	public function render_logs_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'torob-variable-exporter' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tves-settings&tab=logs' ) );
		exit;
	}

	/** @return array<string, mixed> */
	private function get_dashboard_context( string $tab ): array {
		$context = array( 'active_tab' => $tab );
		if ( in_array( $tab, array( 'overview', 'output', 'exclusions' ), true ) ) {
			$context['settings'] = (array) get_option( 'tves_settings', array() );
		}
		if ( 'overview' === $tab ) {
			$context += array(
				'status'          => TVES_Sync_Manager::get_status(),
				'feed_url'        => rest_url( 'torob/v1/products' ),
				'v3_feed_url'     => rest_url( 'torob/v3/products' ),
				'v3_stats'        => TVES_Torob_V3_Catalog::get_stats(),
				'v3_audiences'    => TVES_Torob_JWT_Validator::accepted_audiences(),
				'v3_last_access'  => (int) get_option( 'tves_v3_last_access', 0 ),
				'v3_crypto_ready' => function_exists( 'sodium_crypto_sign_verify_detached' ),
			);
		} elseif ( 'output' === $tab ) {
			$context['attributes'] = self::get_detected_attributes();
		} elseif ( 'exclusions' === $tab ) {
			$context['categories'] = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		} elseif ( 'logs' === $tab ) {
			$page       = max( 1, absint( $_REQUEST['paged'] ?? 1 ) );
			$statuses   = self::get_log_statuses();
			$status     = sanitize_key( wp_unslash( $_REQUEST['status'] ?? '' ) );
			$status     = array_key_exists( $status, $statuses ) ? $status : '';
			$log_result = TVES_Logger::get_logs( $page, 30, $status );
			$total_pages = max( 1, (int) ceil( $log_result['total'] / 30 ) );
			if ( $page > $total_pages ) {
				$page       = $total_pages;
				$log_result = TVES_Logger::get_logs( $page, 30, $status );
			}
			$context += compact( 'page', 'status', 'statuses', 'log_result', 'total_pages' );
			$context['counts'] = TVES_Logger::get_status_counts();
		}
		return $context;
	}

	private function get_dashboard_tab( string $tab ): string {
		return in_array( $tab, array( 'overview', 'output', 'exclusions', 'logs' ), true ) ? $tab : 'overview';
	}

	private function render_dashboard_tab( string $tab ): string {
		$context = $this->get_dashboard_context( $tab );
		extract( $context, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		ob_start();
		include TVES_PATH . 'admin/tabs/' . $tab . '.php';
		return (string) ob_get_clean();
	}

	public function ajax_load_dashboard_tab(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'شما اجازه انجام این عملیات را ندارید.', 'torob-variable-exporter' ) ), 403 );
		}
		check_ajax_referer( 'tves_load_dashboard_tab', 'nonce' );
		$tab = $this->get_dashboard_tab( sanitize_key( wp_unslash( $_POST['tab'] ?? 'overview' ) ) );
		wp_send_json_success( array( 'tab' => $tab, 'html' => $this->render_dashboard_tab( $tab ) ) );
	}

	/**
	 * Start a fresh manual sync after capability and nonce checks.
	 */
	public function handle_manual_sync(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما اجازه بازسازی کاتالوگ ترب را ندارید.', 'torob-variable-exporter' ) );
		}
		check_admin_referer( 'tves_manual_sync' );
		$result = $this->sync_manager->start_sync( true );
		$notice = is_wp_error( $result ) ? 'sync-error' : 'sync-started';
		wp_safe_redirect( add_query_arg( 'tves_notice', $notice, admin_url( 'admin.php?page=tves-settings&tab=overview' ) ) );
		exit;
	}

	/**
	 * Download all matching log records as a UTF-8 CSV or tab-separated TXT file.
	 */
	public function handle_export_logs(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما اجازه دریافت فایل گزارش ترب را ندارید.', 'torob-variable-exporter' ) );
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
			wp_die( esc_html__( 'ساخت فایل گزارش انجام نشد. دوباره تلاش کنید.', 'torob-variable-exporter' ) );
		}

		$columns       = array( 'تاریخ و ساعت', 'شناسه محصول', 'نام محصول', 'شناسه انتخاب', 'وضعیت', 'شرح رویداد', 'جزئیات فنی' );
		$status_labels = self::get_log_statuses();
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
					(string) ( $status_labels[ $log->status ] ?? $log->status ),
					TVES_Logger::display_message( (string) $log->message ),
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
			wp_send_json_error( array( 'message' => __( 'شما اجازه انجام این عملیات را ندارید.', 'torob-variable-exporter' ) ), 403 );
		}
		check_ajax_referer( 'tves_sync_status', 'nonce' );
		$this->sync_manager->maybe_process_due_batch();

		$status = TVES_Sync_Manager::get_status();
		$v3_stats = TVES_Torob_V3_Catalog::get_stats();
		$v3_last_access = (int) get_option( 'tves_v3_last_access', 0 );
		wp_send_json_success(
			array(
				'running'        => $status['running'],
				'status_label'   => $status['running'] ? __( 'در حال پردازش', 'torob-variable-exporter' ) : __( 'آماده', 'torob-variable-exporter' ),
				'last_sync'      => $status['last'] ? wp_date( 'Y-m-d H:i', $status['last'] ) : __( 'هنوز انجام نشده', 'torob-variable-exporter' ),
				'next_sync'      => $status['next'] ? wp_date( 'Y-m-d H:i', $status['next'] ) : __( 'فقط اجرای دستی', 'torob-variable-exporter' ),
				'last_activity'  => $status['last_activity'] ? wp_date( 'Y-m-d H:i:s', $status['last_activity'] ) : __( 'هنوز فعالیتی ثبت نشده', 'torob-variable-exporter' ),
				'processed'      => $status['processed'],
				'total'          => $status['total'],
				'exported_items' => $status['exported_items'],
				'percent'        => $status['percent'],
				'recovery_count' => $status['recovery_count'],
				'total_retries'  => $status['total_retries'],
				'progress_label' => sprintf(
					/* translators: 1: processed source products, 2: total source products. */
					__( '%1$d محصول از مجموع %2$d محصول بررسی شده است', 'torob-variable-exporter' ),
					$status['processed'],
					$status['total']
				),
				'v3_catalog'     => $v3_stats['ready']
					? sprintf( /* translators: %d: item count. */ __( '%d آیتم آماده ارسال است', 'torob-variable-exporter' ), $v3_stats['total'] )
					: __( 'هنوز ساخته نشده', 'torob-variable-exporter' ),
				'v3_last_access' => $v3_last_access ? wp_date( 'Y-m-d H:i:s', $v3_last_access ) : __( 'هنوز درخواستی ثبت نشده', 'torob-variable-exporter' ),
			)
		);
	}

	/**
	 * Load a filtered, paginated log result fragment without reloading the screen.
	 */
	public function ajax_load_logs(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'شما اجازه انجام این عملیات را ندارید.', 'torob-variable-exporter' ) ), 403 );
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
	 * Permanently clear the Torob log table and return a fresh AJAX view.
	 */
	public function ajax_clear_logs(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'شما اجازه انجام این عملیات را ندارید.', 'torob-variable-exporter' ) ), 403 );
		}
		check_ajax_referer( 'tves_clear_logs', 'nonce' );

		$statuses = self::get_log_statuses();
		$status   = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$status   = array_key_exists( $status, $statuses ) ? $status : '';
		$deleted  = TVES_Logger::clear_all();
		if ( $deleted < 0 ) {
			wp_send_json_error( array( 'message' => __( 'پاک‌کردن گزارش‌های ترب انجام نشد. دوباره تلاش کنید.', 'torob-variable-exporter' ) ), 500 );
		}

		$page       = 1;
		$log_result = TVES_Logger::get_logs( $page, 30, $status );
		ob_start();
		include TVES_PATH . 'admin/logs-results.php';
		$html = (string) ob_get_clean();

		wp_send_json_success(
			array(
				'html'    => $html,
				'status'  => $status,
				'paged'   => $page,
				'total'   => (int) $log_result['total'],
				'counts'  => TVES_Logger::get_status_counts(),
				'deleted' => $deleted,
				'message' => sprintf(
					/* translators: %d: number of deleted log records. */
					__( '%d رویداد از گزارش‌های ترب پاک شد.', 'torob-variable-exporter' ),
					$deleted
				),
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
		wp_add_inline_style(
			'common',
			'#toplevel_page_tves-settings .wp-menu-image img{background:#fff;border-radius:5px;box-sizing:border-box;height:20px!important;margin-top:6px;object-fit:contain;opacity:1;padding:2px!important;width:20px!important}'
		);
		if ( ! in_array( $hook_suffix, array( 'toplevel_page_tves-settings', 'admin_page_tves-logs' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'tves-admin', TVES_URL . 'assets/css/admin.css', array(), TVES_VERSION );
		wp_enqueue_script( 'tves-admin', TVES_URL . 'assets/js/admin.js', array( 'jquery' ), TVES_VERSION, true );
		wp_localize_script(
			'tves-admin',
			'tvesAdmin',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'syncNonce'        => wp_create_nonce( 'tves_sync_status' ),
				'logsNonce'        => wp_create_nonce( 'tves_load_logs' ),
				'clearLogsNonce'   => wp_create_nonce( 'tves_clear_logs' ),
				'dashboardNonce'   => wp_create_nonce( 'tves_load_dashboard_tab' ),
				'pollInterval'     => 3000,
				'syncLive'         => __( 'در حال پردازش', 'torob-variable-exporter' ),
				'syncReady'        => __( 'آماده', 'torob-variable-exporter' ),
				'confirmSync'      => __( 'بازسازی کامل کاتالوگ ترب شروع شود؟ این عملیات ممکن است چند دقیقه زمان ببرد.', 'torob-variable-exporter' ),
				'progressError'    => __( 'نمایش زنده پیشرفت موقتاً در دسترس نیست؛ پردازش در پس‌زمینه ادامه پیدا می‌کند.', 'torob-variable-exporter' ),
				'loadingLogs'      => __( 'در حال دریافت گزارش‌ها…', 'torob-variable-exporter' ),
				'logsError'        => __( 'تازه‌سازی گزارش‌ها انجام نشد؛ جدول زیر آخرین اطلاعات بارگذاری‌شده است. دوباره تلاش کنید.', 'torob-variable-exporter' ),
				'clearLogsError'   => __( 'پاک‌کردن گزارش‌های ترب انجام نشد. دوباره تلاش کنید.', 'torob-variable-exporter' ),
				'confirmClearLogs' => __( 'همه گزارش‌های ترب برای همیشه پاک شوند؟ اگر به نسخه پشتیبان نیاز دارید، ابتدا فایل گزارش را دریافت کنید.', 'torob-variable-exporter' ),
				'removeExclusion'  => __( 'حذف', 'torob-variable-exporter' ),
				'loadingTab'       => __( 'در حال آماده‌سازی این بخش…', 'torob-variable-exporter' ),
				'tabError'         => __( 'بارگذاری این بخش انجام نشد. دوباره تلاش کنید.', 'torob-variable-exporter' ),
			)
		);

		if ( 'toplevel_page_tves-settings' === $hook_suffix ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}

	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=tves-settings' ) ) . '">' . esc_html__( 'تنظیمات', 'torob-variable-exporter' ) . '</a>' );
		return $links;
	}
}
