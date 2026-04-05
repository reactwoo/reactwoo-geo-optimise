<?php
/**
 * Geo Optimise — satellite plugin (requires Geo Core).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main controller for ReactWoo Geo Optimise.
 */
class RWGO_Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public function boot() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'maybe_admin_notice_missing_core' ) );
		}

		if ( ! $this->is_geo_core_active() ) {
			return;
		}

		require_once RWGO_PATH . 'includes/class-rwgo-settings.php';
		RWGO_Settings::register_platform_filters();
		RWGO_Settings::maybe_migrate_from_geo_core();
		RWGO_Settings::init();

		require_once RWGO_PATH . 'includes/class-rwgo-stats.php';
		require_once RWGO_PATH . 'includes/class-rwgo-assignment.php';
		require_once RWGO_PATH . 'includes/functions-rwgo.php';
		require_once RWGO_PATH . 'includes/class-rwgo-core-event-bridge.php';
		require_once RWGO_PATH . 'includes/class-rwgo-events.php';
		require_once RWGO_PATH . 'includes/class-rwgo-experiment-cpt.php';
		require_once RWGO_PATH . 'includes/class-rwgo-experiment-repository.php';
		require_once RWGO_PATH . 'includes/class-rwgo-experiment-service.php';
		require_once RWGO_PATH . 'includes/class-rwgo-builder-detector.php';
		require_once RWGO_PATH . 'includes/class-rwgo-goal-service.php';
		require_once RWGO_PATH . 'includes/class-rwgo-winner-service.php';
		require_once RWGO_PATH . 'includes/class-rwgo-woocommerce-goals.php';
		require_once RWGO_PATH . 'includes/class-rwgo-db-schema.php';
		require_once RWGO_PATH . 'includes/class-rwgo-redirect-store.php';
		require_once RWGO_PATH . 'includes/class-rwgo-promotion-log.php';
		require_once RWGO_PATH . 'includes/class-rwgo-promotion-slug-scaffold.php';
		require_once RWGO_PATH . 'includes/class-rwgo-promotion-service.php';
		require_once RWGO_PATH . 'includes/class-rwgo-page-duplicator.php';
		require_once RWGO_PATH . 'includes/class-rwgo-variant-lifecycle.php';
		if ( class_exists( 'RWGO_DB_Schema', false ) ) {
			$v = get_option( RWGO_DB_Schema::VERSION_OPTION, '' );
			if ( version_compare( (string) $v, RWGO_DB_Schema::VERSION, '<' ) ) {
				RWGO_DB_Schema::activate();
			}
		}
		RWGO_Redirect_Store::init();
		require_once RWGO_PATH . 'includes/class-rwgo-admin-content-catalog.php';
		require_once RWGO_PATH . 'includes/class-rwgo-targeting.php';
		require_once RWGO_PATH . 'includes/class-rwgo-event-payload.php';
		require_once RWGO_PATH . 'includes/class-rwgo-gtm-handoff.php';
		require_once RWGO_PATH . 'includes/class-rwgo-event-store.php';
		require_once RWGO_PATH . 'includes/class-rwgo-goal-registry.php';
		require_once RWGO_PATH . 'includes/class-rwgo-defined-goal-service.php';
		require_once RWGO_PATH . 'includes/class-rwgo-rest-tracking.php';
		require_once RWGO_PATH . 'includes/class-rwgo-rest-defined-goals.php';
		require_once RWGO_PATH . 'includes/class-rwgo-elementor-goals.php';
		require_once RWGO_PATH . 'includes/class-rwgo-gutenberg-goals.php';
		require_once RWGO_PATH . 'includes/class-rwgo-page-goal-meta.php';
		require_once RWGO_PATH . 'includes/class-rwgo-elementor-page-goal.php';
		require_once RWGO_PATH . 'includes/class-rwgo-page-swapper.php';
		require_once RWGO_PATH . 'includes/class-rwgo-runtime.php';
		require_once RWGO_PATH . 'includes/class-rwgo-admin-wizard.php';
		require_once RWGO_PATH . 'includes/class-rwgo-admin.php';
		RWGO_Experiment_CPT::init();
		RWGO_Event_Store::init();
		RWGO_REST_Tracking::init();
		RWGO_REST_Defined_Goals::init();
		RWGO_Elementor_Goals::init();
		RWGO_Gutenberg_Goals::init();
		RWGO_Page_Goal_Meta::init();
		RWGO_Elementor_Page_Goal::init();
		RWGO_Runtime::init();
		RWGO_Admin_Wizard::init();
		RWGO_Core_Event_Bridge::init();
		RWGO_Events::init();
		RWGO_Admin::init();

		add_action(
			'plugins_loaded',
			static function () {
				if ( class_exists( 'WooCommerce', false ) ) {
					RWGO_WooCommerce_Goals::register();
				}
			},
			20
		);

		if ( class_exists( 'RWGC_Satellite_Updater', false ) ) {
			RWGC_Satellite_Updater::register(
				array(
					'basename'     => plugin_basename( RWGO_FILE ),
					'version'      => RWGO_VERSION,
					'catalog_slug' => 'reactwoo-geo-optimise',
					'name'         => __( 'ReactWoo Geo Optimise', 'reactwoo-geo-optimise' ),
					'description'  => __( 'Experiments and optimisation on top of ReactWoo Geo Core.', 'reactwoo-geo-optimise' ),
				)
			);
		}

		/**
		 * Fires when Geo Optimise is ready (Geo Core is active).
		 */
		do_action( 'rwgo_loaded' );
	}

	/**
	 * @return bool
	 */
	private function is_geo_core_active() {
		if ( function_exists( 'rwgc_is_geo_core_active' ) ) {
			return (bool) rwgc_is_geo_core_active();
		}
		return class_exists( 'RWGC_Plugin', false )
			|| ( defined( 'RWGC_VERSION' ) && defined( 'RWGC_FILE' ) );
	}

	/**
	 * @return void
	 */
	public function maybe_admin_notice_missing_core() {
		if ( $this->is_geo_core_active() ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'ReactWoo Geo Optimise requires ReactWoo Geo Core to be installed and active.', 'reactwoo-geo-optimise' );
		echo '</p></div>';
	}
}
