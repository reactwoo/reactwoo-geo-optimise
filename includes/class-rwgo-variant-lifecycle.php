<?php
/**
 * Variant B lifecycle: regenerate, detach, promote winner to Control.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test variant management (Geo Optimise).
 */
class RWGO_Variant_Lifecycle {

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return int
	 */
	public static function variant_b_page_id( array $cfg ) {
		return RWGO_Experiment_Service::page_id_for_variant( $cfg, 'var_b' );
	}

	/**
	 * Copy Variant B into Control, mark test completed.
	 *
	 * @param int $experiment_post_id Experiment CPT ID.
	 * @return true|\WP_Error
	 */
	public static function promote_variant_to_control( $experiment_post_id ) {
		if ( ! class_exists( 'RWGO_Promotion_Service', false ) ) {
			return new \WP_Error( 'rwgo_promote_missing', __( 'Promotion service is unavailable.', 'reactwoo-geo-optimise' ) );
		}
		$r = RWGO_Promotion_Service::run(
			(int) $experiment_post_id,
			array(
				'mode'             => RWGO_Promotion_Service::MODE_REPLACE_CONTENT,
				'variant_action'   => RWGO_Promotion_Service::VARIANT_ARCHIVE_REDIRECT,
				'copy_post_title'  => true,
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return true;
	}

	/**
	 * Remove Variant B from the test (optional trash of the page).
	 *
	 * @param int  $experiment_post_id Experiment CPT ID.
	 * @param bool $delete_page        If true, move Variant B post to trash.
	 * @return true|\WP_Error
	 */
	public static function detach_variant_b( $experiment_post_id, $delete_page = false ) {
		$experiment_post_id = (int) $experiment_post_id;
		if ( $experiment_post_id <= 0 || ! current_user_can( 'edit_post', $experiment_post_id ) ) {
			return new \WP_Error( 'rwgo_detach_perm', __( 'You cannot edit this test.', 'reactwoo-geo-optimise' ) );
		}
		$cfg = RWGO_Experiment_Repository::get_config( $experiment_post_id );
		$b   = self::variant_b_page_id( $cfg );
		$variants = isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : array();
		$variants = RWGO_Admin_Wizard::patch_variants_var_b( $variants, 0 );

		if ( $delete_page && $b > 0 && current_user_can( 'delete_post', $b ) ) {
			wp_trash_post( $b );
		}

		RWGO_Experiment_Repository::save_config(
			$experiment_post_id,
			array(
				'variants'         => $variants,
				'status'           => 'paused',
				'variant_detached' => true,
			)
		);
		return true;
	}

	/**
	 * Create a fresh duplicate from Control and attach as Variant B.
	 *
	 * @param int $experiment_post_id Experiment CPT ID.
	 * @return int|\WP_Error New Variant B ID or error.
	 */
	public static function regenerate_variant_b( $experiment_post_id ) {
		$experiment_post_id = (int) $experiment_post_id;
		if ( $experiment_post_id <= 0 || ! current_user_can( 'edit_post', $experiment_post_id ) ) {
			return new \WP_Error( 'rwgo_regen_perm', __( 'You cannot edit this test.', 'reactwoo-geo-optimise' ) );
		}
		$cfg = RWGO_Experiment_Repository::get_config( $experiment_post_id );
		$src = (int) ( $cfg['source_page_id'] ?? 0 );
		if ( $src <= 0 || ! current_user_can( 'edit_post', $src ) ) {
			return new \WP_Error( 'rwgo_regen_source', __( 'Control page is missing or not editable.', 'reactwoo-geo-optimise' ) );
		}
		$old_b = self::variant_b_page_id( $cfg );
		$new   = RWGO_Page_Duplicator::duplicate_page( $src );
		if ( is_wp_error( $new ) ) {
			return $new;
		}
		$new_id = (int) $new;
		$variants = isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : RWGO_Experiment_Service::default_variants( $src, 0 );
		$variants = RWGO_Admin_Wizard::patch_variants_var_b( $variants, $new_id );

		RWGO_Experiment_Repository::save_config(
			$experiment_post_id,
			array(
				'variants'          => $variants,
				'variant_creation'  => 'duplicate',
				'variant_regenerated_at' => gmdate( 'c' ),
			)
		);

		$trash_old = (bool) apply_filters( 'rwgo_trash_old_variant_on_regenerate', true, $old_b, $experiment_post_id );
		if ( $trash_old && $old_b > 0 && $old_b !== $new_id && current_user_can( 'delete_post', $old_b ) ) {
			wp_trash_post( $old_b );
		}

		return $new_id;
	}
}
