<?php
/**
 * Duplicate pages/posts for variant B (Elementor + Gutenberg meta preserved).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page / post duplication.
 */
class RWGO_Page_Duplicator {

	/**
	 * @param int $post_id Source post.
	 * @return int|\WP_Error New post ID.
	 */
	/**
	 * Single entry point: duplicate a page/post for tests and validate Elementor payload when applicable.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|\WP_Error New post ID or error (e.g. Elementor data missing after copy).
	 */
	public static function duplicate_page( $source_post_id ) {
		$res = self::duplicate( (int) $source_post_id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$new_id = (int) $res;
		if ( self::source_expects_elementor_layout( (int) $source_post_id ) ) {
			$data = get_post_meta( $new_id, '_elementor_data', true );
			if ( '' === $data || false === $data ) {
				return new \WP_Error(
					'rwgo_dup_elementor_missing',
					__( 'Duplication did not produce Elementor document data. Try again or check file permissions.', 'reactwoo-geo-optimise' )
				);
			}
		}
		return $new_id;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function source_expects_elementor_layout( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		$mode = get_post_meta( $post_id, '_elementor_edit_mode', true );
		if ( 'builder' === $mode ) {
			return true;
		}
		$d = get_post_meta( $post_id, '_elementor_data', true );
		return is_string( $d ) && '' !== $d;
	}

	/**
	 * Copy page/post content and builder meta from one post into another (e.g. promote Variant B → Control).
	 *
	 * @param int                  $from_id Source document ID.
	 * @param int                  $to_id   Target document ID (updated in place).
	 * @param array<string, mixed> $args    Optional: copy_post_title (bool, default true).
	 * @return true|\WP_Error
	 */
	public static function copy_document_into_post( $from_id, $to_id, array $args = array() ) {
		$from_id        = (int) $from_id;
		$to_id          = (int) $to_id;
		$copy_post_title = array_key_exists( 'copy_post_title', $args ) ? (bool) $args['copy_post_title'] : true;
		$from           = get_post( $from_id );
		$to             = get_post( $to_id );
		if ( ! $from instanceof \WP_Post || ! $to instanceof \WP_Post ) {
			return new \WP_Error( 'rwgo_copy_missing', __( 'Source or target page not found.', 'reactwoo-geo-optimise' ) );
		}
		if ( $from->post_type !== $to->post_type ) {
			return new \WP_Error( 'rwgo_copy_type', __( 'Source and target must be the same post type.', 'reactwoo-geo-optimise' ) );
		}
		$fields = array(
			'ID'             => $to_id,
			'post_content'   => $from->post_content,
			'post_excerpt'   => $from->post_excerpt,
			'comment_status' => $from->comment_status,
			'ping_status'    => $from->ping_status,
			'menu_order'     => (int) $from->menu_order,
		);
		if ( $copy_post_title ) {
			$fields['post_title'] = $from->post_title;
		}
		$upd = wp_update_post( wp_slash( $fields ), true );
		if ( is_wp_error( $upd ) ) {
			return $upd;
		}
		$elementor_keys = array(
			'_elementor_data',
			'_elementor_edit_mode',
			'_elementor_page_settings',
			'_elementor_template_type',
			'_elementor_version',
			'_wp_page_template',
		);
		foreach ( $elementor_keys as $ek ) {
			$v = get_post_meta( $from_id, $ek, true );
			if ( '' === $v || false === $v ) {
				delete_post_meta( $to_id, $ek );
			} else {
				update_post_meta( $to_id, $ek, $v );
			}
		}
		delete_post_meta( $to_id, '_elementor_css' );
		delete_post_meta( $to_id, '_elementor_screenshot' );
		$thumb = get_post_thumbnail_id( $from_id );
		if ( $thumb ) {
			set_post_thumbnail( $to_id, (int) $thumb );
		} else {
			delete_post_meta( $to_id, '_thumbnail_id' );
		}
		return true;
	}

	public static function duplicate( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rwgo_dup_missing', __( 'Source page not found.', 'reactwoo-geo-optimise' ) );
		}

		$new_post = array(
			'post_title'   => $post->post_title . ' — ' . __( 'Variant B', 'reactwoo-geo-optimise' ),
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => 'draft',
			'post_type'      => $post->post_type,
			'post_author'    => get_current_user_id() ? get_current_user_id() : (int) $post->post_author,
			'post_name'      => '',
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'menu_order'     => (int) $post->menu_order,
		);

		$new_id = wp_insert_post( wp_slash( $new_post ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;

		self::copy_post_meta( $post_id, $new_id );
		self::strip_geo_route_meta_from_variant( $new_id );
		self::reset_elementor_generated_assets( $new_id );
		self::maybe_log_duplicate_elementor_meta_debug( $post_id, $new_id );

		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}

		/**
		 * After a variant page is duplicated.
		 *
		 * @param int $new_id New post ID.
		 * @param int $source_id Source post ID.
		 */
		do_action( 'rwgo_variant_page_duplicated', $new_id, $post_id );

		return $new_id;
	}

	/**
	 * Empty draft for Variant B (same post type as source); user edits after publish.
	 *
	 * @param int    $post_id    Source post ID.
	 * @param string $test_title Experiment title (for draft name).
	 * @return int|\WP_Error New post ID.
	 */
	public static function create_blank_variant( $post_id, $test_title = '' ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rwgo_blank_missing', __( 'Source page not found.', 'reactwoo-geo-optimise' ) );
		}
		$test_title = is_string( $test_title ) ? trim( $test_title ) : '';
		$suffix     = '' !== $test_title ? $test_title : __( 'Test', 'reactwoo-geo-optimise' );
		$new_post   = array(
			'post_title'   => sprintf(
				/* translators: %s: test name */
				__( 'Variant B — %s', 'reactwoo-geo-optimise' ),
				$suffix
			),
			'post_content' => '',
			'post_excerpt' => '',
			'post_status'  => 'draft',
			'post_type'    => $post->post_type,
			'post_author'  => get_current_user_id() ? get_current_user_id() : (int) $post->post_author,
			'post_name'    => '',
		);
		$new_id = wp_insert_post( wp_slash( $new_post ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;
		/**
		 * After a blank variant placeholder is created.
		 *
		 * @param int $new_id    New post ID.
		 * @param int $source_id Source post ID.
		 */
		do_action( 'rwgo_blank_variant_created', $new_id, $post_id );
		return $new_id;
	}

	/**
	 * @param int $source_id Source post ID.
	 * @param int $dest_id Destination post ID.
	 * @return void
	 */
	private static function copy_post_meta( $source_id, $dest_id ) {
		$meta = get_post_meta( $source_id );
		if ( ! is_array( $meta ) ) {
			return;
		}
		$skip_keys = array( '_edit_lock', '_edit_last' );
		foreach ( $meta as $key => $values ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}
			if ( ! is_array( $values ) ) {
				continue;
			}
			foreach ( $values as $v ) {
				add_post_meta( $dest_id, $key, maybe_unserialize( $v ) );
			}
		}

		// Ensure Elementor document keys are single canonical values (avoids rare multi-row meta edge cases).
		$elementor_keys = array(
			'_elementor_data',
			'_elementor_edit_mode',
			'_elementor_page_settings',
			'_elementor_template_type',
			'_elementor_version',
			'_wp_page_template',
		);
		foreach ( $elementor_keys as $ek ) {
			$one = get_post_meta( $source_id, $ek, true );
			if ( '' === $one || false === $one ) {
				continue;
			}
			update_post_meta( $dest_id, $ek, $one );
		}
	}

	/**
	 * Variant B should not inherit Geo Core route bindings from Control (would tie B to A's geo routing).
	 *
	 * @param int $variant_id New page ID.
	 * @return void
	 */
	private static function strip_geo_route_meta_from_variant( $variant_id ) {
		$variant_id = (int) $variant_id;
		if ( $variant_id <= 0 ) {
			return;
		}
		if ( class_exists( 'RWGC_Routing', false ) ) {
			$keys = array(
				RWGC_Routing::META_ENABLED,
				RWGC_Routing::META_DEFAULT_PAGE_ID,
				RWGC_Routing::META_COUNTRY_ISO2,
				RWGC_Routing::META_COUNTRY_PAGE_ID,
				RWGC_Routing::META_ROLE,
				RWGC_Routing::META_MASTER_PAGE_ID,
			);
			foreach ( $keys as $k ) {
				delete_post_meta( $variant_id, $k );
			}
			return;
		}
		global $wpdb;
		$prefix = $wpdb->esc_like( '_rwgc_route_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", $variant_id, $prefix ) );
	}

	/**
	 * Regenerate Elementor CSS for the new post ID (copied CSS references wrong post).
	 *
	 * @param int $variant_id New page ID.
	 * @return void
	 */
	private static function reset_elementor_generated_assets( $variant_id ) {
		$variant_id = (int) $variant_id;
		if ( $variant_id <= 0 ) {
			return;
		}
		delete_post_meta( $variant_id, '_elementor_css' );
		delete_post_meta( $variant_id, '_elementor_screenshot' );
	}

	/**
	 * When Geo Core debug is enabled, log Elementor meta presence on source vs duplicate.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $dest_id   Duplicate post ID.
	 * @return void
	 */
	private static function maybe_log_duplicate_elementor_meta_debug( $source_id, $dest_id ) {
		if ( ! class_exists( 'RWGC_Settings', false ) || ! RWGC_Settings::get( 'debug_mode', 0 ) ) {
			return;
		}
		$keys = array( '_elementor_data', '_elementor_edit_mode', '_elementor_page_settings', '_wp_page_template' );
		$out  = array(
			'source_id' => (int) $source_id,
			'dest_id'   => (int) $dest_id,
		);
		foreach ( $keys as $k ) {
			$sv = get_post_meta( (int) $source_id, $k, true );
			$dv = get_post_meta( (int) $dest_id, $k, true );
			$out[ 'src_' . $k ] = is_string( $sv ) ? strlen( $sv ) : ( is_array( $sv ) ? count( $sv ) : ( $sv ? 1 : 0 ) );
			$out[ 'dst_' . $k ] = is_string( $dv ) ? strlen( $dv ) : ( is_array( $dv ) ? count( $dv ) : ( $dv ? 1 : 0 ) );
		}
		error_log( '[RWGO duplicate] ' . wp_json_encode( $out ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
