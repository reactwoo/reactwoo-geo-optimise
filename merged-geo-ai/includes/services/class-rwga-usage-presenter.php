<?php
/**
 * Package / usage presentation for Geo AI (tokens are authoritative; workflow counts are guidance).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds UI-friendly usage summaries from cached API usage + optional JWT hints.
 */
class RWGA_Usage_Presenter {

	/**
	 * Default soft estimates when license package does not override (Pro / Enterprise examples).
	 *
	 * @param string $tier free|pro|enterprise
	 * @return array<string, int>
	 */
	private static function default_display_estimates_for_tier( $tier ) {
		$tier = sanitize_key( (string) $tier );
		if ( 'enterprise' === $tier ) {
			return array(
				'ux_analysis'         => 120,
				'competitor_research' => 40,
				'copy_implement'      => 200,
				'geo_personalise'     => 80,
			);
		}
		return array(
			'ux_analysis'         => 25,
			'competitor_research' => 10,
			'copy_implement'      => 40,
			'geo_personalise'     => 10,
		);
	}

	/**
	 * @param array<string, mixed>|null $cache {@see RWGA_Usage::get_cache()}.
	 * @return array<string, mixed>
	 */
	public static function build_package_summary( $cache ) {
		require_once RWGA_PATH . 'includes/helpers/rwga-usage-estimates.php';

		$tier = isset( $cache['assistant_tier'] ) ? sanitize_key( (string) $cache['assistant_tier'] ) : '';
		if ( '' === $tier && isset( $cache['license_tier'] ) ) {
			$tier = sanitize_key( (string) $cache['license_tier'] );
		}
		if ( '' === $tier || 'unknown' === $tier ) {
			$tier = 'unknown';
		}

		$used      = isset( $cache['used'] ) ? (int) $cache['used'] : 0;
		$limit     = isset( $cache['limit'] ) ? (int) $cache['limit'] : 0;
		$remaining = isset( $cache['remaining'] ) ? (int) $cache['remaining'] : max( 0, $limit - $used );
		$pct       = ( $limit > 0 ) ? min( 100, round( 100 * $used / $limit ) ) : 0;

		$from_api = array();
		if ( isset( $cache['product_context'] ) && is_array( $cache['product_context'] ) ) {
			$pc = $cache['product_context'];
			if ( ! empty( $pc['display_usage_estimates'] ) && is_array( $pc['display_usage_estimates'] ) ) {
				$from_api = $pc['display_usage_estimates'];
			}
		}
		if ( 'unknown' === $tier ) {
			$estimates = ! empty( $from_api ) ? $from_api : array();
		} else {
			$estimates = ! empty( $from_api ) ? $from_api : self::default_display_estimates_for_tier( $tier );
		}

		$hints = rwga_get_workflow_usage_hints();
		$soft  = array();
		foreach ( $estimates as $wk => $est_count ) {
			$wk = sanitize_key( (string) $wk );
			if ( '' === $wk || ! isset( $hints[ $wk ] ) ) {
				continue;
			}
			$label = $hints[ $wk ]['label'];
			$n     = max( 0, (int) $est_count );
			$soft[ $wk ] = array(
				'label'           => $label,
				'estimated_total' => $n,
				'note'            => __( 'Guidance only', 'reactwoo-geo-ai' ),
			);
		}

		return array(
			'assistant_tier'   => $tier,
			'tokens_used'      => $used,
			'tokens_limit'     => $limit,
			'tokens_remaining' => $remaining,
			'usage_percent'    => $pct,
			'soft_quotas'      => $soft,
			'messaging'        => array(
				'headline' => __( 'Usage is measured by AI tokens.', 'reactwoo-geo-ai' ),
				'note'     => __( 'Displayed workflow counts are estimates only and may vary by page size and workflow complexity. Some workflows may run locally or use hybrid processing.', 'reactwoo-geo-ai' ),
			),
		);
	}
}
