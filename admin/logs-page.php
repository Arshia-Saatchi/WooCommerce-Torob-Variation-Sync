<?php
/**
 * Log viewer template.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

$summary_cards = array(
	'success'   => array( 'count' => $counts['success'], 'label' => __( 'موفق', 'torob-variable-exporter' ), 'tone' => 'success' ),
	'warning'   => array( 'count' => $counts['warning'], 'label' => __( 'هشدار', 'torob-variable-exporter' ), 'tone' => 'warning' ),
	'error'     => array( 'count' => $counts['error'], 'label' => __( 'خطا', 'torob-variable-exporter' ), 'tone' => 'error' ),
	'api_error' => array( 'count' => $counts['api_error'], 'label' => __( 'خطای ارتباط API', 'torob-variable-exporter' ), 'tone' => 'error' ),
	'invalid'   => array( 'count' => $counts['invalid'], 'label' => __( 'محصول نامعتبر', 'torob-variable-exporter' ), 'tone' => 'error' ),
);
$all_logs_url = add_query_arg( array( 'page' => 'tves-logs' ), admin_url( 'admin.php' ) );
?>
<div class="wrap tves-wrap tves-logs-screen">
	<header class="tves-page-header">
		<div class="tves-brand-lockup">
			<span class="tves-brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
			<div><h1><?php esc_html_e( 'گزارش فعالیت ترب', 'torob-variable-exporter' ); ?></h1><p><?php esc_html_e( 'رویدادهای کاتالوگ را ببینید، خطاهای محصولات را بررسی کنید و گزارش قابل ارسال دریافت کنید.', 'torob-variable-exporter' ); ?></p></div>
		</div>
		<div class="tves-header-actions"><a class="button tves-button-quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=tves-settings' ) ); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><?php esc_html_e( 'بازگشت به تنظیمات', 'torob-variable-exporter' ); ?></a><span class="tves-version-badge"><span><?php echo esc_html( 'v' . TVES_VERSION ); ?></span><?php esc_html_e( 'پایدار', 'torob-variable-exporter' ); ?></span></div>
	</header>

	<nav class="tves-log-summary" aria-label="<?php esc_attr_e( 'خلاصه وضعیت گزارش‌ها', 'torob-variable-exporter' ); ?>">
		<a class="tves-log-total tves-log-stat <?php echo '' === $status ? 'is-active' : ''; ?>" href="<?php echo esc_url( $all_logs_url ); ?>" data-status=""><span><?php esc_html_e( 'همه رویدادهای ثبت‌شده', 'torob-variable-exporter' ); ?></span><strong data-log-count="all"><?php echo esc_html( number_format_i18n( $counts['all'] ) ); ?></strong><small><?php esc_html_e( '۳۰ روز اخیر؛ حداکثر ۲۰٬۰۰۰ رویداد نگه‌داری می‌شود', 'torob-variable-exporter' ); ?></small></a>
		<div class="tves-log-breakdown">
			<?php foreach ( $summary_cards as $card_status => $card ) : $card_url = add_query_arg( array( 'page' => 'tves-logs', 'status' => $card_status ), admin_url( 'admin.php' ) ); ?>
				<a class="tves-log-stat tves-log-stat-<?php echo esc_attr( $card['tone'] ); ?> <?php echo $status === $card_status ? 'is-active' : ''; ?>" href="<?php echo esc_url( $card_url ); ?>" data-status="<?php echo esc_attr( $card_status ); ?>"><i aria-hidden="true"></i><span><?php echo esc_html( $card['label'] ); ?></span><strong data-log-count="<?php echo esc_attr( $card_status ); ?>"><?php echo esc_html( number_format_i18n( $card['count'] ) ); ?></strong></a>
			<?php endforeach; ?>
		</div>
	</nav>

	<div class="tves-log-toolbar">
		<div class="tves-toolbar-section" aria-label="<?php esc_attr_e( 'فیلتر و تازه‌سازی گزارش‌ها', 'torob-variable-exporter' ); ?>">
			<span class="tves-toolbar-kicker"><?php esc_html_e( 'نمایش گزارش‌ها', 'torob-variable-exporter' ); ?></span>
			<form method="get" class="tves-log-filter tves-toolbar-group"><input type="hidden" name="page" value="tves-logs"><label for="tves-status-filter" class="screen-reader-text"><?php esc_html_e( 'نوع رویداد', 'torob-variable-exporter' ); ?></label><select id="tves-status-filter" name="status"><?php foreach ( $statuses as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><?php submit_button( __( 'اعمال فیلتر', 'torob-variable-exporter' ), 'secondary', 'filter_action', false ); ?></form>
			<button type="button" class="button tves-refresh-logs" id="tves-refresh-logs"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'تازه‌سازی', 'torob-variable-exporter' ); ?></button>
		</div>
		<div class="tves-toolbar-section tves-toolbar-section-secondary" aria-label="<?php esc_attr_e( 'دریافت یا پاک‌سازی گزارش‌ها', 'torob-variable-exporter' ); ?>">
			<span class="tves-toolbar-kicker"><?php esc_html_e( 'فایل گزارش', 'torob-variable-exporter' ); ?></span>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tves-log-export tves-toolbar-group"><input type="hidden" name="action" value="tves_export_logs"><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php wp_nonce_field( 'tves_export_logs' ); ?><label for="tves-export-format" class="screen-reader-text"><?php esc_html_e( 'نوع فایل گزارش', 'torob-variable-exporter' ); ?></label><select id="tves-export-format" name="format"><option value="csv"><?php esc_html_e( 'فایل CSV برای اکسل', 'torob-variable-exporter' ); ?></option><option value="txt"><?php esc_html_e( 'فایل متنی TXT', 'torob-variable-exporter' ); ?></option></select><?php submit_button( __( 'دریافت فایل', 'torob-variable-exporter' ), 'primary', 'download_logs', false ); ?></form>
			<button type="button" class="button tves-clear-logs" id="tves-clear-logs"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'پاک‌کردن گزارش‌ها', 'torob-variable-exporter' ); ?></button>
		</div>
		<p class="tves-toolbar-help"><?php esc_html_e( 'فایل دریافتی بر اساس فیلتر فعلی ساخته می‌شود و همه رویدادهای مطابق آن فیلتر را شامل می‌شود، نه فقط ردیف‌های همین صفحه.', 'torob-variable-exporter' ); ?></p>
	</div>
	<div class="tves-ajax-notice" id="tves-log-notice" role="status" hidden></div>

	<div class="tves-log-results" id="tves-log-results" data-status="<?php echo esc_attr( $status ); ?>" data-paged="<?php echo esc_attr( $page ); ?>" aria-live="polite" aria-busy="false">
		<div class="tves-loading-state" role="status"><span class="tves-loader" aria-hidden="true"><i></i><i></i><i></i></span><strong><?php esc_html_e( 'در حال دریافت گزارش‌ها…', 'torob-variable-exporter' ); ?></strong></div>
		<div class="tves-ajax-error" id="tves-log-error" role="alert" hidden></div>
		<div id="tves-log-results-content"><?php include TVES_PATH . 'admin/logs-results.php'; ?></div>
	</div>
</div>
