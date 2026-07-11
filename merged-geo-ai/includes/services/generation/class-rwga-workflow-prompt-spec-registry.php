<?php
/**
 * Prompt specifications for WordPress AI generation workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versioned system instructions, schemas, and generation defaults.
 */
class RWGA_Workflow_Prompt_Spec_Registry {

	/**
	 * Workflows that may use WordPress AI in this pass.
	 *
	 * @return string[]
	 */
	public static function wordpress_ai_workflow_keys() {
		return array( 'ux_analysis', 'ux_recommend' );
	}

	/**
	 * @param string $workflow_key Workflow key.
	 * @return bool
	 */
	public static function supports_wordpress_ai( $workflow_key ) {
		return in_array( sanitize_key( (string) $workflow_key ), self::wordpress_ai_workflow_keys(), true );
	}

	/**
	 * @param string $workflow_key Workflow key.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_spec( $workflow_key ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		switch ( $workflow_key ) {
			case 'ux_analysis':
				return self::ux_analysis_spec();
			case 'ux_recommend':
				return self::ux_recommend_spec();
			default:
				return new WP_Error(
					'rwga_transport_unsupported',
					sprintf(
						/* translators: %s: workflow key */
						__( 'WordPress AI does not support the “%s” workflow yet.', 'reactwoo-geo-ai' ),
						$workflow_key
					)
				);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function ux_analysis_spec() {
		return array(
			'workflow_key'   => 'ux_analysis',
			'prompt_version' => 'wpai-ux-analysis-1.0.0',
			'temperature'    => 0.35,
			'max_tokens'     => 1800,
			'system'         => 'You are a conversion-focused UX analyst for geo-targeted WordPress pages. '
				. 'Use only the provided normalized page context. Do not invent Elementor document JSON or mutate pages. '
				. 'Return JSON matching the schema: score (0-100), confidence (0-1), summary (string), findings (array). '
				. 'Each finding needs finding_key, category, severity (high|medium|low), confidence, title, evidence, recommendation_hint, impact_estimate.',
			'schema'         => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'score', 'confidence', 'summary', 'findings' ),
				'properties'           => array(
					'score'      => array( 'type' => 'number' ),
					'confidence' => array( 'type' => 'number' ),
					'summary'    => array( 'type' => 'string' ),
					'findings'   => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'required'             => array( 'finding_key', 'category', 'severity', 'title' ),
							'properties'           => array(
								'finding_key'         => array( 'type' => 'string' ),
								'category'            => array( 'type' => 'string' ),
								'severity'            => array( 'type' => 'string' ),
								'confidence'          => array( 'type' => 'number' ),
								'title'               => array( 'type' => 'string' ),
								'evidence'            => array( 'type' => 'string' ),
								'recommendation_hint' => array( 'type' => 'string' ),
								'impact_estimate'     => array( 'type' => 'string' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function ux_recommend_spec() {
		return array(
			'workflow_key'   => 'ux_recommend',
			'prompt_version' => 'wpai-ux-recommend-1.0.0',
			'temperature'    => 0.4,
			'max_tokens'     => 2200,
			'system'         => 'You turn UX analysis findings into actionable recommendation cards for WordPress pages. '
				. 'Use only the provided normalized context and findings. Do not emit raw Elementor or Atomic documents. '
				. 'Return JSON with recommendations[] using the existing Optimise card fields: priority_level, category, title, '
				. 'problem, why_it_matters, recommendation, page_placement, suggested_copy (object), expected_impact, confidence, '
				. 'and optional builder, recommendation_type, target, implementation_possible, risk_level.',
			'schema'         => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'recommendations' ),
				'properties'           => array(
					'recommendations' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'required'             => array( 'priority_level', 'category', 'title', 'problem', 'recommendation' ),
							'properties'           => array(
								'priority_level'            => array( 'type' => 'string' ),
								'category'                  => array( 'type' => 'string' ),
								'title'                     => array( 'type' => 'string' ),
								'problem'                   => array( 'type' => 'string' ),
								'why_it_matters'            => array( 'type' => 'string' ),
								'recommendation'            => array( 'type' => 'string' ),
								'page_placement'            => array( 'type' => 'string' ),
								'suggested_copy'            => array( 'type' => 'object' ),
								'expected_impact'           => array( 'type' => 'string' ),
								'confidence'                => array( 'type' => 'number' ),
								'builder'                   => array( 'type' => 'string' ),
								'recommendation_type'       => array( 'type' => 'string' ),
								'target'                    => array( 'type' => 'object' ),
								'implementation_possible'   => array( 'type' => 'boolean' ),
								'risk_level'                => array( 'type' => 'string' ),
							),
						),
					),
				),
			),
		);
	}
}
