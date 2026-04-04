<?php
/**
 * Read/write experiments (rwgo_experiment CPT + meta).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Experiment persistence.
 */
class RWGO_Experiment_Repository {

	const META_KEY                = '_rwgo_config';
	const LEGACY_OPTION_SNAPSHOT  = 'rwgo_experiment_variant_counts';

	/**
	 * Full config blob (JSON in meta) keys:
	 * experiment_key, status, test_type, source_page_id, builder_type,
	 * variants[], targeting, goals[], traffic_weights, created_gmt, updated_gmt.
	 *
	 * @param int $post_id Experiment post ID.
	 * @return array<string, mixed>
	 */
	public static function get_config( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$dec = json_decode( $raw, true );
			if ( is_array( $dec ) ) {
				return $dec;
			}
		}
		return array();
	}

	/**
	 * @param int                  $post_id Experiment post ID.
	 * @param array<string, mixed> $config  Partial or full config (merged).
	 * @return bool
	 */
	public static function save_config( $post_id, array $config ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		$prev      = self::get_config( $post_id );
		$merged    = array_merge( $prev, $config );
		$merged['updated_gmt'] = gmdate( 'c' );
		if ( empty( $merged['created_gmt'] ) ) {
			$merged['created_gmt'] = $merged['updated_gmt'];
		}
		return (bool) update_post_meta( $post_id, self::META_KEY, wp_json_encode( $merged ) );
	}

	/**
	 * @param array<string, mixed> $args WP_Query args overrides.
	 * @return array<int, \WP_Post>
	 */
	public static function query_experiments( $args = array() ) {
		$defaults = array(
			'post_type'      => RWGO_Experiment_CPT::POST_TYPE,
			'post_status'    => array( 'draft', 'publish', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		$q = new \WP_Query( array_merge( $defaults, $args ) );
		return $q->posts;
	}

	/**
	 * Active experiments targeting a source page (published + status active).
	 *
	 * @param int $source_page_id Post ID of control URL.
	 * @return array<int, array<string, mixed>> List of [ 'post' => WP_Post, 'config' => array ].
	 */
	public static function get_active_for_source_page( $source_page_id ) {
		$source_page_id = (int) $source_page_id;
		$out            = array();
		foreach ( self::query_experiments( array( 'posts_per_page' => 500 ) ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$cfg = self::get_config( $post->ID );
			if ( empty( $cfg['status'] ) || 'active' !== $cfg['status'] ) {
				continue;
			}
			if ( (int) ( $cfg['source_page_id'] ?? 0 ) !== $source_page_id ) {
				continue;
			}
			$out[] = array(
				'post'   => $post,
				'config' => $cfg,
			);
		}
		return $out;
	}

	/**
	 * Active experiments whose control or any variant page matches the post ID (e.g. product page in a test).
	 *
	 * @param int $page_id Post ID.
	 * @return array<int, array{post: \WP_Post, config: array<string, mixed>}>
	 */
	public static function get_active_touching_page( $page_id ) {
		$page_id = (int) $page_id;
		$out     = array();
		if ( $page_id <= 0 ) {
			return $out;
		}
		foreach ( self::query_experiments( array( 'posts_per_page' => 500 ) ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$cfg = self::get_config( $post->ID );
			if ( empty( $cfg['status'] ) || 'active' !== $cfg['status'] ) {
				continue;
			}
			if ( ! self::config_touches_page_id( $cfg, $page_id ) ) {
				continue;
			}
			$out[] = array(
				'post'   => $post,
				'config' => $cfg,
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $cfg     Experiment config.
	 * @param int                    $page_id Post ID.
	 * @return bool
	 */
	public static function config_touches_page_id( array $cfg, $page_id ) {
		$page_id = (int) $page_id;
		if ( (int) ( $cfg['source_page_id'] ?? 0 ) === $page_id ) {
			return true;
		}
		foreach ( isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : array() as $row ) {
			if ( is_array( $row ) && (int) ( $row['page_id'] ?? 0 ) === $page_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $experiment_key Sanitized key.
	 * @return \WP_Post|null
	 */
	public static function find_by_experiment_key( $experiment_key ) {
		$key = sanitize_key( (string) $experiment_key );
		if ( '' === $key ) {
			return null;
		}
		foreach ( self::query_experiments( array( 'posts_per_page' => 500 ) ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$cfg = self::get_config( $post->ID );
			if ( isset( $cfg['experiment_key'] ) && sanitize_key( (string) $cfg['experiment_key'] ) === $key ) {
				return $post;
			}
		}
		return null;
	}

	/**
	 * Count experiments whose config status matches.
	 *
	 * @param string $status draft|active|paused|completed.
	 * @return int
	 */
	public static function count_by_status( $status ) {
		$status = (string) $status;
		$n      = 0;
		foreach ( self::query_experiments( array( 'posts_per_page' => 500 ) ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$cfg = self::get_config( $post->ID );
			if ( (string) ( $cfg['status'] ?? '' ) === $status ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Total managed tests (any status).
	 *
	 * @return int
	 */
	public static function count_all() {
		$q = new \WP_Query(
			array(
				'post_type'      => RWGO_Experiment_CPT::POST_TYPE,
				'post_status'    => array( 'draft', 'publish', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		return (int) $q->found_posts;
	}
}
