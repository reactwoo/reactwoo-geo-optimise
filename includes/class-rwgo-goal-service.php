<?php
/**
 * Primary goal resolution and assignment-only (traffic split) mode.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads goal configuration from experiment meta.
 */
class RWGO_Goal_Service {

	/**
	 * Traffic split only — no conversion winner.
	 *
	 * @param array<string, mixed> $config Experiment config.
	 * @return bool
	 */
	public static function is_assignment_only( array $config ) {
		if ( ! empty( $config['assignment_only'] ) ) {
			return true;
		}
		if ( isset( $config['winner_mode'] ) && 'traffic_only' === sanitize_key( (string) $config['winner_mode'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Primary goal id used for winner calculation.
	 *
	 * @param array<string, mixed> $config Experiment config.
	 * @return string
	 */
	public static function get_primary_goal_id( array $config ) {
		if ( self::is_assignment_only( $config ) ) {
			return '';
		}
		if ( ! empty( $config['primary_goal_id'] ) ) {
			return sanitize_key( (string) $config['primary_goal_id'] );
		}
		$goals = isset( $config['goals'] ) && is_array( $config['goals'] ) ? $config['goals'] : array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			if ( ! empty( $g['is_primary'] ) ) {
				return sanitize_key( (string) $g['goal_id'] );
			}
		}
		if ( ! empty( $goals[0]['goal_id'] ) && is_array( $goals[0] ) ) {
			return sanitize_key( (string) $goals[0]['goal_id'] );
		}
		return '';
	}

	/**
	 * Human label for the primary goal.
	 *
	 * @param array<string, mixed> $config Experiment config.
	 * @return string
	 */
	public static function get_primary_goal_label( array $config ) {
		$pid = self::get_primary_goal_id( $config );
		if ( '' === $pid ) {
			return self::is_assignment_only( $config )
				? __( 'Traffic split only', 'reactwoo-geo-optimise' )
				: '—';
		}
		$goals = isset( $config['goals'] ) && is_array( $config['goals'] ) ? $config['goals'] : array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			if ( sanitize_key( (string) $g['goal_id'] ) === $pid ) {
				return isset( $g['label'] ) ? (string) $g['label'] : $pid;
			}
			if ( ! empty( $g['logical_goal_id'] ) && sanitize_key( (string) $g['logical_goal_id'] ) === $pid ) {
				return isset( $g['label'] ) ? (string) $g['label'] : $pid;
			}
		}
		return $pid;
	}
}
