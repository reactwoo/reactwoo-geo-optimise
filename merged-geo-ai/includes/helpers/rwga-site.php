<?php
/**
 * Site identity helpers for Geo AI records.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable per-install UUID for workflow records and API payloads.
 *
 * @return string
 */
function rwga_get_site_uuid() {
	$u = get_option( 'rwga_site_uuid', '' );
	if ( is_string( $u ) && '' !== $u ) {
		return $u;
	}
	if ( function_exists( 'wp_generate_uuid4' ) ) {
		$u = wp_generate_uuid4();
	} else {
		$u = md5( (string) home_url() );
	}
	update_option( 'rwga_site_uuid', $u, false );
	return $u;
}

if ( ! function_exists( 'rwga_sync_site_intelligence' ) ) {
	/**
	 * Sync Geo Core site intelligence snapshot to the ReactWoo cloud (Geo AI).
	 *
	 * @param bool $force Upload even when the snapshot hash is unchanged.
	 * @return array<string, mixed>|\WP_Error
	 */
	function rwga_sync_site_intelligence( $force = false ) {
		if ( ! class_exists( 'RWGA_Site_Intelligence_Sync', false ) ) {
			return new WP_Error( 'rwga_sync_unavailable', __( 'Site intelligence sync is not loaded.', 'reactwoo-geo-ai' ) );
		}
		return RWGA_Site_Intelligence_Sync::sync( (bool) $force );
	}
}
