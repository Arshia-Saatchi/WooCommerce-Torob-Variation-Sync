<?php
/**
 * Batched WP-Cron synchronization manager.
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

class TVES_Sync_Manager {
	public const CRON_HOOK  = 'tves_sync_event';
	public const BATCH_HOOK = 'tves_sync_batch_event';
	private const LOCK_KEY  = 'tves_sync_lock';
	private const STATE_KEY = 'tves_sync_state';
	private const BATCH_SIZE = 25;

	private TVES_Feed_Generator $feed_generator;
	private ?TVES_Torob_V3_Catalog $v3_catalog;
	private ?TVES_Torob_V3_Product_Mapper $v3_mapper;

	public function __construct( TVES_Feed_Generator $feed_generator, ?TVES_Torob_V3_Catalog $v3_catalog = null, ?TVES_Torob_V3_Product_Mapper $v3_mapper = null ) {
		$this->feed_generator = $feed_generator;
		$this->v3_catalog     = $v3_catalog;
		$this->v3_mapper      = $v3_mapper;
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
		add_action( self::CRON_HOOK, array( $this, 'start_sync' ) );
		add_action( self::BATCH_HOOK, array( $this, 'process_batch' ) );
	}

	/**
	 * Make the six-hour schedule available before activation scheduling.
	 */
	public static function register_schedules(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
	}

	/**
	 * Add custom recurrence.
	 */
	public static function add_cron_schedules( array $schedules ): array {
		$schedules['tves_six_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 hours (Torob)', 'torob-variable-exporter' ),
		);
		return $schedules;
	}

	/**
	 * Schedule the selected recurring sync.
	 */
	public static function reschedule( string $interval ): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		$map = array(
			'hourly'    => 'hourly',
			'six_hours' => 'tves_six_hours',
			'daily'     => 'daily',
		);

		if ( isset( $map[ $interval ] ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $map[ $interval ], self::CRON_HOOK );
		}
	}

	/**
	 * Clear recurring and pending batch jobs.
	 */
	public static function clear_schedules(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::BATCH_HOOK );
		delete_transient( self::LOCK_KEY );
	}

	/**
	 * Start a new cache build.
	 *
	 * @return true|WP_Error
	 */
	public function start_sync( bool $force = false ) {
		if ( get_transient( self::LOCK_KEY ) && ! $force ) {
			return new WP_Error( 'tves_sync_running', __( 'A synchronization is already running.', 'torob-variable-exporter' ) );
		}

		if ( $force ) {
			wp_clear_scheduled_hook( self::BATCH_HOOK );
		}

		$state = array(
			'version'        => wp_generate_uuid4(),
			'page'           => 1,
			'per_page'       => self::BATCH_SIZE,
			'started_at'     => time(),
			'processed'      => 0,
			'total'          => 0,
			'exported_items' => 0,
			'v3_items'       => 0,
		);
		set_transient( self::LOCK_KEY, 1, HOUR_IN_SECONDS );
		update_option( self::STATE_KEY, $state, false );
		TVES_Logger::log( 'success', __( 'Feed synchronization started.', 'torob-variable-exporter' ) );
		$this->process_batch();

		return true;
	}

	/**
	 * Build one bounded page and queue the next page if required.
	 */
	public function process_batch(): void {
		$state = get_option( self::STATE_KEY, array() );
		if ( ! is_array( $state ) || empty( $state['version'] ) || empty( $state['page'] ) ) {
			delete_transient( self::LOCK_KEY );
			return;
		}

		set_transient( self::LOCK_KEY, 1, HOUR_IN_SECONDS );

		try {
			$result      = $this->feed_generator->get_page( (int) $state['page'], (int) $state['per_page'], false, true, (string) $state['version'] );
			$total_pages = (int) ( $result['pagination']['total_pages'] ?? 1 );
			$total        = (int) ( $result['pagination']['total_source_products'] ?? 0 );
			$processed    = min( $total, (int) $state['page'] * (int) $state['per_page'] );
			$v3_items     = 0;

			if ( $this->v3_catalog && $this->v3_mapper ) {
				foreach ( (array) ( $result['products'] ?? array() ) as $item ) {
					$mapped = $this->v3_mapper->map( (array) $item );
					if ( $mapped && $this->v3_catalog->upsert( $mapped, (string) $state['version'] ) ) {
						++$v3_items;
					} elseif ( ! $mapped ) {
						TVES_Logger::log( 'warning', __( 'A synchronized item could not be mapped to the Torob API v3 schema.', 'torob-variable-exporter' ), absint( $item['parent_id'] ?? $item['id'] ?? 0 ), 'variation' === ( $item['type'] ?? '' ) ? absint( $item['id'] ?? 0 ) : 0 );
					} else {
						throw new RuntimeException( __( 'A Torob API v3 catalog item could not be saved to the database.', 'torob-variable-exporter' ) );
					}
				}
			}

			$state['total']          = $total;
			$state['processed']      = $processed;
			$state['exported_items'] = (int) ( $state['exported_items'] ?? 0 ) + count( (array) ( $result['products'] ?? array() ) );
			$state['v3_items']       = (int) ( $state['v3_items'] ?? 0 ) + $v3_items;
			update_option( self::STATE_KEY, $state, false );

			if ( (int) $state['page'] < $total_pages ) {
				++$state['page'];
				update_option( self::STATE_KEY, $state, false );
				wp_schedule_single_event( time() + 10, self::BATCH_HOOK );
				return;
			}

			TVES_Feed_Generator::activate_cache_generation( (string) $state['version'] );
			if ( $this->v3_catalog ) {
				$this->v3_catalog->activate_generation( (string) $state['version'] );
			}
			update_option( 'tves_last_sync', time(), false );
			update_option(
				'tves_last_sync_meta',
				array(
					'pages'          => $total_pages,
					'per_page'       => (int) $state['per_page'],
					'duration'       => time() - (int) $state['started_at'],
					'total'          => $total,
					'processed'      => $processed,
					'exported_items' => (int) $state['exported_items'],
					'v3_items'       => (int) $state['v3_items'],
				),
				false
			);
			delete_option( self::STATE_KEY );
			delete_transient( self::LOCK_KEY );
			TVES_Logger::prune();
			TVES_Logger::log( 'success', __( 'Feed synchronization completed.', 'torob-variable-exporter' ) );
		} catch ( Throwable $exception ) {
			if ( $this->v3_catalog && ! empty( $state['version'] ) ) {
				$this->v3_catalog->discard_generation( (string) $state['version'] );
			}
			delete_option( self::STATE_KEY );
			delete_transient( self::LOCK_KEY );
			TVES_Logger::log( 'error', $exception->getMessage(), 0, 0, array( 'exception' => get_class( $exception ) ) );
		}
	}

	/**
	 * Get UI status timestamps.
	 *
	 * @return array{last: int, next: int, running: bool, processed: int, total: int, exported_items: int, percent: int}
	 */
	public static function get_status(): array {
		$running = (bool) get_transient( self::LOCK_KEY );
		$source  = $running ? (array) get_option( self::STATE_KEY, array() ) : (array) get_option( 'tves_last_sync_meta', array() );
		$total   = max( 0, (int) ( $source['total'] ?? 0 ) );
		$done    = min( $total, max( 0, (int) ( $source['processed'] ?? 0 ) ) );
		$has_completed_sync = (bool) get_option( 'tves_last_sync', 0 );
		$percent            = $total > 0 ? min( 100, (int) floor( ( $done / $total ) * 100 ) ) : ( $running || ! $has_completed_sync ? 0 : 100 );

		return array(
			'last'           => (int) get_option( 'tves_last_sync', 0 ),
			'next'           => (int) wp_next_scheduled( self::CRON_HOOK ),
			'running'        => $running,
			'processed'      => $done,
			'total'          => $total,
			'exported_items' => max( 0, (int) ( $source['exported_items'] ?? 0 ) ),
			'percent'        => $percent,
		);
	}
}
