<?php
/**
 * Experiment exposure: first-class event when a variant experience is rendered.
 *
 * Complements {@see RWGO_Stats::record_variant_served()} option counters with
 * session/day-deduped rows in {@see RWGO_Event_Store}.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records experiment_exposure events.
 */
class RWGO_Exposure {

	/**
	 * Record exposure for a rendered variant (safe to call on every page view).
	 *
	 * @param array<string, mixed> $cfg              Experiment config.
	 * @param string               $variant_slug     Resolved variant.
	 * @param int                  $page_context_id  Control / source page id.
	 * @param int                  $page_variant_id  Post ID being viewed.
	 * @param int                  $experiment_id    Experiment CPT id.
	 * @return array<string, mixed>|null Normalized payload when newly recorded; null if skipped/deduped.
	 */
	public static function record( array $cfg, $variant_slug, $page_context_id, $page_variant_id, $experiment_id = 0 ) {
		$experiment_key = isset( $cfg['experiment_key'] ) ? sanitize_text_field( (string) $cfg['experiment_key'] ) : '';
		$variant_slug   = sanitize_key( (string) $variant_slug );
		if ( '' === $experiment_key || '' === $variant_slug ) {
			return null;
		}

		$session_hash = self::session_hash();
		$instance_id  = self::instance_id( $experiment_key, $variant_slug, $session_hash );

		if ( class_exists( 'RWGO_Event_Store', false ) && RWGO_Event_Store::has_event_instance_id( $instance_id ) ) {
			return null;
		}

		$parts = array(
			'event_instance_id'     => $instance_id,
			'event_type'            => 'experiment_exposure',
			'experiment_id'         => (int) $experiment_id,
			'experiment_key'        => $experiment_key,
			'variant_id'            => $variant_slug,
			'variant_label'         => class_exists( 'RWGO_GTM_Handoff', false )
				? (string) ( RWGO_GTM_Handoff::variant_labels_map( $cfg )[ $variant_slug ] ?? $variant_slug )
				: $variant_slug,
			'page_context_id'       => (int) $page_context_id,
			'page_variant_post_id'  => (int) $page_variant_id,
			'session_hash'          => $session_hash,
			'source'                => 'geo_optimise_exposure',
		);

		$payload = class_exists( 'RWGO_Event_Payload', false )
			? RWGO_Event_Payload::normalize_experiment_exposure( $parts )
			: $parts;

		/**
		 * Before exposure persistence.
		 *
		 * @param array<string, mixed> $payload Exposure payload.
		 * @param array<string, mixed> $cfg     Experiment config.
		 */
		$payload = apply_filters( 'rwgo_experiment_exposure_payload', $payload, $cfg );

		/**
		 * Experiment exposure recorded (or about to persist).
		 *
		 * @param array<string, mixed> $payload Canonical exposure payload.
		 */
		do_action( 'rwgo_experiment_exposure', $payload );

		return is_array( $payload ) ? $payload : null;
	}

	/**
	 * Stable browser session hash from assignment cookie (or daily IP/UA fallback).
	 *
	 * @return string
	 */
	public static function session_hash() {
		$raw = '';
		$cookie_name = class_exists( 'RWGO_Assignment', false ) ? RWGO_Assignment::COOKIE : 'rwgo_ab';
		if ( ! empty( $_COOKIE[ $cookie_name ] ) && is_string( $_COOKIE[ $cookie_name ] ) ) {
			$raw = (string) wp_unslash( $_COOKIE[ $cookie_name ] );
		}
		if ( '' === $raw ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
			$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
			$raw = $ip . '|' . $ua . '|' . gmdate( 'Y-m-d' );
		}
		return substr( hash( 'sha256', $raw ), 0, 32 );
	}

	/**
	 * @param string $experiment_key Key.
	 * @param string $variant_slug   Variant.
	 * @param string $session_hash   Session hash.
	 * @return string
	 */
	public static function instance_id( $experiment_key, $variant_slug, $session_hash ) {
		$digest = hash( 'sha256', $experiment_key . '|' . $variant_slug . '|' . $session_hash . '|' . gmdate( 'Y-m-d' ) );
		return 'exp_' . substr( $digest, 0, 48 );
	}
}
