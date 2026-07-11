<?php
/**
 * UX recommendations workflow (local bounded stub).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns analysis findings into structured recommendation cards (stub engine).
 */
class RWGA_Workflow_UX_Recommend extends RWGA_Workflow_Base {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'ux_recommend';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'UX recommendations', 'reactwoo-geo-ai' );
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
		$rid = isset( $input['analysis_run_id'] ) ? (int) $input['analysis_run_id'] : 0;
		if ( $rid <= 0 ) {
			return new WP_Error(
				'rwga_need_analysis',
				__( 'Provide analysis_run_id for recommendations.', 'reactwoo-geo-ai' )
			);
		}
		$run = RWGA_DB_Analysis_Runs::get( $rid );
		if ( ! is_array( $run ) ) {
			return new WP_Error( 'rwga_analysis_missing', __( 'Analysis run not found.', 'reactwoo-geo-ai' ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input ) {
		$cats = isset( $input['selected_categories'] ) && is_array( $input['selected_categories'] ) ? array_values( array_filter( array_map( 'sanitize_key', $input['selected_categories'] ) ) ) : array();
		return array(
			'analysis_run_id' => isset( $input['analysis_run_id'] ) ? (int) $input['analysis_run_id'] : 0,
			'business_goal'   => isset( $input['business_goal'] ) ? sanitize_text_field( (string) $input['business_goal'] ) : '',
			'geo_target'      => isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '',
			'selected_categories' => $cats,
		);
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

		$analysis_run_id = (int) $input['analysis_run_id'];
		$run               = RWGA_DB_Analysis_Runs::get( $analysis_run_id );
		if ( ! is_array( $run ) ) {
			return new WP_Error( 'rwga_analysis_missing', __( 'Analysis run not found.', 'reactwoo-geo-ai' ) );
		}

		$findings = RWGA_DB_Analysis_Findings::list_for_run( $analysis_run_id );
		$goal     = isset( $input['business_goal'] ) ? sanitize_text_field( (string) $input['business_goal'] ) : '';
		$cats     = isset( $input['selected_categories'] ) && is_array( $input['selected_categories'] ) ? array_values( array_filter( array_map( 'sanitize_key', $input['selected_categories'] ) ) ) : array();
		$mode     = class_exists( 'RWGA_Engine', false ) ? RWGA_Engine::get_mode() : 'local';

		$page_id = isset( $run['page_id'] ) ? (int) $run['page_id'] : 0;

		$remote_payload = class_exists( 'RWGA_Context_Builder', false )
			? RWGA_Context_Builder::for_remote_api(
				RWGA_Context_Builder::build(
					$this->get_key(),
					array(
						'page_id'               => $page_id,
						'analysis_run_id'       => $analysis_run_id,
						'business_goal'         => $goal,
						'geo_target'            => isset( $input['geo_target'] ) ? sanitize_text_field( (string) $input['geo_target'] ) : ( isset( $run['geo_target'] ) ? (string) $run['geo_target'] : '' ),
						'findings'              => is_array( $findings ) ? $findings : array(),
						'selected_categories'   => $cats,
						'user_request'          => $goal,
						'analysis_summary'      => isset( $run['summary'] ) ? (string) $run['summary'] : '',
					)
				)
			)
			: array(
				'analysis_run_id'  => $analysis_run_id,
				'business_goal'    => $goal,
				'findings'         => is_array( $findings ) ? $findings : array(),
			);
		$remote = class_exists( 'RWGA_Engine', false ) && RWGA_Engine::should_try_remote()
			? RWGA_Remote_Client::dispatch( $this->get_key(), $remote_payload )
			: null;
		$use_api = ! is_wp_error( $remote ) && is_array( $remote ) && ! empty( $remote['engine_response'] );

		if ( $use_api ) {
			$norm = $this->normalise_response( $remote['engine_response'] );
		} else {
			if ( is_wp_error( $remote ) && 'remote' === $mode ) {
				return $remote;
			}
			$raw  = $this->produce_stub_recommendations( $run, $findings, $goal );
			$norm = $this->normalise_response( $raw );
		}

		$in = array(
			'analysis_run_id' => $analysis_run_id,
			'page_id'         => isset( $run['page_id'] ) ? (int) $run['page_id'] : 0,
			'geo_target'      => isset( $input['geo_target'] ) ? sanitize_text_field( (string) $input['geo_target'] ) : ( isset( $run['geo_target'] ) ? (string) $run['geo_target'] : '' ),
			'selected_categories' => $cats,
		);

		$persisted = $this->persist( $in, $norm );
		if ( is_array( $persisted ) && empty( $persisted['success'] ) ) {
			$msg = isset( $persisted['error'] ) ? (string) $persisted['error'] : __( 'Could not save recommendations.', 'reactwoo-geo-ai' );
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
			'recommendations'=> isset( $response['recommendations'] ) && is_array( $response['recommendations'] ) ? $response['recommendations'] : array(),
			'schema_version' => self::DEFAULT_SCHEMA_VERSION,
		);
	}

	/**
	 * @param array<string, mixed> $input    Context.
	 * @param array<string, mixed> $result   Normalised result.
	 * @return array<string, mixed>
	 */
	public function persist( array $input, array $result ) {
		$uid             = get_current_user_id();
		$analysis_run_id = isset( $input['analysis_run_id'] ) ? (int) $input['analysis_run_id'] : 0;
		$page_id         = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$geo             = isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '';
		$selected_categories = isset( $input['selected_categories'] ) && is_array( $input['selected_categories'] ) ? $input['selected_categories'] : array();

		$recs = isset( $result['recommendations'] ) && is_array( $result['recommendations'] ) ? $result['recommendations'] : array();
		$ids  = array();

		foreach ( $recs as $rec ) {
			if ( ! is_array( $rec ) ) {
				continue;
			}
			$page_placement = isset( $rec['page_placement'] ) ? sanitize_text_field( (string) $rec['page_placement'] ) : '';
			$suggested_arr  = isset( $rec['suggested_copy'] ) && is_array( $rec['suggested_copy'] ) ? $rec['suggested_copy'] : array();
			$suggested_json = ! empty( $suggested_arr ) ? wp_json_encode( $suggested_arr ) : null;
			$row_for_html   = array_merge(
				$rec,
				array(
					'page_placement' => $page_placement,
					'suggested_copy' => $suggested_arr,
				)
			);
			$id = RWGA_DB_Recommendations::insert(
				array(
					'analysis_run_id' => $analysis_run_id,
					'workflow_key'    => $this->get_key(),
					'agent_key'       => $this->get_agent_key(),
					'page_id'         => $page_id > 0 ? $page_id : null,
					'geo_target'      => $geo,
					'priority_level'  => isset( $rec['priority_level'] ) ? sanitize_key( (string) $rec['priority_level'] ) : 'medium',
					'category'        => isset( $rec['category'] ) ? sanitize_key( (string) $rec['category'] ) : 'general',
					'title'           => isset( $rec['title'] ) ? sanitize_text_field( (string) $rec['title'] ) : '',
					'problem'         => isset( $rec['problem'] ) ? wp_kses_post( (string) $rec['problem'] ) : '',
					'why_it_matters'  => isset( $rec['why_it_matters'] ) ? wp_kses_post( (string) $rec['why_it_matters'] ) : '',
					'recommendation'  => isset( $rec['recommendation'] ) ? wp_kses_post( (string) $rec['recommendation'] ) : '',
					'page_placement'  => '' !== $page_placement ? $page_placement : null,
					'suggested_copy_json' => $suggested_json,
					'selected_categories' => $selected_categories,
					'report_html'     => class_exists( 'RWGA_Report_Formatter', false ) ? RWGA_Report_Formatter::format_recommendation_card_html( $row_for_html ) : '',
					'expected_impact' => isset( $rec['expected_impact'] ) ? sanitize_text_field( (string) $rec['expected_impact'] ) : null,
					'confidence'      => isset( $rec['confidence'] ) ? (float) $rec['confidence'] : null,
					'status'          => 'open',
					'lifecycle_status'=> 'recommendations_generated',
					'created_by'      => $uid > 0 ? $uid : null,
				)
			);
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		if ( empty( $ids ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No recommendation rows were saved.', 'reactwoo-geo-ai' ),
			);
		}

		RWGA_Memory_Service::append(
			'recommendations_generated',
			'analysis_run',
			$analysis_run_id,
			$page_id,
			$geo,
			array(
				'workflow_key'        => $this->get_key(),
				'recommendation_ids'  => $ids,
				'count'               => count( $ids ),
			)
		);
		if ( $analysis_run_id > 0 && class_exists( 'RWGA_DB_Analysis_Runs', false ) ) {
			RWGA_DB_Analysis_Runs::set_lifecycle_status( $analysis_run_id, 'recommendations_generated' );
		}

		$telemetry = class_exists( 'RWGA_Remote_Client', false ) ? RWGA_Remote_Client::telemetry_meta() : array();
		do_action(
			'rwga_workflow_persisted',
			$this->get_key(),
			$input,
			$result,
			array_merge(
				array(
					'analysis_run_id' => $analysis_run_id,
					'snapshot_hash'   => class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::current_snapshot_hash() : '',
				),
				$telemetry
			)
		);

		return array(
			'success'            => true,
			'recommendation_ids' => $ids,
			'result'             => $result,
		);
	}

	/**
	 * @param array<string, mixed>           $run      Analysis run row.
	 * @param array<int, array<string, mixed>> $findings Findings.
	 * @param string                         $goal     Business goal hint.
	 * @return array<string, mixed>
	 */
	private function produce_stub_recommendations( array $run, array $findings, $goal ) {
		$out = array();
		$goal = trim( (string) $goal );

		if ( ! empty( $findings ) ) {
			$findings = array_slice( $findings, 0, 12 );
			foreach ( $findings as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$sev = isset( $f['severity'] ) ? sanitize_key( (string) $f['severity'] ) : 'medium';
				$pri = $sev;
				if ( ! in_array( $pri, array( 'high', 'medium', 'low' ), true ) ) {
					$pri = 'medium';
				}
				$title = isset( $f['title'] ) ? (string) $f['title'] : '';
				$cat   = isset( $f['category'] ) ? sanitize_key( (string) $f['category'] ) : 'general';

				$problem = isset( $f['evidence'] ) && '' !== trim( (string) $f['evidence'] )
					? (string) $f['evidence']
					: $title;

				$why = __( 'Friction here reduces clarity and conversion; fixing it improves the primary user path.', 'reactwoo-geo-ai' );
				if ( '' !== $goal ) {
					$why .= ' ' . sprintf(
						/* translators: %s: business goal */
						__( 'Aligned with goal: %s.', 'reactwoo-geo-ai' ),
						$goal
					);
				}

				$rec_text_raw = isset( $f['recommendation_hint'] ) && '' !== trim( (string) $f['recommendation_hint'] )
					? (string) $f['recommendation_hint']
					: __( 'Clarify the offer, strengthen the primary CTA, and add proof near the decision point.', 'reactwoo-geo-ai' );

				$impact = isset( $f['impact_estimate'] ) ? sanitize_text_field( (string) $f['impact_estimate'] ) : 'medium';
				$conf   = isset( $f['confidence'] ) ? (float) $f['confidence'] : 0.7;

				$title_out = $title
					? __( 'Prioritise:', 'reactwoo-geo-ai' ) . ' ' . $title
					: __( 'Improve conversion focus', 'reactwoo-geo-ai' );

				$placement = $this->stub_page_placement_for_category( $cat );
				$suggested = $this->stub_suggested_copy( $cat, $title, $problem, $rec_text_raw );

				$out[] = array(
					'priority_level'   => $pri,
					'category'         => $cat,
					'title'            => $title_out,
					'problem'          => $problem,
					'why_it_matters'   => $why,
					'recommendation'   => __( 'Implementation:', 'reactwoo-geo-ai' ) . ' ' . sanitize_text_field( $rec_text_raw ),
					'page_placement'   => $placement,
					'suggested_copy'   => $suggested,
					'expected_impact'  => $impact,
					'confidence'       => $conf,
				);
			}
		} else {
			$summary = isset( $run['summary'] ) ? (string) $run['summary'] : '';
			$problem = __( 'No granular findings were stored; the page still needs a sharper narrative and CTA.', 'reactwoo-geo-ai' );
			$why     = __( 'Visitors decide quickly; ambiguity loses leads.', 'reactwoo-geo-ai' );
			$base_recommendation = $summary
				? __( 'Use the analysis summary as a starting point: tighten the hero, add proof, and single primary action.', 'reactwoo-geo-ai' ) . ' ' . $summary
				: __( 'Tighten the hero, add proof, and use a single primary action.', 'reactwoo-geo-ai' );
			$out[]   = array(
				'priority_level'  => 'medium',
				'category'        => 'general',
				'title'           => __( 'Establish a clearer primary story', 'reactwoo-geo-ai' ),
				'problem'         => $problem,
				'why_it_matters'  => $why,
				'recommendation'  => __( 'Implementation:', 'reactwoo-geo-ai' ) . ' ' . sanitize_text_field( $base_recommendation ),
				'page_placement'  => __( 'Hero / first screen: headline, supporting line, and primary CTA.', 'reactwoo-geo-ai' ),
				'suggested_copy'  => $this->stub_suggested_copy( 'general', '', $problem, $base_recommendation ),
				'expected_impact' => 'medium',
				'confidence'      => 0.65,
			);
		}

		return array( 'recommendations' => $out );
	}

	/**
	 * Where on the page this applies (stub).
	 *
	 * @param string $category Category slug.
	 * @return string
	 */
	private function stub_page_placement_for_category( $category ) {
		$category = sanitize_key( (string) $category );
		switch ( $category ) {
			case 'messaging':
				return __( 'Hero / first viewport: main headline (H1) and lead line under it.', 'reactwoo-geo-ai' );
			case 'conversion':
				return __( 'Primary CTA row in hero or sticky bar; secondary CTA one step below or beside.', 'reactwoo-geo-ai' );
			case 'trust':
				return __( 'Trust strip directly under hero or above footer near checkout/contact.', 'reactwoo-geo-ai' );
			case 'layout':
				return __( 'Body sections: headings, bullets, and image captions between hero and footer.', 'reactwoo-geo-ai' );
			case 'performance':
			case 'accessibility':
			case 'content':
				return __( 'Above-the-fold block and the next content section.', 'reactwoo-geo-ai' );
			default:
				return __( 'Hero and first scroll section visitors see.', 'reactwoo-geo-ai' );
		}
	}

	/**
	 * Paste-ready copy object for local stub engine.
	 *
	 * @param string $category Category slug.
	 * @param string $title    Finding title.
	 * @param string $problem  Problem text.
	 * @param string $hint     Recommendation hint.
	 * @return array<string, string>
	 */
	private function stub_suggested_copy( $category, $title, $problem, $hint ) {
		$category = sanitize_key( (string) $category );
		$short    = '' !== trim( (string) $title ) ? sanitize_text_field( (string) $title ) : __( 'this page', 'reactwoo-geo-ai' );
		$primary  = __( 'Shop now', 'reactwoo-geo-ai' );
		$second   = __( 'Browse collection', 'reactwoo-geo-ai' );
		if ( false !== stripos( (string) $hint, 'demo' ) || false !== stripos( (string) $problem, 'demo' ) ) {
			$primary = __( 'Book a demo', 'reactwoo-geo-ai' );
			$second  = __( 'See how it works', 'reactwoo-geo-ai' );
		}

		$headline = sprintf(
			/* translators: %s: short page/finding label */
			__( 'The better way to fix %s — clear outcome, less friction.', 'reactwoo-geo-ai' ),
			$short
		);
		$sub = __( 'Everything you need to decide in one screen: proof, pricing context, and a single next step.', 'reactwoo-geo-ai' );
		$snippet = __( 'Join thousands of happy customers — 4.9★ average · secure checkout', 'reactwoo-geo-ai' );

		if ( 'trust' === $category ) {
			$headline = __( 'Trusted by teams who need results they can measure', 'reactwoo-geo-ai' );
			$sub      = __( 'Short quote + logo row + one numeric proof point (time saved or ROI).', 'reactwoo-geo-ai' );
			$snippet  = __( '“We cut onboarding time by 40% in week one.” — Customer name, Role', 'reactwoo-geo-ai' );
		} elseif ( 'conversion' === $category ) {
			$headline = __( 'Ready when you are', 'reactwoo-geo-ai' );
			$sub      = __( 'One primary action; secondary stays visually quieter.', 'reactwoo-geo-ai' );
		} elseif ( 'layout' === $category ) {
			$headline = __( 'Skim-friendly sections', 'reactwoo-geo-ai' );
			$sub      = __( 'Use H2 + 3 bullets + one image per major topic so visitors can scan.', 'reactwoo-geo-ai' );
		}

		return array(
			'replace_this'        => __( 'Current headline, subhead, and button labels in the section above.', 'reactwoo-geo-ai' ),
			'headline'            => $headline,
			'subheadline'         => trim( $sub ),
			'primary_cta_label'   => $primary,
			'secondary_cta_label' => $second,
			'supporting_snippet'  => $snippet,
		);
	}
}
