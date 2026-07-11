<?php
/**
 * Experiment tracking manifest (measurement contract for a single test).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a serializable measurement contract from experiment config + defined goals.
 */
class RWGO_Tracking_Manifest {

	const SCHEMA_VERSION = '1.0';

	/**
	 * Build manifest for an experiment config.
	 *
	 * @param array<string, mixed> $cfg              Experiment config.
	 * @param int                  $experiment_id    CPT id (optional).
	 * @return array<string, mixed>
	 */
	public static function build( array $cfg, $experiment_id = 0 ) {
		$experiment_key = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
		$source_page_id = (int) ( $cfg['source_page_id'] ?? 0 );
		$variant_b_id   = (int) ( $cfg['variant_b_page_id'] ?? 0 );

		$primary_id = class_exists( 'RWGO_Goal_Service', false )
			? RWGO_Goal_Service::get_primary_goal_id( $cfg )
			: '';

		$tracked = self::tracked_elements_from_config( $cfg );
		if ( empty( $tracked ) && $source_page_id > 0 && class_exists( 'RWGO_Defined_Goal_Service', false ) ) {
			$posts = array_filter( array( $source_page_id, $variant_b_id ) );
			foreach ( RWGO_Defined_Goal_Service::collect_for_posts( $posts ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$ek = isset( $row['element_key'] ) ? (string) $row['element_key'] : '';
				if ( '' === $ek && class_exists( 'RWGO_Element_Key', false ) ) {
					$ek = RWGO_Element_Key::resolve(
						'',
						(string) ( $row['goal_label'] ?? '' ),
						(string) ( $row['ui_goal_type'] ?? 'cta_click' )
					);
				}
				if ( '' === $ek ) {
					continue;
				}
				$tracked[] = array(
					'semantic_key' => $ek,
					'goal_id'      => (string) ( $row['goal_id'] ?? '' ),
					'handler_id'   => (string) ( $row['handler_id'] ?? '' ),
					'goal_label'   => (string) ( $row['goal_label'] ?? '' ),
					'builder'      => (string) ( $row['builder'] ?? '' ),
				);
			}
		}

		$primary = null;
		$secondary = array();
		foreach ( $tracked as $t ) {
			if ( ! is_array( $t ) ) {
				continue;
			}
			$gid = (string) ( $t['goal_id'] ?? '' );
			$entry = array(
				'id'           => $gid,
				'type'         => (string) ( $t['goal_type'] ?? 'click' ),
				'semantic_key' => (string) ( $t['semantic_key'] ?? '' ),
			);
			if ( '' !== $primary_id && $gid === $primary_id && null === $primary ) {
				$primary = $entry;
			} else {
				$secondary[] = $entry;
			}
		}
		if ( null === $primary && ! empty( $tracked[0] ) && is_array( $tracked[0] ) ) {
			$primary = array(
				'id'           => (string) ( $tracked[0]['goal_id'] ?? '' ),
				'type'         => 'click',
				'semantic_key' => (string) ( $tracked[0]['semantic_key'] ?? '' ),
			);
			$secondary = array_slice( $secondary, 1 );
		}

		$manifest = array(
			'schema_version'   => self::SCHEMA_VERSION,
			'experiment_id'    => (int) $experiment_id,
			'experiment_key'   => $experiment_key,
			'hypothesis'       => isset( $cfg['hypothesis'] ) ? sanitize_text_field( (string) $cfg['hypothesis'] ) : '',
			'primary_goal'     => $primary,
			'secondary_goals'  => $secondary,
			'tracked_elements' => $tracked,
			'guardrails'       => self::guardrails( $cfg ),
		);

		/**
		 * @param array<string, mixed> $manifest Manifest.
		 * @param array<string, mixed> $cfg      Config.
		 */
		return apply_filters( 'rwgo_tracking_manifest', $manifest, $cfg );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return list<array<string, mixed>>
	 */
	private static function tracked_elements_from_config( array $cfg ) {
		$out   = array();
		$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			$gid   = sanitize_key( (string) $g['goal_id'] );
			$label = isset( $g['label'] ) ? (string) $g['label'] : $gid;
			$handlers = isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array();
			if ( empty( $handlers ) ) {
				$ek = isset( $g['element_key'] ) ? (string) $g['element_key'] : '';
				if ( '' === $ek && class_exists( 'RWGO_Element_Key', false ) ) {
					$ek = RWGO_Element_Key::resolve( '', $label, (string) ( $g['ui_goal_type'] ?? $g['goal_type'] ?? 'cta_click' ) );
				}
				$out[] = array(
					'semantic_key' => $ek,
					'goal_id'      => $gid,
					'handler_id'   => '',
					'goal_label'   => $label,
					'goal_type'    => (string) ( $g['goal_type'] ?? '' ),
					'builder'      => (string) ( $g['builder'] ?? '' ),
				);
				continue;
			}
			foreach ( $handlers as $h ) {
				if ( ! is_array( $h ) || empty( $h['handler_id'] ) ) {
					continue;
				}
				$hl = isset( $h['label'] ) ? (string) $h['label'] : $label;
				$ek = isset( $h['element_key'] ) ? (string) $h['element_key'] : ( isset( $g['element_key'] ) ? (string) $g['element_key'] : '' );
				if ( '' === $ek && class_exists( 'RWGO_Element_Key', false ) ) {
					$ek = RWGO_Element_Key::resolve( '', $hl, (string) ( $g['ui_goal_type'] ?? $g['goal_type'] ?? 'cta_click' ) );
				}
				$out[] = array(
					'semantic_key' => $ek,
					'goal_id'      => $gid,
					'handler_id'   => sanitize_key( (string) $h['handler_id'] ),
					'goal_label'   => $hl,
					'goal_type'    => (string) ( $g['goal_type'] ?? '' ),
					'builder'      => (string) ( $g['builder'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return list<string>
	 */
	private static function guardrails( array $cfg ) {
		$g = isset( $cfg['guardrails'] ) && is_array( $cfg['guardrails'] ) ? $cfg['guardrails'] : array();
		$out = array();
		foreach ( $g as $item ) {
			$k = sanitize_key( (string) $item );
			if ( '' !== $k ) {
				$out[] = $k;
			}
		}
		if ( empty( $out ) ) {
			$out = array( 'bounce_rate', 'form_error_rate', 'page_performance' );
		}
		return array_values( array_unique( $out ) );
	}
}
