<?php
/**
 * Intelligence workflow key definitions for Geo AI remote engine.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry helpers for site intelligence workflow keys.
 */
class RWGA_Workflow_Intelligence_Definitions {

	/**
	 * @return array<string, array{label:string,agent:string}>
	 */
	public static function get_definitions() {
		$defs = array(
			'site_audit'                  => array(
				'label' => __( 'Site audit', 'reactwoo-geo-ai' ),
				'agent' => 'site_auditor',
			),
			'rule_explain'                => array(
				'label' => __( 'Rule explain', 'reactwoo-geo-ai' ),
				'agent' => 'rule_explainer',
			),
			'rule_debug'                  => array(
				'label' => __( 'Rule debug', 'reactwoo-geo-ai' ),
				'agent' => 'rule_debugger',
			),
			'rule_create'                 => array(
				'label' => __( 'Rule create', 'reactwoo-geo-ai' ),
				'agent' => 'rule_author',
			),
			'popup_fire_debug'            => array(
				'label' => __( 'Popup fire debug', 'reactwoo-geo-ai' ),
				'agent' => 'popup_debugger',
			),
			'variant_relationship_audit'  => array(
				'label' => __( 'Variant relationship audit', 'reactwoo-geo-ai' ),
				'agent' => 'variant_auditor',
			),
			'tracking_gap_audit'          => array(
				'label' => __( 'Tracking gap audit', 'reactwoo-geo-ai' ),
				'agent' => 'tracking_auditor',
			),
			'optimisation_recommendation' => array(
				'label' => __( 'Optimisation recommendation', 'reactwoo-geo-ai' ),
				'agent' => 'optimisation_advisor',
			),
		);

		/**
		 * @param array<string, array{label:string,agent:string}> $defs Intelligence workflow definitions.
		 */
		return apply_filters( 'rwga_intelligence_workflow_definitions', $defs );
	}

	/**
	 * @return array<string, RWGA_Workflow_Intelligence>
	 */
	public static function build_workflows() {
		$out = array();
		foreach ( self::get_definitions() as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? (string) $row['label'] : $key;
			$agent = isset( $row['agent'] ) ? (string) $row['agent'] : 'intelligence';
			$out[ sanitize_key( (string) $key ) ] = new RWGA_Workflow_Intelligence( $key, $label, $agent );
		}
		return $out;
	}
}
