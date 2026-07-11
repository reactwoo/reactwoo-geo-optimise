<?php
/**
 * Task context builder — workflow-specific intelligence bundles for AI.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles summaries and findings instead of raw builder inventory.
 */
class RWGA_Context_Builder {

	const VERSION = '1.0.0';

	/**
	 * @param string               $workflow_key Workflow slug.
	 * @param array<string, mixed> $args         page_id, geo_target, analysis_focus, user_request, etc.
	 * @return array<string, mixed>
	 */
	public static function build( $workflow_key, array $args = array() ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		$bundle       = array();

		if ( in_array( $workflow_key, array( 'ux_analysis', 'ux_recommend' ), true ) ) {
			$bundle = self::build_ux_workflow( $workflow_key, $args );
		} elseif ( 'ux_opportunity_review' === $workflow_key ) {
			$bundle = self::build_ux_opportunity_review( $args );
		} elseif ( class_exists( 'RWGA_Workflow_Intelligence_Definitions', false ) ) {
			$intel_keys = array_keys( RWGA_Workflow_Intelligence_Definitions::get_definitions() );
			if ( in_array( $workflow_key, $intel_keys, true ) ) {
				$bundle = self::build_intelligence_workflow( $workflow_key, $args );
			}
		}

		if ( array() === $bundle ) {
			$bundle = self::build_generic( $workflow_key, $args );
		}

		$bundle['benchmark_context'] = self::benchmark_context( $args );
		$bundle['workflow_key']      = $workflow_key;
		$bundle['context_version']   = self::VERSION;
		$before                      = $bundle;
		$bundle                    = class_exists( 'RWGA_Payload_Guard', false ) ? RWGA_Payload_Guard::sanitize( $bundle ) : $bundle;
		$bundle['_payload_audit']  = class_exists( 'RWGA_Payload_Guard', false )
			? array( 'excluded_keys' => RWGA_Payload_Guard::audit_exclusions( $before, $bundle ) )
			: array();

		/**
		 * @param array<string, mixed> $bundle       Workflow context bundle.
		 * @param string               $workflow_key Workflow key.
		 * @param array<string, mixed> $args         Build args.
		 */
		$bundle = apply_filters( 'rwga_context_builder_bundle', $bundle, $workflow_key, $args );
		return is_array( $bundle ) ? $bundle : array();
	}

	/**
	 * Remote-safe payload (strips internal audit metadata).
	 *
	 * @param array<string, mixed> $bundle Full bundle from build().
	 * @return array<string, mixed>
	 */
	public static function for_remote_api( array $bundle ) {
		unset( $bundle['_payload_audit'] );
		return class_exists( 'RWGA_Payload_Guard', false ) ? RWGA_Payload_Guard::sanitize( $bundle ) : $bundle;
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $args         Args.
	 * @return array<string, mixed>
	 */
	private static function build_ux_workflow( $workflow_key, array $args ) {
		$page_id = isset( $args['page_id'] ) ? (int) $args['page_id'] : 0;
		$geo     = isset( $args['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $args['geo_target'] ), 0, 2 ) ) : '';
		$focus   = isset( $args['analysis_focus'] ) ? sanitize_key( (string) $args['analysis_focus'] ) : 'messaging';

		$bundle = array(
			'page_id'         => $page_id > 0 ? $page_id : 0,
			'page_type'       => isset( $args['page_type'] ) ? sanitize_key( (string) $args['page_type'] ) : 'page',
			'geo_target'      => '' !== $geo ? $geo : null,
			'analysis_focus'  => $focus,
			'user_request'    => isset( $args['user_request'] ) ? sanitize_text_field( (string) $args['user_request'] ) : '',
			'intelligence'    => $page_id > 0 ? self::page_intelligence_bundle( $page_id ) : array(),
			'builder_context' => $page_id > 0 ? self::compact_builder_context( $page_id ) : array(),
			'relationships'   => self::relationship_summary(),
		);

		if ( 'ux_recommend' === $workflow_key ) {
			$bundle['analysis_run_id'] = isset( $args['analysis_run_id'] ) ? (int) $args['analysis_run_id'] : 0;
			$bundle['business_goal']   = isset( $args['business_goal'] ) ? sanitize_text_field( (string) $args['business_goal'] ) : '';
			if ( ! empty( $args['analysis_summary'] ) ) {
				$bundle['analysis_summary'] = sanitize_textarea_field( (string) $args['analysis_summary'] );
			}
			if ( ! empty( $args['findings'] ) && is_array( $args['findings'] ) ) {
				$bundle['findings'] = array_slice( $args['findings'], 0, 12 );
			}
			if ( ! empty( $args['selected_categories'] ) && is_array( $args['selected_categories'] ) ) {
				$bundle['selected_categories'] = array_values( array_map( 'sanitize_key', $args['selected_categories'] ) );
			}
			if ( $page_id > 0 && class_exists( 'RWGA_Builder_Recommendations', false ) && class_exists( 'RWGA_Page_Context_Builder', false ) ) {
				$ai_ctx = RWGA_Page_Context_Builder::build( $page_id );
				if ( ! empty( $ai_ctx ) ) {
					$bundle['builder_recommendations'] = RWGA_Builder_Recommendations::from_context( $ai_ctx );
				}
			}
		}

		if ( $page_id > 0 && class_exists( 'RWGA_Page_Context', false ) && function_exists( 'rwga_ai_reading_bundle_from_page_context' ) ) {
			$page_ctx = RWGA_Page_Context::collect( $page_id );
			if ( is_array( $page_ctx ) ) {
				$bundle['reading_context'] = rwga_ai_reading_bundle_from_page_context( $page_ctx );
			}
		}

		return $bundle;
	}

	/**
	 * @param array<string, mixed> $args Workflow args.
	 * @return array<string, mixed>
	 */
	private static function build_ux_opportunity_review( array $args ) {
		$page_id    = isset( $args['page_id'] ) ? (int) $args['page_id'] : 0;
		$product_id = isset( $args['product_id'] ) ? (int) $args['product_id'] : 0;
		$variant_id = isset( $args['variant_page_id'] ) ? (int) $args['variant_page_id'] : 0;
		$intel_page = $variant_id > 0 ? $variant_id : ( $page_id > 0 ? $page_id : $product_id );

		$capabilities = isset( $args['capabilities'] ) && is_array( $args['capabilities'] )
			? $args['capabilities']
			: ( function_exists( 'rwgc_get_suite_capability_map' ) ? rwgc_get_suite_capability_map() : array() );

		$bundle = array(
			'page_id'               => $page_id,
			'product_id'            => $product_id,
			'variant_page_id'       => $variant_id,
			'rule_id'               => isset( $args['rule_id'] ) ? sanitize_text_field( (string) $args['rule_id'] ) : '',
			'source'                => isset( $args['source'] ) ? sanitize_key( (string) $args['source'] ) : 'dashboard',
			'capabilities'          => $capabilities,
			'site_intelligence'     => self::site_intelligence_slice(),
			'local_site'            => self::local_site_summary(),
			'relationships'         => self::relationship_summary(),
			'audience_context'      => isset( $args['audience_context'] ) && is_array( $args['audience_context'] ) ? $args['audience_context'] : array(),
			'commerce_context'      => isset( $args['commerce_context'] ) && is_array( $args['commerce_context'] ) ? $args['commerce_context'] : array(),
			'optimise_context'      => isset( $args['optimise_context'] ) && is_array( $args['optimise_context'] ) ? $args['optimise_context'] : array(),
			'pro_targeting_context' => isset( $args['pro_targeting_context'] ) && is_array( $args['pro_targeting_context'] ) ? $args['pro_targeting_context'] : array(),
		);

		if ( $intel_page > 0 ) {
			$bundle['page_intelligence'] = self::page_intelligence_bundle( $intel_page );
			$bundle['builder_context']   = self::compact_builder_context( $intel_page );
		}

		if ( $product_id > 0 && empty( $bundle['commerce_context'] ) ) {
			$bundle['commerce_context'] = self::commerce_product_slice( $product_id );
		}

		if ( ! empty( $capabilities['geocore_pro_licensed'] ) && empty( $bundle['pro_targeting_context'] ) ) {
			$bundle['pro_targeting_context'] = array(
				'advanced_targeting' => function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled(),
			);
		}

		return $bundle;
	}

	/**
	 * @param int $product_id WooCommerce product ID.
	 * @return array<string, mixed>
	 */
	private static function commerce_product_slice( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}
		return array(
			'product_id'   => $product_id,
			'name'         => $product->get_name(),
			'type'         => $product->get_type(),
			'price'        => $product->get_price(),
			'regular_price'=> $product->get_regular_price(),
			'sale_price'   => $product->get_sale_price(),
			'permalink'    => get_permalink( $product_id ),
		);
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $args         Args.
	 * @return array<string, mixed>
	 */
	private static function build_intelligence_workflow( $workflow_key, array $args ) {
		unset( $workflow_key );
		$bundle = array(
			'page_id'           => isset( $args['page_id'] ) ? (int) $args['page_id'] : 0,
			'rule_id'           => isset( $args['rule_id'] ) ? sanitize_text_field( (string) $args['rule_id'] ) : '',
			'popup_id'          => isset( $args['popup_id'] ) ? (int) $args['popup_id'] : 0,
			'variant_page_id'   => isset( $args['variant_page_id'] ) ? (int) $args['variant_page_id'] : 0,
			'site_intelligence' => self::site_intelligence_slice(),
			'local_site'        => self::local_site_summary(),
			'relationships'     => self::relationship_summary(),
		);

		$variant_page = (int) ( $bundle['variant_page_id'] ?? 0 );
		$page_id      = (int) ( $bundle['page_id'] ?? 0 );
		$intel_page   = $variant_page > 0 ? $variant_page : $page_id;
		if ( $intel_page > 0 ) {
			$bundle['page_intelligence'] = self::page_intelligence_bundle( $intel_page );
		}

		if ( ! empty( $args['context'] ) && is_array( $args['context'] ) ) {
			$bundle['context'] = $args['context'];
		}

		return $bundle;
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $args         Args.
	 * @return array<string, mixed>
	 */
	private static function build_generic( $workflow_key, array $args ) {
		unset( $workflow_key );
		$page_id = isset( $args['page_id'] ) ? (int) $args['page_id'] : 0;
		return array(
			'page_id'      => $page_id > 0 ? $page_id : 0,
			'intelligence' => $page_id > 0 ? self::page_intelligence_bundle( $page_id ) : array(),
		);
	}

	/**
	 * @param int $page_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function page_intelligence_bundle( $page_id ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 ) {
			return array();
		}

		$messaging = array();
		$ux        = array();
		$visual    = array();
		$semantics = array();
		$summaries = array();

		if ( class_exists( 'RWGA_Local_Intelligence', false ) ) {
			$row = RWGA_Local_Intelligence::get_page_context( $page_id );
			if ( is_array( $row ) ) {
				$summaries = array(
					'messaging'  => isset( $row['messaging_summary'] ) ? (string) $row['messaging_summary'] : '',
					'ux'         => isset( $row['ux_summary'] ) ? (string) $row['ux_summary'] : '',
					'conversion' => isset( $row['conversion_summary'] ) ? (string) $row['conversion_summary'] : '',
				);
			}
			$messaging = RWGA_Local_Intelligence::get_page_messaging( $page_id );
			$ux        = RWGA_Local_Intelligence::get_page_ux_intelligence( $page_id );
			$visual    = RWGA_Local_Intelligence::get_page_visual_intelligence( $page_id );
			$semantics = RWGA_Local_Intelligence::get_page_builder_semantics( $page_id );
		}

		if ( array() === $messaging && class_exists( 'RWGA_Page_Context_Builder', false ) && class_exists( 'RWGA_Messaging_Analyzer', false ) ) {
			$payload   = RWGA_Page_Context_Builder::build( $page_id );
			$messaging = ! empty( $payload ) ? RWGA_Messaging_Analyzer::analyze( $payload, $page_id ) : array();
		}

		$insights = class_exists( 'RWGA_Local_Intelligence', false )
			? RWGA_Local_Intelligence::get_page_insights( $page_id )
			: array();

		return array(
			'page_id'    => $page_id,
			'page_title' => function_exists( 'get_the_title' ) ? get_the_title( $page_id ) : '',
			'summaries'  => $summaries,
			'messaging'  => self::compact_messaging( $messaging ),
			'ux'         => self::compact_ux( $ux ),
			'visual'     => self::compact_visual( $visual ),
			'semantics'  => class_exists( 'RWGA_Context_Extractor_Base', false )
				? RWGA_Context_Extractor_Base::compact_for_api( $semantics )
				: $semantics,
			'insights'   => self::compact_insights( $insights ),
		);
	}

	/**
	 * @param int $page_id Post ID.
	 * @return array<string, mixed>
	 */
	private static function compact_builder_context( $page_id ) {
		if ( ! class_exists( 'RWGA_Page_Context_Builder', false ) ) {
			return array();
		}
		$payload = RWGA_Page_Context_Builder::build( (int) $page_id );
		return ! empty( $payload ) ? RWGA_Page_Context_Builder::compact_for_api( $payload ) : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function site_intelligence_slice() {
		if ( ! function_exists( 'rwgc_build_ai_snapshot' ) ) {
			return array();
		}
		$snapshot = rwgc_build_ai_snapshot();
		if ( ! is_array( $snapshot ) ) {
			return array();
		}
		return array(
			'schema_version'    => isset( $snapshot['schema_version'] ) ? (int) $snapshot['schema_version'] : 1,
			'snapshot_hash'     => isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '',
			'generated_at_gmt'  => isset( $snapshot['generated_at_gmt'] ) ? (string) $snapshot['generated_at_gmt'] : '',
			'site'              => isset( $snapshot['site'] ) && is_array( $snapshot['site'] ) ? $snapshot['site'] : array(),
			'rules'             => isset( $snapshot['rules'] ) && is_array( $snapshot['rules'] ) ? array_slice( $snapshot['rules'], 0, 48 ) : array(),
			'variants'          => isset( $snapshot['variants'] ) && is_array( $snapshot['variants'] ) ? array_slice( $snapshot['variants'], 0, 48 ) : array(),
			'popups'            => isset( $snapshot['popups'] ) && is_array( $snapshot['popups'] ) ? array_slice( $snapshot['popups'], 0, 24 ) : array(),
			'relationships'     => isset( $snapshot['relationships'] ) && is_array( $snapshot['relationships'] ) ? array_slice( $snapshot['relationships'], 0, 48 ) : array(),
			'tracking_events'   => isset( $snapshot['tracking_events'] ) && is_array( $snapshot['tracking_events'] ) ? array_slice( $snapshot['tracking_events'], 0, 24 ) : array(),
			'conversion_events' => isset( $snapshot['conversion_events'] ) && is_array( $snapshot['conversion_events'] ) ? array_slice( $snapshot['conversion_events'], 0, 24 ) : array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function local_site_summary() {
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return array();
		}
		$row = RWGA_Local_Intelligence::get_site_context();
		if ( ! is_array( $row ) ) {
			return array();
		}
		return array(
			'site_type'             => isset( $row['site_type'] ) ? (string) $row['site_type'] : '',
			'primary_goal'          => isset( $row['primary_goal'] ) ? (string) $row['primary_goal'] : '',
			'localisation_maturity' => isset( $row['localisation_maturity'] ) ? (string) $row['localisation_maturity'] : '',
			'optimisation_maturity' => isset( $row['optimisation_maturity'] ) ? (string) $row['optimisation_maturity'] : '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	/**
	 * @param array<string, mixed> $args Build args.
	 * @return array<string, mixed>
	 */
	private static function benchmark_context( array $args ) {
		if ( ! class_exists( 'RWGA_Knowledge_Graph', false ) ) {
			return array();
		}
		return RWGA_Knowledge_Graph::benchmark_context( $args );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function relationship_summary() {
		if ( ! class_exists( 'RWGA_Relationship_Graph', false ) ) {
			return array();
		}
		$graph = RWGA_Relationship_Graph::get_graph();
		if ( array() === $graph ) {
			return array();
		}
		$counts = isset( $graph['counts'] ) && is_array( $graph['counts'] ) ? $graph['counts'] : array();
		return array(
			'snapshot_hash' => isset( $graph['snapshot_hash'] ) ? (string) $graph['snapshot_hash'] : '',
			'variants'      => (int) ( $counts['variants'] ?? 0 ),
			'rules'         => (int) ( $counts['rules'] ?? 0 ),
			'experiments'   => (int) ( $counts['experiments'] ?? 0 ),
			'page_intel'    => (int) ( $counts['page_intelligence'] ?? 0 ),
			'ux_insights'   => (int) ( $counts['ux_insights'] ?? 0 ),
			'nodes'         => (int) ( $counts['nodes'] ?? 0 ),
			'edges'         => (int) ( $counts['edges'] ?? 0 ),
		);
	}

	/**
	 * @param array<string, mixed> $messaging Messaging analyzer output.
	 * @return array<string, mixed>
	 */
	private static function compact_messaging( array $messaging ) {
		if ( array() === $messaging ) {
			return array();
		}
		$objections = array();
		if ( ! empty( $messaging['objections'] ) && is_array( $messaging['objections'] ) ) {
			foreach ( array_slice( $messaging['objections'], 0, 6 ) as $obj ) {
				if ( is_array( $obj ) && ! empty( $obj['key'] ) ) {
					$objections[] = (string) $obj['key'];
				} elseif ( is_string( $obj ) ) {
					$objections[] = $obj;
				}
			}
		}
		return array(
			'promise'   => isset( $messaging['promise']['text'] ) ? RWGA_Builder_Normalize::trim_text( (string) $messaging['promise']['text'], 160 ) : '',
			'uvp'       => isset( $messaging['uvp']['text'] ) ? RWGA_Builder_Normalize::trim_text( (string) $messaging['uvp']['text'], 160 ) : '',
			'clarity'   => isset( $messaging['clarity']['overall'] ) ? (int) $messaging['clarity']['overall'] : 0,
			'audience'  => isset( $messaging['audience'] ) && is_array( $messaging['audience'] ) ? $messaging['audience'] : array(),
			'objections'=> $objections,
		);
	}

	/**
	 * @param array<string, mixed> $ux UX insight output.
	 * @return array<string, mixed>
	 */
	private static function compact_ux( array $ux ) {
		if ( array() === $ux ) {
			return array();
		}
		$cta = isset( $ux['cta_effectiveness'] ) && is_array( $ux['cta_effectiveness'] ) ? $ux['cta_effectiveness'] : array();
		$trust = isset( $ux['trust'] ) && is_array( $ux['trust'] ) ? $ux['trust'] : array();
		$friction = isset( $ux['friction'] ) && is_array( $ux['friction'] ) ? $ux['friction'] : array();
		return array(
			'cta_strength'    => isset( $cta['cta_strength'] ) ? (int) $cta['cta_strength'] : 0,
			'primary_cta'     => isset( $cta['primary_cta'] ) ? RWGA_Builder_Normalize::trim_text( (string) $cta['primary_cta'], 80 ) : '',
			'trust_gap'       => isset( $trust['trust_gap'] ) ? (string) $trust['trust_gap'] : '',
			'friction_level'  => isset( $friction['friction_level'] ) ? (string) $friction['friction_level'] : '',
			'mobile_score'    => isset( $ux['mobile']['mobile_score'] ) ? (int) $ux['mobile']['mobile_score'] : 0,
		);
	}

	/**
	 * @param array<string, mixed> $visual Visual analyzer output.
	 * @return array<string, mixed>
	 */
	private static function compact_visual( array $visual ) {
		if ( array() === $visual ) {
			return array();
		}
		$cta = isset( $visual['cta_emphasis'] ) && is_array( $visual['cta_emphasis'] ) ? $visual['cta_emphasis'] : array();
		return array(
			'primary_cta_emphasis'      => isset( $cta['primary_cta_emphasis'] ) ? (int) $cta['primary_cta_emphasis'] : 0,
			'secondary_cta_competition'   => isset( $cta['secondary_cta_competition'] ) ? (int) $cta['secondary_cta_competition'] : 0,
			'focus_conflicts'           => isset( $visual['visual_competition']['focus_conflicts'] ) ? (int) $visual['visual_competition']['focus_conflicts'] : 0,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $insights Insight rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function compact_insights( array $insights ) {
		$out = array();
		foreach ( array_slice( $insights, 0, 10 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'insight_key' => isset( $row['insight_key'] ) ? (string) $row['insight_key'] : '',
				'finding'     => RWGA_Builder_Normalize::trim_text( isset( $row['finding'] ) ? (string) $row['finding'] : '', 120 ),
				'severity'    => isset( $row['severity'] ) ? (string) $row['severity'] : '',
				'category'    => isset( $row['category'] ) ? (string) $row['category'] : '',
				'source'      => isset( $row['source'] ) ? (string) $row['source'] : '',
			);
		}
		return $out;
	}
}
