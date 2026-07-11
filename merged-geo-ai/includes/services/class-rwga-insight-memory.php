<?php
/**
 * Insight memory — skip premium AI when an equivalent run is cached locally.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option-backed cache of remote workflow engine responses.
 */
class RWGA_Insight_Memory {

	const OPTION_KEY = 'rwga_insight_memory_v1';

	const MAX_ENTRIES = 64;

	const TTL = 86400;

	/**
	 * @var array<string, mixed>|null
	 */
	private static $last_lookup = null;

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $payload      Workflow payload.
	 * @param array<string, mixed> $route        Model route metadata.
	 * @return array<string, mixed>|null Cached parsed response or null.
	 */
	public static function lookup( $workflow_key, array $payload, array $route = array() ) {
		$key    = self::input_key( $workflow_key, $payload, $route );
		$store  = self::get_store();
		$entry  = isset( $store[ $key ] ) && is_array( $store[ $key ] ) ? $store[ $key ] : null;
		self::$last_lookup = array(
			'input_key' => $key,
			'hit'       => false,
		);

		if ( null === $entry ) {
			return null;
		}

		$stored_at = isset( $entry['stored_at'] ) ? (int) $entry['stored_at'] : 0;
		if ( $stored_at <= 0 || ( time() - $stored_at ) > self::TTL ) {
			unset( $store[ $key ] );
			update_option( self::OPTION_KEY, $store, false );
			return null;
		}

		if ( empty( $entry['engine_response'] ) || ! is_array( $entry['engine_response'] ) ) {
			return null;
		}

		self::$last_lookup['hit'] = true;

		return array(
			'remote_run_id'   => isset( $entry['remote_run_id'] ) ? (string) $entry['remote_run_id'] : '',
			'engine_response' => $entry['engine_response'],
			'cache_hit'       => true,
			'model_route'     => isset( $entry['model_route'] ) && is_array( $entry['model_route'] ) ? $entry['model_route'] : $route,
		);
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $payload      Workflow payload.
	 * @param array<string, mixed> $route        Model route metadata.
	 * @param array<string, mixed> $parsed       Parsed remote response.
	 * @return void
	 */
	public static function store( $workflow_key, array $payload, array $route, array $parsed ) {
		if ( empty( $parsed['engine_response'] ) || ! is_array( $parsed['engine_response'] ) ) {
			return;
		}

		$key   = self::input_key( $workflow_key, $payload, $route );
		$store = self::get_store();

		$store[ $key ] = array(
			'workflow_key'      => sanitize_key( (string) $workflow_key ),
			'stored_at'         => time(),
			'remote_run_id'     => isset( $parsed['remote_run_id'] ) ? sanitize_text_field( (string) $parsed['remote_run_id'] ) : '',
			'engine_response'   => $parsed['engine_response'],
			'model_route'       => $route,
			'page_id'           => isset( $payload['page_id'] ) ? (int) $payload['page_id'] : 0,
		);

		if ( count( $store ) > self::MAX_ENTRIES ) {
			uasort(
				$store,
				static function ( $a, $b ) {
					$ta = isset( $a['stored_at'] ) ? (int) $a['stored_at'] : 0;
					$tb = isset( $b['stored_at'] ) ? (int) $b['stored_at'] : 0;
					return $ta <=> $tb;
				}
			);
			$store = array_slice( $store, -1 * self::MAX_ENTRIES, null, true );
		}

		update_option( self::OPTION_KEY, $store, false );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function last_lookup() {
		return self::$last_lookup;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_store() {
		$raw = get_option( self::OPTION_KEY, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $payload      Workflow payload.
	 * @param array<string, mixed> $route        Model route metadata.
	 * @return string
	 */
	public static function input_key( $workflow_key, array $payload, array $route = array() ) {
		$snapshot_hash = class_exists( 'RWGA_Local_Intelligence', false )
			? RWGA_Local_Intelligence::current_snapshot_hash()
			: '';
		$entity_hash   = self::entity_hash_from_payload( $payload );
		$prompt_version = isset( $route['prompt_version'] ) ? (string) $route['prompt_version'] : (
			class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::PROMPT_VERSION : '1.0.0'
		);
		$model_version = isset( $route['model_hint'] ) ? (string) $route['model_hint'] : '';

		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return substr( hash( 'sha256', wp_json_encode( array( $workflow_key, $payload ) ) ), 0, 64 );
		}

		return RWGA_Local_Intelligence::build_run_cache_key(
			$workflow_key,
			$snapshot_hash,
			$entity_hash,
			$prompt_version,
			$model_version,
			array()
		);
	}

	/**
	 * @param array<string, mixed> $payload Workflow payload.
	 * @return string
	 */
	public static function entity_hash_from_payload( array $payload ) {
		$page_id = isset( $payload['page_id'] ) ? (int) $payload['page_id'] : 0;
		if ( $page_id > 0 && class_exists( 'RWGA_Local_Intelligence', false ) ) {
			$row = RWGA_Local_Intelligence::get_page_context( $page_id );
			if ( is_array( $row ) && ! empty( $row['entity_hash'] ) ) {
				return sanitize_text_field( (string) $row['entity_hash'] );
			}
		}

		$fingerprint = class_exists( 'RWGA_Payload_Guard', false )
			? RWGA_Payload_Guard::sanitize( $payload )
			: $payload;
		$json = wp_json_encode( $fingerprint );
		return substr( hash( 'sha256', is_string( $json ) ? $json : '' ), 0, 32 );
	}
}
