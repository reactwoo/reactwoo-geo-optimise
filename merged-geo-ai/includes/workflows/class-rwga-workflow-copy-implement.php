<?php
/**
 * Copy implementation workflow (local bounded stub + optional remote engine).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces reviewable copy drafts (hero, CTA, trust) without publishing.
 */
class RWGA_Workflow_Copy_Implement extends RWGA_Workflow_Base {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'copy_implement';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Copy implementation', 'reactwoo-geo-ai' );
	}

	/**
	 * @return string
	 */
	public function get_agent_key() {
		return 'ux_writer';
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
		$rid = isset( $input['recommendation_id'] ) ? (int) $input['recommendation_id'] : 0;
		$pid = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		if ( $rid <= 0 && $pid <= 0 ) {
			return new WP_Error(
				'rwga_need_source',
				__( 'Provide recommendation_id or page_id to generate copy drafts.', 'reactwoo-geo-ai' )
			);
		}
		if ( $rid > 0 && ! is_array( RWGA_DB_Recommendations::get( $rid ) ) ) {
			return new WP_Error( 'rwga_rec_missing', __( 'Recommendation not found.', 'reactwoo-geo-ai' ) );
		}
		if ( $pid > 0 && ! get_post( $pid ) instanceof WP_Post ) {
			return new WP_Error( 'rwga_page_missing', __( 'Page not found.', 'reactwoo-geo-ai' ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input ) {
		$countries = isset( $input['countries'] ) && is_array( $input['countries'] ) ? $input['countries'] : array();
		return array(
			'recommendation_id'  => isset( $input['recommendation_id'] ) ? (int) $input['recommendation_id'] : 0,
			'page_id'            => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
			'geo_target'         => isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '',
			'visibility_rule_id' => isset( $input['visibility_rule_id'] ) ? absint( $input['visibility_rule_id'] ) : 0,
			'countries'          => $countries,
			'source'             => isset( $input['source'] ) ? sanitize_key( (string) $input['source'] ) : '',
		);
	}

	/**
	 * Copy page/geo from the linked recommendation when omitted.
	 *
	 * @param array<string, mixed> $in Sanitised payload.
	 * @return array<string, mixed>
	 */
	private function enrich_from_recommendation( array $in ) {
		$rid = isset( $in['recommendation_id'] ) ? (int) $in['recommendation_id'] : 0;
		if ( $rid <= 0 ) {
			return $in;
		}
		$rec = RWGA_DB_Recommendations::get( $rid );
		if ( ! is_array( $rec ) ) {
			return $in;
		}
		if ( empty( $in['page_id'] ) && ! empty( $rec['page_id'] ) ) {
			$in['page_id'] = (int) $rec['page_id'];
		}
		if ( ( ! isset( $in['geo_target'] ) || '' === $in['geo_target'] ) && ! empty( $rec['geo_target'] ) ) {
			$in['geo_target'] = strtoupper( substr( sanitize_text_field( (string) $rec['geo_target'] ), 0, 2 ) );
		}
		return $in;
	}

	/**
	 * @param array<string, mixed> $in Sanitised payload.
	 * @return array<string, mixed>
	 */
	private function enrich_targeting( array $in ) {
		if ( ! class_exists( 'RWGA_Targeting_Context_Bridge', false ) ) {
			return $in;
		}
		$targeting = RWGA_Targeting_Context_Bridge::resolve( $in );
		$in['targeting_context'] = $targeting;
		$in['geo_target']        = RWGA_Targeting_Context_Bridge::resolve_geo_target( $in, $targeting );
		if ( empty( $in['visibility_rule_id'] ) && ! empty( $targeting['rule_id'] ) ) {
			$in['visibility_rule_id'] = (int) $targeting['rule_id'];
		}
		return $in;
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

		$in  = $this->build_request_payload( $input );
		$in  = $this->enrich_from_recommendation( $in );
		$in  = $this->enrich_targeting( $in );

		$page_context = array();
		if ( ! empty( $in['page_id'] ) && class_exists( 'RWGA_Page_Context', false ) ) {
			$page_context = RWGA_Page_Context::collect( (int) $in['page_id'] );
		}

		$mode   = class_exists( 'RWGA_Engine', false ) ? RWGA_Engine::get_mode() : 'local';
		$remote = class_exists( 'RWGA_Engine', false ) && RWGA_Engine::should_try_remote()
			? RWGA_Remote_Client::dispatch(
				$this->get_key(),
				array(
					'page_id'            => isset( $in['page_id'] ) ? (int) $in['page_id'] : 0,
					'geo_target'         => isset( $in['geo_target'] ) ? (string) $in['geo_target'] : '',
					'page_context'       => $page_context,
					'targeting_context'  => isset( $in['targeting_context'] ) ? $in['targeting_context'] : array(),
					'recommendation_id'  => isset( $in['recommendation_id'] ) ? (int) $in['recommendation_id'] : 0,
					'visibility_rule_id' => isset( $in['visibility_rule_id'] ) ? (int) $in['visibility_rule_id'] : 0,
				)
			)
			: null;
		$use_api = ! is_wp_error( $remote ) && is_array( $remote ) && ! empty( $remote['engine_response'] );

		if ( $use_api ) {
			$norm = $this->normalise_response( $remote['engine_response'] );
		} else {
			if ( is_wp_error( $remote ) && 'remote' === $mode ) {
				return $remote;
			}
			$raw  = $this->produce_stub_drafts( $in, $page_context );
			$norm = $this->normalise_response( $raw );
		}

		$persisted = $this->persist( $in, $norm );
		if ( is_array( $persisted ) && empty( $persisted['success'] ) ) {
			$msg = isset( $persisted['error'] ) ? (string) $persisted['error'] : __( 'Could not save drafts.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_persist', $msg );
		}
		return $persisted;
	}

	/**
	 * @param array<string, mixed> $response Raw engine response.
	 * @return array<string, mixed>
	 */
	public function normalise_response( array $response ) {
		return array(
			'workflow_key'    => $this->get_key(),
			'agent_key'       => $this->get_agent_key(),
			'drafts'          => isset( $response['drafts'] ) && is_array( $response['drafts'] ) ? $response['drafts'] : array(),
			'schema_version'  => self::DEFAULT_SCHEMA_VERSION,
		);
	}

	/**
	 * @param array<string, mixed> $input    Input used.
	 * @param array<string, mixed> $result   Normalised result.
	 * @return array<string, mixed>
	 */
	public function persist( array $input, array $result ) {
		$uid = get_current_user_id();
		$ids = array();

		$drafts = isset( $result['drafts'] ) && is_array( $result['drafts'] ) ? $result['drafts'] : array();

		foreach ( $drafts as $d ) {
			if ( ! is_array( $d ) ) {
				continue;
			}
			$payload = isset( $d['draft_payload'] ) && is_array( $d['draft_payload'] ) ? $d['draft_payload'] : array();

			$rid_in = isset( $input['recommendation_id'] ) ? (int) $input['recommendation_id'] : 0;
			$pid_in = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
			$geo_in = isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '';

			$id = RWGA_DB_Implementation_Drafts::insert(
				array(
					'recommendation_id' => $rid_in > 0 ? $rid_in : null,
					'workflow_key'      => $this->get_key(),
					'draft_type'        => isset( $d['draft_type'] ) ? sanitize_key( (string) $d['draft_type'] ) : 'copy',
					'page_id'           => $pid_in > 0 ? $pid_in : null,
					'geo_target'        => $geo_in,
					'title'             => isset( $d['title'] ) ? sanitize_text_field( (string) $d['title'] ) : '',
					'input_context'     => isset( $d['input_context'] ) ? wp_kses_post( (string) $d['input_context'] ) : '',
					'draft_payload'     => $payload,
					'report_html'       => class_exists( 'RWGA_Report_Formatter', false ) ? RWGA_Report_Formatter::format_draft_report( array_merge( $d, array( 'draft_payload' => $payload ) ) ) : '',
					'implementation_route' => 'draft',
					'status'            => 'draft',
					'created_by'        => $uid > 0 ? $uid : null,
				)
			);
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		if ( empty( $ids ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No draft rows were saved.', 'reactwoo-geo-ai' ),
			);
		}

		$page_for_mem = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$geo_for_mem  = isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '';

		RWGA_Memory_Service::append(
			'copy_drafts_generated',
			'implementation_draft',
			$ids[0],
			$page_for_mem,
			$geo_for_mem,
			array(
				'workflow_key'       => $this->get_key(),
				'draft_ids'          => $ids,
				'count'              => count( $ids ),
				'visibility_rule_id' => isset( $input['visibility_rule_id'] ) ? (int) $input['visibility_rule_id'] : 0,
				'targeting_summary'  => isset( $input['targeting_context']['summary'] ) ? (string) $input['targeting_context']['summary'] : '',
			)
		);
		$rec_for_status = isset( $input['recommendation_id'] ) ? (int) $input['recommendation_id'] : 0;
		if ( $rec_for_status > 0 && class_exists( 'RWGA_DB_Recommendations', false ) ) {
			RWGA_DB_Recommendations::set_lifecycle_status( $rec_for_status, 'implementation_generated' );
		}

		return array(
			'success'   => true,
			'draft_ids' => $ids,
			'result'    => $result,
		);
	}

	/**
	 * @param array<string, mixed> $in           Sanitised payload.
	 * @param array<string, mixed> $page_context Collected page context.
	 * @return array<string, mixed>
	 */
	private function produce_stub_drafts( array $in, array $page_context = array() ) {
		$rid = isset( $in['recommendation_id'] ) ? (int) $in['recommendation_id'] : 0;
		$pid = isset( $in['page_id'] ) ? (int) $in['page_id'] : 0;

		$targeting = isset( $in['targeting_context'] ) && is_array( $in['targeting_context'] ) ? $in['targeting_context'] : array();
		$geo       = isset( $in['geo_target'] ) ? trim( (string) $in['geo_target'] ) : '';

		$rec          = null;
		$title        = '';
		$blurb        = '';
		$ctx          = '';
		$problem_text = '';
		$action_text  = '';

		if ( $rid > 0 ) {
			$rec = RWGA_DB_Recommendations::get( $rid );
			if ( is_array( $rec ) ) {
				$title = isset( $rec['title'] ) ? (string) $rec['title'] : '';
				$prob  = isset( $rec['problem'] ) ? wp_strip_all_tags( (string) $rec['problem'] ) : '';
				$recmd = isset( $rec['recommendation'] ) ? wp_strip_all_tags( (string) $rec['recommendation'] ) : '';
				$problem_text = trim( $prob );
				$action_text  = trim( $recmd );
				$ctx          = trim( $prob . "\n\n" . $recmd );
				if ( empty( $pid ) && ! empty( $rec['page_id'] ) ) {
					$pid = (int) $rec['page_id'];
				}
				if ( '' === $geo && ! empty( $rec['geo_target'] ) ) {
					$geo = (string) $rec['geo_target'];
				}
			}
		}

		if ( empty( $page_context ) && $pid > 0 && class_exists( 'RWGA_Page_Context', false ) ) {
			$page_context = RWGA_Page_Context::collect( $pid );
		}
		if ( ! empty( $page_context['title'] ) && '' === $title ) {
			$title = (string) $page_context['title'];
		}
		if ( '' === $blurb && ! empty( $page_context['content_plain'] ) ) {
			$plain = (string) $page_context['content_plain'];
			$blurb = strlen( $plain ) > 800 ? substr( $plain, 0, 800 ) . '…' : $plain;
		}

		$targeting_brief = isset( $targeting['adapt_brief'] ) ? trim( (string) $targeting['adapt_brief'] ) : '';
		$targeting_line  = isset( $targeting['summary'] ) ? trim( (string) $targeting['summary'] ) : '';

		$ctx_parts = array();
		if ( '' !== $targeting_brief ) {
			$ctx_parts[] = $targeting_brief;
		}
		if ( '' !== $blurb ) {
			$ctx_parts[] = __( 'Current page content:', 'reactwoo-geo-ai' ) . "\n" . $blurb;
		}
		if ( '' !== $ctx ) {
			$ctx_parts[] = $ctx;
		}
		$ctx = trim( implode( "\n\n", array_filter( $ctx_parts ) ) );

		$geo_label = self::geo_market_label( $geo, $targeting );
		$h         = $title ? __( 'Turn clarity into conversion', 'reactwoo-geo-ai' ) : __( 'Lead with a clear customer outcome', 'reactwoo-geo-ai' );
		if ( $title && '' !== $geo_label ) {
			$h = sprintf(
				/* translators: 1: page title, 2: market label */
				__( '%1$s — tailored for %2$s', 'reactwoo-geo-ai' ),
				$title,
				$geo_label
			);
		} elseif ( $title ) {
			$h = sprintf(
				/* translators: %s: page or issue title */
				__( 'Better story for: %s', 'reactwoo-geo-ai' ),
				$title
			);
		}
		if ( '' !== $action_text ) {
			$h = sanitize_text_field( substr( $action_text, 0, 90 ) );
		}

		$subheadline = __( 'Explain the outcome in one sentence, then how you deliver it.', 'reactwoo-geo-ai' );
		if ( '' !== $problem_text ) {
			$subheadline = sprintf(
				/* translators: %s: short problem summary */
				__( 'Address this friction first: %s', 'reactwoo-geo-ai' ),
				sanitize_text_field( substr( $problem_text, 0, 110 ) )
			);
		} elseif ( '' !== $targeting_line ) {
			$subheadline = sprintf(
				/* translators: %s: targeting summary */
				__( 'Written for visitors matching: %s', 'reactwoo-geo-ai' ),
				sanitize_text_field( substr( $targeting_line, 0, 140 ) )
			);
		}

		$devices = isset( $targeting['device_types'] ) && is_array( $targeting['device_types'] ) ? $targeting['device_types'] : array();
		if ( in_array( 'mobile', $devices, true ) ) {
			$subheadline = __( 'Short, scannable copy for mobile visitors — lead with the outcome.', 'reactwoo-geo-ai' );
		}

		$primary_cta = __( 'Get started', 'reactwoo-geo-ai' );
		if ( false !== stripos( $action_text, 'book' ) || false !== stripos( $problem_text, 'demo' ) ) {
			$primary_cta = __( 'Book a demo', 'reactwoo-geo-ai' );
		} elseif ( false !== stripos( $action_text, 'pricing' ) ) {
			$primary_cta = __( 'See pricing', 'reactwoo-geo-ai' );
		}

		$campaigns = isset( $targeting['campaigns'] ) && is_array( $targeting['campaigns'] ) ? $targeting['campaigns'] : array();
		if ( ! empty( $campaigns[0] ) ) {
			$primary_cta = sprintf(
				/* translators: %s: campaign name */
				__( 'Explore %s', 'reactwoo-geo-ai' ),
				sanitize_text_field( substr( (string) $campaigns[0], 0, 40 ) )
			);
		}

		$trust_line = __( 'Trusted by teams in 12 countries — measurable results in 30 days.', 'reactwoo-geo-ai' );
		if ( '' !== $geo_label ) {
			$trust_line = sprintf(
				/* translators: %s: market label */
				__( 'Trusted by customers in %s — local proof and credible outcomes.', 'reactwoo-geo-ai' ),
				$geo_label
			);
		} elseif ( '' !== $action_text ) {
			$trust_line = sprintf(
				/* translators: %s: short action summary */
				__( 'Make the promise specific and credible: %s', 'reactwoo-geo-ai' ),
				sanitize_text_field( substr( $action_text, 0, 120 ) )
			);
		}

		$drafts = array(
			array(
				'draft_type'    => 'hero_rewrite',
				'title'         => '' !== $geo_label
					? sprintf(
						/* translators: %s: market */
						__( 'Hero — %s option', 'reactwoo-geo-ai' ),
						$geo_label
					)
					: __( 'Hero — primary option', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'headline'    => $h,
					'subheadline' => $subheadline,
					'cta_text'    => $primary_cta,
					'geo_target'  => $geo,
					'targeting'   => $targeting_line,
				),
			),
			array(
				'draft_type'    => 'cta_variant',
				'title'         => __( 'CTA — alternatives', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'primary_cta'   => $primary_cta,
					'secondary_cta' => __( 'See pricing', 'reactwoo-geo-ai' ),
					'geo_target'    => $geo,
				),
			),
			array(
				'draft_type'    => 'trust_snippet',
				'title'         => __( 'Trust — proof line', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'text'       => $trust_line,
					'geo_target' => $geo,
				),
			),
		);

		/**
		 * Filter stub copy drafts before persistence and normalisation.
		 *
		 * @param array<int, array<string, mixed>> $drafts Draft rows.
		 * @param array<string, mixed>            $in     Sanitised input.
		 */
		$drafts = apply_filters( 'rwga_copy_implement_stub_drafts', $drafts, $in );

		return array( 'drafts' => is_array( $drafts ) ? $drafts : array() );
	}

	/**
	 * @param string               $geo       ISO2 geo target.
	 * @param array<string, mixed> $targeting Targeting context.
	 * @return string
	 */
	private static function geo_market_label( $geo, array $targeting ) {
		if ( preg_match( '/^[A-Z]{2}$/', $geo ) && class_exists( 'RWGC_Countries', false ) ) {
			$opts = RWGC_Countries::get_options();
			if ( isset( $opts[ $geo ] ) ) {
				return (string) $opts[ $geo ];
			}
		}
		$codes = isset( $targeting['geo_codes'] ) && is_array( $targeting['geo_codes'] ) ? $targeting['geo_codes'] : array();
		if ( ! empty( $codes[0] ) && class_exists( 'RWGC_Countries', false ) ) {
			$iso  = strtoupper( (string) $codes[0] );
			$opts = RWGC_Countries::get_options();
			if ( isset( $opts[ $iso ] ) ) {
				return (string) $opts[ $iso ];
			}
		}
		return preg_match( '/^[A-Z]{2}$/', $geo ) ? $geo : '';
	}
}
