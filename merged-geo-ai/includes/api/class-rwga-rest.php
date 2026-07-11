<?php
/**
 * WordPress REST API for bounded Geo AI workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers geo-ai/v1 routes.
 */
class RWGA_REST {

	const NS = 'geo-ai/v1';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/analyse/ux',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_analyse_ux' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/agents',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_agents' ),
				'permission_callback' => array( __CLASS__, 'permission_read_agents' ),
			)
		);

		register_rest_route(
			self::NS,
			'/analyses',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_analyses_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/analyses/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_analysis_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/recommend/ux',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_recommend_ux' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/recommendations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_recommendations_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'              => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'          => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'analysis_run_id'   => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/recommendations/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_recommendation_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/implement/copy',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_implement_copy' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/implement/seo',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_implement_seo' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/implementation-drafts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_implementation_drafts_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'               => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'           => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'recommendation_id'  => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'workflow_key'       => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/implementation-drafts/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_implementation_draft_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/research/competitors',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_research_competitors' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/competitor-research',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_competitor_research_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page_id'  => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/competitor-research/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_competitor_research_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_automation_rules_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_automation_rules_create' ),
				'permission_callback' => array( __CLASS__, 'permission_manage_automations' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_automation_rule_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules/(?P<id>\\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'handle_automation_rule_patch' ),
				'permission_callback' => array( __CLASS__, 'permission_manage_automations' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules/(?P<id>\\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'handle_automation_rule_delete' ),
				'permission_callback' => array( __CLASS__, 'permission_manage_automations' ),
			)
		);

		register_rest_route(
			self::NS,
			'/automation/rules/(?P<id>\\d+)/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_automation_rule_run' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/actions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_intelligence_actions_list' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page'         => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'     => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'workflow_key' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'status'       => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/actions/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_intelligence_action_get' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/actions/(?P<id>\\d+)/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_intelligence_action_apply' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/actions/(?P<id>\\d+)/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_intelligence_action_dismiss' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/site',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_site' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/page/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_page' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/insights',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_insights' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'entity_type' => array(
						'default'           => 'page',
						'sanitize_callback' => 'sanitize_key',
					),
					'entity_id'   => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/runs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_runs' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'limit' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/ux/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_ux' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/messaging/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_messaging' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/visual/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_visual' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/context/(?P<workflow>[a-z0-9_]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_context' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'page_id' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/graph',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_graph' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/knowledge',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_knowledge' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
				'args'                => array(
					'industry'  => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'page_type' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'region'    => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_id'   => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/semantics/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_semantics' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/local/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_local_intelligence_refresh' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(
					'page_id' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/interpret/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_assistant_preview' ),
				'permission_callback' => array( __CLASS__, 'permission_assistant' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context' => array(
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/interpret',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_assistant_interpret' ),
				'permission_callback' => array( __CLASS__, 'permission_assistant' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context' => array(
						'default' => array(),
					),
					'debug'   => array(
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/interpret/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_assistant_execute' ),
				'permission_callback' => array( __CLASS__, 'permission_assistant' ),
				'args'                => array(
					'proposal_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/interpret/confirm-split',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_assistant_confirm_split' ),
				'permission_callback' => array( __CLASS__, 'permission_assistant' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'inferred_plan' => array(
						'required' => true,
						'type'     => 'object',
					),
					'source' => array(
						'default'           => 'local_parser',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'context' => array(
						'default' => array(),
					),
					'debug'   => array(
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/interpret/confirm-interpretation',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_assistant_confirm_interpretation' ),
				'permission_callback' => array( __CLASS__, 'permission_assistant' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'ambiguities' => array(
						'default' => array(),
					),
					'resolutions' => array(
						'default' => array(),
					),
					'ai_interpretation' => array(
						'default' => array(),
					),
					'base' => array(
						'default' => array(),
					),
					'source' => array(
						'default'           => 'local_parser',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'context' => array(
						'default' => array(),
					),
					'debug'   => array(
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/command/bundle',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_command_intelligence_bundle' ),
				'permission_callback' => array( __CLASS__, 'permission_view_reports' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/command/interpret',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_command_intelligence_interpret' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
				'args'                => array(
					'phrase' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'context' => array(
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/command/learning-event',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_command_intelligence_learning_event' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
			)
		);

		register_rest_route(
			self::NS,
			'/intelligence/command/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_command_intelligence_sync' ),
				'permission_callback' => array( __CLASS__, 'permission_run_ai' ),
			)
		);

		register_rest_route(
			self::NS,
			'/products/(?P<id>\\d+)/suggest-weather-facets',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_suggest_weather_facets' ),
				'permission_callback' => array( __CLASS__, 'permission_edit_products' ),
			)
		);
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_run_ai() {
		if ( ! RWGA_Capabilities::current_user_can_run_ai() ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		if ( ! RWGA_License::can_run_workflows() ) {
			return new WP_Error( 'rwga_unlicensed', __( 'Geo AI license not configured.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Targeting assistant: editors who can manage pages (no cloud license required).
	 *
	 * @return bool
	 */
	public static function permission_assistant() {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_read_agents() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_view_reports() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_manage_automations() {
		if ( ! RWGA_Capabilities::current_user_can_manage_automations() && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission to manage automations.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @return bool|\WP_Error
	 */
	public static function permission_edit_products() {
		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'rwga_forbidden', __( 'Insufficient permission to edit products.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * POST /geo-ai/v1/products/{id}/suggest-weather-facets
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_suggest_weather_facets( $request ) {
		if ( ! class_exists( 'RWGA_Weather_Facet_Suggester', false ) ) {
			require_once RWGA_PATH . 'includes/services/class-rwga-weather-facet-suggester.php';
		}
		$pid = absint( $request['id'] );
		if ( $pid <= 0 || ! current_user_can( 'edit_post', $pid ) ) {
			return new WP_Error( 'rwga_forbidden', __( 'Cannot edit this product.', 'reactwoo-geo-ai' ), array( 'status' => 403 ) );
		}
		$result = RWGA_Weather_Facet_Suggester::suggest_for_product( $pid );
		return rest_ensure_response( $result );
	}

	/**
	 * POST /geo-ai/v1/analyse/ux
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_analyse_ux( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'ux_analysis' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/agents
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_agents() {
		return rest_ensure_response( array( 'agents' => RWGA_Agent_Registry::all() ) );
	}

	/**
	 * GET /geo-ai/v1/analyses
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_analyses_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$total    = RWGA_DB_Analysis_Runs::count_rows();
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$rows     = RWGA_DB_Analysis_Runs::list_paged( $per_page, $page );

		return rest_ensure_response(
			array(
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
				'per_page' => $per_page,
				'runs'     => $rows,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/analyses/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_analysis_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid analysis id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$run = RWGA_DB_Analysis_Runs::get( $id );
		if ( ! is_array( $run ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Analysis not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		$findings = RWGA_DB_Analysis_Findings::list_for_run( $id );

		return rest_ensure_response(
			array(
				'run'      => $run,
				'findings' => $findings,
			)
		);
	}

	/**
	 * POST /geo-ai/v1/recommend/ux
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_recommend_ux( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'ux_recommend' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/recommendations
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_recommendations_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$filter   = max( 0, (int) $request->get_param( 'analysis_run_id' ) );

		$total = RWGA_DB_Recommendations::count_rows( $filter );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$rows  = RWGA_DB_Recommendations::list_paged( $per_page, $page, $filter );

		return rest_ensure_response(
			array(
				'total'             => $total,
				'pages'             => $pages,
				'page'              => $page,
				'per_page'          => $per_page,
				'analysis_run_id'   => $filter,
				'recommendations'   => $rows,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/recommendations/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_recommendation_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid recommendation id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$row = RWGA_DB_Recommendations::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Recommendation not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'recommendation' => $row ) );
	}

	/**
	 * POST /geo-ai/v1/implement/copy
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_implement_copy( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'copy_implement' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * POST /geo-ai/v1/implement/seo
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_implement_seo( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'seo_implement' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/implementation-drafts
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_implementation_drafts_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$filter   = max( 0, (int) $request->get_param( 'recommendation_id' ) );
		$wk       = sanitize_key( (string) $request->get_param( 'workflow_key' ) );

		$total = RWGA_DB_Implementation_Drafts::count_rows( $filter, $wk );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$rows  = RWGA_DB_Implementation_Drafts::list_paged( $per_page, $page, $filter, $wk );

		return rest_ensure_response(
			array(
				'total'             => $total,
				'pages'             => $pages,
				'page'              => $page,
				'per_page'          => $per_page,
				'recommendation_id' => $filter,
				'workflow_key'      => $wk,
				'drafts'            => array_map( array( __CLASS__, 'decode_implementation_draft_row' ), $rows ),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/implementation-drafts/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_implementation_draft_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid draft id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$row = RWGA_DB_Implementation_Drafts::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Implementation draft not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'draft' => self::decode_implementation_draft_row( $row ),
			)
		);
	}

	/**
	 * JSON-decode payload fields for API consumers.
	 *
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function decode_implementation_draft_row( array $row ) {
		foreach ( array( 'draft_payload', 'diff_payload' ) as $k ) {
			if ( ! isset( $row[ $k ] ) || ! is_string( $row[ $k ] ) || '' === $row[ $k ] ) {
				continue;
			}
			$dec = json_decode( $row[ $k ], true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $dec ) ) {
				$row[ $k ] = $dec;
			}
		}
		return $row;
	}

	/**
	 * POST /geo-ai/v1/research/competitors
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_research_competitors( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$wf = RWGA_Workflow_Registry::get( 'competitor_research' );
		if ( ! $wf ) {
			return new WP_Error( 'rwga_no_workflow', __( 'Workflow not registered.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$out = $wf->execute( $input );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/competitor-research
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_competitor_research_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$filter   = max( 0, (int) $request->get_param( 'page_id' ) );

		$total = RWGA_DB_Competitor_Research::count_rows( $filter );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$rows  = RWGA_DB_Competitor_Research::list_paged( $per_page, $page, $filter );

		return rest_ensure_response(
			array(
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
				'per_page' => $per_page,
				'page_id'  => $filter,
				'items'    => $rows,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/competitor-research/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_competitor_research_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$row = RWGA_DB_Competitor_Research::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Competitor research not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'item' => $row ) );
	}

	/**
	 * GET /geo-ai/v1/automation/rules
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_automation_rules_list( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$total    = RWGA_DB_Automation_Rules::count_rows();
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$rows     = RWGA_DB_Automation_Rules::list_paged( $per_page, $page );

		return rest_ensure_response(
			array(
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
				'per_page' => $per_page,
				'rules'    => array_map( array( __CLASS__, 'decode_automation_rule_row' ), $rows ),
			)
		);
	}

	/**
	 * POST /geo-ai/v1/automation/rules
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rules_create( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$name = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'rwga_bad_input', __( 'name is required.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$wk = isset( $input['workflow_key'] ) ? sanitize_key( (string) $input['workflow_key'] ) : '';
		if ( '' === $wk ) {
			return new WP_Error( 'rwga_bad_input', __( 'workflow_key is required.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$id = RWGA_DB_Automation_Rules::insert(
			array(
				'name'          => $name,
				'workflow_key'  => $wk,
				'trigger_type'  => isset( $input['trigger_type'] ) ? sanitize_key( (string) $input['trigger_type'] ) : 'manual',
				'target_scope'  => isset( $input['target_scope'] ) ? sanitize_key( (string) $input['target_scope'] ) : 'site',
				'page_id'       => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
				'geo_target'    => isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '',
				'rule_config'   => isset( $input['rule_config'] ) && is_array( $input['rule_config'] ) ? $input['rule_config'] : array(),
				'status'        => isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'active',
				'created_by'    => get_current_user_id(),
			)
		);

		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_persist', __( 'Could not create rule.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$row = RWGA_DB_Automation_Rules::get( $id );
		return rest_ensure_response(
			array(
				'rule' => is_array( $row ) ? self::decode_automation_rule_row( $row ) : null,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/automation/rules/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rule_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$row = RWGA_DB_Automation_Rules::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Rule not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'rule' => self::decode_automation_rule_row( $row ) ) );
	}

	/**
	 * PATCH /geo-ai/v1/automation/rules/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rule_patch( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$ok = RWGA_DB_Automation_Rules::update_rule(
			$id,
			array(
				'name'          => isset( $input['name'] ) ? (string) $input['name'] : '',
				'workflow_key'  => isset( $input['workflow_key'] ) ? (string) $input['workflow_key'] : '',
				'trigger_type'  => isset( $input['trigger_type'] ) ? (string) $input['trigger_type'] : 'manual',
				'target_scope'  => isset( $input['target_scope'] ) ? (string) $input['target_scope'] : 'site',
				'page_id'       => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
				'geo_target'    => isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '',
				'rule_config'   => isset( $input['rule_config'] ) && is_array( $input['rule_config'] ) ? $input['rule_config'] : array(),
				'status'        => isset( $input['status'] ) ? (string) $input['status'] : 'active',
			)
		);

		if ( ! $ok ) {
			return new WP_Error( 'rwga_persist', __( 'Could not update rule.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$row = RWGA_DB_Automation_Rules::get( $id );
		return rest_ensure_response( array( 'rule' => is_array( $row ) ? self::decode_automation_rule_row( $row ) : null ) );
	}

	/**
	 * DELETE /geo-ai/v1/automation/rules/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rule_delete( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$ok = RWGA_DB_Automation_Rules::delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'rwga_not_found', __( 'Rule not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * POST /geo-ai/v1/automation/rules/(?P<id>\\d+)/run
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_automation_rule_run( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$out = RWGA_Automation_Runner::run( $id );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function decode_automation_rule_row( array $row ) {
		if ( isset( $row['rule_config'] ) && is_string( $row['rule_config'] ) && '' !== $row['rule_config'] ) {
			$dec = json_decode( $row['rule_config'], true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $dec ) ) {
				$row['rule_config'] = $dec;
			}
		}
		return $row;
	}

	/**
	 * GET /geo-ai/v1/intelligence/actions
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_intelligence_actions_list( $request ) {
		$page         = max( 1, (int) $request->get_param( 'page' ) );
		$per_page     = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$workflow_key = (string) $request->get_param( 'workflow_key' );
		$status       = (string) $request->get_param( 'status' );
		$filters      = array();
		if ( '' !== $status ) {
			$filters['status'] = $status;
		}
		$total = RWGA_DB_Intelligence_Actions::count_rows( $workflow_key, $filters );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$rows  = RWGA_DB_Intelligence_Actions::list_paged( $per_page, $page, $workflow_key, $filters );

		return rest_ensure_response(
			array(
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
				'per_page' => $per_page,
				'actions'  => array_map( array( __CLASS__, 'decode_intelligence_action_row' ), $rows ),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/actions/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_intelligence_action_get( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid action id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		$row = RWGA_DB_Intelligence_Actions::get( $id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Action not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'action' => self::decode_intelligence_action_row( $row ) ) );
	}

	/**
	 * POST /geo-ai/v1/intelligence/actions/(?P<id>\\d+)/apply
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_intelligence_action_apply( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid action id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		$out = RWGA_Intelligence_Action_Applier::apply( $id );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		return rest_ensure_response( $out );
	}

	/**
	 * POST /geo-ai/v1/intelligence/actions/(?P<id>\\d+)/dismiss
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_intelligence_action_dismiss( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid action id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		$out = RWGA_Intelligence_Action_Applier::dismiss( $id );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		return rest_ensure_response( $out );
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/site
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_local_intelligence_site() {
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return rest_ensure_response( array( 'site_context' => null ) );
		}
		$row = RWGA_Local_Intelligence::get_site_context();
		return rest_ensure_response(
			array(
				'site_context'        => is_array( $row ) ? self::decode_local_intelligence_row( $row ) : null,
				'relationship_graph'  => RWGA_Local_Intelligence::get_relationship_graph(),
				'version'             => RWGA_Local_Intelligence::VERSION,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/context/(?P<workflow>[a-z0-9_]+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_local_intelligence_context( $request ) {
		$workflow = isset( $request['workflow'] ) ? sanitize_key( (string) $request['workflow'] ) : '';
		if ( '' === $workflow || ! class_exists( 'RWGA_Context_Builder', false ) ) {
			return new WP_Error( 'rwga_bad_workflow', __( 'Invalid workflow key.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		$page_id = max( 0, (int) $request->get_param( 'page_id' ) );
		$bundle  = RWGA_Context_Builder::build(
			$workflow,
			array(
				'page_id'         => $page_id,
				'geo_target'      => (string) $request->get_param( 'geo_target' ),
				'analysis_focus'  => (string) $request->get_param( 'analysis_focus' ),
				'user_request'    => (string) $request->get_param( 'user_request' ),
				'variant_page_id' => (int) $request->get_param( 'variant_page_id' ),
			)
		);
		return rest_ensure_response(
			array(
				'workflow'      => $workflow,
				'page_id'       => $page_id,
				'context'       => $bundle,
				'remote_ready'  => RWGA_Context_Builder::for_remote_api( $bundle ),
				'context_version' => RWGA_Context_Builder::VERSION,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/graph
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_local_intelligence_graph() {
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return rest_ensure_response( array( 'relationship_graph' => array() ) );
		}
		$graph = RWGA_Local_Intelligence::get_relationship_graph();
		if ( array() === $graph && class_exists( 'RWGA_Relationship_Graph', false ) ) {
			$graph = RWGA_Relationship_Graph::refresh();
		}
		return rest_ensure_response(
			array(
				'relationship_graph' => $graph,
				'compact'            => class_exists( 'RWGA_Relationship_Graph', false )
					? RWGA_Relationship_Graph::compact_for_api( $graph )
					: array(),
				'version'            => RWGA_Local_Intelligence::VERSION,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/knowledge
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_local_intelligence_knowledge( $request ) {
		$args = array(
			'industry'  => isset( $request['industry'] ) ? (string) $request['industry'] : '',
			'page_type' => isset( $request['page_type'] ) ? (string) $request['page_type'] : '',
			'region'    => isset( $request['region'] ) ? (string) $request['region'] : '',
			'page_id'   => isset( $request['page_id'] ) ? (int) $request['page_id'] : 0,
		);
		$context = class_exists( 'RWGA_Knowledge_Graph', false )
			? RWGA_Knowledge_Graph::benchmark_context( $args )
			: array();
		return rest_ensure_response(
			array(
				'knowledge' => $context,
				'version'   => class_exists( 'RWGA_Knowledge_Graph', false ) ? RWGA_Knowledge_Graph::VERSION : '',
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/page/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_local_intelligence_page( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid page id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return rest_ensure_response( array( 'page_context' => null, 'insights' => array() ) );
		}
		$row = RWGA_Local_Intelligence::get_page_context( $id );
		$decoded = is_array( $row ) ? self::decode_local_intelligence_row( $row ) : null;
		$messaging = class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::get_page_messaging( $id ) : array();
		$ux_intel  = class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::get_page_ux_intelligence( $id ) : array();
		$visual    = class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::get_page_visual_intelligence( $id ) : array();
		$semantics = class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::get_page_builder_semantics( $id ) : array();

		return rest_ensure_response(
			array(
				'page_context'         => $decoded,
				'messaging'            => $messaging,
				'ux_intelligence'      => $ux_intel,
				'visual_intelligence'  => $visual,
				'builder_semantics'    => $semantics,
				'insights'             => array_map( array( __CLASS__, 'decode_local_intelligence_row' ), RWGA_Local_Intelligence::get_page_insights( $id ) ),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/ux/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_local_intelligence_ux( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid page id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return rest_ensure_response( array( 'ux_intelligence' => array() ) );
		}
		return rest_ensure_response(
			array(
				'page_id'         => $id,
				'ux_intelligence' => RWGA_Local_Intelligence::get_page_ux_intelligence( $id ),
				'insights'        => array_values(
					array_filter(
						RWGA_Local_Intelligence::get_page_insights( $id ),
						static function ( $row ) {
							return is_array( $row ) && isset( $row['source'] ) && 'ux_insight_builder' === (string) $row['source'];
						}
					)
				),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/semantics/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_local_intelligence_semantics( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid page id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return rest_ensure_response( array( 'builder_semantics' => array() ) );
		}
		return rest_ensure_response(
			array(
				'page_id'           => $id,
				'builder_semantics' => RWGA_Local_Intelligence::get_page_builder_semantics( $id ),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/visual/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_local_intelligence_visual( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid page id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return rest_ensure_response( array( 'visual_intelligence' => array() ) );
		}
		return rest_ensure_response(
			array(
				'page_id'             => $id,
				'visual_intelligence' => RWGA_Local_Intelligence::get_page_visual_intelligence( $id ),
				'insights'            => array_values(
					array_filter(
						RWGA_Local_Intelligence::get_page_insights( $id ),
						static function ( $row ) {
							return is_array( $row ) && isset( $row['source'] ) && 'visual_analyzer' === (string) $row['source'];
						}
					)
				),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/messaging/(?P<id>\\d+)
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_local_intelligence_messaging( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid page id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return rest_ensure_response( array( 'messaging' => array() ) );
		}
		return rest_ensure_response(
			array(
				'page_id'   => $id,
				'messaging' => RWGA_Local_Intelligence::get_page_messaging( $id ),
				'insights'  => array_values(
					array_filter(
						RWGA_Local_Intelligence::get_page_insights( $id ),
						static function ( $row ) {
							return is_array( $row ) && isset( $row['source'] ) && 'messaging_analyzer' === (string) $row['source'];
						}
					)
				),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/insights
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_local_intelligence_insights( $request ) {
		$entity_type = sanitize_key( (string) $request->get_param( 'entity_type' ) );
		$entity_id   = max( 0, (int) $request->get_param( 'entity_id' ) );
		if ( '' === $entity_type ) {
			$entity_type = 'page';
		}
		$rows = class_exists( 'RWGA_DB_UX_Insights', false )
			? RWGA_DB_UX_Insights::list_for_entity( $entity_type, $entity_id, 50 )
			: array();
		return rest_ensure_response(
			array(
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'insights'    => array_map( array( __CLASS__, 'decode_local_intelligence_row' ), $rows ),
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/local/runs
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_local_intelligence_runs( $request ) {
		$limit = max( 1, min( 100, (int) $request->get_param( 'limit' ) ) );
		$rows  = class_exists( 'RWGA_DB_AI_Runs', false ) ? RWGA_DB_AI_Runs::list_recent( $limit ) : array();
		return rest_ensure_response(
			array(
				'runs' => array_map(
					static function ( $row ) {
						if ( is_array( $row ) ) {
							$row['cache_hit'] = ! empty( $row['cache_hit'] );
						}
						return $row;
					},
					$rows
				),
			)
		);
	}

	/**
	 * POST /geo-ai/v1/intelligence/local/refresh
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_local_intelligence_refresh( $request ) {
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Local intelligence layer is not available.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$page_id = max( 0, (int) $request->get_param( 'page_id' ) );
		$result  = RWGA_Local_Intelligence::refresh_all( $page_id );
		return rest_ensure_response(
			array(
				'refreshed' => $result,
				'version'   => RWGA_Local_Intelligence::VERSION,
			)
		);
	}

	/**
	 * GET /geo-ai/v1/intelligence/command/bundle
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_command_intelligence_bundle() {
		if ( ! class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Intelligence sync service unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$bundle = RWGA_Intelligence_Sync_Service::get_local_bundle();
		$status = RWGA_Intelligence_Sync_Service::get_status();
		return rest_ensure_response(
			array(
				'status' => $status,
				'bundle' => is_array( $bundle ) ? $bundle : null,
			)
		);
	}

	/**
	 * POST /geo-ai/v1/intelligence/command/interpret
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_command_intelligence_interpret( $request ) {
		if ( ! class_exists( 'RWGA_Local_Intent_Interpreter', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Local interpreter unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$phrase  = (string) $request->get_param( 'phrase' );
		$context = $request->get_param( 'context' );
		if ( ! is_array( $context ) ) {
			$context = array();
		}
		if ( class_exists( 'RWGA_Context_Resolver', false ) ) {
			$context = RWGA_Context_Resolver::resolve( $context );
		}
		$result = RWGA_Local_Intent_Interpreter::interpret( $phrase, $context );
		return rest_ensure_response( $result );
	}

	/**
	 * POST /geo-ai/v1/intelligence/command/learning-event
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_command_intelligence_learning_event( $request ) {
		if ( ! class_exists( 'RWGA_Learning_Event_Service', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Learning event service unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		$result = RWGA_Learning_Event_Service::record( $payload );
		return rest_ensure_response( $result );
	}

	/**
	 * POST /geo-ai/v1/intelligence/command/sync
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_command_intelligence_sync() {
		if ( ! class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Intelligence sync service unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$result = RWGA_Intelligence_Sync_Service::sync( true );
		return rest_ensure_response( $result );
	}

	/**
	 * POST /geo-ai/v1/interpret/preview
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_assistant_preview( $request ) {
		if ( ! class_exists( 'RWGA_Assistant_Service', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Assistant service unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$message = (string) $request->get_param( 'message' );
		$context = $request->get_param( 'context' );
		if ( ! is_array( $context ) ) {
			$context = array();
		}
		$context['screen'] = 'targeting_assistant';
		return rest_ensure_response( RWGA_Assistant_Service::preview( $message, $context ) );
	}

	/**
	 * POST /geo-ai/v1/interpret
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_assistant_interpret( $request ) {
		if ( ! class_exists( 'RWGA_Assistant_Service', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Assistant service unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$message = (string) $request->get_param( 'message' );
		$context = $request->get_param( 'context' );
		if ( ! is_array( $context ) ) {
			$context = array();
		}
		$context['screen'] = 'targeting_assistant';
		$debug             = rest_sanitize_boolean( $request->get_param( 'debug' ) );
		if ( ! empty( $context['force_ai'] ) && class_exists( 'RWGA_Local_Intent_Interpreter', false ) ) {
			$raw = RWGA_Local_Intent_Interpreter::interpret_with_ai_fallback( $message, $context );
			if ( ! empty( $raw['matched_action'] ) ) {
				return rest_ensure_response( RWGA_Assistant_Service::interpret_from_raw( $message, $raw, $context, $debug ) );
			}
		}
		return rest_ensure_response( RWGA_Assistant_Service::interpret( $message, $context, $debug ) );
	}

	/**
	 * POST /geo-ai/v1/interpret/confirm-split
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_assistant_confirm_split( $request ) {
		if ( ! class_exists( 'RWGA_Assistant_Service', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Assistant service unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$message = (string) $request->get_param( 'message' );
		$plan    = $request->get_param( 'inferred_plan' );
		if ( ! is_array( $plan ) ) {
			return new WP_Error( 'rwga_invalid_plan', __( 'Inferred plan is required.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}
		$context = $request->get_param( 'context' );
		if ( ! is_array( $context ) ) {
			$context = array();
		}
		$context['screen'] = 'targeting_assistant';
		$source            = (string) $request->get_param( 'source' );
		$debug             = rest_sanitize_boolean( $request->get_param( 'debug' ) );
		return rest_ensure_response(
			RWGA_Assistant_Service::confirm_inferred_split( $message, $plan, $context, $source, $debug )
		);
	}

	/**
	 * POST /geo-ai/v1/interpret/confirm-interpretation
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_assistant_confirm_interpretation( $request ) {
		if ( ! class_exists( 'RWGA_Assistant_Service', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Assistant service unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$message = (string) $request->get_param( 'message' );
		$payload = array(
			'ambiguities'       => $request->get_param( 'ambiguities' ),
			'resolutions'       => $request->get_param( 'resolutions' ),
			'ai_interpretation' => $request->get_param( 'ai_interpretation' ),
			'base'              => $request->get_param( 'base' ),
		);
		foreach ( array( 'ambiguities', 'resolutions', 'ai_interpretation', 'base' ) as $key ) {
			if ( ! is_array( $payload[ $key ] ) ) {
				$payload[ $key ] = array();
			}
		}
		$context = $request->get_param( 'context' );
		if ( ! is_array( $context ) ) {
			$context = array();
		}
		$context['screen'] = 'targeting_assistant';
		$source            = (string) $request->get_param( 'source' );
		$debug             = rest_sanitize_boolean( $request->get_param( 'debug' ) );
		return rest_ensure_response(
			RWGA_Assistant_Service::confirm_interpretation( $message, $payload, $context, $source, $debug )
		);
	}

	/**
	 * POST /geo-ai/v1/interpret/execute
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_assistant_execute( $request ) {
		if ( ! class_exists( 'RWGA_Assistant_Service', false ) ) {
			return new WP_Error( 'rwga_not_loaded', __( 'Assistant service unavailable.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}
		$id          = (string) $request->get_param( 'proposal_id' );
		$resolutions = $request->get_param( 'resolutions' );
		if ( ! is_array( $resolutions ) ) {
			$resolutions = array();
		}
		$result = RWGA_Assistant_Service::execute( $id, $resolutions );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function decode_local_intelligence_row( array $row ) {
		foreach ( array( 'context_json', 'installed_satellites', 'scores_json', 'evidence_json' ) as $key ) {
			if ( ! isset( $row[ $key ] ) || ! is_string( $row[ $key ] ) || '' === $row[ $key ] ) {
				continue;
			}
			$dec = json_decode( $row[ $key ], true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $dec ) ) {
				$row[ $key ] = $dec;
			}
		}
		return $row;
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function decode_intelligence_action_row( array $row ) {
		foreach ( array( 'action_json', 'apply_result_json' ) as $k ) {
			if ( ! isset( $row[ $k ] ) || ! is_string( $row[ $k ] ) || '' === $row[ $k ] ) {
				continue;
			}
			$dec = json_decode( $row[ $k ], true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $dec ) ) {
				$row[ $k ] = $dec;
			}
		}
		$row['requires_approval'] = ! empty( $row['requires_approval'] );
		return $row;
	}
}
