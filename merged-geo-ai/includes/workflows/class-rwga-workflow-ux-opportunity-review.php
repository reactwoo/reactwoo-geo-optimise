<?php
/**
 * Capability-aware UX opportunity review workflow.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reviews page/product/variant UX with suite-aware recommendation cards.
 */
class RWGA_Workflow_UX_Opportunity_Review extends RWGA_Workflow_Base {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'ux_opportunity_review';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'UX opportunity review', 'reactwoo-geo-ai' );
	}

	/**
	 * @return string
	 */
	public function get_agent_key() {
		return 'ux_strategist';
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return true|\WP_Error
	 */
	public function validate_input( array $input ) {
		$g = $this->gate_capabilities();
		if ( is_wp_error( $g ) ) {
			return $g;
		}
		if ( ! class_exists( 'RWGC_Plugin', false ) ) {
			return new WP_Error( 'rwga_geocore_required', __( 'Geo Core must be active to run UX opportunity reviews.', 'reactwoo-geo-ai' ) );
		}
		$page_id    = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$product_id = isset( $input['product_id'] ) ? (int) $input['product_id'] : 0;
		$rule_id    = isset( $input['rule_id'] ) ? sanitize_text_field( (string) $input['rule_id'] ) : '';
		if ( $page_id <= 0 && $product_id <= 0 && '' === $rule_id ) {
			return new WP_Error(
				'rwga_review_target',
				__( 'Provide a page, product, or rule to review.', 'reactwoo-geo-ai' )
			);
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input ) {
		$base         = $this->sanitise_common( $input );
		$capabilities = $this->resolve_capabilities( $input );

		$payload = array_merge(
			$base,
			array(
				'product_id'            => isset( $input['product_id'] ) ? (int) $input['product_id'] : 0,
				'variant_page_id'       => isset( $input['variant_page_id'] ) ? (int) $input['variant_page_id'] : 0,
				'rule_id'               => isset( $input['rule_id'] ) ? sanitize_text_field( (string) $input['rule_id'] ) : '',
				'source'                => isset( $input['source'] ) ? sanitize_key( (string) $input['source'] ) : 'dashboard',
				'capabilities'          => $capabilities,
				'audience_context'      => isset( $input['audience_context'] ) && is_array( $input['audience_context'] ) ? $input['audience_context'] : array(),
				'commerce_context'      => isset( $input['commerce_context'] ) && is_array( $input['commerce_context'] ) ? $input['commerce_context'] : array(),
				'optimise_context'      => isset( $input['optimise_context'] ) && is_array( $input['optimise_context'] ) ? $input['optimise_context'] : array(),
				'pro_targeting_context' => isset( $input['pro_targeting_context'] ) && is_array( $input['pro_targeting_context'] ) ? $input['pro_targeting_context'] : array(),
			)
		);

		if ( class_exists( 'RWGA_Context_Builder', false ) ) {
			$bundle = RWGA_Context_Builder::for_remote_api(
				RWGA_Context_Builder::build( $this->get_key(), array_merge( $input, $payload ) )
			);
			$payload = array_merge( $payload, $bundle );
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	private function resolve_capabilities( array $input ) {
		if ( ! empty( $input['capabilities'] ) && is_array( $input['capabilities'] ) ) {
			return $input['capabilities'];
		}
		return class_exists( 'RWGA_UX_Opportunity_Action_Filter', false )
			? RWGA_UX_Opportunity_Action_Filter::get_capabilities()
			: array();
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute( array $input ) {
		$v = $this->validate_input( $input );
		if ( is_wp_error( $v ) ) {
			return $v;
		}

		$payload      = $this->build_request_payload( $input );
		$capabilities = isset( $payload['capabilities'] ) && is_array( $payload['capabilities'] ) ? $payload['capabilities'] : array();
		$mode         = class_exists( 'RWGA_Engine', false ) ? RWGA_Engine::get_mode() : 'local';
		$engine_source = 'local_fallback';

		$remote = class_exists( 'RWGA_Engine', false ) && RWGA_Engine::should_try_remote()
			? RWGA_Remote_Client::dispatch( $this->get_key(), $payload )
			: null;
		$use_api = ! is_wp_error( $remote ) && is_array( $remote ) && ! empty( $remote['engine_response'] );

		if ( $use_api ) {
			$norm          = $this->normalise_response( $remote['engine_response'] );
			$engine_source = 'remote_ai';
		} else {
			if ( is_wp_error( $remote ) && 'remote' === $mode ) {
				return $remote;
			}
			$raw           = $this->produce_local_cards( $payload, $capabilities );
			$norm          = $this->normalise_response( $raw );
			$engine_source = 'local_deterministic';
			$remote        = null;
		}

		$norm = $this->apply_capability_filter( $norm, $capabilities, $payload );
		$norm['engine_source'] = $engine_source;

		$persisted = $this->persist( $payload, $norm, is_array( $remote ) ? $remote : null );
		if ( is_array( $persisted ) && isset( $persisted['success'] ) && ! $persisted['success'] ) {
			$msg = isset( $persisted['error'] ) ? (string) $persisted['error'] : __( 'Could not save UX opportunity review.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_persist', $msg );
		}
		if ( is_array( $persisted ) ) {
			$persisted['engine_source'] = $engine_source;
		}
		return $persisted;
	}

	/**
	 * @param array<string, mixed> $response Raw engine response.
	 * @return array<string, mixed>
	 */
	public function normalise_response( array $response ) {
		$cards = isset( $response['cards'] ) && is_array( $response['cards'] )
			? $response['cards']
			: ( isset( $response['recommendations'] ) && is_array( $response['recommendations'] ) ? $response['recommendations'] : array() );

		$normalised = array();
		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$normalised[] = $this->normalise_card( $card );
		}

		return array(
			'workflow_key'    => $this->get_key(),
			'schema_version'  => self::DEFAULT_SCHEMA_VERSION,
			'summary'         => isset( $response['summary'] ) ? sanitize_text_field( (string) $response['summary'] ) : '',
			'cards'           => $normalised,
			'recommendations' => $this->cards_to_legacy_recommendations( $normalised ),
			'actions'         => array(),
			'usage'           => isset( $response['usage'] ) && is_array( $response['usage'] ) ? $response['usage'] : array(),
		);
	}

	/**
	 * @param array<string, mixed> $card Raw card.
	 * @return array<string, mixed>
	 */
	private function normalise_card( array $card ) {
		$title = isset( $card['title'] ) ? sanitize_text_field( (string) $card['title'] ) : '';
		if ( '' === $title && ! empty( $card['ux_area'] ) ) {
			$title = sanitize_text_field( (string) $card['ux_area'] );
		}
		$required = array();
		if ( ! empty( $card['required_products'] ) && is_array( $card['required_products'] ) ) {
			$required = array_values( array_map( 'sanitize_key', $card['required_products'] ) );
		}

		return array(
			'title'             => $title,
			'problem'           => isset( $card['problem'] ) ? wp_kses_post( (string) $card['problem'] ) : '',
			'audience'          => isset( $card['audience'] ) ? sanitize_text_field( (string) $card['audience'] ) : '',
			'recommendation'    => isset( $card['recommendation'] ) ? wp_kses_post( (string) $card['recommendation'] ) : '',
			'ux_area'           => isset( $card['ux_area'] ) ? sanitize_key( (string) $card['ux_area'] ) : 'general',
			'expected_impact'   => isset( $card['expected_impact'] ) ? sanitize_text_field( (string) $card['expected_impact'] ) : 'medium',
			'confidence'        => isset( $card['confidence'] ) ? (float) $card['confidence'] : 0.7,
			'effort'            => isset( $card['effort'] ) ? sanitize_key( (string) $card['effort'] ) : 'medium',
			'available_now'     => ! empty( $card['available_now'] ),
			'required_products' => $required,
			'suggested_action'  => isset( $card['suggested_action'] ) ? sanitize_key( (string) $card['suggested_action'] ) : '',
			'requires_approval' => true,
			'upgrade_label'     => isset( $card['upgrade_label'] ) ? sanitize_text_field( (string) $card['upgrade_label'] ) : '',
			'page_id'           => isset( $card['page_id'] ) ? (int) $card['page_id'] : 0,
			'product_id'        => isset( $card['product_id'] ) ? (int) $card['product_id'] : 0,
			'variant_page_id'   => isset( $card['variant_page_id'] ) ? (int) $card['variant_page_id'] : 0,
			'admin_page'        => isset( $card['admin_page'] ) ? sanitize_key( (string) $card['admin_page'] ) : '',
			'query_args'        => isset( $card['query_args'] ) && is_array( $card['query_args'] ) ? $card['query_args'] : array(),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $cards Cards.
	 * @return array<int, array<string, mixed>>
	 */
	private function cards_to_legacy_recommendations( array $cards ) {
		$out = array();
		foreach ( $cards as $card ) {
			$out[] = array(
				'title'    => isset( $card['title'] ) ? (string) $card['title'] : '',
				'detail'   => isset( $card['recommendation'] ) ? (string) $card['recommendation'] : '',
				'priority' => isset( $card['expected_impact'] ) ? (string) $card['expected_impact'] : 'medium',
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $norm         Normalised result.
	 * @param array<string, mixed> $capabilities Capability map.
	 * @param array<string, mixed> $payload      Request payload.
	 * @return array<string, mixed>
	 */
	private function apply_capability_filter( array $norm, array $capabilities, array $payload ) {
		if ( ! class_exists( 'RWGA_UX_Opportunity_Action_Filter', false ) ) {
			return $norm;
		}

		$page_id    = (int) ( $payload['page_id'] ?? 0 );
		$product_id = (int) ( $payload['product_id'] ?? 0 );
		$variant_id = (int) ( $payload['variant_page_id'] ?? 0 );

		$cards   = isset( $norm['cards'] ) && is_array( $norm['cards'] ) ? $norm['cards'] : array();
		$actions = array();
		$enriched = array();

		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			if ( $page_id > 0 && empty( $card['page_id'] ) ) {
				$card['page_id'] = $page_id;
			}
			if ( $product_id > 0 && empty( $card['product_id'] ) ) {
				$card['product_id'] = $product_id;
			}
			if ( $variant_id > 0 && empty( $card['variant_page_id'] ) ) {
				$card['variant_page_id'] = $variant_id;
			}

			$card = RWGA_UX_Opportunity_Action_Filter::enrich_card( $card, $capabilities );
			if ( ! empty( $card['required_products'] ) ) {
				$card = $this->apply_product_requirements( $card, $capabilities );
			}

			$enriched[] = $card;
			$pending    = RWGA_UX_Opportunity_Action_Filter::build_pending_action_row( $card, $capabilities );
			if ( is_array( $pending ) ) {
				$actions[] = $pending;
			}
		}

		$norm['cards']           = $enriched;
		$norm['actions']         = $actions;
		$norm['recommendations'] = $this->cards_to_legacy_recommendations( $enriched );
		return $norm;
	}

	/**
	 * @param array<string, mixed> $card         Card.
	 * @param array<string, mixed> $capabilities Capabilities.
	 * @return array<string, mixed>
	 */
	private function apply_product_requirements( array $card, array $capabilities ) {
		$labels = class_exists( 'RWGA_UX_Opportunity_Action_Filter', false )
			? RWGA_UX_Opportunity_Action_Filter::upgrade_labels()
			: array();

		$required = isset( $card['required_products'] ) && is_array( $card['required_products'] ) ? $card['required_products'] : array();
		foreach ( $required as $product ) {
			$product = sanitize_key( (string) $product );
			switch ( $product ) {
				case 'geocore_pro':
					if ( empty( $capabilities['geocore_pro_licensed'] ) ) {
						$card['available_now']    = false;
						$card['upgrade_label']    = $labels['geocore_pro'] ?? __( 'Requires GeoCore Pro', 'reactwoo-geo-ai' );
						$card['suggested_action'] = 'requires_geocore_pro';
					}
					break;
				case 'geo_optimise':
					if ( empty( $capabilities['geo_optimise_licensed'] ) ) {
						$card['available_now']    = false;
						$card['upgrade_label']    = $labels['geo_optimise'] ?? __( 'Requires Geo Optimise', 'reactwoo-geo-ai' );
						$card['suggested_action'] = 'requires_geo_optimise';
					}
					break;
				case 'geo_commerce':
					if ( empty( $capabilities['geo_commerce_licensed'] ) ) {
						$card['available_now']    = false;
						$card['upgrade_label']    = $labels['geo_commerce'] ?? __( 'Requires Geo Commerce', 'reactwoo-geo-ai' );
						$card['suggested_action'] = 'requires_geo_commerce';
					}
					break;
				case 'woocommerce':
					if ( empty( $capabilities['woocommerce_active'] ) ) {
						$card['available_now']    = false;
						$card['upgrade_label']    = $labels['woocommerce'] ?? __( 'Requires WooCommerce', 'reactwoo-geo-ai' );
						$card['suggested_action'] = 'requires_woocommerce';
					}
					break;
			}
		}
		return $card;
	}

	/**
	 * @param array<string, mixed> $payload      Request payload.
	 * @param array<string, mixed> $capabilities Capability map.
	 * @return array<string, mixed>
	 */
	private function produce_local_cards( array $payload, array $capabilities ) {
		$page_id    = (int) ( $payload['page_id'] ?? 0 );
		$product_id = (int) ( $payload['product_id'] ?? 0 );
		$variant_id = (int) ( $payload['variant_page_id'] ?? 0 );
		$title      = '';
		if ( $page_id > 0 ) {
			$title = get_the_title( $page_id );
		} elseif ( $product_id > 0 ) {
			$title = get_the_title( $product_id );
		}

		$audience = __( 'Geo-targeted visitors', 'reactwoo-geo-ai' );
		if ( ! empty( $payload['audience_context']['label'] ) ) {
			$audience = sanitize_text_field( (string) $payload['audience_context']['label'] );
		}

		$cards = array(
			array(
				'title'            => __( 'Strengthen primary CTA and offer clarity', 'reactwoo-geo-ai' ),
				'problem'          => $title
					? sprintf(
						/* translators: %s: content title */
						__( 'Visitors on “%s” may not see a clear next step aligned with their segment.', 'reactwoo-geo-ai' ),
						$title
					)
					: __( 'The primary conversion path may lack a clear next step for geo-targeted visitors.', 'reactwoo-geo-ai' ),
				'audience'         => $audience,
				'recommendation'   => __( 'Tighten headline-to-CTA messaging, add segment-specific proof near the decision point, and ensure the hero CTA matches the visitor intent for this geo rule.', 'reactwoo-geo-ai' ),
				'ux_area'          => 'copy_cta',
				'expected_impact'  => 'high',
				'confidence'       => 0.75,
				'effort'           => 'low',
				'suggested_action' => 'create_implementation_draft',
				'page_id'          => $page_id,
				'product_id'       => $product_id,
				'variant_page_id'  => $variant_id,
			),
		);

		if ( ! empty( $capabilities['geocore_pro_licensed'] ) ) {
			$cards[] = array(
				'title'             => __( 'Use campaign or audience targeting for this offer', 'reactwoo-geo-ai' ),
				'problem'           => __( 'Country-only rules may be too broad for high-intent segments on this page.', 'reactwoo-geo-ai' ),
				'audience'          => $audience,
				'recommendation'    => __( 'Layer campaign, audience profile, or time-window conditions so returning visitors and paid traffic see a tailored variant.', 'reactwoo-geo-ai' ),
				'ux_area'           => 'targeting',
				'expected_impact'   => 'medium',
				'confidence'        => 0.68,
				'effort'            => 'medium',
				'required_products' => array( 'geocore_pro' ),
				'suggested_action'  => 'open_admin_route',
				'admin_page'        => 'rwgc-visibility-rules',
				'page_id'           => $page_id,
			);
		} else {
			$cards[] = array(
				'title'             => __( 'Advanced targeting could sharpen this experience', 'reactwoo-geo-ai' ),
				'problem'           => __( 'Campaign, audience, time, and weather rules are not available on Geo Core Free.', 'reactwoo-geo-ai' ),
				'audience'          => $audience,
				'recommendation'    => __( 'Upgrade to GeoCore Pro to target campaigns, audience profiles, time windows, and weather facets for this page.', 'reactwoo-geo-ai' ),
				'ux_area'           => 'targeting',
				'expected_impact'   => 'medium',
				'confidence'        => 0.65,
				'effort'            => 'medium',
				'required_products' => array( 'geocore_pro' ),
				'suggested_action'  => 'requires_geocore_pro',
				'page_id'           => $page_id,
			);
		}

		if ( ! empty( $capabilities['geo_optimise_licensed'] ) && ( $page_id > 0 || $variant_id > 0 ) ) {
			$cards[] = array(
				'title'             => __( 'Validate UX changes with an A/B test', 'reactwoo-geo-ai' ),
				'problem'           => __( 'UX improvements on geo variants are hard to measure without a controlled experiment.', 'reactwoo-geo-ai' ),
				'audience'          => $audience,
				'recommendation'    => __( 'Create a Geo Optimise experiment comparing the current variant against an improved draft to measure conversion lift.', 'reactwoo-geo-ai' ),
				'ux_area'           => 'experiment',
				'expected_impact'   => 'high',
				'confidence'        => 0.72,
				'effort'            => 'medium',
				'required_products' => array( 'geo_optimise' ),
				'suggested_action'  => 'create_optimise_test_prefill',
				'page_id'           => $page_id,
				'variant_page_id'   => $variant_id,
			);
		} else {
			$cards[] = array(
				'title'             => __( 'Measure variant performance with split testing', 'reactwoo-geo-ai' ),
				'problem'           => __( 'Without Geo Optimise, you cannot run controlled experiments on geo variants.', 'reactwoo-geo-ai' ),
				'audience'          => $audience,
				'recommendation'    => __( 'Install Geo Optimise to run A/B tests, track goals, and promote winning variants.', 'reactwoo-geo-ai' ),
				'ux_area'           => 'experiment',
				'expected_impact'   => 'high',
				'confidence'        => 0.7,
				'effort'            => 'medium',
				'required_products' => array( 'geo_optimise' ),
				'suggested_action'  => 'requires_geo_optimise',
				'page_id'           => $page_id,
			);
		}

		if ( $product_id > 0 || ! empty( $capabilities['geo_commerce_licensed'] ) ) {
			if ( ! empty( $capabilities['geo_commerce_licensed'] ) && ! empty( $capabilities['woocommerce_active'] ) ) {
				$cards[] = array(
					'title'             => __( 'Tailor product messaging and offers by segment', 'reactwoo-geo-ai' ),
					'problem'           => __( 'Shoppers in different regions may need different pricing cues, badges, or shipping messaging.', 'reactwoo-geo-ai' ),
					'audience'          => $audience,
					'recommendation'    => __( 'Review Geo Commerce pricing rules, overlays, and shipping restrictions for this product so the storefront matches local expectations.', 'reactwoo-geo-ai' ),
					'ux_area'           => 'commerce',
					'expected_impact'   => 'medium',
					'confidence'        => 0.7,
					'effort'            => 'medium',
					'required_products' => array( 'geo_commerce', 'woocommerce' ),
					'suggested_action'  => 'open_admin_route',
					'admin_page'        => 'rwgcm-rules',
					'query_args'        => $product_id > 0 ? array( 'product_id' => (string) $product_id ) : array(),
					'product_id'        => $product_id,
				);
			} else {
				$cards[] = array(
					'title'             => __( 'Geo-specific product merchandising', 'reactwoo-geo-ai' ),
					'problem'           => __( 'Product-level geo pricing and overlays require Geo Commerce and WooCommerce.', 'reactwoo-geo-ai' ),
					'audience'          => $audience,
					'recommendation'    => __( 'Activate Geo Commerce to apply geo-specific pricing, badges, overlays, and shipping restrictions.', 'reactwoo-geo-ai' ),
					'ux_area'           => 'commerce',
					'expected_impact'   => 'medium',
					'confidence'        => 0.65,
					'effort'            => 'medium',
					'required_products' => array( 'geo_commerce' ),
					'suggested_action'  => 'requires_geo_commerce',
					'product_id'        => $product_id,
				);
			}
		}

		return array(
			'summary' => $title
				? sprintf(
					/* translators: %s: page or product title */
					__( 'UX opportunity review for %s (local deterministic analysis).', 'reactwoo-geo-ai' ),
					$title
				)
				: __( 'UX opportunity review (local deterministic analysis).', 'reactwoo-geo-ai' ),
			'cards'   => $cards,
		);
	}

	/**
	 * @param array<string, mixed>      $input  Input used.
	 * @param array<string, mixed>      $result Normalised result.
	 * @param array<string, mixed>|null $remote Remote client response.
	 * @return array<string, mixed>
	 */
	public function persist( array $input, array $result, $remote = null ) {
		$uid           = get_current_user_id();
		$page_id       = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$geo           = isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '';
		$snapshot_hash = '';
		if ( isset( $input['site_intelligence']['snapshot_hash'] ) ) {
			$snapshot_hash = sanitize_text_field( (string) $input['site_intelligence']['snapshot_hash'] );
		} elseif ( class_exists( 'RWGA_Local_Intelligence', false ) ) {
			$snapshot_hash = RWGA_Local_Intelligence::current_snapshot_hash();
		}

		$recommendation_ids = array();
		$action_ids         = array();
		$cards              = isset( $result['cards'] ) && is_array( $result['cards'] ) ? $result['cards'] : array();

		if ( class_exists( 'RWGA_DB_Recommendations', false ) ) {
			foreach ( $cards as $card ) {
				if ( ! is_array( $card ) ) {
					continue;
				}
				$rid = RWGA_DB_Recommendations::insert(
					array(
						'workflow_key'     => $this->get_key(),
						'agent_key'        => $this->get_agent_key(),
						'page_id'          => ! empty( $card['page_id'] ) ? (int) $card['page_id'] : ( $page_id > 0 ? $page_id : null ),
						'geo_target'       => $geo,
						'priority_level'   => isset( $card['expected_impact'] ) ? sanitize_key( (string) $card['expected_impact'] ) : 'medium',
						'category'         => isset( $card['ux_area'] ) ? sanitize_key( (string) $card['ux_area'] ) : 'ux_opportunity',
						'title'            => isset( $card['title'] ) ? (string) $card['title'] : '',
						'problem'          => isset( $card['problem'] ) ? (string) $card['problem'] : '',
						'why_it_matters'   => isset( $card['audience'] ) ? (string) $card['audience'] : '',
						'recommendation'   => isset( $card['recommendation'] ) ? (string) $card['recommendation'] : '',
						'expected_impact'  => isset( $card['expected_impact'] ) ? (string) $card['expected_impact'] : null,
						'confidence'       => isset( $card['confidence'] ) ? (float) $card['confidence'] : null,
						'lifecycle_status' => 'ux_opportunity_generated',
						'status'           => 'open',
						'report_html'      => $this->format_card_html( $card, isset( $result['engine_source'] ) ? (string) $result['engine_source'] : '' ),
						'created_by'       => $uid,
					)
				);
				if ( $rid > 0 ) {
					$recommendation_ids[] = $rid;
				}
			}
		}

		if ( class_exists( 'RWGA_DB_Intelligence_Actions', false ) ) {
			$actions = isset( $result['actions'] ) && is_array( $result['actions'] ) ? $result['actions'] : array();
			foreach ( $actions as $idx => $action ) {
				if ( ! is_array( $action ) ) {
					continue;
				}
				$rec_link = isset( $recommendation_ids[ $idx ] ) ? (int) $recommendation_ids[ $idx ] : ( ! empty( $recommendation_ids ) ? (int) $recommendation_ids[0] : 0 );
				$aid      = RWGA_DB_Intelligence_Actions::insert(
					array(
						'workflow_key'      => $this->get_key(),
						'recommendation_id' => $rec_link,
						'action_type'       => isset( $action['action_type'] ) ? (string) $action['action_type'] : '',
						'label'             => isset( $action['label'] ) ? (string) $action['label'] : '',
						'action_json'       => isset( $action['action_json'] ) && is_array( $action['action_json'] ) ? $action['action_json'] : array(),
						'entity_type'       => isset( $action['entity_type'] ) ? (string) $action['entity_type'] : 'ux_opportunity',
						'entity_id'         => isset( $action['entity_id'] ) ? (string) $action['entity_id'] : '',
						'page_id'           => $page_id,
						'snapshot_hash'     => $snapshot_hash,
						'status'            => 'pending',
						'created_by'        => $uid,
					)
				);
				if ( $aid > 0 ) {
					$action_ids[] = $aid;
				}
			}
		}

		if ( empty( $recommendation_ids ) && empty( $cards ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No UX opportunity cards were saved.', 'reactwoo-geo-ai' ),
			);
		}

		$usage         = isset( $result['usage'] ) && is_array( $result['usage'] ) ? $result['usage'] : array();
		$remote_run_id = is_array( $remote ) && isset( $remote['remote_run_id'] ) ? (string) $remote['remote_run_id'] : '';
		$telemetry     = class_exists( 'RWGA_Remote_Client', false ) ? RWGA_Remote_Client::telemetry_meta( $usage ) : array();

		do_action(
			'rwga_workflow_persisted',
			$this->get_key(),
			$input,
			$result,
			array_merge(
				array(
					'remote_run_id'  => $remote_run_id,
					'snapshot_hash'  => $snapshot_hash,
					'engine_source'  => isset( $result['engine_source'] ) ? (string) $result['engine_source'] : '',
					'action_ids'     => $action_ids,
				),
				$telemetry
			)
		);

		return array(
			'success'            => true,
			'result'             => $result,
			'recommendation_ids' => $recommendation_ids,
			'action_ids'         => $action_ids,
			'engine_source'      => isset( $result['engine_source'] ) ? (string) $result['engine_source'] : '',
		);
	}

	/**
	 * @param array<string, mixed> $card          Card row.
	 * @param string               $engine_source Engine source label.
	 * @return string
	 */
	private function format_card_html( array $card, $engine_source = '' ) {
		$parts = array();
		if ( '' !== $engine_source ) {
			$label = 'remote_ai' === $engine_source
				? __( 'Remote Geo AI', 'reactwoo-geo-ai' )
				: __( 'Local deterministic fallback', 'reactwoo-geo-ai' );
			$parts[] = '<p class="rwga-engine-source"><em>' . esc_html( $label ) . '</em></p>';
		}
		if ( ! empty( $card['problem'] ) ) {
			$parts[] = '<p><strong>' . esc_html__( 'Problem', 'reactwoo-geo-ai' ) . '</strong>: ' . wp_kses_post( (string) $card['problem'] ) . '</p>';
		}
		if ( ! empty( $card['audience'] ) ) {
			$parts[] = '<p><strong>' . esc_html__( 'Audience', 'reactwoo-geo-ai' ) . '</strong>: ' . esc_html( (string) $card['audience'] ) . '</p>';
		}
		if ( ! empty( $card['recommendation'] ) ) {
			$parts[] = '<p><strong>' . esc_html__( 'Recommendation', 'reactwoo-geo-ai' ) . '</strong>: ' . wp_kses_post( (string) $card['recommendation'] ) . '</p>';
		}
		if ( ! empty( $card['upgrade_label'] ) && empty( $card['available_now'] ) ) {
			$parts[] = '<p class="rwga-upgrade-label"><span class="rwgc-geo-badge rwgc-geo-badge--locked">' . esc_html( (string) $card['upgrade_label'] ) . '</span></p>';
		} elseif ( ! empty( $card['available_now'] ) ) {
			$parts[] = '<p class="rwga-available-now"><span class="rwgc-geo-badge rwgc-geo-badge--success">' . esc_html__( 'Available now', 'reactwoo-geo-ai' ) . '</span></p>';
		}
		return implode( "\n", $parts );
	}
}
