<?php
/**
 * Site intelligence remote workflows (Geo suite audit / explain / debug).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parameterised intelligence workflow — remote-only with structured JSON responses.
 */
class RWGA_Workflow_Intelligence extends RWGA_Workflow_Base {

	/**
	 * @var string
	 */
	private $key;

	/**
	 * @var string
	 */
	private $label;

	/**
	 * @var string
	 */
	private $agent_key;

	/**
	 * @param string $key       Workflow key.
	 * @param string $label     Human label.
	 * @param string $agent_key Agent registry key.
	 */
	public function __construct( $key, $label, $agent_key ) {
		$this->key       = sanitize_key( (string) $key );
		$this->label     = (string) $label;
		$this->agent_key = sanitize_key( (string) $agent_key );
	}

	/**
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * @return string
	 */
	public function get_agent_key() {
		return $this->agent_key;
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
		if ( class_exists( 'RWGA_AI_Usage_Guard', false ) ) {
			$guard = RWGA_AI_Usage_Guard::can_run_workflow( $this->get_key() );
			if ( empty( $guard['allowed'] ) ) {
				return new WP_Error(
					'rwga_workflow_blocked',
					isset( $guard['reason'] ) ? (string) $guard['reason'] : __( 'Workflow not allowed.', 'reactwoo-geo-ai' )
				);
			}
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input ) {
		$base = $this->sanitise_common( $input );

		if ( isset( $input['rule_id'] ) ) {
			$base['rule_id'] = sanitize_text_field( (string) $input['rule_id'] );
		}
		if ( isset( $input['popup_id'] ) ) {
			$base['popup_id'] = absint( $input['popup_id'] );
		}
		if ( isset( $input['variant_page_id'] ) ) {
			$base['variant_page_id'] = absint( $input['variant_page_id'] );
		}
		if ( isset( $input['context'] ) && is_array( $input['context'] ) ) {
			$base['context'] = $input['context'];
		}

		if ( class_exists( 'RWGA_Context_Builder', false ) ) {
			$bundle = RWGA_Context_Builder::for_remote_api(
				RWGA_Context_Builder::build(
					$this->get_key(),
					array_merge(
						$base,
						array(
							'rule_id'         => isset( $input['rule_id'] ) ? $input['rule_id'] : '',
							'popup_id'        => isset( $input['popup_id'] ) ? $input['popup_id'] : 0,
							'variant_page_id' => isset( $input['variant_page_id'] ) ? $input['variant_page_id'] : 0,
							'context'         => isset( $input['context'] ) && is_array( $input['context'] ) ? $input['context'] : array(),
						)
					)
				)
			);
			$base = array_merge( $base, $bundle );
		} else {
			$base['site_intelligence'] = $this->build_site_intelligence_slice();
		}

		/**
		 * @param array<string, mixed> $base         Request payload.
		 * @param string               $workflow_key Workflow key.
		 * @param array<string, mixed> $input        Raw input.
		 */
		return apply_filters( 'rwga_intelligence_workflow_payload', $base, $this->get_key(), $input );
	}

	/**
	 * Compact snapshot for remote workflows (not full page content).
	 *
	 * @return array<string, mixed>
	 */
	private function build_site_intelligence_slice() {
		if ( ! function_exists( 'rwgc_build_ai_snapshot' ) ) {
			return array();
		}
		$snapshot = rwgc_build_ai_snapshot();
		if ( ! is_array( $snapshot ) ) {
			return array();
		}
		return array(
			'schema_version'  => isset( $snapshot['schema_version'] ) ? (int) $snapshot['schema_version'] : 1,
			'snapshot_hash'   => isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '',
			'generated_at_gmt' => isset( $snapshot['generated_at_gmt'] ) ? (string) $snapshot['generated_at_gmt'] : '',
			'site'            => isset( $snapshot['site'] ) && is_array( $snapshot['site'] ) ? $snapshot['site'] : array(),
			'rules'           => isset( $snapshot['rules'] ) && is_array( $snapshot['rules'] ) ? $snapshot['rules'] : array(),
			'variants'        => isset( $snapshot['variants'] ) && is_array( $snapshot['variants'] ) ? $snapshot['variants'] : array(),
			'popups'          => isset( $snapshot['popups'] ) && is_array( $snapshot['popups'] ) ? $snapshot['popups'] : array(),
			'relationships'   => isset( $snapshot['relationships'] ) && is_array( $snapshot['relationships'] ) ? $snapshot['relationships'] : array(),
			'tracking_events' => isset( $snapshot['tracking_events'] ) && is_array( $snapshot['tracking_events'] ) ? $snapshot['tracking_events'] : array(),
			'conversion_events' => isset( $snapshot['conversion_events'] ) && is_array( $snapshot['conversion_events'] ) ? $snapshot['conversion_events'] : array(),
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

		$payload = $this->build_request_payload( $input );
		$mode    = RWGA_Engine::get_mode();

		if ( ! RWGA_Engine::should_try_remote() ) {
			return new WP_Error(
				'rwga_remote_required',
				__( 'Intelligence workflows require remote engine mode in Geo AI Advanced settings.', 'reactwoo-geo-ai' )
			);
		}

		$remote = RWGA_Remote_Client::dispatch( $this->get_key(), $payload );
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}

		if ( empty( $remote['engine_response'] ) || ! is_array( $remote['engine_response'] ) ) {
			return new WP_Error( 'rwga_remote_shape', __( 'Remote intelligence response was empty.', 'reactwoo-geo-ai' ) );
		}

		$norm = $this->normalise_response( $remote['engine_response'] );
		return $this->persist( $payload, $norm, $remote );
	}

	/**
	 * @param array<string, mixed> $response Raw engine response.
	 * @return array<string, mixed>
	 */
	public function normalise_response( array $response ) {
		if ( ! class_exists( 'RWGA_Intelligence_Response', false ) ) {
			return $response;
		}
		return RWGA_Intelligence_Response::normalise( $response, $this->get_key() );
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
		}

		$recommendation_ids = array();
		$action_ids         = array();

		if ( class_exists( 'RWGA_DB_Recommendations', false ) ) {
			$recs = isset( $result['recommendations'] ) && is_array( $result['recommendations'] ) ? $result['recommendations'] : array();
			foreach ( $recs as $rec ) {
				if ( ! is_array( $rec ) ) {
					continue;
				}
				$rid = RWGA_DB_Recommendations::insert(
					array(
						'workflow_key'     => $this->get_key(),
						'agent_key'        => $this->get_agent_key(),
						'page_id'          => $page_id,
						'geo_target'       => $geo,
						'priority_level'   => isset( $rec['priority'] ) ? sanitize_key( (string) $rec['priority'] ) : 'medium',
						'category'         => 'intelligence',
						'title'            => isset( $rec['title'] ) ? (string) $rec['title'] : '',
						'problem'          => isset( $result['summary'] ) ? (string) $result['summary'] : '',
						'why_it_matters'   => isset( $rec['detail'] ) ? (string) $rec['detail'] : '',
						'recommendation'   => isset( $rec['detail'] ) ? (string) $rec['detail'] : '',
						'lifecycle_status' => 'intelligence_generated',
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
						'entity_type'       => isset( $action['entity_type'] ) ? (string) $action['entity_type'] : '',
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

		if ( class_exists( 'RWGA_Memory_Service', false ) ) {
			RWGA_Memory_Service::append(
				'intelligence_workflow_completed',
				'workflow',
				0,
				$page_id,
				$geo,
				array(
					'workflow_key'        => $this->get_key(),
					'summary'             => isset( $result['summary'] ) ? (string) $result['summary'] : '',
					'findings'            => isset( $result['findings'] ) ? count( (array) $result['findings'] ) : 0,
					'recommendation_ids'  => $recommendation_ids,
					'action_ids'          => $action_ids,
				)
			);
		}

		$usage           = isset( $result['usage'] ) && is_array( $result['usage'] ) ? $result['usage'] : array();
		$remote_run_id   = is_array( $remote ) && isset( $remote['remote_run_id'] ) ? (string) $remote['remote_run_id'] : '';
		$telemetry       = class_exists( 'RWGA_Remote_Client', false ) ? RWGA_Remote_Client::telemetry_meta( $usage ) : array();

		do_action(
			'rwga_workflow_persisted',
			$this->get_key(),
			$input,
			$result,
			array_merge(
				array(
					'remote_run_id' => $remote_run_id,
					'snapshot_hash' => $snapshot_hash,
					'total_tokens'  => isset( $usage['charged_units'] ) ? (int) $usage['charged_units'] : ( isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : 0 ),
				),
				$telemetry
			)
		);

		return array(
			'success'            => true,
			'result'             => $result,
			'recommendation_ids' => $recommendation_ids,
			'action_ids'         => $action_ids,
		);
	}
}
