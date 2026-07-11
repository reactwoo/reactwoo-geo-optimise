<?php
/**
 * Geo AI ownership of site intelligence cloud sync (Geo Core builds; Geo AI uploads).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates licence checks, Geo Core snapshot build, hash dedupe, and API upload.
 */
class RWGA_Site_Intelligence_Sync {

	const OPTION_KEY = 'rwga_site_intelligence_sync';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwga_loaded', array( __CLASS__, 'on_loaded' ), 40 );
		add_filter( 'rwga_usage_display_rows', array( __CLASS__, 'filter_usage_display_rows' ), 15, 2 );
	}

	/**
	 * @return void
	 */
	public static function on_loaded() {
		add_action( RWGA_Cron::HOOK, array( __CLASS__, 'maybe_sync_on_cron' ), 20 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_status() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$defaults = array(
			'cloud_site_id'            => '',
			'last_snapshot_hash'       => '',
			'last_synced_at_gmt'       => '',
			'last_sync_status'         => '',
			'last_sync_error'          => '',
			'last_skip_reason'         => '',
			'snapshot_quota_used'      => 0,
			'snapshot_quota_limit'     => 0,
			'snapshot_quota_month'     => '',
		);

		$status = array_merge( $defaults, $raw );

		if ( class_exists( 'RWGC_AI_Snapshot_Sync_Status', false ) ) {
			$core = RWGC_AI_Snapshot_Sync_Status::get_status();
			if ( empty( $status['last_snapshot_hash'] ) && ! empty( $core['last_built_hash'] ) ) {
				$status['last_snapshot_hash'] = (string) $core['last_built_hash'];
			}
		}

		return $status;
	}

	/**
	 * @param array<string, mixed> $patch Partial status update.
	 * @return void
	 */
	private static function save_status( array $patch ) {
		$status = self::get_status();
		update_option( self::OPTION_KEY, array_merge( $status, $patch ), false );
	}

	/**
	 * Sync site intelligence to the cloud.
	 *
	 * @param bool $force Upload even when snapshot hash is unchanged.
	 * @return array<string, mixed>|\WP_Error Result with status key.
	 */
	public static function sync( $force = false ) {
		$guard = RWGA_AI_Usage_Guard::can_sync_snapshot();
		if ( empty( $guard['allowed'] ) ) {
			$reason = isset( $guard['reason'] ) ? (string) $guard['reason'] : __( 'Sync not allowed.', 'reactwoo-geo-ai' );
			self::save_status(
				array(
					'last_sync_status' => 'blocked',
					'last_sync_error'  => $reason,
				)
			);
			return new WP_Error( 'rwga_sync_blocked', $reason );
		}

		$snapshot = rwgc_build_ai_snapshot();
		if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
			$err = __( 'Geo Core returned an empty site intelligence snapshot.', 'reactwoo-geo-ai' );
			self::save_status(
				array(
					'last_sync_status' => 'error',
					'last_sync_error'  => $err,
				)
			);
			return new WP_Error( 'rwga_empty_snapshot', $err );
		}

		$size_check = RWGA_AI_Usage_Guard::check_payload_size( $snapshot );
		if ( is_wp_error( $size_check ) ) {
			self::save_status(
				array(
					'last_sync_status' => 'error',
					'last_sync_error'  => $size_check->get_error_message(),
				)
			);
			return $size_check;
		}

		$hash = isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '';
		if ( '' === $hash && class_exists( 'RWGC_AI_Snapshot_Schema', false ) ) {
			$hash = RWGC_AI_Snapshot_Schema::compute_hash( $snapshot );
		}

		$status = self::get_status();
		if ( ! $force && '' !== $hash && $hash === (string) ( $status['last_snapshot_hash'] ?? '' ) && 'synced' === (string) ( $status['last_sync_status'] ?? '' ) ) {
			$skip = __( 'Snapshot unchanged since last sync.', 'reactwoo-geo-ai' );
			self::save_status(
				array(
					'last_sync_status' => 'skipped',
					'last_skip_reason' => $skip,
					'last_sync_error'  => '',
				)
			);
			return array(
				'status'  => 'skipped',
				'message' => $skip,
				'hash'    => $hash,
			);
		}

		if ( ! $force && self::is_snapshot_quota_blocked() ) {
			$quota = self::get_snapshot_quota_status();
			$err   = RWGA_Site_Snapshot_Client::format_snapshot_quota_message( $quota );
			self::save_status(
				array(
					'last_sync_status' => 'error',
					'last_sync_error'  => $err,
				)
			);
			return new WP_Error(
				'rwga_snapshot_quota_exceeded',
				$err,
				array(
					'quota' => $quota,
					'code'  => 'SNAPSHOT_QUOTA_EXCEEDED',
				)
			);
		}

		$site_id = isset( $status['cloud_site_id'] ) ? (string) $status['cloud_site_id'] : '';
		if ( '' === $site_id ) {
			$registered = RWGA_Site_Snapshot_Client::register_site();
			if ( is_wp_error( $registered ) ) {
				self::save_status(
					array(
						'last_sync_status' => 'error',
						'last_sync_error'  => $registered->get_error_message(),
					)
				);
				return $registered;
			}
			$site_id = (string) $registered['site_id'];
			self::save_status( array( 'cloud_site_id' => $site_id ) );
		}

		$upload = RWGA_Site_Snapshot_Client::upload_snapshot( $site_id, $snapshot );
		if ( is_wp_error( $upload ) ) {
			$patch = array(
				'last_sync_status' => 'error',
				'last_sync_error'  => $upload->get_error_message(),
			);
			$quota = self::extract_quota_from_error( $upload );
			if ( is_array( $quota ) ) {
				$patch = array_merge( $patch, self::quota_status_patch( $quota ) );
			}
			self::save_status( $patch );
			if ( class_exists( 'RWGC_AI_Snapshot_Sync_Status', false ) ) {
				RWGC_AI_Snapshot_Sync_Status::record_sync_error( $upload->get_error_message() );
			}
			return $upload;
		}

		$now   = gmdate( 'c' );
		$patch = array(
			'cloud_site_id'      => $site_id,
			'last_snapshot_hash' => $hash,
			'last_synced_at_gmt' => $now,
			'last_sync_status'   => 'synced',
			'last_sync_error'    => '',
			'last_skip_reason'   => '',
		);
		$quota = self::extract_quota_from_upload( $upload );
		if ( is_array( $quota ) ) {
			$patch = array_merge( $patch, self::quota_status_patch( $quota ) );
		}
		self::save_status( $patch );

		if ( class_exists( 'RWGC_AI_Snapshot_Sync_Status', false ) ) {
			RWGC_AI_Snapshot_Sync_Status::record_sync_success( $hash );
		}

		/**
		 * Fires after a successful site intelligence cloud sync.
		 *
		 * @param array<string, mixed> $upload   Upload response from {@see RWGA_Site_Snapshot_Client::upload_snapshot()}.
		 * @param array<string, mixed> $snapshot Snapshot payload sent.
		 */
		do_action( 'rwga_site_intelligence_synced', $upload, $snapshot );

		return array(
			'status'   => 'synced',
			'hash'     => $hash,
			'site_id'  => $site_id,
			'synced_at_gmt' => $now,
		);
	}

	/**
	 * Cron hook: sync when licensed and snapshot changed.
	 *
	 * @return void
	 */
	public static function maybe_sync_on_cron() {
		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::is_configured() ) {
			return;
		}
		if ( self::is_snapshot_quota_blocked() ) {
			return;
		}
		self::sync( false );
	}

	/**
	 * Whether the monthly snapshot upload quota is exhausted for the current month.
	 *
	 * @return bool
	 */
	public static function is_snapshot_quota_blocked() {
		$quota = self::get_snapshot_quota_status();
		if ( empty( $quota['month'] ) || $quota['month'] !== gmdate( 'Y-m' ) ) {
			return false;
		}
		$limit = isset( $quota['limit'] ) ? (int) $quota['limit'] : 0;
		$used  = isset( $quota['used'] ) ? (int) $quota['used'] : 0;
		if ( $limit <= 0 ) {
			return true;
		}
		return $used >= $limit;
	}

	/**
	 * @return array{used:int,limit:int,month:string}
	 */
	public static function get_snapshot_quota_status() {
		$status = self::get_status();
		return array(
			'used'  => isset( $status['snapshot_quota_used'] ) ? (int) $status['snapshot_quota_used'] : 0,
			'limit' => isset( $status['snapshot_quota_limit'] ) ? (int) $status['snapshot_quota_limit'] : 0,
			'month' => isset( $status['snapshot_quota_month'] ) ? (string) $status['snapshot_quota_month'] : '',
		);
	}

	/**
	 * Whether a successful cloud sync has completed at least once.
	 *
	 * @return bool
	 */
	public static function has_synced_snapshot() {
		$status = self::get_status();
		return ! empty( $status['last_synced_at_gmt'] ) && 'synced' === sanitize_key( (string) ( $status['last_sync_status'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $upload Upload response.
	 * @return array{used:int,limit:int}|null
	 */
	private static function extract_quota_from_upload( $upload ) {
		if ( ! is_array( $upload ) ) {
			return null;
		}
		$response = isset( $upload['response'] ) && is_array( $upload['response'] ) ? $upload['response'] : array();
		$inner    = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;
		if ( isset( $inner['quota'] ) && is_array( $inner['quota'] ) ) {
			return self::normalize_quota_row( $inner['quota'] );
		}
		return null;
	}

	/**
	 * @param \WP_Error $error Upload error.
	 * @return array{used:int,limit:int}|null
	 */
	private static function extract_quota_from_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return null;
		}
		$data = $error->get_error_data();
		if ( isset( $data['quota'] ) && is_array( $data['quota'] ) ) {
			return self::normalize_quota_row( $data['quota'] );
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data']['quota'] ) && is_array( $data['data']['quota'] ) ) {
			return self::normalize_quota_row( $data['data']['quota'] );
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $quota API quota row.
	 * @return array{used:int,limit:int}
	 */
	private static function normalize_quota_row( array $quota ) {
		return array(
			'used'  => isset( $quota['used'] ) ? (int) $quota['used'] : 0,
			'limit' => isset( $quota['limit'] ) ? (int) $quota['limit'] : 0,
		);
	}

	/**
	 * @param array{used:int,limit:int} $quota Quota row.
	 * @return array<string, int|string>
	 */
	private static function quota_status_patch( array $quota ) {
		return array(
			'snapshot_quota_used'  => (int) $quota['used'],
			'snapshot_quota_limit' => (int) $quota['limit'],
			'snapshot_quota_month' => gmdate( 'Y-m' ),
		);
	}

	/**
	 * Append site intelligence sync rows to usage table.
	 *
	 * @param array<string, string> $rows  Display rows.
	 * @param array<string, mixed>  $cache Usage cache.
	 * @return array<string, string>
	 */
	public static function filter_usage_display_rows( $rows, $cache ) {
		unset( $cache );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$page   = self::get_license_page_status();
		$status = isset( $page['status'] ) && is_array( $page['status'] ) ? $page['status'] : self::get_status();
		$rows[ __( 'Site intelligence sync', 'reactwoo-geo-ai' ) ] = isset( $page['label'] ) ? (string) $page['label'] : self::format_status_label( $status );
		if ( ! empty( $status['last_synced_at_gmt'] ) ) {
			$rows[ __( 'Last intelligence sync (GMT)', 'reactwoo-geo-ai' ) ] = (string) $status['last_synced_at_gmt'];
		}
		if ( ! empty( $status['last_snapshot_hash'] ) ) {
			$hash = (string) $status['last_snapshot_hash'];
			$rows[ __( 'Snapshot hash', 'reactwoo-geo-ai' ) ] = substr( $hash, 0, 12 ) . '…';
		}
		return $rows;
	}

	/**
	 * License page status: merges stored sync state with a live pre-flight check.
	 *
	 * Refresh usage does not upload snapshots; a stale "blocked" row can linger after the license is fixed.
	 *
	 * @return array{
	 *   label:string,
	 *   hint:string,
	 *   error:string,
	 *   live_allowed:bool,
	 *   geocore_ready:bool,
	 *   status:array<string, mixed>
	 * }
	 */
	public static function get_license_page_status() {
		$status = self::get_status();
		$stored = isset( $status['last_sync_status'] ) ? sanitize_key( (string) $status['last_sync_status'] ) : '';

		$guard = class_exists( 'RWGA_AI_Usage_Guard', false )
			? RWGA_AI_Usage_Guard::can_sync_snapshot()
			: array(
				'allowed' => false,
				'reason'  => __( 'Site intelligence sync is not loaded.', 'reactwoo-geo-ai' ),
			);

		$live_allowed   = ! empty( $guard['allowed'] );
		$geocore_ready  = function_exists( 'rwgc_build_ai_snapshot' );
		$live_reason    = isset( $guard['reason'] ) ? trim( (string) $guard['reason'] ) : '';
		$stored_error   = isset( $status['last_sync_error'] ) ? trim( (string) $status['last_sync_error'] ) : '';
		$label          = self::format_status_label( $status );
		$hint           = '';
		$error          = '';

		if ( ! $geocore_ready ) {
			$label = __( 'Blocked', 'reactwoo-geo-ai' );
			$error = __( 'Geo Core site intelligence snapshot is not available. Update ReactWoo Geo Core.', 'reactwoo-geo-ai' );
		} elseif ( ! $live_allowed ) {
			$label = __( 'Blocked', 'reactwoo-geo-ai' );
			$error = '' !== $live_reason ? $live_reason : $stored_error;
		} elseif ( 'blocked' === $stored ) {
			$label = __( 'Ready to sync', 'reactwoo-geo-ai' );
			if ( '' !== $stored_error ) {
				$hint = sprintf(
					/* translators: %s: prior block reason */
					__( 'Previously blocked: %s', 'reactwoo-geo-ai' ),
					$stored_error
				);
			}
		} elseif ( 'error' === $stored && '' !== $stored_error ) {
			$error = $stored_error;
		}

		return array(
			'label'          => $label,
			'hint'           => $hint,
			'error'          => $error,
			'live_allowed'   => $live_allowed && $geocore_ready,
			'geocore_ready'  => $geocore_ready,
			'status'         => $status,
		);
	}

	/**
	 * Retry cloud sync when the license is valid but the stored row is still blocked/error.
	 *
	 * @return array<string, mixed>|\WP_Error|null Null when retry was not attempted.
	 */
	public static function maybe_retry_sync_when_ready() {
		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::is_configured() ) {
			return null;
		}

		$guard = RWGA_AI_Usage_Guard::can_sync_snapshot();
		if ( empty( $guard['allowed'] ) ) {
			return null;
		}

		$stored = sanitize_key( (string) ( self::get_status()['last_sync_status'] ?? '' ) );
		if ( in_array( $stored, array( 'synced', 'skipped' ), true ) ) {
			return null;
		}

		return self::sync( false );
	}

	/**
	 * @param array<string, mixed> $status Sync status row.
	 * @return string
	 */
	public static function format_status_label( array $status ) {
		$key = isset( $status['last_sync_status'] ) ? sanitize_key( (string) $status['last_sync_status'] ) : '';
		switch ( $key ) {
			case 'synced':
				return __( 'Synced', 'reactwoo-geo-ai' );
			case 'skipped':
				return __( 'Skipped (unchanged)', 'reactwoo-geo-ai' );
			case 'blocked':
				return __( 'Blocked', 'reactwoo-geo-ai' );
			case 'error':
				return __( 'Error', 'reactwoo-geo-ai' );
			default:
				return __( 'Not synced yet', 'reactwoo-geo-ai' );
		}
	}
}
