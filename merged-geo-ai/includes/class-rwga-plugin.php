<?php
/**
 * Geo AI — satellite plugin (requires Geo Core).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main controller for ReactWoo Geo AI.
 */
class RWGA_Plugin {

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

		require_once RWGA_PATH . 'includes/class-rwga-license-state.php';
		require_once RWGA_PATH . 'includes/class-rwga-platform-client.php';
		require_once RWGA_PATH . 'includes/class-rwga-settings.php';
		require_once RWGA_PATH . 'includes/partials-rwga-automation-rule-fields.php';
		RWGA_Settings::init();
		RWGA_Platform_Client::register_update_check_warm_hooks();

		require_once RWGA_PATH . 'includes/class-rwga-updates-diagnostics.php';
		RWGA_Updates_Diagnostics::init();

		add_action( 'init', array( __CLASS__, 'register_satellite_updater' ), 2 );

		$this->load_workflow_engine();

		require_once RWGA_PATH . 'includes/class-rwga-cron.php';
		RWGA_Cron::init();

		require_once RWGA_PATH . 'includes/class-rwga-connection.php';
		require_once RWGA_PATH . 'includes/class-rwga-stats.php';
		require_once RWGA_PATH . 'includes/class-rwga-license-introspection.php';
		require_once RWGA_PATH . 'includes/class-rwga-usage.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-usage-presenter.php';
		require_once RWGA_PATH . 'includes/class-rwga-drafts.php';
		require_once RWGA_PATH . 'includes/class-rwga-admin.php';
		require_once RWGA_PATH . 'includes/class-rwga-block-editor.php';
		RWGA_Admin::init();
		RWGA_Block_Editor::init();

		/**
		 * Fires when Geo AI satellite is ready (Geo Core is active).
		 */
		do_action( 'rwga_loaded' );
	}

	/**
	 * Boot Geo AI inside merged Geo Optimise (no standalone menus/updater).
	 *
	 * @return void
	 */
	public function boot_embedded() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		if ( ! $this->is_geo_core_active() ) {
			return;
		}

		require_once RWGA_PATH . 'includes/class-rwga-license-state.php';
		require_once RWGA_PATH . 'includes/class-rwga-platform-client.php';
		require_once RWGA_PATH . 'includes/class-rwga-settings.php';
		require_once RWGA_PATH . 'includes/partials-rwga-automation-rule-fields.php';
		RWGA_Settings::init();
		RWGA_Platform_Client::register_update_check_warm_hooks();

		require_once RWGA_PATH . 'includes/class-rwga-updates-diagnostics.php';
		RWGA_Updates_Diagnostics::init();

		$this->load_workflow_engine();

		require_once RWGA_PATH . 'includes/class-rwga-cron.php';
		RWGA_Cron::init();

		require_once RWGA_PATH . 'includes/class-rwga-connection.php';
		require_once RWGA_PATH . 'includes/class-rwga-stats.php';
		require_once RWGA_PATH . 'includes/class-rwga-license-introspection.php';
		require_once RWGA_PATH . 'includes/class-rwga-usage.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-usage-presenter.php';
		require_once RWGA_PATH . 'includes/class-rwga-drafts.php';
		require_once RWGA_PATH . 'includes/class-rwga-admin.php';
		require_once RWGA_PATH . 'includes/class-rwga-block-editor.php';
		RWGA_Admin::init();
		RWGA_Block_Editor::init();

		do_action( 'rwga_loaded' );
	}

	/**
	 * @return void
	 */
	public static function register_satellite_updater() {
		if ( ! class_exists( 'RWGC_Satellite_Updater', false ) ) {
			return;
		}
		RWGC_Satellite_Updater::register(
			array(
				'basename'              => plugin_basename( RWGA_FILE ),
				'version'               => RWGA_VERSION,
				'catalog_slug'          => 'reactwoo-geo-ai',
				'name'                  => __( 'ReactWoo Geo AI', 'reactwoo-geo-ai' ),
				'description'           => __( 'AI-assisted geo variant drafts using the ReactWoo API with Geo Core.', 'reactwoo-geo-ai' ),
				'get_bearer_callback'   => array( 'RWGA_Platform_Client', 'get_bearer_for_updates' ),
				'get_api_base_callback' => array( 'RWGA_Platform_Client', 'get_api_base' ),
			)
		);
	}

	/**
	 * DB, capabilities, workflows, REST.
	 *
	 * @return void
	 */
	public function load_workflow_engine() {
		require_once RWGA_PATH . 'includes/helpers/rwga-site.php';
		require_once RWGA_PATH . 'includes/helpers/rwga-builder-text.php';
		require_once RWGA_PATH . 'includes/builders/class-rwga-builder-loader.php';
		RWGA_Builder_Loader::load();
		require_once RWGA_PATH . 'includes/class-rwga-platform-client.php';
		require_once RWGA_PATH . 'includes/class-rwga-settings.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db.php';
		require_once RWGA_PATH . 'includes/class-rwga-install.php';
		RWGA_Install::maybe_upgrade();

		require_once RWGA_PATH . 'includes/class-rwga-capabilities.php';
		RWGA_Capabilities::install();

		require_once RWGA_PATH . 'includes/class-rwga-license.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-analysis-runs.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-analysis-findings.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-recommendations.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-implementation-drafts.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-intelligence-actions.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-local-intelligence.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-competitor-research.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-automation-rules.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-memory-service.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-automation-runner.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-current-workflow.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-report-formatter.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-journey-router.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-implementation-router.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-engine.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-remote-client.php';
		require_once RWGA_PATH . 'includes/services/generation/interface-rwga-generation-transport.php';
		require_once RWGA_PATH . 'includes/services/generation/class-rwga-workflow-prompt-spec-registry.php';
		require_once RWGA_PATH . 'includes/services/generation/class-rwga-prompt-context-formatter.php';
		require_once RWGA_PATH . 'includes/services/generation/class-rwga-local-ai-transport.php';
		require_once RWGA_PATH . 'includes/services/generation/class-rwga-managed-ai-transport.php';
		require_once RWGA_PATH . 'includes/services/generation/class-rwga-wordpress-ai-transport.php';
		require_once RWGA_PATH . 'includes/services/generation/class-rwga-generation-router.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-intelligence-response.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-ux-opportunity-action-filter.php';
		require_once RWGA_PATH . 'includes/class-rwga-ux-reviewer-ui.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-intelligence-action-applier.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-ai-usage-guard.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-site-snapshot-client.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-intelligence-cloud-client.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-intelligence-optimise-handoff.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-site-intelligence-sync.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-intelligence-sync-service.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-context-resolver.php';
		require_once RWGA_PATH . 'includes/intelligence/class-rwga-intelligence-bundle-bootstrap.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-compound-condition-interpreter.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-page-reference-resolver.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-variant-group-extractor.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-original-source-targeting-extractor.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-country-rule-interpreter.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-segment-condition-extractor.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-weather-rule-interpreter.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-phrase-shape-normaliser.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-interpretation-memory-store.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-interpretation-memory-client.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-interpretation-memory-matcher.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-interpreter-debug.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-interpretation-status.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-inferred-plan-builder.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-site-interpretation-preferences.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-ambiguity-detector.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-ai-interpretation-builder.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-ambiguity-gate.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-parser-hints-service.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-learning-promotion-service.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-variant-plan-parser.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-rule-plan-parser.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-geo-action-types.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-location-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-action-clause-splitter.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-action-type-detector.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-target-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-confirmation-instruction-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-region-ambiguity-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-condition-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-variant-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-parent-variant-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-condition-polarity-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-inherited-target-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-campaign-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-url-condition-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-narrative-clause-splitter.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-second-version-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-synced-entity-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-audience-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-utm-condition-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-plan-validator.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-ordinal-variant-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-resolve-clarifications.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-confirmation-builder.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-target-registry-resolver.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-action-card-builder.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-learned-patterns.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-ai-fallback.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-planner-legacy-adapter.php';
		require_once RWGA_PATH . 'includes/services/planner/class-rwga-geo-assistant-planner.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-variant-plan-interpreter.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-multi-variant-interpreter.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-proposal-store.php';
		require_once RWGA_PATH . 'includes/services/executor/class-rwga-card-resolution-applier.php';
		require_once RWGA_PATH . 'includes/services/executor/class-rwga-plan-condition-converter.php';
		require_once RWGA_PATH . 'includes/services/executor/class-rwga-plan-executor.php';
		require_once RWGA_PATH . 'includes/services/executor/class-rwga-assistant-executor-bridge.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-assistant-service.php';
		RWGA_Assistant_Executor_Bridge::register();
		require_once RWGA_PATH . 'includes/services/class-rwga-local-intent-interpreter.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-learning-event-service.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-site-intelligence-journey.php';
		require_once RWGA_PATH . 'includes/analyzers/class-rwga-messaging-analyzer.php';
		require_once RWGA_PATH . 'includes/analyzers/class-rwga-ux-insight-builder.php';
		require_once RWGA_PATH . 'includes/analyzers/class-rwga-visual-analyzer.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-payload-guard.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-context-builder.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-relationship-graph.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-knowledge-graph.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-model-router.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-insight-memory.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-local-intelligence.php';
		require_once RWGA_PATH . 'includes/class-rwga-ai-snapshot.php';
		RWGA_Knowledge_Graph::init();
		RWGA_AI_Snapshot::init();
		require_once RWGA_PATH . 'includes/class-rwga-insights-provider.php';
		RWGA_Insights_Provider::init();
		RWGA_Site_Intelligence_Sync::init();
		RWGA_Intelligence_Sync_Service::init();
		RWGA_Learning_Event_Service::init();
		RWGA_Site_Intelligence_Journey::init();
		RWGA_Local_Intelligence::init();
		require_once RWGA_PATH . 'includes/services/class-rwga-page-context.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-targeting-context-bridge.php';
		require_once RWGA_PATH . 'includes/workflows/interface-rwga-workflow.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-base.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-ux-analysis.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-ux-recommend.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-ux-opportunity-review.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-copy-implement.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-seo-implement.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-competitor-research.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-intelligence.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-intelligence-definitions.php';
		require_once RWGA_PATH . 'includes/class-rwga-workflow-registry.php';
		add_action( 'init', array( 'RWGA_Workflow_Registry', 'init' ), 2 );

		require_once RWGA_PATH . 'includes/class-rwga-agent-registry.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-weather-facet-suggester.php';
		require_once RWGA_PATH . 'includes/integrations/class-rwga-commerce-weather.php';
		RWGA_Commerce_Weather::init();
		require_once RWGA_PATH . 'includes/services/class-rwga-weather-catalog-audit.php';
		RWGA_Weather_Catalog_Audit::init();
		require_once RWGA_PATH . 'includes/api/class-rwga-rest.php';
		RWGA_REST::init();
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
		echo esc_html__( 'ReactWoo Geo AI requires ReactWoo Geo Core to be installed and active.', 'reactwoo-geo-ai' );
		echo '</p></div>';
	}
}
