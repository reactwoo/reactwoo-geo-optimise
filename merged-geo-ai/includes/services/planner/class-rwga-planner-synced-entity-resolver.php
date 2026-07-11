<?php
/**
 * Resolve audience / campaign phrases against the site's synced registry.
 *
 * Audiences and ad campaigns must never be invented from natural language. They
 * are only valid when they exist in the site's synced data (GA4, Google Ads,
 * Meta Ads, CRM, etc.). This resolver reads that registry from the planner
 * context (or entity rows) and matches a raw phrase against it, returning an
 * explicit status so the assistant can ask for clarification instead of
 * fabricating a slug.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Synced_Entity_Resolver {

	const STATUS_MATCHED          = 'matched';
	const STATUS_AMBIGUOUS        = 'ambiguous';
	const STATUS_NOT_DEFINED      = 'not_defined';
	const STATUS_SYNC_UNAVAILABLE = 'sync_unavailable';

	/** Minimum similarity (0-1) before a registry item is offered as a suggestion. */
	const SUGGESTION_THRESHOLD = 0.5;

	/**
	 * Normalised synced audiences for this site.
	 *
	 * @param array<string,mixed> $context  Planner context.
	 * @param array<int,array>    $entities Entity rows.
	 * @return array<int,array{id:string,name:string,source:string,aliases:array<int,string>}>
	 */
	public static function audiences( array $context = array(), array $entities = array() ) {
		return self::registry( $context, $entities, 'syncedAudiences', 'audience' );
	}

	/**
	 * Normalised synced campaigns for this site.
	 *
	 * @param array<string,mixed> $context  Planner context.
	 * @param array<int,array>    $entities Entity rows.
	 * @return array<int,array{id:string,name:string,source:string,aliases:array<int,string>}>
	 */
	public static function campaigns( array $context = array(), array $entities = array() ) {
		return self::registry( $context, $entities, 'syncedCampaigns', 'campaign' );
	}

	/**
	 * Resolve a single raw phrase against a registry.
	 *
	 * @param string                                                                          $raw         Raw phrase.
	 * @param array<int,array{id:string,name:string,source:string,aliases:array<int,string>}> $registry    Registry.
	 * @param string|null                                                                     $source_hint Preferred source.
	 * @return array{raw:string,status:string,matched?:array,suggestions:array<int,array>,message:string,sourceHint?:string}
	 */
	public static function resolve_phrase( $raw, array $registry, $source_hint = null ) {
		$raw  = trim( (string) $raw );
		$norm = self::normalise( $raw );

		if ( empty( $registry ) ) {
			return array(
				'raw'         => $raw,
				'status'      => self::STATUS_SYNC_UNAVAILABLE,
				'suggestions' => array(),
				'message'     => __( 'No synced list is available for this site yet.', 'reactwoo-geo-ai' ),
			);
		}

		foreach ( $registry as $item ) {
			foreach ( self::candidate_strings( $item ) as $candidate ) {
				if ( self::normalise( $candidate ) === $norm && '' !== $norm ) {
					return array(
						'raw'         => $raw,
						'status'      => self::STATUS_MATCHED,
						'matched'     => array(
							'id'     => (string) $item['id'],
							'name'   => (string) $item['name'],
							'source' => (string) $item['source'],
						),
						'suggestions' => array(),
						'message'     => '',
					);
				}
			}
		}

		$suggestions = array();
		foreach ( $registry as $item ) {
			$best = 0.0;
			foreach ( self::candidate_strings( $item ) as $candidate ) {
				$best = max( $best, self::similarity( $norm, self::normalise( $candidate ) ) );
			}
			if ( null !== $source_hint && '' !== (string) $source_hint
				&& (string) $item['source'] !== (string) $source_hint ) {
				$best *= 0.85;
			}
			if ( $best >= self::SUGGESTION_THRESHOLD ) {
				$suggestions[] = array(
					'id'         => (string) $item['id'],
					'name'       => (string) $item['name'],
					'source'     => (string) $item['source'],
					'confidence' => round( $best, 2 ),
				);
			}
		}

		usort(
			$suggestions,
			static function ( $a, $b ) {
				return ( $b['confidence'] <=> $a['confidence'] );
			}
		);
		$suggestions = array_slice( $suggestions, 0, 5 );

		$out = array(
			'raw'         => $raw,
			'status'      => empty( $suggestions ) ? self::STATUS_NOT_DEFINED : self::STATUS_AMBIGUOUS,
			'suggestions' => $suggestions,
			'message'     => empty( $suggestions )
				? __( 'No matching synced entry was found for this site.', 'reactwoo-geo-ai' )
				: __( 'Multiple possible synced entries were found. Please choose one.', 'reactwoo-geo-ai' ),
		);
		if ( null !== $source_hint && '' !== (string) $source_hint ) {
			$out['sourceHint'] = (string) $source_hint;
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $context     Context.
	 * @param array<int,array>    $entities    Entities.
	 * @param string              $context_key Context registry key.
	 * @param string              $entity_type Entity type for fallback.
	 * @return array<int,array{id:string,name:string,source:string,aliases:array<int,string>}>
	 */
	private static function registry( array $context, array $entities, $context_key, $entity_type ) {
		$rows = array();
		if ( isset( $context[ $context_key ] ) && is_array( $context[ $context_key ] ) ) {
			foreach ( $context[ $context_key ] as $row ) {
				$normalised = self::normalise_context_row( $row );
				if ( null !== $normalised ) {
					$rows[] = $normalised;
				}
			}
			return $rows;
		}

		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) || (string) ( $row['entity_type'] ?? '' ) !== $entity_type ) {
				continue;
			}
			$normalised = self::normalise_entity_row( $row );
			if ( null !== $normalised ) {
				$rows[] = $normalised;
			}
		}
		return $rows;
	}

	/**
	 * @param mixed $row Context row.
	 * @return array{id:string,name:string,source:string,aliases:array<int,string>}|null
	 */
	private static function normalise_context_row( $row ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$name = trim( (string) ( $row['name'] ?? $row['label'] ?? '' ) );
		if ( '' === $name ) {
			return null;
		}
		return array(
			'id'      => (string) ( $row['id'] ?? $row['slug'] ?? sanitize_title( $name ) ),
			'name'    => $name,
			'source'  => (string) ( $row['source'] ?? '' ),
			'aliases' => array_values( array_filter( array_map( 'strval', (array) ( $row['aliases'] ?? array() ) ) ) ),
		);
	}

	/**
	 * @param array<string,mixed> $row Entity row.
	 * @return array{id:string,name:string,source:string,aliases:array<int,string>}|null
	 */
	private static function normalise_entity_row( array $row ) {
		$name = trim( (string) ( $row['display_name'] ?? $row['value'] ?? $row['entity_key'] ?? '' ) );
		if ( '' === $name ) {
			return null;
		}
		$source = (string) ( $row['source'] ?? '' );
		if ( '' === $source && isset( $row['meta'] ) && is_array( $row['meta'] ) ) {
			$source = (string) ( $row['meta']['source'] ?? '' );
		}
		return array(
			'id'      => (string) ( $row['entity_key'] ?? $row['id'] ?? sanitize_title( $name ) ),
			'name'    => $name,
			'source'  => $source,
			'aliases' => array_values( array_filter( array_map( 'strval', (array) ( $row['aliases'] ?? array() ) ) ) ),
		);
	}

	/**
	 * @param array{id:string,name:string,source:string,aliases:array<int,string>} $item Registry item.
	 * @return array<int,string>
	 */
	private static function candidate_strings( array $item ) {
		$candidates = array( (string) $item['name'] );
		foreach ( (array) ( $item['aliases'] ?? array() ) as $alias ) {
			$candidates[] = (string) $alias;
		}
		return array_values( array_filter( $candidates, static function ( $c ) {
			return '' !== trim( (string) $c );
		} ) );
	}

	/**
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function normalise( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = (string) preg_replace( '/[^a-z0-9]+/', ' ', $value );
		return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * Token-aware similarity in the range 0-1.
	 *
	 * @param string $a First normalised value.
	 * @param string $b Second normalised value.
	 * @return float
	 */
	private static function similarity( $a, $b ) {
		if ( '' === $a || '' === $b ) {
			return 0.0;
		}
		if ( $a === $b ) {
			return 1.0;
		}

		$percent = 0.0;
		similar_text( $a, $b, $percent );
		$char_sim = $percent / 100;

		$tokens_a = array_filter( explode( ' ', $a ) );
		$tokens_b = array_filter( explode( ' ', $b ) );
		$jaccard  = 0.0;
		if ( ! empty( $tokens_a ) && ! empty( $tokens_b ) ) {
			$intersect = array_intersect( $tokens_a, $tokens_b );
			$union     = array_unique( array_merge( $tokens_a, $tokens_b ) );
			$jaccard   = count( $union ) > 0 ? count( $intersect ) / count( $union ) : 0.0;
		}

		return max( $char_sim, $jaccard );
	}
}
