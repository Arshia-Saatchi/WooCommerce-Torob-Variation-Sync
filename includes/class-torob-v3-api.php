<?php
/**
 * Official Torob Product API v3 REST controller.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Torob_V3_API {
	private TVES_Torob_V3_Catalog $catalog;
	private TVES_Torob_JWT_Validator $jwt;

	public function __construct( TVES_Torob_V3_Catalog $catalog, TVES_Torob_JWT_Validator $jwt ) {
		$this->catalog = $catalog;
		$this->jwt     = $jwt;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'torob/v3',
			'/products',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Authenticate and serve one of the three request modes defined by Torob. */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		if ( 'yes' !== (string) TVES_Admin_Settings::get_setting( 'v3_enabled', 'yes' ) ) {
			return $this->error( __( 'Torob API v3 is disabled in the plugin settings.', 'torob-variable-exporter' ), 403 );
		}
		if ( '1' !== trim( (string) $request->get_header( 'X-Torob-Token-Version' ) ) ) {
			return $this->auth_error( __( 'The X-Torob-Token-Version header must be 1.', 'torob-variable-exporter' ) );
		}
		$token = trim( (string) $request->get_header( 'X-Torob-Token' ) );
		if ( '' === $token ) {
			return $this->auth_error( __( 'The X-Torob-Token header is required.', 'torob-variable-exporter' ) );
		}
		$accepted_audiences = TVES_Torob_JWT_Validator::accepted_audiences();
		$request_host       = TVES_Torob_JWT_Validator::request_host( $request );
		if ( '' === $request_host || ! in_array( $request_host, $accepted_audiences, true ) ) {
			return $this->auth_error(
				__( 'The API request host is not an accepted host for this shop.', 'torob-variable-exporter' ),
				401,
				array( 'request_host' => $request_host, 'accepted_audiences' => $accepted_audiences )
			);
		}
		$validation = $this->jwt->validate( $token, $accepted_audiences );
		if ( is_wp_error( $validation ) ) {
			$context = $validation->get_error_data();
			$context = is_array( $context ) ? $context : array();
			$context['request_host'] = $request_host;
			return $this->auth_error( $validation->get_error_message(), 'tves_v3_sodium_missing' === $validation->get_error_code() ? 500 : 401, $context );
		}

		$content_type = strtolower( (string) $request->get_header( 'Content-Type' ) );
		if ( false === strpos( $content_type, 'application/json' ) ) {
			return $this->error( __( 'Content-Type must be application/json.', 'torob-variable-exporter' ), 400 );
		}
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return $this->error( __( 'The request body must contain valid JSON.', 'torob-variable-exporter' ), 400 );
		}

		$mode = $this->validate_body( $body );
		if ( is_wp_error( $mode ) ) {
			return $this->error( $mode->get_error_message(), 400 );
		}
		$stats = TVES_Torob_V3_Catalog::get_stats();
		if ( ! $stats['ready'] ) {
			return $this->error( __( 'The Torob API v3 catalog is not ready. Run a complete feed synchronization first.', 'torob-variable-exporter' ), 503 );
		}

		if ( 'page' === $mode ) {
			$page   = (int) $body['page'];
			$result = $this->catalog->get_page( $page, (string) $body['sort'] );
		} elseif ( 'page_urls' === $mode ) {
			$page   = 1;
			$items  = array_map( 'strval', $body['page_urls'] );
			$found  = $this->catalog->find_by_urls( $items );
			$result = array( 'products' => $found, 'total' => count( $found ), 'max_pages' => 1 );
		} else {
			$page   = 1;
			$items  = array_map( 'strval', $body['page_uniques'] );
			$found  = $this->catalog->find_by_uniques( $items );
			$result = array( 'products' => $found, 'total' => count( $found ), 'max_pages' => 1 );
		}

		update_option( 'tves_v3_last_access', time(), false );
		TVES_Logger::log(
			'success',
			__( 'Torob Product API v3 request completed.', 'torob-variable-exporter' ),
			0,
			0,
			array(
				'mode'         => $mode,
				'page'         => $page,
				'returned'     => count( $result['products'] ),
				'request_host' => $request_host,
				'audience'     => $validation['aud'] ?? '',
			)
		);

		$response = new WP_REST_Response(
			array(
				'api_version'  => 'torob_api_v3',
				'current_page' => $page,
				'total'        => (int) $result['total'],
				'max_pages'    => (int) $result['max_pages'],
				'products'     => array_values( $result['products'] ),
			),
			200
		);
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		return $response;
	}

	/** Require exactly one supported lookup/page request shape. */
	private function validate_body( array $body ) {
		$keys = array_keys( $body );
		sort( $keys );
		if ( array( 'page', 'sort' ) === $keys ) {
			if ( ! is_int( $body['page'] ) || $body['page'] < 1 || ! in_array( $body['sort'], array( 'date_added_desc', 'date_updated_desc' ), true ) ) {
				return new WP_Error( 'tves_v3_invalid_page', __( 'page must be a positive integer and sort must be date_added_desc or date_updated_desc.', 'torob-variable-exporter' ) );
			}
			return 'page';
		}
		foreach ( array( 'page_urls', 'page_uniques' ) as $lookup_key ) {
			if ( array( $lookup_key ) !== $keys || ! is_array( $body[ $lookup_key ] ) || ! $body[ $lookup_key ] ) {
				continue;
			}
			foreach ( $body[ $lookup_key ] as $value ) {
				if ( ! is_string( $value ) || '' === trim( $value ) || ( 'page_urls' === $lookup_key && ( strlen( $value ) > 1500 || ! wp_http_validate_url( $value ) ) ) || ( 'page_uniques' === $lookup_key && strlen( $value ) > 200 ) ) {
					return new WP_Error( 'tves_v3_invalid_lookup', __( 'The lookup list contains an invalid URL or identifier.', 'torob-variable-exporter' ) );
				}
			}
			return $lookup_key;
		}
		return new WP_Error( 'tves_v3_invalid_body', __( 'Send exactly one supported body: page and sort, page_urls, or page_uniques.', 'torob-variable-exporter' ) );
	}

	private function auth_error( string $message, int $status = 401, array $context = array() ): WP_REST_Response {
		$key = 'tves_v3_auth_log_' . substr( md5( $message ), 0, 12 );
		if ( ! get_transient( $key ) ) {
			set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );
			TVES_Logger::log( 'api_error', $message, 0, 0, array_merge( array( 'endpoint' => 'torob/v3/products' ), $context ) );
		}
		return $this->error( $message, $status );
	}

	private function error( string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response( array( 'error' => $message ), $status );
	}
}
