<?php
/**
 * Live GTM provision via React Cloud OAuth (tokens never stored in WordPress).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GTM live connect + push orchestration.
 */
class RWGO_GTM_Live {

	const OPTION_CONNECTED = 'rwgo_gtm_connected';
	const OPTION_TARGET    = 'rwgo_gtm_target';
	const TRANSIENT_STATE  = 'rwgo_gtm_oauth_state_';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_rwgo_gtm_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_rwgo_gtm_oauth_callback', array( __CLASS__, 'handle_oauth_callback' ) );
		add_action( 'admin_post_rwgo_gtm_disconnect', array( __CLASS__, 'handle_disconnect' ) );
		add_action( 'admin_post_rwgo_gtm_save_target', array( __CLASS__, 'handle_save_target' ) );
		add_action( 'admin_post_rwgo_gtm_push', array( __CLASS__, 'handle_push' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_connected() {
		return (bool) get_option( self::OPTION_CONNECTED, false );
	}

	/**
	 * @return array{account_id:string,container_id:string,workspace_id:string,measurement_id:string}
	 */
	public static function get_target() {
		$row = get_option( self::OPTION_TARGET, array() );
		if ( ! is_array( $row ) ) {
			$row = array();
		}
		return array(
			'account_id'     => isset( $row['account_id'] ) ? (string) $row['account_id'] : '',
			'container_id'   => isset( $row['container_id'] ) ? (string) $row['container_id'] : '',
			'workspace_id'   => isset( $row['workspace_id'] ) ? (string) $row['workspace_id'] : '',
			'measurement_id' => isset( $row['measurement_id'] ) ? (string) $row['measurement_id'] : '',
		);
	}

	/**
	 * @return string
	 */
	public static function oauth_callback_url() {
		return admin_url( 'admin-post.php?action=rwgo_gtm_oauth_callback' );
	}

	/**
	 * @return string|\WP_Error
	 */
	public static function begin_connect() {
		if ( ! class_exists( 'RWGO_Cloud_Client', false ) ) {
			return new WP_Error( 'rwgo_gtm_no_client', __( 'Cloud client unavailable.', 'reactwoo-geo-optimise' ) );
		}
		$state = wp_generate_password( 32, false, false );
		set_transient(
			self::TRANSIENT_STATE . $state,
			array(
				'user_id' => get_current_user_id(),
				'ts'      => time(),
			),
			15 * MINUTE_IN_SECONDS
		);
		$payload = array(
			'redirect_uri' => self::oauth_callback_url(),
			'integration'  => 'google_tag_manager',
			'state'        => $state,
		);
		$res = RWGO_Cloud_Client::google_auth_url( $payload );
		if ( is_wp_error( $res ) ) {
			delete_transient( self::TRANSIENT_STATE . $state );
			return $res;
		}
		$url = '';
		if ( ! empty( $res['authorization_url'] ) ) {
			$url = (string) $res['authorization_url'];
		} elseif ( ! empty( $res['auth_url'] ) ) {
			$url = (string) $res['auth_url'];
		}
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			delete_transient( self::TRANSIENT_STATE . $state );
			return new WP_Error( 'rwgo_gtm_no_auth_url', __( 'React Cloud did not return a Google authorization URL.', 'reactwoo-geo-optimise' ) );
		}
		return $url;
	}

	/**
	 * After cloud redirect with google_oauth_success + state.
	 *
	 * @param string $state State.
	 * @return true|\WP_Error
	 */
	public static function finalize_oauth( $state ) {
		$state = is_string( $state ) ? trim( $state ) : '';
		if ( '' === $state ) {
			return new WP_Error( 'rwgo_gtm_oauth_missing', __( 'OAuth response incomplete.', 'reactwoo-geo-optimise' ) );
		}
		$row = get_transient( self::TRANSIENT_STATE . $state );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwgo_gtm_oauth_state', __( 'OAuth session expired. Try connecting again.', 'reactwoo-geo-optimise' ) );
		}
		if ( isset( $row['user_id'] ) && (int) $row['user_id'] !== (int) get_current_user_id() ) {
			return new WP_Error( 'rwgo_gtm_oauth_user', __( 'OAuth session does not match the current user.', 'reactwoo-geo-optimise' ) );
		}
		$res = RWGO_Cloud_Client::google_finalize_token( array( 'state' => $state ) );
		delete_transient( self::TRANSIENT_STATE . $state );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		update_option( self::OPTION_CONNECTED, 1, false );
		return true;
	}

	/**
	 * Refresh connected flag from cloud.
	 *
	 * @return bool
	 */
	public static function refresh_status() {
		$res = RWGO_Cloud_Client::gtm_status();
		if ( is_wp_error( $res ) ) {
			return self::is_connected();
		}
		$ok = ! empty( $res['connected'] );
		update_option( self::OPTION_CONNECTED, $ok ? 1 : 0, false );
		return $ok;
	}

	/**
	 * Push provision pack for an experiment.
	 *
	 * @param int  $experiment_post_id Experiment id.
	 * @param bool $dry_run            Preview only.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function push_experiment( $experiment_post_id, $dry_run = true ) {
		$experiment_post_id = (int) $experiment_post_id;
		$post = get_post( $experiment_post_id );
		if ( ! ( $post instanceof WP_Post ) || ! class_exists( 'RWGO_Experiment_Repository', false ) || ! class_exists( 'RWGO_GTM_Provisioner', false ) ) {
			return new WP_Error( 'rwgo_gtm_bad_exp', __( 'Experiment not found.', 'reactwoo-geo-optimise' ) );
		}
		$cfg  = RWGO_Experiment_Repository::get_config( $experiment_post_id );
		$pack = RWGO_GTM_Provisioner::build_pack( $post, is_array( $cfg ) ? $cfg : array() );
		$target = self::get_target();
		if ( '' === $target['account_id'] || '' === $target['container_id'] ) {
			return new WP_Error( 'rwgo_gtm_no_target', __( 'Save a GTM account and container before pushing.', 'reactwoo-geo-optimise' ) );
		}
		$payload = array(
			'account_id'     => $target['account_id'],
			'container_id'   => $target['container_id'],
			'workspace_id'   => $target['workspace_id'],
			'measurement_id' => $target['measurement_id'],
			'pack'           => $pack,
			'dry_run'        => (bool) $dry_run,
		);
		return RWGO_Cloud_Client::gtm_provision( $payload );
	}

	/**
	 * @return void
	 */
	public static function handle_connect() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_gtm_connect' );
		$url = self::begin_connect();
		if ( is_wp_error( $url ) ) {
			wp_safe_redirect( add_query_arg( 'rwgo_gtm_err', rawurlencode( $url->get_error_message() ), self::tracking_tools_url() ) );
			exit;
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_oauth_callback() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		$ok = isset( $_GET['google_oauth_success'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $ok ) {
			wp_safe_redirect( add_query_arg( 'rwgo_gtm_err', rawurlencode( __( 'Google connection failed.', 'reactwoo-geo-optimise' ) ), self::tracking_tools_url() ) );
			exit;
		}
		$res = self::finalize_oauth( $state );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( 'rwgo_gtm_err', rawurlencode( $res->get_error_message() ), self::tracking_tools_url() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'rwgo_gtm_ok', '1', self::tracking_tools_url() ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_disconnect() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_gtm_disconnect' );
		update_option( self::OPTION_CONNECTED, 0, false );
		wp_safe_redirect( add_query_arg( 'rwgo_gtm_disconnected', '1', self::tracking_tools_url() ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_save_target() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_gtm_save_target' );
		$target = array(
			'account_id'     => isset( $_POST['rwgo_gtm_account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_gtm_account_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'container_id'   => isset( $_POST['rwgo_gtm_container_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_gtm_container_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'workspace_id'   => isset( $_POST['rwgo_gtm_workspace_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_gtm_workspace_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'measurement_id' => isset( $_POST['rwgo_gtm_measurement_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_gtm_measurement_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);
		update_option( self::OPTION_TARGET, $target, false );
		wp_safe_redirect( add_query_arg( 'rwgo_gtm_saved', '1', self::tracking_tools_url() ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_push() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		$exp_id = isset( $_POST['rwgo_experiment_id'] ) ? (int) $_POST['rwgo_experiment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		check_admin_referer( 'rwgo_gtm_push_' . $exp_id );
		$dry = ! empty( $_POST['rwgo_gtm_dry_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$res = self::push_experiment( $exp_id, $dry );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( 'rwgo_gtm_err', rawurlencode( $res->get_error_message() ), self::tracking_tools_url() ) );
			exit;
		}
		$key = $dry ? 'rwgo_gtm_preview' : 'rwgo_gtm_pushed';
		set_transient( 'rwgo_gtm_last_result_' . get_current_user_id(), $res, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( $key, (string) $exp_id, self::tracking_tools_url() ) );
		exit;
	}

	/**
	 * @return string
	 */
	private static function tracking_tools_url() {
		return admin_url( 'admin.php?page=rwgo-tracking-tools' );
	}
}
