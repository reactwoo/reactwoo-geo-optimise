<?php
/**
 * Task-class model routing hints for remote workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves model tier and provider hints; API may override server-side.
 */
class RWGA_Model_Router {

	const VERSION = '1.0.0';

	/**
	 * @param string $workflow_key Workflow slug.
	 * @return array<string, mixed>
	 */
	public static function resolve( $workflow_key ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		$tier         = self::tier_for_workflow( $workflow_key );

		$route = array(
			'router_version' => self::VERSION,
			'workflow_key'   => $workflow_key,
			'tier'           => $tier,
			'engine'         => 'deterministic' === $tier ? 'deterministic' : 'llm',
			'provider_hint'  => self::provider_hint( $tier ),
			'model_hint'     => self::model_hint( $tier ),
			'prompt_version' => class_exists( 'RWGA_Local_Intelligence', false )
				? RWGA_Local_Intelligence::PROMPT_VERSION
				: '1.0.0',
		);

		/**
		 * @param array<string, mixed> $route        Resolved route metadata.
		 * @param string               $workflow_key Workflow key.
		 */
		$route = apply_filters( 'rwga_model_route', $route, $workflow_key );
		return is_array( $route ) ? $route : array();
	}

	/**
	 * Remote-safe routing block for workflow payload.
	 *
	 * @param array<string, mixed> $route Route from resolve().
	 * @return array<string, mixed>
	 */
	public static function for_api( array $route ) {
		return array(
			'router_version' => isset( $route['router_version'] ) ? (string) $route['router_version'] : self::VERSION,
			'tier'           => isset( $route['tier'] ) ? sanitize_key( (string) $route['tier'] ) : 'light',
			'engine'         => isset( $route['engine'] ) ? sanitize_key( (string) $route['engine'] ) : 'llm',
			'provider_hint'  => isset( $route['provider_hint'] ) ? sanitize_key( (string) $route['provider_hint'] ) : 'openai',
			'model_hint'     => isset( $route['model_hint'] ) ? sanitize_text_field( (string) $route['model_hint'] ) : '',
			'prompt_version' => isset( $route['prompt_version'] ) ? sanitize_text_field( (string) $route['prompt_version'] ) : '1.0.0',
		);
	}

	/**
	 * @param string $workflow_key Workflow key.
	 * @return string premium|light|deterministic
	 */
	private static function tier_for_workflow( $workflow_key ) {
		$premium = array(
			'ux_analysis',
			'ux_recommend',
			'competitor_research',
			'site_audit',
			'optimisation_recommendation',
		);
		$deterministic = array(
			'rule_debug',
			'popup_fire_debug',
			'variant_relationship_audit',
			'tracking_gap_audit',
			'rule_explain',
		);

		if ( in_array( $workflow_key, $premium, true ) ) {
			return 'premium';
		}
		if ( in_array( $workflow_key, $deterministic, true ) ) {
			return 'deterministic';
		}
		return 'light';
	}

	/**
	 * @param string $tier Route tier.
	 * @return string
	 */
	private static function provider_hint( $tier ) {
		if ( 'premium' === $tier ) {
			return 'anthropic';
		}
		if ( 'deterministic' === $tier ) {
			return 'internal';
		}
		return 'openai';
	}

	/**
	 * @param string $tier Route tier.
	 * @return string
	 */
	private static function model_hint( $tier ) {
		if ( 'premium' === $tier ) {
			return 'claude-sonnet-4-20250514';
		}
		if ( 'deterministic' === $tier ) {
			return 'deterministic';
		}
		return 'gpt-4o-mini';
	}
}
