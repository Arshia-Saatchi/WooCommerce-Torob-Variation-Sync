<?php
/** RESA exclusions tab. */
defined( 'ABSPATH' ) || exit;

$excluded_products        = (array) ( $settings['excluded_products'] ?? array() );
$excluded_variations      = (array) ( $settings['excluded_variations'] ?? array() );
$excluded_product_items   = array();
$excluded_variation_items = array();

foreach ( $excluded_products as $product_id ) {
	$product = wc_get_product( $product_id );
	if ( $product ) {
		$excluded_product_items[] = array(
			'id'   => (int) $product_id,
			'name' => wp_strip_all_tags( $product->get_formatted_name() ),
		);
	}
}

foreach ( $excluded_variations as $variation_id ) {
	$variation = wc_get_product( $variation_id );
	if ( $variation ) {
		$excluded_variation_items[] = array(
			'id'   => (int) $variation_id,
			'name' => wp_strip_all_tags( $variation->get_formatted_name() ),
		);
	}
}
?>
<form method="post" action="options.php" class="tves-settings-form">
	<?php settings_fields( 'tves_settings_group' ); ?>
	<input type="hidden" name="tves_settings[settings_section]" value="exclusions">
	<section class="tves-panel">
		<div class="tves-panel-heading">
			<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
			<div>
				<h2><?php esc_html_e( 'حذف از ترب', 'torob-variable-exporter' ); ?></h2>
				<p><?php esc_html_e( 'محصول کامل، فقط یک انتخاب یا یک دسته را از کاتالوگ ترب کنار بگذارید.', 'torob-variable-exporter' ); ?></p>
			</div>
		</div>
		<div class="tves-scope-guide" aria-label="<?php esc_attr_e( 'روش‌های حذف از ترب', 'torob-variable-exporter' ); ?>">
			<div><strong><?php esc_html_e( 'کل محصول', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'محصول ساده یا همه انتخاب‌های محصول متغیر حذف می‌شوند.', 'torob-variable-exporter' ); ?></span></div>
			<div><strong><?php esc_html_e( 'فقط یک انتخاب', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'مثلاً فقط یک رنگ حذف می‌شود و رنگ‌های دیگر باقی می‌مانند.', 'torob-variable-exporter' ); ?></span></div>
			<div><strong><?php esc_html_e( 'یک دسته کامل', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'تمام محصولات عضو دسته از خروجی کنار گذاشته می‌شوند.', 'torob-variable-exporter' ); ?></span></div>
		</div>
		<div class="tves-field-stack">
			<div class="tves-field">
				<label class="tves-field-label" for="tves-excluded-products"><?php esc_html_e( 'محصولات کامل', 'torob-variable-exporter' ); ?></label>
				<div class="tves-control tves-exclusion-control">
					<select class="wc-product-search" multiple="multiple" id="tves-excluded-products" name="tves_settings[excluded_products][]" data-placeholder="<?php esc_attr_e( 'نام یا شناسه محصول را جست‌وجو کنید…', 'torob-variable-exporter' ); ?>" data-action="woocommerce_json_search_products" data-exclude_type="variation">
						<?php foreach ( $excluded_product_items as $item ) : ?>
							<option value="<?php echo esc_attr( $item['id'] ); ?>" selected><?php echo esc_html( $item['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p><?php esc_html_e( 'انتخاب محصول متغیر در این فیلد، همه انتخاب‌های آن را حذف می‌کند.', 'torob-variable-exporter' ); ?></p>
					<div class="tves-selected-exclusions" data-select-id="tves-excluded-products" <?php echo empty( $excluded_product_items ) ? 'hidden' : ''; ?>>
						<div class="tves-selected-exclusions-head">
							<strong><?php esc_html_e( 'محصولات کامل انتخاب‌شده', 'torob-variable-exporter' ); ?></strong>
							<span><b data-exclusion-count><?php echo esc_html( number_format_i18n( count( $excluded_product_items ) ) ); ?></b> <?php esc_html_e( 'مورد', 'torob-variable-exporter' ); ?></span>
						</div>
						<div class="tves-selected-exclusions-table-wrap">
							<table class="tves-selected-exclusions-table">
								<thead><tr><th><?php esc_html_e( 'نام محصول', 'torob-variable-exporter' ); ?></th><th><?php esc_html_e( 'شناسه', 'torob-variable-exporter' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'torob-variable-exporter' ); ?></span></th></tr></thead>
								<tbody>
									<?php foreach ( $excluded_product_items as $item ) : ?>
										<tr data-value="<?php echo esc_attr( $item['id'] ); ?>">
											<td><?php echo esc_html( $item['name'] ); ?></td>
											<td class="tves-exclusion-id" dir="ltr">#<?php echo esc_html( $item['id'] ); ?></td>
											<td class="tves-exclusion-actions"><button type="button" class="tves-remove-exclusion" data-value="<?php echo esc_attr( $item['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name. */ __( 'حذف %s از فهرست', 'torob-variable-exporter' ), $item['name'] ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="tves-remove-exclusion-label"><?php esc_html_e( 'حذف', 'torob-variable-exporter' ); ?></span></button></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
			<div class="tves-field">
				<label class="tves-field-label" for="tves-excluded-variations"><?php esc_html_e( 'یک انتخاب مشخص', 'torob-variable-exporter' ); ?></label>
				<div class="tves-control tves-exclusion-control">
					<select class="wc-product-search" multiple="multiple" id="tves-excluded-variations" name="tves_settings[excluded_variations][]" data-placeholder="<?php esc_attr_e( 'رنگ، اندازه یا انتخاب موردنظر را جست‌وجو کنید…', 'torob-variable-exporter' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-include_type="variation">
						<?php foreach ( $excluded_variation_items as $item ) : ?>
							<option value="<?php echo esc_attr( $item['id'] ); ?>" selected><?php echo esc_html( $item['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p><?php esc_html_e( 'فقط همین انتخاب حذف می‌شود و بقیه انتخاب‌های محصول برای ترب ارسال خواهند شد.', 'torob-variable-exporter' ); ?></p>
					<div class="tves-selected-exclusions" data-select-id="tves-excluded-variations" <?php echo empty( $excluded_variation_items ) ? 'hidden' : ''; ?>>
						<div class="tves-selected-exclusions-head">
							<strong><?php esc_html_e( 'انتخاب‌های مشخص حذف‌شده', 'torob-variable-exporter' ); ?></strong>
							<span><b data-exclusion-count><?php echo esc_html( number_format_i18n( count( $excluded_variation_items ) ) ); ?></b> <?php esc_html_e( 'مورد', 'torob-variable-exporter' ); ?></span>
						</div>
						<div class="tves-selected-exclusions-table-wrap">
							<table class="tves-selected-exclusions-table">
								<thead><tr><th><?php esc_html_e( 'نام محصول و انتخاب', 'torob-variable-exporter' ); ?></th><th><?php esc_html_e( 'شناسه انتخاب', 'torob-variable-exporter' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'عملیات', 'torob-variable-exporter' ); ?></span></th></tr></thead>
								<tbody>
									<?php foreach ( $excluded_variation_items as $item ) : ?>
										<tr data-value="<?php echo esc_attr( $item['id'] ); ?>">
											<td><?php echo esc_html( $item['name'] ); ?></td>
											<td class="tves-exclusion-id" dir="ltr">#<?php echo esc_html( $item['id'] ); ?></td>
											<td class="tves-exclusion-actions"><button type="button" class="tves-remove-exclusion" data-value="<?php echo esc_attr( $item['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: variation name. */ __( 'حذف %s از فهرست', 'torob-variable-exporter' ), $item['name'] ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="tves-remove-exclusion-label"><?php esc_html_e( 'حذف', 'torob-variable-exporter' ); ?></span></button></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
			<div class="tves-field">
				<label class="tves-field-label" for="tves-category-search"><?php esc_html_e( 'دسته‌های محصول', 'torob-variable-exporter' ); ?></label>
				<div class="tves-control">
					<div class="tves-category-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" id="tves-category-search" placeholder="<?php esc_attr_e( 'جست‌وجو در دسته‌ها…', 'torob-variable-exporter' ); ?>"></div>
					<div class="tves-checkbox-list tves-category-list">
						<?php if ( ! is_wp_error( $categories ) ) : ?>
							<?php foreach ( $categories as $category ) : ?>
								<label data-search="<?php echo esc_attr( $category->name ); ?>"><input type="checkbox" name="tves_settings[excluded_categories][]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( (int) $category->term_id, array_map( 'intval', (array) ( $settings['excluded_categories'] ?? array() ) ), true ) ); ?>><span><?php echo esc_html( $category->name ); ?></span></label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<p><?php esc_html_e( 'محصولات تمام زیرشاخه‌های انتخاب‌شده نیز طبق عضویت دسته‌بندی بررسی می‌شوند.', 'torob-variable-exporter' ); ?></p>
				</div>
			</div>
		</div>
	</section>
	<div class="tves-form-actions"><div><strong><?php esc_html_e( 'فهرست حذف‌ها کامل است؟', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'پس از ذخیره، برای اعمال حذف‌ها کاتالوگ را از نمای کلی بازسازی کنید.', 'torob-variable-exporter' ); ?></span></div><?php submit_button( __( 'ذخیره موارد حذف‌شده', 'torob-variable-exporter' ), 'primary', 'submit', false ); ?></div>
</form>
