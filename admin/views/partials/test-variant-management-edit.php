<?php
/**
 * Variant management block for Edit Test (operations + advanced actions).
 *
 * Expects: $rwgo_exp_id, $rwgo_cfg, $rwgo_return_ctx, $var_b_id, $src_id, $rwgo_edit_redirect, $rwgo_mgmt_completed (bool).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_exp_id          = isset( $rwgo_exp_id ) ? (int) $rwgo_exp_id : 0;
$rwgo_return_ctx      = isset( $rwgo_return_ctx ) ? sanitize_key( (string) $rwgo_return_ctx ) : '';
$rwgo_mgmt_completed  = ! empty( $rwgo_mgmt_completed );
$var_b_id             = isset( $var_b_id ) ? (int) $var_b_id : 0;
$src_id               = isset( $src_id ) ? (int) $src_id : 0;
$rwgo_edit_redirect   = isset( $rwgo_edit_redirect ) ? (string) $rwgo_edit_redirect : '';
$rwgo_cfg             = isset( $rwgo_cfg ) && is_array( $rwgo_cfg ) ? $rwgo_cfg : array();

if ( $rwgo_exp_id <= 0 || ! class_exists( 'RWGO_Variant_Lifecycle', false ) || $rwgo_mgmt_completed ) {
	return;
}
?>
<section class="rwgo-card rwgo-section rwgo-section--variant-mgmt" id="rwgo-variant-management" aria-labelledby="rwgo-variant-mgmt-title">
	<h2 class="rwgo-section__title" id="rwgo-variant-mgmt-title"><?php esc_html_e( 'Variant management', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="rwgo-section__lead"><?php esc_html_e( 'Promote a winning variant, recreate Variant B from Control, or remove Variant B from this test.', 'reactwoo-geo-optimise' ); ?></p>
	<?php if ( $var_b_id > 0 && get_post( $var_b_id ) ) : ?>
		<div class="rwgo-btn-row rwgo-btn-row--wrap">
			<?php if ( class_exists( 'RWGO_Admin', false ) ) : ?>
				<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( RWGO_Admin::promote_winner_url( $rwgo_exp_id, $rwgo_return_ctx ) ); ?>"><?php esc_html_e( 'Promote Winner', 'reactwoo-geo-optimise' ); ?></a>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
				<?php wp_nonce_field( 'rwgo_regenerate_variant' ); ?>
				<input type="hidden" name="action" value="rwgo_regenerate_variant" />
				<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $rwgo_exp_id; ?>" />
				<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $rwgo_edit_redirect ); ?>" />
				<button type="submit" class="button rwgo-btn rwgo-btn--secondary" onclick="return confirm('<?php echo esc_js( __( 'Create a fresh Variant B from Control? The current Variant B page will be moved to trash if your role allows it.', 'reactwoo-geo-optimise' ) ); ?>');">
					<?php esc_html_e( 'Regenerate Variant B', 'reactwoo-geo-optimise' ); ?>
				</button>
			</form>
		</div>
		<p class="rwgo-hint rwgo-hint--spaced"><?php esc_html_e( 'Remove Variant B from this test:', 'reactwoo-geo-optimise' ); ?></p>
		<p class="description"><?php esc_html_e( 'If you move the variant page to Trash, WordPress keeps its URL slug reserved until the page is permanently deleted or the trash is emptied — future duplicates may use a numbered slug instead.', 'reactwoo-geo-optimise' ); ?></p>
		<div class="rwgo-btn-row rwgo-btn-row--wrap">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
				<?php wp_nonce_field( 'rwgo_detach_variant' ); ?>
				<input type="hidden" name="action" value="rwgo_detach_variant" />
				<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $rwgo_exp_id; ?>" />
				<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $rwgo_edit_redirect ); ?>" />
				<input type="hidden" name="rwgo_detach_mode" value="keep" />
				<button type="submit" class="button rwgo-btn rwgo-btn--secondary" onclick="return confirm('<?php echo esc_js( __( 'Remove Variant B from this test only? The page will stay in the site.', 'reactwoo-geo-optimise' ) ); ?>');">
					<?php esc_html_e( 'Remove from test (keep page)', 'reactwoo-geo-optimise' ); ?>
				</button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
				<?php wp_nonce_field( 'rwgo_detach_variant' ); ?>
				<input type="hidden" name="action" value="rwgo_detach_variant" />
				<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $rwgo_exp_id; ?>" />
				<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $rwgo_edit_redirect ); ?>" />
				<input type="hidden" name="rwgo_detach_mode" value="delete" />
				<button type="submit" class="button rwgo-btn rwgo-btn--danger-outline" onclick="return confirm('<?php echo esc_js( __( 'Remove Variant B from this test and move the Variant B page to trash?', 'reactwoo-geo-optimise' ) ); ?>');">
					<?php esc_html_e( 'Remove and delete page', 'reactwoo-geo-optimise' ); ?>
				</button>
			</form>
		</div>
	<?php elseif ( $src_id > 0 && get_post( $src_id ) ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'This test has no linked Variant B. Create one by duplicating Control.', 'reactwoo-geo-optimise' ); ?></p></div>
		<div class="rwgo-btn-row">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
				<?php wp_nonce_field( 'rwgo_regenerate_variant' ); ?>
				<input type="hidden" name="action" value="rwgo_regenerate_variant" />
				<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $rwgo_exp_id; ?>" />
				<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $rwgo_edit_redirect ); ?>" />
				<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary" onclick="return confirm('<?php echo esc_js( __( 'Create Variant B as a new duplicate of Control?', 'reactwoo-geo-optimise' ) ); ?>');">
					<?php esc_html_e( 'Add Variant B (duplicate Control)', 'reactwoo-geo-optimise' ); ?>
				</button>
			</form>
		</div>
	<?php endif; ?>
</section>
