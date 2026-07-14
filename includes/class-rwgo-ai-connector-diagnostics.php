<?php
/**
 * AI Connector diagnostics — local / remote API / remote workflow tests.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Separate pass/fail checks for the embedded AI connector (merged Geo AI).
 */
class RWGO_AI_Connector_Diagnostics {

	const FLASH_META   = 'rwgo_ai_connector_diag_flash';
	const ACTION_PREFIX = 'rwgo_ai_diag_';

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		$actions = array( 'local', 'remote_api', 'remote_workflow', 'remote_fallback', 'save_engine' );
		foreach ( $actions as $action ) {
			add_action( 'admin_post_' . self::ACTION_PREFIX . $action, array( __CLASS__, 'handle_admin_post' ) );
		}
	}

	/**
	 * Redirect target (Optimise hub Settings).
	 *
	 * @return string
	 */
	public static function settings_url() {
		if ( class_exists( 'RWGO_Optimise_Hub', false ) ) {
			return RWGO_Optimise_Hub::tab_url( 'settings' );
		}
		return admin_url( 'admin.php?page=rwgo-optimise&tab=settings' );
	}

	/**
	 * Status rows for the diagnostics panel.
	 *
	 * @return array<string, mixed>
	 */
	public static function status_rows() {
		$local_state = 'not_installed';
		$local_label = __( 'Not installed', 'reactwoo-geo-optimise' );
		if ( class_exists( 'RWGA_Local_AI_Transport', false ) && class_exists( 'RWGA_Generation_Router', false ) ) {
			$snap = RWGA_Generation_Router::status_snapshot(
				'ux_opportunity_review',
				array(
					'local_callback' => static function () {
						return array( 'summary' => 'ok', 'cards' => array() );
					},
				)
			);
			if ( ! empty( $snap['local_available'] ) ) {
				$local_state = 'available';
				$local_label = __( 'Available', 'reactwoo-geo-optimise' );
			} else {
				$local_state = 'failed';
				$local_label = __( 'Failed', 'reactwoo-geo-optimise' );
			}
		}

		$remote_api_state = 'not_connected';
		$remote_api_label = __( 'Not connected', 'reactwoo-geo-optimise' );
		$lic_ok           = class_exists( 'RWGA_Settings', false ) && RWGA_Settings::is_license_configured_for_geo_ai_ui();
		if ( $lic_ok && class_exists( 'RWGA_Platform_Client', false ) && RWGA_Platform_Client::is_configured() ) {
			$remote_api_state = 'connected';
			$remote_api_label = __( 'Connected', 'reactwoo-geo-optimise' );
		} elseif ( $lic_ok ) {
			$remote_api_state = 'error';
			$remote_api_label = __( 'Error', 'reactwoo-geo-optimise' );
		}

		$entitlement_state = 'requires_pro';
		$entitlement_label = __( 'Requires Pro', 'reactwoo-geo-optimise' );
		if ( class_exists( 'RWGA_AI_Usage_Guard', false ) ) {
			$gate = RWGA_AI_Usage_Guard::can_run_managed_generation( 'ux_opportunity_review' );
			if ( ! empty( $gate['allowed'] ) ) {
				$entitlement_state = 'available';
				$entitlement_label = __( 'Available', 'reactwoo-geo-optimise' );
			} elseif ( ! $lic_ok ) {
				$entitlement_state = 'error';
				$entitlement_label = __( 'Error', 'reactwoo-geo-optimise' );
			}
		} elseif ( ! $lic_ok ) {
			$entitlement_state = 'error';
			$entitlement_label = __( 'Error', 'reactwoo-geo-optimise' );
		}

		$product_slug = self::resolve_product_slug();
		$slug_label   = 'reactwoo-geo-optimise' === $product_slug
			? 'reactwoo-geo-optimise'
			: sprintf(
				/* translators: %s: legacy product slug */
				__( 'legacy %s', 'reactwoo-geo-optimise' ),
				$product_slug
			);

		$engine = class_exists( 'RWGA_Engine', false ) ? RWGA_Engine::get_mode() : 'automatic';
		$path   = class_exists( 'RWGA_Remote_Client', false )
			? (string) apply_filters( 'rwga_remote_workflow_path', RWGA_Remote_Client::DEFAULT_PATH, 'ux_opportunity_review', array() )
			: '/api/v5/geo-ai/workflow';
		$api_base = class_exists( 'RWGA_Platform_Client', false )
			? RWGA_Platform_Client::get_api_base()
			: ( class_exists( 'RWGO_Platform_Client', false ) ? RWGO_Platform_Client::get_api_base() : 'https://api.reactwoo.com' );

		return array(
			'local' => array(
				'state' => $local_state,
				'label' => $local_label,
			),
			'remote_api' => array(
				'state' => $remote_api_state,
				'label' => $remote_api_label,
			),
			'entitlement' => array(
				'state' => $entitlement_state,
				'label' => $entitlement_label,
			),
			'product_slug' => array(
				'value' => $product_slug,
				'label' => $slug_label,
			),
			'workflow_engine' => $engine,
			'api_base'        => $api_base,
			'workflow_path'   => $path,
			'route_alias'     => ( false !== strpos( $path, 'geo-optimise' ) ) ? 'geo-optimise' : 'geo-ai',
		);
	}

	/**
	 * @return string
	 */
	public static function resolve_product_slug() {
		if ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Platform_Client', false ) ) {
			return RWGO_Platform_Client::PRODUCT_SLUG;
		}
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			return RWGA_Platform_Client::PRODUCT_SLUG;
		}
		return 'reactwoo-geo-optimise';
	}

	/**
	 * Consume flash notice for the current user.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function consume_flash() {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return null;
		}
		$flash = get_user_meta( $uid, self::FLASH_META, true );
		delete_user_meta( $uid, self::FLASH_META );
		return is_array( $flash ) ? $flash : null;
	}

	/**
	 * @param array<string, mixed> $flash Flash payload.
	 * @return void
	 */
	private static function set_flash( array $flash ) {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}
		update_user_meta( $uid, self::FLASH_META, $flash );
	}

	/**
	 * @return void
	 */
	public static function handle_admin_post() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run AI Connector diagnostics.', 'reactwoo-geo-optimise' ) );
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key    = str_replace( self::ACTION_PREFIX, '', $action );
		check_admin_referer( 'rwgo_ai_diag_' . $key );

		if ( 'save_engine' === $key ) {
			self::save_workflow_engine();
			self::redirect_back();
		}

		$result = null;
		switch ( $key ) {
			case 'local':
				$result = self::run_local_test();
				break;
			case 'remote_api':
				$result = self::run_remote_api_test();
				break;
			case 'remote_workflow':
				$result = self::run_remote_workflow_test();
				break;
			case 'remote_fallback':
				$result = self::run_remote_fallback_test();
				break;
			default:
				$result = array(
					'ok'      => false,
					'title'   => __( 'Unknown diagnostic', 'reactwoo-geo-optimise' ),
					'message' => __( 'Unrecognised diagnostic action.', 'reactwoo-geo-optimise' ),
				);
		}

		self::set_flash( is_array( $result ) ? $result : array( 'ok' => false, 'message' => __( 'Diagnostic failed.', 'reactwoo-geo-optimise' ) ) );
		self::redirect_back();
	}

	/**
	 * @return void
	 */
	private static function redirect_back() {
		wp_safe_redirect( self::settings_url() );
		exit;
	}

	/**
	 * Persist workflow_engine on rwga_settings.
	 *
	 * @return void
	 */
	private static function save_workflow_engine() {
		if ( ! class_exists( 'RWGA_Settings', false ) ) {
			self::set_flash(
				array(
					'ok'      => false,
					'title'   => __( 'AI settings unavailable', 'reactwoo-geo-optimise' ),
					'message' => __( 'Embedded Geo AI settings are not loaded.', 'reactwoo-geo-optimise' ),
				)
			);
			return;
		}
		$mode = isset( $_POST['workflow_engine'] ) ? sanitize_key( wp_unslash( (string) $_POST['workflow_engine'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed = class_exists( 'RWGA_Engine', false )
			? RWGA_Engine::allowed_modes()
			: array( 'automatic', 'wordpress_ai', 'managed', 'local', 'remote', 'remote_fallback' );
		if ( ! in_array( $mode, $allowed, true ) ) {
			$mode = 'automatic';
		}
		$settings                     = RWGA_Settings::get_settings();
		$settings['workflow_engine']  = $mode;
		$settings['rwga_form_scope']  = 'advanced';
		update_option( RWGA_Settings::OPTION_KEY, RWGA_Settings::sanitize_settings( $settings ) );
		self::set_flash(
			array(
				'ok'      => true,
				'title'   => __( 'Execution mode saved', 'reactwoo-geo-optimise' ),
				'message' => sprintf(
					/* translators: %s: engine mode */
					__( 'AI workflow engine set to “%s”.', 'reactwoo-geo-optimise' ),
					$mode
				),
			)
		);
	}

	/**
	 * Safe sample page for local/remote review tests.
	 *
	 * @return int
	 */
	private static function sample_page_id() {
		$front = (int) get_option( 'page_on_front' );
		if ( $front > 0 ) {
			return $front;
		}
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		return ! empty( $pages[0] ) ? (int) $pages[0] : 0;
	}

	/**
	 * Deterministic local callback used by diagnostics (does not call the API).
	 *
	 * @return callable
	 */
	private static function local_callback() {
		return static function ( $workflow_key, array $request ) {
			unset( $request );
			return array(
				'summary'        => __( 'Local diagnostic review completed.', 'reactwoo-geo-optimise' ),
				'cards'          => array(
					array(
						'title'       => __( 'Diagnostics probe', 'reactwoo-geo-optimise' ),
						'description' => __( 'Review the homepage copy', 'reactwoo-geo-optimise' ),
						'priority'    => 'low',
					),
				),
				'workflow_key'   => sanitize_key( (string) $workflow_key ),
				'schema_version' => 1,
			);
		};
	}

	/**
	 * Temporarily force generation mode for one request.
	 *
	 * @param string   $mode Mode.
	 * @param callable $fn   Callback.
	 * @return mixed
	 */
	private static function with_engine_mode( $mode, $fn ) {
		$mode = sanitize_key( (string) $mode );
		$filter = static function () use ( $mode ) {
			return $mode;
		};
		add_filter( 'rwga_workflow_engine_mode', $filter, 999 );
		try {
			return call_user_func( $fn );
		} finally {
			remove_filter( 'rwga_workflow_engine_mode', $filter, 999 );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function run_local_test() {
		$workflow = 'ux_opportunity_review';
		if ( ! class_exists( 'RWGA_Generation_Router', false ) ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Local test failed', 'reactwoo-geo-optimise' ),
				'message' => sprintf(
					/* translators: %s: workflow key */
					__( 'Local deterministic review failed. Workflow: %s. Missing class: RWGA_Generation_Router.', 'reactwoo-geo-optimise' ),
					$workflow
				),
			);
		}
		if ( ! class_exists( 'RWGA_Local_AI_Transport', false ) ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Local test failed', 'reactwoo-geo-optimise' ),
				'message' => sprintf(
					/* translators: %s: workflow key */
					__( 'Local deterministic review failed. Workflow: %s. Missing class: RWGA_Local_AI_Transport.', 'reactwoo-geo-optimise' ),
					$workflow
				),
			);
		}

		$page_id = self::sample_page_id();
		try {
			$envelope = self::with_engine_mode(
				'local',
				static function () use ( $workflow, $page_id ) {
					return RWGA_Generation_Router::generate(
						$workflow,
						array(
							'payload'        => array(
								'page_id'      => $page_id,
								'instructions' => 'Review the homepage copy',
								'source'       => 'rwgo_ai_diagnostics',
							),
							'local_callback' => self::local_callback(),
						)
					);
				}
			);
		} catch ( Exception $e ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Local test failed', 'reactwoo-geo-optimise' ),
				'message' => sprintf(
					/* translators: 1: workflow key, 2: error */
					__( 'Local deterministic review failed. Workflow: %1$s. PHP error: %2$s', 'reactwoo-geo-optimise' ),
					$workflow,
					$e->getMessage()
				),
			);
		}

		if ( is_wp_error( $envelope ) ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Local test failed', 'reactwoo-geo-optimise' ),
				'message' => sprintf(
					/* translators: 1: workflow key, 2: error code, 3: message */
					__( 'Local deterministic review failed. Workflow: %1$s. %2$s: %3$s', 'reactwoo-geo-optimise' ),
					$workflow,
					$envelope->get_error_code(),
					$envelope->get_error_message()
				),
			);
		}

		$transport = isset( $envelope['transport'] ) ? sanitize_key( (string) $envelope['transport'] ) : '';
		if ( 'local' !== $transport ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Local test failed', 'reactwoo-geo-optimise' ),
				'message' => sprintf(
					/* translators: 1: workflow, 2: transport */
					__( 'Local deterministic review failed. Workflow: %1$s. Unexpected transport: %2$s (API may have been contacted).', 'reactwoo-geo-optimise' ),
					$workflow,
					$transport ? $transport : '—'
				),
			);
		}

		$summary = '';
		if ( isset( $envelope['engine_response']['summary'] ) ) {
			$summary = sanitize_text_field( (string) $envelope['engine_response']['summary'] );
		}

		return array(
			'ok'      => true,
			'title'   => __( 'Local test passed', 'reactwoo-geo-optimise' ),
			'message' => __( 'Local deterministic review is working. WordPress can run the embedded AI review workflow.', 'reactwoo-geo-optimise' ),
			'detail'  => $summary,
		);
	}

	/**
	 * Authenticated API reachability without running a billable workflow when possible.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_remote_api_test() {
		$status   = self::status_rows();
		$api_base = (string) $status['api_base'];
		$slug     = (string) $status['product_slug']['value'];
		$path     = (string) $status['workflow_path'];
		$alias    = (string) $status['route_alias'];

		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Remote API check failed', 'reactwoo-geo-optimise' ),
				'message' => __( 'JWT missing — platform client is not loaded.', 'reactwoo-geo-optimise' ),
				'meta'    => compact( 'api_base', 'slug', 'path', 'alias' ),
			);
		}

		if ( ! RWGA_Platform_Client::is_configured() ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Remote API check failed', 'reactwoo-geo-optimise' ),
				'message' => __( 'JWT missing — save an Optimise / Geo AI licence key first.', 'reactwoo-geo-optimise' ),
				'meta'    => compact( 'api_base', 'slug', 'path', 'alias' ),
			);
		}

		$token = RWGA_Platform_Client::get_access_token();
		if ( is_wp_error( $token ) || ! is_string( $token ) || '' === $token ) {
			$err = get_transient( RWGA_Platform_Client::BEARER_ERROR_TRANSIENT );
			$msg = is_wp_error( $token )
				? $token->get_error_message()
				: ( is_string( $err ) && '' !== $err ? $err : __( 'Licence inactive or login failed.', 'reactwoo-geo-optimise' ) );
			$lower = strtolower( $msg );
			if ( false !== strpos( $lower, 'jwt' ) || false !== strpos( $lower, 'token' ) ) {
				$msg = __( 'JWT missing', 'reactwoo-geo-optimise' );
			} elseif ( false !== strpos( $lower, 'inactive' ) || false !== strpos( $lower, 'license' ) || false !== strpos( $lower, 'licence' ) ) {
				$msg = __( 'Licence inactive', 'reactwoo-geo-optimise' );
			} elseif ( false !== strpos( $lower, 'entitlement' ) || false !== strpos( $lower, 'product' ) ) {
				$msg = __( 'Product entitlement missing', 'reactwoo-geo-optimise' );
			}
			return array(
				'ok'      => false,
				'title'   => __( 'Remote API check failed', 'reactwoo-geo-optimise' ),
				'message' => $msg,
				'meta'    => array(
					'api_base'      => $api_base,
					'product_slug'  => $slug,
					'http_status'   => 0,
					'route_alias'   => $alias,
					'workflow_path' => $path,
				),
			);
		}

		$usage = RWGA_Platform_Client::get_usage();
		if ( is_wp_error( $usage ) ) {
			$data   = $usage->get_error_data();
			$http   = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
			$msg    = $usage->get_error_message();
			$lower  = strtolower( $msg );
			if ( 404 === $http ) {
				$msg = __( 'API route not found', 'reactwoo-geo-optimise' );
			} elseif ( 401 === $http || false !== strpos( $lower, 'jwt' ) ) {
				$msg = __( 'JWT missing', 'reactwoo-geo-optimise' );
			} elseif ( false !== strpos( $lower, 'entitlement' ) || false !== strpos( $lower, 'product' ) ) {
				$msg = __( 'Product entitlement missing', 'reactwoo-geo-optimise' );
			} elseif ( false !== strpos( $lower, 'inactive' ) || false !== strpos( $lower, 'license' ) ) {
				$msg = __( 'Licence inactive', 'reactwoo-geo-optimise' );
			} elseif ( $http >= 500 ) {
				$msg = __( 'API returned 500', 'reactwoo-geo-optimise' );
			}
			return array(
				'ok'      => false,
				'title'   => __( 'Remote API check failed', 'reactwoo-geo-optimise' ),
				'message' => $msg,
				'meta'    => array(
					'api_base'      => $api_base,
					'product_slug'  => $slug,
					'http_status'   => $http,
					'route_alias'   => $alias,
					'workflow_path' => $path,
					'entitlement'   => 'error',
				),
			);
		}

		$http = isset( $usage['http_code'] ) ? (int) $usage['http_code'] : 200;
		$gate = class_exists( 'RWGA_AI_Usage_Guard', false )
			? RWGA_AI_Usage_Guard::can_run_managed_generation( 'ux_opportunity_review' )
			: array( 'allowed' => false, 'reason' => '' );

		return array(
			'ok'      => true,
			'title'   => __( 'Remote API check passed', 'reactwoo-geo-optimise' ),
			'message' => __( 'Remote API is reachable and authenticated.', 'reactwoo-geo-optimise' ),
			'meta'    => array(
				'api_base'      => $api_base,
				'product_slug'  => $slug,
				'http_status'   => $http,
				'route_alias'   => $alias,
				'workflow_path' => $path,
				'entitlement'   => ! empty( $gate['allowed'] ) ? 'available' : 'requires_pro_or_blocked',
				'entitlement_detail' => isset( $gate['reason'] ) ? (string) $gate['reason'] : '',
			),
		);
	}

	/**
	 * Minimal remote workflow (Pro-gated). Does not treat local engine as failed on tier block.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_remote_workflow_test() {
		$workflow = 'ux_opportunity_review';
		if ( ! class_exists( 'RWGA_Generation_Router', false ) || ! class_exists( 'RWGA_Managed_AI_Transport', false ) ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Remote workflow test failed', 'reactwoo-geo-optimise' ),
				'message' => __( 'Managed AI transport is not installed.', 'reactwoo-geo-optimise' ),
				'tier'    => false,
			);
		}

		$page_id = self::sample_page_id();
		$envelope = self::with_engine_mode(
			'managed',
			static function () use ( $workflow, $page_id ) {
				return RWGA_Generation_Router::generate(
					$workflow,
					array(
						'payload' => array(
							'page_id'      => $page_id,
							'instructions' => 'Review the homepage copy',
							'source'       => 'rwgo_ai_diagnostics_remote',
						),
					)
				);
			}
		);

		if ( is_wp_error( $envelope ) ) {
			$msg = $envelope->get_error_message();
			if ( self::is_tier_gate_message( $msg ) ) {
				return array(
					'ok'      => false,
					'title'   => __( 'Remote workflow requires Pro', 'reactwoo-geo-optimise' ),
					'message' => __( 'Remote AI workflow requires Pro tier or higher. Local deterministic review may still be available.', 'reactwoo-geo-optimise' ),
					'tier'    => true,
					'detail'  => $msg,
				);
			}
			return array(
				'ok'      => false,
				'title'   => __( 'Remote workflow test failed', 'reactwoo-geo-optimise' ),
				'message' => $msg,
				'tier'    => false,
			);
		}

		return array(
			'ok'      => true,
			'title'   => __( 'Remote workflow test passed', 'reactwoo-geo-optimise' ),
			'message' => __( 'Remote AI workflow completed successfully.', 'reactwoo-geo-optimise' ),
			'tier'    => false,
		);
	}

	/**
	 * Attempt managed remote, then local when remote is blocked/unavailable.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_remote_fallback_test() {
		$workflow = 'ux_opportunity_review';
		if ( ! class_exists( 'RWGA_Generation_Router', false ) ) {
			return array(
				'ok'      => false,
				'title'   => __( 'Remote fallback test failed', 'reactwoo-geo-optimise' ),
				'message' => __( 'Generation router is not available.', 'reactwoo-geo-optimise' ),
			);
		}

		$page_id  = self::sample_page_id();
		$envelope = self::with_engine_mode(
			'remote_fallback',
			static function () use ( $workflow, $page_id ) {
				return RWGA_Generation_Router::generate(
					$workflow,
					array(
						'payload'        => array(
							'page_id'      => $page_id,
							'instructions' => 'Review the homepage copy',
							'source'       => 'rwgo_ai_diagnostics_fallback',
						),
						'local_callback' => self::local_callback(),
					)
				);
			}
		);

		$meta = class_exists( 'RWGA_Generation_Router', false ) ? RWGA_Generation_Router::last_meta() : array();
		$skipped = isset( $meta['skipped'] ) && is_array( $meta['skipped'] ) ? $meta['skipped'] : array();

		if ( is_wp_error( $envelope ) ) {
			$msg = $envelope->get_error_message();
			if ( self::is_tier_gate_message( $msg ) ) {
				// Generation started on managed and failed mid-flight — router will not fall through.
				$local = self::run_local_test();
				if ( ! empty( $local['ok'] ) ) {
					return array(
						'ok'      => true,
						'title'   => __( 'Remote fallback completed', 'reactwoo-geo-optimise' ),
						'message' => __( 'Remote workflow blocked by tier. Local fallback completed successfully.', 'reactwoo-geo-optimise' ),
						'detail'  => $msg,
					);
				}
				return array(
					'ok'      => false,
					'title'   => __( 'Remote fallback test failed', 'reactwoo-geo-optimise' ),
					'message' => __( 'Remote workflow blocked by tier. Local fallback also failed.', 'reactwoo-geo-optimise' ),
					'detail'  => isset( $local['message'] ) ? (string) $local['message'] : $msg,
				);
			}
			return array(
				'ok'      => false,
				'title'   => __( 'Remote fallback test failed', 'reactwoo-geo-optimise' ),
				'message' => $msg,
			);
		}

		$transport = isset( $envelope['transport'] ) ? sanitize_key( (string) $envelope['transport'] ) : '';
		if ( 'local' === $transport ) {
			$remote_note = ! empty( $skipped['managed'] ) ? (string) $skipped['managed'] : '';
			if ( self::is_tier_gate_message( $remote_note ) || self::is_tier_gate_message( (string) ( $meta['fallback_reason'] ?? '' ) ) ) {
				return array(
					'ok'      => true,
					'title'   => __( 'Remote fallback completed', 'reactwoo-geo-optimise' ),
					'message' => __( 'Remote workflow blocked by tier. Local fallback completed successfully.', 'reactwoo-geo-optimise' ),
					'detail'  => $remote_note,
				);
			}
			return array(
				'ok'      => true,
				'title'   => __( 'Remote fallback completed', 'reactwoo-geo-optimise' ),
				'message' => __( 'Remote managed AI was unavailable. Local fallback completed successfully.', 'reactwoo-geo-optimise' ),
				'detail'  => $remote_note,
			);
		}

		return array(
			'ok'      => true,
			'title'   => __( 'Remote fallback completed', 'reactwoo-geo-optimise' ),
			'message' => __( 'Remote workflow succeeded (local fallback was not required).', 'reactwoo-geo-optimise' ),
		);
	}

	/**
	 * @param string $message Message.
	 * @return bool
	 */
	private static function is_tier_gate_message( $message ) {
		$m = strtolower( (string) $message );
		return false !== strpos( $m, 'requires' ) && false !== strpos( $m, 'tier' );
	}
}
