<?php
/**
 * Soft workflow cost hints for UI only (not billing enforcement).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Average token cost hints per workflow key — for display estimates only.
 *
 * @return array<string, array{avg_tokens:int, label:string}>
 */
function rwga_get_workflow_usage_hints() {
	return array(
		'ux_analysis'          => array(
			'avg_tokens' => 120000,
			'label'      => __( 'UX analyses', 'reactwoo-geo-ai' ),
		),
		'competitor_research'  => array(
			'avg_tokens' => 250000,
			'label'      => __( 'Competitor reviews', 'reactwoo-geo-ai' ),
		),
		'ux_recommend'         => array(
			'avg_tokens' => 60000,
			'label'      => __( 'Recommendations', 'reactwoo-geo-ai' ),
		),
		'copy_implement'       => array(
			'avg_tokens' => 40000,
			'label'      => __( 'Copy / SEO generations', 'reactwoo-geo-ai' ),
		),
		'seo_analysis'         => array(
			'avg_tokens' => 80000,
			'label'      => __( 'SEO analyses', 'reactwoo-geo-ai' ),
		),
		'seo_implement'        => array(
			'avg_tokens' => 40000,
			'label'      => __( 'SEO generations', 'reactwoo-geo-ai' ),
		),
		'geo_personalise'      => array(
			'avg_tokens' => 70000,
			'label'      => __( 'Geo variants', 'reactwoo-geo-ai' ),
		),
		'automation_suggest'   => array(
			'avg_tokens' => 50000,
			'label'      => __( 'Automation suggestions', 'reactwoo-geo-ai' ),
		),
		'site_audit'                  => array(
			'avg_tokens' => 80000,
			'label'      => __( 'Site audits', 'reactwoo-geo-ai' ),
		),
		'rule_explain'                => array(
			'avg_tokens' => 40000,
			'label'      => __( 'Rule explanations', 'reactwoo-geo-ai' ),
		),
		'rule_debug'                  => array(
			'avg_tokens' => 50000,
			'label'      => __( 'Rule debug runs', 'reactwoo-geo-ai' ),
		),
		'rule_create'                 => array(
			'avg_tokens' => 60000,
			'label'      => __( 'Rule drafts', 'reactwoo-geo-ai' ),
		),
		'popup_fire_debug'            => array(
			'avg_tokens' => 45000,
			'label'      => __( 'Popup debug runs', 'reactwoo-geo-ai' ),
		),
		'variant_relationship_audit'  => array(
			'avg_tokens' => 55000,
			'label'      => __( 'Variant audits', 'reactwoo-geo-ai' ),
		),
		'tracking_gap_audit'          => array(
			'avg_tokens' => 50000,
			'label'      => __( 'Tracking gap audits', 'reactwoo-geo-ai' ),
		),
		'optimisation_recommendation' => array(
			'avg_tokens' => 70000,
			'label'      => __( 'Optimisation recommendations', 'reactwoo-geo-ai' ),
		),
	);
}
