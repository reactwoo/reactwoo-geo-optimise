<?php
/**
 * Variant page titles and slugs: predictable names, explicit uniqueness (incl. trash).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central naming for Variant B duplicates — do not rely on WordPress implicit slug uniquification.
 */
class RWGO_Page_Naming_Service {

	/**
	 * Case 4: user renamed variant to match Control — used for admin warning only (does not change titles).
	 *
	 * @param int $control_post_id Control (source) post ID.
	 * @param int $variant_post_id Variant B post ID.
	 * @return bool
	 */
	public static function variant_title_matches_control_title( $control_post_id, $variant_post_id ) {
		$c = get_post( (int) $control_post_id );
		$v = get_post( (int) $variant_post_id );
		if ( ! $c instanceof \WP_Post || ! $v instanceof \WP_Post ) {
			return false;
		}

		return trim( (string) $c->post_title ) === trim( (string) $v->post_title );
	}

	/**
	 * Base title: "{Original Title} — Variant {key}".
	 *
	 * @param string $source_title Original post title.
	 * @param string $variant_key  Variant label, e.g. "B".
	 * @return string
	 */
	public static function generate_variant_title( $source_title, $variant_key = 'B' ) {
		$source_title = is_string( $source_title ) ? $source_title : '';
		$variant_key  = is_string( $variant_key ) && '' !== $variant_key ? $variant_key : 'B';

		return trim( $source_title ) . ' — ' . sprintf(
			/* translators: %s: variant letter or label (e.g. B). */
			__( 'Variant %s', 'reactwoo-geo-optimise' ),
			$variant_key
		);
	}

	/**
	 * Ensure no other post uses this title (any status incl. trash). Appends (2), (3), … if needed.
	 *
	 * @param string $base_title   Full title (e.g. from generate_variant_title).
	 * @param string $post_type      Post type.
	 * @param int    $exclude_post_id Post ID to ignore (0 = new post).
	 * @return string
	 */
	public static function ensure_unique_variant_title( $base_title, $post_type, $exclude_post_id = 0 ) {
		$base_title       = is_string( $base_title ) ? $base_title : '';
		$post_type        = is_string( $post_type ) ? $post_type : 'page';
		$exclude_post_id  = (int) $exclude_post_id;
		$candidate        = $base_title;
		$suffix_num       = 2;

		while ( self::title_is_taken( $candidate, $post_type, $exclude_post_id ) ) {
			$candidate = $base_title . ' (' . $suffix_num . ')';
			$suffix_num++;
			if ( $suffix_num > 500 ) {
				break;
			}
		}

		return $candidate;
	}

	/**
	 * Base slug: sanitize(source) + "-variant-{key}".
	 *
	 * @param string $source_slug Slug or empty (caller may pass sanitized title).
	 * @param string $variant_key Lowercase slug fragment, e.g. "b".
	 * @return string
	 */
	public static function generate_variant_slug( $source_slug, $variant_key = 'b' ) {
		$source_slug = is_string( $source_slug ) ? $source_slug : '';
		$variant_key = is_string( $variant_key ) && '' !== $variant_key ? strtolower( $variant_key ) : 'b';
		$variant_key = preg_replace( '/[^a-z0-9]+/', '', $variant_key );
		if ( '' === $variant_key ) {
			$variant_key = 'b';
		}

		$base = sanitize_title( $source_slug );
		if ( '' === $base ) {
			$base = 'page';
		}

		return $base . '-variant-' . $variant_key;
	}

	/**
	 * Resolve slug conflicts (published, draft, private, trash, etc.). Never relies on WP auto-append.
	 *
	 * @param string $base_slug       First candidate (e.g. from generate_variant_slug).
	 * @param string $post_type       Post type.
	 * @param int    $exclude_post_id Ignore this post ID (updates).
	 * @return array{ slug: string, conflict_detected: bool, conflict_count: int }
	 */
	public static function ensure_unique_slug( $base_slug, $post_type = 'page', $exclude_post_id = 0 ) {
		$base_slug       = is_string( $base_slug ) ? sanitize_title( $base_slug ) : '';
		$post_type       = is_string( $post_type ) ? $post_type : 'page';
		$exclude_post_id = (int) $exclude_post_id;

		if ( '' === $base_slug ) {
			$base_slug = 'variant-b';
		}

		$conflict_count   = 0;
		$candidate        = $base_slug;
		$n                = 2;
		$max_iterations   = 500;
		$iteration        = 0;

		while ( ! self::slug_is_available( $candidate, $post_type, $exclude_post_id ) ) {
			$conflict_count++;
			$candidate = $base_slug . '-' . $n;
			$n++;
			$iteration++;
			if ( $iteration > $max_iterations ) {
				$candidate = $base_slug . '-' . wp_generate_password( 6, false, false );
				break;
			}
		}

		return array(
			'slug'              => $candidate,
			'conflict_detected' => $conflict_count > 0,
			'conflict_count'    => $conflict_count,
		);
	}

	/**
	 * After insert/update: ensure post_name matches intended slug; fix and log if WordPress changed it.
	 *
	 * @param int    $post_id        New or updated post ID.
	 * @param string $intended_slug  Slug we expect.
	 * @param string $intended_title Title we expect (optional check).
	 * @param int    $source_post_id Source control page ID (logging).
	 * @param string $context        Log context e.g. duplicate|elementor_after_normalize|blank.
	 * @return void
	 */
	public static function verify_variant_identity_after_save( $post_id, $intended_slug, $intended_title, $source_post_id, $context = 'duplicate' ) {
		$post_id         = (int) $post_id;
		$source_post_id  = (int) $source_post_id;
		$intended_slug   = is_string( $intended_slug ) ? sanitize_title( $intended_slug ) : '';
		$intended_title  = is_string( $intended_title ) ? $intended_title : '';

		if ( $post_id <= 0 || '' === $intended_slug ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$fixed = false;
		if ( $post->post_name !== $intended_slug ) {
			self::log_naming(
				array(
					'event'          => 'slug_mismatch_after_save',
					'context'        => $context,
					'post_id'        => $post_id,
					'source_post_id' => $source_post_id,
					'intended_slug'  => $intended_slug,
					'actual_slug'    => $post->post_name,
				)
			);

			$upd = wp_update_post(
				wp_slash(
					array(
						'ID'        => $post_id,
						'post_name' => $intended_slug,
					)
				),
				true
			);
			if ( ! is_wp_error( $upd ) ) {
				clean_post_cache( $post_id );
				$post = get_post( $post_id );
				if ( $post instanceof \WP_Post && $post->post_name === $intended_slug ) {
					$fixed = true;
				}
			}

			if ( ! $fixed && $post instanceof \WP_Post && $post->post_name !== $intended_slug ) {
				self::log_naming(
					array(
						'event'          => 'slug_fix_failed',
						'context'        => $context,
						'post_id'        => $post_id,
						'intended_slug'  => $intended_slug,
						'actual_slug'    => $post->post_name,
					)
				);
			}
		}

		if ( '' !== $intended_title && $post instanceof \WP_Post && $post->post_title !== $intended_title ) {
			self::log_naming(
				array(
					'event'          => 'title_mismatch_after_save',
					'context'        => $context,
					'post_id'        => $post_id,
					'intended_title' => $intended_title,
					'actual_title'   => $post->post_title,
				)
			);
		}
	}

	/**
	 * @param array<string, mixed> $data Log payload.
	 * @return void
	 */
	public static function log_naming( array $data ) {
		$enabled = apply_filters( 'rwgo_log_variant_naming', ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) );
		if ( ! $enabled ) {
			return;
		}

		$line = '[RWGO variant naming] ' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * @param string $title           Exact title.
	 * @param string $post_type       Post type.
	 * @param int    $exclude_post_id Exclude ID.
	 * @return bool
	 */
	private static function title_is_taken( $title, $post_type, $exclude_post_id ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s AND post_status IN ('publish','draft','pending','private','future','trash') AND ID != %d LIMIT 1",
			$title,
			$post_type,
			$exclude_post_id
		);

		return (bool) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Slug is free for insert/update when no conflicting row exists, or trash reuse is allowed and handled.
	 *
	 * @param string $slug            Candidate slug.
	 * @param string $post_type       Post type.
	 * @param int    $exclude_post_id Exclude post ID.
	 * @return bool
	 */
	private static function slug_is_available( $slug, $post_type, $exclude_post_id ) {
		$rows = self::get_posts_having_slug( $slug, $post_type, $exclude_post_id );
		if ( array() === $rows ) {
			$by_path = get_page_by_path( $slug, OBJECT, $post_type );
			if ( $by_path instanceof \WP_Post && (int) $by_path->ID !== (int) $exclude_post_id ) {
				$st = (string) $by_path->post_status;
				if ( in_array( $st, array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), true ) ) {
					return false;
				}
			}
			return true;
		}

		$all_trash = true;
		foreach ( $rows as $row ) {
			if ( ! isset( $row->post_status ) || 'trash' !== $row->post_status ) {
				$all_trash = false;
				break;
			}
		}

		if ( ! $all_trash ) {
			return false;
		}

		foreach ( $rows as $row ) {
			$pid = isset( $row->ID ) ? (int) $row->ID : 0;
			if ( $pid <= 0 ) {
				continue;
			}
			$allow = apply_filters( 'rwgo_allow_slug_reuse_from_trash', false, $slug, $pid );
			if ( $allow && current_user_can( 'delete_post', $pid ) ) {
				wp_delete_post( $pid, true );
			} else {
				return false;
			}
		}

		return array() === self::get_posts_having_slug( $slug, $post_type, $exclude_post_id );
	}

	/**
	 * Rows matching slug (incl. trash). Excludes one ID.
	 *
	 * @param string $slug            Slug.
	 * @param string $post_type       Post type.
	 * @param int    $exclude_post_id Exclude.
	 * @return array<int, object{ID: int, post_status: string}>
	 */
	private static function get_posts_having_slug( $slug, $post_type, $exclude_post_id ) {
		global $wpdb;

		$exclude_post_id = (int) $exclude_post_id;
		$sql             = $wpdb->prepare(
			"SELECT ID, post_status FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_status IN ('publish','draft','pending','private','future','trash')",
			$slug,
			$post_type
		);
		if ( $exclude_post_id > 0 ) {
			$sql .= $wpdb->prepare( ' AND ID != %d', $exclude_post_id );
		}

		$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $results ) ? $results : array();
	}
}
