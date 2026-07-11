<?php
/**
 * Records results from {@see RWGC_Satellite_Updater} /api/v5/updates/check for the Settings screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists last satellite updater outcome so admins can see 401 / no-JWT / transport errors (otherwise WP shows nothing).
 */
class RWGA_Updates_Diagnostics {

	const OPTION_LAST = 'rwga_updates_check_last';

	const SLUG = 'reactwoo-geo-ai';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwgc_satellite_updater_no_bearer', array( __CLASS__, 'on_no_bearer' ), 10, 2 );
		add_action( 'rwgc_satellite_updater_check_transport_error', array( __CLASS__, 'on_transport_error' ), 10, 3 );
		add_action( 'rwgc_satellite_updater_check_http', array( __CLASS__, 'on_http' ), 10, 5 );
	}

	/**
	 * Drop a stale “HTTP 0 / rwga_no_license” row when a bearer is available now (e.g. cron ran before the key existed).
	 *
	 * @return void
	 */
	public static function maybe_clear_stale_no_bearer_record() {
		if ( ! class_exists( 'RWGA_Platform_Client', false ) || ! RWGA_Platform_Client::is_configured() ) {
			return;
		}
		$last = self::get_last();
		if ( empty( $last['summary'] ) ) {
			return;
		}
		if ( ! empty( $last['http'] ) && 0 !== (int) $last['http'] ) {
			return;
		}
		$summary = (string) $last['summary'];
		if ( false === strpos( $summary, 'No license JWT' ) && false === strpos( $summary, 'rwga_no_license' ) ) {
			return;
		}
		$cached = RWGA_Platform_Client::get_cached_access_token_string();
		if ( is_string( $cached ) && '' !== $cached ) {
			delete_option( self::OPTION_LAST );
			return;
		}
		$bearer = RWGA_Platform_Client::get_bearer_for_updates();
		if ( null !== $bearer && '' !== $bearer ) {
			delete_option( self::OPTION_LAST );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_last() {
		$raw = get_option( self::OPTION_LAST, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param string               $catalog_slug Catalog slug.
	 * @param array<string, mixed> $cfg          Item config.
	 * @return void
	 */
	public static function on_no_bearer( $catalog_slug, $cfg ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::SLUG !== (string) $catalog_slug ) {
			return;
		}
		$summary = __( 'No license JWT for update checks — save a license key and ensure login succeeds (Plugins → updates use the same token as the API).', 'reactwoo-geo-ai' );
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			$err = get_transient( RWGA_Platform_Client::BEARER_ERROR_TRANSIENT );
			if ( is_array( $err ) && ! empty( $err['message'] ) ) {
				$code = isset( $err['code'] ) ? (string) $err['code'] : '';
				$summary .= ' ' . sprintf(
					/* translators: 1: error code, 2: error message */
					__( '(Last attempt: %1$s — %2$s)', 'reactwoo-geo-ai' ),
					$code ? $code : 'error',
					wp_strip_all_tags( (string) $err['message'] )
				);
			}
		}
		self::save(
			array(
				'ts'       => time(),
				'http'     => 0,
				'summary'  => $summary,
				'body_snip' => '',
			)
		);
	}

	/**
	 * @param string               $catalog_slug Catalog slug.
	 * @param \WP_Error            $err          Transport error.
	 * @param array<string, mixed> $cfg          Item config.
	 * @return void
	 */
	public static function on_transport_error( $catalog_slug, $err, $cfg ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::SLUG !== (string) $catalog_slug ) {
			return;
		}
		$msg = is_wp_error( $err ) ? $err->get_error_message() : '';
		self::save(
			array(
				'ts'        => time(),
				'http'      => 0,
				'summary'   => __( 'HTTP transport error calling the updates API.', 'reactwoo-geo-ai' ) . ( $msg ? ' ' . $msg : '' ),
				'body_snip' => '',
			)
		);
	}

	/**
	 * @param string               $catalog_slug Catalog slug.
	 * @param int                  $http_code    Status code.
	 * @param string               $raw_body     Body.
	 * @param mixed                $data         Decoded JSON.
	 * @param array<string, mixed> $cfg          Item config.
	 * @return void
	 */
	public static function on_http( $catalog_slug, $http_code, $raw_body, $data, $cfg ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::SLUG !== (string) $catalog_slug ) {
			return;
		}
		$http_code = (int) $http_code;
		$raw_body  = is_string( $raw_body ) ? $raw_body : '';
		$snip      = function_exists( 'mb_substr' ) ? mb_substr( $raw_body, 0, 400 ) : substr( $raw_body, 0, 400 );

		if ( 401 === $http_code ) {
			$summary = __( 'Updates API returned 401 — JWT rejected (domain/product mismatch with site_host, expired token, or wrong secret on the API). Compare “Domain match” and “Geo AI product match” above.', 'reactwoo-geo-ai' );
		} elseif ( 200 === $http_code && is_array( $data ) && empty( $data['update'] ) ) {
			$summary = __( 'Updates API returned 200 with no pending update — installed version may already match the catalog, the release is not published yet, or this site is outside the rollout bucket.', 'reactwoo-geo-ai' );
		} elseif ( 200 !== $http_code ) {
			$summary = __( 'Updates API returned a non-200 status.', 'reactwoo-geo-ai' );
		} else {
			$summary = __( 'Updates API returned 200 with an available update (or check again after WordPress merges the transient).', 'reactwoo-geo-ai' );
		}

		self::save(
			array(
				'ts'         => time(),
				'http'       => $http_code,
				'summary'    => $summary,
				'body_snip'  => $snip,
				'has_update' => is_array( $data ) && ! empty( $data['update'] ) && ! empty( $data['version'] ),
				'api_version'=> is_array( $data ) && isset( $data['version'] ) ? (string) $data['version'] : '',
			)
		);
	}

	/**
	 * @param array<string, mixed> $row Row.
	 * @return void
	 */
	private static function save( $row ) {
		update_option( self::OPTION_LAST, $row, false );
	}
}
