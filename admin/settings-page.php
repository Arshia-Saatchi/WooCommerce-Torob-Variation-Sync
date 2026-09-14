<?php
/**
 * Settings screen template.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

$enabled             = (string) ( $settings['enabled'] ?? 'yes' );
$v3_enabled          = (string) ( $settings['v3_enabled'] ?? 'yes' );
$title_format        = (string) ( $settings['title_format'] ?? 'parent_attributes' );
$title_attributes    = (array) ( $settings['title_attributes'] ?? array() );
$export_attributes   = (array) ( $settings['export_attributes'] ?? array() );
$excluded_products   = (array) ( $settings['excluded_products'] ?? array() );
$excluded_variations = (array) ( $settings['excluded_variations'] ?? array() );
$notice              = sanitize_key( wp_unslash( $_GET['tves_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$api_ready           = 'yes' === $v3_enabled && $v3_crypto_ready;
$catalog_ready       = ! empty( $v3_stats['ready'] );
$sync_state          = $status['running'] ? 'running' : 'ready';
?>
<div class="wrap tves-wrap tves-settings-screen">
	<header class="tves-page-header">
		<div class="tves-brand-lockup">
			<span class="tves-brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
			<div>
				<h1><?php esc_html_e( 'مدیریت اتصال ترب', 'torob-variable-exporter' ); ?></h1>
				<p><?php esc_html_e( 'خروجی محصولات ووکامرس، وضعیت ارتباط با ترب و سلامت کاتالوگ را از همین صفحه مدیریت کنید.', 'torob-variable-exporter' ); ?></p>
			</div>
		</div>
		<div class="tves-header-actions">
			<a class="button tves-button-quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=tves-logs' ) ); ?>"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><?php esc_html_e( 'مشاهده گزارش‌ها', 'torob-variable-exporter' ); ?></a>
			<span class="tves-version-badge"><span><?php echo esc_html( 'v' . TVES_VERSION ); ?></span><?php esc_html_e( 'آزمایشی', 'torob-variable-exporter' ); ?></span>
		</div>
	</header>

	<?php if ( 'sync-started' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'بازسازی کاتالوگ شروع شد. بهتر است این صفحه را باز نگه دارید؛ اگر زمان‌بندی وردپرس در دسترس نباشد، پردازش از طریق همین صفحه ادامه پیدا می‌کند.', 'torob-variable-exporter' ); ?></p></div>
	<?php elseif ( 'sync-error' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'بازسازی کاتالوگ شروع نشد. برای مشاهده علت، صفحه گزارش‌های ترب را بررسی کنید.', 'torob-variable-exporter' ); ?></p></div>
	<?php endif; ?>

	<nav class="tves-section-nav" aria-label="<?php esc_attr_e( 'بخش‌های تنظیمات', 'torob-variable-exporter' ); ?>">
		<a href="#tves-overview" class="is-current"><?php esc_html_e( 'نمای کلی', 'torob-variable-exporter' ); ?></a>
		<a href="#tves-output"><?php esc_html_e( 'تنظیم خروجی', 'torob-variable-exporter' ); ?></a>
		<a href="#tves-exclusions"><?php esc_html_e( 'حذف از ترب', 'torob-variable-exporter' ); ?></a>
		<a href="#tves-sync-now"><?php esc_html_e( 'بازسازی کاتالوگ', 'torob-variable-exporter' ); ?></a>
	</nav>

	<section class="tves-overview" id="tves-overview">
		<div class="tves-signal-rail" aria-label="<?php esc_attr_e( 'مسیر ارتباط فروشگاه با ترب', 'torob-variable-exporter' ); ?>">
			<div class="tves-signal <?php echo $api_ready ? 'is-ready' : 'is-alert'; ?>">
				<span class="tves-signal-dot" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'ارتباط امن API', 'torob-variable-exporter' ); ?></strong><small><?php echo $api_ready ? esc_html__( 'اعتبارسنجی توکن آماده است', 'torob-variable-exporter' ) : esc_html__( 'تنظیمات API یا Sodium را بررسی کنید', 'torob-variable-exporter' ); ?></small></span>
			</div>
			<div class="tves-signal <?php echo $catalog_ready ? 'is-ready' : 'is-alert'; ?>">
				<span class="tves-signal-dot" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'کاتالوگ محصولات', 'torob-variable-exporter' ); ?></strong><small id="tves-v3-catalog-status"><?php echo $catalog_ready ? esc_html( sprintf( /* translators: %d: item count. */ __( '%d آیتم آماده ارسال است', 'torob-variable-exporter' ), $v3_stats['total'] ) ) : esc_html__( 'ابتدا کاتالوگ را بازسازی کنید', 'torob-variable-exporter' ); ?></small></span>
			</div>
			<div class="tves-signal is-<?php echo esc_attr( $sync_state ); ?>" id="tves-sync-signal">
				<span class="tves-signal-dot" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'همگام‌سازی', 'torob-variable-exporter' ); ?></strong><small id="tves-sync-status"><?php echo $status['running'] ? esc_html__( 'در حال پردازش', 'torob-variable-exporter' ) : esc_html__( 'آماده', 'torob-variable-exporter' ); ?></small></span>
			</div>
		</div>

		<div class="tves-overview-grid">
			<article class="tves-api-console">
				<div class="tves-console-heading">
					<div><h2><?php esc_html_e( 'درگاه رسمی محصولات ترب (API نسخه ۳)', 'torob-variable-exporter' ); ?></h2><p><?php esc_html_e( 'این همان آدرسی است که باید در اختیار پشتیبانی ترب قرار بگیرد.', 'torob-variable-exporter' ); ?></p></div>
					<span class="tves-state-pill <?php echo $api_ready ? 'is-positive' : 'is-negative'; ?>"><?php echo $api_ready ? esc_html__( 'امن و فعال', 'torob-variable-exporter' ) : esc_html__( 'نیازمند بررسی', 'torob-variable-exporter' ); ?></span>
				</div>
				<div class="tves-endpoint" dir="ltr">
					<code id="tves-v3-endpoint"><?php echo esc_html( $v3_feed_url ); ?></code>
					<button type="button" class="button tves-copy-button" data-copy-target="tves-v3-endpoint" data-label="<?php esc_attr_e( 'کپی نشانی', 'torob-variable-exporter' ); ?>" data-success="<?php esc_attr_e( 'کپی شد', 'torob-variable-exporter' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span><?php esc_html_e( 'کپی نشانی', 'torob-variable-exporter' ); ?></span></button>
				</div>
				<dl class="tves-fact-list">
					<div><dt><?php esc_html_e( 'دامنه‌های مجاز', 'torob-variable-exporter' ); ?></dt><dd dir="ltr"><?php echo esc_html( implode( ' / ', $v3_audiences ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'اعتبارسنجی امنیتی', 'torob-variable-exporter' ); ?></dt><dd><?php echo $v3_crypto_ready ? esc_html__( 'آماده؛ افزونه PHP Sodium فعال است', 'torob-variable-exporter' ) : esc_html__( 'افزونه PHP Sodium روی سرور فعال نیست', 'torob-variable-exporter' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'آخرین درخواست ترب', 'torob-variable-exporter' ); ?></dt><dd id="tves-v3-last-access"><?php echo $v3_last_access ? esc_html( wp_date( 'Y-m-d H:i:s', $v3_last_access ) ) : esc_html__( 'هنوز درخواستی ثبت نشده', 'torob-variable-exporter' ); ?></dd></div>
				</dl>
				<details class="tves-legacy-endpoint">
					<summary><?php esc_html_e( 'نشانی قدیمی خروجی (نسخه ۱)', 'torob-variable-exporter' ); ?></summary>
					<p><?php esc_html_e( 'این نشانی فقط برای سازگاری با اتصال‌های قدیمی نگه‌داری شده است؛ آن را به‌عنوان API نسخه ۳ برای ترب ارسال نکنید.', 'torob-variable-exporter' ); ?></p>
					<div class="tves-endpoint" dir="ltr"><code id="tves-v1-endpoint"><?php echo esc_html( $feed_url ); ?></code><button type="button" class="button tves-copy-button" data-copy-target="tves-v1-endpoint" data-label="<?php esc_attr_e( 'کپی نشانی', 'torob-variable-exporter' ); ?>" data-success="<?php esc_attr_e( 'کپی شد', 'torob-variable-exporter' ); ?>"><span><?php esc_html_e( 'کپی نشانی', 'torob-variable-exporter' ); ?></span></button></div>
				</details>
			</article>

			<article class="tves-sync-monitor" id="tves-sync-card" data-running="<?php echo $status['running'] ? '1' : '0'; ?>">
				<div class="tves-monitor-heading">
					<div><span><?php esc_html_e( 'وضعیت کاتالوگ', 'torob-variable-exporter' ); ?></span><strong id="tves-exported-count"><?php echo esc_html( number_format_i18n( $status['exported_items'] ) ); ?></strong><small><?php esc_html_e( 'آیتم آماده ارسال به ترب', 'torob-variable-exporter' ); ?></small></div>
					<span class="tves-live-indicator"><i aria-hidden="true"></i><span id="tves-live-label"><?php echo $status['running'] ? esc_html__( 'در حال پردازش', 'torob-variable-exporter' ) : esc_html__( 'آماده', 'torob-variable-exporter' ); ?></span></span>
				</div>
				<div class="tves-progress" role="progressbar" aria-label="<?php esc_attr_e( 'پیشرفت بازسازی کاتالوگ', 'torob-variable-exporter' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $status['percent'] ); ?>"><span id="tves-progress-bar" style="width: <?php echo esc_attr( $status['percent'] ); ?>%"></span></div>
				<p class="tves-progress-copy" id="tves-progress-label" aria-live="polite"><?php echo esc_html( sprintf( /* translators: 1: processed, 2: total. */ __( '%1$d محصول از مجموع %2$d محصول بررسی شده است', 'torob-variable-exporter' ), $status['processed'], $status['total'] ) ); ?></p>
				<dl class="tves-monitor-facts">
					<div><dt><?php esc_html_e( 'آخرین تکمیل موفق', 'torob-variable-exporter' ); ?></dt><dd id="tves-last-sync"><?php echo $status['last'] ? esc_html( wp_date( 'Y-m-d H:i', $status['last'] ) ) : esc_html__( 'هنوز انجام نشده', 'torob-variable-exporter' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'اجرای بعدی', 'torob-variable-exporter' ); ?></dt><dd id="tves-next-sync"><?php echo $status['next'] ? esc_html( wp_date( 'Y-m-d H:i', $status['next'] ) ) : esc_html__( 'فقط اجرای دستی', 'torob-variable-exporter' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'آخرین فعالیت پردازشگر', 'torob-variable-exporter' ); ?></dt><dd id="tves-last-activity"><?php echo $status['last_activity'] ? esc_html( wp_date( 'Y-m-d H:i:s', $status['last_activity'] ) ) : esc_html__( 'هنوز فعالیتی ثبت نشده', 'torob-variable-exporter' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'بازیابی خودکار / تلاش مجدد', 'torob-variable-exporter' ); ?></dt><dd id="tves-recovery-count"><?php echo esc_html( (string) $status['recovery_count'] . ' / ' . (string) $status['total_retries'] ); ?></dd></div>
				</dl>
			</article>
		</div>
	</section>

	<form method="post" action="options.php" class="tves-settings-form">
		<?php settings_fields( 'tves_settings_group' ); ?>
		<section class="tves-panel" id="tves-output">
			<div class="tves-panel-heading"><span class="dashicons dashicons-products" aria-hidden="true"></span><div><h2><?php esc_html_e( 'تنظیم خروجی محصولات', 'torob-variable-exporter' ); ?></h2><p><?php esc_html_e( 'مشخص کنید کدام اطلاعات و با چه عنوانی برای ترب ارسال شود.', 'torob-variable-exporter' ); ?></p></div></div>
			<div class="tves-field-stack">
				<div class="tves-field tves-field-toggle"><div><strong><?php esc_html_e( 'درگاه رسمی API نسخه ۳', 'torob-variable-exporter' ); ?></strong><p><?php esc_html_e( 'با فعال بودن این گزینه، درخواست‌های امن و تأییدشده ترب پاسخ داده می‌شوند. برای اتصال فعلی فروشگاه باید روشن بماند.', 'torob-variable-exporter' ); ?></p></div><label class="tves-switch"><input type="checkbox" name="tves_settings[v3_enabled]" value="1" <?php checked( 'yes', $v3_enabled ); ?>><span aria-hidden="true"></span><b><?php esc_html_e( 'API فعال باشد', 'torob-variable-exporter' ); ?></b></label></div>
				<div class="tves-field tves-field-toggle"><div><strong><?php esc_html_e( 'ارسال جداگانه انتخاب‌های محصول متغیر', 'torob-variable-exporter' ); ?></strong><p><?php esc_html_e( 'هر رنگ، اندازه یا انتخاب قابل خرید به‌صورت یک محصول مستقل در ترب نمایش داده می‌شود.', 'torob-variable-exporter' ); ?></p></div><label class="tves-switch"><input type="checkbox" name="tves_settings[enabled]" value="1" <?php checked( 'yes', $enabled ); ?>><span aria-hidden="true"></span><b><?php esc_html_e( 'انتخاب‌ها جدا ارسال شوند', 'torob-variable-exporter' ); ?></b></label></div>
				<div class="tves-field"><label class="tves-field-label" for="tves-title-format"><?php esc_html_e( 'ساختار عنوان در ترب', 'torob-variable-exporter' ); ?></label><div class="tves-control"><select id="tves-title-format" name="tves_settings[title_format]"><option value="parent_attributes" <?php selected( $title_format, 'parent_attributes' ); ?>><?php esc_html_e( 'نام محصول + ویژگی انتخاب‌شده', 'torob-variable-exporter' ); ?></option><option value="parent" <?php selected( $title_format, 'parent' ); ?>><?php esc_html_e( 'فقط نام محصول اصلی', 'torob-variable-exporter' ); ?></option><option value="custom" <?php selected( $title_format, 'custom' ); ?>><?php esc_html_e( 'الگوی دلخواه', 'torob-variable-exporter' ); ?></option></select><p><?php esc_html_e( 'این گزینه تعیین می‌کند عنوان هر انتخاب محصول متغیر در ترب چگونه نمایش داده شود. حالت پیشنهادی «نام محصول + ویژگی انتخاب‌شده» است.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field tves-template-row"><label class="tves-field-label" for="tves-title-template"><?php esc_html_e( 'الگوی دلخواه عنوان', 'torob-variable-exporter' ); ?></label><div class="tves-control"><input class="regular-text" dir="ltr" id="tves-title-template" name="tves_settings[title_template]" value="<?php echo esc_attr( $settings['title_template'] ?? '{parent} - {attributes}' ); ?>"><p><?php esc_html_e( 'برای نام محصول از {parent}، برای همه ویژگی‌ها از {attributes} و برای یک ویژگی مشخص از عبارتی مانند {attribute:pa_color} استفاده کنید.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><span class="tves-field-label"><?php esc_html_e( 'ویژگی‌های قابل نمایش در عنوان', 'torob-variable-exporter' ); ?></span><div class="tves-control tves-checkbox-list"><?php if ( ! $attributes ) : ?><em><?php esc_html_e( 'برای شناسایی ویژگی‌های محصولات، یک‌بار کاتالوگ را بازسازی کنید.', 'torob-variable-exporter' ); ?></em><?php endif; ?><?php foreach ( $attributes as $slug => $label ) : ?><label><input type="checkbox" name="tves_settings[title_attributes][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $title_attributes, true ) ); ?>><span><?php echo esc_html( $label ); ?></span><code dir="ltr"><?php echo esc_html( $slug ); ?></code></label><?php endforeach; ?><p><?php esc_html_e( 'اگر هیچ موردی را انتخاب نکنید، تمام ویژگی‌های موجود به عنوان محصول اضافه می‌شوند.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><span class="tves-field-label"><?php esc_html_e( 'ویژگی‌های ارسالی برای ترب', 'torob-variable-exporter' ); ?></span><div class="tves-control tves-checkbox-list"><?php foreach ( $attributes as $slug => $label ) : ?><label><input type="checkbox" name="tves_settings[export_attributes][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $export_attributes, true ) ); ?>><span><?php echo esc_html( $label ); ?></span><code dir="ltr"><?php echo esc_html( $slug ); ?></code></label><?php endforeach; ?><p><?php esc_html_e( 'فقط ویژگی‌های انتخاب‌شده در مشخصات محصول ترب ارسال می‌شوند. اگر همه را بدون انتخاب بگذارید، تمام ویژگی‌ها ارسال خواهند شد.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><label class="tves-field-label" for="tves-sync-interval"><?php esc_html_e( 'زمان‌بندی بازسازی خودکار', 'torob-variable-exporter' ); ?></label><div class="tves-control"><select id="tves-sync-interval" name="tves_settings[sync_interval]"><option value="manual" <?php selected( $settings['sync_interval'] ?? 'manual', 'manual' ); ?>><?php esc_html_e( 'فقط به‌صورت دستی', 'torob-variable-exporter' ); ?></option><option value="hourly" <?php selected( $settings['sync_interval'] ?? '', 'hourly' ); ?>><?php esc_html_e( 'هر یک ساعت', 'torob-variable-exporter' ); ?></option><option value="six_hours" <?php selected( $settings['sync_interval'] ?? '', 'six_hours' ); ?>><?php esc_html_e( 'هر ۶ ساعت', 'torob-variable-exporter' ); ?></option><option value="daily" <?php selected( $settings['sync_interval'] ?? '', 'daily' ); ?>><?php esc_html_e( 'روزی یک‌بار', 'torob-variable-exporter' ); ?></option></select><p><?php esc_html_e( 'بازسازی دوره‌ای باعث می‌شود تغییر قیمت، موجودی و محصولات جدید در کاتالوگ API ترب ثبت شوند.', 'torob-variable-exporter' ); ?></p></div></div>
				<details class="tves-advanced-field"><summary><?php esc_html_e( 'تنظیمات اتصال قدیمی (برای کاربران فنی)', 'torob-variable-exporter' ); ?></summary><div><label class="tves-field-label" for="tves-api-token"><?php esc_html_e( 'توکن خروجی قدیمی', 'torob-variable-exporter' ); ?></label><input type="password" autocomplete="new-password" class="regular-text" dir="ltr" id="tves-api-token" name="tves_settings[api_token]" value="<?php echo esc_attr( $settings['api_token'] ?? '' ); ?>"><p><?php esc_html_e( 'اختیاری است و فقط از خروجی قدیمی GET محافظت می‌کند. اتصال رسمی API نسخه ۳ از این توکن استفاده نمی‌کند.', 'torob-variable-exporter' ); ?></p></div></details>
			</div>
		</section>

		<section class="tves-panel" id="tves-exclusions">
			<div class="tves-panel-heading"><span class="dashicons dashicons-hidden" aria-hidden="true"></span><div><h2><?php esc_html_e( 'حذف محصولات از خروجی ترب', 'torob-variable-exporter' ); ?></h2><p><?php esc_html_e( 'محصول، یک انتخاب مشخص از محصول متغیر یا یک دسته کامل را از کاتالوگ ترب کنار بگذارید.', 'torob-variable-exporter' ); ?></p></div></div>
			<div class="tves-scope-guide" aria-label="<?php esc_attr_e( 'روش‌های حذف از ترب', 'torob-variable-exporter' ); ?>"><div><strong><?php esc_html_e( 'حذف کل محصول', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'محصول ساده یا تمام انتخاب‌های یک محصول متغیر از ترب حذف می‌شوند.', 'torob-variable-exporter' ); ?></span></div><div><strong><?php esc_html_e( 'حذف فقط یک انتخاب', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'فقط همان رنگ، اندازه یا مدل انتخاب‌شده حذف می‌شود و بقیه باقی می‌مانند.', 'torob-variable-exporter' ); ?></span></div><div><strong><?php esc_html_e( 'حذف یک دسته', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'تمام محصولاتی که عضو دسته انتخاب‌شده هستند از خروجی کنار گذاشته می‌شوند.', 'torob-variable-exporter' ); ?></span></div></div>
			<div class="tves-field-stack">
				<div class="tves-field"><label class="tves-field-label" for="tves-excluded-products"><?php esc_html_e( 'محصولات کامل', 'torob-variable-exporter' ); ?></label><div class="tves-control"><select class="wc-product-search" multiple="multiple" id="tves-excluded-products" name="tves_settings[excluded_products][]" data-placeholder="<?php esc_attr_e( 'نام یا شناسه محصول را جست‌وجو کنید…', 'torob-variable-exporter' ); ?>" data-action="woocommerce_json_search_products" data-exclude_type="variation"><?php foreach ( $excluded_products as $product_id ) : $product = wc_get_product( $product_id ); if ( $product ) : ?><option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option><?php endif; endforeach; ?></select><p><?php esc_html_e( 'اگر محصول متغیر را اینجا انتخاب کنید، تمام رنگ‌ها، اندازه‌ها و انتخاب‌های آن از ترب حذف می‌شوند.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><label class="tves-field-label" for="tves-excluded-variations"><?php esc_html_e( 'یک انتخاب مشخص از محصول متغیر', 'torob-variable-exporter' ); ?></label><div class="tves-control"><select class="wc-product-search" multiple="multiple" id="tves-excluded-variations" name="tves_settings[excluded_variations][]" data-placeholder="<?php esc_attr_e( 'رنگ، اندازه یا انتخاب موردنظر را جست‌وجو کنید…', 'torob-variable-exporter' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-include_type="variation"><?php foreach ( $excluded_variations as $variation_id ) : $variation = wc_get_product( $variation_id ); if ( $variation ) : ?><option value="<?php echo esc_attr( $variation_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $variation->get_formatted_name() ) ); ?></option><?php endif; endforeach; ?></select><p><?php esc_html_e( 'فقط انتخاب مشخص‌شده حذف می‌شود؛ سایر انتخاب‌های همان محصول همچنان برای ترب ارسال خواهند شد.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><label class="tves-field-label" for="tves-category-search"><?php esc_html_e( 'دسته‌های محصول', 'torob-variable-exporter' ); ?></label><div class="tves-control"><div class="tves-category-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" id="tves-category-search" placeholder="<?php esc_attr_e( 'جست‌وجو در دسته‌ها…', 'torob-variable-exporter' ); ?>"></div><div class="tves-checkbox-list tves-category-list"><?php if ( ! is_wp_error( $categories ) ) : foreach ( $categories as $category ) : ?><label data-search="<?php echo esc_attr( $category->name ); ?>"><input type="checkbox" name="tves_settings[excluded_categories][]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, array_map( 'intval', (array) ( $settings['excluded_categories'] ?? array() ) ), true ) ); ?>><span><?php echo esc_html( $category->name ); ?></span></label><?php endforeach; endif; ?></div><p><?php esc_html_e( 'انتخاب یک دسته، تمام محصولات همان دسته را از خروجی ترب حذف می‌کند.', 'torob-variable-exporter' ); ?></p></div></div>
			</div>
		</section>

		<div class="tves-form-actions"><div><strong><?php esc_html_e( 'تنظیمات را بررسی کردید؟', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'بعد از ذخیره، برای اعمال تغییرات روی تمام محصولات، کاتالوگ را دوباره بازسازی کنید.', 'torob-variable-exporter' ); ?></span></div><?php submit_button( __( 'ذخیره تنظیمات', 'torob-variable-exporter' ), 'primary', 'submit', false ); ?></div>
	</form>

	<section class="tves-sync-panel" id="tves-sync-now">
		<div class="tves-sync-illustration" aria-hidden="true"><span></span><span></span><span></span></div>
		<div><h2><?php esc_html_e( 'بازسازی کامل کاتالوگ ترب', 'torob-variable-exporter' ); ?></h2><p><?php esc_html_e( 'پس از تغییر تنظیمات، قیمت، موجودی یا محصولات، این عملیات کاتالوگ را به‌روز می‌کند. تا پایان پردازش، نسخه قبلی کاتالوگ همچنان در دسترس ترب می‌ماند.', 'torob-variable-exporter' ); ?></p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tves-manual-sync"><input type="hidden" name="action" value="tves_manual_sync"><?php wp_nonce_field( 'tves_manual_sync' ); ?><?php submit_button( __( 'شروع بازسازی کاتالوگ', 'torob-variable-exporter' ), 'secondary', 'submit', false ); ?></form>
	</section>
</div>
