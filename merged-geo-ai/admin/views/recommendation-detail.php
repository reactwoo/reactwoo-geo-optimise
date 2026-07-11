<?php
/**
 * Single recommendation detail.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rec         = isset( $rwga_rec ) && is_array( $rwga_rec ) ? $rwga_rec : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-recommendations';

$rec_id = isset( $rwga_rec['id'] ) ? (int) $rwga_rec['id'] : 0;
$list_url = admin_url( 'admin.php?page=rwga-recommendations' );

$analysis_run_id = isset( $rwga_analysis_run_id ) ? (int) $rwga_analysis_run_id : ( isset( $rwga_rec['analysis_run_id'] ) ? (int) $rwga_rec['analysis_run_id'] : 0 );
$page_id         = isset( $rwga_rec['page_id'] ) ? (int) $rwga_rec['page_id'] : 0;
$geo             = isset( $rwga_rec['geo_target'] ) ? (string) $rwga_rec['geo_target'] : '';
$rec_title       = isset( $rwga_rec['title'] ) ? (string) $rwga_rec['title'] : '';
$confidence      = isset( $rwga_rec['confidence'] ) && null !== $rwga_rec['confidence'] ? $rwga_rec['confidence'] : null;
$exp_impact      = isset( $rwga_rec['expected_impact'] ) ? (string) $rwga_rec['expected_impact'] : '';
$rwga_report_html = isset( $rwga_report_html ) ? (string) $rwga_report_html : '';
$is_grouped_report = '' !== $rwga_report_html;
$rwga_same_run_rec_count = isset( $rwga_same_run_rec_count ) ? (int) $rwga_same_run_rec_count : 0;
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--recommendation-detail">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			'' !== $rec_title ? $rec_title : sprintf(
				/* translators: %d: recommendation id */
				__( 'Recommendation #%d', 'reactwoo-geo-ai' ),
				$rec_id
			),
			__( 'Review this recommendation report, then generate implementation drafts as your next step.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php echo esc_html( sprintf( __( 'Recommendation #%d', 'reactwoo-geo-ai' ), $rec_id ) ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>

	<div class="rwgc-actions rwgc-actions--stack-mobile" style="margin-bottom: 16px;">
		<a href="<?php echo esc_url( $list_url ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( '← All recommendations', 'reactwoo-geo-ai' ); ?></a>
		<?php if ( $analysis_run_id > 0 ) : ?>
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-analyses', 'run_id' => $analysis_run_id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View analysis', 'reactwoo-geo-ai' ); ?></a>
		<?php endif; ?>
		<?php if ( $page_id > 0 && current_user_can( 'edit_post', $page_id ) ) : ?>
			<?php
			$edit = get_edit_post_link( $page_id, 'raw' );
			if ( is_string( $edit ) && '' !== $edit ) {
				echo '<a class="rwgc-btn rwgc-btn--primary" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit page', 'reactwoo-geo-ai' ) . '</a>';
			}
			?>
		<?php endif; ?>
	</div>

	<div class="rwgc-card rwga-analysis-meta rwga-report-content">
		<h2><?php esc_html_e( 'Recommendation report', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( '' !== $rwga_report_html ) : ?>
			<div class="rwga-report-html"><?php echo wp_kses_post( $rwga_report_html ); ?></div>
		<?php elseif ( ! empty( $rwga_rec['report_html'] ) ) : ?>
			<div class="rwga-report-html"><?php echo wp_kses_post( (string) $rwga_rec['report_html'] ); ?></div>
		<?php endif; ?>
		<?php if ( $is_grouped_report ) : ?>
		<details class="rwga-dev-details">
			<summary><?php esc_html_e( 'Technical details', 'reactwoo-geo-ai' ); ?></summary>
		<?php else : ?>
		<h3><?php esc_html_e( 'Summary', 'reactwoo-geo-ai' ); ?></h3>
		<?php endif; ?>
		<dl class="rwga-license-dl">
			<dt><?php esc_html_e( 'Priority', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_rec['priority_level'] ) ? esc_html( (string) $rwga_rec['priority_level'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Category', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_rec['category'] ) ? esc_html( (string) $rwga_rec['category'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Target country', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo '' !== $geo ? esc_html( $geo ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_rec['status'] ) ? esc_html( (string) $rwga_rec['status'] ) : '—'; ?></dd>
			<?php if ( null !== $confidence && '' !== (string) $confidence ) : ?>
			<dt><?php esc_html_e( 'Confidence', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo esc_html( (string) $confidence ); ?></dd>
			<?php endif; ?>
			<?php if ( '' !== $exp_impact ) : ?>
			<dt><?php esc_html_e( 'Expected impact', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo esc_html( $exp_impact ); ?></dd>
			<?php endif; ?>
			<dt><?php esc_html_e( 'Created (UTC)', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_rec['created_at'] ) ? esc_html( (string) $rwga_rec['created_at'] ) : '—'; ?></dd>
		</dl>
		<?php if ( $is_grouped_report ) : ?>
		</details>
		<?php endif; ?>

		<?php if ( empty( $rwga_rec['report_html'] ) ) : ?>
		<h3><?php esc_html_e( 'Problem', 'reactwoo-geo-ai' ); ?></h3>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_rec['problem'] ) ? wp_kses_post( wpautop( (string) $rwga_rec['problem'] ) ) : ''; ?></div>

		<?php if ( ! empty( $rwga_rec['why_it_matters'] ) ) : ?>
		<h3><?php esc_html_e( 'Why it matters', 'reactwoo-geo-ai' ); ?></h3>
		<div class="rwga-pre-wrap"><?php echo wp_kses_post( wpautop( (string) $rwga_rec['why_it_matters'] ) ); ?></div>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Suggested action', 'reactwoo-geo-ai' ); ?></h3>
		<div class="rwga-pre-wrap"><?php echo isset( $rwga_rec['recommendation'] ) ? wp_kses_post( wpautop( (string) $rwga_rec['recommendation'] ) ) : ''; ?></div>
		<?php endif; ?>
	</div>

	<?php if ( $is_grouped_report && $analysis_run_id > 0 && $rwga_same_run_rec_count > 0 && current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card rwgc-card--highlight" id="rwga-generate-implementation">
		<h2><?php esc_html_e( 'Generate implementation drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Generate section-aware implementation drafts from this recommendation report.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwga_bulk_implement_analysis" />
			<input type="hidden" name="analysis_run_id" value="<?php echo (int) $analysis_run_id; ?>" />
			<?php wp_nonce_field( 'rwga_bulk_implement_analysis' ); ?>
			<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Generate implementation drafts', 'reactwoo-geo-ai' ); ?></button>
		</form>
	</div>
	<?php elseif ( $is_grouped_report && $analysis_run_id > 0 && $rwga_same_run_rec_count <= 0 && current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Generate implementation drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'No recommendation records remain for this analysis (they may have been deleted). Open the analysis and generate a new recommendation report before creating drafts.', 'reactwoo-geo-ai' ); ?></p>
		<p><a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-analyses&run_id=' . (int) $analysis_run_id . '&journey=1#rwga-generate-recommendations' ) ); ?>"><?php esc_html_e( 'Open analysis report', 'reactwoo-geo-ai' ); ?></a></p>
	</div>
	<?php elseif ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card rwgc-card--highlight">
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_header(
				__( 'Generate implementation drafts', 'reactwoo-geo-ai' ),
				__( 'Create reviewable copy and SEO drafts from this recommendation. Nothing is published automatically.', 'reactwoo-geo-ai' )
			);
		} else {
			echo '<h2>' . esc_html__( 'Implementation drafts', 'reactwoo-geo-ai' ) . '</h2>';
		}
		?>
		<div class="rwgc-form-grid">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-field" style="margin-bottom: 16px;">
				<input type="hidden" name="action" value="rwga_copy_implement" />
				<input type="hidden" name="recommendation_id" value="<?php echo (int) $rec_id; ?>" />
				<?php if ( $page_id > 0 ) : ?>
					<input type="hidden" name="page_id" value="<?php echo (int) $page_id; ?>" />
				<?php endif; ?>
				<?php if ( '' !== $geo ) : ?>
					<input type="hidden" name="geo_target" value="<?php echo esc_attr( $geo ); ?>" />
				<?php endif; ?>
				<?php wp_nonce_field( 'rwga_copy_implement' ); ?>
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Generate copy drafts', 'reactwoo-geo-ai' ); ?></button>
				<p class="rwgc-field__hint"><?php esc_html_e( 'Hero, CTA, and trust-line ideas you can paste into the editor.', 'reactwoo-geo-ai' ); ?></p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-field">
				<input type="hidden" name="action" value="rwga_seo_implement" />
				<input type="hidden" name="recommendation_id" value="<?php echo (int) $rec_id; ?>" />
				<?php if ( $page_id > 0 ) : ?>
					<input type="hidden" name="page_id" value="<?php echo (int) $page_id; ?>" />
				<?php endif; ?>
				<?php if ( '' !== $geo ) : ?>
					<input type="hidden" name="geo_target" value="<?php echo esc_attr( $geo ); ?>" />
				<?php endif; ?>
				<?php wp_nonce_field( 'rwga_seo_implement' ); ?>
				<button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Generate SEO drafts', 'reactwoo-geo-ai' ); ?></button>
				<p class="rwgc-field__hint"><?php esc_html_e( 'Meta title, description, and a short checklist.', 'reactwoo-geo-ai' ); ?></p>
			</form>
		</div>
	</div>
	<?php elseif ( class_exists( 'RWGA_License', false ) && ! RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<p class="description"><?php esc_html_e( 'Add a Geo AI license key in Settings to generate implementation drafts.', 'reactwoo-geo-ai' ); ?></p>
		<p><a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>"><?php esc_html_e( 'Open Settings', 'reactwoo-geo-ai' ); ?></a></p>
	</div>
	<?php endif; ?>
	<div class="rwgc-card rwga-implementation-actions">
		<h2><?php esc_html_e( 'Choose how to implement', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use one primary next action to continue this workflow.', 'reactwoo-geo-ai' ); ?></p>
		<?php
		$state = class_exists( 'RWGA_Current_Workflow', false ) ? RWGA_Current_Workflow::get() : array();
		$draft_ids = isset( $state['draft_ids'] ) && is_array( $state['draft_ids'] ) ? implode( ',', array_map( 'intval', $state['draft_ids'] ) ) : '';
		$variant_page_id = isset( $state['variant_page_id'] ) ? (int) $state['variant_page_id'] : 0;
		$has_drafts = '' !== $draft_ids;
		?>
		<div class="rwgc-actions rwgc-actions--stack-mobile">
			<?php if ( ! $has_drafts ) : ?>
				<p class="description"><?php esc_html_e( 'Generate implementation drafts to unlock apply, variant, and testing actions.', 'reactwoo-geo-ai' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rwga_apply_drafts_to_live" />
					<input type="hidden" name="page_id" value="<?php echo (int) $page_id; ?>" />
					<input type="hidden" name="draft_ids" value="<?php echo esc_attr( $draft_ids ); ?>" />
					<?php wp_nonce_field( 'rwga_apply_drafts_to_live' ); ?>
					<button class="rwgc-btn rwgc-btn--primary" type="submit"><?php esc_html_e( 'Apply to current page', 'reactwoo-geo-ai' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rwga_create_variant_from_drafts" />
					<input type="hidden" name="page_id" value="<?php echo (int) $page_id; ?>" />
					<input type="hidden" name="draft_ids" value="<?php echo esc_attr( $draft_ids ); ?>" />
					<?php wp_nonce_field( 'rwga_create_variant_from_drafts' ); ?>
					<button class="rwgc-btn rwgc-btn--secondary" type="submit"><?php esc_html_e( 'Create variant draft', 'reactwoo-geo-ai' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rwga_send_variant_to_geo_optimise" />
					<input type="hidden" name="variant_page_id" value="<?php echo (int) $variant_page_id; ?>" />
					<input type="hidden" name="analysis_run_id" value="<?php echo (int) $analysis_run_id; ?>" />
					<?php wp_nonce_field( 'rwga_send_variant_to_geo_optimise' ); ?>
					<button class="rwgc-btn rwgc-btn--secondary" type="submit"><?php esc_html_e( 'Send variant to Geo Optimise', 'reactwoo-geo-ai' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>
</div>
