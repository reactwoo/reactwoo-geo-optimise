<?php
/**
 * Offline GTM provisioning pack (agency import JSON — no Google API required).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a machine-readable GTM provision pack for one experiment.
 */
class RWGO_GTM_Provisioner {

	const SCHEMA_VERSION = '1.0';

	/**
	 * Build provision pack for an experiment.
	 *
	 * @param \WP_Post             $exp_post Experiment post.
	 * @param array<string, mixed> $cfg      Config.
	 * @return array<string, mixed>
	 */
	public static function build_pack( \WP_Post $exp_post, array $cfg ) {
		$preflight = class_exists( 'RWGO_Tracking_Preflight', false )
			? RWGO_Tracking_Preflight::run( $exp_post, $cfg )
			: array( 'ready' => false, 'checks' => array() );

		$labels = class_exists( 'RWGO_GTM_Handoff', false )
			? RWGO_GTM_Handoff::variant_labels_map( $cfg )
			: array();

		$variables = array();
		if ( class_exists( 'RWGO_GTM_Handoff', false ) ) {
			foreach ( RWGO_GTM_Handoff::standard_variable_definitions() as $row ) {
				$variables[] = array(
					'type'               => 'dlv',
					'name'               => $row['label'],
					'dataLayerVariable'  => $row['key'],
				);
			}
		}

		$triggers = array(
			array(
				'type'      => 'customEvent',
				'name'      => 'RWGO Goal Fired',
				'eventName' => class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::EVENT_NAME : 'rwgo_goal_fired',
			),
			array(
				'type'      => 'customEvent',
				'name'      => 'RWGO Experiment Exposure',
				'eventName' => class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::EXPOSURE_EVENT_NAME : 'rwgo_experiment_exposure',
			),
		);

		$ga4_goal = array(
			'type'      => 'ga4Event',
			'name'      => 'RWGO GA4 — Goal Fired',
			'eventName' => class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::EVENT_NAME : 'rwgo_goal_fired',
			'trigger'   => 'RWGO Goal Fired',
			'parameters'=> self::ga4_parameter_map( true ),
		);
		$ga4_exp  = array(
			'type'      => 'ga4Event',
			'name'      => 'RWGO GA4 — Experiment Exposure',
			'eventName' => class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::EXPOSURE_EVENT_NAME : 'rwgo_experiment_exposure',
			'trigger'   => 'RWGO Experiment Exposure',
			'parameters'=> self::ga4_parameter_map( false ),
		);

		$example = null;
		if ( class_exists( 'RWGO_GTM_Handoff', false ) && RWGO_GTM_Handoff::is_gtm_ready( $cfg ) ) {
			$pair = RWGO_GTM_Handoff::primary_goal_handler_pair( $cfg );
			if ( $pair ) {
				$example = RWGO_GTM_Handoff::build_datalayer_example_object( $exp_post, $cfg, 'var_b', $pair['goal'], $pair['handler'] );
			}
		}

		$pack = array(
			'schema_version'   => self::SCHEMA_VERSION,
			'product'          => 'reactwoo-geo-optimise',
			'generated_at_utc' => gmdate( 'c' ),
			'plugin_version'   => defined( 'RWGO_VERSION' ) ? RWGO_VERSION : '',
			'note'             => 'GTM provision pack. Offline import (variables, triggers, GA4 tags) or live push via React Cloud OAuth (Tag Manager API — workspace draft only, no auto-publish).',
			'experiment'       => array(
				'id'             => (int) $exp_post->ID,
				'title'          => get_the_title( $exp_post ),
				'experiment_key' => (string) ( $cfg['experiment_key'] ?? '' ),
				'status'         => (string) ( $cfg['status'] ?? '' ),
				'builder'        => class_exists( 'RWGO_GTM_Handoff', false )
					? RWGO_GTM_Handoff::builder_slug_for_datalayer( $cfg )
					: '',
				'variant_labels' => $labels,
				'source_page_id' => (int) ( $cfg['source_page_id'] ?? 0 ),
				'variant_b_page_id' => class_exists( 'RWGO_Tracking_Preflight', false )
					? RWGO_Tracking_Preflight::variant_b_page_id( $cfg )
					: 0,
			),
			'preflight'        => array(
				'ready'  => ! empty( $preflight['ready'] ),
				'pass'   => (int) ( $preflight['pass'] ?? 0 ),
				'warn'   => (int) ( $preflight['warn'] ?? 0 ),
				'fail'   => (int) ( $preflight['fail'] ?? 0 ),
				'checks' => isset( $preflight['checks'] ) ? $preflight['checks'] : array(),
			),
			'tracking_manifest'=> isset( $preflight['manifest'] ) ? $preflight['manifest'] : array(),
			'gtm'              => array(
				'variables' => $variables,
				'triggers'  => $triggers,
				'tags'      => array( $ga4_goal, $ga4_exp ),
				'copy_plain'=> array(
					'trigger'      => class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::trigger_block_plain() : '',
					'variables'    => class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::variables_plain() : '',
					'ga4_mapping'  => class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::ga4_mapping_plain() : '',
				),
			),
			'example_datalayer'=> $example,
		);

		/**
		 * @param array<string, mixed> $pack Pack.
		 * @param array<string, mixed> $cfg  Config.
		 */
		return apply_filters( 'rwgo_gtm_provision_pack', $pack, $cfg );
	}

	/**
	 * Suggested download filename.
	 *
	 * @param array<string, mixed> $cfg Config.
	 * @return string
	 */
	public static function filename_for_config( array $cfg ) {
		$key = isset( $cfg['experiment_key'] ) ? sanitize_file_name( (string) $cfg['experiment_key'] ) : 'experiment';
		if ( '' === $key ) {
			$key = 'experiment';
		}
		return 'rwgo-gtm-provision-' . $key . '.json';
	}

	/**
	 * @param bool $include_goal_fields Include goal/handler/element key params.
	 * @return list<array{name:string,value:string}>
	 */
	private static function ga4_parameter_map( $include_goal_fields ) {
		$base = array(
			array( 'name' => 'rwgo_test_name', 'value' => '{{DLV - rwgo_test_name}}' ),
			array( 'name' => 'rwgo_experiment_key', 'value' => '{{DLV - rwgo_experiment_key}}' ),
			array( 'name' => 'rwgo_variant_id', 'value' => '{{DLV - rwgo_variant_id}}' ),
			array( 'name' => 'rwgo_variant_label', 'value' => '{{DLV - rwgo_variant_label}}' ),
			array( 'name' => 'rwgo_page_context_id', 'value' => '{{DLV - rwgo_page_context_id}}' ),
			array( 'name' => 'rwgo_builder', 'value' => '{{DLV - rwgo_builder}}' ),
		);
		if ( $include_goal_fields ) {
			$base[] = array( 'name' => 'rwgo_goal_id', 'value' => '{{DLV - rwgo_goal_id}}' );
			$base[] = array( 'name' => 'rwgo_goal_label', 'value' => '{{DLV - rwgo_goal_label}}' );
			$base[] = array( 'name' => 'rwgo_handler_id', 'value' => '{{DLV - rwgo_handler_id}}' );
			$base[] = array( 'name' => 'rwgo_element_key', 'value' => '{{DLV - rwgo_element_key}}' );
		}
		return $base;
	}
}
