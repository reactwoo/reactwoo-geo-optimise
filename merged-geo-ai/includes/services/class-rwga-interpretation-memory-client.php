<?php
/**
 * ReactWoo API client for shared interpretation memory lookup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Interpretation_Memory_Client {

	const MEMORY_MATCH_PATH = '/api/v1/intelligence/geocore/memory-match';

	/**
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>|null
	 */
	public static function match( array $payload ) {
		if ( ! RWGA_Interpretation_Memory_Store::is_shared_enabled() ) {
			return null;
		}
		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return null;
		}

		$res = RWGA_Platform_Client::request( 'POST', self::MEMORY_MATCH_PATH, $payload, true );
		if ( is_wp_error( $res ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return is_array( $body ) ? $body : null;
	}

	/**
	 * @return array{site_hash:string,license_hash:string}
	 */
	public static function hashes() {
		$site_hash = hash( 'sha256', (string) home_url( '/' ) );
		$license   = class_exists( 'RWGA_Platform_Client', false ) ? RWGA_Platform_Client::get_license_key() : '';
		$license_hash = '' !== $license ? hash( 'sha256', $license ) : hash( 'sha256', 'no-license' );
		return array(
			'site_hash'    => $site_hash,
			'license_hash' => $license_hash,
		);
	}
}
