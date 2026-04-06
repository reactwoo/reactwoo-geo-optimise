<?php
/**
 * Promote Winner — Mode A (replace primary content) + post-promotion guidance.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_exp_id = isset( $_GET['rwgo_experiment_id'] ) ? absint( wp_unslash( $_GET['rwgo_experiment_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgo_done   = isset( $_GET['rwgo_promotion_done'] ) ? absint( wp_unslash( $_GET['rwgo_promotion_done'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgo_ret    = isset( $_GET['rwgo_return'] ) ? sanitize_key( wp_unslash( $_GET['rwgo_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $rwgo_ret, array( 'tests', 'reports' ), true ) ) {
	$rwgo_ret = '';
}

$rwgo_exp_post = $rwgo_exp_id > 0 ? get_post( $rwgo_exp_id ) : null;
$rwgo_cfg      = ( $rwgo_exp_post instanceof \WP_Post && class_exists( 'RWGO_Experiment_CPT', false ) && RWGO_Experiment_CPT::POST_TYPE === $rwgo_exp_post->post_type )
	? RWGO_Experiment_Repository::get_config( $rwgo_exp_id )
	: array();

$rwgo_load_error = false;
if ( ! $rwgo_exp_post instanceof \WP_Post || ! class_exists( 'RWGO_Experiment_CPT', false ) || RWGO_Experiment_CPT::POST_TYPE !== $rwgo_exp_post->post_type ) {
	$rwgo_load_error = true;
} elseif ( ! current_user_can( 'edit_post', $rwgo_exp_id ) ) {
	$rwgo_load_error = true;
} elseif ( empty( $rwgo_cfg['experiment_key'] ) ) {
	$rwgo_load_error = true;
}

$src_id   = (int) ( $rwgo_cfg['source_page_id'] ?? 0 );
$var_b_id = class_exists( 'RWGO_Variant_Lifecycle', false ) ? RWGO_Variant_Lifecycle::variant_b_page_id( $rwgo_cfg ) : 0;
$st       = isset( $rwgo_cfg['status'] ) ? sanitize_key( (string) $rwgo_cfg['status'] ) : '';

$exp_dist = isset( $exp_dist ) && is_array( $exp_dist ) ? $exp_dist : array();
$key_stats   = isset( $rwgo_cfg['experiment_key'] ) ? (string) $rwgo_cfg['experiment_key'] : '';
$analysis    = class_exists( 'RWGO_Winner_Service', false ) && '' !== $key_stats
	? RWGO_Winner_Service::analyze( $key_stats, $rwgo_cfg, $exp_dist )
	: array( 'assignment_only' => true, 'conversion_mode' => false, 'leading_variant' => null );
$lead_slug   = isset( $analysis['leading_variant'] ) ? (string) $analysis['leading_variant'] : '';
$assign_only = ! empty( $analysis['assignment_only'] );
$conv_mode   = ! empty( $analysis['conversion_mode'] );
$lead_promo_label = '';
if ( $lead_slug && isset( $rwgo_cfg['variants'] ) && is_array( $rwgo_cfg['variants'] ) ) {
	foreach ( $rwgo_cfg['variants'] as $row ) {
		if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
			continue;
		}
		if ( sanitize_key( (string) $row['variant_id'] ) === sanitize_key( $lead_slug ) ) {
			$lead_promo_label = isset( $row['variant_label'] ) ? (string) $row['variant_label'] : $lead_slug;
			break;
		}
	}
}
if ( '' === $lead_promo_label && $lead_slug ) {
	if ( 'control' === sanitize_key( $lead_slug ) ) {
		$lead_promo_label = __( 'Control', 'reactwoo-geo-optimise' );
	} elseif ( 'var_b' === sanitize_key( $lead_slug ) ) {
		$lead_promo_label = __( 'Variant B', 'reactwoo-geo-optimise' );
	} else {
		$lead_promo_label = $lead_slug;
	}
}

$rwgo_promo_log = null;
$rwgo_redirect  = null;
if ( $rwgo_done > 0 && class_exists( 'RWGO_Promotion_Log', false ) ) {
	$rwgo_promo_log = RWGO_Promotion_Log::get( $rwgo_done );
	if ( $rwgo_promo_log && isset( $rwgo_promo_log->redirect_id ) && (int) $rwgo_promo_log->redirect_id > 0 && class_exists( 'RWGO_Redirect_Store', false ) ) {
		$rwgo_redirect = RWGO_Redirect_Store::get_rule( (int) $rwgo_promo_log->redirect_id );
	}
}

$rwgo_fallback = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::promote_winner_url( $rwgo_exp_id, $rwgo_ret ) : '';
$rwgo_back_url = 'tests' === $rwgo_ret ? admin_url( 'admin.php?page=rwgo-tests' ) : ( 'reports' === $rwgo_ret ? admin_url( 'admin.php?page=rwgo-reports' ) : admin_url( 'admin.php?page=rwgo-tests' ) );

$rwgc_nav_current = 'rwgo-tests';
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--promote-winner">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Promote Winner', 'reactwoo-geo-optimise' ),
			__( 'Make the winning version live on your primary URL — safely and with optional redirects.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Promote Winner', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( $rwgo_load_error ) : ?>
		<div class="rwgc-card"><p><?php esc_html_e( 'This test could not be loaded or you do not have permission.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-tests' ) ); ?>"><?php esc_html_e( 'Back to Tests', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	<?php elseif ( 'completed' === $st ) : ?>
		<div class="rwgc-card"><p><?php esc_html_e( 'This test is already completed.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::edit_test_url( $rwgo_exp_id, $rwgo_ret ) : '' ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	<?php elseif ( $rwgo_done > 0 && ! $rwgo_promo_log ) : ?>
		<div class="rwgo-card rwgo-section">
			<h2 class="rwgo-section__title"><?php esc_html_e( 'Promotion recorded', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'The test was completed. Detailed promotion metadata could not be loaded (it may have been pruned).', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-cta-row"><a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $rwgo_back_url ); ?>"><?php esc_html_e( 'Done', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	<?php elseif ( $rwgo_done > 0 && $rwgo_promo_log ) : ?>
		<div class="rwgo-card rwgo-section rwgo-promote-success">
			<h2 class="rwgo-section__title"><?php esc_html_e( 'Winner promoted successfully', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-section__lead"><?php esc_html_e( 'The primary page is now live with the winning content.', 'reactwoo-geo-optimise' ); ?></p>

			<h3 class="rwgo-promote-success__h"><?php esc_html_e( 'What Geo Optimise handled', 'reactwoo-geo-optimise' ); ?></h3>
			<ul class="rwgo-checklist">
				<li><?php esc_html_e( 'Primary page kept its original URL (canonical / SEO stable).', 'reactwoo-geo-optimise' ); ?></li>
				<li><?php esc_html_e( 'Winning page content and builder data were copied into the primary page.', 'reactwoo-geo-optimise' ); ?></li>
				<li><?php esc_html_e( 'Test was marked completed — routing stops for this experiment.', 'reactwoo-geo-optimise' ); ?></li>
				<?php if ( $rwgo_redirect && ! empty( $rwgo_redirect->active ) ) : ?>
					<li><?php esc_html_e( 'A managed redirect was created from the former variant URL to the primary URL.', 'reactwoo-geo-optimise' ); ?></li>
				<?php elseif ( $rwgo_promo_log && isset( $rwgo_promo_log->variant_action ) && 'leave' === (string) $rwgo_promo_log->variant_action ) : ?>
					<li><?php esc_html_e( 'Variant page was left unchanged (per your choice).', 'reactwoo-geo-optimise' ); ?></li>
				<?php endif; ?>
			</ul>

			<h3 class="rwgo-promote-success__h"><?php esc_html_e( 'What you should review manually', 'reactwoo-geo-optimise' ); ?></h3>
			<p class="rwgo-hint"><?php esc_html_e( 'Geo Optimise cannot detect every place you may have used the variant URL. Review menus, CTAs, emails, and campaigns so they point at the final live page.', 'reactwoo-geo-optimise' ); ?></p>
			<ul class="rwgo-checklist rwgo-checklist--muted">
				<li><?php esc_html_e( 'Internal links that pointed directly at the variant URL', 'reactwoo-geo-optimise' ); ?></li>
				<li><?php esc_html_e( 'Menu items and buttons', 'reactwoo-geo-optimise' ); ?></li>
				<li><?php esc_html_e( 'Email or ad destinations', 'reactwoo-geo-optimise' ); ?></li>
				<li><?php esc_html_e( 'External links if the variant was shared publicly', 'reactwoo-geo-optimise' ); ?></li>
			</ul>

			<?php if ( $rwgo_redirect ) : ?>
				<h3 class="rwgo-promote-success__h"><?php esc_html_e( 'Redirects created', 'reactwoo-geo-optimise' ); ?></h3>
				<table class="widefat striped rwgo-table-comfortable">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source path', 'reactwoo-geo-optimise' ); ?></th>
							<th><?php esc_html_e( 'Target', 'reactwoo-geo-optimise' ); ?></th>
							<th><?php esc_html_e( 'Status', 'reactwoo-geo-optimise' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'reactwoo-geo-optimise' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code><?php echo esc_html( (string) $rwgo_redirect->source_path ); ?></code></td>
							<td><a href="<?php echo esc_url( (string) $rwgo_redirect->target_url ); ?>"><?php echo esc_html( (string) $rwgo_redirect->target_url ); ?></a></td>
							<td><?php echo ! empty( $rwgo_redirect->active ) ? esc_html__( 'Active', 'reactwoo-geo-optimise' ) : esc_html__( 'Disabled', 'reactwoo-geo-optimise' ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
									<?php wp_nonce_field( 'rwgo_redirect_rule_toggle' ); ?>
									<input type="hidden" name="action" value="rwgo_redirect_rule_toggle" />
									<input type="hidden" name="rwgo_redirect_id" value="<?php echo (int) $rwgo_redirect->id; ?>" />
									<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $rwgo_fallback ); ?>" />
									<button type="submit" class="button button-small"><?php echo ! empty( $rwgo_redirect->active ) ? esc_html__( 'Disable redirect', 'reactwoo-geo-optimise' ) : esc_html__( 'Enable redirect', 'reactwoo-geo-optimise' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this redirect rule?', 'reactwoo-geo-optimise' ) ); ?>');">
									<?php wp_nonce_field( 'rwgo_redirect_rule_delete' ); ?>
									<input type="hidden" name="action" value="rwgo_redirect_rule_delete" />
									<input type="hidden" name="rwgo_redirect_id" value="<?php echo (int) $rwgo_redirect->id; ?>" />
									<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $rwgo_fallback ); ?>" />
									<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Remove', 'reactwoo-geo-optimise' ); ?></button>
								</form>
							</td>
						</tr>
					</tbody>
				</table>
			<?php endif; ?>

			<p class="rwgo-cta-row" style="margin-top:16px;">
				<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $rwgo_back_url ); ?>"><?php esc_html_e( 'Done', 'reactwoo-geo-optimise' ); ?></a>
				<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::edit_test_url( $rwgo_exp_id, $rwgo_ret ) : '' ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-card rwgo-section">
			<?php wp_nonce_field( 'rwgo_promote_variant' ); ?>
			<input type="hidden" name="action" value="rwgo_promote_variant" />
			<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $rwgo_exp_id; ?>" />
			<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $rwgo_fallback ); ?>" />
			<input type="hidden" name="rwgo_promotion_mode" value="replace_content" />

			<h2 class="rwgo-section__title"><?php esc_html_e( 'Promote winning variant', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Choose how you want to make the winning version live.', 'reactwoo-geo-optimise' ); ?></p>

			<?php if ( $conv_mode && $lead_slug ) : ?>
				<p class="rwgo-hint"><?php echo esc_html( sprintf( /* translators: %s: variant label */ __( 'Current leading variant by total conversions: %s', 'reactwoo-geo-optimise' ), $lead_promo_label ) ); ?></p>
			<?php endif; ?>

			<fieldset class="rwgo-fieldset">
				<legend class="rwgo-field__label"><?php esc_html_e( 'Promotion mode', 'reactwoo-geo-optimise' ); ?></legend>
				<label class="rwgo-radio-line">
					<input type="radio" name="rwgo_promotion_mode_ui" value="replace" checked="checked" disabled="disabled" />
					<span><strong><?php esc_html_e( 'Replace primary content (recommended)', 'reactwoo-geo-optimise' ); ?></strong> — <?php esc_html_e( 'Keep the current primary page URL and replace its content with the winning version.', 'reactwoo-geo-optimise' ); ?></span>
				</label>
				<p class="description"><?php esc_html_e( 'Advanced “promote winning URL / slug” is not available in this release — it requires strict slug ordering to avoid WordPress auto-renaming (-2, -3).', 'reactwoo-geo-optimise' ); ?></p>
			</fieldset>

			<div class="rwgo-field">
				<label class="rwgo-field__label" for="rwgo_copy_post_title"><?php esc_html_e( 'Primary page title after promotion', 'reactwoo-geo-optimise' ); ?></label>
				<select name="rwgo_copy_post_title" id="rwgo_copy_post_title" class="rwgo-input">
					<option value="1" selected="selected"><?php esc_html_e( 'Use winning page title', 'reactwoo-geo-optimise' ); ?></option>
					<option value="0"><?php esc_html_e( 'Keep current primary title', 'reactwoo-geo-optimise' ); ?></option>
				</select>
			</div>

			<h3 class="rwgo-section__title"><?php esc_html_e( 'What should happen to the winning variant page?', 'reactwoo-geo-optimise' ); ?></h3>
			<fieldset class="rwgo-fieldset">
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_disposal" value="<?php echo esc_attr( RWGO_Promotion_Service::VARIANT_ARCHIVE_REDIRECT ); ?>" checked="checked" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Keep archived and redirect to primary (recommended)', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Saves the variant as draft and creates a managed redirect from the old variant URL to the primary URL.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_disposal" value="<?php echo esc_attr( RWGO_Promotion_Service::VARIANT_ARCHIVE_NO_REDIRECT ); ?>" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Keep archived without redirect', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Draft the variant only — no automatic redirect.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_disposal" value="<?php echo esc_attr( RWGO_Promotion_Service::VARIANT_TRASH_REDIRECT ); ?>" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Move to trash after creating redirect', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Creates the redirect first (path stored in the redirect table), then moves the variant to trash.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_disposal" value="<?php echo esc_attr( RWGO_Promotion_Service::VARIANT_LEAVE ); ?>" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Leave unchanged', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Do not change the variant page — you manage cleanup manually.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
			</fieldset>

			<?php
			if ( $src_id > 0 && $var_b_id > 0 ) :
				$pu = get_permalink( $src_id );
				$vu = get_permalink( $var_b_id );
				?>
				<p class="rwgo-hint"><?php esc_html_e( 'Primary (canonical):', 'reactwoo-geo-optimise' ); ?> <?php echo $pu ? '<code>' . esc_html( $pu ) . '</code>' : '—'; ?></p>
				<p class="rwgo-hint"><?php esc_html_e( 'Variant:', 'reactwoo-geo-optimise' ); ?> <?php echo $vu ? '<code>' . esc_html( $vu ) . '</code>' : '—'; ?></p>
			<?php endif; ?>

			<p class="rwgo-cta-row">
				<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary" onclick="return confirm('<?php echo esc_js( __( 'Promote the winning content to the primary page and complete this test?', 'reactwoo-geo-optimise' ) ); ?>');"><?php esc_html_e( 'Promote Winner', 'reactwoo-geo-optimise' ); ?></button>
				<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $rwgo_back_url ); ?>"><?php esc_html_e( 'Cancel', 'reactwoo-geo-optimise' ); ?></a>
			</p>
		</form>
	<?php endif; ?>
</div>
