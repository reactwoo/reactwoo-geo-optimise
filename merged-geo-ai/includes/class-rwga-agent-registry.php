<?php
/**
 * Agent metadata (bounded roles).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declarative agent registry for UI and guardrails.
 */
class RWGA_Agent_Registry {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all() {
		$agents = array(
			'ux_analyst' => array(
				'label'            => __( 'UX Analyst', 'reactwoo-geo-ai' ),
				'allowed_workflows'=> array( 'ux_analysis' ),
				'schema_version'   => '1.0.0',
				'supports_geo'     => true,
				'supports_performance_context' => true,
			),
			'ux_strategist' => array(
				'label'             => __( 'UX Strategist', 'reactwoo-geo-ai' ),
				'allowed_workflows' => array( 'ux_recommend' ),
				'schema_version'    => '1.0.0',
				'supports_geo'      => true,
			),
			'ux_writer' => array(
				'label'             => __( 'UX Writer', 'reactwoo-geo-ai' ),
				'allowed_workflows' => array( 'copy_implement' ),
				'schema_version'    => '1.0.0',
				'supports_geo'      => true,
			),
			'seo_strategist' => array(
				'label'             => __( 'SEO Strategist', 'reactwoo-geo-ai' ),
				'allowed_workflows' => array( 'seo_implement' ),
				'schema_version'    => '1.0.0',
				'supports_geo'      => true,
			),
			'market_analyst' => array(
				'label'             => __( 'Market Analyst', 'reactwoo-geo-ai' ),
				'allowed_workflows' => array( 'competitor_research' ),
				'schema_version'    => '1.0.0',
				'supports_geo'      => true,
			),
		);

		/**
		 * Register Geo AI agents.
		 *
		 * @param array<string, array<string, mixed>> $agents Agent definitions.
		 */
		return apply_filters( 'rwga_register_agents', $agents );
	}
}
