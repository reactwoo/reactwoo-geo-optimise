<?php
/**
 * Experiment-level builder-defined goal mapping (Control vs Variant B targets).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps physical goal_id/handler_id pairs to a logical primary goal id for reporting.
 */
class RWGO_Goal_Mapping {

	/**
	 * Whether config uses explicit per-variant mapping (not label-based expansion).
	 *
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return bool
	 */
	public static function is_active( array $cfg ) {
		$m = isset( $cfg['defined_goal_mapping'] ) && is_array( $cfg['defined_goal_mapping'] ) ? $cfg['defined_goal_mapping'] : array();
		return '' !== sanitize_key( (string) ( $m['logical_goal_id'] ?? '' ) );
	}

	/**
	 * Logical primary goal id stored on completions when mapping is active.
	 *
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return string
	 */
	public static function logical_goal_id( array $cfg ) {
		$m = isset( $cfg['defined_goal_mapping'] ) && is_array( $cfg['defined_goal_mapping'] ) ? $cfg['defined_goal_mapping'] : array();
		return sanitize_key( (string) ( $m['logical_goal_id'] ?? '' ) );
	}

	/**
	 * If the client fired a mapped physical pair for this variant, return the logical goal id; else the physical id.
	 *
	 * @param array<string, mixed> $cfg         Experiment config.
	 * @param string               $goal_id     Physical goal id from DOM.
	 * @param string               $handler_id  Handler id from DOM.
	 * @param string               $variant_id  Assignment slug (control|var_b).
	 * @return string Logical or original goal id.
	 */
	public static function normalize_stored_goal_id( array $cfg, $goal_id, $handler_id, $variant_id ) {
		$goal_id    = sanitize_key( (string) $goal_id );
		$handler_id = sanitize_key( (string) $handler_id );
		$variant_id = sanitize_key( (string) $variant_id );
		if ( '' === $goal_id || '' === $handler_id || '' === $variant_id ) {
			return $goal_id;
		}
		if ( ! self::is_active( $cfg ) ) {
			return $goal_id;
		}
		$m       = $cfg['defined_goal_mapping'];
		$logical = sanitize_key( (string) ( $m['logical_goal_id'] ?? '' ) );
		if ( '' === $logical ) {
			return $goal_id;
		}
		$key = self::variant_slug_to_target_key( $variant_id );
		if ( '' === $key ) {
			return $goal_id;
		}
		$targets = isset( $m['targets'] ) && is_array( $m['targets'] ) ? $m['targets'] : array();
		$pairs   = isset( $targets[ $key ] ) && is_array( $targets[ $key ] ) ? $targets[ $key ] : array();
		foreach ( $pairs as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$g = sanitize_key( (string) ( $p['goal_id'] ?? '' ) );
			$h = sanitize_key( (string) ( $p['handler_id'] ?? '' ) );
			if ( $g === $goal_id && $h === $handler_id ) {
				return $logical;
			}
		}
		return $goal_id;
	}

	/**
	 * Map assignment variant slug to defined_goal_mapping.targets key.
	 *
	 * @param string $variant_id control|var_b|legacy aliases.
	 * @return string control|var_b or empty.
	 */
	public static function variant_slug_to_target_key( $variant_id ) {
		$variant_id = sanitize_key( (string) $variant_id );
		if ( 'control' === $variant_id || 'a' === $variant_id ) {
			return 'control';
		}
		if ( 'var_b' === $variant_id || 'b' === $variant_id || 'variant_b' === $variant_id ) {
			return 'var_b';
		}
		return '';
	}
}
