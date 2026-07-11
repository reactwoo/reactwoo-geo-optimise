<?php
/**
 * Pre-flight checks before Geo AI cloud jobs and site intelligence sync.
 *
 * User-facing copy refers to AI usage/actions, not raw tokens.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License, entitlement, and payload guards for cloud intelligence operations.
 */
class RWGA_AI_Usage_Guard {

	/** Soft cap for snapshot JSON body (bytes) before upload. */
	const MAX_SNAPSHOT_BYTES = 524288;

	/**
	 * Whether site intelligence sync may run.
	 *
	 * @return array{allowed:bool,reason:string}
	 */
	public static function can_sync_snapshot() {
		$license = self::check_license();
		if ( is_wp_error( $license ) ) {
			return array(
				'allowed' => false,
				'reason'  => $license->get_error_message(),
			);
		}

		$core = self::check_geocore_snapshot_api();
		if ( is_wp_error( $core ) ) {
			return array(
				'allowed' => false,
				'reason'  => $core->get_error_message(),
			);
		}

		$usage = self::check_usage_headroom();
		if ( is_wp_error( $usage ) ) {
			return array(
				'allowed' => false,
				'reason'  => $usage->get_error_message(),
			);
		}

		/**
		 * Final gate for site intelligence sync (entitlements, maintenance flags).
		 *
		 * @param bool   $allowed Default true when prior checks passed.
		 * @param string $reason  Empty when allowed.
		 */
		$allowed = (bool) apply_filters( 'rwga_ai_can_sync_site_intelligence', true, '' );
		$reason  = '';
		if ( ! $allowed ) {
			$reason = __( 'Site intelligence sync is not enabled for this plan.', 'reactwoo-geo-ai' );
		}

		return array(
			'allowed' => $allowed,
			'reason'  => $reason,
		);
	}

	/**
	 * Intelligence workflow keys that consume remote AI.
	 *
	 * @return string[]
	 */
	public static function intelligence_workflow_keys() {
		if ( ! class_exists( 'RWGA_Workflow_Intelligence_Definitions', false ) ) {
			return array();
		}
		return array_keys( RWGA_Workflow_Intelligence_Definitions::get_definitions() );
	}

	/**
	 * Whether a remote intelligence workflow may run.
	 *
	 * @param string $workflow_key Workflow key.
	 * @return array{allowed:bool,reason:string}
	 */
	public static function can_run_workflow( $workflow_key ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		if ( '' === $workflow_key ) {
			return array(
				'allowed' => false,
				'reason'  => __( 'Invalid workflow key.', 'reactwoo-geo-ai' ),
			);
		}

		$sync = self::can_sync_snapshot();
		if ( empty( $sync['allowed'] ) ) {
			return $sync;
		}

		$keys = self::intelligence_workflow_keys();
		if ( ! empty( $keys ) && ! in_array( $workflow_key, $keys, true ) ) {
			return array(
				'allowed' => true,
				'reason'  => '',
			);
		}

		/**
		 * Gate intelligence workflow execution (plan entitlements).
		 *
		 * @param bool   $allowed      Default true when licence and usage checks passed.
		 * @param string $workflow_key Workflow key.
		 */
		$allowed = (bool) apply_filters( 'rwga_ai_can_run_intelligence_workflow', true, $workflow_key );
		if ( ! $allowed ) {
			return array(
				'allowed' => false,
				'reason'  => __( 'This intelligence workflow is not included in your plan.', 'reactwoo-geo-ai' ),
			);
		}

		return array(
			'allowed' => true,
			'reason'  => '',
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function check_license() {
		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::is_configured() ) {
			return new WP_Error(
				'rwga_no_license',
				__( 'Connect a Geo AI license before syncing site intelligence.', 'reactwoo-geo-ai' )
			);
		}
		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return new WP_Error(
				'rwga_no_platform',
				__( 'Geo AI platform client is not available.', 'reactwoo-geo-ai' )
			);
		}
		$token = RWGA_Platform_Client::get_access_token();
		if ( is_wp_error( $token ) ) {
			return new WP_Error(
				'rwga_auth_failed',
				__( 'Could not authenticate with the ReactWoo API. Check your license key.', 'reactwoo-geo-ai' )
			);
		}
		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function check_geocore_snapshot_api() {
		if ( ! function_exists( 'rwgc_build_ai_snapshot' ) ) {
			return new WP_Error(
				'rwga_no_snapshot_builder',
				__( 'Geo Core site intelligence snapshot is not available. Update Geo Core.', 'reactwoo-geo-ai' )
			);
		}
		return true;
	}

	/**
	 * Block sync when assistant quota is fully exhausted (workflows still validated separately).
	 *
	 * @return true|\WP_Error
	 */
	public static function check_usage_headroom() {
		if ( ! class_exists( 'RWGA_Usage', false ) ) {
			return true;
		}
		$cache = RWGA_Usage::get_cache();
		if ( ! is_array( $cache ) ) {
			return true;
		}
		if ( ! empty( $cache['over_limit'] ) ) {
			return new WP_Error(
				'rwga_over_limit',
				__( 'AI usage limit reached for this billing period. Sync will resume when quota resets or your plan is upgraded.', 'reactwoo-geo-ai' )
			);
		}
		$remaining = isset( $cache['remaining'] ) ? (int) $cache['remaining'] : null;
		if ( null !== $remaining && $remaining <= 0 && ! empty( $cache['limit'] ) ) {
			return new WP_Error(
				'rwga_no_headroom',
				__( 'No AI usage remaining this period.', 'reactwoo-geo-ai' )
			);
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $payload Snapshot payload.
	 * @return true|\WP_Error
	 */
	public static function check_payload_size( array $payload ) {
		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return new WP_Error( 'rwga_bad_payload', __( 'Snapshot could not be encoded.', 'reactwoo-geo-ai' ) );
		}
		$bytes = strlen( $json );
		$max   = (int) apply_filters( 'rwga_ai_snapshot_max_bytes', self::MAX_SNAPSHOT_BYTES );
		if ( $bytes > $max ) {
			return new WP_Error(
				'rwga_payload_too_large',
				sprintf(
					/* translators: 1: payload size KB, 2: max KB */
					__( 'Site intelligence snapshot is too large (%1$s KB; max %2$s KB).', 'reactwoo-geo-ai' ),
					(string) round( $bytes / 1024 ),
					(string) round( $max / 1024 )
				)
			);
		}
		return true;
	}
}
