<?php
/**
 * Canonical goal / measurement payloads.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes event payloads for storage and GTM.
 */
class RWGO_Event_Payload {

	/**
	 * @param array<string, mixed> $parts Partial payload.
	 * @return array<string, mixed>
	 */
	public static function normalize_goal_fired( array $parts ) {
		$defaults = array(
			'event_instance_id'   => 'evt_' . strtolower( wp_generate_password( 12, false, false ) ),
			'event_type'        => 'goal_fired',
			'experiment_id'     => 0,
			'experiment_key'    => '',
			'variant_id'        => '',
			'variant_label'     => '',
			'goal_id'           => '',
			'goal_type'         => '',
			'handler_id'        => '',
			'page_context_id'   => 0,
			'page_variant_post_id' => 0,
			'element_fingerprint' => '',
			'source'            => 'geo_optimise',
			'timestamp_utc'     => gmdate( 'c' ),
		);
		$payload = array_merge( $defaults, $parts );
		/**
		 * Filters a goal event payload before persistence or forwarding.
		 *
		 * @param array<string, mixed> $payload Canonical payload.
		 */
		return apply_filters( 'rwgo_goal_event_payload', $payload, $parts );
	}
}
