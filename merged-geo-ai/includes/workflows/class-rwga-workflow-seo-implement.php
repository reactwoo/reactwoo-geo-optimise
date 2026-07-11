<?php
/**
 * SEO implementation workflow (local bounded stub).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces reviewable SEO asset drafts (meta, headings, checklist) without publishing.
 */
class RWGA_Workflow_SEO_Implement extends RWGA_Workflow_Base {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'seo_implement';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'SEO implementation', 'reactwoo-geo-ai' );
	}

	/**
	 * @return string
	 */
	public function get_agent_key() {
		return 'seo_strategist';
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
				__( 'Provide recommendation_id or page_id to generate SEO drafts.', 'reactwoo-geo-ai' )
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
		return array(
			'recommendation_id' => isset( $input['recommendation_id'] ) ? (int) $input['recommendation_id'] : 0,
			'page_id'           => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
			'geo_target'        => isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '',
		);
	}

	/**
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
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute( array $input ) {
		$v = $this->validate_input( $input );
		if ( is_wp_error( $v ) ) {
			return $v;
		}

		$in   = $this->build_request_payload( $input );
		$in   = $this->enrich_from_recommendation( $in );
		$raw  = $this->produce_stub_drafts( $in );
		$norm = $this->normalise_response( $raw );

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
			'workflow_key'   => $this->get_key(),
			'agent_key'      => $this->get_agent_key(),
			'drafts'         => isset( $response['drafts'] ) && is_array( $response['drafts'] ) ? $response['drafts'] : array(),
			'schema_version' => self::DEFAULT_SCHEMA_VERSION,
		);
	}

	/**
	 * @param array<string, mixed> $input  Input used.
	 * @param array<string, mixed> $result Normalised result.
	 * @return array<string, mixed>
	 */
	public function persist( array $input, array $result ) {
		$uid    = get_current_user_id();
		$ids    = array();
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
					'draft_type'        => isset( $d['draft_type'] ) ? sanitize_key( (string) $d['draft_type'] ) : 'seo',
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
			'seo_drafts_generated',
			'implementation_draft',
			$ids[0],
			$page_for_mem,
			$geo_for_mem,
			array(
				'workflow_key' => $this->get_key(),
				'draft_ids'    => $ids,
				'count'        => count( $ids ),
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
	 * @param array<string, mixed> $in Sanitised payload.
	 * @return array<string, mixed>
	 */
	private function produce_stub_drafts( array $in ) {
		$rid = isset( $in['recommendation_id'] ) ? (int) $in['recommendation_id'] : 0;
		$pid = isset( $in['page_id'] ) ? (int) $in['page_id'] : 0;

		$title = '';
		$blurb = '';
		$ctx   = '';

		if ( $rid > 0 ) {
			$rec = RWGA_DB_Recommendations::get( $rid );
			if ( is_array( $rec ) ) {
				$title = isset( $rec['title'] ) ? (string) $rec['title'] : '';
				$prob  = isset( $rec['problem'] ) ? wp_strip_all_tags( (string) $rec['problem'] ) : '';
				$recmd = isset( $rec['recommendation'] ) ? wp_strip_all_tags( (string) $rec['recommendation'] ) : '';
				$ctx   = trim( $prob . "\n\n" . $recmd );
				if ( empty( $pid ) && ! empty( $rec['page_id'] ) ) {
					$pid = (int) $rec['page_id'];
				}
			}
		}

		if ( $pid > 0 && class_exists( 'RWGA_Page_Context', false ) ) {
			$pc = RWGA_Page_Context::collect( $pid );
			if ( ! empty( $pc['title'] ) && '' === $title ) {
				$title = (string) $pc['title'];
			}
			if ( '' === $blurb && ! empty( $pc['content_plain'] ) ) {
				$plain = (string) $pc['content_plain'];
				$blurb = strlen( $plain ) > 200 ? substr( $plain, 0, 200 ) . '…' : $plain;
			}
		}

		if ( '' === $ctx && '' !== $blurb ) {
			$ctx = $blurb;
		}

		$focus = $title ? sanitize_title( $title ) : 'primary-topic';
		$focus = str_replace( '-', ' ', $focus );
		$focus = ucwords( trim( (string) $focus ) );
		if ( '' === $focus ) {
			$focus = __( 'Primary offer', 'reactwoo-geo-ai' );
		}

		$meta_title = $title
			? $title . ' — ' . get_bloginfo( 'name' )
			: sprintf(
				/* translators: %s: site name */
				__( 'Key page — %s', 'reactwoo-geo-ai' ),
				get_bloginfo( 'name' )
			);
		if ( strlen( $meta_title ) > 60 ) {
			$meta_title = substr( $meta_title, 0, 57 ) . '…';
		}

		$meta_desc = $blurb
			? $blurb
			: __( 'Clear value proposition, proof, and a single primary action — tuned for search intent.', 'reactwoo-geo-ai' );
		if ( strlen( $meta_desc ) > 155 ) {
			$meta_desc = substr( $meta_desc, 0, 152 ) . '…';
		}

		$drafts = array(
			array(
				'draft_type'    => 'seo_meta',
				'title'         => __( 'Meta title & description', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'focus_keyphrase'  => $focus,
					'meta_title'       => $meta_title,
					'meta_description' => $meta_desc,
				),
			),
			array(
				'draft_type'    => 'seo_headings',
				'title'         => __( 'Heading outline', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'h1' => $title ? $title : __( 'Solve [customer problem] with [your approach]', 'reactwoo-geo-ai' ),
					'h2' => array(
						__( 'Why it matters', 'reactwoo-geo-ai' ),
						__( 'How it works', 'reactwoo-geo-ai' ),
						__( 'Proof & results', 'reactwoo-geo-ai' ),
						__( 'Pricing & next steps', 'reactwoo-geo-ai' ),
					),
				),
			),
			array(
				'draft_type'    => 'seo_checklist',
				'title'         => __( 'On-page checklist', 'reactwoo-geo-ai' ),
				'input_context' => $ctx,
				'draft_payload' => array(
					'items' => array(
						__( 'One primary topic per page; keyphrase in title, H1, and first paragraph.', 'reactwoo-geo-ai' ),
						__( 'Descriptive alt text on hero and key images.', 'reactwoo-geo-ai' ),
						__( 'Internal links to parent/related pages and a clear CTA destination.', 'reactwoo-geo-ai' ),
						__( 'Canonical and indexability match the live URL you want indexed.', 'reactwoo-geo-ai' ),
					),
				),
			),
		);

		/**
		 * Filter stub SEO drafts before persistence and normalisation.
		 *
		 * @param array<int, array<string, mixed>> $drafts Draft rows.
		 * @param array<string, mixed>            $in     Sanitised input.
		 */
		$drafts = apply_filters( 'rwga_seo_implement_stub_drafts', $drafts, $in );

		return array( 'drafts' => is_array( $drafts ) ? $drafts : array() );
	}
}
