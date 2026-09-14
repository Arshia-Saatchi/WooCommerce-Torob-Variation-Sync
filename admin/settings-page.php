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
				<h1><?php esc_html_e( 'Torob operations', 'torob-variable-exporter' ); ?></h1>
				<p><?php esc_html_e( 'Manage what WooCommerce sends to Torob and keep the catalog healthy.', 'torob-variable-exporter' ); ?></p>
			</div>
		</div>
		<div class="tves-header-actions">
			<a class="button tves-button-quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=tves-logs' ) ); ?>"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><?php esc_html_e( 'Open activity log', 'torob-variable-exporter' ); ?></a>
			<span class="tves-version-badge"><span><?php echo esc_html( 'v' . TVES_VERSION ); ?></span><?php esc_html_e( 'Beta', 'torob-variable-exporter' ); ?></span>
		</div>
	</header>

	<?php if ( 'sync-started' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Feed synchronization started. Keep this page open; AJAX progress will continue the remaining batches if WP-Cron is unavailable.', 'torob-variable-exporter' ); ?></p></div>
	<?php elseif ( 'sync-error' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The synchronization could not be started. Review Torob Logs for details.', 'torob-variable-exporter' ); ?></p></div>
	<?php endif; ?>

	<nav class="tves-section-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'torob-variable-exporter' ); ?>">
		<a href="#tves-overview" class="is-current"><?php esc_html_e( 'Overview', 'torob-variable-exporter' ); ?></a>
		<a href="#tves-output"><?php esc_html_e( 'Product output', 'torob-variable-exporter' ); ?></a>
		<a href="#tves-exclusions"><?php esc_html_e( 'Exclusions', 'torob-variable-exporter' ); ?></a>
		<a href="#tves-sync-now"><?php esc_html_e( 'Synchronization', 'torob-variable-exporter' ); ?></a>
	</nav>

	<section class="tves-overview" id="tves-overview">
		<div class="tves-signal-rail" aria-label="<?php esc_attr_e( 'Torob connection path', 'torob-variable-exporter' ); ?>">
			<div class="tves-signal <?php echo $api_ready ? 'is-ready' : 'is-alert'; ?>">
				<span class="tves-signal-dot" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'Secure API', 'torob-variable-exporter' ); ?></strong><small><?php echo $api_ready ? esc_html__( 'JWT verification is ready', 'torob-variable-exporter' ) : esc_html__( 'Check API or Sodium', 'torob-variable-exporter' ); ?></small></span>
			</div>
			<div class="tves-signal <?php echo $catalog_ready ? 'is-ready' : 'is-alert'; ?>">
				<span class="tves-signal-dot" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'Product catalog', 'torob-variable-exporter' ); ?></strong><small id="tves-v3-catalog-status"><?php echo $catalog_ready ? esc_html( sprintf( /* translators: %d: item count. */ __( '%d items ready', 'torob-variable-exporter' ), $v3_stats['total'] ) ) : esc_html__( 'Run the first synchronization', 'torob-variable-exporter' ); ?></small></span>
			</div>
			<div class="tves-signal is-<?php echo esc_attr( $sync_state ); ?>" id="tves-sync-signal">
				<span class="tves-signal-dot" aria-hidden="true"></span>
				<span><strong><?php esc_html_e( 'Synchronization', 'torob-variable-exporter' ); ?></strong><small id="tves-sync-status"><?php echo $status['running'] ? esc_html__( 'running', 'torob-variable-exporter' ) : esc_html__( 'idle', 'torob-variable-exporter' ); ?></small></span>
			</div>
		</div>

		<div class="tves-overview-grid">
			<article class="tves-api-console">
				<div class="tves-console-heading">
					<div><h2><?php esc_html_e( 'Official Torob Product API v3', 'torob-variable-exporter' ); ?></h2><p><?php esc_html_e( 'This is the endpoint Torob should use.', 'torob-variable-exporter' ); ?></p></div>
					<span class="tves-state-pill <?php echo $api_ready ? 'is-positive' : 'is-negative'; ?>"><?php echo $api_ready ? esc_html__( 'Protected', 'torob-variable-exporter' ) : esc_html__( 'Needs attention', 'torob-variable-exporter' ); ?></span>
				</div>
				<div class="tves-endpoint" dir="ltr">
					<code id="tves-v3-endpoint"><?php echo esc_html( $v3_feed_url ); ?></code>
					<button type="button" class="button tves-copy-button" data-copy-target="tves-v3-endpoint" data-label="<?php esc_attr_e( 'Copy', 'torob-variable-exporter' ); ?>" data-success="<?php esc_attr_e( 'Copied', 'torob-variable-exporter' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span><?php esc_html_e( 'Copy', 'torob-variable-exporter' ); ?></span></button>
				</div>
				<dl class="tves-fact-list">
					<div><dt><?php esc_html_e( 'Accepted domains', 'torob-variable-exporter' ); ?></dt><dd dir="ltr"><?php echo esc_html( implode( ' / ', $v3_audiences ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'JWT verification', 'torob-variable-exporter' ); ?></dt><dd><?php echo $v3_crypto_ready ? esc_html__( 'Ready with PHP Sodium', 'torob-variable-exporter' ) : esc_html__( 'PHP Sodium is missing', 'torob-variable-exporter' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Last Torob request', 'torob-variable-exporter' ); ?></dt><dd id="tves-v3-last-access"><?php echo $v3_last_access ? esc_html( wp_date( 'Y-m-d H:i:s', $v3_last_access ) ) : esc_html__( 'Never', 'torob-variable-exporter' ); ?></dd></div>
				</dl>
				<details class="tves-legacy-endpoint">
					<summary><?php esc_html_e( 'Legacy GET endpoint', 'torob-variable-exporter' ); ?></summary>
					<p><?php esc_html_e( 'Keep this endpoint only for backward compatibility. Do not send it to Torob as the v3 API.', 'torob-variable-exporter' ); ?></p>
					<div class="tves-endpoint" dir="ltr"><code id="tves-v1-endpoint"><?php echo esc_html( $feed_url ); ?></code><button type="button" class="button tves-copy-button" data-copy-target="tves-v1-endpoint" data-label="<?php esc_attr_e( 'Copy', 'torob-variable-exporter' ); ?>" data-success="<?php esc_attr_e( 'Copied', 'torob-variable-exporter' ); ?>"><span><?php esc_html_e( 'Copy', 'torob-variable-exporter' ); ?></span></button></div>
				</details>
			</article>

			<article class="tves-sync-monitor" id="tves-sync-card" data-running="<?php echo $status['running'] ? '1' : '0'; ?>">
				<div class="tves-monitor-heading">
					<div><span><?php esc_html_e( 'Catalog activity', 'torob-variable-exporter' ); ?></span><strong id="tves-exported-count"><?php echo esc_html( number_format_i18n( $status['exported_items'] ) ); ?></strong><small><?php esc_html_e( 'exported feed items', 'torob-variable-exporter' ); ?></small></div>
					<span class="tves-live-indicator"><i aria-hidden="true"></i><span id="tves-live-label"><?php echo $status['running'] ? esc_html__( 'Live', 'torob-variable-exporter' ) : esc_html__( 'Ready', 'torob-variable-exporter' ); ?></span></span>
				</div>
				<div class="tves-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Synchronization progress', 'torob-variable-exporter' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $status['percent'] ); ?>"><span id="tves-progress-bar" style="width: <?php echo esc_attr( $status['percent'] ); ?>%"></span></div>
				<p class="tves-progress-copy" id="tves-progress-label" aria-live="polite"><?php echo esc_html( sprintf( /* translators: 1: processed, 2: total. */ __( '%1$d of %2$d source products checked', 'torob-variable-exporter' ), $status['processed'], $status['total'] ) ); ?></p>
				<dl class="tves-monitor-facts">
					<div><dt><?php esc_html_e( 'Last completed', 'torob-variable-exporter' ); ?></dt><dd id="tves-last-sync"><?php echo $status['last'] ? esc_html( wp_date( 'Y-m-d H:i', $status['last'] ) ) : esc_html__( 'Never', 'torob-variable-exporter' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Next run', 'torob-variable-exporter' ); ?></dt><dd id="tves-next-sync"><?php echo $status['next'] ? esc_html( wp_date( 'Y-m-d H:i', $status['next'] ) ) : esc_html__( 'Manual only', 'torob-variable-exporter' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Last activity', 'torob-variable-exporter' ); ?></dt><dd id="tves-last-activity"><?php echo $status['last_activity'] ? esc_html( wp_date( 'Y-m-d H:i:s', $status['last_activity'] ) ) : esc_html__( 'Never', 'torob-variable-exporter' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Recoveries / retries', 'torob-variable-exporter' ); ?></dt><dd id="tves-recovery-count"><?php echo esc_html( (string) $status['recovery_count'] . ' / ' . (string) $status['total_retries'] ); ?></dd></div>
				</dl>
			</article>
		</div>
	</section>

	<form method="post" action="options.php" class="tves-settings-form">
		<?php settings_fields( 'tves_settings_group' ); ?>
		<section class="tves-panel" id="tves-output">
			<div class="tves-panel-heading"><span class="dashicons dashicons-products" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Product output', 'torob-variable-exporter' ); ?></h2><p><?php esc_html_e( 'Choose what appears in Torob and how variation titles are built.', 'torob-variable-exporter' ); ?></p></div></div>
			<div class="tves-field-stack">
				<div class="tves-field tves-field-toggle"><div><strong><?php esc_html_e( 'Official API v3', 'torob-variable-exporter' ); ?></strong><p><?php esc_html_e( 'Accept authenticated product requests from Torob.', 'torob-variable-exporter' ); ?></p></div><label class="tves-switch"><input type="checkbox" name="tves_settings[v3_enabled]" value="1" <?php checked( 'yes', $v3_enabled ); ?>><span aria-hidden="true"></span><b><?php esc_html_e( 'Enable API v3', 'torob-variable-exporter' ); ?></b></label></div>
				<div class="tves-field tves-field-toggle"><div><strong><?php esc_html_e( 'Export variations independently', 'torob-variable-exporter' ); ?></strong><p><?php esc_html_e( 'Each selectable variation becomes its own Torob product.', 'torob-variable-exporter' ); ?></p></div><label class="tves-switch"><input type="checkbox" name="tves_settings[enabled]" value="1" <?php checked( 'yes', $enabled ); ?>><span aria-hidden="true"></span><b><?php esc_html_e( 'Export variations', 'torob-variable-exporter' ); ?></b></label></div>
				<div class="tves-field"><label class="tves-field-label" for="tves-title-format"><?php esc_html_e( 'Product title format', 'torob-variable-exporter' ); ?></label><div class="tves-control"><select id="tves-title-format" name="tves_settings[title_format]"><option value="parent_attributes" <?php selected( $title_format, 'parent_attributes' ); ?>><?php esc_html_e( 'Parent name + attributes', 'torob-variable-exporter' ); ?></option><option value="parent" <?php selected( $title_format, 'parent' ); ?>><?php esc_html_e( 'Only parent name', 'torob-variable-exporter' ); ?></option><option value="custom" <?php selected( $title_format, 'custom' ); ?>><?php esc_html_e( 'Custom template', 'torob-variable-exporter' ); ?></option></select><p><?php esc_html_e( 'Controls the title Torob receives for every variation.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field tves-template-row"><label class="tves-field-label" for="tves-title-template"><?php esc_html_e( 'Custom title template', 'torob-variable-exporter' ); ?></label><div class="tves-control"><input class="regular-text" id="tves-title-template" name="tves_settings[title_template]" value="<?php echo esc_attr( $settings['title_template'] ?? '{parent} - {attributes}' ); ?>"><p><?php esc_html_e( 'Use {parent}, {attributes}, or a field such as {attribute:pa_color}.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><span class="tves-field-label"><?php esc_html_e( 'Attributes in title', 'torob-variable-exporter' ); ?></span><div class="tves-control tves-checkbox-list"><?php if ( ! $attributes ) : ?><em><?php esc_html_e( 'Run a synchronization to discover product attributes.', 'torob-variable-exporter' ); ?></em><?php endif; ?><?php foreach ( $attributes as $slug => $label ) : ?><label><input type="checkbox" name="tves_settings[title_attributes][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $title_attributes, true ) ); ?>><span><?php echo esc_html( $label ); ?></span><code><?php echo esc_html( $slug ); ?></code></label><?php endforeach; ?><p><?php esc_html_e( 'Leave everything unchecked to include all available attributes.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><span class="tves-field-label"><?php esc_html_e( 'Attributes sent to Torob', 'torob-variable-exporter' ); ?></span><div class="tves-control tves-checkbox-list"><?php foreach ( $attributes as $slug => $label ) : ?><label><input type="checkbox" name="tves_settings[export_attributes][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $export_attributes, true ) ); ?>><span><?php echo esc_html( $label ); ?></span><code><?php echo esc_html( $slug ); ?></code></label><?php endforeach; ?><p><?php esc_html_e( 'Leave everything unchecked to send all variation attributes.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><label class="tves-field-label" for="tves-sync-interval"><?php esc_html_e( 'Automatic synchronization', 'torob-variable-exporter' ); ?></label><div class="tves-control"><select id="tves-sync-interval" name="tves_settings[sync_interval]"><option value="manual" <?php selected( $settings['sync_interval'] ?? 'manual', 'manual' ); ?>><?php esc_html_e( 'Manual only', 'torob-variable-exporter' ); ?></option><option value="hourly" <?php selected( $settings['sync_interval'] ?? '', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'torob-variable-exporter' ); ?></option><option value="six_hours" <?php selected( $settings['sync_interval'] ?? '', 'six_hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'torob-variable-exporter' ); ?></option><option value="daily" <?php selected( $settings['sync_interval'] ?? '', 'daily' ); ?>><?php esc_html_e( 'Daily', 'torob-variable-exporter' ); ?></option></select></div></div>
				<details class="tves-advanced-field"><summary><?php esc_html_e( 'Legacy feed security', 'torob-variable-exporter' ); ?></summary><div><label class="tves-field-label" for="tves-api-token"><?php esc_html_e( 'Legacy feed token', 'torob-variable-exporter' ); ?></label><input type="password" autocomplete="new-password" class="regular-text" id="tves-api-token" name="tves_settings[api_token]" value="<?php echo esc_attr( $settings['api_token'] ?? '' ); ?>"><p><?php esc_html_e( 'Optional. This token protects only the old GET feed and is never used by API v3.', 'torob-variable-exporter' ); ?></p></div></details>
			</div>
		</section>

		<section class="tves-panel" id="tves-exclusions">
			<div class="tves-panel-heading"><span class="dashicons dashicons-hidden" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Exclusions', 'torob-variable-exporter' ); ?></h2><p><?php esc_html_e( 'Remove only the scope you intend: a whole product, one variation, or a category.', 'torob-variable-exporter' ); ?></p></div></div>
			<div class="tves-scope-guide" aria-label="<?php esc_attr_e( 'Exclusion scopes', 'torob-variable-exporter' ); ?>"><div><strong><?php esc_html_e( 'Whole product', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'Removes a simple product or every variation of a variable product.', 'torob-variable-exporter' ); ?></span></div><div><strong><?php esc_html_e( 'One variation', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'Removes only the selected color, size, or option.', 'torob-variable-exporter' ); ?></span></div><div><strong><?php esc_html_e( 'Category', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'Removes all products assigned to the selected category.', 'torob-variable-exporter' ); ?></span></div></div>
			<div class="tves-field-stack">
				<div class="tves-field"><label class="tves-field-label" for="tves-excluded-products"><?php esc_html_e( 'Products', 'torob-variable-exporter' ); ?></label><div class="tves-control"><select class="wc-product-search" multiple="multiple" id="tves-excluded-products" name="tves_settings[excluded_products][]" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'torob-variable-exporter' ); ?>" data-action="woocommerce_json_search_products" data-exclude_type="variation"><?php foreach ( $excluded_products as $product_id ) : $product = wc_get_product( $product_id ); if ( $product ) : ?><option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option><?php endif; endforeach; ?></select><p><?php esc_html_e( 'Selecting a variable parent removes all of its variations.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><label class="tves-field-label" for="tves-excluded-variations"><?php esc_html_e( 'Specific variations', 'torob-variable-exporter' ); ?></label><div class="tves-control"><select class="wc-product-search" multiple="multiple" id="tves-excluded-variations" name="tves_settings[excluded_variations][]" data-placeholder="<?php esc_attr_e( 'Search for a variation…', 'torob-variable-exporter' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-include_type="variation"><?php foreach ( $excluded_variations as $variation_id ) : $variation = wc_get_product( $variation_id ); if ( $variation ) : ?><option value="<?php echo esc_attr( $variation_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $variation->get_formatted_name() ) ); ?></option><?php endif; endforeach; ?></select><p><?php esc_html_e( 'Other variations of the same product remain available to Torob.', 'torob-variable-exporter' ); ?></p></div></div>
				<div class="tves-field"><label class="tves-field-label" for="tves-category-search"><?php esc_html_e( 'Product categories', 'torob-variable-exporter' ); ?></label><div class="tves-control"><div class="tves-category-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" id="tves-category-search" placeholder="<?php esc_attr_e( 'Filter categories…', 'torob-variable-exporter' ); ?>"></div><div class="tves-checkbox-list tves-category-list"><?php if ( ! is_wp_error( $categories ) ) : foreach ( $categories as $category ) : ?><label data-search="<?php echo esc_attr( $category->name ); ?>"><input type="checkbox" name="tves_settings[excluded_categories][]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, array_map( 'intval', (array) ( $settings['excluded_categories'] ?? array() ) ), true ) ); ?>><span><?php echo esc_html( $category->name ); ?></span></label><?php endforeach; endif; ?></div></div></div>
			</div>
		</section>

		<div class="tves-form-actions"><div><strong><?php esc_html_e( 'Ready to apply these rules?', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'Saving invalidates the previous feed cache. Run a new synchronization afterward.', 'torob-variable-exporter' ); ?></span></div><?php submit_button( __( 'Save changes', 'torob-variable-exporter' ), 'primary', 'submit', false ); ?></div>
	</form>

	<section class="tves-sync-panel" id="tves-sync-now">
		<div class="tves-sync-illustration" aria-hidden="true"><span></span><span></span><span></span></div>
		<div><h2><?php esc_html_e( 'Rebuild the Torob catalog', 'torob-variable-exporter' ); ?></h2><p><?php esc_html_e( 'Use this after changing settings or products. Existing live data stays available until the new catalog is complete.', 'torob-variable-exporter' ); ?></p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tves-manual-sync"><input type="hidden" name="action" value="tves_manual_sync"><?php wp_nonce_field( 'tves_manual_sync' ); ?><?php submit_button( __( 'Regenerate catalog', 'torob-variable-exporter' ), 'secondary', 'submit', false ); ?></form>
	</section>
</div>
