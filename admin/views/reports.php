<?php
/**
 * Reports — goal-based outcomes + assignment reach.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Friendly label for a variant slug from experiment config.
 *
 * @param array<string, mixed> $cfg          Experiment config.
 * @param string               $variant_slug Variant id from stats.
 * @return string
 */
$rwgo_variant_label = static function ( $cfg, $variant_slug ) {
	$slug = sanitize_key( (string) $variant_slug );
	if ( isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
		foreach ( $cfg['variants'] as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['variant_id'] ) ) {
				continue;
			}
			if ( sanitize_key( (string) $row['variant_id'] ) === $slug ) {
				return isset( $row['variant_label'] ) ? (string) $row['variant_label'] : $slug;
			}
		}
	}
	if ( 'control' === $slug ) {
		return __( 'Control', 'reactwoo-geo-optimise' );
	}
	if ( 'var_b' === $slug ) {
		return __( 'Variant B', 'reactwoo-geo-optimise' );
	}
	return (string) $variant_slug;
};

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-reports';
$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();
$exp_dist         = isset( $exp_dist ) && is_array( $exp_dist ) ? $exp_dist : array();
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--reports">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Reports', 'reactwoo-geo-optimise' ),
			__( 'See which variant leads on total conversions across your selected success goals — with a per-target breakdown below.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Reports', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['rwgo_promoted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible rwgo-notice"><p><?php esc_html_e( 'Variant B was copied into Control and this test was marked completed.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_error'] ) && 'promote' === sanitize_key( (string) wp_unslash( $_GET['rwgo_error'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error rwgo-notice"><p><?php esc_html_e( 'Could not promote Variant B to Control. Check permissions and that both pages exist.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $rwgo_experiments ) ) : ?>
		<div class="rwgc-card">
			<p><?php esc_html_e( 'No tests yet — create a test to see how variants perform on total conversions.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	<?php else : ?>
		<?php
		foreach ( $rwgo_experiments as $exp_post ) :
			if ( ! $exp_post instanceof \WP_Post ) {
				continue;
			}
			$cfg = RWGO_Experiment_Repository::get_config( $exp_post->ID );
			$key = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
			$st  = isset( $cfg['status'] ) ? (string) $cfg['status'] : '';

			$analysis = class_exists( 'RWGO_Winner_Service', false )
				? RWGO_Winner_Service::analyze( $key, $cfg, $exp_dist )
				: array(
					'assignment_only'   => true,
					'conversion_mode'   => false,
					'metric_label'      => '',
					'metric_description'=> '',
					'variants'          => array(),
					'leading_variant'   => null,
					'goal_breakdown'    => array(),
					'insight_line'      => '',
				);

			$assignment_only  = ! empty( $analysis['assignment_only'] );
			$conversion_mode  = ! empty( $analysis['conversion_mode'] );
			$metric_label     = isset( $analysis['metric_label'] ) ? (string) $analysis['metric_label'] : '';
			$metric_desc      = isset( $analysis['metric_description'] ) ? (string) $analysis['metric_description'] : '';
			$variants_rows    = isset( $analysis['variants'] ) && is_array( $analysis['variants'] ) ? $analysis['variants'] : array();
			$lead_slug        = isset( $analysis['leading_variant'] ) ? $analysis['leading_variant'] : null;
			$lead_label       = ( $conversion_mode && $lead_slug ) ? $rwgo_variant_label( $cfg, (string) $lead_slug ) : '';
			$goal_breakdown   = isset( $analysis['goal_breakdown'] ) && is_array( $analysis['goal_breakdown'] ) ? $analysis['goal_breakdown'] : array();
			$insight_line     = isset( $analysis['insight_line'] ) ? (string) $analysis['insight_line'] : '';

			$total_assign = 0;
			foreach ( $variants_rows as $vr ) {
				$total_assign += (int) ( $vr['assignments'] ?? 0 );
			}

			$summary = '';
			if ( 'paused' === $st ) {
				$summary = __( 'This test is paused.', 'reactwoo-geo-optimise' );
			} elseif ( 'completed' === $st ) {
				$summary = __( 'This test has ended.', 'reactwoo-geo-optimise' );
			} elseif ( $assignment_only ) {
				$summary = __( 'Assignment-only mode: traffic split is tracked; no conversion winner is declared.', 'reactwoo-geo-optimise' );
			} elseif ( $total_assign < 5 ) {
				$summary = __( 'Not enough visitors yet for a confident read — check back after more traffic.', 'reactwoo-geo-optimise' );
			} elseif ( $conversion_mode && $lead_label ) {
				/* translators: %s: variant label */
				$summary = sprintf( __( '%s is leading on total conversions so far.', 'reactwoo-geo-optimise' ), $lead_label );
			} elseif ( ! $assignment_only && ! $conversion_mode ) {
				$summary = __( 'No mapped success goals — add goals and handlers in the test configuration to measure conversions.', 'reactwoo-geo-optimise' );
			} else {
				$summary = __( 'No conversions recorded for this test yet.', 'reactwoo-geo-optimise' );
			}

			$goal_events = ( class_exists( 'RWGO_Event_Store', false ) && '' !== $key ) ? RWGO_Event_Store::count_by_experiment_key( $key ) : 0;
			if ( $conversion_mode && 0 === (int) $goal_events && $total_assign > 0 ) {
				$summary .= ' ' . __( 'Tracking may be incomplete — use Tracking Tools to validate measurement.', 'reactwoo-geo-optimise' );
			}
			?>
			<div class="rwgc-card rwgc-card--full" id="<?php echo esc_attr( 'exp-' . (int) $exp_post->ID ); ?>">
				<h2><?php echo esc_html( get_the_title( $exp_post ) ); ?></h2>
				<div class="rwgo-report-summary">
					<p class="rwgo-report-summary__goal"><?php echo $assignment_only ? esc_html__( 'Mode', 'reactwoo-geo-optimise' ) : esc_html__( 'Leading variant', 'reactwoo-geo-optimise' ); ?></p>
					<p class="rwgo-report-summary__headline"><?php echo esc_html( $assignment_only ? '—' : ( $lead_label ? $lead_label : '—' ) ); ?></p>
					<?php if ( $conversion_mode && ( $metric_label || $metric_desc ) ) : ?>
						<p class="rwgo-report-summary__metric"><strong><?php echo esc_html( $metric_label ); ?></strong><?php echo $metric_desc ? ' — ' . esc_html( $metric_desc ) : ''; ?></p>
					<?php endif; ?>
					<p class="rwgo-report-summary__meta"><?php echo esc_html( $summary ); ?></p>
					<?php if ( '' !== $insight_line ) : ?>
						<p class="rwgo-report-summary__insight"><?php echo esc_html( $insight_line ); ?></p>
					<?php endif; ?>
				</div>
				<ul class="rwgo-report-meta">
					<li><?php esc_html_e( 'Status:', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( $st ); ?></li>
					<li><?php esc_html_e( 'Visitors assigned (total):', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( (string) $total_assign ); ?></li>
					<?php if ( current_user_can( 'edit_post', $exp_post->ID ) && class_exists( 'RWGO_Admin', false ) ) : ?>
						<li><a href="<?php echo esc_url( RWGO_Admin::edit_test_url( (int) $exp_post->ID, 'reports' ) ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a></li>
					<?php endif; ?>
				</ul>
				<?php if ( ! $assignment_only && ! empty( $variants_rows ) ) : ?>
					<table class="widefat striped rwgo-table-comfortable">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Variant', 'reactwoo-geo-optimise' ); ?></th>
								<th><?php esc_html_e( 'Assignments', 'reactwoo-geo-optimise' ); ?></th>
								<th><?php esc_html_e( 'Total conversions', 'reactwoo-geo-optimise' ); ?></th>
								<th><?php esc_html_e( 'Conversion rate', 'reactwoo-geo-optimise' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $variants_rows as $slug => $vr ) : ?>
								<tr>
									<td><?php echo esc_html( $rwgo_variant_label( $cfg, (string) $slug ) ); ?></td>
									<td><?php echo esc_html( (string) (int) ( $vr['assignments'] ?? 0 ) ); ?></td>
									<td><?php echo esc_html( (string) (int) ( $vr['completions'] ?? 0 ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (float) ( $vr['rate'] ?? 0 ) * 100, 2 ) ); ?>%</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( ! empty( $goal_breakdown ) ) : ?>
						<h3 class="rwgo-report-breakdown-title"><?php esc_html_e( 'By success target', 'reactwoo-geo-optimise' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Counts per mapped goal and handler (labels are for display only).', 'reactwoo-geo-optimise' ); ?></p>
						<table class="widefat striped rwgo-table-comfortable rwgo-report-breakdown">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Success target', 'reactwoo-geo-optimise' ); ?></th>
									<?php foreach ( $variants_rows as $slug => $_vr ) : ?>
										<th scope="col"><?php echo esc_html( $rwgo_variant_label( $cfg, (string) $slug ) ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $goal_breakdown as $br ) : ?>
									<?php if ( ! is_array( $br ) ) { continue; } ?>
									<tr>
										<td><?php echo esc_html( isset( $br['label'] ) ? (string) $br['label'] : '' ); ?></td>
										<?php foreach ( $variants_rows as $slug => $_vr ) : ?>
											<td><?php echo esc_html( (string) (int) ( is_array( $br['counts'] ?? null ) ? ( $br['counts'][ $slug ] ?? 0 ) : 0 ) ); ?></td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				<?php elseif ( $assignment_only && ! empty( $variants_rows ) ) : ?>
					<table class="widefat striped rwgo-table-comfortable">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Variant', 'reactwoo-geo-optimise' ); ?></th>
								<th><?php esc_html_e( 'Visitors', 'reactwoo-geo-optimise' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $variants_rows as $slug => $vr ) : ?>
								<tr>
									<td><?php echo esc_html( $rwgo_variant_label( $cfg, (string) $slug ) ); ?></td>
									<td><?php echo esc_html( (string) (int) ( $vr['assignments'] ?? 0 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="rwgo-empty-hint"><?php esc_html_e( 'No data yet — share the original page URL so visitors can enter the test.', 'reactwoo-geo-optimise' ); ?></p>
				<?php endif; ?>
				<?php
				if ( 'completed' !== $st && current_user_can( 'edit_post', $exp_post->ID ) && class_exists( 'RWGO_Variant_Lifecycle', false ) ) :
					$vb_rep = RWGO_Variant_Lifecycle::variant_b_page_id( $cfg );
					if ( $vb_rep > 0 && class_exists( 'RWGO_Admin', false ) ) :
						?>
				<p class="rwgo-cta-row" style="margin-top:12px;">
					<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( RWGO_Admin::promote_winner_url( (int) $exp_post->ID, 'reports' ) ); ?>"><?php esc_html_e( 'Promote Winner', 'reactwoo-geo-optimise' ); ?></a>
				</p>
						<?php
					endif;
				endif;
				?>
				<details class="rwgo-dev-details rwgo-report-technical">
					<summary><?php esc_html_e( 'Technical details (support & integrations)', 'reactwoo-geo-optimise' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Internal test key:', 'reactwoo-geo-optimise' ); ?> <code><?php echo esc_html( $key ); ?></code></p>
				</details>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
