<?php
/**
 * Sync Geo Core intelligence bundles from ReactWoo API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Intelligence_Sync_Service {

	const OPTION_BUNDLE         = 'geo_ai_intelligence_bundle';
	const OPTION_BUNDLE_VERSION = 'geo_ai_intelligence_bundle_version';
	const OPTION_BUNDLE_HASH    = 'geo_ai_intelligence_bundle_hash';
	const OPTION_LAST_SYNC      = 'geo_ai_intelligence_last_sync';
	const OPTION_LAST_SYNC_ERR  = 'geo_ai_intelligence_last_sync_error';
	const TRANSIENT_BUNDLE      = 'rwga_intel_bundle_runtime';

	const BUNDLE_PATH = '/api/v1/intelligence/geocore/bundle';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_auto_sync' ), 20 );
		add_action( 'rwga_loaded', array( __CLASS__, 'ensure_bundle_on_load' ), 5 );
	}

	/**
	 * @return void
	 */
	public static function ensure_bundle_on_load() {
		self::ensure_bundle();
	}

	/**
	 * @return void
	 */
	public static function maybe_auto_sync() {
		$last = (int) get_option( self::OPTION_LAST_SYNC, 0 );
		if ( $last > 0 && ( time() - $last ) < DAY_IN_SECONDS ) {
			return;
		}
		self::sync( false );
	}

	/**
	 * @param bool $force Force refresh even when hash matches.
	 * @return array{success:bool,message:string,bundle_version?:string,hash?:string,source?:string}
	 */
	public static function sync( $force = true ) {
		$remote = self::fetch_remote_bundle();
		if ( is_wp_error( $remote ) ) {
			$fallback = self::load_fallback_bundle();
			if ( $fallback ) {
				self::store_bundle( $fallback, 'fallback' );
				update_option( self::OPTION_LAST_SYNC_ERR, $remote->get_error_message(), false );
				return array(
					'success'        => true,
					'message'        => __( 'Using bundled fallback intelligence (API unavailable).', 'reactwoo-geo-ai' ),
					'bundle_version' => isset( $fallback['bundle_version'] ) ? (string) $fallback['bundle_version'] : '',
					'hash'           => isset( $fallback['hash'] ) ? (string) $fallback['hash'] : '',
					'source'         => 'fallback',
				);
			}
			update_option( self::OPTION_LAST_SYNC_ERR, $remote->get_error_message(), false );
			return array(
				'success' => false,
				'message' => $remote->get_error_message(),
			);
		}

		$new_hash = isset( $remote['hash'] ) ? (string) $remote['hash'] : '';
		$old_hash = (string) get_option( self::OPTION_BUNDLE_HASH, '' );
		if ( ! $force && $new_hash && $old_hash === $new_hash ) {
			return array(
				'success'        => true,
				'message'        => __( 'Intelligence bundle is already up to date.', 'reactwoo-geo-ai' ),
				'bundle_version' => (string) get_option( self::OPTION_BUNDLE_VERSION, '' ),
				'hash'           => $old_hash,
				'source'         => 'cache',
			);
		}

		if ( ! self::validate_bundle_schema( $remote ) ) {
			update_option( self::OPTION_LAST_SYNC_ERR, 'Invalid bundle schema from API.', false );
			return array(
				'success' => false,
				'message' => __( 'Invalid intelligence bundle received from API.', 'reactwoo-geo-ai' ),
			);
		}

		self::store_bundle( $remote, 'api' );
		delete_option( self::OPTION_LAST_SYNC_ERR );
		update_option( self::OPTION_LAST_SYNC, time(), false );

		/**
		 * Fires after a Geo Core intelligence bundle sync completes.
		 *
		 * @param array<string,mixed> $remote Bundle payload.
		 * @param string            $source api|fallback.
		 */
		do_action( 'rwga_intelligence_bundle_synced', $remote, 'api' );

		return array(
			'success'        => true,
			'message'        => __( 'Intelligence bundle synced successfully.', 'reactwoo-geo-ai' ),
			'bundle_version' => isset( $remote['bundle_version'] ) ? (string) $remote['bundle_version'] : '',
			'hash'           => $new_hash,
			'source'         => 'api',
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_local_bundle() {
		return self::ensure_bundle();
	}

	/**
	 * Load, augment, and persist bundle when missing.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function ensure_bundle() {
		$cached = get_transient( self::TRANSIENT_BUNDLE );
		if ( is_array( $cached ) && ! empty( $cached['phrase_patterns'] ) ) {
			return class_exists( 'RWGA_Intelligence_Bundle_Bootstrap', false )
				? RWGA_Intelligence_Bundle_Bootstrap::augment( $cached )
				: $cached;
		}
		$stored = get_option( self::OPTION_BUNDLE, array() );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			$bundle = class_exists( 'RWGA_Intelligence_Bundle_Bootstrap', false )
				? RWGA_Intelligence_Bundle_Bootstrap::augment( $stored )
				: $stored;
			set_transient( self::TRANSIENT_BUNDLE, $bundle, HOUR_IN_SECONDS );
			return $bundle;
		}
		$fallback = self::load_fallback_bundle();
		if ( $fallback && self::validate_bundle_schema( $fallback ) ) {
			$fallback = class_exists( 'RWGA_Intelligence_Bundle_Bootstrap', false )
				? RWGA_Intelligence_Bundle_Bootstrap::augment( $fallback )
				: $fallback;
			self::store_bundle( $fallback, 'fallback' );
			return $fallback;
		}
		if ( class_exists( 'RWGA_Intelligence_Bundle_Bootstrap', false ) ) {
			$minimal = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
			if ( is_array( $minimal ) && ! empty( $minimal['phrase_patterns'] ) ) {
				self::store_bundle( $minimal, 'bootstrap' );
				return $minimal;
			}
		}
		return null;
	}

	/**
	 * @return array{version:string,hash:string,last_sync:int,last_error:string,source:string}
	 */
	public static function get_status() {
		$bundle = self::get_local_bundle();
		return array(
			'version'    => (string) get_option( self::OPTION_BUNDLE_VERSION, '' ),
			'hash'       => (string) get_option( self::OPTION_BUNDLE_HASH, '' ),
			'last_sync'  => (int) get_option( self::OPTION_LAST_SYNC, 0 ),
			'last_error' => (string) get_option( self::OPTION_LAST_SYNC_ERR, '' ),
			'source'     => is_array( $bundle ) && ! empty( $bundle['_source'] ) ? (string) $bundle['_source'] : 'unknown',
		);
	}

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function fetch_remote_bundle() {
		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return new WP_Error( 'rwga_no_client', __( 'Platform client unavailable.', 'reactwoo-geo-ai' ) );
		}

		$query = array(
			'plugin_version'     => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
			'satellite_version'  => RWGA_VERSION,
			'locale'             => get_locale(),
		);
		$path  = add_query_arg( $query, self::BUNDLE_PATH );
		$res   = RWGA_Platform_Client::request( 'GET', $path, null, true );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			return new WP_Error(
				'rwga_intel_bundle_fetch',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Intelligence bundle request failed (HTTP %d).', 'reactwoo-geo-ai' ),
					$code
				)
			);
		}
		return $body;
	}

	/**
	 * @param array<string,mixed> $bundle Bundle payload.
	 * @param string              $source api|fallback.
	 * @return void
	 */
	private static function store_bundle( array $bundle, $source ) {
		$bundle['_source'] = $source;
		update_option( self::OPTION_BUNDLE, $bundle, false );
		update_option(
			self::OPTION_BUNDLE_VERSION,
			isset( $bundle['bundle_version'] ) ? (string) $bundle['bundle_version'] : '',
			false
		);
		update_option(
			self::OPTION_BUNDLE_HASH,
			isset( $bundle['hash'] ) ? (string) $bundle['hash'] : '',
			false
		);
		delete_transient( self::TRANSIENT_BUNDLE );
		set_transient( self::TRANSIENT_BUNDLE, $bundle, HOUR_IN_SECONDS );
	}

	/**
	 * @param array<string,mixed> $bundle Bundle payload.
	 * @return bool
	 */
	private static function validate_bundle_schema( array $bundle ) {
		$required = array( 'suite', 'bundle_version', 'schema_version', 'actions', 'intents', 'phrase_patterns', 'entities' );
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $bundle ) ) {
				return false;
			}
		}
		return is_array( $bundle['actions'] ) && is_array( $bundle['phrase_patterns'] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function load_fallback_bundle() {
		$paths = array(
			RWGA_PATH . 'data/geocore-intelligence-fallback.json',
			RWGA_PATH . 'assets/intelligence/geocore-default-bundle.json',
		);
		foreach ( $paths as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_string( $raw ) || '' === $raw ) {
				continue;
			}
			$data = json_decode( $raw, true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		return null;
	}
}
