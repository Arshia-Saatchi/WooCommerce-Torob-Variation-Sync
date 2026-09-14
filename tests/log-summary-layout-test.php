<?php
/** Source-level regression checks for the compact log summary layout. */

$root = dirname( __DIR__ );
$view = file_get_contents( $root . '/admin/tabs/logs.php' );
$css  = file_get_contents( $root . '/assets/css/admin.css' );
$failures = array();

if ( false === strpos( $view, 'tves-log-stat-label' ) ) {
	$failures[] = 'Status dot and title are not grouped in one label row.';
}
if ( false === strpos( $css, '.tves-log-stat-label' ) || false === strpos( $css, 'min-height: 104px' ) ) {
	$failures[] = 'Compact log summary sizing or label-row styling is missing.';
}
foreach ( array( 'success', 'warning', 'error' ) as $status ) {
	$selector = '.tves-log-breakdown .tves-log-stat-' . $status . ' .tves-log-stat-label i';
	if ( false === strpos( $css, $selector ) ) {
		$failures[] = sprintf( 'The %s dot color is not protected from the generic dot rule.', $status );
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "log-summary-layout-test: ok\n";
