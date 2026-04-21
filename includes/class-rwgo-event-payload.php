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
		$payload = self::attach_profile_context( $payload );
		/**
		 * Filters a goal event payload before persistence or forwarding.
		 *
		 * @param array<string, mixed> $payload Canonical payload.
		 */
		return apply_filters( 'rwgo_goal_event_payload', $payload, $parts );
	}

	/**
	 * @param array<string, mixed> $payload Event payload.
	 * @return array<string, mixed>
	 */
	private static function attach_profile_context( array $payload ) {
		if ( ! function_exists( 'rwgc_get_context_snapshot' ) ) {
			return $payload;
		}
		$context = rwgc_get_context_snapshot();
		if ( ! is_array( $context ) ) {
			return $payload;
		}

		$attribution = isset( $context['attribution'] ) && is_array( $context['attribution'] )
			? $context['attribution']
			: array();

		$payload['profile_id'] = '';
		if ( isset( $context['matched_profile'] ) ) {
			$matched = $context['matched_profile'];
			if ( is_array( $matched ) && ! empty( $matched['profile_id'] ) ) {
				$payload['profile_id'] = (string) $matched['profile_id'];
			} elseif ( is_string( $matched ) ) {
				$payload['profile_id'] = $matched;
			}
		}

		$payload['source']   = isset( $attribution['source'] ) ? (string) $attribution['source'] : '';
		$payload['medium']   = isset( $attribution['medium'] ) ? (string) $attribution['medium'] : '';
		$payload['campaign'] = isset( $attribution['campaign'] ) ? (string) $attribution['campaign'] : '';
		$payload['country']  = isset( $context['country'] ) ? (string) $context['country'] : '';

		return $payload;
	}
}
