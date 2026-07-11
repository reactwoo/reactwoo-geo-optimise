<?php
/**
 * Implementation drafts (copy, etc.) list.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows                    = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination              = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwga_filter_recommendation   = isset( $rwga_filter_recommendation ) ? (int) $rwga_filter_recommendation : 0;
$rwga_filter_workflow         = isset( $rwga_filter_workflow ) ? (string) $rwga_filter_workflow : '';
$rwgc_nav_current             = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-implementation-drafts';
$rwga_recommendation_rows     = isset( $rwga_recommendation_rows ) && is_array( $rwga_recommendation_rows ) ? $rwga_recommendation_rows : array();
$rwga_filters                 = isset( $rwga_filters ) && is_array( $rwga_filters ) ? $rwga_filters : array();
$rwga_prefill_page_id         = isset( $rwga_prefill_page_id ) ? (int) $rwga_prefill_page_id : 0;
$rwga_prefill_geo             = isset( $rwga_prefill_geo ) ? (string) $rwga_prefill_geo : '';
$rwga_prefill_visibility_rule = isset( $rwga_prefill_visibility_rule ) ? (int) $rwga_prefill_visibility_rule : 0;

$list_url = ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Optimise_Hub', false ) )
	? RWGO_Optimise_Hub::tab_url( 'drafts' )
	: admin_url( 'admin.php?page=rwga-implementation-drafts' );
$rwgo_hub_embed = ! empty( $rwgo_optimise_hub_embed );
?>
<?php if ( ! $rwgo_hub_embed ) : ?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--implementation-drafts">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Draft library', 'reactwoo-geo-ai' ),
			__( 'Archive and manage generated drafts. For guided continuation, use the Implementation review journey screen.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Draft library', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php if ( ! $rwgo_hub_embed ) : ?>
		<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>
<?php else : ?>
<div class="rwgo-optimise-hub__embed rwgo-optimise-hub__embed--drafts">
<?php endif; ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>
	<p><a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-implementation-drafts&view=review&journey=1' ) ); ?>"><?php esc_html_e( 'Open implementation review', 'reactwoo-geo-ai' ); ?></a></p>

	<?php
	$rwga_copy = isset( $_GET['rwga_copy'] ) ? sanitize_key( wp_unslash( $_GET['rwga_copy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_copy ) {
		$n = isset( $_GET['rwga_draft_count'] ) ? (int) $_GET['rwga_draft_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of draft rows saved */
				_n( 'Generated %d implementation draft.', 'Generated %d implementation drafts.', $n, 'reactwoo-geo-ai' ),
				$n
			)
		);
		echo '</p></div>';
	} elseif ( 'unlicensed' === $rwga_copy ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to generate implementation drafts.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'bad' === $rwga_copy ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Provide a recommendation ID and/or page ID.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'noflow' === $rwga_copy ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Copy implementation workflow is not available.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $rwga_copy && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	$rwga_seo = isset( $_GET['rwga_seo'] ) ? sanitize_key( wp_unslash( $_GET['rwga_seo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_seo ) {
		$n = isset( $_GET['rwga_draft_count'] ) ? (int) $_GET['rwga_draft_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: number of SEO draft rows saved */
				_n( 'Generated %d SEO draft.', 'Generated %d SEO drafts.', $n, 'reactwoo-geo-ai' ),
				$n
			)
		);
		echo '</p></div>';
	} elseif ( 'unlicensed' === $rwga_seo ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to generate SEO drafts.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'bad' === $rwga_seo ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Provide a recommendation ID and/or page ID.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'noflow' === $rwga_seo ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'SEO implementation workflow is not available.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $rwga_seo && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	$rwga_draft = isset( $_GET['rwga_draft'] ) ? sanitize_key( wp_unslash( $_GET['rwga_draft'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'deleted' === $rwga_draft ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Implementation draft deleted.', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	?>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Generate copy drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use a recommendation for full context, or choose a page on its own.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-form-grid">
			<input type="hidden" name="action" value="rwga_copy_implement" />
			<?php if ( $rwga_prefill_visibility_rule > 0 ) : ?>
				<input type="hidden" name="visibility_rule_id" value="<?php echo (int) $rwga_prefill_visibility_rule; ?>" />
			<?php endif; ?>
			<?php wp_nonce_field( 'rwga_copy_implement' ); ?>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_copy_rec_id"><?php esc_html_e( 'Select recommendation (optional)', 'reactwoo-geo-ai' ); ?></label>
				<select name="recommendation_id" id="rwga_copy_rec_id" class="rwgc-select rwgc-input">
					<option value="0"><?php esc_html_e( '— None —', 'reactwoo-geo-ai' ); ?></option>
					<?php foreach ( $rwga_recommendation_rows as $rec_row ) : ?>
						<?php
						$rec_id = isset( $rec_row['id'] ) ? (int) $rec_row['id'] : 0;
						if ( $rec_id <= 0 ) {
							continue;
						}
						$rec_title = isset( $rec_row['title'] ) && (string) $rec_row['title'] !== '' ? (string) $rec_row['title'] : __( '(No title)', 'reactwoo-geo-ai' );
						$rec_pid   = isset( $rec_row['page_id'] ) ? (int) $rec_row['page_id'] : 0;
						$rec_pt    = $rec_pid > 0 ? get_the_title( $rec_pid ) : '';
						$rec_date  = isset( $rec_row['created_at'] ) ? (string) $rec_row['created_at'] : '';
						$opt_label = $rec_title;
						if ( '' !== $rec_pt ) {
							$opt_label .= ' — ' . $rec_pt;
						}
						if ( '' !== $rec_date ) {
							$opt_label .= ' — ' . $rec_date;
						}
						?>
						<option value="<?php echo (int) $rec_id; ?>" <?php selected( $rwga_filter_recommendation, $rec_id ); ?>><?php echo esc_html( $opt_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_copy_page_id"><?php esc_html_e( 'Page (optional)', 'reactwoo-geo-ai' ); ?></label>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'page_id',
						'id'                => 'rwga_copy_page_id',
						'show_option_none'  => __( '— Select page —', 'reactwoo-geo-ai' ),
						'option_none_value' => '0',
						'class'             => 'rwgc-select rwgc-input',
						'selected'          => $rwga_prefill_page_id,
					)
				);
				?>
				<p class="rwgc-field__hint"><?php esc_html_e( 'Optional when the recommendation already references a page.', 'reactwoo-geo-ai' ); ?></p>
			</div>
			<div class="rwgc-field">
				<span class="rwgc-field__label"><?php esc_html_e( 'Target country (optional)', 'reactwoo-geo-ai' ); ?></span>
				<?php
				if ( class_exists( 'RWGC_Admin', false ) ) {
					RWGC_Admin::render_country_select(
						'geo_target',
						$rwga_prefill_geo,
						array(
							'id'                => 'rwga_copy_geo',
							'class'             => 'rwgc-select rwgc-input regular-text',
							'show_option_none'  => __( '— Any / default —', 'reactwoo-geo-ai' ),
							'option_none_value' => '',
						)
					);
				} else {
					echo '<input type="text" maxlength="2" class="rwgc-input" name="geo_target" id="rwga_copy_geo" value="' . esc_attr( $rwga_prefill_geo ) . '" />';
				}
				?>
			</div>
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Generate copy drafts', 'reactwoo-geo-ai' ); ?></button>
			</p>
		</form>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Generate SEO drafts', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Meta title and description, heading outline, and a short on-page checklist.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-form-grid">
			<input type="hidden" name="action" value="rwga_seo_implement" />
			<?php wp_nonce_field( 'rwga_seo_implement' ); ?>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_seo_rec_id"><?php esc_html_e( 'Select recommendation (optional)', 'reactwoo-geo-ai' ); ?></label>
				<select name="recommendation_id" id="rwga_seo_rec_id" class="rwgc-select rwgc-input">
					<option value="0"><?php esc_html_e( '— None —', 'reactwoo-geo-ai' ); ?></option>
					<?php foreach ( $rwga_recommendation_rows as $rec_row ) : ?>
						<?php
						$rec_id = isset( $rec_row['id'] ) ? (int) $rec_row['id'] : 0;
						if ( $rec_id <= 0 ) {
							continue;
						}
						$rec_title = isset( $rec_row['title'] ) && (string) $rec_row['title'] !== '' ? (string) $rec_row['title'] : __( '(No title)', 'reactwoo-geo-ai' );
						$rec_pid   = isset( $rec_row['page_id'] ) ? (int) $rec_row['page_id'] : 0;
						$rec_pt    = $rec_pid > 0 ? get_the_title( $rec_pid ) : '';
						$rec_date  = isset( $rec_row['created_at'] ) ? (string) $rec_row['created_at'] : '';
						$opt_label = $rec_title;
						if ( '' !== $rec_pt ) {
							$opt_label .= ' — ' . $rec_pt;
						}
						if ( '' !== $rec_date ) {
							$opt_label .= ' — ' . $rec_date;
						}
						?>
						<option value="<?php echo (int) $rec_id; ?>" <?php selected( $rwga_filter_recommendation, $rec_id ); ?>><?php echo esc_html( $opt_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_seo_page_id"><?php esc_html_e( 'Page (optional)', 'reactwoo-geo-ai' ); ?></label>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'page_id',
						'id'                => 'rwga_seo_page_id',
						'show_option_none'  => __( '— Select page —', 'reactwoo-geo-ai' ),
						'option_none_value' => '0',
						'class'             => 'rwgc-select rwgc-input',
					)
				);
				?>
			</div>
			<div class="rwgc-field">
				<span class="rwgc-field__label"><?php esc_html_e( 'Target country (optional)', 'reactwoo-geo-ai' ); ?></span>
				<?php
				if ( class_exists( 'RWGC_Admin', false ) ) {
					RWGC_Admin::render_country_select(
						'geo_target',
						'',
						array(
							'id'                => 'rwga_seo_geo',
							'class'             => 'rwgc-select rwgc-input regular-text',
							'show_option_none'  => __( '— Any / default —', 'reactwoo-geo-ai' ),
							'option_none_value' => '',
						)
					);
				} else {
					echo '<input type="text" maxlength="2" class="rwgc-input" name="geo_target" id="rwga_seo_geo" value="" />';
				}
				?>
			</div>
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Generate SEO drafts', 'reactwoo-geo-ai' ); ?></button>
			</p>
		</form>
	</div>
	<?php elseif ( class_exists( 'RWGA_License', false ) && ! RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<p class="description"><?php esc_html_e( 'Add a Geo AI license key to generate implementation drafts.', 'reactwoo-geo-ai' ); ?></p>
	</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-rec-filter rwgc-form-grid">
			<input type="hidden" name="page" value="rwga-implementation-drafts" />
			<p>
				<label class="rwgc-field__label" for="rwga-filter-rec"><?php esc_html_e( 'Recommendation', 'reactwoo-geo-ai' ); ?></label>
				<select name="recommendation_id" id="rwga-filter-rec" class="rwgc-select rwgc-input">
					<option value="0"><?php esc_html_e( 'All', 'reactwoo-geo-ai' ); ?></option>
					<?php foreach ( $rwga_recommendation_rows as $rec_row ) : ?>
						<?php
						$rec_id = isset( $rec_row['id'] ) ? (int) $rec_row['id'] : 0;
						if ( $rec_id <= 0 ) {
							continue;
						}
						$rec_title = isset( $rec_row['title'] ) && (string) $rec_row['title'] !== '' ? (string) $rec_row['title'] : __( '(No title)', 'reactwoo-geo-ai' );
						?>
						<option value="<?php echo (int) $rec_id; ?>" <?php selected( $rwga_filter_recommendation, $rec_id ); ?>><?php echo esc_html( '#' . $rec_id . ' — ' . $rec_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label class="rwgc-field__label" for="rwga-filter-wf"><?php esc_html_e( 'Draft type', 'reactwoo-geo-ai' ); ?></label>
				<select name="workflow_key" id="rwga-filter-wf" class="rwgc-select rwgc-input">
					<option value="" <?php selected( '', $rwga_filter_workflow ); ?>><?php esc_html_e( 'All', 'reactwoo-geo-ai' ); ?></option>
					<option value="copy_implement" <?php selected( 'copy_implement', $rwga_filter_workflow ); ?>><?php esc_html_e( 'Copy', 'reactwoo-geo-ai' ); ?></option>
					<option value="seo_implement" <?php selected( 'seo_implement', $rwga_filter_workflow ); ?>><?php esc_html_e( 'SEO', 'reactwoo-geo-ai' ); ?></option>
				</select>
			</p>
			<p>
				<label class="rwgc-field__label" for="rwga-filter-status"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></label>
				<select name="status" id="rwga-filter-status" class="rwgc-select rwgc-input">
					<option value=""><?php esc_html_e( 'All', 'reactwoo-geo-ai' ); ?></option>
					<option value="draft" <?php selected( 'draft', isset( $rwga_filters['status'] ) ? $rwga_filters['status'] : '' ); ?>><?php esc_html_e( 'Draft', 'reactwoo-geo-ai' ); ?></option>
					<option value="applied" <?php selected( 'applied', isset( $rwga_filters['status'] ) ? $rwga_filters['status'] : '' ); ?>><?php esc_html_e( 'Applied', 'reactwoo-geo-ai' ); ?></option>
				</select>
			</p>
			<input type="date" name="from_date" value="<?php echo esc_attr( isset( $rwga_filters['from_date'] ) ? (string) $rwga_filters['from_date'] : '' ); ?>" />
			<input type="date" name="to_date" value="<?php echo esc_attr( isset( $rwga_filters['to_date'] ) ? (string) $rwga_filters['to_date'] : '' ); ?>" />
			<button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Filter', 'reactwoo-geo-ai' ); ?></button>
			<?php if ( $rwga_filter_recommendation > 0 || '' !== $rwga_filter_workflow ) : ?>
				<a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Clear', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_rows ) ) : ?>
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_empty_state(
					__( 'No drafts yet', 'reactwoo-geo-ai' ),
					__( 'Drafts appear after you generate copy or SEO from a recommendation or page.', 'reactwoo-geo-ai' ),
					array(
						array(
							'url'     => admin_url( 'admin.php?page=rwga-recommendations' ),
							'label'   => __( 'View recommendations', 'reactwoo-geo-ai' ),
							'primary' => true,
						),
						array(
							'url'   => admin_url( 'admin.php?page=rwga-analyses' ),
							'label' => __( 'Start workflow', 'reactwoo-geo-ai' ),
						),
					),
					array( 'dashicon' => 'dashicons-media-document' )
				);
			} else {
				echo '<p class="description">' . esc_html__( 'No implementation drafts yet.', 'reactwoo-geo-ai' ) . '</p>';
			}
			?>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Title', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Recommendation', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$did = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$rid = isset( $row['recommendation_id'] ) ? (int) $row['recommendation_id'] : 0;
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$pt  = $pid > 0 ? get_the_title( $pid ) : '';
						$detail_url = add_query_arg(
							array(
								'page'     => 'rwga-implementation-drafts',
								'draft_id' => $did,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo (int) $did; ?></td>
							<td><code><?php echo isset( $row['workflow_key'] ) ? esc_html( (string) $row['workflow_key'] ) : '—'; ?></code></td>
							<td>
								<strong><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo isset( $row['title'] ) ? esc_html( (string) $row['title'] ) : ''; ?></a></strong>
							</td>
							<td><code><?php echo isset( $row['draft_type'] ) ? esc_html( (string) $row['draft_type'] ) : '—'; ?></code></td>
							<td>
								<?php if ( $rid > 0 ) : ?>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-recommendations', 'rec_id' => $rid ), admin_url( 'admin.php' ) ) ); ?>"><?php echo (int) $rid; ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo $pid > 0 && '' !== $pt ? esc_html( $pt ) : '—'; ?></td>
							<td><?php echo isset( $row['status'] ) ? esc_html( (string) $row['status'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_at'] ) ? esc_html( (string) $row['created_at'] ) : '—'; ?></td>
							<td>
								<a class="rwgc-btn rwgc-btn--sm rwgc-btn--secondary" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View', 'reactwoo-geo-ai' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
									<input type="hidden" name="action" value="rwga_draft_delete" />
									<input type="hidden" name="draft_id" value="<?php echo (int) $did; ?>" />
									<?php if ( $rwga_filter_recommendation > 0 ) : ?>
										<input type="hidden" name="recommendation_id" value="<?php echo (int) $rwga_filter_recommendation; ?>" />
									<?php endif; ?>
									<?php if ( '' !== $rwga_filter_workflow ) : ?>
										<input type="hidden" name="workflow_key" value="<?php echo esc_attr( $rwga_filter_workflow ); ?>" />
									<?php endif; ?>
									<?php wp_nonce_field( 'rwga_draft_delete' ); ?>
									<button type="submit" class="rwgc-btn rwgc-btn--sm rwgc-btn--tertiary" onclick="return confirm('<?php echo esc_js( __( 'Delete this implementation draft?', 'reactwoo-geo-ai' ) ); ?>');"><?php esc_html_e( 'Delete', 'reactwoo-geo-ai' ); ?></button>
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
				if ( $rwga_filter_recommendation > 0 ) {
					$base_url = add_query_arg( 'recommendation_id', $rwga_filter_recommendation, $base_url );
				}
				if ( '' !== $rwga_filter_workflow ) {
					$base_url = add_query_arg( 'workflow_key', $rwga_filter_workflow, $base_url );
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
<?php if ( ! $rwgo_hub_embed ) : ?>
</div>
<?php else : ?>
</div>
<?php endif; ?>
