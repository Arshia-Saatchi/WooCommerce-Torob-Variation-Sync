<?php
/** Source-level regression checks for RESA brand asset wiring. */

$root     = dirname( __DIR__ );
$admin    = file_get_contents( $root . '/includes/class-admin-settings.php' );
$settings = file_get_contents( $root . '/admin/settings-page.php' );
$failures = array();

if ( ! is_file( $root . '/assets/images/resa-icon.png' ) ) {
	$failures[] = 'Square RESA icon is missing.';
}
if ( ! is_file( $root . '/assets/images/resa-logo-horizontal.png' ) ) {
	$failures[] = 'Horizontal RESA logo is missing.';
}
if ( false === strpos( $admin, "TVES_URL . 'assets/images/resa-icon.png'" ) ) {
	$failures[] = 'Admin menu is not wired to the RESA icon.';
}
if ( false === strpos( $settings, "TVES_URL . 'assets/images/resa-icon.png'" ) ) {
	$failures[] = 'Dashboard header is not wired to the RESA icon.';
}
if ( false === strpos( $admin, '#toplevel_page_tves-settings .wp-menu-image img' ) || false === strpos( $admin, 'width:20px!important' ) ) {
	$failures[] = 'Admin menu icon does not have a defensive 20px size constraint.';
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "branding-assets-test: ok\n";
