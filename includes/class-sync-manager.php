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
	private const BATCH_LOCK_KEY = 'tves_sync_batch_lock';
	private const BATCH_SIZE = 15;
	private const BATCH_LOCK_TTL = 5 * MINUTE_IN_SECONDS;
	private const STALL_TIMEOUT = 90;
	private const MAX_BATCH_RETRIES = 3;

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
		add_action( 'init', array( $this, 'maybe_recover_stalled_sync' ), 20 );
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
		delete_option( self::BATCH_LOCK_KEY );
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
			'last_activity'  => time(),
			'retry_count'    => 0,
			'total_retries'  => 0,
			'recovery_count' => 0,
		);
		set_transient( self::LOCK_KEY, 1, HOUR_IN_SECONDS );
		update_option( self::STATE_KEY, $state, false );
		TVES_Logger::log( 'success', __( 'Feed synchronization started.', 'torob-variable-exporter' ) );
		$this->process_batch( (string) $state['version'] );

		return true;
	}

	/**
	 * Build one bounded page and queue the next page if required.
	 */
	public function process_batch( string $scheduled_generation = '' ): void {
		$state = get_option( self::STATE_KEY, array() );
		if ( ! is_array( $state ) || empty( $state['version'] ) || empty( $state['page'] ) ) {
			delete_transient( self::LOCK_KEY );
			return;
		}
		if ( '' !== $scheduled_generation && ! hash_equals( (string) $state['version'], $scheduled_generation ) ) {
			return;
		}

		$batch_lock = $this->acquire_batch_lock();
		if ( '' === $batch_lock ) {
			return;
		}

		set_transient( self::LOCK_KEY, 1, HOUR_IN_SECONDS );
		$state['last_activity'] = time();
		update_option( self::STATE_KEY, $state, false );

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

			$current_state = (array) get_option( self::STATE_KEY, array() );
			if ( empty( $current_state['version'] ) || ! hash_equals( (string) $state['version'], (string) $current_state['version'] ) ) {
				return;
			}

			$state['total']          = $total;
			$state['processed']      = $processed;
			$state['exported_items'] = (int) ( $state['exported_items'] ?? 0 ) + count( (array) ( $result['products'] ?? array() ) );
			$state['v3_items']       = (int) ( $state['v3_items'] ?? 0 ) + $v3_items;
			$state['last_activity']  = time();
			$state['retry_count']    = 0;
			update_option( self::STATE_KEY, $state, false );

			if ( (int) $state['page'] < $total_pages ) {
				++$state['page'];
				update_option( self::STATE_KEY, $state, false );
				if ( ! $this->queue_batch( (string) $state['version'], 10 ) ) {
					TVES_Logger::log( 'warning', __( 'The next synchronization batch could not be scheduled. The watchdog will retry it automatically.', 'torob-variable-exporter' ), 0, 0, array( 'page' => (int) $state['page'], 'generation' => (string) $state['version'] ) );
				}
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
					'last_activity'  => time(),
					'total_retries'  => (int) ( $state['total_retries'] ?? 0 ),
					'recovery_count' => (int) ( $state['recovery_count'] ?? 0 ),
				),
				false
			);
			delete_option( self::STATE_KEY );
			delete_transient( self::LOCK_KEY );
			TVES_Logger::prune();
			TVES_Logger::log( 'success', __( 'Feed synchronization completed.', 'torob-variable-exporter' ) );
		} catch ( Throwable $exception ) {
			$this->handle_batch_failure( $state, $exception );
		} finally {
			$this->release_batch_lock( $batch_lock );
		}
	}

	/** Requeue a sync whose one-shot cron event was lost or remained overdue. */
	public function maybe_recover_stalled_sync(): void {
		if ( wp_doing_cron() ) {
			return;
		}

		$state = (array) get_option( self::STATE_KEY, array() );
		if ( empty( $state['version'] ) || empty( $state['page'] ) ) {
			return;
		}

		set_transient( self::LOCK_KEY, 1, HOUR_IN_SECONDS );
		$now           = time();
		$last_activity = max( (int) ( $state['last_activity'] ?? 0 ), (int) ( $state['watchdog_at'] ?? 0 ), (int) ( $state['started_at'] ?? 0 ) );
		if ( $last_activity > $now - self::STALL_TIMEOUT ) {
			return;
		}

		$args       = array( (string) $state['version'] );
		$next_batch = wp_next_scheduled( self::BATCH_HOOK, $args );
		if ( $next_batch && $next_batch >= $now - self::STALL_TIMEOUT ) {
			return;
		}
		if ( $next_batch ) {
			wp_unschedule_event( $next_batch, self::BATCH_HOOK, $args );
		}

		if ( $this->queue_batch( (string) $state['version'], 0 ) ) {
			$state['watchdog_at']   = $now;
			$state['recovery_count'] = (int) ( $state['recovery_count'] ?? 0 ) + 1;
			update_option( self::STATE_KEY, $state, false );
			TVES_Logger::log( 'warning', __( 'A stalled feed synchronization was detected and automatically resumed.', 'torob-variable-exporter' ), 0, 0, array( 'page' => (int) $state['page'], 'generation' => (string) $state['version'], 'recovery_count' => (int) $state['recovery_count'] ) );
		}
	}

	/** Queue one generation-specific batch and report scheduling failures. */
	private function queue_batch( string $generation, int $delay ): bool {
		$args = array( $generation );
		if ( wp_next_scheduled( self::BATCH_HOOK, $args ) ) {
			return true;
		}
		$result = wp_schedule_single_event( time() + max( 0, $delay ), self::BATCH_HOOK, $args, true );
		return true === $result;
	}

	/** Acquire a crash-tolerant mutex so duplicate cron runners cannot process one page concurrently. */
	private function acquire_batch_lock(): string {
		$now      = time();
		$existing = (array) get_option( self::BATCH_LOCK_KEY, array() );
		if ( ! empty( $existing['expires'] ) && (int) $existing['expires'] > $now ) {
			return '';
		}
		if ( $existing ) {
			delete_option( self::BATCH_LOCK_KEY );
		}

		$token = wp_generate_uuid4();
		$added = add_option(
			self::BATCH_LOCK_KEY,
			array( 'token' => $token, 'expires' => $now + self::BATCH_LOCK_TTL ),
			'',
			false
		);
		return $added ? $token : '';
	}

	/** Release only the mutex owned by this runner. */
	private function release_batch_lock( string $token ): void {
		$existing = (array) get_option( self::BATCH_LOCK_KEY, array() );
		if ( '' !== $token && isset( $existing['token'] ) && hash_equals( $token, (string) $existing['token'] ) ) {
			delete_option( self::BATCH_LOCK_KEY );
		}
	}

	/** Retry recoverable batch failures and preserve the previous active catalog. */
	private function handle_batch_failure( array $state, Throwable $exception ): void {
		$current_state = (array) get_option( self::STATE_KEY, array() );
		if ( empty( $current_state['version'] ) || empty( $state['version'] ) || ! hash_equals( (string) $state['version'], (string) $current_state['version'] ) ) {
			return;
		}

		$retry_count = (int) ( $current_state['retry_count'] ?? 0 ) + 1;
		if ( $retry_count <= self::MAX_BATCH_RETRIES ) {
			$current_state['retry_count']   = $retry_count;
			$current_state['total_retries'] = (int) ( $current_state['total_retries'] ?? 0 ) + 1;
			$current_state['last_activity'] = time();
			$current_state['last_error']    = sanitize_text_field( $exception->getMessage() );
			update_option( self::STATE_KEY, $current_state, false );
			$this->queue_batch( (string) $current_state['version'], 30 );
			TVES_Logger::log( 'warning', __( 'A synchronization batch failed and was scheduled for an automatic retry.', 'torob-variable-exporter' ), 0, 0, array( 'page' => (int) $current_state['page'], 'retry' => $retry_count, 'exception' => get_class( $exception ), 'message' => $exception->getMessage() ) );
			return;
		}

		if ( $this->v3_catalog ) {
			$this->v3_catalog->discard_generation( (string) $current_state['version'] );
		}
		delete_option( self::STATE_KEY );
		delete_transient( self::LOCK_KEY );
		TVES_Logger::log( 'error', $exception->getMessage(), 0, 0, array( 'exception' => get_class( $exception ), 'retries' => $retry_count ) );
	}

	/**
	 * Get UI status timestamps.
	 *
	 * @return array{last: int, next: int, running: bool, processed: int, total: int, exported_items: int, percent: int, last_activity: int, total_retries: int, recovery_count: int}
	 */
	public static function get_status(): array {
		$active_state = (array) get_option( self::STATE_KEY, array() );
		$running      = ! empty( $active_state['version'] ) && ! empty( $active_state['page'] );
		$source       = $running ? $active_state : (array) get_option( 'tves_last_sync_meta', array() );
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
			'last_activity'  => max( 0, (int) ( $source['last_activity'] ?? 0 ) ),
			'total_retries'  => max( 0, (int) ( $source['total_retries'] ?? 0 ) ),
			'recovery_count' => max( 0, (int) ( $source['recovery_count'] ?? 0 ) ),
		);
	}
}
