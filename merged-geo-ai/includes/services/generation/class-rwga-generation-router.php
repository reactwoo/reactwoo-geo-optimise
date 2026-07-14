<?php
/**
 * Generation router — transport selection and preflight fallback policy.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns automatic / explicit routing. Never falls through after a started generation fails.
 */
class RWGA_Generation_Router {

	/**
	 * @var array<string, mixed>|null
	 */
	private static $last_meta = null;

	/**
	 * @var array<string, RWGA_Generation_Transport>|null
	 */
	private static $transport_overrides = null;

	/**
	 * Test helper: inject fake transports keyed by transport key.
	 *
	 * @param array<string, RWGA_Generation_Transport>|null $map Map or null to clear.
	 * @return void
	 */
	public static function set_transport_overrides( $map ) {
		self::$transport_overrides = is_array( $map ) ? $map : null;
	}

	/**
	 * Metadata from the most recent generate() call.
	 *
	 * @return array<string, mixed>
	 */
	public static function last_meta() {
		return is_array( self::$last_meta ) ? self::$last_meta : array();
	}

	/**
	 * Telemetry fields for rwga_workflow_persisted.
	 *
	 * @return array<string, mixed>
	 */
	public static function telemetry_meta() {
		$m = self::last_meta();
		return array(
			'transport'       => isset( $m['transport'] ) ? sanitize_key( (string) $m['transport'] ) : '',
			'engine_source'   => isset( $m['engine_source'] ) ? sanitize_key( (string) $m['engine_source'] ) : '',
			'provider'        => isset( $m['provider'] ) ? sanitize_key( (string) $m['provider'] ) : '',
			'model'           => isset( $m['model'] ) ? sanitize_text_field( (string) $m['model'] ) : '',
			'prompt_version'  => isset( $m['prompt_version'] ) ? sanitize_text_field( (string) $m['prompt_version'] ) : '',
			'fallback_reason' => isset( $m['fallback_reason'] ) ? sanitize_text_field( (string) $m['fallback_reason'] ) : '',
			'remote_run_id'   => isset( $m['remote_run_id'] ) ? sanitize_text_field( (string) $m['remote_run_id'] ) : '',
			'cache_hit'       => ! empty( $m['cache_hit'] ),
		);
	}

	/**
	 * Run generation for a workflow.
	 *
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Must include payload; optional local_callback.
	 * @return array<string, mixed>|\WP_Error Transport success envelope or error.
	 */
	public static function generate( $workflow_key, array $request ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		self::$last_meta = array();

		$mode  = class_exists( 'RWGA_Engine', false ) ? RWGA_Engine::get_mode() : 'local';
		$chain = self::resolve_chain( $mode );

		$skipped = array();
		$last_unavailable = null;

		foreach ( $chain as $transport_key ) {
			$transport = self::get_transport( $transport_key );
			if ( ! $transport instanceof RWGA_Generation_Transport ) {
				$skipped[ $transport_key ] = 'missing_transport';
				continue;
			}

			if ( ! $transport->supports( $workflow_key, $request ) ) {
				$skipped[ $transport_key ] = 'unsupported';
				if ( ! self::allows_preflight_skip( $mode ) ) {
					return self::finish_error(
						new WP_Error(
							'rwga_transport_unsupported',
							sprintf(
								/* translators: 1: transport, 2: workflow */
								__( 'Transport “%1$s” does not support workflow “%2$s”.', 'reactwoo-geo-ai' ),
								$transport_key,
								$workflow_key
							)
						),
						$mode,
						$skipped
					);
				}
				continue;
			}

			$avail = $transport->availability( $workflow_key, $request );
			if ( is_wp_error( $avail ) ) {
				$code = $avail->get_error_code();
				$skipped[ $transport_key ] = $avail->get_error_message();
				$last_unavailable          = $avail;
				if ( ! self::allows_preflight_skip( $mode ) ) {
					return self::finish_error( $avail, $mode, $skipped );
				}
				// Only skip unavailable/unsupported before dispatch.
				if ( ! in_array( $code, array( 'rwga_transport_unavailable', 'rwga_transport_unsupported' ), true ) ) {
					return self::finish_error( $avail, $mode, $skipped );
				}
				continue;
			}

			$result = $transport->dispatch( $workflow_key, $request );
			if ( is_wp_error( $result ) ) {
				// Tier / entitlement gates are safe to fall through in automatic modes —
				// the remote call never started billable generation. Other post-dispatch
				// failures stay hard stops (do not silently switch engines mid-run).
				if ( self::allows_preflight_skip( $mode ) && self::is_fallback_eligible_error( $result ) ) {
					$skipped[ $transport_key ] = $result->get_error_message();
					$last_unavailable          = $result;
					continue;
				}
				return self::finish_error( $result, $mode, $skipped, $transport_key );
			}

			return self::finish_success( $result, $mode, $skipped, $transport_key );
		}

		if ( $last_unavailable instanceof WP_Error ) {
			return self::finish_error( $last_unavailable, $mode, $skipped );
		}

		return self::finish_error(
			new WP_Error(
				'rwga_transport_unavailable',
				__( 'No AI generation transport is available for this workflow. Connect WordPress AI, ReactWoo managed AI, or enable Local deterministic mode.', 'reactwoo-geo-ai' )
			),
			$mode,
			$skipped
		);
	}

	/**
	 * Whether the mode may skip unavailable transports before dispatch.
	 *
	 * @param string $mode Engine mode.
	 * @return bool
	 */
	private static function allows_preflight_skip( $mode ) {
		$mode = sanitize_key( (string) $mode );
		return in_array( $mode, array( 'automatic', 'remote_fallback' ), true );
	}

	/**
	 * Errors that mean “this transport cannot run” rather than a failed generation mid-flight.
	 *
	 * Includes Pro-tier gates from reactwoo-api (`This workflow requires pro tier or higher`).
	 *
	 * @param \WP_Error $error Error from availability or dispatch.
	 * @return bool
	 */
	public static function is_fallback_eligible_error( $error ) {
		if ( ! ( $error instanceof WP_Error ) ) {
			return false;
		}
		$code = $error->get_error_code();
		if ( in_array( $code, array( 'rwga_transport_unavailable', 'rwga_transport_unsupported' ), true ) ) {
			return true;
		}
		$data = $error->get_error_data();
		if ( is_array( $data ) && ( ! empty( $data['required_tier'] ) || ! empty( $data['min_tier'] ) ) ) {
			return true;
		}
		$msg = strtolower( $error->get_error_message() );
		if ( '' === $msg ) {
			return false;
		}
		if ( false !== strpos( $msg, 'requires' ) && false !== strpos( $msg, 'tier' ) ) {
			return true;
		}
		if ( false !== strpos( $msg, 'entitlement' ) || false !== strpos( $msg, 'not included in your plan' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Ordered transport keys for a mode.
	 *
	 * @param string $mode Engine mode.
	 * @return string[]
	 */
	public static function resolve_chain( $mode ) {
		$mode = sanitize_key( (string) $mode );
		switch ( $mode ) {
			case 'wordpress_ai':
				return array( 'wordpress_ai' );
			case 'managed':
			case 'remote':
				return array( 'managed' );
			case 'local':
				return array( 'local' );
			case 'remote_fallback':
				// Legacy: managed then local only — never silently prefer WordPress AI.
				return array( 'managed', 'local' );
			case 'automatic':
			default:
				if ( 'automatic' === $mode ) {
					return array( 'wordpress_ai', 'managed', 'local' );
				}
				// Unknown stored value: safest explicit local.
				return array( 'local' );
		}
	}

	/**
	 * @param string $key Transport key.
	 * @return RWGA_Generation_Transport|null
	 */
	private static function get_transport( $key ) {
		$key = sanitize_key( (string) $key );
		if ( is_array( self::$transport_overrides ) && isset( self::$transport_overrides[ $key ] ) ) {
			return self::$transport_overrides[ $key ];
		}

		switch ( $key ) {
			case 'wordpress_ai':
				return new RWGA_WordPress_AI_Transport();
			case 'managed':
				return new RWGA_Managed_AI_Transport();
			case 'local':
				return new RWGA_Local_AI_Transport();
			default:
				return null;
		}
	}

	/**
	 * @param array<string, mixed> $result        Envelope.
	 * @param string               $mode          Mode.
	 * @param array<string, string> $skipped      Skip map.
	 * @param string               $transport_key Selected.
	 * @return array<string, mixed>
	 */
	private static function finish_success( array $result, $mode, array $skipped, $transport_key ) {
		$meta = isset( $result['meta'] ) && is_array( $result['meta'] ) ? $result['meta'] : array();
		$fallback_reason = '';
		if ( ! empty( $skipped ) ) {
			$parts = array();
			foreach ( $skipped as $k => $reason ) {
				$parts[] = $k . ':' . $reason;
			}
			$fallback_reason = implode( '; ', $parts );
		}

		self::$last_meta = array(
			'transport'       => isset( $result['transport'] ) ? (string) $result['transport'] : $transport_key,
			'engine_source'   => isset( $meta['engine_source'] ) ? (string) $meta['engine_source'] : $transport_key,
			'provider'        => isset( $meta['provider'] ) ? (string) $meta['provider'] : '',
			'model'           => isset( $meta['model'] ) ? (string) $meta['model'] : '',
			'prompt_version'  => isset( $meta['prompt_version'] ) ? (string) $meta['prompt_version'] : '',
			'fallback_reason' => $fallback_reason,
			'remote_run_id'   => isset( $result['remote_run_id'] ) ? (string) $result['remote_run_id'] : '',
			'cache_hit'       => ! empty( $meta['cache_hit'] ),
			'mode'            => sanitize_key( (string) $mode ),
			'skipped'         => $skipped,
		);

		$result['meta'] = array_merge( $meta, self::$last_meta );
		return $result;
	}

	/**
	 * @param \WP_Error             $error         Error.
	 * @param string                $mode          Mode.
	 * @param array<string, string> $skipped       Skips.
	 * @param string|null           $transport_key Optional attempted transport.
	 * @return \WP_Error
	 */
	private static function finish_error( $error, $mode, array $skipped, $transport_key = null ) {
		self::$last_meta = array(
			'transport'       => $transport_key ? (string) $transport_key : '',
			'engine_source'   => '',
			'provider'        => '',
			'model'           => '',
			'prompt_version'  => '',
			'fallback_reason' => ! empty( $skipped ) ? wp_json_encode( $skipped ) : '',
			'remote_run_id'   => '',
			'cache_hit'       => false,
			'mode'            => sanitize_key( (string) $mode ),
			'skipped'         => $skipped,
			'error_code'      => $error instanceof WP_Error ? $error->get_error_code() : '',
		);
		return $error;
	}

	/**
	 * Read-only status for Advanced settings UI.
	 *
	 * @param string               $workflow_key Sample workflow for support checks.
	 * @param array<string, mixed> $request      Optional request (may include local_callback).
	 * @return array<string, mixed>
	 */
	public static function status_snapshot( $workflow_key = 'ux_analysis', array $request = array() ) {
		$mode = class_exists( 'RWGA_Engine', false ) ? RWGA_Engine::get_mode() : 'local';
		$wp   = self::get_transport( 'wordpress_ai' );
		$mg   = self::get_transport( 'managed' );
		$loc  = self::get_transport( 'local' );

		$wp_api = function_exists( 'wp_supports_ai' ) && function_exists( 'wp_ai_client_prompt' );
		$wp_ok  = false;
		$wp_msg = '';
		if ( $wp instanceof RWGA_Generation_Transport ) {
			$a = $wp->availability( $workflow_key, $request );
			if ( is_wp_error( $a ) ) {
				$wp_msg = $a->get_error_message();
			} else {
				$wp_ok = true;
			}
		}

		$mg_ok  = false;
		$mg_msg = '';
		if ( $mg instanceof RWGA_Generation_Transport ) {
			$a = $mg->availability( $workflow_key, $request );
			if ( is_wp_error( $a ) ) {
				$mg_msg = $a->get_error_message();
			} else {
				$mg_ok = true;
			}
		}

		$loc_ok = $loc instanceof RWGA_Generation_Transport && $loc->supports( $workflow_key, $request );

		$effective = '';
		foreach ( self::resolve_chain( $mode ) as $key ) {
			if ( 'wordpress_ai' === $key && $wp_ok ) {
				$effective = 'wordpress_ai';
				break;
			}
			if ( 'managed' === $key && $mg_ok ) {
				$effective = 'managed';
				break;
			}
			if ( 'local' === $key && $loc_ok ) {
				$effective = 'local';
				break;
			}
		}

		return array(
			'mode'                         => $mode,
			'public_mode'                  => class_exists( 'RWGA_Engine', false ) ? RWGA_Engine::get_public_mode() : $mode,
			'wordpress_ai_api_available'   => $wp_api,
			'wordpress_ai_provider_ready'  => $wp_ok,
			'wordpress_ai_message'         => $wp_msg,
			'managed_connected'            => $mg_ok,
			'managed_message'              => $mg_msg,
			'local_available'              => $loc_ok,
			'effective_transport'          => $effective,
			'chain'                        => self::resolve_chain( $mode ),
		);
	}
}
