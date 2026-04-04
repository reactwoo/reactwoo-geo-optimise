<?php
/**
 * Resolves which experiments / goals apply to the current front request.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Goal + handler registry for front-end localization.
 */
class RWGO_Goal_Registry {

	/**
	 * Build rwgoTracking localisation: experiments active on this page (control or variant URL).
	 *
	 * @param int $queried_post_id Current singular post ID.
	 * @return array<string, mixed>
	 */
	public static function build_frontend_config( $queried_post_id ) {
		$queried_post_id = (int) $queried_post_id;
		$experiments     = array();

		foreach ( RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 200 ) ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$cfg = RWGO_Experiment_Repository::get_config( $post->ID );
			if ( empty( $cfg['status'] ) || 'active' !== $cfg['status'] || empty( $cfg['experiment_key'] ) ) {
				continue;
			}
			$source = (int) ( $cfg['source_page_id'] ?? 0 );
			$match  = false;
			if ( $source === $queried_post_id ) {
				$match = true;
			} else {
				foreach ( isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : array() as $row ) {
					if ( is_array( $row ) && (int) ( $row['page_id'] ?? 0 ) === $queried_post_id ) {
						$match = true;
						break;
					}
				}
			}
			if ( ! $match ) {
				continue;
			}
			$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
			if ( empty( $goals ) ) {
				$goals = array(
					array(
						'goal_id'   => 'goal_default_pageview',
						'goal_type' => 'page_view',
						'label'     => __( 'Page view', 'reactwoo-geo-optimise' ),
						'handlers'  => array(),
					),
				);
			}
			$resolved_variant = class_exists( 'RWGO_Experiment_Service', false )
				? RWGO_Experiment_Service::resolve_variant_for_context( $cfg, $queried_post_id )
				: '';

			$experiments[] = array(
				'experimentId'     => (int) $post->ID,
				'experimentKey'    => (string) $cfg['experiment_key'],
				'sourcePageId'     => $source,
				'goals'            => $goals,
				'resolvedVariant'  => (string) $resolved_variant,
			);
		}

		$strict = class_exists( 'RWGO_Settings', false ) && RWGO_Settings::strict_binding_mode_enabled();

		return array(
			'pageContextId'  => $queried_post_id,
			'experiments'    => $experiments,
			'strictBinding'  => $strict,
		);
	}
}
