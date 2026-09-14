<?php
/** Unified RESA admin dashboard. */
defined( 'ABSPATH' ) || exit;

$tabs = array(
	'overview'   => array( 'label' => __( 'نمای کلی', 'torob-variable-exporter' ), 'icon' => 'dashicons-dashboard' ),
	'output'     => array( 'label' => __( 'تنظیم خروجی', 'torob-variable-exporter' ), 'icon' => 'dashicons-products' ),
	'exclusions' => array( 'label' => __( 'حذف از ترب', 'torob-variable-exporter' ), 'icon' => 'dashicons-hidden' ),
	'logs'       => array( 'label' => __( 'گزارش‌ها', 'torob-variable-exporter' ), 'icon' => 'dashicons-list-view' ),
);
$notice = sanitize_key( wp_unslash( $_GET['tves_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap tves-wrap tves-settings-screen" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
	<header class="tves-page-header">
		<div class="tves-brand-lockup">
			<span class="tves-brand-mark" aria-hidden="true"><img src="<?php echo esc_url( TVES_URL . 'assets/images/resa-icon.png' ); ?>" alt=""></span>
			<div><div class="tves-brand-name"><b>RESA</b><span><?php esc_html_e( 'رسا', 'torob-variable-exporter' ); ?></span></div><h1><?php esc_html_e( 'مرکز مدیریت اتصال ترب', 'torob-variable-exporter' ); ?></h1><p><?php esc_html_e( 'کاتالوگ ووکامرس، ارتباط امن با ترب و گزارش‌های همگام‌سازی را یک‌جا مدیریت کنید.', 'torob-variable-exporter' ); ?></p></div>
		</div>
		<div class="tves-header-actions"><span class="tves-version-badge"><span><?php echo esc_html( 'v' . TVES_VERSION ); ?></span><?php esc_html_e( 'آزمایشی', 'torob-variable-exporter' ); ?></span></div>
	</header>

	<?php if ( 'sync-started' === $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'بازسازی کاتالوگ شروع شد و پیشرفت آن در نمای کلی نمایش داده می‌شود.', 'torob-variable-exporter' ); ?></p></div><?php elseif ( 'sync-error' === $notice ) : ?><div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'بازسازی شروع نشد. برای مشاهده علت، تب گزارش‌ها را بررسی کنید.', 'torob-variable-exporter' ); ?></p></div><?php endif; ?>

	<nav class="tves-dashboard-tabs" aria-label="<?php esc_attr_e( 'بخش‌های رسا', 'torob-variable-exporter' ); ?>">
		<?php foreach ( $tabs as $tab_key => $tab ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tves-settings', 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>" data-tab="<?php echo esc_attr( $tab_key ); ?>" class="<?php echo $active_tab === $tab_key ? 'is-current' : ''; ?>" <?php echo $active_tab === $tab_key ? 'aria-current="page"' : ''; ?>><span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $tab['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="tves-dashboard-content" id="tves-dashboard-content" data-tab="<?php echo esc_attr( $active_tab ); ?>" aria-live="polite" aria-busy="false">
		<div class="tves-dashboard-loading" role="status"><span class="tves-loader" aria-hidden="true"><i></i><i></i><i></i></span><strong><?php esc_html_e( 'در حال آماده‌سازی این بخش…', 'torob-variable-exporter' ); ?></strong></div>
		<div class="tves-dashboard-error" role="alert" hidden></div>
		<div class="tves-dashboard-view"><?php include TVES_PATH . 'admin/tabs/' . $active_tab . '.php'; ?></div>
	</div>
</div>
