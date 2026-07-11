<?php
/**
 * Local relationship graph — config edges from snapshot plus page intelligence nodes.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mirrors api.reactwoo.com buildRelationshipGraph with local page/insight enrichment.
 */
class RWGA_Relationship_Graph {

	const VERSION = '1.0.0';

	const MAX_NODES = 500;

	const MAX_EDGES = 1000;

	/**
	 * Build full graph, persist to site context, and return it.
	 *
	 * @param array<string, mixed>|null $snapshot Optional snapshot payload.
	 * @return array<string, mixed>
	 */
	public static function refresh( $snapshot = null ) {
		if ( ! is_array( $snapshot ) && function_exists( 'rwgc_build_ai_snapshot' ) ) {
			$snapshot = rwgc_build_ai_snapshot();
		}
		if ( ! is_array( $snapshot ) ) {
			$snapshot = array();
		}

		$graph = self::build_from_snapshot( $snapshot );
		$graph = self::extend_with_local_intelligence( $graph );
		self::persist( $graph );
		return $graph;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_graph() {
		$row = class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::get_site_context() : null;
		if ( ! is_array( $row ) || empty( $row['context_json'] ) ) {
			return array();
		}
		$ctx = is_string( $row['context_json'] ) ? json_decode( $row['context_json'], true ) : $row['context_json'];
		if ( ! is_array( $ctx ) || empty( $ctx['relationship_graph'] ) || ! is_array( $ctx['relationship_graph'] ) ) {
			return array();
		}
		return $ctx['relationship_graph'];
	}

	/**
	 * @param array<string, mixed> $snapshot Site intelligence snapshot.
	 * @return array<string, mixed>
	 */
	public static function build_from_snapshot( array $snapshot ) {
		$nodes    = array();
		$edges    = array();
		$edge_seq = 0;

		$rules         = self::as_array( $snapshot['rules'] ?? array() );
		$variants      = self::as_array( $snapshot['variants'] ?? array() );
		$popups        = self::as_array( $snapshot['popups'] ?? array() );
		$relationships = self::as_array( $snapshot['relationships'] ?? array() );

		$pro_block = is_array( $snapshot['geocore_pro'] ?? null ) ? $snapshot['geocore_pro'] : array();
		$google    = is_array( $pro_block['google'] ?? null ) ? $pro_block['google'] : array();
		$campaigns = self::as_array( $google['campaigns'] ?? array() );
		$audiences = self::as_array( $google['audiences'] ?? array() );
		$profiles  = self::as_array( $pro_block['profiles'] ?? array() );

		$optimise    = is_array( $snapshot['geo_optimise'] ?? null ) ? $snapshot['geo_optimise'] : array();
		$commerce    = is_array( $snapshot['geo_commerce'] ?? null ) ? $snapshot['geo_commerce'] : array();
		$experiments = self::as_array( $optimise['experiments'] ?? array() );
		$commerce_rules = self::as_array( $commerce['rules'] ?? array() );

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$id = self::row_id( $rule, array( 'rule_id', 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			self::add_node( $nodes, 'rule', $id, self::row_label( $rule, array( 'title', 'name' ), 'Rule ' . $id ) );
		}

		foreach ( $variants as $variant ) {
			if ( ! is_array( $variant ) ) {
				continue;
			}
			$master = isset( $variant['master_page_id'] ) ? (string) $variant['master_page_id'] : ( isset( $variant['master_id'] ) ? (string) $variant['master_id'] : '' );
			$child  = isset( $variant['variant_page_id'] ) ? (string) $variant['variant_page_id'] : '';
			if ( '' !== $master ) {
				self::add_node( $nodes, 'page', $master, self::row_label( $variant, array( 'master_title' ), 'Page ' . $master ) );
			}
			if ( '' !== $child ) {
				self::add_node( $nodes, 'page', $child, self::row_label( $variant, array( 'variant_title' ), 'Variant ' . $child ) );
				if ( '' !== $master ) {
					self::add_edge( $edges, $edge_seq, 'variant_of', self::node_id( 'page', $child ), self::node_id( 'page', $master ) );
				}
			}
		}

		foreach ( $popups as $popup ) {
			if ( ! is_array( $popup ) ) {
				continue;
			}
			$id = self::row_id( $popup, array( 'popup_id', 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			self::add_node( $nodes, 'popup', $id, self::row_label( $popup, array( 'title', 'name' ), 'Popup ' . $id ) );
		}

		foreach ( $campaigns as $campaign ) {
			if ( ! is_array( $campaign ) ) {
				continue;
			}
			$id = self::row_id( $campaign, array( 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			self::add_node( $nodes, 'campaign', $id, self::row_label( $campaign, array( 'name' ), 'Campaign ' . $id ) );
		}

		foreach ( $audiences as $audience ) {
			if ( ! is_array( $audience ) ) {
				continue;
			}
			$id = self::row_id( $audience, array( 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			self::add_node( $nodes, 'audience', $id, self::row_label( $audience, array( 'name' ), 'Audience ' . $id ) );
		}

		foreach ( $profiles as $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}
			$id = self::row_id( $profile, array( 'profile_id', 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			self::add_node( $nodes, 'profile', $id, self::row_label( $profile, array( 'name' ), 'Profile ' . $id ) );
		}

		foreach ( $experiments as $experiment ) {
			if ( ! is_array( $experiment ) ) {
				continue;
			}
			$id = self::row_id( $experiment, array( 'experiment_id', 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			self::add_node( $nodes, 'experiment', $id, self::row_label( $experiment, array( 'name' ), 'Experiment ' . $id ) );
			$source = isset( $experiment['source_page_id'] ) ? (string) $experiment['source_page_id'] : '';
			if ( '' !== $source ) {
				self::add_node( $nodes, 'page', $source, 'Page ' . $source );
				self::add_edge( $edges, $edge_seq, 'tests', self::node_id( 'experiment', $id ), self::node_id( 'page', $source ) );
			}
		}

		foreach ( $commerce_rules as $crule ) {
			if ( ! is_array( $crule ) ) {
				continue;
			}
			$id = self::row_id( $crule, array( 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			self::add_node( $nodes, 'commerce_rule', $id, self::row_label( $crule, array( 'label' ), 'Commerce rule ' . $id ) );
		}

		foreach ( $relationships as $rel ) {
			if ( ! is_array( $rel ) ) {
				continue;
			}
			$from_type = sanitize_key( (string) ( $rel['from_type'] ?? '' ) );
			$from_id   = (string) ( $rel['from_id'] ?? '' );
			$to_type   = sanitize_key( (string) ( $rel['to_type'] ?? '' ) );
			$to_id     = (string) ( $rel['to_id'] ?? '' );
			$rel_type  = sanitize_key( (string) ( $rel['type'] ?? 'linked' ) );
			if ( '' === $from_type || '' === $from_id || '' === $to_type || '' === $to_id ) {
				continue;
			}
			self::add_node( $nodes, $from_type, $from_id, $from_type . ' ' . $from_id );
			self::add_node( $nodes, $to_type, $to_id, $to_type . ' ' . $to_id );
			$meta = is_array( $rel['meta'] ?? null ) ? $rel['meta'] : array();
			self::add_edge( $edges, $edge_seq, $rel_type, self::node_id( $from_type, $from_id ), self::node_id( $to_type, $to_id ), $meta );
		}

		return self::finalize_graph( $snapshot, $nodes, $edges, array(
			'rules'          => count( $rules ),
			'variants'       => count( $variants ),
			'popups'         => count( $popups ),
			'relationships'  => count( $relationships ),
			'campaigns'      => count( $campaigns ),
			'audiences'      => count( $audiences ),
			'profiles'       => count( $profiles ),
			'experiments'    => count( $experiments ),
			'commerce_rules' => count( $commerce_rules ),
		) );
	}

	/**
	 * Add page intelligence and UX insight nodes/edges.
	 *
	 * @param array<string, mixed> $graph Base graph from snapshot.
	 * @return array<string, mixed>
	 */
	public static function extend_with_local_intelligence( array $graph ) {
		if ( array() === $graph ) {
			$graph = self::finalize_graph( array(), array(), array(), array() );
		}

		$nodes    = isset( $graph['nodes'] ) && is_array( $graph['nodes'] ) ? $graph['nodes'] : array();
		$edges    = isset( $graph['edges'] ) && is_array( $graph['edges'] ) ? $graph['edges'] : array();
		$edge_seq = count( $edges );
		$counts   = isset( $graph['counts'] ) && is_array( $graph['counts'] ) ? $graph['counts'] : array();

		$pages = class_exists( 'RWGA_DB_Page_Intelligence', false )
			? RWGA_DB_Page_Intelligence::list_recent( 200 )
			: array();
		$insights = class_exists( 'RWGA_DB_UX_Insights', false )
			? RWGA_DB_UX_Insights::list_active( 300 )
			: array();

		foreach ( $pages as $page_row ) {
			if ( ! is_array( $page_row ) ) {
				continue;
			}
			$page_id = (int) ( $page_row['page_id'] ?? 0 );
			if ( $page_id <= 0 ) {
				continue;
			}
			$title = function_exists( 'get_the_title' ) ? get_the_title( $page_id ) : ( 'Page ' . $page_id );
			$meta  = array(
				'page_type'    => isset( $page_row['page_type'] ) ? (string) $page_row['page_type'] : '',
				'funnel_stage' => isset( $page_row['funnel_stage'] ) ? (string) $page_row['funnel_stage'] : '',
				'builder_type' => isset( $page_row['builder_type'] ) ? (string) $page_row['builder_type'] : '',
			);
			$ctx = array();
			if ( ! empty( $page_row['context_json'] ) ) {
				$ctx = is_string( $page_row['context_json'] ) ? json_decode( $page_row['context_json'], true ) : $page_row['context_json'];
			}
			if ( is_array( $ctx ) ) {
				if ( ! empty( $ctx['builder_semantics']['page_goal'] ) ) {
					$meta['page_goal'] = (string) $ctx['builder_semantics']['page_goal'];
				}
				if ( ! empty( $ctx['messaging']['clarity']['overall'] ) ) {
					$meta['messaging_clarity'] = (int) $ctx['messaging']['clarity']['overall'];
				}
				$meta['has_messaging']         = ! empty( $ctx['messaging'] );
				$meta['has_ux_intelligence']   = ! empty( $ctx['ux_intelligence'] );
				$meta['has_visual_intelligence'] = ! empty( $ctx['visual_intelligence'] );
				$meta['has_semantics']         = ! empty( $ctx['builder_semantics'] );
			}
			self::add_node( $nodes, 'page', (string) $page_id, (string) $title, $meta );
		}

		foreach ( $insights as $insight ) {
			if ( ! is_array( $insight ) ) {
				continue;
			}
			$insight_id = (int) ( $insight['id'] ?? 0 );
			$page_id    = (int) ( $insight['entity_id'] ?? 0 );
			if ( $insight_id <= 0 || $page_id <= 0 || 'page' !== (string) ( $insight['entity_type'] ?? '' ) ) {
				continue;
			}
			$key = 'insight:' . $insight_id;
			self::add_node(
				$nodes,
				'insight',
				(string) $insight_id,
				RWGA_Builder_Normalize::trim_text( (string) ( $insight['finding'] ?? '' ), 80 ),
				array(
					'category'     => (string) ( $insight['category'] ?? '' ),
					'severity'     => (string) ( $insight['severity'] ?? '' ),
					'insight_type' => (string) ( $insight['insight_type'] ?? '' ),
					'source'       => (string) ( $insight['source'] ?? '' ),
				)
			);
			self::add_edge( $edges, $edge_seq, 'has_insight', self::node_id( 'page', (string) $page_id ), self::node_id( 'insight', (string) $insight_id ) );
		}

		$counts['page_intelligence'] = count( $pages );
		$counts['ux_insights']       = count( $insights );
		$counts['local_edges'] = count( array_filter(
			$edges,
			static function ( $edge ) {
				return is_array( $edge ) && isset( $edge['type'] ) && in_array( (string) $edge['type'], array( 'has_insight', 'variant_of', 'tests' ), true );
			}
		) );

		$snapshot = array(
			'snapshot_hash'     => isset( $graph['snapshot_hash'] ) ? (string) $graph['snapshot_hash'] : '',
			'generated_at_gmt'  => isset( $graph['generated_at_gmt'] ) ? (string) $graph['generated_at_gmt'] : '',
			'schema_version'    => isset( $graph['schema_version'] ) ? $graph['schema_version'] : 1,
		);

		return self::finalize_graph( $snapshot, $nodes, $edges, $counts );
	}

	/**
	 * @param array<string, mixed> $graph Full graph.
	 * @return array<string, mixed>
	 */
	public static function compact_for_api( array $graph ) {
		if ( array() === $graph ) {
			return array();
		}
		$nodes = array();
		foreach ( isset( $graph['nodes'] ) && is_array( $graph['nodes'] ) ? array_slice( $graph['nodes'], 0, 80 ) : array() as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$compact = array(
				'id'    => isset( $node['id'] ) ? (string) $node['id'] : '',
				'type'  => isset( $node['type'] ) ? (string) $node['type'] : '',
				'label' => RWGA_Builder_Normalize::trim_text( isset( $node['label'] ) ? (string) $node['label'] : '', 60 ),
			);
			if ( ! empty( $node['meta'] ) && is_array( $node['meta'] ) ) {
				$compact['meta'] = $node['meta'];
			}
			$nodes[] = $compact;
		}
		$edges = array();
		foreach ( isset( $graph['edges'] ) && is_array( $graph['edges'] ) ? array_slice( $graph['edges'], 0, 120 ) : array() as $edge ) {
			if ( ! is_array( $edge ) ) {
				continue;
			}
			$edges[] = array(
				'type' => isset( $edge['type'] ) ? (string) $edge['type'] : '',
				'from' => isset( $edge['from'] ) ? (string) $edge['from'] : '',
				'to'   => isset( $edge['to'] ) ? (string) $edge['to'] : '',
			);
		}
		return array(
			'schema_version' => isset( $graph['schema_version'] ) ? $graph['schema_version'] : 1,
			'snapshot_hash'  => isset( $graph['snapshot_hash'] ) ? (string) $graph['snapshot_hash'] : '',
			'counts'         => isset( $graph['counts'] ) && is_array( $graph['counts'] ) ? $graph['counts'] : array(),
			'nodes'          => $nodes,
			'edges'          => $edges,
		);
	}

	/**
	 * @param array<string, mixed> $graph Graph payload.
	 * @return int Site context row id or 0.
	 */
	public static function persist( array $graph ) {
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return 0;
		}
		$row = RWGA_Local_Intelligence::get_site_context();
		if ( ! is_array( $row ) || empty( $row['site_uuid'] ) ) {
			return 0;
		}
		$ctx = array();
		if ( ! empty( $row['context_json'] ) ) {
			$ctx = is_string( $row['context_json'] ) ? json_decode( $row['context_json'], true ) : $row['context_json'];
		}
		if ( ! is_array( $ctx ) ) {
			$ctx = array();
		}
		$ctx['relationship_graph'] = $graph;
		$row['context_json']       = $ctx;
		return RWGA_DB_Site_Context::upsert( $row );
	}

	/**
	 * @param array<string, mixed>             $snapshot Snapshot.
	 * @param array<string, array<string,mixed>> $nodes    Nodes map.
	 * @param array<int, array<string, mixed>> $edges    Edges.
	 * @param array<string, int>               $counts   Counts.
	 * @return array<string, mixed>
	 */
	private static function finalize_graph( array $snapshot, array $nodes, array $edges, array $counts ) {
		$node_list = array_values( $nodes );
		if ( count( $node_list ) > self::MAX_NODES ) {
			$node_list = array_slice( $node_list, 0, self::MAX_NODES );
		}
		if ( count( $edges ) > self::MAX_EDGES ) {
			$edges = array_slice( $edges, 0, self::MAX_EDGES );
		}

		$counts['nodes'] = count( $node_list );
		$counts['edges'] = count( $edges );
		$counts['targeting_edges'] = count( array_filter(
			$edges,
			static function ( $edge ) {
				return is_array( $edge ) && isset( $edge['type'] ) && 0 === strpos( (string) $edge['type'], 'targets' );
			}
		) );

		$graph = array(
			'schema_version'   => self::VERSION,
			'snapshot_hash'    => isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '',
			'generated_at_gmt' => isset( $snapshot['generated_at_gmt'] ) ? (string) $snapshot['generated_at_gmt'] : gmdate( 'c' ),
			'refreshed_at_gmt' => gmdate( 'c' ),
			'nodes'            => $node_list,
			'edges'            => $edges,
			'counts'           => $counts,
		);

		/**
		 * @param array<string, mixed> $graph Local relationship graph.
		 */
		return apply_filters( 'rwga_relationship_graph', $graph );
	}

	/**
	 * @param mixed $value Value.
	 * @return array<int, mixed>
	 */
	private static function as_array( $value ) {
		return is_array( $value ) ? $value : array();
	}

	/**
	 * @param string $type Entity type.
	 * @param string $id   Entity id.
	 * @return string
	 */
	private static function node_id( $type, $id ) {
		return sanitize_key( (string) $type ) . ':' . sanitize_text_field( (string) $id );
	}

	/**
	 * @param array<string, array<string, mixed>> $nodes Nodes map.
	 * @param string                             $type  Type.
	 * @param string                             $id    Id.
	 * @param string                             $label Label.
	 * @param array<string, mixed>               $meta  Meta.
	 * @return void
	 */
	private static function add_node( array &$nodes, $type, $id, $label, array $meta = array() ) {
		$key = self::node_id( $type, $id );
		if ( isset( $nodes[ $key ] ) ) {
			if ( array() !== $meta ) {
				$nodes[ $key ]['meta'] = array_merge( isset( $nodes[ $key ]['meta'] ) && is_array( $nodes[ $key ]['meta'] ) ? $nodes[ $key ]['meta'] : array(), $meta );
			}
			return;
		}
		$node = array(
			'id'    => $key,
			'type'  => sanitize_key( (string) $type ),
			'label' => RWGA_Builder_Normalize::trim_text( (string) $label, 120 ),
		);
		if ( array() !== $meta ) {
			$node['meta'] = $meta;
		}
		$nodes[ $key ] = $node;
	}

	/**
	 * @param array<int, array<string, mixed>> $edges   Edges.
	 * @param int                              $edge_seq Sequence.
	 * @param string                           $type    Edge type.
	 * @param string                           $from    From node id.
	 * @param string                           $to      To node id.
	 * @param array<string, mixed>             $meta    Meta.
	 * @return void
	 */
	private static function add_edge( array &$edges, &$edge_seq, $type, $from, $to, array $meta = array() ) {
		$edge = array(
			'id'   => 'e' . ( ++$edge_seq ),
			'type' => sanitize_key( (string) $type ),
			'from' => (string) $from,
			'to'   => (string) $to,
		);
		if ( array() !== $meta ) {
			$edge['meta'] = $meta;
		}
		$edges[] = $edge;
	}

	/**
	 * @param array<string, mixed>   $row Row.
	 * @param array<int, string>     $keys Id keys.
	 * @return string
	 */
	private static function row_id( array $row, array $keys ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $row[ $key ] ) ) {
				return (string) $row[ $key ];
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $row      Row.
	 * @param array<int, string>   $keys     Label keys.
	 * @param string               $fallback Fallback label.
	 * @return string
	 */
	private static function row_label( array $row, array $keys, $fallback ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $row[ $key ] ) ) {
				return (string) $row[ $key ];
			}
		}
		return (string) $fallback;
	}
}
