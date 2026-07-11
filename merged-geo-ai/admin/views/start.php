<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwga_summary          = isset( $rwga_summary ) && is_array( $rwga_summary ) ? $rwga_summary : array();
$rwga_analysis_preview = isset( $rwga_analysis_preview ) && is_array( $rwga_analysis_preview ) ? $rwga_analysis_preview : array();
$rwgc_nav_current      = isset( $rwgc_nav_current ) ? $rwgc_nav_current : RWGA_Admin::MENU_PARENT;
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--start">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header( __( 'Start an optimisation workflow', 'reactwoo-geo-ai' ), __( 'Choose what to improve, run analysis, then generate recommendations and implementation drafts.', 'reactwoo-geo-ai' ) );
		RWGC_Admin_UI::render_provider_badge( 'geo_ai' );
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Start', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>
	<p><a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . RWGA_Admin::MENU_PARENT . '&rwga_mode=admin' ) ); ?>"><?php esc_html_e( 'Switch to Admin mode', 'reactwoo-geo-ai' ); ?></a></p>
	<?php settings_errors( 'rwga_geo_ai' ); ?>
	<?php RWGA_Admin::render_usage_refresh_notices(); ?>

	<?php RWGA_Admin::render_ux_review_entry_card( 'start' ); ?>

	<div class="rwgc-card rwga-wizard">
		<ul class="rwga-progress">
			<li class="is-active"><?php esc_html_e( '1. Select asset', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( '2. Choose focus', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( '3. Run analysis', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( '4. Recommendations', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( '5. Implement', 'reactwoo-geo-ai' ); ?></li>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-form-grid">
			<input type="hidden" name="action" value="rwga_start_workflow" />
			<?php wp_nonce_field( 'rwga_start_workflow' ); ?>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_asset_type"><?php esc_html_e( 'What do you want to improve?', 'reactwoo-geo-ai' ); ?></label>
				<select name="asset_type" id="rwga_asset_type" class="rwgc-select rwgc-input">
					<option value="page"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></option>
					<option value="product"><?php esc_html_e( 'Product', 'reactwoo-geo-ai' ); ?></option>
					<option value="variant"><?php esc_html_e( 'Variant page', 'reactwoo-geo-ai' ); ?></option>
					<option value="competitor"><?php esc_html_e( 'Competitor URL', 'reactwoo-geo-ai' ); ?></option>
				</select>
			</div>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_asset_id"><?php esc_html_e( 'Select page/product (optional for competitor)', 'reactwoo-geo-ai' ); ?></label>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'asset_id',
						'id'                => 'rwga_asset_id',
						'show_option_none'  => __( '— Select page/product —', 'reactwoo-geo-ai' ),
						'option_none_value' => '0',
						'class'             => 'rwgc-select rwgc-input',
					)
				);
				?>
			</div>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_competitor_url"><?php esc_html_e( 'Competitor URL (if competitor selected)', 'reactwoo-geo-ai' ); ?></label>
				<input type="url" name="competitor_url" id="rwga_competitor_url" class="rwgc-input" placeholder="https://example.com" />
			</div>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_analysis_focus"><?php esc_html_e( 'Analysis focus', 'reactwoo-geo-ai' ); ?></label>
				<select name="analysis_focus" id="rwga_analysis_focus" class="rwgc-select rwgc-input">
					<option value="messaging"><?php esc_html_e( 'Copy', 'reactwoo-geo-ai' ); ?></option>
					<option value="layout"><?php esc_html_e( 'Layout', 'reactwoo-geo-ai' ); ?></option>
					<option value="both"><?php esc_html_e( 'Copy + Layout', 'reactwoo-geo-ai' ); ?></option>
				</select>
			</div>
			<div class="rwgc-field">
				<span class="rwgc-field__label"><?php esc_html_e( 'Target country (optional)', 'reactwoo-geo-ai' ); ?></span>
				<?php if ( class_exists( 'RWGC_Admin', false ) ) : ?>
					<?php RWGC_Admin::render_country_select( 'geo_target', '', array( 'id' => 'rwga_start_geo', 'class' => 'rwgc-select rwgc-input', 'show_option_none' => __( '— Any / default —', 'reactwoo-geo-ai' ), 'option_none_value' => '' ) ); ?>
				<?php else : ?>
					<input type="text" maxlength="2" class="rwgc-input" name="geo_target" id="rwga_start_geo" value="" />
				<?php endif; ?>
			</div>
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Run analysis', 'reactwoo-geo-ai' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Nothing is published automatically.', 'reactwoo-geo-ai' ); ?></p>
		</form>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Recent reports', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_analysis_preview ) ) : ?>
			<p class="description"><?php esc_html_e( 'No reports yet.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $rwga_analysis_preview as $row ) : ?>
					<?php $id = isset( $row['id'] ) ? (int) $row['id'] : 0; ?>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-analyses&run_id=' . $id ) ); ?>"><?php echo esc_html( sprintf( __( 'Analysis #%d', 'reactwoo-geo-ai' ), $id ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>

