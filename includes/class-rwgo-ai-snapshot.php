<?php
/**
 * Geo Optimise rows for Geo Core AI site intelligence snapshot.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends compact `geo_optimise` metadata via {@see rwgc_ai_snapshot_payload}.
 */
class RWGO_AI_Snapshot {

	const MAX_EXPERIMENT_ROWS = 35;

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_ai_snapshot_payload', array( __CLASS__, 'append_optimise_metadata' ), 20, 2 );
		add_filter( 'rwgc_ai_snapshot_relationships', array( __CLASS__, 'append_experiment_relationships' ), 20, 1 );
	}

	/**
	 * @param array<string, mixed> $payload Snapshot payload.
	 * @param array<string, mixed> $context Builder context.
	 * @return array<string, mixed>
	 */
	public static function append_optimise_metadata( array $payload, array $context = array() ) {
		unset( $context );

		if ( ! class_exists( 'RWGC_AI_Snapshot_Schema', false ) || ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
			return $payload;
		}

		$block = array(
			'active'       => true,
			'version'      => defined( 'RWGO_VERSION' ) ? (string) RWGO_VERSION : '',
			'counts'       => array(
				'total'  => RWGO_Experiment_Repository::count_all(),
				'active' => RWGO_Experiment_Repository::count_by_status( 'active' ),
				'draft'  => RWGO_Experiment_Repository::count_by_status( 'draft' ),
				'paused' => RWGO_Experiment_Repository::count_by_status( 'paused' ),
			),
			'experiments'  => self::collect_experiments(),
		);

		/**
		 * Filter Geo Optimise block appended to the Geo AI site intelligence snapshot.
		 *
		 * @param array<string, mixed> $block   Optimise metadata block.
		 * @param array<string, mixed> $payload Full snapshot before normalization.
		 */
		$block = apply_filters( 'rwgo_ai_snapshot_block', $block, $payload );

		$payload['geo_optimise'] = is_array( $block ) ? $block : array();

		return $payload;
	}

	/**
	 * @param array<int, array<string, mixed>> $rels Existing relationship rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function append_experiment_relationships( array $rels ) {
		if ( ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
			return $rels;
		}

		foreach ( RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => self::MAX_EXPERIMENT_ROWS ) ) as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$cfg = RWGO_Experiment_Repository::get_config( $post->ID );
			$source = isset( $cfg['source_page_id'] ) ? absint( $cfg['source_page_id'] ) : 0;
			if ( $source <= 0 ) {
				continue;
			}
			$rels[] = array(
				'type'      => 'tests',
				'from_type' => 'experiment',
				'from_id'   => (string) $post->ID,
				'to_type'   => 'page',
				'to_id'     => (string) $source,
				'meta'      => array(
					'status'    => isset( $cfg['status'] ) ? sanitize_key( (string) $cfg['status'] ) : '',
					'test_type' => isset( $cfg['test_type'] ) ? sanitize_key( (string) $cfg['test_type'] ) : '',
				),
			);
		}

		return $rels;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_experiments() {
		$rows = array();
		foreach ( RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => self::MAX_EXPERIMENT_ROWS ) ) as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$cfg = RWGO_Experiment_Repository::get_config( $post->ID );
			$key = isset( $cfg['experiment_key'] ) ? sanitize_key( (string) $cfg['experiment_key'] ) : '';
			$rows[] = array(
				'experiment_id'   => (int) $post->ID,
				'experiment_key'  => $key,
				'name'            => sanitize_text_field( get_the_title( $post ) ),
				'status'          => isset( $cfg['status'] ) ? sanitize_key( (string) $cfg['status'] ) : 'draft',
				'test_type'       => isset( $cfg['test_type'] ) ? sanitize_key( (string) $cfg['test_type'] ) : '',
				'source_page_id'  => isset( $cfg['source_page_id'] ) ? absint( $cfg['source_page_id'] ) : 0,
				'goal_type'       => isset( $cfg['goal_type'] ) ? sanitize_key( (string) $cfg['goal_type'] ) : '',
				'targeting_mode'  => isset( $cfg['targeting']['mode'] ) ? sanitize_key( (string) $cfg['targeting']['mode'] ) : '',
			);
			if ( count( $rows ) >= self::MAX_EXPERIMENT_ROWS ) {
				break;
			}
		}
		return $rows;
	}
}
