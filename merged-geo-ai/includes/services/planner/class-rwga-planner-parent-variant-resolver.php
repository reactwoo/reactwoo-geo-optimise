<?php
/**
 * Parent variant instruction + child one/other clause expansion.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Parent_Variant_Resolver {

	/** @var array<string,int> */
	const COUNT_MAP = array(
		'one'   => 1,
		'two'   => 2,
		'three' => 3,
		'four'  => 4,
		'five'  => 5,
		'1'     => 1,
		'2'     => 2,
		'3'     => 3,
		'4'     => 4,
		'5'     => 5,
	);

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<string,mixed>|null
	 */
	public static function detect_parent( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		$patterns = array(
			'/\b(?:can you\s+)?(?:create|make|build)\s+(?:an?\s+)?(?:additional\s+)?(?:(\d+|one|two|three|four|five))\s+(?:new\s+)?variants?\s+of\s+(?:the\s+)?(shop(?:\s+page)?|home\s*page|homepage|landing(?:\s+page)?|pricing(?:\s+page)?|contact(?:\s+page)?|checkout|cart)\b/i',
			'/\b(?:can you\s+)?(?:create|make|build)\s+(?:(\d+|one|two|three|four|five))\s+(?:new\s+)?(?:versions?|variations?)\s+of\s+(?:the\s+)?(shop(?:\s+page)?|home\s*page|homepage|landing(?:\s+page)?|pricing(?:\s+page)?|contact(?:\s+page)?|checkout|cart)\b/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( ! preg_match( $pattern, $phrase, $m ) ) {
				continue;
			}
			$count_key = strtolower( (string) $m[1] );
			$page_raw  = trim( (string) $m[2] );
			$page      = self::normalise_page_slug( $page_raw );

			return array(
				'type'            => 'create_variants',
				'count'           => self::COUNT_MAP[ $count_key ] ?? (int) $count_key,
				'sourcePage'      => $page,
				'sourcePageLabel' => self::page_label( $page_raw, $page ),
			);
		}

		return null;
	}

	/**
	 * @param string              $phrase Normalised phrase (variant block only).
	 * @param array<string,mixed> $parent Parent row.
	 * @return array<int,string>
	 */
	public static function split_child_clauses( $phrase, array $parent ) {
		if ( class_exists( 'RWGA_Planner_Ordinal_Variant_Resolver', false ) ) {
			$ordinal = RWGA_Planner_Ordinal_Variant_Resolver::split_child_clauses( $phrase, $parent );
			if ( count( $ordinal ) >= 2 ) {
				return $ordinal;
			}
		}

		return self::split_one_other_child_clauses( $phrase, $parent );
	}

	/**
	 * @param string              $phrase Normalised phrase (variant block only).
	 * @param array<string,mixed> $parent Parent row.
	 * @return array<int,string>
	 */
	private static function split_one_other_child_clauses( $phrase, array $parent ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		$page_slug = preg_quote( (string) ( $parent['sourcePage'] ?? '' ), '/' );
		$page_alt  = preg_quote( (string) ( $parent['sourcePageLabel'] ?? '' ), '/' );
		$page_alt  = str_replace( '\ ', '\s+', $page_alt );

		$remainder = preg_replace(
			'/^.*?\bvariants?\s+of\s+(?:the\s+)?(?:' . $page_slug . '(?:\s+page)?|' . $page_alt . ')\s*(?:[—–-]\s*|[,:]\s*)/i',
			'',
			$phrase
		);
		$remainder = trim( (string) $remainder );
		if ( '' === $remainder ) {
			return array();
		}

		$parts = preg_split( '/\s+and\s+the\s+other\s+/i', $remainder, 2 );
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return array();
		}

		$first  = trim( (string) $parts[0] );
		$second = trim( (string) $parts[1] );

		if ( ! preg_match( '/^(?:one|the first|first)\b/i', $first ) ) {
			$first = 'one ' . ltrim( $first );
		}
		$second = 'the other ' . ltrim( preg_replace( '/^(?:should|will|would)\b/i', '', $second ) );

		return array(
			trim( $first, " \t\n\r\0\x0B," ),
			trim( $second, " \t\n\r\0\x0B." ),
		);
	}

	/**
	 * @param string              $child_clause Child clause text.
	 * @param array<string,mixed> $parent       Parent instruction.
	 * @param array<int,array>    $entities     Entities.
	 * @param int|null            $index        Variant index.
	 * @return array<string,mixed>
	 */
	public static function build_child_action( $child_clause, array $parent, array $entities, $index = null ) {
		$child_clause = trim( (string) $child_clause );
		$index        = null === $index ? self::child_ordinal( $child_clause ) : (int) $index;

		$target = array(
			'type'   => 'page',
			'label'  => (string) ( $parent['sourcePageLabel'] ?? $parent['sourcePage'] ?? 'page' ),
			'slug'   => (string) ( $parent['sourcePage'] ?? 'page' ),
			'source' => 'parent_variant',
		);

		$type_row   = self::child_type_row( $child_clause );
		$cond       = RWGA_Planner_Condition_Resolver::resolve( $child_clause, $entities );
		$unresolved = array(
			'audiences' => (array) ( $cond['unresolved']['audiences'] ?? array() ),
			'campaigns' => array(),
		);

		return array(
			'id'                  => RWGA_Geo_Assistant_Planner::new_id(),
			'type'                => RWGA_Geo_Action_Types::CREATE_VARIANT,
			'target'              => $target,
			'variant'             => array(
				'index'        => $index,
				'label'        => self::variant_label( $target, $cond, $child_clause ),
				'sourcePage'   => (string) ( $parent['sourcePageLabel'] ?? $parent['sourcePage'] ?? '' ),
				'relationship' => 'variant',
			),
			'conditions'          => $cond['conditions'],
			'location_labels'     => $cond['location_labels'] ?? array(),
			'warnings'            => $cond['warnings'] ?? array(),
			'operation'           => array(
				'visibility' => (string) $type_row['visibility'],
				'mode'       => 'create',
			),
			'confidence'          => (float) $cond['confidence'],
			'needsClarification'  => ! empty( $unresolved['audiences'] ),
			'clarificationReason' => ! empty( $unresolved['audiences'] ) ? 'audience_not_defined' : null,
			'sourceClause'        => $child_clause,
			'parentInstruction'   => $parent,
			'unresolved'          => $unresolved,
		);
	}

	/**
	 * @param string              $phrase   Variant block phrase.
	 * @param array<string,mixed> $parent   Parent instruction.
	 * @param array<int,array>    $entities Entities.
	 * @return array<int,array<string,mixed>>
	 */
	public static function expand_children( $phrase, array $parent, array $entities ) {
		$children = self::split_child_clauses( $phrase, $parent );
		if ( count( $children ) < 2 ) {
			return array();
		}

		$actions = array();
		foreach ( $children as $idx => $child_clause ) {
			$actions[] = self::build_child_action( $child_clause, $parent, $entities, $idx + 1 );
		}
		return $actions;
	}

	/**
	 * @param string $clause Child clause.
	 * @return int
	 */
	private static function child_ordinal( $clause ) {
		if ( preg_match( '/^(?:the other|another|the second|second)\b/i', $clause ) ) {
			return 2;
		}
		return 1;
	}

	/**
	 * @param string $clause Child clause.
	 * @return bool
	 */
	public static function is_variant_child_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		if ( class_exists( 'RWGA_Planner_Ordinal_Variant_Resolver', false )
			&& RWGA_Planner_Ordinal_Variant_Resolver::is_ordinal_child_clause( $clause ) ) {
			return true;
		}
		return (bool) preg_match( '/^(?:one|the other|another|the first|the second|first|second)\b/i', $clause );
	}

	/**
	 * @param string $clause Clause.
	 * @return array{type:string,visibility:string,mode:string,confidence:float}
	 */
	private static function child_type_row( $clause ) {
		$visibility = preg_match( '/\bonly\s+(?:for|show|display)\b|\b(?:only|just)\s+(?:show|display)\b|\bshow\s+only\b|\bonly\s+show\b/i', $clause )
			? 'only_show'
			: 'show';
		return array(
			'type'       => RWGA_Geo_Action_Types::CREATE_VARIANT,
			'visibility' => $visibility,
			'mode'       => 'create',
			'confidence' => 0.9,
		);
	}

	/**
	 * @param array<string,mixed> $target Target.
	 * @param array<string,mixed> $cond   Condition bundle.
	 * @param string              $clause Child clause.
	 * @return string
	 */
	private static function variant_label( array $target, array $cond, $clause ) {
		$page_label = ucfirst( (string) ( $target['label'] ?? 'page' ) );
		$include = RWGA_Planner_Condition_Polarity_Resolver::include_group( $cond['conditions'] ?? array() );
		$loc_parts  = array();
		if ( ! empty( $cond['location_labels'] ) ) {
			$loc_parts = (array) $cond['location_labels'];
		} else {
			$loc = RWGA_Planner_Location_Resolver::display_label(
				array(
					'countries' => $include['countries'] ?? array(),
					'regions'   => $include['regions'] ?? array(),
					'labels'    => array(),
				)
			);
			if ( '' !== $loc ) {
				$loc_parts[] = $loc;
			}
		}
		$devices = (array) ( $include['devices'] ?? array() );
		if ( ! empty( $devices ) ) {
			$loc_parts[] = ucfirst( implode( ' + ', $devices ) );
		}
		$weather = array_values( array_filter( (array) ( $include['weather'] ?? array() ) ) );
		if ( ! empty( $weather ) ) {
			$loc_parts[] = ucfirst( (string) $weather[0] );
		}
		return $page_label . ( $loc_parts ? ' - ' . implode( ' ', $loc_parts ) : '' );
	}

	/**
	 * @param string $token Raw page token.
	 * @param string $slug  Normalised slug.
	 * @return string
	 */
	private static function page_label( $token, $slug ) {
		$token = strtolower( trim( (string) $token ) );
		if ( preg_match( '/\s+page$/', $token ) ) {
			return $token;
		}
		if ( in_array( $slug, array( 'pricing', 'contact', 'shop', 'landing' ), true ) ) {
			return $slug . ' page';
		}
		return $slug;
	}

	/**
	 * @param string $token Page token.
	 * @return string
	 */
	private static function normalise_page_slug( $token ) {
		$token = strtolower( trim( (string) $token ) );
		if ( in_array( $token, array( 'shop', 'shop page' ), true ) ) {
			return 'shop';
		}
		if ( in_array( $token, array( 'homepage', 'home page', 'home' ), true ) ) {
			return 'homepage';
		}
		if ( in_array( $token, array( 'pricing', 'pricing page' ), true ) ) {
			return 'pricing';
		}
		if ( in_array( $token, array( 'contact', 'contact page' ), true ) ) {
			return 'contact';
		}
		if ( in_array( $token, array( 'landing', 'landing page' ), true ) ) {
			return 'landing';
		}
		return $token;
	}
}
