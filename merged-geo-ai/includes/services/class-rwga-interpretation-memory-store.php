<?php
/**
 * Local cache for approved interpretation memory entries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Interpretation_Memory_Store {

	const OPTION_CACHE          = 'geo_ai_interpretation_memory_cache';
	const OPTION_CACHE_VERSION  = 'geo_ai_interpretation_memory_cache_version';
	const OPTION_LAST_SYNC      = 'geo_ai_interpretation_memory_last_sync';
	const OPTION_LOCAL_ENABLED  = 'geo_ai_interpretation_memory_local_enabled';
	const OPTION_SHARED_ENABLED = 'geo_ai_interpretation_memory_shared_enabled';
	const CACHE_VERSION         = '1';
	const MAX_ENTRIES           = 200;

	/**
	 * @return bool
	 */
	public static function is_local_enabled() {
		return (bool) apply_filters(
			'rwga_interpretation_memory_local_enabled',
			(bool) get_option( self::OPTION_LOCAL_ENABLED, true )
		);
	}

	/**
	 * @return bool
	 */
	public static function is_shared_enabled() {
		return (bool) apply_filters(
			'rwga_interpretation_memory_shared_enabled',
			(bool) get_option( self::OPTION_SHARED_ENABLED, true )
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_entries() {
		if ( ! self::is_local_enabled() ) {
			return array();
		}
		$cache = get_option( self::OPTION_CACHE, array() );
		return is_array( $cache['entries'] ?? null ) ? $cache['entries'] : array();
	}

	/**
	 * @param array<string,mixed> $entry Memory row.
	 * @return void
	 */
	public static function upsert( array $entry ) {
		if ( ! self::is_local_enabled() || empty( $entry['phrase_shape'] ) ) {
			return;
		}
		$entries = self::get_entries();
		$id      = (string) ( $entry['memory_id'] ?? $entry['id'] ?? '' );
		$found   = false;
		foreach ( $entries as $idx => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row_id = (string) ( $row['memory_id'] ?? $row['id'] ?? '' );
			if ( $id && $row_id === $id ) {
				$entries[ $idx ] = array_merge( $row, $entry, array( 'updated_at' => gmdate( 'c' ) ) );
				$found           = true;
				break;
			}
			if ( (string) ( $row['phrase_shape'] ?? '' ) === (string) $entry['phrase_shape'] ) {
				$entries[ $idx ] = array_merge( $row, $entry, array( 'updated_at' => gmdate( 'c' ) ) );
				$found           = true;
				break;
			}
		}
		if ( ! $found ) {
			$entries[] = array_merge(
				$entry,
				array(
					'created_at' => gmdate( 'c' ),
					'updated_at' => gmdate( 'c' ),
				)
			);
		}
		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}
		update_option(
			self::OPTION_CACHE,
			array(
				'version' => self::CACHE_VERSION,
				'entries' => $entries,
				'updated' => time(),
			),
			false
		);
		update_option( self::OPTION_CACHE_VERSION, self::CACHE_VERSION, false );
	}

	/**
	 * @param string $normalised Normalised phrase.
	 * @return array<string,mixed>|null
	 */
	public static function find_by_normalised( $normalised ) {
		foreach ( self::get_entries() as $row ) {
			if ( ! is_array( $row ) || ( $row['status'] ?? 'active' ) !== 'active' ) {
				continue;
			}
			if ( (string) ( $row['normalised_phrase'] ?? '' ) === (string) $normalised ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @param string $phrase_shape Phrase shape.
	 * @return array<string,mixed>|null
	 */
	public static function find_by_shape( $phrase_shape ) {
		foreach ( self::get_entries() as $row ) {
			if ( ! is_array( $row ) || ( $row['status'] ?? 'active' ) !== 'active' ) {
				continue;
			}
			if ( (string) ( $row['phrase_shape'] ?? '' ) === (string) $phrase_shape ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $remote Remote memory-match payload.
	 * @return void
	 */
	public static function cache_remote( array $remote ) {
		if ( empty( $remote['matched'] ) || empty( $remote['interpretation'] ) ) {
			return;
		}
		self::upsert(
			array(
				'memory_id'                 => (string) ( $remote['memory_id'] ?? '' ),
				'scope'                     => (string) ( $remote['scope'] ?? 'global' ),
				'confidence'                => (float) ( $remote['confidence'] ?? 0.85 ),
				'phrase_shape'              => (string) ( $remote['phrase_shape'] ?? '' ),
				'normalised_phrase'         => (string) ( $remote['normalised_phrase'] ?? '' ),
				'intent_key'                => (string) ( $remote['interpretation']['intent'] ?? '' ),
				'action_key'                => (string) ( $remote['interpretation']['matched_action'] ?? '' ),
				'params_template'           => $remote['interpretation']['params'] ?? array(),
				'resolved_params_example'   => $remote['interpretation']['params'] ?? array(),
				'status'                    => 'active',
				'source'                    => 'remote_cache',
			)
		);
		update_option( self::OPTION_LAST_SYNC, time(), false );
	}

	/**
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION_CACHE );
		delete_option( self::OPTION_LAST_SYNC );
	}
}
