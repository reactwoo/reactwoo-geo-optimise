<?php
/**
 * Experiment measurement targets: (goal_id, handler_id) pairs as stored in rwgo_events.
 *
 * Labels are resolved for display only; aggregation uses IDs from config mapping / goals.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds stored-event pairs and resolves labels for reporting.
 */
class RWGO_Experiment_Measurements {

	/**
	 * Pairs as they appear in the events table for this variant’s contribution to totals.
	 * Mapped tests store logical goal_id + physical handler_id per target.
	 *
	 * @param array<string, mixed> $cfg          Experiment config.
	 * @param string               $variant_slug control|var_b|…
	 * @return list<array{goal_id: string, handler_id: string}>
	 */
	public static function stored_pairs_for_variant( array $cfg, $variant_slug ) {
		$variant_slug = sanitize_key( (string) $variant_slug );
		$out          = array();
		if ( class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg ) ) {
			$logical = RWGO_Goal_Mapping::logical_goal_id( $cfg );
			if ( '' === $logical ) {
				return $out;
			}
			$key = RWGO_Goal_Mapping::variant_slug_to_target_key( $variant_slug );
			if ( '' === $key ) {
				return $out;
			}
			$m       = isset( $cfg['defined_goal_mapping'] ) && is_array( $cfg['defined_goal_mapping'] ) ? $cfg['defined_goal_mapping'] : array();
			$targets = isset( $m['targets'] ) && is_array( $m['targets'] ) ? $m['targets'] : array();
			$pairs   = isset( $targets[ $key ] ) && is_array( $targets[ $key ] ) ? $targets[ $key ] : array();
			foreach ( $pairs as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$h = sanitize_key( (string) ( $p['handler_id'] ?? '' ) );
				if ( '' !== $h ) {
					$out[] = array(
						'goal_id'    => $logical,
						'handler_id' => $h,
					);
				}
			}
			return $out;
		}
		$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			$gid = sanitize_key( (string) $g['goal_id'] );
			foreach ( isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array() as $h ) {
				if ( ! is_array( $h ) || empty( $h['handler_id'] ) ) {
					continue;
				}
				$hid = sanitize_key( (string) $h['handler_id'] );
				if ( '' !== $gid && '' !== $hid ) {
					$out[] = array(
						'goal_id'    => $gid,
						'handler_id' => $hid,
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Union of all stored pairs (any variant) for legacy configs where the same goals apply to all variants.
	 *
	 * @param array<string, mixed> $cfg Config.
	 * @return list<array{goal_id: string, handler_id: string}>
	 */
	public static function stored_pairs_all_goals( array $cfg ) {
		$seen = array();
		$out  = array();
		$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			$gid = sanitize_key( (string) $g['goal_id'] );
			foreach ( isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array() as $h ) {
				if ( ! is_array( $h ) || empty( $h['handler_id'] ) ) {
					continue;
				}
				$hid = sanitize_key( (string) $h['handler_id'] );
				if ( '' === $gid || '' === $hid ) {
					continue;
				}
				$pk = $gid . '|' . $hid;
				if ( isset( $seen[ $pk ] ) ) {
					continue;
				}
				$seen[ $pk ] = true;
				$out[]       = array(
					'goal_id'    => $gid,
					'handler_id' => $hid,
				);
			}
		}
		return $out;
	}

	/**
	 * Display label for a stored pair (matches config goals / mapping; never matches by label string).
	 *
	 * @param array<string, mixed> $cfg      Config.
	 * @param string                 $goal_id    Stored goal id.
	 * @param string                 $handler_id Stored handler id.
	 * @return string
	 */
	public static function label_for_pair( array $cfg, $goal_id, $handler_id ) {
		$goal_id    = sanitize_key( (string) $goal_id );
		$handler_id = sanitize_key( (string) $handler_id );
		$goals      = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) ) {
				continue;
			}
			$gid = sanitize_key( (string) ( $g['goal_id'] ?? '' ) );
			if ( $gid !== $goal_id && ( empty( $g['logical_goal_id'] ) || sanitize_key( (string) $g['logical_goal_id'] ) !== $goal_id ) ) {
				continue;
			}
			$handlers = isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array();
			foreach ( $handlers as $h ) {
				if ( ! is_array( $h ) || empty( $h['handler_id'] ) ) {
					continue;
				}
				if ( sanitize_key( (string) $h['handler_id'] ) !== $handler_id ) {
					continue;
				}
				$hl = isset( $h['label'] ) ? trim( (string) $h['label'] ) : '';
				$gl = isset( $g['label'] ) ? trim( (string) $g['label'] ) : '';
				$base = '' !== $hl ? $hl : ( '' !== $gl ? $gl : $goal_id );
				$mv   = isset( $g['mapping_variant'] ) ? sanitize_key( (string) $g['mapping_variant'] ) : '';
				if ( 'control' === $mv ) {
					$base .= ' — ' . __( 'Control', 'reactwoo-geo-optimise' );
				} elseif ( 'var_b' === $mv ) {
					$base .= ' — ' . __( 'Variant B', 'reactwoo-geo-optimise' );
				}
				return $base;
			}
		}
		return $goal_id ? $goal_id : $handler_id;
	}
}
