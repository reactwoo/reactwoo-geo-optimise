<?php
/**
 * Advanced: slug / URL takeover (not exposed in UI until ordering + redirects are fully validated).
 *
 * WordPress disallows duplicate slugs: the control page must be renamed first, saved, then the
 * variant renamed to the original slug — never the reverse. Implementation belongs here when shipped.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Placeholder for Mode B (slug swap). Do not call from production UI until complete.
 */
class RWGO_Promotion_Slug_Scaffold {

	/**
	 * @param int   $experiment_post_id Experiment CPT ID.
	 * @param array $args               Context.
	 * @return true|\WP_Error
	 */
	public static function not_available( $experiment_post_id, array $args = array() ) {
		unset( $experiment_post_id, $args );
		return new \WP_Error(
			'rwgo_promotion_slug_mode_unavailable',
			__( 'Promoting the winning URL/slug is not available in this release. Use “Replace primary content” to keep your canonical URL stable.', 'reactwoo-geo-optimise' )
		);
	}
}
