<?php
/**
 * Optional auto-promotion when winner policy is ready.
 *
 * Disabled by default — enable with filter `rwgo_auto_promote_when_ready`.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Promotion automation hooks.
 */
class RWGO_Promotion_Automation {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwgo_winner_policy_evaluated', array( __CLASS__, 'on_policy_evaluated' ), 10, 3 );
	}

	/**
	 * @param int                  $experiment_post_id Experiment CPT id.
	 * @param array<string, mixed> $analysis           Analysis with winner_policy.
	 * @param array<string, mixed> $cfg                Config.
	 * @return void
	 */
	public static function on_policy_evaluated( $experiment_post_id, array $analysis, array $cfg ) {
		$experiment_post_id = (int) $experiment_post_id;
		if ( $experiment_post_id <= 0 ) {
			return;
		}
		$status = isset( $cfg['status'] ) ? sanitize_key( (string) $cfg['status'] ) : '';
		if ( ! in_array( $status, array( 'active', 'running' ), true ) ) {
			return;
		}
		$policy = isset( $analysis['winner_policy'] ) && is_array( $analysis['winner_policy'] ) ? $analysis['winner_policy'] : array();
		if ( empty( $policy['ready_to_promote'] ) ) {
			return;
		}

		/**
		 * Return true to auto-promote Variant B when winner policy says ready.
		 *
		 * @param bool                 $enable Default false.
		 * @param int                  $experiment_post_id Experiment id.
		 * @param array<string, mixed> $analysis Analysis.
		 * @param array<string, mixed> $cfg Config.
		 */
		$enable = (bool) apply_filters( 'rwgo_auto_promote_when_ready', false, $experiment_post_id, $analysis, $cfg );
		if ( ! $enable ) {
			return;
		}
		if ( ! class_exists( 'RWGO_Promotion_Service', false ) ) {
			return;
		}

		$lock_key = 'rwgo_auto_promote_' . $experiment_post_id;
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 15 * MINUTE_IN_SECONDS );

		$result = RWGO_Promotion_Service::run(
			$experiment_post_id,
			array(
				'mode'            => RWGO_Promotion_Service::MODE_REPLACE_CONTENT,
				'variant_action'  => RWGO_Promotion_Service::VARIANT_ARCHIVE_REDIRECT,
				'copy_post_title' => true,
			)
		);

		/**
		 * After an auto-promote attempt.
		 *
		 * @param array<string, mixed>|\WP_Error $result Result.
		 * @param int                            $experiment_post_id Experiment id.
		 */
		do_action( 'rwgo_auto_promote_completed', $result, $experiment_post_id );
	}
}
