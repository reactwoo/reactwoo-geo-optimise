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
	/**
	 * Canonical page binding snapshot for persistence (alias for resolver output).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function build_page_binding_snapshot( $post_id ) {
		if ( ! class_exists( 'RWGO_Page_Binding_Resolver', false ) ) {
			return array();
		}
		return RWGO_Page_Binding_Resolver::snapshot_for_post( (int) $post_id );
	}

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
			$cfg = self::normalize_page_bindings( $cfg, $post->ID, false );
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
			$cfg = self::normalize_page_bindings( $cfg, $post->ID, false );
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
	 * Add source_page + per-variant post_name / relative_path / post_type from live posts (call on create/update).
	 *
	 * @param array<string, mixed> $config Experiment config.
	 * @return array<string, mixed>
	 */
	public static function enrich_config_with_page_snapshots( array $config ) {
		if ( ! class_exists( 'RWGO_Page_Binding_Resolver', false ) ) {
			return $config;
		}
		$src = (int) ( $config['source_page_id'] ?? 0 );
		if ( $src > 0 ) {
			$snap = RWGO_Page_Binding_Resolver::snapshot_for_post( $src );
			if ( ! empty( $snap ) ) {
				$config['source_page'] = $snap;
			}
		}
		if ( empty( $config['variants'] ) || ! is_array( $config['variants'] ) ) {
			return $config;
		}
		foreach ( $config['variants'] as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pid = (int) ( $row['page_id'] ?? 0 );
			if ( $pid <= 0 ) {
				continue;
			}
			$snap = RWGO_Page_Binding_Resolver::snapshot_for_post( $pid );
			if ( ! empty( $snap ) ) {
				$config['variants'][ $i ] = array_merge( $row, $snap );
			}
		}
		return $config;
	}

	/**
	 * Heal stale source_page_id / variants[].page_id using stored locators; optionally persist.
	 *
	 * @param array<string, mixed> $cfg                 Experiment config.
	 * @param int                  $experiment_post_id  Experiment CPT id (0 = in-memory only, no save).
	 * @param bool                 $persist             Save meta when IDs or snapshots change.
	 * @return array<string, mixed>
	 */
	public static function normalize_page_bindings( array $cfg, $experiment_post_id = 0, $persist = false ) {
		if ( ! class_exists( 'RWGO_Page_Binding_Resolver', false ) ) {
			return $cfg;
		}
		$experiment_post_id = (int) $experiment_post_id;
		$out                = $cfg;

		$src_binding = isset( $out['source_page'] ) && is_array( $out['source_page'] ) ? $out['source_page'] : array();
		$src_binding['page_id'] = (int) ( $out['source_page_id'] ?? $src_binding['page_id'] ?? 0 );
		$new_src = RWGO_Page_Binding_Resolver::resolve_post_id( $src_binding );
		if ( $new_src > 0 ) {
			$legacy_home = self::infer_legacy_homepage_source_id( $out, $src_binding, $new_src );
			if ( $legacy_home > 0 && $legacy_home !== $new_src ) {
				self::log_binding_heal( $experiment_post_id, 'source_page_id', $new_src, $legacy_home );
				$new_src = $legacy_home;
			}
		}
		if ( $new_src > 0 && (int) ( $out['source_page_id'] ?? 0 ) !== $new_src ) {
			self::log_binding_heal( $experiment_post_id, 'source_page_id', (int) ( $out['source_page_id'] ?? 0 ), $new_src );
			$out['source_page_id'] = $new_src;
		}
		if ( $new_src > 0 ) {
			$out['source_page'] = RWGO_Page_Binding_Resolver::snapshot_for_post( $new_src );
		}

		$vars = isset( $out['variants'] ) && is_array( $out['variants'] ) ? $out['variants'] : array();
		foreach ( $vars as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$bind = $row;
			$bind['page_id'] = (int) ( $row['page_id'] ?? 0 );
			$new_pid         = RWGO_Page_Binding_Resolver::resolve_post_id( $bind );
			$vid             = isset( $row['variant_id'] ) ? sanitize_key( (string) $row['variant_id'] ) : '';
			if ( $new_pid > 0 && (int) ( $row['page_id'] ?? 0 ) !== $new_pid ) {
				self::log_binding_heal( $experiment_post_id, 'variant:' . $vid, (int) ( $row['page_id'] ?? 0 ), $new_pid );
				$vars[ $i ]['page_id'] = $new_pid;
			}
			$use_id = $new_pid > 0 ? $new_pid : (int) ( $row['page_id'] ?? 0 );
			if ( $use_id > 0 ) {
				$snap = RWGO_Page_Binding_Resolver::snapshot_for_post( $use_id );
				if ( ! empty( $snap ) ) {
					$vars[ $i ] = array_merge( $vars[ $i ], $snap );
				}
			}
		}
		$out['variants'] = $vars;

		$sig_before = wp_json_encode( self::binding_signature( $cfg ) );
		$sig_after  = wp_json_encode( self::binding_signature( $out ) );
		if ( $persist && $experiment_post_id > 0 && $sig_before !== $sig_after ) {
			self::save_config( $experiment_post_id, $out );
		}

		return $out;
	}

	/**
	 * Legacy migration fallback for old tests that were created against a stale "Home" clone.
	 * Applies only when snapshot metadata is missing and experiment key still points to old source ID.
	 *
	 * @param array<string, mixed> $cfg         Experiment config.
	 * @param array<string, mixed> $src_binding Source binding row.
	 * @param int                  $resolved_src Source resolved by standard resolver.
	 * @return int Front page ID or 0.
	 */
	private static function infer_legacy_homepage_source_id( array $cfg, array $src_binding, $resolved_src ) {
		$resolved_src  = (int) $resolved_src;
		$show_on_front = (string) get_option( 'show_on_front', 'posts' );
		$page_on_front = (int) get_option( 'page_on_front', 0 );
		if ( 'page' !== $show_on_front || $page_on_front <= 0 || $resolved_src <= 0 || $resolved_src === $page_on_front ) {
			return 0;
		}

		$binding_rel = isset( $src_binding['relative_path'] ) ? '/' . trim( (string) $src_binding['relative_path'], '/' ) : '';
		$binding_rel = '/' === $binding_rel ? '/' : untrailingslashit( $binding_rel );
		$binding_slug = isset( $src_binding['post_name'] ) ? sanitize_key( (string) $src_binding['post_name'] ) : '';
		$binding_points_home = ( '/' === $binding_rel || '' === $binding_rel )
			|| in_array( $binding_rel, array( '/home', '/homepage', '/home-page' ), true )
			|| in_array( $binding_slug, array( 'home', 'homepage', 'home-page' ), true );
		if ( ! empty( $src_binding['is_front_page'] ) ) {
			$binding_points_home = true;
		}

		$key = isset( $cfg['experiment_key'] ) ? sanitize_key( (string) $cfg['experiment_key'] ) : '';
		if ( '' === $key || false === strpos( $key, 'rwgo_page_' . $resolved_src . '_ab_' ) ) {
			return 0;
		}

		$src = get_post( $resolved_src );
		$fp  = get_post( $page_on_front );
		if ( ! $src instanceof \WP_Post || ! $fp instanceof \WP_Post || 'page' !== $src->post_type || 'page' !== $fp->post_type || 'trash' === $src->post_status || 'trash' === $fp->post_status ) {
			return 0;
		}

		$name  = sanitize_key( (string) $src->post_name );
		$title = strtolower( sanitize_text_field( (string) $src->post_title ) );
		$looks_like_home = in_array( $name, array( 'home', 'homepage', 'home-page' ), true )
			|| false !== strpos( $title, 'home' );
		$same_title = strtolower( trim( (string) $src->post_title ) ) === strtolower( trim( (string) $fp->post_title ) );
		$src_url    = get_permalink( $resolved_src );
		$fp_url     = get_permalink( $page_on_front );
		$same_path  = false;
		if ( is_string( $src_url ) && is_string( $fp_url ) && '' !== $src_url && '' !== $fp_url ) {
			$sp = wp_parse_url( $src_url, PHP_URL_PATH );
			$fp = wp_parse_url( $fp_url, PHP_URL_PATH );
			$same_path = is_string( $sp ) && is_string( $fp ) && untrailingslashit( $sp ) === untrailingslashit( $fp );
		}
		if ( ! $looks_like_home && ! $same_title && ! $same_path && ! $binding_points_home ) {
			self::log_binding_skipped(
				$cfg,
				sprintf(
					'legacy-home fallback skipped: src=%d fp=%d no home/same-title/same-path/binding-home signal',
					$resolved_src,
					$page_on_front
				)
			);
			return 0;
		}
		self::log_binding_skipped(
			$cfg,
			sprintf(
				'legacy-home fallback selected: src=%d -> fp=%d (looks_like_home=%s same_title=%s same_path=%s)',
				$resolved_src,
				$page_on_front,
				$looks_like_home ? '1' : '0',
				$same_title ? '1' : '0',
				$same_path ? '1' : '0'
			)
		);

		return $page_on_front;
	}

	/**
	 * Debug-only diagnostic line for skipped/selected legacy fallback.
	 *
	 * @param array<string, mixed> $cfg     Experiment config.
	 * @param string               $message Detail message.
	 * @return void
	 */
	private static function log_binding_skipped( array $cfg, $message ) {
		$log = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			|| ( defined( 'RWGO_PAGE_BINDING_LOG' ) && RWGO_PAGE_BINDING_LOG );
		if ( ! $log ) {
			return;
		}
		$key = isset( $cfg['experiment_key'] ) ? sanitize_key( (string) $cfg['experiment_key'] ) : '';
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug.
		error_log( '[RWGO] ' . $message . ' key=' . $key );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return array<string, mixed>
	 */
	private static function binding_signature( array $cfg ) {
		$sig = array(
			'source_page_id' => (int) ( $cfg['source_page_id'] ?? 0 ),
			'v'              => array(),
		);
		foreach ( isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : array() as $row ) {
			if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
				continue;
			}
			$sig['v'][ sanitize_key( (string) $row['variant_id'] ) ] = (int) ( $row['page_id'] ?? 0 );
		}
		ksort( $sig['v'] );
		return $sig;
	}

	/**
	 * @param int    $experiment_post_id Experiment CPT id (0 if unknown).
	 * @param string $context             Field label.
	 * @param int    $old_id              Previous ID.
	 * @param int    $new_id              Resolved ID.
	 * @return void
	 */
	private static function log_binding_heal( $experiment_post_id, $context, $old_id, $new_id ) {
		if ( (int) $old_id === (int) $new_id ) {
			return;
		}
		$log = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			|| ( defined( 'RWGO_PAGE_BINDING_LOG' ) && RWGO_PAGE_BINDING_LOG );
		if ( ! $log ) {
			return;
		}
		$eid = (int) $experiment_post_id;
		if ( 0 === strpos( (string) $context, 'variant:' ) ) {
			$vid = substr( (string) $context, strlen( 'variant:' ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug.
			error_log(
				sprintf(
					'[RWGO] Resynced experiment %d variant %s page_id from %d to %d',
					$eid,
					$vid,
					(int) $old_id,
					(int) $new_id
				)
			);
			return;
		}
		if ( 'source_page_id' === (string) $context ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug.
			error_log(
				sprintf(
					'[RWGO] Resynced experiment %d source_page_id from %d to %d',
					$eid,
					(int) $old_id,
					(int) $new_id
				)
			);
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug.
		error_log(
			sprintf(
				'[RWGO] Healed page binding (experiment_post_id=%d, %s): %d -> %d',
				$eid,
				$context,
				(int) $old_id,
				(int) $new_id
			)
		);
	}

	/**
	 * Loop all experiments and normalize bindings (recovery after import/staging).
	 *
	 * @return array{scanned: int, updated: int, source_repaired: int, variant_repaired: int}
	 */
	public static function resync_all_page_bindings() {
		$scanned          = 0;
		$updated          = 0;
		$source_repaired  = 0;
		$variant_repaired = 0;
		foreach ( self::query_experiments( array( 'posts_per_page' => 500 ) ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			++$scanned;
			$raw  = self::get_config( $post->ID );
			$prev = self::binding_signature( $raw );
			self::normalize_page_bindings( $raw, $post->ID, true );
			$next_cfg = self::get_config( $post->ID );
			$next     = self::binding_signature( $next_cfg );
			if ( wp_json_encode( $prev ) !== wp_json_encode( $next ) ) {
				++$updated;
				if ( (int) ( $prev['source_page_id'] ?? 0 ) !== (int) ( $next['source_page_id'] ?? 0 ) ) {
					++$source_repaired;
				}
				foreach ( isset( $prev['v'] ) && is_array( $prev['v'] ) ? $prev['v'] : array() as $vk => $vpid ) {
					$npid = isset( $next['v'][ $vk ] ) ? (int) $next['v'][ $vk ] : 0;
					if ( (int) $vpid !== $npid ) {
						++$variant_repaired;
					}
				}
			}
		}
		return array(
			'scanned'          => $scanned,
			'updated'          => $updated,
			'source_repaired'  => $source_repaired,
			'variant_repaired' => $variant_repaired,
		);
	}

	/**
	 * @param array<string, mixed> $cfg     Experiment config.
	 * @param int                    $page_id Post ID.
	 * @return bool
	 */
	public static function config_touches_page_id( array $cfg, $page_id ) {
		$page_id = (int) $page_id;
		$cfg     = self::normalize_page_bindings( $cfg, 0, false );
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
	 * Admin warnings: permalink round-trip, missing variant B, etc.
	 *
	 * @param array<string, mixed> $cfg Experiment config (raw or normalized).
	 * @return list<array{code: string, message: string}>
	 */
	public static function binding_health_warnings( array $cfg ) {
		$cfg = self::normalize_page_bindings( $cfg, 0, false );
		$out = array();
		$src = (int) ( $cfg['source_page_id'] ?? 0 );
		if ( $src <= 0 ) {
			$out[] = array(
				'code'    => 'missing_source',
				'message' => __( 'This test has no valid source page ID.', 'reactwoo-geo-optimise' ),
			);
			return $out;
		}
		$perm = get_permalink( $src );
		if ( is_string( $perm ) && '' !== $perm ) {
			$round = (int) url_to_postid( $perm );
			if ( $round > 0 && $round !== $src ) {
				$out[] = array(
					'code'    => 'source_permalink_mismatch',
					'message' => __( 'The saved source page does not match the post ID WordPress resolves for its public URL. Run Resync Page Bindings or reselect the source page.', 'reactwoo-geo-optimise' ),
				);
			}
		}
		return $out;
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
