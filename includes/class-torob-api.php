<?php
/**
 * Torob REST API controller.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Torob_API {
	private TVES_Feed_Generator $feed_generator;

	public function __construct( TVES_Feed_Generator $feed_generator ) {
		$this->feed_generator = $feed_generator;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the public, read-only marketplace feed.
	 */
	public function register_routes(): void {
		register_rest_route(
			'torob/v1',
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ) && (int) $value >= 1,
					),
					'per_page' => array(
						'default'           => 25,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 100,
					),
				),
			)
		);
	}

	/**
	 * Keep the feed public by default; optionally require an access token header.
	 */
	public function permissions_check( WP_REST_Request $request ) {
		$token = (string) TVES_Admin_Settings::get_setting( 'api_token', '' );
		if ( '' === $token ) {
			return true;
		}

		$provided = (string) $request->get_header( 'X-Torob-Token' );
		return '' !== $provided && hash_equals( $token, $provided )
			? true
			: new WP_Error( 'tves_forbidden', __( 'A valid Torob feed token is required.', 'torob-variable-exporter' ), array( 'status' => 401 ) );
	}

	/**
	 * Return one feed page.
	 */
	public function get_products( WP_REST_Request $request ) {
		try {
			$data     = $this->feed_generator->get_page( (int) $request['page'], (int) $request['per_page'], true );
			$response = rest_ensure_response( $data );
			$response->header( 'Cache-Control', 'public, max-age=300' );
			$response->header( 'X-WP-Total', (string) $data['pagination']['total_source_products'] );
			$response->header( 'X-WP-TotalPages', (string) $data['pagination']['total_pages'] );
			return $response;
		} catch ( Throwable $exception ) {
			TVES_Logger::log( 'api_error', $exception->getMessage(), 0, 0, array( 'exception' => get_class( $exception ) ) );
			return new WP_Error( 'tves_feed_error', __( 'The Torob feed could not be generated.', 'torob-variable-exporter' ), array( 'status' => 500 ) );
		}
	}
}
