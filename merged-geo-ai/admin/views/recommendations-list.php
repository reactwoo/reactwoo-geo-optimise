<?php
/**
 * Recommendations list screen.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows              = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination        = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwga_filter_analysis   = isset( $rwga_filter_analysis ) ? (int) $rwga_filter_analysis : 0;
$rwgc_nav_current       = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-recommendations';
$rwga_filters           = isset( $rwga_filters ) && is_array( $rwga_filters ) ? $rwga_filters : array();

$list_url = admin_url( 'admin.php?page=rwga-recommendations' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--recommendations">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Recommendation library', 'reactwoo-geo-ai' ),
			__( 'Browse grouped and record-level recommendations from prior analyses and reopen them to continue implementation.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Recommendations', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>

	<?php
	$rwga_rec = isset( $_GET['rwga_rec'] ) ? sanitize_key( wp_unslash( $_GET['rwga_rec'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_rec ) {
		$n = isset( $_GET['rwga_rec_count'] ) ? (int) $_GET['rwga_rec_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of cards saved */
				_n( 'Generated %d recommendation.', 'Generated %d recommendations.', $n, 'reactwoo-geo-ai' ),
				$n
			)
		);
		echo '</p></div>';
	} elseif ( 'unlicensed' === $rwga_rec ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to generate recommendations.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'deleted' === $rwga_rec ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Recommendation deleted (linked implementation drafts removed too).', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	$rwga_impl = isset( $_GET['rwga_impl'] ) ? sanitize_key( wp_unslash( $_GET['rwga_impl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'unlicensed' === $rwga_impl ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to generate implementation drafts.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'nodrafts' === $rwga_impl ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No implementation drafts were saved. Confirm recommendations still exist and try generating drafts again.', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	?>

	<div class="rwgc-card">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-rec-filter">
			<input type="hidden" name="page" value="rwga-recommendations" />
			<label class="rwgc-field__label" for="rwga-filter-analysis"><?php esc_html_e( 'Analysis run #', 'reactwoo-geo-ai' ); ?></label>
			<input type="number" min="0" name="analysis_run" id="rwga-filter-analysis" class="rwgc-input" value="<?php echo $rwga_filter_analysis > 0 ? (int) $rwga_filter_analysis : ''; ?>" style="max-width: 8rem;" />
			<button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Filter', 'reactwoo-geo-ai' ); ?></button>
			<select name="lifecycle_status" class="rwgc-select rwgc-input">
				<option value=""><?php esc_html_e( 'All statuses', 'reactwoo-geo-ai' ); ?></option>
				<option value="recommendations_generated" <?php selected( 'recommendations_generated', isset( $rwga_filters['lifecycle_status'] ) ? $rwga_filters['lifecycle_status'] : '' ); ?>><?php esc_html_e( 'Recommendations generated', 'reactwoo-geo-ai' ); ?></option>
				<option value="implementation_generated" <?php selected( 'implementation_generated', isset( $rwga_filters['lifecycle_status'] ) ? $rwga_filters['lifecycle_status'] : '' ); ?>><?php esc_html_e( 'Implementation generated', 'reactwoo-geo-ai' ); ?></option>
			</select>
			<input type="date" name="from_date" value="<?php echo esc_attr( isset( $rwga_filters['from_date'] ) ? (string) $rwga_filters['from_date'] : '' ); ?>" />
			<input type="date" name="to_date" value="<?php echo esc_attr( isset( $rwga_filters['to_date'] ) ? (string) $rwga_filters['to_date'] : '' ); ?>" />
			<?php if ( $rwga_filter_analysis > 0 ) : ?>
				<a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Clear', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
		</form>
		<?php if ( $rwga_filter_analysis > 0 && current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
				<input type="hidden" name="action" value="rwga_bulk_implement_analysis" />
				<input type="hidden" name="analysis_run_id" value="<?php echo (int) $rwga_filter_analysis; ?>" />
				<?php wp_nonce_field( 'rwga_bulk_implement_analysis' ); ?>
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Generate implementation drafts for this analysis', 'reactwoo-geo-ai' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_rows ) ) : ?>
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_empty_state(
					__( 'No recommendations yet', 'reactwoo-geo-ai' ),
					__( 'They appear after you run an analysis and generate recommendations for that run.', 'reactwoo-geo-ai' ),
					array(
						array(
							'url'     => admin_url( 'admin.php?page=rwga-analyses' ),
							'label'   => __( 'View analyses', 'reactwoo-geo-ai' ),
							'primary' => true,
						),
						array(
							'url'   => admin_url( 'admin.php?page=' . RWGA_Admin::MENU_PARENT ),
							'label' => __( 'Start workflow', 'reactwoo-geo-ai' ),
						),
					),
					array( 'dashicon' => 'dashicons-lightbulb' )
				);
			} else {
				echo '<p class="description">' . esc_html__( 'No recommendations yet.', 'reactwoo-geo-ai' ) . '</p>';
			}
			?>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Title', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Priority', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Analysis', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lifecycle', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$aid = isset( $row['analysis_run_id'] ) ? (int) $row['analysis_run_id'] : 0;
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$pt  = $pid > 0 ? get_the_title( $pid ) : '';
						$geo = isset( $row['geo_target'] ) ? (string) $row['geo_target'] : '';
						?>
						<?php
						$rec_url = add_query_arg(
							array(
								'page'   => 'rwga-recommendations',
								'rec_id' => $rid,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo (int) $rid; ?></td>
							<td><strong><a href="<?php echo esc_url( $rec_url ); ?>"><?php echo isset( $row['title'] ) ? esc_html( (string) $row['title'] ) : ''; ?></a></strong></td>
							<td><?php echo isset( $row['priority_level'] ) ? esc_html( (string) $row['priority_level'] ) : '—'; ?></td>
							<td>
								<?php if ( $aid > 0 ) : ?>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-analyses', 'run_id' => $aid ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $aid; ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo $pid > 0 && '' !== $pt ? esc_html( $pt ) : '—'; ?></td>
							<td><?php echo ! empty( $row['lifecycle_status'] ) ? esc_html( (string) $row['lifecycle_status'] ) : esc_html__( 'recommendations_generated', 'reactwoo-geo-ai' ); ?></td>
							<td><?php echo isset( $row['created_at'] ) ? esc_html( (string) $row['created_at'] ) : '—'; ?></td>
							<td>
								<a class="rwgc-btn rwgc-btn--sm rwgc-btn--secondary" href="<?php echo esc_url( $rec_url ); ?>"><?php esc_html_e( 'Open recommendation report', 'reactwoo-geo-ai' ); ?></a>
								<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
										<input type="hidden" name="action" value="rwga_copy_implement" />
										<input type="hidden" name="recommendation_id" value="<?php echo (int) $rid; ?>" />
										<?php if ( $pid > 0 ) : ?><input type="hidden" name="page_id" value="<?php echo (int) $pid; ?>" /><?php endif; ?>
										<?php if ( '' !== $geo ) : ?><input type="hidden" name="geo_target" value="<?php echo esc_attr( $geo ); ?>" /><?php endif; ?>
										<?php wp_nonce_field( 'rwga_copy_implement' ); ?>
										<button type="submit" class="rwgc-btn rwgc-btn--sm rwgc-btn--primary"><?php esc_html_e( 'Generate copy', 'reactwoo-geo-ai' ); ?></button>
									</form>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
									<input type="hidden" name="action" value="rwga_recommendation_delete" />
									<input type="hidden" name="recommendation_id" value="<?php echo (int) $rid; ?>" />
									<?php if ( $rwga_filter_analysis > 0 ) : ?>
										<input type="hidden" name="analysis_run" value="<?php echo (int) $rwga_filter_analysis; ?>" />
									<?php endif; ?>
									<?php wp_nonce_field( 'rwga_recommendation_delete' ); ?>
									<button type="submit" class="rwgc-btn rwgc-btn--sm rwgc-btn--tertiary" onclick="return confirm('<?php echo esc_js( __( 'Delete this recommendation and its generated implementation drafts?', 'reactwoo-geo-ai' ) ); ?>');"><?php esc_html_e( 'Delete', 'reactwoo-geo-ai' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$total_pages = isset( $rwga_pagination['pages'] ) ? (int) $rwga_pagination['pages'] : 1;
			$current     = isset( $rwga_pagination['current'] ) ? (int) $rwga_pagination['current'] : 1;
			if ( $total_pages > 1 ) {
				$base_url = $list_url;
				if ( $rwga_filter_analysis > 0 ) {
					$base_url = add_query_arg( 'analysis_run', $rwga_filter_analysis, $list_url );
				}
				$base = esc_url_raw( add_query_arg( 'paged', '%#%', $base_url ) );
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo paginate_links(
					array(
						'base'      => $base,
						'format'    => '',
						'prev_text' => __( '&laquo;', 'reactwoo-geo-ai' ),
						'next_text' => __( '&raquo;', 'reactwoo-geo-ai' ),
						'total'     => $total_pages,
						'current'   => $current,
					)
				);
				echo '</div></div>';
			}
			?>
		<?php endif; ?>
	</div>
</div>
