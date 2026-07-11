<?php
/**
 * Local learned interpretation patterns (variant pair splits, etc.).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Learned_Patterns {

	const OPTION_KEY = 'rwga_geo_assistant_learned_patterns';

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<string,mixed>|null
	 */
	public static function match( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		foreach ( self::all() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$example = RWGA_Local_Intent_Interpreter::normalise( (string) ( $row['rawExample'] ?? '' ) );
			if ( '' !== $example && $example === $phrase ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $plan Confirmed plan.
	 * @return void
	 */
	public static function save_from_plan( array $plan ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( (string) ( $plan['sourceText'] ?? '' ) );
		if ( '' === $phrase || empty( $plan['actions'] ) || ! is_array( $plan['actions'] ) ) {
			return;
		}

		if ( count( $plan['actions'] ) >= 2
			&& preg_match( '/\bone\b.*\bthe\s+other\b/i', $phrase ) ) {
			$groups = array();
			foreach ( $plan['actions'] as $action ) {
				if ( ! is_array( $action ) ) {
					continue;
				}
				$conds = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
				$groups[] = array_merge(
					(array) ( $conds['countries'] ?? array() ),
					(array) ( $conds['regions'] ?? array() )
				);
			}
			self::upsert(
				array(
					'patternType'     => 'variant_pair_split',
					'rawExample'      => $phrase,
					'interpretation'  => array(
						'splitStrategy' => 'variant_pair',
						'groups'        => $groups,
					),
					'confidenceBoost' => 0.12,
					'createdAt'       => time(),
				)
			);
		}
	}

	/**
	 * @param array<string,mixed> $row Pattern row.
	 * @return void
	 */
	public static function upsert( array $row ) {
		$all   = self::all();
		$found = false;
		foreach ( $all as $idx => $existing ) {
			if ( ! is_array( $existing ) ) {
				continue;
			}
			if ( (string) ( $existing['rawExample'] ?? '' ) === (string) ( $row['rawExample'] ?? '' ) ) {
				$all[ $idx ] = array_merge( $existing, $row );
				$found       = true;
				break;
			}
		}
		if ( ! $found ) {
			$all[] = $row;
		}
		update_option( self::OPTION_KEY, array_slice( $all, -100 ), false );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}
}
