<?php
/**
 * UX analysis workflow (local bounded stub until remote engine is wired).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fake UX analysis for foundation testing.
 */
class RWGA_Workflow_UX_Analysis extends RWGA_Workflow_Base {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'ux_analysis';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'UX analysis', 'reactwoo-geo-ai' );
	}

	/**
	 * @return string
	 */
	public function get_agent_key() {
		return 'ux_analyst';
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
		$s = $this->sanitise_common( $input );
		if ( $s['page_id'] <= 0 && '' === $s['page_url'] ) {
			return new WP_Error(
				'rwga_need_page',
				__( 'Provide a page ID or page URL for UX analysis.', 'reactwoo-geo-ai' )
			);
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input ) {
		$base                   = $this->sanitise_common( $input );
		$base['analysis_focus'] = $this->normalise_analysis_focus( $input );
		return $base;
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
		$in                   = $this->sanitise_common( $input );
		$in['analysis_focus'] = $this->normalise_analysis_focus( $input );

		$mode           = RWGA_Engine::get_mode();
		$remote_payload = class_exists( 'RWGA_Context_Builder', false )
			? RWGA_Context_Builder::for_remote_api(
				RWGA_Context_Builder::build(
					$this->get_key(),
					array_merge(
						$in,
						array(
							'analysis_focus' => $in['analysis_focus'],
							'user_request'   => isset( $input['user_request'] ) ? (string) $input['user_request'] : '',
						)
					)
				)
			)
			: $in;
		$remote = RWGA_Engine::should_try_remote() ? RWGA_Remote_Client::dispatch( $this->get_key(), $remote_payload ) : null;
		$use_api = ! is_wp_error( $remote ) && is_array( $remote ) && ! empty( $remote['engine_response'] );

		if ( $use_api ) {
			$norm = $this->normalise_response( $remote['engine_response'] );
			$rid  = isset( $remote['remote_run_id'] ) ? trim( (string) $remote['remote_run_id'] ) : '';
			$rid  = '' !== $rid ? $rid : null;
			return $this->finish_execute( $in, $norm, $rid );
		}

		if ( is_wp_error( $remote ) && 'remote' === $mode ) {
			return $remote;
		}

		$raw  = $this->produce_stub_response( $in );
		$norm = $this->normalise_response( $raw );
		return $this->finish_execute( $in, $norm, null );
	}

	/**
	 * Persist and shape success payload.
	 *
	 * @param array<string, mixed> $in    Sanitised input.
	 * @param array<string, mixed> $norm  Normalised result.
	 * @param string|null          $remote_run_id Remote run id or null.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function finish_execute( array $in, array $norm, $remote_run_id ) {
		$persisted = $this->persist( $in, $norm, $remote_run_id );
		if ( is_array( $persisted ) && empty( $persisted['success'] ) ) {
			$msg = isset( $persisted['error'] ) ? (string) $persisted['error'] : __( 'Could not persist analysis.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_persist', $msg );
		}
		return $persisted;
	}

	/**
	 * @param array<string, mixed> $response Raw engine response.
	 * @return array<string, mixed>
	 */
	public function normalise_response( array $response ) {
		$out = array(
			'workflow_key'   => $this->get_key(),
			'agent_key'      => $this->get_agent_key(),
			'score'          => isset( $response['score'] ) ? (float) $response['score'] : 0.0,
			'confidence'     => isset( $response['confidence'] ) ? (float) $response['confidence'] : 0.0,
			'summary'        => isset( $response['summary'] ) ? (string) $response['summary'] : '',
			'findings'       => isset( $response['findings'] ) && is_array( $response['findings'] ) ? $response['findings'] : array(),
			'schema_version' => self::DEFAULT_SCHEMA_VERSION,
		);
		return $out;
	}

	/**
	 * @param array<string, mixed> $input         Input used.
	 * @param array<string, mixed> $result        Normalised result.
	 * @param string|null          $remote_run_id Remote engine run id when applicable.
	 * @return array<string, mixed>
	 */
	public function persist( array $input, array $result, $remote_run_id = null ) {
		$uid = get_current_user_id();
		$seed = $this->hash_input( $input );

		$run_id = RWGA_DB_Analysis_Runs::insert(
			array(
				'site_id'               => rwga_get_site_uuid(),
				'workflow_key'          => $this->get_key(),
				'agent_key'             => $this->get_agent_key(),
				'page_id'               => $input['page_id'] > 0 ? $input['page_id'] : null,
				'page_url'              => isset( $input['page_url'] ) ? (string) $input['page_url'] : '',
				'page_type'             => isset( $input['page_type'] ) ? (string) $input['page_type'] : 'page',
				'asset_type'            => 'page',
				'asset_id'              => $input['page_id'] > 0 ? $input['page_id'] : null,
				'analysis_focus'        => isset( $input['analysis_focus'] ) ? (string) $input['analysis_focus'] : 'messaging',
				'geo_target'            => isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '',
				'device_type'           => isset( $input['device_type'] ) ? (string) $input['device_type'] : 'desktop',
				'status'                => 'complete',
				'lifecycle_status'      => 'analysed',
				'score'                 => isset( $result['score'] ) ? $result['score'] : null,
				'confidence'            => isset( $result['confidence'] ) ? $result['confidence'] : null,
				'summary'               => isset( $result['summary'] ) ? $result['summary'] : '',
				'report_html'           => class_exists( 'RWGA_Report_Formatter', false ) ? RWGA_Report_Formatter::format_analysis_report( $result ) : '',
				'input_hash'            => $seed,
				'result_schema_version' => isset( $result['schema_version'] ) ? (string) $result['schema_version'] : self::DEFAULT_SCHEMA_VERSION,
				'remote_run_id'         => $remote_run_id,
				'created_by'            => $uid > 0 ? $uid : null,
			)
		);

		if ( $run_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Could not save analysis run.', 'reactwoo-geo-ai' ),
			);
		}

		RWGA_DB_Analysis_Findings::replace_for_run( $run_id, isset( $result['findings'] ) ? $result['findings'] : array() );

		RWGA_Memory_Service::append(
			'analysis_completed',
			'analysis_run',
			$run_id,
			isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
			isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '',
			array(
				'workflow_key' => $this->get_key(),
				'score'        => isset( $result['score'] ) ? $result['score'] : null,
			)
		);

		/**
		 * Fires after a workflow result is persisted (local intelligence layer).
		 *
		 * @param string               $workflow_key Workflow key.
		 * @param array<string, mixed> $input        Workflow input.
		 * @param array<string, mixed> $result       Normalised result.
		 * @param array<string, mixed> $meta         Telemetry metadata.
		 */
		$telemetry = class_exists( 'RWGA_Remote_Client', false ) ? RWGA_Remote_Client::telemetry_meta() : array();
		do_action(
			'rwga_workflow_persisted',
			$this->get_key(),
			$input,
			$result,
			array_merge(
				array(
					'remote_run_id'   => $remote_run_id,
					'analysis_run_id' => $run_id,
					'input_hash'      => $seed,
					'snapshot_hash'   => class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::current_snapshot_hash() : '',
				),
				$telemetry
			)
		);

		return array(
			'success'         => true,
			'analysis_run_id' => $run_id,
			'result'          => $result,
		);
	}

	/**
	 * @param array<string, mixed> $input Raw or sanitised input (may include analysis_focus).
	 * @return string One of messaging|layout|both.
	 */
	private function normalise_analysis_focus( array $input ) {
		$allowed = array( 'messaging', 'layout', 'both' );
		$f       = isset( $input['analysis_focus'] ) ? sanitize_key( (string) $input['analysis_focus'] ) : '';
		if ( ! in_array( $f, $allowed, true ) && class_exists( 'RWGA_Settings', false ) ) {
			$s = RWGA_Settings::get_settings();
			$f = isset( $s['ux_analysis_focus'] ) ? sanitize_key( (string) $s['ux_analysis_focus'] ) : '';
		}
		if ( ! in_array( $f, $allowed, true ) ) {
			$f = 'messaging';
		}
		return $f;
	}

	/**
	 * Deterministic stub from page context (no remote call).
	 *
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	private function produce_stub_response( array $input ) {
		$focus = isset( $input['analysis_focus'] ) ? sanitize_key( (string) $input['analysis_focus'] ) : 'messaging';
		if ( ! in_array( $focus, array( 'messaging', 'layout', 'both' ), true ) ) {
			$focus = 'messaging';
		}
		$key = (string) ( $input['page_id'] > 0 ? $input['page_id'] : $input['page_url'] );
		$h   = crc32( $key . '|' . ( isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '' ) . '|' . $focus );
		$score = 55 + ( $h % 40 );
		$conf  = 0.75 + ( ( $h % 20 ) / 100 );

		$title_hint = '';
		if ( $input['page_id'] > 0 ) {
			$p = get_post( (int) $input['page_id'] );
			if ( $p instanceof WP_Post ) {
				$title_hint = $p->post_title;
			}
		}

		if ( 'layout' === $focus ) {
			$summary = __( 'Bounded UX scan (local stub, layout focus): infer hierarchy from structure cues; full visual critique needs screenshots.', 'reactwoo-geo-ai' );
		} elseif ( 'both' === $focus ) {
			$summary = __( 'Bounded UX scan (local stub, messaging + layout): review copy, trust, and information hierarchy together.', 'reactwoo-geo-ai' );
		} else {
			$summary = __( 'Bounded UX scan (local stub, messaging focus): review hero clarity, trust, and primary CTA.', 'reactwoo-geo-ai' );
		}
		if ( '' !== $title_hint ) {
			$summary .= ' ' . sprintf(
				/* translators: %s: page title */
				__( 'Page: %s', 'reactwoo-geo-ai' ),
				$title_hint
			);
		}

		if ( ! empty( $input['page_context'] ) && is_array( $input['page_context'] ) ) {
			$pc = $input['page_context'];
			if ( ! empty( $pc['word_count'] ) ) {
				$summary .= ' ' . sprintf(
					/* translators: %d: approximate word count */
					__( 'Content (~%d words).', 'reactwoo-geo-ai' ),
					(int) $pc['word_count']
				);
			}
			if ( ! empty( $pc['builder'] ) ) {
				$summary .= ' ' . sprintf(
					/* translators: %s: builder id */
					__( 'Builder: %s.', 'reactwoo-geo-ai' ),
					sanitize_key( (string) $pc['builder'] )
				);
			}
		}

		$findings = array(
			array(
				'finding_key'        => 'hero_clarity',
				'category'           => 'messaging',
				'severity'           => 'high',
				'confidence'         => min( 0.95, $conf ),
				'title'              => __( 'Hero value proposition could be sharper', 'reactwoo-geo-ai' ),
				'evidence'           => __( 'Headline and supporting line do not state the outcome in one glance.', 'reactwoo-geo-ai' ),
				'recommendation_hint'=> __( 'Lead with the customer outcome, then the mechanism.', 'reactwoo-geo-ai' ),
				'impact_estimate'    => 'high',
			),
			array(
				'finding_key'        => 'cta_visibility',
				'category'           => 'conversion',
				'severity'           => 'medium',
				'confidence'         => 0.72,
				'title'              => __( 'Primary CTA competes with secondary actions', 'reactwoo-geo-ai' ),
				'evidence'           => __( 'Multiple similar-weight buttons reduce the obvious next step.', 'reactwoo-geo-ai' ),
				'recommendation_hint'=> __( 'Emphasise one primary CTA; demote or relocate secondary links.', 'reactwoo-geo-ai' ),
				'impact_estimate'    => 'medium',
			),
			array(
				'finding_key'        => 'trust_signals',
				'category'           => 'trust',
				'severity'           => 'low',
				'confidence'         => 0.68,
				'title'              => __( 'Trust proof is present but generic', 'reactwoo-geo-ai' ),
				'evidence'           => __( 'Logos or testimonials lack specificity (role, result, geography).', 'reactwoo-geo-ai' ),
				'recommendation_hint'=> __( 'Add concrete proof points tied to the hero promise.', 'reactwoo-geo-ai' ),
				'impact_estimate'    => 'low',
			),
		);

		return array(
			'score'      => (float) $score,
			'confidence' => (float) $conf,
			'summary'    => $summary,
			'findings'   => $findings,
		);
	}

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return string
	 */
	private function hash_input( array $input ) {
		$payload = wp_json_encode( $input );
		if ( ! is_string( $payload ) ) {
			$payload = '';
		}
		return substr( sha1( $payload ), 0, 64 );
	}
}
