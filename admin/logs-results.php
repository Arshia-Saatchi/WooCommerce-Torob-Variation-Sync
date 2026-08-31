<?php
/**
 * Reusable initial/AJAX log table and pagination fragment.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

$total_pages = max( 1, (int) ceil( $log_result['total'] / 30 ) );
$pagination_args = array( 'page' => 'tves-logs' );
if ( $status ) {
	$pagination_args['status'] = $status;
}
$pagination_base = add_query_arg( $pagination_args, admin_url( 'admin.php' ) );
$pagination_base = add_query_arg( 'paged', '%#%', $pagination_base );
?>
<div class="tves-table-card">
	<div class="tves-table-scroll">
		<table class="widefat tves-log-table">
			<thead><tr><th class="column-date"><?php esc_html_e( 'Date', 'torob-variable-exporter' ); ?></th><th class="column-product"><?php esc_html_e( 'Product', 'torob-variable-exporter' ); ?></th><th class="column-variation"><?php esc_html_e( 'Variation ID', 'torob-variable-exporter' ); ?></th><th class="column-status"><?php esc_html_e( 'Status', 'torob-variable-exporter' ); ?></th><th class="column-message"><?php esc_html_e( 'Message', 'torob-variable-exporter' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $log_result['items'] ) : ?>
				<tr><td colspan="5" class="tves-empty-state"><strong><?php esc_html_e( 'No matching logs', 'torob-variable-exporter' ); ?></strong><span><?php esc_html_e( 'Try another status filter or run a feed synchronization.', 'torob-variable-exporter' ); ?></span></td></tr>
			<?php else : foreach ( $log_result['items'] as $log ) :
				$product      = $log->product_id ? wc_get_product( (int) $log->product_id ) : false;
				$product_name = $product ? $product->get_name() : ( $log->product_id ? '#' . $log->product_id : '—' );
				$edit_link    = $product ? get_edit_post_link( $product->get_id() ) : '';
			?>
				<tr>
					<td class="column-date"><time datetime="<?php echo esc_attr( get_date_from_gmt( $log->created_at, 'c' ) ); ?>"><?php echo esc_html( get_date_from_gmt( $log->created_at, 'Y-m-d H:i:s' ) ); ?></time></td>
					<td class="column-product"><?php if ( $edit_link ) : ?><a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $product_name ); ?></a><?php else : echo esc_html( $product_name ); endif; ?></td>
					<td class="column-variation"><?php echo $log->variation_id ? esc_html( (string) $log->variation_id ) : '—'; ?></td>
					<td class="column-status"><span class="tves-status tves-status-<?php echo esc_attr( $log->status ); ?>"><?php echo esc_html( $statuses[ $log->status ] ?? $log->status ); ?></span></td>
					<td class="column-message"><span class="tves-log-message"><?php echo esc_html( $log->message ); ?></span></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav tves-log-pagination" data-current-page="<?php echo esc_attr( $page ); ?>"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => $pagination_base, 'format' => '', 'current' => $page, 'total' => $total_pages, 'prev_text' => '&lsaquo;', 'next_text' => '&rsaquo;' ) ) ); ?></div></div>
<?php endif; ?>
