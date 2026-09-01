<?php
/**
 * Plugin Name: Torob Variable Product Exporter
 * Description: Exports WooCommerce simple products and individual variations through a Torob-ready REST feed.
 * Version:     1.3.0
 * Author:      ARSHIA
 * Text Domain: torob-variable-exporter
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 *
 * @package TorobVariableExporter
 */

defined( 'ABSPATH' ) || exit;

define( 'TVES_VERSION', '1.3.0' );
define( 'TVES_FILE', __FILE__ );
define( 'TVES_PATH', plugin_dir_path( __FILE__ ) );
define( 'TVES_URL', plugin_dir_url( __FILE__ ) );

require_once TVES_PATH . 'includes/class-logger.php';
require_once TVES_PATH . 'includes/class-exclusion-manager.php';
require_once TVES_PATH . 'includes/class-variation-handler.php';
require_once TVES_PATH . 'includes/class-product-handler.php';
require_once TVES_PATH . 'includes/class-feed-generator.php';
require_once TVES_PATH . 'includes/class-torob-v3-catalog.php';
require_once TVES_PATH . 'includes/class-torob-v3-product-mapper.php';
require_once TVES_PATH . 'includes/class-sync-manager.php';
require_once TVES_PATH . 'includes/class-torob-api.php';
require_once TVES_PATH . 'includes/class-admin-settings.php';
require_once TVES_PATH . 'includes/class-torob-jwt-validator.php';
require_once TVES_PATH . 'includes/class-torob-v3-api.php';

/**
 * Main plugin container.
 */
final class TVES_Plugin {
	/** @var TVES_Plugin|null */
	private static ?TVES_Plugin $instance = null;

	/** @var TVES_Sync_Manager|null */
	private ?TVES_Sync_Manager $sync_manager = null;

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): TVES_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register bootstrap hooks.
	 */
	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Declare WooCommerce feature compatibility.
	 */
	public function declare_compatibility(): void {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TVES_FILE, true );
		}
	}

	/**
	 * Initialize services once WooCommerce is loaded.
	 */
	public function init(): void {
		load_plugin_textdomain( 'torob-variable-exporter', false, dirname( plugin_basename( TVES_FILE ) ) . '/languages' );
		if ( TVES_VERSION !== (string) get_option( 'tves_db_version', '' ) ) {
			TVES_Logger::install();
			TVES_Torob_V3_Catalog::install();
			update_option( 'tves_db_version', TVES_VERSION, false );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_required_notice' ) );
			return;
		}

		$exclusions        = new TVES_Exclusion_Manager();
		$variation_handler = new TVES_Variation_Handler();
		$product_handler   = new TVES_Product_Handler();
		$feed_generator    = new TVES_Feed_Generator( $product_handler, $variation_handler, $exclusions );
		$v3_catalog        = new TVES_Torob_V3_Catalog();
		$v3_mapper         = new TVES_Torob_V3_Product_Mapper();
		$this->sync_manager = new TVES_Sync_Manager( $feed_generator, $v3_catalog, $v3_mapper );

		new TVES_Torob_API( $feed_generator );
		new TVES_Torob_V3_API( $v3_catalog, new TVES_Torob_JWT_Validator() );
		new TVES_Admin_Settings( $this->sync_manager );

		add_action( 'save_post_product', array( $this, 'invalidate_feed' ), 10, 3 );
		add_action( 'woocommerce_update_product', array( $this, 'invalidate_feed' ) );
	}

	/**
	 * Invalidate cached pages after product changes.
	 */
	public function invalidate_feed(): void {
		TVES_Feed_Generator::bump_cache_generation();
	}

	/**
	 * Show dependency notice.
	 */
	public function woocommerce_required_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'Torob Variable Product Exporter requires WooCommerce to be installed and active.', 'torob-variable-exporter' ) . '</p></div>';
	}
}

/**
 * Activation routine.
 */
function tves_activate(): void {
	TVES_Logger::install();
	TVES_Torob_V3_Catalog::install();
	update_option( 'tves_db_version', TVES_VERSION, false );
	TVES_Sync_Manager::register_schedules();
	TVES_Sync_Manager::reschedule( (string) TVES_Admin_Settings::get_setting( 'sync_interval', 'manual' ) );
	flush_rewrite_rules();
}

/**
 * Deactivation routine.
 */
function tves_deactivate(): void {
	TVES_Sync_Manager::clear_schedules();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'tves_activate' );
register_deactivation_hook( __FILE__, 'tves_deactivate' );

TVES_Plugin::instance();
