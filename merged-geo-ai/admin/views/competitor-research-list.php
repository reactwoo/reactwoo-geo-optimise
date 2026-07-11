<?php
/**
 * Competitor research list + run form.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows            = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination      = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwga_filter_page     = isset( $rwga_filter_page ) ? (int) $rwga_filter_page : 0;
$rwgc_nav_current     = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-competitors';
$list_url             = admin_url( 'admin.php?page=rwga-competitors' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--competitors">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Competitors', 'reactwoo-geo-ai' ),
			__( 'Compare another site to yours: capture a snapshot and review positioning in plain language.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Competitor research', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php
	$rwga_cr = isset( $_GET['rwga_cr'] ) ? sanitize_key( wp_unslash( $_GET['rwga_cr'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_cr ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Competitor research saved.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'unlicensed' === $rwga_cr ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to run competitor research.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'badurl' === $rwga_cr ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Enter a valid competitor URL.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'noflow' === $rwga_cr ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Competitor research workflow is not available.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $rwga_cr && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	?>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'New research', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Enter the competitor URL. Optionally link one of your pages and a target country for context.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-form-grid">
			<input type="hidden" name="action" value="rwga_competitor_research" />
			<?php wp_nonce_field( 'rwga_competitor_research' ); ?>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_cr_url"><?php esc_html_e( 'Competitor URL', 'reactwoo-geo-ai' ); ?></label>
				<input type="url" class="rwgc-input regular-text" name="competitor_url" id="rwga_cr_url" required placeholder="https://example.com" />
			</div>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_cr_page"><?php esc_html_e( 'Your page (optional)', 'reactwoo-geo-ai' ); ?></label>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'page_id',
						'id'                => 'rwga_cr_page',
						'show_option_none'  => __( '— None —', 'reactwoo-geo-ai' ),
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
							'id'                => 'rwga_cr_geo',
							'class'             => 'rwgc-select rwgc-input regular-text',
							'show_option_none'  => __( '— Any —', 'reactwoo-geo-ai' ),
							'option_none_value' => '',
						)
					);
				} else {
					echo '<input type="text" maxlength="2" class="rwgc-input" name="geo_target" id="rwga_cr_geo" value="" />';
				}
				?>
			</div>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_cr_pt"><?php esc_html_e( 'Page type (optional)', 'reactwoo-geo-ai' ); ?></label>
				<input type="text" class="rwgc-input regular-text" name="page_type" id="rwga_cr_pt" value="page" />
			</div>
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Run research', 'reactwoo-geo-ai' ); ?></button>
			</p>
		</form>
	</div>
	<?php elseif ( class_exists( 'RWGA_License', false ) && ! RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<p class="description"><?php esc_html_e( 'Add a Geo AI license key to run competitor research.', 'reactwoo-geo-ai' ); ?></p>
	</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2 class="screen-reader-text"><?php esc_html_e( 'Filter saved research', 'reactwoo-geo-ai' ); ?></h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-rec-filter rwgc-form-grid">
			<input type="hidden" name="page" value="rwga-competitors" />
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga-cr-filter-page"><?php esc_html_e( 'Filter by your page', 'reactwoo-geo-ai' ); ?></label>
				<p class="description rwga-filter-hint">
					<?php esc_html_e( 'Only show competitor snapshots that were run with that page selected under “Your page” in the form above. “All pages” lists every run.', 'reactwoo-geo-ai' ); ?>
				</p>
				<?php
				wp_dropdown_pages(
					array(
						'name'               => 'filter_page',
						'id'                 => 'rwga-cr-filter-page',
						'selected'           => $rwga_filter_page > 0 ? $rwga_filter_page : 0,
						'show_option_none'   => __( 'All pages', 'reactwoo-geo-ai' ),
						'option_none_value'  => '0',
						'class'              => 'rwgc-select rwgc-input',
					)
				);
				?>
			</div>
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Filter', 'reactwoo-geo-ai' ); ?></button>
				<?php if ( $rwga_filter_page > 0 ) : ?>
					<a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Clear', 'reactwoo-geo-ai' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_rows ) ) : ?>
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_empty_state(
					__( 'No research yet', 'reactwoo-geo-ai' ),
					__( 'Add a competitor URL above to create your first snapshot.', 'reactwoo-geo-ai' ),
					array(),
					array( 'dashicon' => 'dashicons-search' )
				);
			} else {
				echo '<p class="description">' . esc_html__( 'No competitor research rows yet.', 'reactwoo-geo-ai' ) . '</p>';
			}
			?>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Competitor URL', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Country', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$url = isset( $row['competitor_url'] ) ? (string) $row['competitor_url'] : '';
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$pst = $pid > 0 ? get_the_title( $pid ) : '';
						$det = add_query_arg(
							array(
								'page'         => 'rwga-competitors',
								'research_id'  => $rid,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo (int) $rid; ?></td>
							<td><a href="<?php echo esc_url( $det ); ?>"><?php echo esc_html( $url ); ?></a></td>
							<td><?php echo $pid > 0 && '' !== $pst ? esc_html( $pst ) : ( $pid > 0 ? (string) (int) $pid : '—' ); ?></td>
							<td><?php echo isset( $row['geo_target'] ) && $row['geo_target'] ? esc_html( (string) $row['geo_target'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_at'] ) ? esc_html( (string) $row['created_at'] ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$total_pages = isset( $rwga_pagination['pages'] ) ? (int) $rwga_pagination['pages'] : 1;
			$current     = isset( $rwga_pagination['current'] ) ? (int) $rwga_pagination['current'] : 1;
			if ( $total_pages > 1 ) {
				$base_url = $list_url;
				if ( $rwga_filter_page > 0 ) {
					$base_url = add_query_arg( 'filter_page', $rwga_filter_page, $list_url );
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
