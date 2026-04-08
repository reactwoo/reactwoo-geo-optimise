<?php
/**
 * Promote winning variant: copy content to primary, complete test, optional redirect, variant disposal.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates promotion (Mode A default; Mode B scaffold only).
 */
class RWGO_Promotion_Service {

	const MODE_REPLACE_CONTENT = 'replace_content';
	const MODE_SLUG_SWAP       = 'slug_swap';

	const VARIANT_ARCHIVE_REDIRECT     = 'archive_redirect';
	const VARIANT_ARCHIVE_NO_REDIRECT  = 'archive_no_redirect';
	const VARIANT_TRASH_REDIRECT       = 'trash_redirect';
	const VARIANT_LEAVE                = 'leave';

	/**
	 * @param int                  $experiment_post_id Experiment CPT ID.
	 * @param array<string, mixed> $args               mode, variant_action, copy_post_title.
	 * @return array<string, mixed>|\WP_Error Success payload or error.
	 */
	public static function run( $experiment_post_id, array $args = array() ) {
		$experiment_post_id = (int) $experiment_post_id;
		$mode               = isset( $args['mode'] ) ? sanitize_key( (string) $args['mode'] ) : self::MODE_REPLACE_CONTENT;
		$variant_action     = isset( $args['variant_action'] ) ? sanitize_key( (string) $args['variant_action'] ) : self::VARIANT_ARCHIVE_REDIRECT;
		$copy_post_title    = array_key_exists( 'copy_post_title', $args ) ? (bool) $args['copy_post_title'] : true;

		if ( self::MODE_SLUG_SWAP === $mode ) {
			return RWGO_Promotion_Slug_Scaffold::not_available( $experiment_post_id, $args );
		}

		if ( self::MODE_REPLACE_CONTENT !== $mode ) {
			return new \WP_Error( 'rwgo_promotion_mode', __( 'Unknown promotion mode.', 'reactwoo-geo-optimise' ) );
		}

		$allowed_va = array(
			self::VARIANT_ARCHIVE_REDIRECT,
			self::VARIANT_ARCHIVE_NO_REDIRECT,
			self::VARIANT_TRASH_REDIRECT,
			self::VARIANT_LEAVE,
		);
		if ( ! in_array( $variant_action, $allowed_va, true ) ) {
			return new \WP_Error( 'rwgo_promotion_variant_action', __( 'Invalid variant handling option.', 'reactwoo-geo-optimise' ) );
		}

		if ( $experiment_post_id <= 0 || ! current_user_can( 'edit_post', $experiment_post_id ) ) {
			return new \WP_Error( 'rwgo_promotion_perm', __( 'You cannot edit this test.', 'reactwoo-geo-optimise' ) );
		}

		$cfg = RWGO_Experiment_Repository::normalize_page_bindings(
			RWGO_Experiment_Repository::get_config( $experiment_post_id ),
			$experiment_post_id,
			false
		);
		$src = (int) ( $cfg['source_page_id'] ?? 0 );
		$b   = RWGO_Variant_Lifecycle::variant_b_page_id( $cfg, $experiment_post_id );

		if ( $src <= 0 || $b <= 0 || $b === $src ) {
			return new \WP_Error( 'rwgo_promotion_config', __( 'Control or Variant B is missing from this test.', 'reactwoo-geo-optimise' ) );
		}
		if ( ! current_user_can( 'edit_post', $src ) || ! current_user_can( 'edit_post', $b ) ) {
			return new \WP_Error( 'rwgo_promotion_pages', __( 'You cannot edit the Control or Variant page.', 'reactwoo-geo-optimise' ) );
		}

		$source_url  = get_permalink( $src );
		$variant_url = get_permalink( $b );
		$variant_path = RWGO_Redirect_Store::path_from_post_or_url( $b );
		if ( ! is_string( $source_url ) ) {
			$source_url = '';
		}
		if ( ! is_string( $variant_url ) ) {
			$variant_url = '';
		}

		$copy_args = array(
			'copy_post_title' => (bool) apply_filters( 'rwgo_promotion_copy_post_title', $copy_post_title, $experiment_post_id, $b, $src ),
		);
		$ok = RWGO_Page_Duplicator::copy_document_into_post( $b, $src, $copy_args );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}

		RWGO_Experiment_Repository::save_config(
			$experiment_post_id,
			array(
				'status'              => 'completed',
				'promoted_at'         => gmdate( 'c' ),
				'promoted_from_id'    => $b,
				'promotion_mode'      => self::MODE_REPLACE_CONTENT,
				'promotion_variant_act' => $variant_action,
			)
		);

		$redirect_id = 0;
		$needs_redirect = in_array(
			$variant_action,
			array( self::VARIANT_ARCHIVE_REDIRECT, self::VARIANT_TRASH_REDIRECT ),
			true
		);
		if ( $needs_redirect && is_string( $variant_url ) && '' !== $variant_url && is_string( $source_url ) && '' !== $source_url && '/' !== $variant_path ) {
			$r = RWGO_Redirect_Store::insert_rule(
				array(
					'source_path'        => $variant_path,
					'target_url'         => $source_url,
					'source_post_id'     => $b,
					'target_post_id'     => $src,
					'experiment_post_id' => $experiment_post_id,
					'promotion_event_id' => 0,
					'active'             => 1,
					'meta_json'          => array(
						'source' => 'promotion',
						'mode'   => self::MODE_REPLACE_CONTENT,
					),
				)
			);
			if ( ! is_wp_error( $r ) ) {
				$redirect_id = (int) $r;
			}
		}

		self::apply_variant_disposal( $b, $variant_action, $redirect_id );

		$log_id = RWGO_Promotion_Log::insert(
			array(
				'experiment_post_id'   => $experiment_post_id,
				'source_post_id'       => $src,
				'variant_post_id'      => $b,
				'mode'                 => self::MODE_REPLACE_CONTENT,
				'variant_action'       => $variant_action,
				'redirect_id'          => $redirect_id,
				'source_url_snapshot'  => $source_url,
				'target_url_snapshot'  => $source_url,
				'variant_url_snapshot' => $variant_url,
				'meta_json'            => array(
					'variant_path' => $variant_path,
					'redirect_created' => $redirect_id > 0,
				),
			)
		);

		if ( ! is_wp_error( $log_id ) && $redirect_id > 0 ) {
			RWGO_Redirect_Store::link_promotion_event( (int) $log_id, $redirect_id );
		}

		/**
		 * Variant B content was promoted onto the Control page and the test was completed.
		 *
		 * @param int $experiment_post_id Experiment post ID.
		 * @param int $control_page_id    Control page ID.
		 * @param int $variant_page_id    Variant B page ID.
		 */
		do_action( 'rwgo_variant_promoted_to_control', $experiment_post_id, $src, $b );
		do_action(
			'rwgo_promotion_completed',
			$experiment_post_id,
			$src,
			$b,
			array(
				'promotion_log_id' => is_wp_error( $log_id ) ? 0 : (int) $log_id,
				'redirect_id'      => $redirect_id,
				'variant_action'   => $variant_action,
			)
		);

		return array(
			'promotion_log_id' => is_wp_error( $log_id ) ? 0 : (int) $log_id,
			'redirect_id'      => $redirect_id,
			'source_post_id'   => $src,
			'variant_post_id'  => $b,
			'source_url'       => $source_url,
			'variant_url'      => $variant_url,
			'variant_path'     => $variant_path,
			'variant_action'   => $variant_action,
			'redirect_created' => $redirect_id > 0,
		);
	}

	/**
	 * @param int    $variant_post_id Variant page ID.
	 * @param string $variant_action  Action slug.
	 * @param int    $redirect_id     Created redirect rule ID (0 if none).
	 * @return void
	 */
	private static function apply_variant_disposal( $variant_post_id, $variant_action, $redirect_id ) {
		$variant_post_id = (int) $variant_post_id;
		if ( $variant_post_id <= 0 || ! current_user_can( 'edit_post', $variant_post_id ) ) {
			return;
		}

		switch ( $variant_action ) {
			case self::VARIANT_LEAVE:
				return;
			case self::VARIANT_ARCHIVE_REDIRECT:
			case self::VARIANT_ARCHIVE_NO_REDIRECT:
				wp_update_post(
					wp_slash(
						array(
							'ID'          => $variant_post_id,
							'post_status' => 'draft',
						)
					)
				);
				return;
			case self::VARIANT_TRASH_REDIRECT:
				if ( $redirect_id <= 0 ) {
					// Redirect should exist before trash; if insert failed, still try draft to avoid losing continuity.
					wp_update_post(
						wp_slash(
							array(
								'ID'          => $variant_post_id,
								'post_status' => 'draft',
							)
						)
					);
					return;
				}
				if ( current_user_can( 'delete_post', $variant_post_id ) ) {
					wp_trash_post( $variant_post_id );
				}
				return;
			default:
				return;
		}
	}
}
