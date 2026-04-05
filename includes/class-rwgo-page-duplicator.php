<?php
/**
 * Duplicate pages/posts for variant B — builder-aware (Elementor + standard).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page / post duplication with Elementor document lifecycle and validation.
 */
class RWGO_Page_Duplicator {

	/**
	 * Intended slug/title for the last duplicate_standard_post run (Elementor verify step).
	 *
	 * @var string
	 */
	private static $last_variant_intended_slug = '';

	/**
	 * @var string
	 */
	private static $last_variant_intended_title = '';

	/**
	 * Single entry: duplicate for tests, validate, return new ID or WP_Error.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|\WP_Error
	 */
	public static function duplicate_page( $source_post_id ) {
		$source_post_id = (int) $source_post_id;
		self::$last_variant_intended_slug  = '';
		self::$last_variant_intended_title = '';
		if ( $source_post_id <= 0 ) {
			return new \WP_Error( 'rwgo_dup_missing', __( 'Source page not found.', 'reactwoo-geo-optimise' ) );
		}

		/**
		 * Before duplicating a variant page from Control. Not for blank variants.
		 *
		 * @param int $source_post_id Source post ID.
		 */
		do_action( 'rwgo_pre_duplicate_variant', $source_post_id );

		$source_post_id = (int) apply_filters( 'rwgo_duplicate_variant_source_id', $source_post_id );
		if ( $source_post_id <= 0 ) {
			return new \WP_Error( 'rwgo_dup_missing', __( 'Source page not found.', 'reactwoo-geo-optimise' ) );
		}

		$use_elementor = self::should_use_elementor_duplication_path( $source_post_id );

		if ( $use_elementor ) {
			$result = self::duplicate_elementor_document( $source_post_id );
		} else {
			$result = self::duplicate_standard_post( $source_post_id );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_id = (int) $result;

		$validated = self::validate_duplicate( $source_post_id, $new_id, true );
		if ( is_wp_error( $validated ) ) {
			if ( current_user_can( 'delete_post', $new_id ) ) {
				wp_trash_post( $new_id );
			}
			return $validated;
		}

		/**
		 * After a validated variant duplicate exists (attach to test after this).
		 *
		 * @param int $new_id         New post ID.
		 * @param int $source_post_id Source post ID.
		 */
		do_action( 'rwgo_post_duplicate_variant', $new_id, $source_post_id );

		/**
		 * Legacy hook — same moment as {@see rwgo_post_duplicate_variant}.
		 *
		 * @param int $new_id         New post ID.
		 * @param int $source_post_id Source post ID.
		 */
		do_action( 'rwgo_variant_page_duplicated', $new_id, $source_post_id );

		return $new_id;
	}

	/**
	 * Alias for {@see duplicate_page()} (spec / external integrations).
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|\WP_Error
	 */
	public static function duplicate( $source_post_id ) {
		return self::duplicate_page( $source_post_id );
	}

	/**
	 * Whether to run Elementor-specific normalization after a standard duplicate.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return bool
	 */
	public static function should_use_elementor_duplication_path( $source_post_id ) {
		if ( ! self::source_expects_elementor_layout( (int) $source_post_id ) ) {
			return false;
		}
		return (bool) apply_filters(
			'rwgo_use_elementor_duplication_path',
			did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin', false ),
			(int) $source_post_id
		);
	}

	/**
	 * Standard: new post + copy meta + geo strip + clear Elementor caches + thumbnail.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|\WP_Error
	 */
	public static function duplicate_standard_post( $source_post_id ) {
		$post_id = (int) $source_post_id;
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rwgo_dup_missing', __( 'Source page not found.', 'reactwoo-geo-optimise' ) );
		}

		self::$last_variant_intended_slug  = '';
		self::$last_variant_intended_title = '';

		$source_slug = is_string( $post->post_name ) ? $post->post_name : '';
		if ( '' === trim( (string) $source_slug ) ) {
			$source_slug = sanitize_title( $post->post_title );
		}

		$base_title = class_exists( 'RWGO_Page_Naming_Service', false )
			? RWGO_Page_Naming_Service::generate_variant_title( $post->post_title, 'B' )
			: $post->post_title . ' — ' . __( 'Variant B', 'reactwoo-geo-optimise' );
		$final_title = class_exists( 'RWGO_Page_Naming_Service', false )
			? RWGO_Page_Naming_Service::ensure_unique_variant_title( $base_title, $post->post_type, 0 )
			: $base_title;

		$base_slug = class_exists( 'RWGO_Page_Naming_Service', false )
			? RWGO_Page_Naming_Service::generate_variant_slug( $source_slug, 'b' )
			: sanitize_title( $source_slug ) . '-variant-b';
		$slug_info = class_exists( 'RWGO_Page_Naming_Service', false )
			? RWGO_Page_Naming_Service::ensure_unique_slug( $base_slug, $post->post_type, 0 )
			: array(
				'slug'              => $base_slug,
				'conflict_detected' => false,
				'conflict_count'    => 0,
			);
		$final_slug = isset( $slug_info['slug'] ) ? (string) $slug_info['slug'] : $base_slug;

		if ( class_exists( 'RWGO_Page_Naming_Service', false ) ) {
			RWGO_Page_Naming_Service::log_naming(
				array(
					'event'             => 'variant_duplicate_prepare',
					'source_post_id'    => $post_id,
					'source_title'      => $post->post_title,
					'source_slug'       => $source_slug,
					'base_title'        => $base_title,
					'intended_title'    => $final_title,
					'base_slug'         => $base_slug,
					'intended_slug'     => $final_slug,
					'final_slug'        => $final_slug,
					'conflict_detected' => ! empty( $slug_info['conflict_detected'] ),
					'conflict_count'    => isset( $slug_info['conflict_count'] ) ? (int) $slug_info['conflict_count'] : 0,
				)
			);
		}

		self::$last_variant_intended_slug  = $final_slug;
		self::$last_variant_intended_title = $final_title;

		$new_post = array(
			'post_title'     => $final_title,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => 'draft',
			'post_type'      => $post->post_type,
			'post_author'    => get_current_user_id() ? get_current_user_id() : (int) $post->post_author,
			'post_name'      => $final_slug,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'menu_order'     => (int) $post->menu_order,
		);

		$new_id = wp_insert_post( wp_slash( $new_post ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;

		if ( class_exists( 'RWGO_Page_Naming_Service', false ) ) {
			RWGO_Page_Naming_Service::verify_variant_identity_after_save( $new_id, $final_slug, $final_title, $post_id, 'duplicate_after_insert' );
			$pchk = get_post( $new_id );
			if ( $pchk instanceof \WP_Post ) {
				RWGO_Page_Naming_Service::log_naming(
					array(
						'event'          => 'variant_duplicate_post_insert',
						'source_post_id' => $post_id,
						'source_title'   => $post->post_title,
						'source_slug'    => $source_slug,
						'new_post_id'    => $new_id,
						'intended_slug'  => $final_slug,
						'final_slug'     => $pchk->post_name,
						'actual_slug'    => $pchk->post_name,
						'intended_title' => $final_title,
						'actual_title'   => $pchk->post_title,
						'conflict_detected' => ! empty( $slug_info['conflict_detected'] ),
						'conflict_count'    => isset( $slug_info['conflict_count'] ) ? (int) $slug_info['conflict_count'] : 0,
					)
				);
			}
		}

		self::copy_post_meta( $post_id, $new_id );
		self::strip_geo_route_meta_from_variant( $new_id );
		self::reset_elementor_generated_assets( $new_id );

		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}

		return $new_id;
	}

	/**
	 * Elementor: standard duplicate + document save + CSS refresh.
	 *
	 * @param int $source_post_id Source post ID.
	 * @return int|\WP_Error
	 */
	public static function duplicate_elementor_document( $source_post_id ) {
		$res = self::duplicate_standard_post( $source_post_id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$new_id = (int) $res;
		self::elementor_normalize_duplicate( (int) $source_post_id, $new_id );
		if ( class_exists( 'RWGO_Page_Naming_Service', false ) && '' !== self::$last_variant_intended_slug ) {
			RWGO_Page_Naming_Service::verify_variant_identity_after_save(
				$new_id,
				self::$last_variant_intended_slug,
				self::$last_variant_intended_title,
				(int) $source_post_id,
				'elementor_after_normalize'
			);
			$after_el = get_post( $new_id );
			if ( $after_el instanceof \WP_Post ) {
				RWGO_Page_Naming_Service::log_naming(
					array(
						'event'         => 'variant_duplicate_after_elementor',
						'new_post_id'   => $new_id,
						'source_post_id'=> (int) $source_post_id,
						'intended_slug' => self::$last_variant_intended_slug,
						'final_slug'    => $after_el->post_name,
					)
				);
			}
		}
		self::elementor_regenerate_post_assets( $new_id );
		return $new_id;
	}

	/**
	 * Re-save Elementor data through the document API so the duplicate is a real editor document.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $new_id    New post ID.
	 * @return void
	 */
	private static function elementor_normalize_duplicate( $source_id, $new_id ) {
		$source_id = (int) $source_id;
		$new_id    = (int) $new_id;
		if ( $new_id <= 0 || ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! isset( $plugin->documents ) || ! is_object( $plugin->documents ) ) {
			return;
		}
		$src_doc = $plugin->documents->get( $source_id, false );
		$new_doc = $plugin->documents->get( $new_id, false );
		if ( ! $src_doc || ! $new_doc ) {
			return;
		}
		if ( method_exists( $src_doc, 'is_built_with_elementor' ) && ! $src_doc->is_built_with_elementor() ) {
			return;
		}
		$elements = null;
		if ( method_exists( $src_doc, 'get_elements_data' ) ) {
			$elements = $src_doc->get_elements_data();
		}
		if ( null === $elements || ! is_array( $elements ) ) {
			return;
		}
		$settings = array();
		if ( method_exists( $src_doc, 'get_settings' ) ) {
			$settings = $src_doc->get_settings();
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
		}
		$save_data = array( 'elements' => $elements );
		if ( array() !== $settings ) {
			$save_data['settings'] = $settings;
		}
		if ( ! method_exists( $new_doc, 'save' ) ) {
			return;
		}
		try {
			$new_doc->save( $save_data );
		} catch ( \Throwable $e ) {
			self::maybe_log_duplicate_debug(
				$source_id,
				$new_id,
				'elementor_save_exception',
				false,
				array( 'message' => $e->getMessage() )
			);
		}
	}

	/**
	 * Regenerate per-post Elementor CSS and clear stale screenshot meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function elementor_regenerate_post_assets( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		if ( class_exists( '\Elementor\Core\Files\CSS\Post', false ) ) {
			try {
				$file = \Elementor\Core\Files\CSS\Post::create( $post_id );
				if ( $file && method_exists( $file, 'update' ) ) {
					$file->update();
				}
			} catch ( \Throwable $e ) {
				self::maybe_log_duplicate_debug(
					0,
					$post_id,
					'elementor_css_update_exception',
					false,
					array( 'message' => $e->getMessage() )
				);
			}
		}
		delete_post_meta( $post_id, '_elementor_screenshot' );
	}

	/**
	 * @param int  $source_id Source.
	 * @param int  $dest_id   Duplicate.
	 * @param bool $log       Whether to emit debug log lines (duplicate flow only).
	 * @return true|\WP_Error
	 */
	public static function validate_duplicate( $source_id, $dest_id, $log = false ) {
		$source_id = (int) $source_id;
		$dest_id   = (int) $dest_id;
		$log       = (bool) $log;
		if ( $dest_id <= 0 || ! get_post( $dest_id ) instanceof \WP_Post ) {
			$err = new \WP_Error(
				'rwgo_dup_validation_failed',
				__( 'Duplicate validation failed: destination page is missing.', 'reactwoo-geo-optimise' )
			);
			if ( $log ) {
				self::maybe_log_validation_result( $source_id, $dest_id, $err );
			}
			return $err;
		}
		if ( self::source_expects_elementor_layout( $source_id ) ) {
			$result = self::validate_elementor_duplicate( $source_id, $dest_id );
		} else {
			$result = self::validate_standard_duplicate( $source_id, $dest_id );
		}
		if ( $log ) {
			self::maybe_log_validation_result( $source_id, $dest_id, $result );
		}
		return $result;
	}

	/**
	 * @param int $source_id Source.
	 * @param int $dest_id   Duplicate.
	 * @return true|\WP_Error
	 */
	private static function validate_standard_duplicate( $source_id, $dest_id ) {
		/**
		 * Filter duplicate validation for non-Elementor sources.
		 *
		 * @param bool               $ok          Default true.
		 * @param int                $source_id   Source post ID.
		 * @param int                $dest_id     New post ID.
		 * @param string             $context     "standard".
		 * @param array<string, mixed> $diag      Empty for standard.
		 */
		$ok = apply_filters( 'rwgo_validate_duplicate_variant', true, (int) $source_id, (int) $dest_id, 'standard', array() );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( false === $ok ) {
			return new \WP_Error(
				'rwgo_dup_validation_failed',
				__( 'Duplicate validation failed.', 'reactwoo-geo-optimise' )
			);
		}
		return true;
	}

	/**
	 * @param int $source_id Source.
	 * @param int $dest_id   Duplicate.
	 * @return true|\WP_Error
	 */
	private static function validate_elementor_duplicate( $source_id, $dest_id ) {
		$source_id = (int) $source_id;
		$dest_id   = (int) $dest_id;
		$failed    = array();

		$src_data = get_post_meta( $source_id, '_elementor_data', true );
		$dst_data = get_post_meta( $dest_id, '_elementor_data', true );
		$src_len  = is_string( $src_data ) ? strlen( $src_data ) : 0;
		$dst_len  = is_string( $dst_data ) ? strlen( $dst_data ) : 0;

		if ( $src_len < 2 ) {
			$failed[] = 'source_elementor_data_empty';
		}
		if ( $dst_len < 2 ) {
			$failed[] = 'dest_elementor_data_empty';
		}
		if ( is_string( $src_data ) && '' !== $src_data ) {
			json_decode( $src_data, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$failed[] = 'source_elementor_data_invalid_json';
			}
		}
		if ( is_string( $dst_data ) && '' !== $dst_data ) {
			json_decode( $dst_data, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$failed[] = 'dest_elementor_data_invalid_json';
			}
		}

		$src_mode = (string) get_post_meta( $source_id, '_elementor_edit_mode', true );
		$dst_mode = (string) get_post_meta( $dest_id, '_elementor_edit_mode', true );
		if ( 'builder' === $src_mode && 'builder' !== $dst_mode ) {
			$failed[] = 'dest_edit_mode_not_builder';
		}

		if ( $src_len > 500 && $dst_len > 0 && $dst_len < max( 200, (int) floor( $src_len * 0.45 ) ) ) {
			$failed[] = 'dest_data_suspiciously_smaller';
		}

		$checks = array(
			'source_data_len' => $src_len,
			'dest_data_len'   => $dst_len,
			'source_mode'     => $src_mode,
			'dest_mode'       => $dst_mode,
			'failed_checks'   => $failed,
		);

		$pass = array() === $failed;
		/**
		 * Filter Elementor duplicate validation.
		 *
		 * @param bool                 $pass       Whether built-in checks pass.
		 * @param int                  $source_id  Source post ID.
		 * @param int                  $dest_id    Duplicate post ID.
		 * @param string               $context    "elementor".
		 * @param array<string, mixed> $checks     Diagnostics including failed_checks.
		 */
		$filtered = apply_filters( 'rwgo_validate_duplicate_variant', $pass, $source_id, $dest_id, 'elementor', $checks );

		if ( is_wp_error( $filtered ) ) {
			return $filtered;
		}

		if ( false === $filtered ) {
			return new \WP_Error(
				'rwgo_dup_elementor_invalid',
				__( 'Variant B could not be created as a valid Elementor duplicate. The page was not attached to the test. Try again, use an existing page, or create a blank variant.', 'reactwoo-geo-optimise' ),
				array( 'failed_checks' => $failed, 'checks' => $checks )
			);
		}

		if ( array() !== $failed ) {
			return new \WP_Error(
				'rwgo_dup_elementor_invalid',
				__( 'Variant B could not be created as a valid Elementor duplicate. The page was not attached to the test. Try again, use an existing page, or create a blank variant.', 'reactwoo-geo-optimise' ),
				array( 'failed_checks' => $failed, 'checks' => $checks )
			);
		}

		return true;
	}

	/**
	 * Map WP_Error from duplicate_page() to admin ?rwgo_error= slug.
	 *
	 * @param \WP_Error $err Error from duplicate_page.
	 * @return string dup|dup_invalid
	 */
	public static function duplicate_redirect_error_arg( $err ) {
		if ( ! is_wp_error( $err ) ) {
			return 'dup';
		}
		$c = $err->get_error_code();
		if ( in_array( $c, array( 'rwgo_dup_elementor_invalid', 'rwgo_dup_validation_failed' ), true ) ) {
			return 'dup_invalid';
		}
		return 'dup';
	}

	/**
	 * Human-readable fidelity for tests UI: ready|missing|builder_mismatch|duplicate_failed|neutral.
	 *
	 * @param int $source_id  Control page ID.
	 * @param int $variant_id Variant B page ID.
	 * @return string
	 */
	public static function get_variant_fidelity_status( $source_id, $variant_id ) {
		$source_id  = (int) $source_id;
		$variant_id = (int) $variant_id;
		if ( $variant_id <= 0 || ! get_post( $variant_id ) instanceof \WP_Post ) {
			return 'missing';
		}
		if ( $source_id <= 0 ) {
			return 'neutral';
		}
		$src_el = self::source_expects_elementor_layout( $source_id );
		$dst_el = self::source_expects_elementor_layout( $variant_id );
		if ( $src_el && ! $dst_el ) {
			return 'builder_mismatch';
		}
		$v = self::validate_duplicate( $source_id, $variant_id, false );
		if ( is_wp_error( $v ) ) {
			return 'duplicate_failed';
		}
		if ( $src_el && $dst_el ) {
			$dd = get_post_meta( $variant_id, '_elementor_data', true );
			if ( ! is_string( $dd ) || strlen( $dd ) < 2 ) {
				return 'missing_builder';
			}
		}
		return 'ready';
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
	 * Copy document content + meta from one post to another (promote / replace).
	 *
	 * @param int                  $from_id Source document ID.
	 * @param int                  $to_id   Target document ID (updated in place).
	 * @param array<string, mixed> $args    Optional: copy_post_title (bool, default true).
	 * @return true|\WP_Error
	 */
	public static function copy_document_into_post( $from_id, $to_id, array $args = array() ) {
		$from_id         = (int) $from_id;
		$to_id           = (int) $to_id;
		$copy_post_title = array_key_exists( 'copy_post_title', $args ) ? (bool) $args['copy_post_title'] : true;
		$from            = get_post( $from_id );
		$to              = get_post( $to_id );
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
		if ( self::should_use_elementor_duplication_path( $from_id ) ) {
			self::elementor_normalize_duplicate( $from_id, $to_id );
			self::elementor_regenerate_post_assets( $to_id );
		}
		return true;
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
		$base_title = sprintf(
			/* translators: %s: test name */
			__( 'Variant B — %s', 'reactwoo-geo-optimise' ),
			$suffix
		);
		$final_title = class_exists( 'RWGO_Page_Naming_Service', false )
			? RWGO_Page_Naming_Service::ensure_unique_variant_title( $base_title, $post->post_type, 0 )
			: $base_title;

		$slug_source = sanitize_title( $suffix );
		if ( '' === $slug_source ) {
			$slug_source = 'variant-b';
		}
		$base_slug = class_exists( 'RWGO_Page_Naming_Service', false )
			? RWGO_Page_Naming_Service::generate_variant_slug( $slug_source, 'b' )
			: $slug_source . '-variant-b';
		$slug_info = class_exists( 'RWGO_Page_Naming_Service', false )
			? RWGO_Page_Naming_Service::ensure_unique_slug( $base_slug, $post->post_type, 0 )
			: array(
				'slug'              => $base_slug,
				'conflict_detected' => false,
				'conflict_count'    => 0,
			);
		$final_slug = isset( $slug_info['slug'] ) ? (string) $slug_info['slug'] : $base_slug;

		if ( class_exists( 'RWGO_Page_Naming_Service', false ) ) {
			RWGO_Page_Naming_Service::log_naming(
				array(
					'event'             => 'blank_variant_prepare',
					'source_post_id'    => $post_id,
					'source_title'      => $post->post_title,
					'source_slug'       => $slug_source,
					'intended_title'    => $final_title,
					'base_slug'         => $base_slug,
					'intended_slug'     => $final_slug,
					'conflict_detected' => ! empty( $slug_info['conflict_detected'] ),
					'conflict_count'    => isset( $slug_info['conflict_count'] ) ? (int) $slug_info['conflict_count'] : 0,
				)
			);
		}

		$new_post = array(
			'post_title'   => $final_title,
			'post_content' => '',
			'post_excerpt' => '',
			'post_status'  => 'draft',
			'post_type'    => $post->post_type,
			'post_author'  => get_current_user_id() ? get_current_user_id() : (int) $post->post_author,
			'post_name'    => $final_slug,
		);
		$new_id = wp_insert_post( wp_slash( $new_post ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;

		if ( class_exists( 'RWGO_Page_Naming_Service', false ) ) {
			RWGO_Page_Naming_Service::verify_variant_identity_after_save( $new_id, $final_slug, $final_title, $post_id, 'blank_after_insert' );
			$pchk = get_post( $new_id );
			if ( $pchk instanceof \WP_Post ) {
				RWGO_Page_Naming_Service::log_naming(
					array(
						'event'               => 'blank_variant_post_insert',
						'source_post_id'      => $post_id,
						'source_title'        => $post->post_title,
						'source_slug'         => $slug_source,
						'intended_slug'       => $final_slug,
						'final_slug'          => $pchk->post_name,
						'conflict_detected'   => ! empty( $slug_info['conflict_detected'] ),
						'conflict_count'      => isset( $slug_info['conflict_count'] ) ? (int) $slug_info['conflict_count'] : 0,
					)
				);
			}
		}
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
	 * @param int $dest_id   Destination post ID.
	 * @return void
	 */
	private static function copy_post_meta( $source_id, $dest_id ) {
		$meta = get_post_meta( $source_id );
		if ( ! is_array( $meta ) ) {
			return;
		}
		$skip_keys = array(
			'_edit_lock',
			'_edit_last',
			'_elementor_css',
			'_elementor_screenshot',
		);
		/**
		 * Meta keys to skip when copying to a duplicate (avoid stale generated assets).
		 *
		 * @param array<int, string> $skip_keys Default keys.
		 * @param int                  $source_id Source post ID.
		 * @param int                  $dest_id   New post ID.
		 */
		$skip_keys = apply_filters( 'rwgo_duplicate_skip_meta_keys', $skip_keys, (int) $source_id, (int) $dest_id );
		foreach ( $meta as $key => $values ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}
			if ( 0 === strpos( $key, '_elementor_css' ) ) {
				continue;
			}
			if ( ! is_array( $values ) ) {
				continue;
			}
			foreach ( $values as $v ) {
				add_post_meta( $dest_id, $key, maybe_unserialize( $v ) );
			}
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
			$one = get_post_meta( $source_id, $ek, true );
			if ( '' === $one || false === $one ) {
				continue;
			}
			update_post_meta( $dest_id, $ek, $one );
		}
	}

	/**
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
	 * Structured debug log when Geo Core debug mode is on. Does not affect success path.
	 *
	 * @param int    $source_id      Source post ID.
	 * @param int    $dest_id        Duplicate post ID.
	 * @param string $stage          Stage label.
	 * @param bool   $validation_pass Whether validation passed (if applicable).
	 * @param array<string, mixed> $extra Extra fields.
	 * @return void
	 */
	private static function maybe_log_duplicate_debug( $source_id, $dest_id, $stage, $validation_pass, array $extra ) {
		if ( ! class_exists( 'RWGC_Settings', false ) || ! RWGC_Settings::get( 'debug_mode', 0 ) ) {
			return;
		}
		$src_builder = class_exists( 'RWGO_Builder_Detector', false ) ? RWGO_Builder_Detector::detect( (int) $source_id ) : array();
		$dst_builder = $dest_id > 0 && class_exists( 'RWGO_Builder_Detector', false ) ? RWGO_Builder_Detector::detect( (int) $dest_id ) : array();

		$src_data = get_post_meta( (int) $source_id, '_elementor_data', true );
		$dst_data = get_post_meta( (int) $dest_id, '_elementor_data', true );
		$src_meta = get_post_meta( (int) $source_id );
		$dst_meta = get_post_meta( (int) $dest_id );

		$out = array_merge(
			array(
				'stage'                    => $stage,
				'source_id'                => (int) $source_id,
				'dest_id'                  => (int) $dest_id,
				'source_builder'           => isset( $src_builder['builder'] ) ? (string) $src_builder['builder'] : '',
				'dest_builder'             => isset( $dst_builder['builder'] ) ? (string) $dst_builder['builder'] : '',
				'elementor_data_len_src'   => is_string( $src_data ) ? strlen( $src_data ) : 0,
				'elementor_data_len_dest'  => is_string( $dst_data ) ? strlen( $dst_data ) : 0,
				'elementor_edit_mode_src'  => (bool) get_post_meta( (int) $source_id, '_elementor_edit_mode', true ),
				'elementor_edit_mode_dest' => (bool) get_post_meta( (int) $dest_id, '_elementor_edit_mode', true ),
				'page_settings_src'        => '' !== (string) get_post_meta( (int) $source_id, '_elementor_page_settings', true ),
				'page_settings_dest'       => '' !== (string) get_post_meta( (int) $dest_id, '_elementor_page_settings', true ),
				'page_template_src'        => (string) get_post_meta( (int) $source_id, '_wp_page_template', true ),
				'page_template_dest'       => (string) get_post_meta( (int) $dest_id, '_wp_page_template', true ),
				'meta_key_count_src'       => is_array( $src_meta ) ? count( $src_meta ) : 0,
				'meta_key_count_dest'      => is_array( $dst_meta ) ? count( $dst_meta ) : 0,
				'validation_pass'          => $validation_pass,
			),
			$extra
		);

		error_log( '[RWGO duplicate] ' . wp_json_encode( $out ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Log validation failure details (debug mode).
	 *
	 * @param int               $source_id Source ID.
	 * @param int               $dest_id   Dest ID.
	 * @param true|\WP_Error    $result    Validation result.
	 * @return void
	 */
	private static function maybe_log_validation_result( $source_id, $dest_id, $result ) {
		if ( ! class_exists( 'RWGC_Settings', false ) || ! RWGC_Settings::get( 'debug_mode', 0 ) ) {
			return;
		}
		$extra = array( 'stage' => 'validation' );
		if ( is_wp_error( $result ) ) {
			$extra['error_code']    = $result->get_error_code();
			$extra['error_message'] = $result->get_error_message();
			$data                   = $result->get_error_data();
			if ( is_array( $data ) ) {
				if ( isset( $data['failed_checks'] ) ) {
					$extra['failed_checks'] = $data['failed_checks'];
				}
				if ( isset( $data['checks'] ) ) {
					$extra['checks'] = $data['checks'];
				}
			}
			self::maybe_log_duplicate_debug( (int) $source_id, (int) $dest_id, 'validate_failed', false, $extra );
		} else {
			self::maybe_log_duplicate_debug( (int) $source_id, (int) $dest_id, 'validate_ok', true, $extra );
		}
	}
}
