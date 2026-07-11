<?php
/**
 * WordPress 7 AI Client transport (BYOK via site providers).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uses public wp_supports_ai / wp_ai_client_prompt APIs only.
 *
 * No load-time references to WordPress 7 AI classes (safe on WP 6.x).
 */
class RWGA_WordPress_AI_Transport implements RWGA_Generation_Transport {

	/**
	 * Optional test/fake prompt executor: function( string $user, array $spec ): string|\WP_Error
	 *
	 * @var callable|null
	 */
	public static $test_prompt_executor = null;

	/**
	 * @return string
	 */
	public function get_key() {
		return 'wordpress_ai';
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request.
	 * @return bool
	 */
	public function supports( $workflow_key, array $request ) {
		unset( $request );
		return class_exists( 'RWGA_Workflow_Prompt_Spec_Registry', false )
			&& RWGA_Workflow_Prompt_Spec_Registry::supports_wordpress_ai( $workflow_key );
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request.
	 * @return true|\WP_Error
	 */
	public function availability( $workflow_key, array $request ) {
		if ( ! $this->supports( $workflow_key, $request ) ) {
			return new WP_Error(
				'rwga_transport_unsupported',
				sprintf(
					/* translators: %s: workflow key */
					__( 'WordPress AI does not support the “%s” workflow yet.', 'reactwoo-geo-ai' ),
					sanitize_key( (string) $workflow_key )
				)
			);
		}

		if ( is_callable( self::$test_prompt_executor ) ) {
			return true;
		}

		if ( ! function_exists( 'wp_supports_ai' ) || ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'The WordPress AI Client API is not available on this site. Use Automatic, ReactWoo managed AI, or Local — or upgrade to WordPress 7 with a connected AI provider.', 'reactwoo-geo-ai' )
			);
		}

		if ( ! wp_supports_ai() ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'WordPress AI is disabled or unsupported in this environment.', 'reactwoo-geo-ai' )
			);
		}

		$probe = $this->probe_text_generation_support();
		if ( is_wp_error( $probe ) ) {
			return $probe;
		}

		return true;
	}

	/**
	 * Soft preflight: builder reports text generation support when a capable provider exists.
	 *
	 * @return true|\WP_Error
	 */
	private function probe_text_generation_support() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'The WordPress AI Client API is not available on this site.', 'reactwoo-geo-ai' )
			);
		}

		try {
			$builder = wp_ai_client_prompt( 'ping' );
		} catch ( Exception $e ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'WordPress AI prompt builder could not be created.', 'reactwoo-geo-ai' )
			);
		}

		if ( ! is_object( $builder ) || ! method_exists( $builder, 'is_supported_for_text_generation' ) ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'WordPress AI prompt builder is missing text-generation support checks.', 'reactwoo-geo-ai' )
			);
		}

		$supported = $builder->is_supported_for_text_generation();
		if ( false === $supported ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'No capable WordPress AI provider is available for text generation. Connect a provider under WordPress settings.', 'reactwoo-geo-ai' )
			);
		}

		return true;
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function dispatch( $workflow_key, array $request ) {
		$avail = $this->availability( $workflow_key, $request );
		if ( is_wp_error( $avail ) ) {
			return $avail;
		}

		$spec = RWGA_Workflow_Prompt_Spec_Registry::get_spec( $workflow_key );
		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$user_prompt = class_exists( 'RWGA_Prompt_Context_Formatter', false )
			? RWGA_Prompt_Context_Formatter::format_user_prompt( $workflow_key, $request )
			: '';

		if ( '' === trim( $user_prompt ) ) {
			return new WP_Error(
				'rwga_generation_failed',
				__( 'Could not build a WordPress AI prompt from page context.', 'reactwoo-geo-ai' )
			);
		}

		$raw = $this->generate_json_text( $user_prompt, $spec );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$decoded = $this->decode_json_object( $raw );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		return array(
			'transport'       => 'wordpress_ai',
			'engine_response' => $decoded,
			'remote_run_id'   => null,
			'usage'           => array(),
			'meta'            => array(
				'engine_source'  => 'wordpress_ai',
				'provider'       => 'wordpress_ai',
				'model'          => '',
				'prompt_version' => isset( $spec['prompt_version'] ) ? (string) $spec['prompt_version'] : '',
				'cache_hit'      => false,
			),
		);
	}

	/**
	 * @param string               $user_prompt User prompt.
	 * @param array<string, mixed> $spec        Prompt spec.
	 * @return string|\WP_Error Raw model text.
	 */
	private function generate_json_text( $user_prompt, array $spec ) {
		if ( is_callable( self::$test_prompt_executor ) ) {
			$out = call_user_func( self::$test_prompt_executor, $user_prompt, $spec );
			if ( is_wp_error( $out ) ) {
				return $out;
			}
			return is_string( $out ) ? $out : (string) $out;
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'The WordPress AI Client API is not available on this site.', 'reactwoo-geo-ai' )
			);
		}

		try {
			$builder = wp_ai_client_prompt( $user_prompt );
			if ( ! is_object( $builder ) ) {
				return new WP_Error(
					'rwga_generation_failed',
					__( 'WordPress AI prompt builder returned an unexpected value.', 'reactwoo-geo-ai' )
				);
			}

			if ( method_exists( $builder, 'using_system_instruction' ) && ! empty( $spec['system'] ) ) {
				$builder = $builder->using_system_instruction( (string) $spec['system'] );
			}
			if ( method_exists( $builder, 'using_temperature' ) && isset( $spec['temperature'] ) ) {
				$builder = $builder->using_temperature( (float) $spec['temperature'] );
			}
			if ( method_exists( $builder, 'using_max_tokens' ) && isset( $spec['max_tokens'] ) ) {
				$builder = $builder->using_max_tokens( (int) $spec['max_tokens'] );
			}
			if ( method_exists( $builder, 'as_json_response' ) && ! empty( $spec['schema'] ) && is_array( $spec['schema'] ) ) {
				$builder = $builder->as_json_response( $spec['schema'] );
			}

			if ( method_exists( $builder, 'is_supported_for_text_generation' ) && false === $builder->is_supported_for_text_generation() ) {
				return new WP_Error(
					'rwga_transport_unavailable',
					__( 'No capable WordPress AI provider is available for text generation.', 'reactwoo-geo-ai' )
				);
			}

			if ( ! method_exists( $builder, 'generate_text' ) ) {
				return new WP_Error(
					'rwga_generation_failed',
					__( 'WordPress AI prompt builder does not expose generate_text().', 'reactwoo-geo-ai' )
				);
			}

			$result = $builder->generate_text();
		} catch ( Exception $e ) {
			return new WP_Error(
				'rwga_generation_failed',
				__( 'WordPress AI generation failed.', 'reactwoo-geo-ai' )
			);
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'rwga_generation_failed',
				$result->get_error_message()
			);
		}

		if ( ! is_string( $result ) || '' === trim( $result ) ) {
			return new WP_Error(
				'rwga_generation_invalid_response',
				__( 'WordPress AI returned an empty response.', 'reactwoo-geo-ai' )
			);
		}

		return $result;
	}

	/**
	 * Strict JSON object decode.
	 *
	 * @param string $raw Raw text.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function decode_json_object( $raw ) {
		$raw = trim( (string) $raw );
		// Tolerate fenced JSON from some providers.
		if ( preg_match( '/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}

		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error(
				'rwga_generation_invalid_response',
				__( 'WordPress AI returned invalid JSON for this workflow.', 'reactwoo-geo-ai' )
			);
		}

		// Require object (associative), not a bare list.
		if ( array_values( $decoded ) === $decoded ) {
			return new WP_Error(
				'rwga_generation_invalid_response',
				__( 'WordPress AI returned a JSON array instead of the expected object.', 'reactwoo-geo-ai' )
			);
		}

		return $decoded;
	}
}
