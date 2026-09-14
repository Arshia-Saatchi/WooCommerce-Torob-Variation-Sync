<?php
/** Source-level regression checks for RESA brand asset wiring. */

$root     = dirname( __DIR__ );
$admin    = file_get_contents( $root . '/includes/class-admin-settings.php' );
$settings = file_get_contents( $root . '/admin/settings-page.php' );
$failures = array();
$icon_path = $root . '/assets/images/resa-icon.png';
$logo_path = $root . '/assets/images/resa-logo-horizontal.png';

if ( ! is_file( $icon_path ) ) {
	$failures[] = 'Square RESA icon is missing.';
}
if ( ! is_file( $logo_path ) ) {
	$failures[] = 'Horizontal RESA logo is missing.';
}
if ( false === strpos( $admin, "TVES_URL . 'assets/images/resa-icon.png'" ) ) {
	$failures[] = 'Admin menu is not wired to the RESA icon.';
}
if ( false === strpos( $settings, "TVES_URL . 'assets/images/resa-logo-horizontal.png'" ) ) {
	$failures[] = 'Dashboard header is not wired to the horizontal RESA logo.';
}
if ( false === strpos( $admin, '#toplevel_page_tves-settings .wp-menu-image img' ) || false === strpos( $admin, 'width:20px!important' ) ) {
	$failures[] = 'Admin menu icon does not have a defensive 20px size constraint.';
}

if ( is_file( $icon_path ) && is_file( $logo_path ) ) {
	$icon_size = getimagesize( $icon_path );
	$logo_size = getimagesize( $logo_path );
	$icon_data = file_get_contents( $icon_path, false, null, 0, 26 );
	$logo_data = file_get_contents( $logo_path, false, null, 0, 26 );

	if ( ! $icon_size || $icon_size[0] !== $icon_size[1] ) {
		$failures[] = 'RESA menu icon must use a square canvas.';
	}
	if ( ! $logo_size || $logo_size[0] <= ( $logo_size[1] * 2 ) ) {
		$failures[] = 'RESA horizontal logo must retain its wide lockup.';
	}
	if ( strlen( $icon_data ) < 26 || 6 !== ord( $icon_data[25] ) || strlen( $logo_data ) < 26 || 6 !== ord( $logo_data[25] ) ) {
		$failures[] = 'RESA brand PNG files must retain genuine alpha transparency.';
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "branding-assets-test: ok\n";
