<?php
/**
 * Dependency-free validation for Torob's Ed25519-signed JWT access tokens.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Torob_JWT_Validator {
	private const PUBLIC_KEY_DER = 'MCowBQYDK2VwAyEAt6Mu4T0pBORY11W+QeM35UsmLO3vsf+6yKpFDEImFk0=';

	/** Validate signature, time claims, algorithm and audience. */
	public function validate( string $jwt, string $expected_audience ) {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return new WP_Error( 'tves_v3_sodium_missing', __( 'The PHP Sodium extension is required to validate Torob API tokens.', 'torob-variable-exporter' ) );
		}

		$parts = explode( '.', trim( $jwt ) );
		if ( 3 !== count( $parts ) ) {
			return $this->invalid_token();
		}

		$header_json  = $this->base64url_decode( $parts[0] );
		$payload_json = $this->base64url_decode( $parts[1] );
		$signature    = $this->base64url_decode( $parts[2] );
		$header       = json_decode( $header_json, true );
		$payload      = json_decode( $payload_json, true );
		$der          = base64_decode( self::PUBLIC_KEY_DER, true );
		$public_key   = false !== $der ? substr( $der, -SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ) : '';

		if ( ! is_array( $header ) || ! is_array( $payload ) || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $signature ) || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $public_key ) ) {
			return $this->invalid_token();
		}
		if ( 'EdDSA' !== (string) ( $header['alg'] ?? '' ) ) {
			return $this->invalid_token();
		}
		if ( ! sodium_crypto_sign_verify_detached( $signature, $parts[0] . '.' . $parts[1], $public_key ) ) {
			return $this->invalid_token();
		}

		$now = time();
		if ( ! isset( $payload['exp'], $payload['nbf'], $payload['aud'] ) || ! is_numeric( $payload['exp'] ) || ! is_numeric( $payload['nbf'] ) ) {
			return $this->invalid_token();
		}
		if ( $now > (int) $payload['exp'] || $now < (int) $payload['nbf'] ) {
			return new WP_Error( 'tves_v3_token_time', __( 'The Torob API token is expired or not active yet.', 'torob-variable-exporter' ) );
		}

		$audiences = is_array( $payload['aud'] ) ? array_map( 'strval', $payload['aud'] ) : array( (string) $payload['aud'] );
		if ( ! in_array( $expected_audience, $audiences, true ) ) {
			return new WP_Error( 'tves_v3_token_audience', __( 'The Torob API token audience does not match this shop host.', 'torob-variable-exporter' ) );
		}

		return $payload;
	}

	/** Canonical audience host used by Torob, with a non-standard port if present. */
	public static function expected_audience( ?WP_REST_Request $request = null ): string {
		if ( $request ) {
			$host = strtolower( trim( (string) $request->get_header( 'Host' ) ) );
			if ( preg_match( '/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)(?::[0-9]{1,5})?$/', $host ) ) {
				return $host;
			}
		}
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$port = (int) wp_parse_url( home_url( '/' ), PHP_URL_PORT );
		return strtolower( $host . ( $port > 0 && ! in_array( $port, array( 80, 443 ), true ) ? ':' . $port : '' ) );
	}

	private function base64url_decode( string $value ): string {
		$value   = strtr( $value, '-_', '+/' );
		$padding = strlen( $value ) % 4;
		if ( $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}
		$decoded = base64_decode( $value, true );
		return false === $decoded ? '' : $decoded;
	}

	private function invalid_token(): WP_Error {
		return new WP_Error( 'tves_v3_invalid_token', __( 'The Torob API token is invalid.', 'torob-variable-exporter' ) );
	}
}
