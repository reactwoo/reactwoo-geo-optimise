<?php
/**
 * Geo AI → Geo Optimise merge bridge (capabilities, API paths, licence slug).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires merged Optimise product into Core capability map and ReactWoo API clients.
 */
class RWGO_Merge_Bridge {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_suite_capability_map', array( __CLASS__, 'filter_suite_capability_map' ), 20 );
		add_filter( 'rwga_remote_workflow_path', array( __CLASS__, 'filter_geo_api_path' ), 10, 3 );
		add_filter( 'rwga_site_snapshot_register_path', array( __CLASS__, 'filter_geo_api_path_simple' ) );
		add_filter( 'rwga_site_snapshot_upload_path', array( __CLASS__, 'filter_geo_api_path' ), 10, 3 );
		add_filter( 'rwga_intelligence_cloud_runs_path', array( __CLASS__, 'filter_geo_api_path' ), 10, 3 );
		add_filter( 'rwga_intelligence_cloud_run_path', array( __CLASS__, 'filter_geo_api_path' ), 10, 3 );
		add_filter( 'rwga_intelligence_cloud_graph_path', array( __CLASS__, 'filter_geo_api_path' ), 10, 3 );
		add_filter( 'rwga_site_snapshot_register_body', array( __CLASS__, 'filter_snapshot_register_body' ) );
	}

	/**
	 * @return bool
	 */
	private static function is_embedded_client() {
		return defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED
			&& class_exists( 'RWGO_AI_Module', false )
			&& RWGO_AI_Module::uses_optimise_hub();
	}

	/**
	 * @param array<string, mixed> $map Capability map.
	 * @return array<string, mixed>
	 */
	public static function filter_suite_capability_map( $map ) {
		if ( ! is_array( $map ) ) {
			$map = array();
		}

		$ai_ready     = class_exists( 'RWGO_AI_Module', false ) && RWGO_AI_Module::is_ready();
		$standalone   = class_exists( 'RWGO_AI_Module', false ) && RWGO_AI_Module::is_standalone_geo_ai_active();
		$ai_licensed  = $ai_ready && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows();
		$opt_licensed = class_exists( 'RWGO_Platform_Client', false ) && RWGO_Platform_Client::is_configured();

		$license_state = 'inactive';
		if ( $ai_licensed || $opt_licensed ) {
			$license_state = 'active';
		}

		$map['legacy_geo_ai_detected'] = $standalone;
		$map['optimise']               = array(
			'active'          => ! empty( $map['geo_optimise_active'] ),
			'version'         => defined( 'RWGO_VERSION' ) ? (string) RWGO_VERSION : '',
			'license'         => $license_state,
			'ai_review'       => $ai_ready,
			'recommendations' => $ai_ready,
			'drafts'          => $ai_ready,
			'experiments'     => ! empty( $map['geo_optimise_active'] ),
			'goals'           => ! empty( $map['geo_optimise_active'] ),
			'reports'         => ! empty( $map['geo_optimise_active'] ) || $ai_ready,
		);

		if ( $ai_ready && ! $standalone ) {
			$map['geo_ai_active']   = true;
			$map['geo_ai_licensed'] = $ai_licensed;
			if ( $ai_licensed && class_exists( 'RWGA_Engine', false ) ) {
				$map['remote_ai_available'] = (bool) RWGA_Engine::should_try_remote();
			}
		}

		if ( ! empty( $map['geo_optimise_active'] ) && class_exists( 'RWGO_Platform_Client', false ) ) {
			$map['geo_optimise_licensed'] = $opt_licensed || $ai_licensed;
		}

		return $map;
	}

	/**
	 * @param string $path API path.
	 * @return string
	 */
	public static function filter_geo_api_path_simple( $path ) {
		return self::to_geo_optimise_api_path( (string) $path );
	}

	/**
	 * @param string               $path         API path.
	 * @param mixed                $context_arg  Optional context (unused).
	 * @param mixed                $context_arg2 Optional context (unused).
	 * @return string
	 */
	public static function filter_geo_api_path( $path, $context_arg = null, $context_arg2 = null ) {
		unset( $context_arg, $context_arg2 );
		return self::to_geo_optimise_api_path( (string) $path );
	}

	/**
	 * @param string $path API path.
	 * @return string
	 */
	private static function to_geo_optimise_api_path( $path ) {
		if ( ! self::is_embedded_client() ) {
			return $path;
		}
		return str_replace( '/geo-ai/', '/geo-optimise/', $path );
	}

	/**
	 * @param array<string, mixed> $body Register JSON body.
	 * @return array<string, mixed>
	 */
	public static function filter_snapshot_register_body( $body ) {
		if ( ! self::is_embedded_client() || ! is_array( $body ) ) {
			return $body;
		}
		$slug = class_exists( 'RWGO_Platform_Client', false )
			? RWGO_Platform_Client::PRODUCT_SLUG
			: 'reactwoo-geo-optimise';
		$body['product_slug'] = $slug;
		return $body;
	}
}
