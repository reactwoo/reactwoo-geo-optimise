<?php
/**
 * Workflow-first implementation review screen.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_drafts = isset( $rwga_drafts ) && is_array( $rwga_drafts ) ? $rwga_drafts : array();
$rwga_workflow_state = isset( $rwga_workflow_state ) && is_array( $rwga_workflow_state ) ? $rwga_workflow_state : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-implementation-drafts';
$page_id = isset( $rwga_workflow_state['asset_id'] ) ? (int) $rwga_workflow_state['asset_id'] : 0;
$variant_page_id = isset( $rwga_workflow_state['variant_page_id'] ) ? (int) $rwga_workflow_state['variant_page_id'] : 0;
$analysis_run_id = isset( $rwga_workflow_state['analysis_run_id'] ) ? (int) $rwga_workflow_state['analysis_run_id'] : 0;
$draft_ids = array();
foreach ( $rwga_drafts as $draft ) {
	if ( is_array( $draft ) && ! empty( $draft['id'] ) ) {
		$draft_ids[] = (int) $draft['id'];
	}
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--implementation-review">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Implementation review', 'reactwoo-geo-ai' ),
			__( 'Review generated changes, then apply to the current page or send a variant to testing.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Implementation review', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>
	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>
	<p><a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-implementation-drafts' ) ); ?>"><?php esc_html_e( 'Open draft library', 'reactwoo-geo-ai' ); ?></a></p>

	<?php
	$rwga_apply = isset( $_GET['rwga_apply'] ) ? sanitize_key( wp_unslash( $_GET['rwga_apply'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$rwga_variant = isset( $_GET['rwga_variant'] ) ? sanitize_key( wp_unslash( $_GET['rwga_variant'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_apply ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Changes applied to the current page.', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	if ( 'ok' === $rwga_variant ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Variant draft created and ready for testing.', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	if ( ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ) ) . '</p></div>';
	}
	$rwga_impl_flag = isset( $_GET['rwga_impl'] ) ? sanitize_key( wp_unslash( $_GET['rwga_impl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_impl_flag ) {
		$n = isset( $_GET['rwga_draft_count'] ) ? (int) $_GET['rwga_draft_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $n > 0 ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( 'Generated %d implementation draft.', 'Generated %d implementation drafts.', $n, 'reactwoo-geo-ai' ), $n ) ) . '</p></div>';
		} else {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No new drafts were found for this visit. If you expected drafts, regenerate from the recommendation report.', 'reactwoo-geo-ai' ) . '</p></div>';
		}
	}
	if ( 'nodrafts' === $rwga_impl_flag ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Draft generation produced no rows. Check recommendations and try again from the recommendation report.', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	?>

	<div class="rwgc-card rwga-report-content">
		<h2><?php esc_html_e( 'Generated implementation report', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_drafts ) ) : ?>
			<p><?php esc_html_e( 'No drafts were generated yet. Go back and generate implementation drafts first.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<?php foreach ( $rwga_drafts as $draft ) : ?>
				<?php
				$payload = isset( $draft['draft_payload'] ) ? json_decode( (string) $draft['draft_payload'], true ) : array();
				$payload = is_array( $payload ) ? $payload : array();
				$title   = isset( $draft['title'] ) ? (string) $draft['title'] : __( 'Section', 'reactwoo-geo-ai' );
				?>
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><strong><?php esc_html_e( 'Issue addressed:', 'reactwoo-geo-ai' ); ?></strong> <?php esc_html_e( 'Mapped from recommendation context', 'reactwoo-geo-ai' ); ?></p>
				<?php foreach ( $payload as $k => $v ) : ?>
					<h4><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $k ) ) ); ?></h4>
					<?php echo wp_kses_post( wpautop( esc_html( is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) ) ) ); ?>
				<?php endforeach; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="rwgc-card rwga-implementation-actions">
		<h2><?php esc_html_e( 'Apply your changes', 'reactwoo-geo-ai' ); ?></h2>
		<div class="rwgc-actions rwgc-actions--stack-mobile">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rwga_apply_drafts_to_live" />
				<input type="hidden" name="page_id" value="<?php echo (int) $page_id; ?>" />
				<input type="hidden" name="draft_ids" value="<?php echo esc_attr( implode( ',', $draft_ids ) ); ?>" />
				<?php wp_nonce_field( 'rwga_apply_drafts_to_live' ); ?>
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Apply to current page', 'reactwoo-geo-ai' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rwga_create_variant_from_drafts" />
				<input type="hidden" name="page_id" value="<?php echo (int) $page_id; ?>" />
				<input type="hidden" name="draft_ids" value="<?php echo esc_attr( implode( ',', $draft_ids ) ); ?>" />
				<?php wp_nonce_field( 'rwga_create_variant_from_drafts' ); ?>
				<button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Create a new variant draft', 'reactwoo-geo-ai' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rwga_send_variant_to_geo_optimise" />
				<input type="hidden" name="variant_page_id" value="<?php echo (int) $variant_page_id; ?>" />
				<input type="hidden" name="analysis_run_id" value="<?php echo (int) $analysis_run_id; ?>" />
				<?php wp_nonce_field( 'rwga_send_variant_to_geo_optimise' ); ?>
				<button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Send to Geo Optimise for testing', 'reactwoo-geo-ai' ); ?></button>
			</form>
		</div>
	</div>
</div>

