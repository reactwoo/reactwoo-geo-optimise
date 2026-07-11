<?php
/**
 * Bridge confirmed assistant proposals into the Geo Core plan executor.
 *
 * Hooks `rwga_assistant_execute_proposal`. When the proposal carries a
 * multi-action plan and Geo Core's rule repository is available, it:
 *   1. applies the user's field-level card resolutions,
 *   2. re-validates by rebuilding the action cards,
 *   3. hard-gates (WP_Error) if anything is still unresolved, and
 *   4. otherwise executes the plan into draft Geo Core rules.
 *
 * If there is no plan or Geo Core is unavailable, it returns null so the
 * existing guided-workflow redirect remains the fallback.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Assistant_Executor_Bridge {

	/**
	 * @return void
	 */
	public static function register() {
		add_filter( 'rwga_assistant_execute_proposal', array( __CLASS__, 'maybe_execute' ), 10, 4 );
	}

	/**
	 * @param mixed                          $result      Existing result (null until handled).
	 * @param array<string,mixed>            $proposal    Stored proposal.
	 * @param string                         $action      Matched action key.
	 * @param array<int,array<string,mixed>> $resolutions Client card resolutions.
	 * @return mixed
	 */
	public static function maybe_execute( $result, $proposal, $action, $resolutions = array() ) {
		unset( $action );

		if ( null !== $result ) {
			return $result;
		}
		if ( ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) || ! class_exists( 'RWGA_Plan_Executor', false ) ) {
			return null;
		}

		$plan    = is_array( $proposal['interpretation_plan'] ?? null ) ? $proposal['interpretation_plan'] : array();
		$actions = is_array( $plan['actions'] ?? null ) ? $plan['actions'] : array();
		if ( empty( $actions ) ) {
			return null;
		}

		if ( ! is_array( $resolutions ) ) {
			$resolutions = array();
		}
		if ( ! empty( $resolutions ) && class_exists( 'RWGA_Card_Resolution_Applier', false ) ) {
			$actions = RWGA_Card_Resolution_Applier::apply( $actions, $resolutions );
		}

		if ( empty( $actions ) ) {
			return new WP_Error(
				'rwga_plan_empty',
				__( 'Every action was removed, so there is nothing to create.', 'reactwoo-geo-ai' ),
				array( 'status' => 400 )
			);
		}

		if ( class_exists( 'RWGA_Planner_Action_Card_Builder', false ) ) {
			$entities = is_array( $plan['entities'] ?? null ) ? $plan['entities'] : array();
			$rebuilt  = RWGA_Planner_Action_Card_Builder::build( $actions, array(), $entities );
			foreach ( $rebuilt['cards'] as $card ) {
				if ( ! is_array( $card ) || RWGA_Planner_Action_Card_Builder::STATUS_READY !== (string) ( $card['status'] ?? '' ) ) {
					return new WP_Error(
						'rwga_plan_unresolved',
						__( 'Some fields still need resolving before this setup can be created.', 'reactwoo-geo-ai' ),
						array(
							'status'                   => 409,
							'action_cards'             => $rebuilt['cards'],
							'fields_needing_attention' => $rebuilt['fields_needing_attention'],
							'requires_resolution'      => true,
						)
					);
				}
			}
			if ( ! empty( $rebuilt['requires_resolution'] ) ) {
				return new WP_Error(
					'rwga_plan_unresolved',
					__( 'Some fields still need resolving before this setup can be created.', 'reactwoo-geo-ai' ),
					array(
						'status'                   => 409,
						'action_cards'             => $rebuilt['cards'],
						'fields_needing_attention' => $rebuilt['fields_needing_attention'],
						'requires_resolution'      => true,
					)
				);
			}
		}

		return RWGA_Plan_Executor::execute_plan(
			$actions,
			array(
				'proposal_id'            => (string) ( $proposal['proposal_id'] ?? '' ),
				'source_phrase'          => (string) ( $proposal['original_message'] ?? '' ),
				'interpretation_source'  => (string) ( $proposal['interpretation_source'] ?? '' ),
			)
		);
	}
}
