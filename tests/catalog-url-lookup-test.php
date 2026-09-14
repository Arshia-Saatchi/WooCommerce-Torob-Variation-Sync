<?php
/** Minimal behavior test for resilient Torob page_urls lookups. */

define( 'ABSPATH', __DIR__ . '/' );

function get_option( string $key, $default = false ) {
	return 'tves_v3_active_generation' === $key ? 'test-generation' : $default;
}

function sanitize_key( string $value ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function absint( $value ): int {
	return abs( (int) $value );
}

final class TVES_Test_WPDB {
	public string $prefix = 'wp_';
	private array $prepared_args = array();
	private string $payload;

	public function __construct() {
		$this->payload = json_encode(
			array(
				'page_unique' => '30670',
				'page_url'    => 'https://www.samaeistore.com/product/alborzrooz-karen-4-piece-set/?attribute_pa_color=white&variation_id=30670',
				'title'       => 'Karen - white',
				'spec'        => (object) array( 'Color' => 'White' ),
			)
		);
	}

	public function prepare( string $query, ...$args ): string {
		$this->prepared_args = 1 === count( $args ) && is_array( $args[0] ) ? $args[0] : $args;
		return $query;
	}

	public function get_col( string $query ): array {
		$current_url = 'samaeistore.com/product/alborzrooz-karen-4-piece-set/?attribute_pa_color=white&variation_id=30670';
		$stable_url  = 'samaeistore.com/product/alborzrooz-karen-4-piece-set/?attribute_pa_color=white';
		$hash        = false !== strpos( $query, 'lookup_hash' ) ? hash( 'sha256', $stable_url ) : hash( 'sha256', $current_url );
		return in_array( $hash, $this->prepared_args, true ) ? array( $this->payload ) : array();
	}
}

$wpdb = new TVES_Test_WPDB();
require dirname( __DIR__ ) . '/includes/class-torob-v3-catalog.php';

$catalog     = new TVES_Torob_V3_Catalog();
$old_url     = 'http://samaeistore.com/product/alborzrooz-karen-4-piece-set?variation_id=30665&attribute_pa_color=white';
$current_url = 'https://www.samaeistore.com/product/alborzrooz-karen-4-piece-set/?attribute_pa_color=white&variation_id=30670';
$wrong_url   = 'https://www.samaeistore.com/product/alborzrooz-karen-4-piece-set/?attribute_pa_color=black&variation_id=30665';

$old_result     = $catalog->find_by_urls( array( $old_url ) );
$current_result = $catalog->find_by_urls( array( $current_url ) );
$wrong_result   = $catalog->find_by_urls( array( $wrong_url ) );

if ( 1 !== count( $old_result ) || '30670' !== $old_result[0]['page_unique'] ) {
	throw new RuntimeException( 'A historical variation URL did not resolve to its current catalog item.' );
}
if ( 1 !== count( $current_result ) || '30670' !== $current_result[0]['page_unique'] ) {
	throw new RuntimeException( 'An exact current variation URL did not resolve.' );
}
if ( array() !== $wrong_result ) {
	throw new RuntimeException( 'A different variation attribute was matched incorrectly.' );
}
if ( ! TVES_Torob_V3_Catalog::urls_match( $old_url, $current_url ) ) {
	throw new RuntimeException( 'Equivalent historical and current URLs were not recognized.' );
}
if ( TVES_Torob_V3_Catalog::urls_match( $wrong_url, $current_url ) ) {
	throw new RuntimeException( 'Different variation attributes were treated as equivalent.' );
}

echo "catalog-url-lookup-test: ok\n";
