<?php
/** Source-level regression checks for exclusion selection tables. */

$root = dirname( __DIR__ );
$view = file_get_contents( $root . '/admin/tabs/exclusions.php' );
$js   = file_get_contents( $root . '/assets/js/admin.js' );
$css  = file_get_contents( $root . '/assets/css/admin.css' );
$failures = array();

foreach ( array( 'tves-excluded-products', 'tves-excluded-variations' ) as $select_id ) {
	if ( false === strpos( $view, 'data-select-id="' . $select_id . '"' ) ) {
		$failures[] = sprintf( 'The selected-items table for %s is missing.', $select_id );
	}
}

if ( false === strpos( $js, 'renderSelectedExclusions' ) || false === strpos( $js, '.tves-remove-exclusion' ) ) {
	$failures[] = 'Dynamic exclusion-table rendering or row removal is missing.';
}

if ( false === strpos( $css, '.tves-selected-exclusions-table' ) || false === strpos( $css, '.tves-exclusion-control .select2-selection__choice' ) ) {
	$failures[] = 'Exclusion tables are not styled or Select2 chips are still visible.';
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "exclusion-table-ui-test: ok\n";
