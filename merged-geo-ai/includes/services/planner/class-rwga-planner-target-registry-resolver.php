<?php
/**
 * Resolve action targets (pages, categories, products, popups, banners, …) against
 * the site's known target registries.
 *
 * Targets must not be assumed valid just because the parser produced a label. They
 * are validated against registries supplied via planner context (`targets`) or
 * entity rows, plus server-side fallbacks (WordPress pages and WooCommerce product
 * categories). When a registry exists but nothing matches, the target is flagged
 * `not_found` (with fuzzy suggestions). When no registry is available for that
 * target type, it is flagged `registry_unavailable` so the assistant can show it
 * without hard-blocking on data it cannot verify.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Target_Registry_Resolver {

	const STATUS_MATCHED              = 'matched';
	const STATUS_AMBIGUOUS            = 'ambiguous';
	const STATUS_NOT_FOUND            = 'not_found';
	const STATUS_REGISTRY_UNAVAILABLE = 'registry_unavailable';

	/**
	 * Map a target type to its registry group key.
	 *
	 * @var array<string,string>
	 */
	const TYPE_GROUPS = array(
		'page'          => 'pages',
		'category'      => 'categories',
		'category_page' => 'categories',
		'product'       => 'products',
		'product_page'  => 'products',
		'popup'         => 'popups',
		'banner'        => 'banners',
		'variant'       => 'variants',
		'rule'          => 'rules',
	);

	/**
	 * Resolve a single target against the relevant registry.
	 *
	 * @param array<string,mixed> $target   Target row ({type,label,slug,source}).
	 * @param array<string,mixed> $context  Planner context (may carry `targets`).
	 * @param array<int,array>    $entities Entity rows.
	 * @return array{type:string,raw:string,resolved:?array,status:string,suggestions:array<int,array>}
	 */
	public static function resolve( array $target, array $context = array(), array $entities = array() ) {
		$type  = (string) ( $target['type'] ?? 'page' );
		$raw   = trim( (string) ( $target['label'] ?? '' ) );
		$group = self::TYPE_GROUPS[ $type ] ?? '';

		$base = array(
			'type'        => $type,
			'raw'         => $raw,
			'resolved'    => null,
			'status'      => self::STATUS_REGISTRY_UNAVAILABLE,
			'suggestions' => array(),
		);

		if ( '' === $raw || '' === $group || self::is_generic_label( $raw ) ) {
			return $base;
		}

		$registry = self::registry( $group, $context, $entities );
		if ( empty( $registry ) || ! class_exists( 'RWGA_Planner_Synced_Entity_Resolver', false ) ) {
			return $base;
		}

		$resolution = RWGA_Planner_Synced_Entity_Resolver::resolve_phrase( $raw, $registry );
		$status     = (string) ( $resolution['status'] ?? '' );

		if ( RWGA_Planner_Synced_Entity_Resolver::STATUS_MATCHED === $status ) {
			$base['status']   = self::STATUS_MATCHED;
			$base['resolved'] = array(
				'id'   => (string) ( $resolution['matched']['id'] ?? '' ),
				'name' => (string) ( $resolution['matched']['name'] ?? $raw ),
				'type' => $type,
			);
			return $base;
		}

		$base['suggestions'] = self::normalise_suggestions( (array) ( $resolution['suggestions'] ?? array() ), $type );

		if ( RWGA_Planner_Synced_Entity_Resolver::STATUS_AMBIGUOUS === $status ) {
			$base['status'] = self::STATUS_AMBIGUOUS;
		} elseif ( RWGA_Planner_Synced_Entity_Resolver::STATUS_NOT_DEFINED === $status ) {
			$base['status'] = self::STATUS_NOT_FOUND;
		}

		return $base;
	}

	/**
	 * Build the registry for a target group from context, entities, then server fallbacks.
	 *
	 * @param string              $group    Registry group key.
	 * @param array<string,mixed> $context  Context.
	 * @param array<int,array>    $entities Entities.
	 * @return array<int,array{id:string,name:string,source:string,aliases:array<int,string>}>
	 */
	private static function registry( $group, array $context, array $entities ) {
		$rows = array();

		if ( isset( $context['targets'][ $group ] ) && is_array( $context['targets'][ $group ] ) ) {
			foreach ( $context['targets'][ $group ] as $row ) {
				$normalised = self::normalise_row( $row, $group );
				if ( null !== $normalised ) {
					$rows[] = $normalised;
				}
			}
		}

		$entity_type = self::group_entity_type( $group );
		if ( '' !== $entity_type ) {
			foreach ( $entities as $row ) {
				if ( ! is_array( $row ) || (string) ( $row['entity_type'] ?? '' ) !== $entity_type ) {
					continue;
				}
				$normalised = self::normalise_row(
					array(
						'id'      => $row['entity_key'] ?? $row['id'] ?? '',
						'name'    => $row['display_name'] ?? $row['value'] ?? '',
						'aliases' => $row['aliases'] ?? array(),
					),
					$group
				);
				if ( null !== $normalised ) {
					$rows[] = $normalised;
				}
			}
		}

		if ( empty( $rows ) ) {
			$rows = self::server_fallback( $group );
		}

		return $rows;
	}

	/**
	 * Pull a registry from the live site when context/entities supply nothing.
	 *
	 * @param string $group Registry group key.
	 * @return array<int,array{id:string,name:string,source:string,aliases:array<int,string>}>
	 */
	private static function server_fallback( $group ) {
		$rows = array();

		if ( 'categories' === $group && function_exists( 'get_terms' ) && function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'number'     => 200,
				)
			);
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( ! is_object( $term ) || empty( $term->name ) ) {
						continue;
					}
					$rows[] = array(
						'id'      => (string) ( $term->term_id ?? $term->slug ?? '' ),
						'name'    => (string) $term->name,
						'source'  => 'woocommerce',
						'aliases' => array(
							(string) $term->name . ' category',
							(string) $term->name . ' category page',
						),
					);
				}
			}
		}

		if ( 'pages' === $group && function_exists( 'get_pages' ) ) {
			$pages = get_pages( array( 'number' => 200 ) );
			if ( is_array( $pages ) ) {
				foreach ( $pages as $page ) {
					if ( ! is_object( $page ) || empty( $page->post_title ) ) {
						continue;
					}
					$rows[] = array(
						'id'      => (string) ( $page->ID ?? '' ),
						'name'    => (string) $page->post_title,
						'source'  => 'wordpress',
						'aliases' => array( (string) $page->post_title . ' page' ),
					);
				}
			}
		}

		return $rows;
	}

	/**
	 * @param string $group Registry group key.
	 * @return string Entity type that maps to this group.
	 */
	private static function group_entity_type( $group ) {
		$map = array(
			'pages'      => 'page',
			'categories' => 'category',
			'products'   => 'product',
			'popups'     => 'popup',
			'banners'    => 'banner',
			'variants'   => 'variant',
			'rules'      => 'rule',
		);
		return $map[ $group ] ?? '';
	}

	/**
	 * @param mixed  $row   Raw registry row.
	 * @param string $group Registry group key (drives synthetic aliases).
	 * @return array{id:string,name:string,source:string,aliases:array<int,string>}|null
	 */
	private static function normalise_row( $row, $group = '' ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$name = trim( (string) ( $row['name'] ?? $row['label'] ?? $row['title'] ?? '' ) );
		if ( '' === $name ) {
			return null;
		}
		$aliases = array_values( array_filter( array_map( 'strval', (array) ( $row['aliases'] ?? array() ) ) ) );
		foreach ( self::synthetic_aliases( $name, $group ) as $alias ) {
			if ( ! in_array( $alias, $aliases, true ) ) {
				$aliases[] = $alias;
			}
		}
		return array(
			'id'      => (string) ( $row['id'] ?? $row['slug'] ?? sanitize_title( $name ) ),
			'name'    => $name,
			'source'  => (string) ( $row['source'] ?? '' ),
			'aliases' => $aliases,
		);
	}

	/**
	 * Type-appropriate aliases so phrases like "trainers category page" can match
	 * a registry entry stored simply as "Trainers".
	 *
	 * @param string $name  Entry name.
	 * @param string $group Registry group key.
	 * @return array<int,string>
	 */
	private static function synthetic_aliases( $name, $group ) {
		switch ( $group ) {
			case 'categories':
				return array( $name . ' category', $name . ' category page' );
			case 'pages':
				return array( $name . ' page' );
			case 'products':
				return array( $name . ' product', $name . ' product page' );
			case 'popups':
				return array( $name . ' popup' );
			case 'banners':
				return array( $name . ' banner' );
			default:
				return array();
		}
	}

	/**
	 * @param array<int,array> $suggestions Suggestions from the synced resolver.
	 * @param string           $type        Target type.
	 * @return array<int,array{id:string,name:string,type:string,confidence:float}>
	 */
	private static function normalise_suggestions( array $suggestions, $type ) {
		$out = array();
		foreach ( $suggestions as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = trim( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'         => (string) ( $row['id'] ?? '' ),
				'name'       => $name,
				'type'       => $type,
				'confidence' => (float) ( $row['confidence'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Generic placeholder labels carry no real target to validate.
	 *
	 * @param string $label Target label.
	 * @return bool
	 */
	private static function is_generic_label( $label ) {
		$label = strtolower( trim( (string) $label ) );
		return in_array(
			$label,
			array( '', 'page', 'product', 'product page', 'category', 'category page', 'popup', 'banner', 'variant', 'rule' ),
			true
		);
	}
}
