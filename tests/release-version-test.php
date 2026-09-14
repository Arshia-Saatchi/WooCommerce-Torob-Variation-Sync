<?php
/** Regression checks for a stable RESA release. */

$root     = dirname( __DIR__ );
$plugin   = file_get_contents( $root . '/torob-variable-exporter.php' );
$readme   = file_get_contents( $root . '/README.md' );
$wp_readme = file_get_contents( $root . '/readme.txt' );
$settings = file_get_contents( $root . '/admin/settings-page.php' );
$legacy_logs = file_get_contents( $root . '/admin/logs-page.php' );
$failures = array();

if ( ! preg_match( '/^\s*\* Version:\s+2\.0\.0\s*$/m', $plugin ) || false === strpos( $plugin, "define( 'TVES_VERSION', '2.0.0' );" ) ) {
	$failures[] = 'Plugin header and runtime constant are not set to stable version 2.0.0.';
}

if ( false === strpos( $plugin, '* Requires Plugins: woocommerce' ) ) {
	$failures[] = 'The stable release does not declare WooCommerce as a WordPress dependency.';
}

if ( false === strpos( $readme, '**نسخه:** 2.0.0<br>' ) || ! preg_match( '/^Stable tag: 2\.0\.0\s*$/m', $wp_readme ) ) {
	$failures[] = 'README version metadata is not synchronized with 2.0.0.';
}

if ( false !== strpos( $settings, "esc_html_e( 'آزمایشی'" ) || false !== strpos( $legacy_logs, "esc_html_e( 'آزمایشی'" ) ) {
	$failures[] = 'An experimental badge remains in the stable admin interface.';
}

if ( false === strpos( $settings, "esc_html_e( 'پایدار'" ) || false === strpos( $legacy_logs, "esc_html_e( 'پایدار'" ) ) {
	$failures[] = 'Stable badges are missing from an admin entry point.';
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "release-version-test: ok\n";
