<?php
/**
 * Optional cleanup when the plugin is deleted from WordPress.
 *
 * Product data is never modified by this plugin.
 *
 * @package TorobVariableExporter
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

wp_clear_scheduled_hook( 'tves_sync_event' );
wp_clear_scheduled_hook( 'tves_sync_batch_event' );

delete_option( 'tves_settings' );
delete_option( 'tves_db_version' );
delete_option( 'tves_cache_generation' );
delete_option( 'tves_detected_attributes' );
delete_option( 'tves_last_sync' );
delete_option( 'tves_last_sync_meta' );
delete_option( 'tves_sync_state' );
delete_option( 'tves_v3_active_generation' );
delete_option( 'tves_v3_last_access' );
delete_transient( 'tves_sync_lock' );

$table_name = $wpdb->prefix . 'tves_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
$v3_table_name = $wpdb->prefix . 'tves_torob_v3_products';
$wpdb->query( "DROP TABLE IF EXISTS {$v3_table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
