<?php
/**
 * Runs automation rules: executes workflows where supported, then updates timestamps.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatches the rule’s workflow (cron or manual) using a licensed user context.
 */
class RWGA_Automation_Runner {

	/**
	 * Execute one rule: optional workflow run, then last/next timestamps.
	 *
	 * @param int $rule_id Rule ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function run( $rule_id ) {
		$rule_id = (int) $rule_id;
		if ( $rule_id <= 0 ) {
			return new WP_Error( 'rwga_bad_id', __( 'Invalid rule id.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$rule = RWGA_DB_Automation_Rules::get( $rule_id );
		if ( ! is_array( $rule ) ) {
			return new WP_Error( 'rwga_not_found', __( 'Automation rule not found.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		$status = isset( $rule['status'] ) ? sanitize_key( (string) $rule['status'] ) : '';
		if ( 'active' !== $status ) {
			return new WP_Error( 'rwga_rule_inactive', __( 'Rule is not active.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		$workflow_result = null;
		$dispatch        = array(
			'attempted' => false,
			'success'   => false,
			'code'      => '',
			'message'   => '',
		);

		if ( class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) {
			$uid = self::get_acting_user_id( $rule );
			if ( $uid > 0 ) {
				$wf_key = isset( $rule['workflow_key'] ) ? sanitize_key( (string) $rule['workflow_key'] ) : '';
				$wf     = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( $wf_key ) : null;
				if ( $wf ) {
					$input = self::build_workflow_input( $rule );
					if ( ! is_wp_error( $input ) ) {
						$dispatch['attempted'] = true;
						$prev_user             = get_current_user_id();
						wp_set_current_user( $uid );
						try {
							$workflow_result = $wf->execute( $input );
						} catch ( \Throwable $e ) {
							$workflow_result = new WP_Error(
								'rwga_workflow_exception',
								$e->getMessage(),
								array( 'status' => 500 )
							);
						}
						wp_set_current_user( $prev_user );

						if ( is_wp_error( $workflow_result ) ) {
							$dispatch['code']    = $workflow_result->get_error_code();
							$dispatch['message'] = $workflow_result->get_error_message();
						} else {
							$dispatch['success'] = true;
							if ( class_exists( 'RWGA_Site_Intelligence_Journey', false )
								&& in_array( $wf_key, RWGA_Site_Intelligence_Journey::get_site_wide_audit_keys(), true )
								&& is_array( $workflow_result ) ) {
								$action_count = isset( $workflow_result['action_ids'] ) && is_array( $workflow_result['action_ids'] )
									? count( $workflow_result['action_ids'] )
									: 0;
								RWGA_Site_Intelligence_Journey::record_audit_run( $wf_key, $action_count );
							}
						}
					} else {
						$dispatch['code']    = $input->get_error_code();
						$dispatch['message'] = $input->get_error_message();
					}
				} else {
					$dispatch['code']    = 'rwga_no_workflow';
					$dispatch['message'] = __( 'Unknown workflow key.', 'reactwoo-geo-ai' );
				}
			} else {
				$dispatch['code']    = 'rwga_no_actor';
				$dispatch['message'] = __( 'No user with permission to run AI workflows was found for this rule.', 'reactwoo-geo-ai' );
			}
		} else {
			$dispatch['code']    = 'rwga_unlicensed';
			$dispatch['message'] = __( 'License key not configured.', 'reactwoo-geo-ai' );
		}

		$last = current_time( 'mysql', true );
		$next = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );

		$ok = RWGA_DB_Automation_Rules::touch_run( $rule_id, $last, $next );
		if ( ! $ok ) {
			return new WP_Error( 'rwga_persist', __( 'Could not update run timestamps.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$page_id = isset( $rule['page_id'] ) ? (int) $rule['page_id'] : 0;
		$geo     = isset( $rule['geo_target'] ) && $rule['geo_target'] ? (string) $rule['geo_target'] : '';

		RWGA_Memory_Service::append(
			'automation_ran',
			'automation_rule',
			$rule_id,
			$page_id,
			$geo,
			array(
				'workflow_key'       => isset( $rule['workflow_key'] ) ? (string) $rule['workflow_key'] : '',
				'trigger_type'       => isset( $rule['trigger_type'] ) ? (string) $rule['trigger_type'] : '',
				'workflow_dispatch'  => $dispatch,
				'workflow_success'   => $dispatch['success'],
			)
		);

		return array(
			'success'          => true,
			'rule_id'          => $rule_id,
			'last_run_at'      => $last,
			'next_run_at'      => $next,
			'workflow_result'  => $workflow_result,
			'dispatch'         => $dispatch,
		);
	}

	/**
	 * User to impersonate: rule author if they can run AI, else first administrator with rwga_run_ai.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @return int User ID or 0.
	 */
	private static function get_acting_user_id( array $rule ) {
		$preferred = isset( $rule['created_by'] ) ? (int) $rule['created_by'] : 0;
		if ( $preferred > 0 && user_can( $preferred, RWGA_Capabilities::CAP_RUN_AI ) ) {
			return $preferred;
		}

		foreach ( array( 'administrator', 'shop_manager', 'editor' ) as $role_name ) {
			$users = get_users(
				array(
					'role'   => $role_name,
					'number' => 25,
					'fields' => array( 'ID' ),
				)
			);
			if ( ! is_array( $users ) ) {
				continue;
			}
			foreach ( $users as $u ) {
				$id = isset( $u->ID ) ? (int) $u->ID : 0;
				if ( $id > 0 && user_can( $id, RWGA_Capabilities::CAP_RUN_AI ) ) {
					return $id;
				}
			}
		}

		return 0;
	}

	/**
	 * Decode rule_config JSON.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @return array<string, mixed>
	 */
	private static function parse_rule_config( array $rule ) {
		$raw = isset( $rule['rule_config'] ) ? $rule['rule_config'] : null;
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			$j = json_decode( $raw, true );
			return is_array( $j ) ? $j : array();
		}
		return array();
	}

	/**
	 * Build workflow execute input from rule + rule_config.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function build_workflow_input( array $rule ) {
		$wk      = isset( $rule['workflow_key'] ) ? sanitize_key( (string) $rule['workflow_key'] ) : '';
		$cfg     = self::parse_rule_config( $rule );
		$page_id = isset( $rule['page_id'] ) ? (int) $rule['page_id'] : 0;
		$geo     = isset( $rule['geo_target'] ) && $rule['geo_target'] ? strtoupper( substr( sanitize_text_field( (string) $rule['geo_target'] ), 0, 2 ) ) : '';

		$base = array(
			'geo_target' => '' !== $geo ? $geo : '',
		);

		if ( 'ux_analysis' === $wk ) {
			$page_url = isset( $cfg['page_url'] ) ? esc_url_raw( (string) $cfg['page_url'] ) : '';
			if ( $page_id <= 0 && ( '' === $page_url || ! wp_http_validate_url( $page_url ) ) ) {
				return new WP_Error(
					'rwga_auto_input',
					__( 'Set a page ID or a valid automation page URL for UX analysis.', 'reactwoo-geo-ai' )
				);
			}
			$focus = isset( $cfg['analysis_focus'] ) ? sanitize_key( (string) $cfg['analysis_focus'] ) : 'inherit';
			if ( 'inherit' === $focus || '' === $focus ) {
				$defs  = class_exists( 'RWGA_Settings', false ) ? RWGA_Settings::get_settings() : array();
				$focus = isset( $defs['ux_analysis_focus'] ) ? sanitize_key( (string) $defs['ux_analysis_focus'] ) : 'messaging';
			}
			if ( ! in_array( $focus, array( 'messaging', 'layout', 'both' ), true ) ) {
				$focus = 'messaging';
			}
			return array_merge(
				$base,
				array(
					'page_id'        => $page_id > 0 ? $page_id : 0,
					'page_url'       => $page_url,
					'page_type'      => 'page',
					'device_type'    => 'desktop',
					'analysis_focus' => $focus,
				)
			);
		}

		if ( 'competitor_research' === $wk ) {
			$curl = isset( $cfg['competitor_url'] ) ? esc_url_raw( (string) $cfg['competitor_url'] ) : '';
			if ( '' === $curl || ! wp_http_validate_url( $curl ) ) {
				return new WP_Error(
					'rwga_auto_input',
					__( 'Set a valid competitor URL in the rule for competitor research automation.', 'reactwoo-geo-ai' )
				);
			}
			return array_merge(
				$base,
				array(
					'page_id'        => $page_id,
					'competitor_url' => $curl,
					'page_type'      => 'page',
				)
			);
		}

		if ( class_exists( 'RWGA_Site_Intelligence_Journey', false )
			&& in_array( $wk, RWGA_Site_Intelligence_Journey::get_site_wide_audit_keys(), true ) ) {
			return array();
		}

		/**
		 * Return custom workflow input for automation, or null to use default unsupported error.
		 *
		 * @param array<string, mixed>|null $input Built input or null.
		 * @param array<string, mixed>     $rule  Rule row.
		 * @param array<string, mixed>     $cfg   Decoded rule_config.
		 */
		$custom = apply_filters( 'rwga_automation_build_workflow_input', null, $rule, $cfg );
		if ( is_wp_error( $custom ) ) {
			return $custom;
		}
		if ( is_array( $custom ) ) {
			return $custom;
		}

		return new WP_Error(
			'rwga_auto_unsupported',
			__( 'This workflow is not configured for scheduled automation (extend via rwga_automation_build_workflow_input).', 'reactwoo-geo-ai' )
		);
	}
}
