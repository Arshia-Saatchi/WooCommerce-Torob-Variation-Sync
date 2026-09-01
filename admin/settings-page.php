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
?>
<div class="wrap tves-wrap">
	<header class="tves-page-header">
		<div>
			<h1><?php esc_html_e( 'Torob Variable Product Exporter', 'torob-variable-exporter' ); ?></h1>
			<p><?php esc_html_e( 'Control product exports, variation titles, exclusions, and synchronization.', 'torob-variable-exporter' ); ?></p>
		</div>
		<div class="tves-header-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tves-logs' ) ); ?>"><?php esc_html_e( 'View logs', 'torob-variable-exporter' ); ?></a>
			<span class="tves-version-badge"><?php echo esc_html( 'v' . TVES_VERSION ); ?></span>
		</div>
	</header>

	<?php if ( 'sync-started' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Feed synchronization started. Remaining pages will continue through WP-Cron.', 'torob-variable-exporter' ); ?></p></div>
	<?php elseif ( 'sync-error' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The synchronization could not be started. Review Torob Logs for details.', 'torob-variable-exporter' ); ?></p></div>
	<?php endif; ?>

	<div class="tves-status-grid">
		<div class="tves-card">
			<strong><?php esc_html_e( 'Official Torob Product API v3', 'torob-variable-exporter' ); ?></strong>
			<code><?php echo esc_html( $v3_feed_url ); ?></code>
			<p><?php esc_html_e( 'Catalog:', 'torob-variable-exporter' ); ?> <strong id="tves-v3-catalog-status"><?php echo $v3_stats['ready'] ? esc_html( sprintf( /* translators: %d: item count. */ __( 'ready — %d items', 'torob-variable-exporter' ), $v3_stats['total'] ) ) : esc_html__( 'not generated yet', 'torob-variable-exporter' ); ?></strong></p>
			<p><?php esc_html_e( 'JWT audience:', 'torob-variable-exporter' ); ?> <code><?php echo esc_html( $v3_audience ); ?></code></p>
			<p><?php esc_html_e( 'JWT verification:', 'torob-variable-exporter' ); ?> <strong><?php echo $v3_crypto_ready ? esc_html__( 'ready (PHP Sodium)', 'torob-variable-exporter' ) : esc_html__( 'PHP Sodium is missing', 'torob-variable-exporter' ); ?></strong></p>
			<p><?php esc_html_e( 'Last authenticated Torob request:', 'torob-variable-exporter' ); ?> <strong id="tves-v3-last-access"><?php echo $v3_last_access ? esc_html( wp_date( 'Y-m-d H:i:s', $v3_last_access ) ) : esc_html__( 'Never', 'torob-variable-exporter' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'Give this POST endpoint to Torob support. Torob supplies and signs the JWT token; do not create a static token for v3.', 'torob-variable-exporter' ); ?></p>
		</div>
		<div class="tves-card">
			<strong><?php esc_html_e( 'Legacy GET feed', 'torob-variable-exporter' ); ?></strong>
			<code><?php echo esc_html( $feed_url ); ?></code>
			<p class="description"><?php esc_html_e( 'Kept for backward compatibility. Do not submit this URL as the official Torob v3 endpoint.', 'torob-variable-exporter' ); ?></p>
		</div>
		<div class="tves-card" id="tves-sync-card" data-running="<?php echo $status['running'] ? '1' : '0'; ?>">
			<strong><?php esc_html_e( 'Synchronization', 'torob-variable-exporter' ); ?></strong>
			<p><?php esc_html_e( 'Status:', 'torob-variable-exporter' ); ?> <strong id="tves-sync-status"><?php echo $status['running'] ? esc_html__( 'running', 'torob-variable-exporter' ) : esc_html__( 'idle', 'torob-variable-exporter' ); ?></strong></p>
			<p><?php esc_html_e( 'Last sync:', 'torob-variable-exporter' ); ?> <span id="tves-last-sync"><?php echo $status['last'] ? esc_html( wp_date( 'Y-m-d H:i', $status['last'] ) ) : esc_html__( 'Never', 'torob-variable-exporter' ); ?></span></p>
			<p><?php esc_html_e( 'Next sync:', 'torob-variable-exporter' ); ?> <span id="tves-next-sync"><?php echo $status['next'] ? esc_html( wp_date( 'Y-m-d H:i', $status['next'] ) ) : esc_html__( 'Manual only', 'torob-variable-exporter' ); ?></span></p>
			<div class="tves-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Synchronization progress', 'torob-variable-exporter' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $status['percent'] ); ?>">
				<span id="tves-progress-bar" style="width: <?php echo esc_attr( $status['percent'] ); ?>%"></span>
			</div>
			<p id="tves-progress-label" aria-live="polite"><?php echo esc_html( sprintf( /* translators: 1: processed, 2: total. */ __( '%1$d of %2$d source products checked', 'torob-variable-exporter' ), $status['processed'], $status['total'] ) ); ?></p>
			<p><?php esc_html_e( 'Exported feed items:', 'torob-variable-exporter' ); ?> <strong id="tves-exported-count"><?php echo esc_html( (string) $status['exported_items'] ); ?></strong></p>
		</div>
	</div>

	<form method="post" action="options.php" class="tves-settings-form">
		<?php settings_fields( 'tves_settings_group' ); ?>

		<section class="tves-panel">
		<div class="tves-panel-heading">
			<h2><?php esc_html_e( 'Export settings', 'torob-variable-exporter' ); ?></h2>
			<p><?php esc_html_e( 'Choose which variation data appears in the feed and how product titles are generated.', 'torob-variable-exporter' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Official API v3', 'torob-variable-exporter' ); ?></th>
				<td><label><input type="checkbox" name="tves_settings[v3_enabled]" value="1" <?php checked( 'yes', $v3_enabled ); ?>> <?php esc_html_e( 'Enable the JWT-protected Torob Product API v3 endpoint', 'torob-variable-exporter' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Variation export', 'torob-variable-exporter' ); ?></th>
				<td><label><input type="checkbox" name="tves_settings[enabled]" value="1" <?php checked( 'yes', $enabled ); ?>> <?php esc_html_e( 'Export each variable product variation as an independent item', 'torob-variable-exporter' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="tves-title-format"><?php esc_html_e( 'Product title format', 'torob-variable-exporter' ); ?></label></th>
				<td>
					<select id="tves-title-format" name="tves_settings[title_format]">
						<option value="parent_attributes" <?php selected( $title_format, 'parent_attributes' ); ?>><?php esc_html_e( 'Parent name + attributes', 'torob-variable-exporter' ); ?></option>
						<option value="parent" <?php selected( $title_format, 'parent' ); ?>><?php esc_html_e( 'Only parent name', 'torob-variable-exporter' ); ?></option>
						<option value="custom" <?php selected( $title_format, 'custom' ); ?>><?php esc_html_e( 'Custom template', 'torob-variable-exporter' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="tves-template-row">
				<th scope="row"><label for="tves-title-template"><?php esc_html_e( 'Custom title template', 'torob-variable-exporter' ); ?></label></th>
				<td><input class="regular-text" id="tves-title-template" name="tves_settings[title_template]" value="<?php echo esc_attr( $settings['title_template'] ?? '{parent} - {attributes}' ); ?>"><p class="description"><?php esc_html_e( 'Available: {parent}, {attributes}, and {attribute:pa_color} (replace pa_color with an attribute slug).', 'torob-variable-exporter' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Attributes in title', 'torob-variable-exporter' ); ?></th>
				<td class="tves-checkbox-list">
					<?php if ( ! $attributes ) : ?><em><?php esc_html_e( 'Run a sync to discover product-local attributes.', 'torob-variable-exporter' ); ?></em><?php endif; ?>
					<?php foreach ( $attributes as $slug => $label ) : ?>
						<label><input type="checkbox" name="tves_settings[title_attributes][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $title_attributes, true ) ); ?>> <?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'If none are selected, every exported attribute appears in the title.', 'torob-variable-exporter' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Exported attributes', 'torob-variable-exporter' ); ?></th>
				<td class="tves-checkbox-list">
					<?php foreach ( $attributes as $slug => $label ) : ?>
						<label><input type="checkbox" name="tves_settings[export_attributes][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $export_attributes, true ) ); ?>> <?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'If none are selected, every variation attribute is exported.', 'torob-variable-exporter' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="tves-sync-interval"><?php esc_html_e( 'Sync interval', 'torob-variable-exporter' ); ?></label></th>
				<td><select id="tves-sync-interval" name="tves_settings[sync_interval]">
					<option value="manual" <?php selected( $settings['sync_interval'] ?? 'manual', 'manual' ); ?>><?php esc_html_e( 'Manual only', 'torob-variable-exporter' ); ?></option>
					<option value="hourly" <?php selected( $settings['sync_interval'] ?? '', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'torob-variable-exporter' ); ?></option>
					<option value="six_hours" <?php selected( $settings['sync_interval'] ?? '', 'six_hours' ); ?>><?php esc_html_e( 'Every 6 hours', 'torob-variable-exporter' ); ?></option>
					<option value="daily" <?php selected( $settings['sync_interval'] ?? '', 'daily' ); ?>><?php esc_html_e( 'Daily', 'torob-variable-exporter' ); ?></option>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><label for="tves-api-token"><?php esc_html_e( 'Legacy feed token', 'torob-variable-exporter' ); ?></label></th>
				<td><input type="password" autocomplete="new-password" class="regular-text" id="tves-api-token" name="tves_settings[api_token]" value="<?php echo esc_attr( $settings['api_token'] ?? '' ); ?>"><p class="description"><?php esc_html_e( 'Optional static token for the legacy GET feed only. It is not used by the official v3 endpoint.', 'torob-variable-exporter' ); ?></p></td>
			</tr>
		</table>
		</section>

		<section class="tves-panel">
		<div class="tves-panel-heading">
			<h2><?php esc_html_e( 'Exclusions', 'torob-variable-exporter' ); ?></h2>
			<p><?php esc_html_e( 'Prevent complete products, individual variations, or categories from appearing in the feed.', 'torob-variable-exporter' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="tves-excluded-products"><?php esc_html_e( 'Products', 'torob-variable-exporter' ); ?></label></th>
				<td><select class="wc-product-search" multiple="multiple" style="width:50%" id="tves-excluded-products" name="tves_settings[excluded_products][]" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'torob-variable-exporter' ); ?>" data-action="woocommerce_json_search_products" data-exclude_type="variation">
					<?php foreach ( $excluded_products as $product_id ) : $product = wc_get_product( $product_id ); if ( $product ) : ?><option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option><?php endif; endforeach; ?>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><label for="tves-excluded-variations"><?php esc_html_e( 'Specific variations', 'torob-variable-exporter' ); ?></label></th>
				<td><select class="wc-product-search" multiple="multiple" style="width:50%" id="tves-excluded-variations" name="tves_settings[excluded_variations][]" data-placeholder="<?php esc_attr_e( 'Search for a variation…', 'torob-variable-exporter' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-include_type="variation">
					<?php foreach ( $excluded_variations as $variation_id ) : $variation = wc_get_product( $variation_id ); if ( $variation ) : ?><option value="<?php echo esc_attr( $variation_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $variation->get_formatted_name() ) ); ?></option><?php endif; endforeach; ?>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Product categories', 'torob-variable-exporter' ); ?></th>
				<td>
					<div class="tves-checkbox-list tves-category-list">
						<?php if ( ! is_wp_error( $categories ) ) : foreach ( $categories as $category ) : ?><label><input type="checkbox" name="tves_settings[excluded_categories][]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, array_map( 'intval', (array) ( $settings['excluded_categories'] ?? array() ) ), true ) ); ?>> <?php echo esc_html( $category->name ); ?></label><?php endforeach; endif; ?>
					</div>
				</td>
			</tr>
		</table>
		</section>

		<div class="tves-form-actions">
			<?php submit_button( __( 'Save settings', 'torob-variable-exporter' ), 'primary', 'submit', false ); ?>
			<span class="description"><?php esc_html_e( 'Saving settings invalidates the previous feed cache.', 'torob-variable-exporter' ); ?></span>
		</div>
	</form>

	<section class="tves-panel tves-sync-panel">
		<div>
			<h2><?php esc_html_e( 'Manual synchronization', 'torob-variable-exporter' ); ?></h2>
			<p><?php esc_html_e( 'Regenerate every cached feed page now. Large catalogs continue safely in background batches.', 'torob-variable-exporter' ); ?></p>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tves-manual-sync">
			<input type="hidden" name="action" value="tves_manual_sync">
			<?php wp_nonce_field( 'tves_manual_sync' ); ?>
			<?php submit_button( __( 'Regenerate feed now', 'torob-variable-exporter' ), 'secondary', 'submit', false ); ?>
		</form>
	</section>
</div>
