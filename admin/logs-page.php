<?php
/**
 * Log viewer template.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

$summary_cards = array(
	'success'   => array( 'count' => $counts['success'], 'label' => __( 'Successful', 'torob-variable-exporter' ), 'tone' => 'success' ),
	'warning'   => array( 'count' => $counts['warning'], 'label' => __( 'Warnings', 'torob-variable-exporter' ), 'tone' => 'warning' ),
	'error'     => array( 'count' => $counts['error'], 'label' => __( 'Errors', 'torob-variable-exporter' ), 'tone' => 'error' ),
	'api_error' => array( 'count' => $counts['api_error'], 'label' => __( 'API errors', 'torob-variable-exporter' ), 'tone' => 'error' ),
	'invalid'   => array( 'count' => $counts['invalid'], 'label' => __( 'Invalid items', 'torob-variable-exporter' ), 'tone' => 'error' ),
);
$all_logs_url = add_query_arg( array( 'page' => 'tves-logs' ), admin_url( 'admin.php' ) );
?>
<div class="wrap tves-wrap tves-logs-screen">
	<header class="tves-page-header">
		<div class="tves-brand-lockup">
			<span class="tves-brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
			<div><h1><?php esc_html_e( 'Activity log', 'torob-variable-exporter' ); ?></h1><p><?php esc_html_e( 'Follow catalog events, investigate failed products, and export evidence.', 'torob-variable-exporter' ); ?></p></div>
		</div>
		<div class="tves-header-actions"><a class="button tves-button-quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=tves-settings' ) ); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><?php esc_html_e( 'Back to settings', 'torob-variable-exporter' ); ?></a><span class="tves-version-badge"><span><?php echo esc_html( 'v' . TVES_VERSION ); ?></span><?php esc_html_e( 'Beta', 'torob-variable-exporter' ); ?></span></div>
	</header>

	<nav class="tves-log-summary" aria-label="<?php esc_attr_e( 'Log status summary', 'torob-variable-exporter' ); ?>">
		<a class="tves-log-total tves-log-stat <?php echo '' === $status ? 'is-active' : ''; ?>" href="<?php echo esc_url( $all_logs_url ); ?>" data-status=""><span><?php esc_html_e( 'All recorded events', 'torob-variable-exporter' ); ?></span><strong data-log-count="all"><?php echo esc_html( number_format_i18n( $counts['all'] ) ); ?></strong><small><?php esc_html_e( 'Last 30 days, up to 20,000 entries', 'torob-variable-exporter' ); ?></small></a>
		<div class="tves-log-breakdown">
			<?php foreach ( $summary_cards as $card_status => $card ) : $card_url = add_query_arg( array( 'page' => 'tves-logs', 'status' => $card_status ), admin_url( 'admin.php' ) ); ?>
				<a class="tves-log-stat tves-log-stat-<?php echo esc_attr( $card['tone'] ); ?> <?php echo $status === $card_status ? 'is-active' : ''; ?>" href="<?php echo esc_url( $card_url ); ?>" data-status="<?php echo esc_attr( $card_status ); ?>"><i aria-hidden="true"></i><span><?php echo esc_html( $card['label'] ); ?></span><strong data-log-count="<?php echo esc_attr( $card_status ); ?>"><?php echo esc_html( number_format_i18n( $card['count'] ) ); ?></strong></a>
			<?php endforeach; ?>
		</div>
	</nav>

	<div class="tves-log-toolbar">
		<div class="tves-toolbar-section">
			<form method="get" class="tves-log-filter tves-toolbar-group"><input type="hidden" name="page" value="tves-logs"><label for="tves-status-filter"><?php esc_html_e( 'Show events', 'torob-variable-exporter' ); ?></label><select id="tves-status-filter" name="status"><?php foreach ( $statuses as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><?php submit_button( __( 'Apply filter', 'torob-variable-exporter' ), 'secondary', 'filter_action', false ); ?></form>
			<button type="button" class="button tves-refresh-logs" id="tves-refresh-logs"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Refresh', 'torob-variable-exporter' ); ?></button>
		</div>
		<div class="tves-toolbar-section tves-toolbar-section-secondary">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tves-log-export tves-toolbar-group"><input type="hidden" name="action" value="tves_export_logs"><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php wp_nonce_field( 'tves_export_logs' ); ?><label for="tves-export-format"><?php esc_html_e( 'Report', 'torob-variable-exporter' ); ?></label><select id="tves-export-format" name="format"><option value="csv"><?php esc_html_e( 'CSV report', 'torob-variable-exporter' ); ?></option><option value="txt"><?php esc_html_e( 'TXT report', 'torob-variable-exporter' ); ?></option></select><?php submit_button( __( 'Download', 'torob-variable-exporter' ), 'primary', 'download_logs', false ); ?></form>
			<button type="button" class="button tves-clear-logs" id="tves-clear-logs"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Clear log', 'torob-variable-exporter' ); ?></button>
		</div>
		<p class="tves-toolbar-help"><?php esc_html_e( 'The downloaded report follows the active status filter and includes every matching row.', 'torob-variable-exporter' ); ?></p>
	</div>
	<div class="tves-ajax-notice" id="tves-log-notice" role="status" hidden></div>

	<div class="tves-log-results" id="tves-log-results" data-status="<?php echo esc_attr( $status ); ?>" data-paged="<?php echo esc_attr( $page ); ?>" aria-live="polite" aria-busy="false">
		<div class="tves-loading-state" role="status"><span class="tves-loader" aria-hidden="true"><i></i><i></i><i></i></span><strong><?php esc_html_e( 'Collecting activity…', 'torob-variable-exporter' ); ?></strong></div>
		<div class="tves-ajax-error" id="tves-log-error" role="alert" hidden></div>
		<div id="tves-log-results-content"><?php include TVES_PATH . 'admin/logs-results.php'; ?></div>
	</div>
</div>
