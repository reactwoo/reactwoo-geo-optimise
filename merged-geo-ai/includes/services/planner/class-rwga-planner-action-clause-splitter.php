<?php
/**
 * Split user input into action-owning clauses without breaking paired variant groups.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Action_Clause_Splitter {

	/** @var array<int,string> */
	const HARD_BOUNDARIES = array(
		'and then',
		'then',
		'also',
		'plus',
		'as well as',
		'after that',
		'next',
	);

	/** @var array<int,string> */
	const VARIANT_PAIR_MARKERS = array(
		'one variant',
		'another variant',
		'the other',
		'second variant',
		'third variant',
		'first one',
		'second one',
		'one will',
		'one should',
		'the other will',
		'the other should',
		'one for',
		'another for',
	);

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array{raw:string,index:int}>
	 */
	public static function split( $phrase ) {
		$phrase = trim( (string) $phrase );
		if ( '' === $phrase ) {
			return array();
		}

		if ( class_exists( 'RWGA_Planner_Confirmation_Instruction_Resolver', false ) ) {
			$confirmation = RWGA_Planner_Confirmation_Instruction_Resolver::extract( $phrase );
			$phrase       = (string) ( $confirmation['phrase'] ?? $phrase );
			if ( '' === $phrase ) {
				return array();
			}
		}

		$peeled   = self::peel_trailing_actions( $phrase );
		$block    = (string) ( $peeled['variant_block'] ?? $phrase );
		$trailing = is_array( $peeled['trailing'] ?? null ) ? $peeled['trailing'] : array();
		$clauses  = array();

		if ( class_exists( 'RWGA_Planner_Narrative_Clause_Splitter', false ) ) {
			$narrative = RWGA_Planner_Narrative_Clause_Splitter::split( $block );
			if ( count( $narrative ) >= 2 ) {
				return self::merge_modifier_preamble( self::append_trailing_clauses( $narrative, $trailing ) );
			}
		}

		$parent = class_exists( 'RWGA_Planner_Parent_Variant_Resolver', false )
			? RWGA_Planner_Parent_Variant_Resolver::detect_parent( $block )
			: null;
		$children = ( is_array( $parent ) && class_exists( 'RWGA_Planner_Parent_Variant_Resolver', false ) )
			? RWGA_Planner_Parent_Variant_Resolver::split_child_clauses( $block, $parent )
			: array();

		if ( is_array( $parent ) && count( $children ) >= 2 ) {
			foreach ( $children as $idx => $child ) {
				$clauses[] = array(
					'raw'        => $child,
					'index'      => count( $clauses ),
					'type'       => 'variant_child',
					'parent'     => $parent,
					'childIndex' => $idx + 1,
				);
			}
		} else {
			$segments     = self::split_rule_segments( $block );
			$main_clauses = self::coarse_split( $segments['main'] );
			if ( count( $main_clauses ) < 1 ) {
				$main_clauses = array(
					array(
						'raw'   => $segments['main'],
						'index' => 0,
					),
				);
			}

			foreach ( $main_clauses as $row ) {
				$raw = trim( (string) ( $row['raw'] ?? '' ) );

				if ( class_exists( 'RWGA_Variant_Plan_Parser', false )
					&& RWGA_Variant_Plan_Parser::is_variant_plan_command( $raw ) ) {
					$variant_segments = RWGA_Variant_Plan_Parser::split_segments( $raw );
					if ( count( $variant_segments ) >= 2 ) {
						foreach ( self::segments_to_clauses( $variant_segments ) as $segment_clause ) {
							$segment_clause['index'] = count( $clauses );
							$clauses[]               = $segment_clause;
						}
						continue;
					}
				}

				$clauses[] = array(
					'raw'   => $raw,
					'index' => count( $clauses ),
				);
			}

			foreach ( $segments['rules'] as $rule_clause ) {
				$clauses[] = array(
					'raw'   => $rule_clause,
					'index' => count( $clauses ),
					'type'  => 'rule',
				);
			}
		}

		foreach ( $trailing as $trail_row ) {
			if ( ! is_array( $trail_row ) ) {
				continue;
			}
			$raw = trim( (string) ( $trail_row['raw'] ?? '' ) );
			if ( '' === $raw ) {
				continue;
			}
			$clauses[] = array(
				'raw'   => $raw,
				'index' => count( $clauses ),
				'type'  => (string) ( $trail_row['type'] ?? 'rule' ),
			);
		}

		if ( empty( $clauses ) ) {
			return array(
				array(
					'raw'   => $phrase,
					'index' => 0,
				),
			);
		}

		return self::merge_modifier_preamble( self::filter_confirmation_clauses( $clauses ) );
	}

	/**
	 * Drop confirmation-only clauses that must not become actions.
	 *
	 * @param array<int,array<string,mixed>> $clauses Clause rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function filter_confirmation_clauses( array $clauses ) {
		if ( ! class_exists( 'RWGA_Planner_Confirmation_Instruction_Resolver', false ) ) {
			return $clauses;
		}
		$filtered = array();
		foreach ( $clauses as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( RWGA_Planner_Confirmation_Instruction_Resolver::is_confirmation_only( (string) ( $row['raw'] ?? '' ) ) ) {
				continue;
			}
			$filtered[] = $row;
		}
		return $filtered;
	}

	/**
	 * Re-attach a leading modifier preamble ("For the Spring Promo campaign,")
	 * to the following action clause so it does not become a standalone action.
	 *
	 * @param array<int,array<string,mixed>> $clauses Clauses.
	 * @return array<int,array<string,mixed>>
	 */
	private static function merge_modifier_preamble( array $clauses ) {
		if ( count( $clauses ) < 2 ) {
			return $clauses;
		}

		$first = trim( (string) ( $clauses[0]['raw'] ?? '' ) );
		if ( '' === $first ) {
			return $clauses;
		}

		// A preamble starts with a scoping preposition but carries no action verb of its own.
		$is_preamble = preg_match( '/^(?:for|in|on|using|with|during)\b/i', $first )
			&& ! preg_match( '/\b(?:update|create|change|show|hide|display|make|test|diagnose|preview|set|add|remove|exclude|build|duplicate|clone|target)\b/i', $first );
		if ( ! $is_preamble ) {
			return $clauses;
		}

		$clauses[1]['raw'] = $first . ', ' . trim( (string) ( $clauses[1]['raw'] ?? '' ) );
		array_splice( $clauses, 0, 1 );
		foreach ( $clauses as $i => $row ) {
			$clauses[ $i ]['index'] = $i;
		}

		return $clauses;
	}

	/**
	 * @param array<int,array<string,mixed>> $clauses  Base clauses.
	 * @param array<int,array<string,mixed>> $trailing Trailing rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function append_trailing_clauses( array $clauses, array $trailing ) {
		foreach ( $trailing as $trail_row ) {
			if ( ! is_array( $trail_row ) ) {
				continue;
			}
			$raw = trim( (string) ( $trail_row['raw'] ?? '' ) );
			if ( '' === $raw ) {
				continue;
			}
			$clauses[] = array(
				'raw'   => $raw,
				'index' => count( $clauses ),
				'type'  => (string) ( $trail_row['type'] ?? 'rule' ),
			);
		}
		return $clauses;
	}

	/**
	 * Peel rule/test/diagnose clauses off the end of a variant block.
	 *
	 * @param string $phrase Normalised phrase.
	 * @return array{variant_block:string,trailing:array<int,array{raw:string,type:string}>}
	 */
	private static function peel_trailing_actions( $phrase ) {
		$block    = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		$trailing = array();

		if ( preg_match( '/^(.*?)(?:[,.]\s*|\s*,\s*)(?:and\s+)?(?:test\s+what|check\s+what)\b(.+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => 'test what ' . trim( (string) $m[2] ),
				'type' => 'test',
			);
		}

		if ( preg_match( '/^(.*?)(?:[,.]\s*|\s*,\s*)(?:and\s+)?(?:preview|see|simulate)\s+(?:what|how)\b(.+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => 'preview what' . ( ' ' . ltrim( (string) $m[2] ) ),
				'type' => 'diagnose',
			);
		}

		if ( preg_match( '/^(.*?)(?:[,.]\s*|\s*,\s*)(?:also|and)\s+(hide|show)\s+(?:the\s+)?(.+)$/is', $block, $m ) ) {
			$block = trim( (string) $m[1] );
			array_unshift(
				$trailing,
				array(
					'raw'  => trim( (string) $m[2] ) . ' the ' . trim( (string) $m[3] ),
					'type' => 'rule',
				)
			);
		}

		if ( preg_match( '/^(.*?)(?:[,.]\s*|\s*,\s*)(?:also|and)\s+create\s+(?:a\s+)?rule\b(.+)$/is', $block, $m ) ) {
			$block = trim( (string) $m[1] );
			array_unshift(
				$trailing,
				array(
					'raw'  => 'create a rule' . trim( (string) $m[2] ),
					'type' => 'rule',
				)
			);
		}

		if ( preg_match( '/^(.*?)(?:[,.]\s*|\s*,\s*)(?:also|and)\s+diagnose\b(.+)$/is', $block, $m ) ) {
			$block = trim( (string) $m[1] );
			array_unshift(
				$trailing,
				array(
					'raw'  => 'diagnose' . trim( (string) $m[2] ),
					'type' => 'diagnose',
				)
			);
		}

		if ( preg_match( '/^(.*?)[,.]\s*then\s+(hide|show)\s+(.+)$/is', $block, $m ) ) {
			$block = trim( (string) $m[1] );
			array_unshift(
				$trailing,
				array(
					'raw'  => trim( (string) $m[2] ) . ' ' . trim( (string) $m[3] ),
					'type' => 'rule',
				)
			);
		}

		if ( preg_match( '/^(.*?)(?:\s*-\s*|\s*,\s*)(?:update|change)\s+(?:the\s+)?original\b(.+)$/is', $block, $m ) ) {
			$block = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => 'update the original' . trim( (string) $m[2] ),
				'type' => 'update',
			);
		}

		return array(
			'variant_block' => trim( $block ),
			'trailing'      => $trailing,
		);
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array{main:string,rules:array<int,string>}
	 */
	private static function split_rule_segments( $phrase ) {
		$rules = array();
		$main  = $phrase;

		if ( preg_match( '/^(.*?)(?:\.\s*)?\balso\s+create\s+(?:a\s+)?rule\b(.+)$/i', $phrase, $m ) ) {
			$main = trim( (string) $m[1] );
			$rules[] = trim( 'create a rule' . (string) $m[2] );
		}

		return array(
			'main'  => $main,
			'rules' => $rules,
		);
	}

	/**
	 * @param string $phrase Phrase.
	 * @return array<int,array{raw:string,index:int}>
	 */
	private static function coarse_split( $phrase ) {
		if ( class_exists( 'RWGA_Rule_Plan_Parser', false )
			&& RWGA_Rule_Plan_Parser::is_rule_plan_command( $phrase ) ) {
			return array();
		}

		$pattern = '/(?:^|[,.;]|\s+-\s+|\s*[—–]\s+|\band then\b|\bthen\b)(?=\s*(?:update|create|change|show|hide|only show|display|make|test|diagnose)\b)/i';
		$parts   = preg_split( $pattern, $phrase, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return array();
		}

		$clauses = array();
		foreach ( array_values( array_filter( array_map( 'trim', $parts ) ) ) as $idx => $raw ) {
			if ( '' === $raw ) {
				continue;
			}
			$clauses[] = array(
				'raw'   => $raw,
				'index' => $idx,
			);
		}
		return $clauses;
	}

	/**
	 * @param array<int,array<string,mixed>> $segments Parser segments.
	 * @return array<int,array{raw:string,index:int}>
	 */
	private static function segments_to_clauses( array $segments ) {
		$clauses = array();
		foreach ( $segments as $idx => $segment ) {
			$raw = trim( (string) ( $segment['raw'] ?? '' ) );
			if ( '' === $raw ) {
				continue;
			}
			$clauses[] = array(
				'raw'   => $raw,
				'index' => $idx,
				'type'  => (string) ( $segment['type'] ?? '' ),
			);
		}
		return $clauses;
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function has_variant_pair_marker( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		foreach ( self::VARIANT_PAIR_MARKERS as $marker ) {
			if ( false !== strpos( $clause, $marker ) ) {
				return true;
			}
		}
		return (bool) preg_match( '/\bone\s+(?:variant|version|variation)\b.*\bthe\s+other\b/i', $clause );
	}
}
