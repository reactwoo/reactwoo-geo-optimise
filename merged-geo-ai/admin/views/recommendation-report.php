<?php
/**
 * Grouped recommendation report (journey mode).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_report_html = isset( $rwga_report_html ) ? (string) $rwga_report_html : '';
$rwga_analysis_run_id = isset( $rwga_analysis_run_id ) ? (int) $rwga_analysis_run_id : 0;
$rwga_recommendation_count = isset( $rwga_recommendation_count ) ? (int) $rwga_recommendation_count : 0;
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-recommendations';
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--recommendation-report">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Recommendation report', 'reactwoo-geo-ai' ),
			__( 'Review grouped recommendations, then generate implementation drafts to continue.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Recommendation report', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>
	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>

	<?php
	$rwga_impl_flag = isset( $_GET['rwga_impl'] ) ? sanitize_key( wp_unslash( $_GET['rwga_impl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'nodrafts' === $rwga_impl_flag ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No implementation drafts were created. Check that recommendations still exist, your license allows workflows, then try again.', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	?>

	<div class="rwgc-actions rwgc-actions--stack-mobile" style="margin-bottom:16px;">
		<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-analyses&run_id=' . (int) $rwga_analysis_run_id ) ); ?>"><?php esc_html_e( 'View analysis', 'reactwoo-geo-ai' ); ?></a>
		<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-recommendations' ) ); ?>"><?php esc_html_e( 'Open recommendation library', 'reactwoo-geo-ai' ); ?></a>
	</div>

	<div class="rwgc-card rwga-report-content">
		<?php if ( '' !== $rwga_report_html ) : ?>
			<div class="rwga-report-html"><?php echo wp_kses_post( $rwga_report_html ); ?></div>
		<?php else : ?>
			<p><?php esc_html_e( 'No grouped recommendation report was generated yet.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
		<?php if ( $rwga_recommendation_count > 0 ) : ?>
	<div class="rwgc-card rwgc-card--highlight" id="rwga-generate-implementation">
		<h2><?php esc_html_e( 'Generate implementation drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Continue the journey by generating section-aware implementation drafts.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwga_bulk_implement_analysis" />
			<input type="hidden" name="analysis_run_id" value="<?php echo (int) $rwga_analysis_run_id; ?>" />
			<?php wp_nonce_field( 'rwga_bulk_implement_analysis' ); ?>
			<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Generate implementation drafts', 'reactwoo-geo-ai' ); ?></button>
		</form>
	</div>
		<?php else : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Generate implementation drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'There are no recommendations saved for this analysis run (they may have been deleted). Generate a recommendation report from the analysis first, then return here.', 'reactwoo-geo-ai' ); ?></p>
		<p><a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-analyses&run_id=' . (int) $rwga_analysis_run_id . '&journey=1#rwga-generate-recommendations' ) ); ?>"><?php esc_html_e( 'Open analysis report', 'reactwoo-geo-ai' ); ?></a></p>
	</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

