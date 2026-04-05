<?php
/**
 * GTM / GA4 handoff text and per-test dataLayer examples (admin UI + docs).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds agency-friendly GTM setup content for Tracking Tools and test modals.
 */
class RWGO_GTM_Handoff {

	const EVENT_NAME = 'rwgo_goal_fired';

	/**
	 * Standard Data Layer Variable names (snake_case, match rwgo-tracking.js push).
	 *
	 * @return array<int, array{key:string,label:string,gtm_type:string}>
	 */
	public static function standard_variable_definitions() {
		return array(
			array(
				'key'       => 'rwgo_test_name',
				'label'     => 'DLV - rwgo_test_name',
				'gtm_type'  => 'Data Layer Variable',
			),
			array(
				'key'       => 'rwgo_experiment_key',
				'label'     => 'DLV - rwgo_experiment_key',
				'gtm_type'  => 'Data Layer Variable',
			),
			array(
				'key'       => 'rwgo_variant_id',
				'label'     => 'DLV - rwgo_variant_id',
				'gtm_type'  => 'Data Layer Variable',
			),
			array(
				'key'       => 'rwgo_variant_label',
				'label'     => 'DLV - rwgo_variant_label',
				'gtm_type'  => 'Data Layer Variable',
			),
			array(
				'key'       => 'rwgo_goal_id',
				'label'     => 'DLV - rwgo_goal_id',
				'gtm_type'  => 'Data Layer Variable',
			),
			array(
				'key'       => 'rwgo_goal_label',
				'label'     => 'DLV - rwgo_goal_label',
				'gtm_type'  => 'Data Layer Variable',
			),
			array(
				'key'       => 'rwgo_handler_id',
				'label'     => 'DLV - rwgo_handler_id',
				'gtm_type'  => 'Data Layer Variable',
			),
			array(
				'key'       => 'rwgo_page_context_id',
				'label'     => 'DLV - rwgo_page_context_id',
				'gtm_type'  => 'Data Layer Variable',
			),
			array(
				'key'       => 'rwgo_builder',
				'label'     => 'DLV - rwgo_builder',
				'gtm_type'  => 'Data Layer Variable',
			),
		);
	}

	/**
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return string Builder slug for dataLayer (lowercase).
	 */
	public static function builder_slug_for_datalayer( array $cfg ) {
		$bt = isset( $cfg['builder_type'] ) ? sanitize_key( (string) $cfg['builder_type'] ) : '';
		if ( '' === $bt ) {
			return 'unknown';
		}
		return $bt;
	}

	/**
	 * Variant id => label for GTM examples.
	 *
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return array<string, string>
	 */
	public static function variant_labels_map( array $cfg ) {
		$out = array(
			'control' => __( 'Control', 'reactwoo-geo-optimise' ),
			'var_b'   => __( 'Variant B', 'reactwoo-geo-optimise' ),
		);
		if ( empty( $cfg['variants'] ) || ! is_array( $cfg['variants'] ) ) {
			return $out;
		}
		foreach ( $cfg['variants'] as $row ) {
			if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
				continue;
			}
			$vid = sanitize_key( (string) $row['variant_id'] );
			$out[ $vid ] = isset( $row['variant_label'] ) ? (string) $row['variant_label'] : $vid;
		}
		return $out;
	}

	/**
	 * Whether a test has enough configuration to show GTM handoff (goal + handler for example).
	 *
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return bool
	 */
	public static function is_gtm_ready( array $cfg ) {
		if ( empty( $cfg['experiment_key'] ) ) {
			return false;
		}
		$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			$handlers = isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array();
			foreach ( $handlers as $h ) {
				if ( is_array( $h ) && ! empty( $h['handler_id'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Pick primary (or first) goal + first handler for a representative dataLayer example.
	 *
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return array{goal:array<string,mixed>,handler:array<string,mixed>}|null
	 */
	public static function primary_goal_handler_pair( array $cfg ) {
		$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
		if ( empty( $goals ) ) {
			return null;
		}
		$primary_id = class_exists( 'RWGO_Goal_Service', false ) ? RWGO_Goal_Service::get_primary_goal_id( $cfg ) : '';
		$ordered    = $goals;
		if ( '' !== $primary_id ) {
			usort(
				$ordered,
				static function ( $a, $b ) use ( $primary_id ) {
					$aid = is_array( $a ) && isset( $a['goal_id'] ) ? sanitize_key( (string) $a['goal_id'] ) : '';
					$bid = is_array( $b ) && isset( $b['goal_id'] ) ? sanitize_key( (string) $b['goal_id'] ) : '';
					if ( $aid === $primary_id ) {
						return -1;
					}
					if ( $bid === $primary_id ) {
						return 1;
					}
					return 0;
				}
			);
		}
		foreach ( $ordered as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			$handlers = isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array();
			foreach ( $handlers as $h ) {
				if ( is_array( $h ) && ! empty( $h['handler_id'] ) ) {
					return array(
						'goal'    => $g,
						'handler' => $h,
					);
				}
			}
		}
		return null;
	}

	/**
	 * DataLayer object for documentation (matches client push field names).
	 *
	 * @param \WP_Post             $exp_post Experiment post.
	 * @param array<string, mixed> $cfg      Config.
	 * @param string               $variant_id Example variant slug.
	 * @param array<string, mixed> $goal       Goal row.
	 * @param array<string, mixed> $handler    Handler row.
	 * @return array<string, mixed>
	 */
	public static function build_datalayer_example_object( \WP_Post $exp_post, array $cfg, $variant_id, array $goal, array $handler ) {
		$src         = (int) ( $cfg['source_page_id'] ?? 0 );
		$vmap        = self::variant_labels_map( $cfg );
		$vid         = sanitize_key( (string) $variant_id );
		$gid         = isset( $goal['goal_id'] ) ? (string) $goal['goal_id'] : '';
		$glabel      = isset( $goal['label'] ) ? (string) $goal['label'] : $gid;
		$hid         = isset( $handler['handler_id'] ) ? (string) $handler['handler_id'] : '';
		$vlabel      = isset( $vmap[ $vid ] ) ? $vmap[ $vid ] : $vid;

		return array(
			'event'                 => self::EVENT_NAME,
			'rwgo_test_name'        => get_the_title( $exp_post ),
			'rwgo_experiment_key'   => (string) ( $cfg['experiment_key'] ?? '' ),
			'rwgo_variant_id'       => $vid,
			'rwgo_variant_label'    => $vlabel,
			'rwgo_goal_id'          => $gid,
			'rwgo_goal_label'       => $glabel,
			'rwgo_handler_id'       => $hid,
			'rwgo_page_context_id'  => $src,
			'rwgo_builder'          => self::builder_slug_for_datalayer( $cfg ),
		);
	}

	/**
	 * Plain-text block: recommended trigger.
	 *
	 * @return string
	 */
	public static function trigger_block_plain() {
		return "Trigger Type: Custom Event\nEvent Name: " . self::EVENT_NAME;
	}

	/**
	 * Plain-text GA4 parameter mapping for copy.
	 *
	 * @return string
	 */
	public static function ga4_mapping_plain() {
		$e = self::EVENT_NAME;
		$lines = array(
			"Event Name: {$e}",
			'',
			'Parameters:',
			"rwgo_test_name → {{DLV - rwgo_test_name}}",
			"rwgo_experiment_key → {{DLV - rwgo_experiment_key}}",
			"rwgo_variant_id → {{DLV - rwgo_variant_id}}",
			"rwgo_variant_label → {{DLV - rwgo_variant_label}}",
			"rwgo_goal_id → {{DLV - rwgo_goal_id}}",
			"rwgo_goal_label → {{DLV - rwgo_goal_label}}",
			"rwgo_handler_id → {{DLV - rwgo_handler_id}}",
			"rwgo_page_context_id → {{DLV - rwgo_page_context_id}}",
			"rwgo_builder → {{DLV - rwgo_builder}}",
		);
		return implode( "\n", $lines );
	}

	/**
	 * Variables list as plain text for “Copy variables”.
	 *
	 * @return string
	 */
	public static function variables_plain() {
		$rows = self::standard_variable_definitions();
		$out  = array( 'Create Data Layer Variables with these names:', '' );
		foreach ( $rows as $r ) {
			$out[] = $r['label'] . ' — Data Layer Variable Name: ' . $r['key'];
		}
		return implode( "\n", $out );
	}

	/**
	 * Full “copy all” pack (simple mode).
	 *
	 * @param string $example_js Full dataLayer push JS.
	 * @return string
	 */
	public static function copy_all_simple_pack( $example_js ) {
		$parts = array(
			'--- Recommended trigger ---',
			self::trigger_block_plain(),
			'',
			'--- Recommended Data Layer Variables ---',
			self::variables_plain(),
			'',
			'--- GA4 mapping ---',
			self::ga4_mapping_plain(),
			'',
			'--- Example dataLayer push ---',
			$example_js,
		);
		return implode( "\n", $parts );
	}

	/**
	 * Placeholder dataLayer example when no tests exist yet.
	 *
	 * @return string JS snippet.
	 */
	public static function generic_example_datalayer_js() {
		$obj = array(
			'event'                => self::EVENT_NAME,
			'rwgo_test_name'       => 'Example Test',
			'rwgo_experiment_key'  => 'rwgo_page_00000_ab_example01',
			'rwgo_variant_id'      => 'var_b',
			'rwgo_variant_label'   => 'Variant B',
			'rwgo_goal_id'         => 'goal_example',
			'rwgo_goal_label'      => 'CTA click',
			'rwgo_handler_id'      => 'hdl_example',
			'rwgo_page_context_id' => 0,
			'rwgo_builder'         => 'elementor',
		);
		return "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . ');';
	}

	/**
	 * Build modal payload for Tests list (JSON-safe structure for wp_json_encode).
	 *
	 * @param \WP_Post             $exp_post Experiment post.
	 * @param array<string, mixed> $cfg      Config.
	 * @return array<string, mixed>|null
	 */
	public static function build_modal_payload_for_test( \WP_Post $exp_post, array $cfg ) {
		if ( ! self::is_gtm_ready( $cfg ) ) {
			return null;
		}
		$pair = self::primary_goal_handler_pair( $cfg );
		if ( null === $pair ) {
			return null;
		}
		$goal    = $pair['goal'];
		$handler = $pair['handler'];
		$obj     = self::build_datalayer_example_object( $exp_post, $cfg, 'var_b', $goal, $handler );
		$js      = "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . ');';

		$src     = (int) ( $cfg['source_page_id'] ?? 0 );
		$var_b   = 0;
		if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
			foreach ( $cfg['variants'] as $row ) {
				if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
					$var_b = (int) ( $row['page_id'] ?? 0 );
					break;
				}
			}
		}
		$control_title = $src > 0 ? get_the_title( $src ) : '—';
		$variant_title = $var_b > 0 ? get_the_title( $var_b ) : '—';
		$goal_label    = class_exists( 'RWGO_Goal_Service', false ) ? RWGO_Goal_Service::get_primary_goal_label( $cfg ) : ( isset( $goal['label'] ) ? (string) $goal['label'] : '' );

		return array(
			'title'   => sprintf(
				/* translators: %s: test title */
				__( 'GTM Setup for "%s"', 'reactwoo-geo-optimise' ),
				get_the_title( $exp_post )
			),
			'intro'   => __( 'Copy a ready-to-implement GTM setup for this test, including trigger, variables, GA4 mapping, and an example payload.', 'reactwoo-geo-optimise' ),
			'summary' => array(
				'test'       => get_the_title( $exp_post ),
				'goal'       => $goal_label,
				'control'    => $control_title,
				'variant_b'  => $variant_title,
				'event'      => self::EVENT_NAME,
				'exp_key'    => (string) ( $cfg['experiment_key'] ?? '' ),
			),
			'sections' => array(
				array(
					'id'    => 'trigger',
					'label' => __( 'Recommended trigger', 'reactwoo-geo-optimise' ),
					'body'  => self::trigger_block_plain(),
				),
				array(
					'id'    => 'variables',
					'label' => __( 'Recommended variables', 'reactwoo-geo-optimise' ),
					'body'  => self::variables_plain(),
				),
				array(
					'id'    => 'ga4',
					'label' => __( 'Recommended GA4 mapping', 'reactwoo-geo-optimise' ),
					'body'  => self::ga4_mapping_plain(),
				),
				array(
					'id'    => 'datalayer',
					'label' => __( 'Example dataLayer push', 'reactwoo-geo-optimise' ),
					'body'  => $js,
				),
				array(
					'id'    => 'hint',
					'label' => __( 'Reporting hint', 'reactwoo-geo-optimise' ),
					'body'  => __( 'Use GA4 explorations or reports filtered by rwgo_experiment_key and rwgo_variant_id to compare Control vs Variant B without creating separate events per test.', 'reactwoo-geo-optimise' ),
				),
			),
			'copyAll' => self::copy_all_simple_pack( $js ),
		);
	}
}
