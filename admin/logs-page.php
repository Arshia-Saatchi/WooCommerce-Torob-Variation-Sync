<?php
/**
 * Log viewer template.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

$summary_cards = array(
	''          => array( 'count' => $counts['all'], 'label' => __( 'All logs', 'torob-variable-exporter' ), 'tone' => 'neutral' ),
	'success'   => array( 'count' => $counts['success'], 'label' => __( 'Successful', 'torob-variable-exporter' ), 'tone' => 'success' ),
	'warning'   => array( 'count' => $counts['warning'], 'label' => __( 'Warnings', 'torob-variable-exporter' ), 'tone' => 'warning' ),
	'error'     => array( 'count' => $counts['error'], 'label' => __( 'Errors', 'torob-variable-exporter' ), 'tone' => 'error' ),
	'api_error' => array( 'count' => $counts['api_error'], 'label' => __( 'API errors', 'torob-variable-exporter' ), 'tone' => 'error' ),
	'invalid'   => array( 'count' => $counts['invalid'], 'label' => __( 'Invalid items', 'torob-variable-exporter' ), 'tone' => 'error' ),
);
?>
<div class="wrap tves-wrap">
	<header class="tves-page-header">
		<div>
			<h1><?php esc_html_e( 'Torob Logs', 'torob-variable-exporter' ); ?></h1>
			<p><?php esc_html_e( 'Review synchronization activity, diagnose catalog issues, and export reports.', 'torob-variable-exporter' ); ?></p>
		</div>
		<span class="tves-version-badge"><?php echo esc_html( 'v' . TVES_VERSION ); ?></span>
	</header>

	<nav class="tves-log-summary" aria-label="<?php esc_attr_e( 'Log status summary', 'torob-variable-exporter' ); ?>">
		<?php foreach ( $summary_cards as $card_status => $card ) :
			$card_url = add_query_arg( array_filter( array( 'page' => 'tves-logs', 'status' => $card_status ) ), admin_url( 'admin.php' ) );
		?>
			<a class="tves-log-stat tves-log-stat-<?php echo esc_attr( $card['tone'] ); ?> <?php echo $status === $card_status ? 'is-active' : ''; ?>" href="<?php echo esc_url( $card_url ); ?>" data-status="<?php echo esc_attr( $card_status ); ?>">
				<span><?php echo esc_html( $card['label'] ); ?></span>
				<strong data-log-count="<?php echo esc_attr( $card_status ?: 'all' ); ?>"><?php echo esc_html( number_format_i18n( $card['count'] ) ); ?></strong>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="tves-log-toolbar">
		<form method="get" class="tves-log-filter tves-toolbar-group">
			<input type="hidden" name="page" value="tves-logs">
			<label for="tves-status-filter"><?php esc_html_e( 'Show', 'torob-variable-exporter' ); ?></label>
			<select id="tves-status-filter" name="status">
				<?php foreach ( $statuses as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Apply filter', 'torob-variable-exporter' ), 'secondary', 'filter_action', false ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tves-log-export tves-toolbar-group">
			<input type="hidden" name="action" value="tves_export_logs">
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<?php wp_nonce_field( 'tves_export_logs' ); ?>
			<label for="tves-export-format"><?php esc_html_e( 'Export', 'torob-variable-exporter' ); ?></label>
			<select id="tves-export-format" name="format">
				<option value="csv"><?php esc_html_e( 'CSV report', 'torob-variable-exporter' ); ?></option>
				<option value="txt"><?php esc_html_e( 'TXT report', 'torob-variable-exporter' ); ?></option>
			</select>
			<?php submit_button( __( 'Download', 'torob-variable-exporter' ), 'primary', 'download_logs', false ); ?>
		</form>
		<button type="button" class="button tves-refresh-logs" id="tves-refresh-logs"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Refresh', 'torob-variable-exporter' ); ?></button>
		<p class="tves-toolbar-help"><?php esc_html_e( 'Exports include all records matching the selected status, not only this page.', 'torob-variable-exporter' ); ?></p>
	</div>

	<div class="tves-log-results" id="tves-log-results" data-status="<?php echo esc_attr( $status ); ?>" data-paged="<?php echo esc_attr( $page ); ?>" aria-live="polite" aria-busy="false">
		<div class="tves-loading-state" role="status"><span class="spinner is-active" aria-hidden="true"></span><strong><?php esc_html_e( 'Loading logs…', 'torob-variable-exporter' ); ?></strong></div>
		<div class="tves-ajax-error" id="tves-log-error" role="alert" hidden></div>
		<div id="tves-log-results-content">
			<?php include TVES_PATH . 'admin/logs-results.php'; ?>
		</div>
	</div>
</div>
