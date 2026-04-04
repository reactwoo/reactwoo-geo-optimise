<?php
/**
 * Tests list — card-based management layout.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current  = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-tests';
$rwgo_experiments  = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();
$exp_dist          = isset( $exp_dist ) && is_array( $exp_dist ) ? $exp_dist : array();
$rwgo_created_id   = isset( $_GET['rwgo_exp_id'] ) ? absint( wp_unslash( $_GET['rwgo_exp_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgo_control_id   = isset( $_GET['rwgo_control'] ) ? absint( wp_unslash( $_GET['rwgo_control'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgo_var_b_id     = isset( $_GET['rwgo_var_b'] ) ? absint( wp_unslash( $_GET['rwgo_var_b'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

/**
 * @param array<string, mixed> $cfg Config.
 * @return string
 */
$rwgo_builder_col = static function ( $cfg ) {
	if ( ! empty( $cfg['builder_detection']['user_builder_label'] ) ) {
		return (string) $cfg['builder_detection']['user_builder_label'];
	}
	$bt = isset( $cfg['builder_type'] ) ? sanitize_key( (string) $cfg['builder_type'] ) : '';
	$map = array(
		'elementor'   => __( 'Elementor', 'reactwoo-geo-optimise' ),
		'gutenberg'   => __( 'Gutenberg', 'reactwoo-geo-optimise' ),
		'classic'     => __( 'Classic', 'reactwoo-geo-optimise' ),
		'woocommerce' => __( 'WooCommerce', 'reactwoo-geo-optimise' ),
	);
	return isset( $map[ $bt ] ) ? $map[ $bt ] : ( $bt ? $bt : '—' );
};

/**
 * @param array<string, mixed> $cfg .
 * @param string               $variant_slug .
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

/**
 * Status pill modifier class.
 *
 * @param string $st Status slug.
 * @return string
 */
$rwgo_status_pill_class = static function ( $st ) {
	$st = sanitize_key( (string) $st );
	$map = array(
		'active'    => 'rwgo-pill--active',
		'paused'    => 'rwgo-pill--paused',
		'draft'     => 'rwgo-pill--draft',
		'completed' => 'rwgo-pill--completed',
	);
	return isset( $map[ $st ] ) ? $map[ $st ] : 'rwgo-pill--draft';
};

?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--tests">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Tests', 'reactwoo-geo-optimise' ),
			__( 'Control (A), Variant B, goals, and status — edit each version from here.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Tests', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgo-stack">
	<?php if ( ! empty( $_GET['rwgo_created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible rwgo-notice rwgo-panel rwgo-panel--compact">
			<p><strong><?php esc_html_e( 'Test published successfully.', 'reactwoo-geo-optimise' ); ?></strong>
			<?php esc_html_e( 'Control and Variant B are ready. Open Edit Test to review targeting and goals.', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-actions rwgo-actions--primary-secondary rwgo-actions--stack-mobile">
				<?php
				$c_edit = $rwgo_control_id > 0 ? get_edit_post_link( $rwgo_control_id ) : false;
				$v_edit = $rwgo_var_b_id > 0 ? get_edit_post_link( $rwgo_var_b_id ) : false;
				$exp_h  = $rwgo_created_id > 0 ? admin_url( 'admin.php?page=rwgo-reports#exp-' . $rwgo_created_id ) : admin_url( 'admin.php?page=rwgo-reports' );
				$edit_t = $rwgo_created_id > 0 && class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::edit_test_url( $rwgo_created_id ) : '';
				?>
				<?php if ( $edit_t ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $edit_t ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a>
				<?php endif; ?>
				<?php if ( is_string( $c_edit ) && $c_edit ) : ?>
					<a class="button" href="<?php echo esc_url( $c_edit ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
				<?php endif; ?>
				<?php if ( is_string( $v_edit ) && $v_edit ) : ?>
					<a class="button" href="<?php echo esc_url( $v_edit ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( $exp_h ); ?>"><?php esc_html_e( 'View report', 'reactwoo-geo-optimise' ); ?></a>
			</p>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test status updated.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_duplicated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test duplicated as a draft.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $rwgo_experiments ) ) : ?>
		<div class="rwgo-panel rwgo-panel--hero">
			<h2 class="rwgo-section__title"><?php esc_html_e( 'No tests yet', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Create your first test to compare two versions of a page or product and track which one performs better.', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<?php
		foreach ( $rwgo_experiments as $exp_post ) :
			if ( ! $exp_post instanceof \WP_Post ) {
				continue;
			}
			$cfg  = RWGO_Experiment_Repository::get_config( $exp_post->ID );
			$key  = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
			$st   = isset( $cfg['status'] ) ? (string) $cfg['status'] : 'draft';
			$src  = (int) ( $cfg['source_page_id'] ?? 0 );
			$type = isset( $cfg['test_type'] ) ? (string) $cfg['test_type'] : '';
			$type_lab = $type;
			if ( 'page_ab' === $type ) {
				$type_lab = __( 'Page A/B', 'reactwoo-geo-optimise' );
			} elseif ( 'elementor_page' === $type ) {
				$type_lab = __( 'Elementor', 'reactwoo-geo-optimise' );
			} elseif ( 'gutenberg_page' === $type ) {
				$type_lab = __( 'Gutenberg', 'reactwoo-geo-optimise' );
			} elseif ( 'woo_product' === $type ) {
				$type_lab = __( 'Woo product', 'reactwoo-geo-optimise' );
			} elseif ( 'custom_php' === $type ) {
				$type_lab = __( 'Custom / PHP', 'reactwoo-geo-optimise' );
			}
			$vsum = 0;
			if ( '' !== $key && isset( $exp_dist[ $key ] ) && is_array( $exp_dist[ $key ] ) ) {
				foreach ( $exp_dist[ $key ] as $c ) {
					$vsum += (int) $c;
				}
			}
			$analysis = class_exists( 'RWGO_Winner_Service', false )
				? RWGO_Winner_Service::analyze( $key, $cfg, $exp_dist )
				: array( 'assignment_only' => true, 'leading_variant' => null );
			$assign_only = ! empty( $analysis['assignment_only'] );
			$lead_slug   = isset( $analysis['leading_variant'] ) ? $analysis['leading_variant'] : null;
			$lead_disp   = ( ! $assign_only && $lead_slug ) ? $rwgo_variant_label( $cfg, (string) $lead_slug ) : '';
			$primary_goal_lab = class_exists( 'RWGO_Goal_Service', false )
				? RWGO_Goal_Service::get_primary_goal_label( $cfg )
				: '—';
			$builder_lab = $rwgo_builder_col( $cfg );
			$var_b_id    = 0;
			if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
				foreach ( $cfg['variants'] as $row ) {
					if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
						$var_b_id = (int) ( $row['page_id'] ?? 0 );
						break;
					}
				}
			}
			$c_edit   = $src > 0 ? get_edit_post_link( $src ) : false;
			$v_edit   = $var_b_id > 0 ? get_edit_post_link( $var_b_id ) : false;
			$edit_url = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::edit_test_url( (int) $exp_post->ID ) : '';
			$report_u = admin_url( 'admin.php?page=rwgo-reports#exp-' . (int) $exp_post->ID );
			$src_title = $src > 0 ? get_the_title( $src ) : '';
			$vb_title  = $var_b_id > 0 ? get_the_title( $var_b_id ) : '';
			?>
		<article class="rwgo-test-card rwgo-panel" id="<?php echo esc_attr( 'exp-row-' . (int) $exp_post->ID ); ?>">
			<header class="rwgo-test-card__header">
				<div>
					<h2 class="rwgo-test-card__title"><?php echo esc_html( get_the_title( $exp_post ) ); ?></h2>
					<code class="rwgo-muted-key"><?php echo esc_html( $key ); ?></code>
				</div>
				<span class="rwgo-pill <?php echo esc_attr( $rwgo_status_pill_class( $st ) ); ?>"><?php echo esc_html( $st ); ?></span>
			</header>

			<div class="rwgo-test-card__meta rwgo-meta-chips" role="list">
				<span class="rwgo-chip" role="listitem"><span class="rwgo-chip__k"><?php esc_html_e( 'Type', 'reactwoo-geo-optimise' ); ?></span> <span class="rwgo-chip__v"><?php echo esc_html( $type ? $type_lab : '—' ); ?></span></span>
				<span class="rwgo-chip" role="listitem"><span class="rwgo-chip__k"><?php esc_html_e( 'Source', 'reactwoo-geo-optimise' ); ?></span> <span class="rwgo-chip__v"><?php if ( $src > 0 ) : ?><a href="<?php echo esc_url( get_edit_post_link( $src ) ); ?>"><?php echo esc_html( get_the_title( $src ) ); ?></a><?php else : ?>—<?php endif; ?></span></span>
				<span class="rwgo-chip" role="listitem"><span class="rwgo-chip__k"><?php esc_html_e( 'Builder', 'reactwoo-geo-optimise' ); ?></span> <span class="rwgo-chip__v"><?php echo esc_html( $builder_lab ); ?></span></span>
				<span class="rwgo-chip" role="listitem"><span class="rwgo-chip__k"><?php esc_html_e( 'Primary goal', 'reactwoo-geo-optimise' ); ?></span> <span class="rwgo-chip__v"><?php echo esc_html( $primary_goal_lab ); ?></span></span>
				<span class="rwgo-chip" role="listitem"><span class="rwgo-chip__k"><?php esc_html_e( 'Assignments', 'reactwoo-geo-optimise' ); ?></span> <span class="rwgo-chip__v"><?php echo esc_html( (string) $vsum ); ?></span></span>
				<span class="rwgo-chip" role="listitem"><span class="rwgo-chip__k"><?php esc_html_e( 'Leading variant', 'reactwoo-geo-optimise' ); ?></span> <span class="rwgo-chip__v"><?php if ( $assign_only ) : ?><em><?php esc_html_e( 'Traffic split only', 'reactwoo-geo-optimise' ); ?></em><?php elseif ( $lead_disp ) : ?><?php echo esc_html( $lead_disp ); ?><?php else : ?><?php esc_html_e( '—', 'reactwoo-geo-optimise' ); ?><?php endif; ?></span></span>
			</div>

			<div class="rwgo-test-card__versions">
				<p class="rwgo-test-card__versions-title"><?php esc_html_e( 'Versions', 'reactwoo-geo-optimise' ); ?></p>
				<div class="rwgo-version-rows">
					<div class="rwgo-version-row">
						<span class="rwgo-version-row__label"><?php esc_html_e( 'Control (A)', 'reactwoo-geo-optimise' ); ?></span>
						<span class="rwgo-version-row__page"><?php echo $src > 0 ? esc_html( $src_title ) : '—'; ?></span>
						<?php if ( is_string( $c_edit ) && $c_edit ) : ?>
							<a class="button button-small" href="<?php echo esc_url( $c_edit ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="rwgo-version-row">
						<span class="rwgo-version-row__label"><?php esc_html_e( 'Variant B', 'reactwoo-geo-optimise' ); ?></span>
						<span class="rwgo-version-row__page"><?php echo $var_b_id > 0 ? esc_html( $vb_title ) : '—'; ?></span>
						<?php if ( is_string( $v_edit ) && $v_edit ) : ?>
							<a class="button button-small" href="<?php echo esc_url( $v_edit ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="rwgo-test-card__actions rwgo-actions rwgo-actions--primary-secondary rwgo-actions--stack-mobile">
				<?php if ( '' !== $edit_url ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( $report_u ); ?>"><?php esc_html_e( 'View Report', 'reactwoo-geo-optimise' ); ?></a>
			</div>

			<details class="rwgo-more-actions">
				<summary class="rwgo-more-actions__summary"><?php esc_html_e( 'More actions', 'reactwoo-geo-optimise' ); ?></summary>
				<div class="rwgo-more-actions__body">
					<p class="rwgo-action-links">
						<?php if ( $src > 0 ) : ?>
							<a href="<?php echo esc_url( get_permalink( $src ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Control live', 'reactwoo-geo-optimise' ); ?></a>
						<?php endif; ?>
						<?php if ( $src > 0 && $var_b_id > 0 ) : ?>
							<span class="rwgo-action-links__sep" aria-hidden="true">|</span>
						<?php endif; ?>
						<?php if ( $var_b_id > 0 ) : ?>
							<a href="<?php echo esc_url( get_permalink( $var_b_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Variant B live', 'reactwoo-geo-optimise' ); ?></a>
						<?php endif; ?>
					</p>
					<?php if ( 'active' === $st ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
							<input type="hidden" name="action" value="rwgo_pause_test" />
							<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
							<?php wp_nonce_field( 'rwgo_pause_test' ); ?>
							<button type="submit" class="button"><?php esc_html_e( 'Pause Test', 'reactwoo-geo-optimise' ); ?></button>
						</form>
					<?php elseif ( 'paused' === $st ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
							<input type="hidden" name="action" value="rwgo_resume_test" />
							<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
							<?php wp_nonce_field( 'rwgo_resume_test' ); ?>
							<button type="submit" class="button"><?php esc_html_e( 'Resume Test', 'reactwoo-geo-optimise' ); ?></button>
						</form>
					<?php endif; ?>
					<?php if ( 'completed' !== $st ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'End this test?', 'reactwoo-geo-optimise' ) ); ?>');">
							<input type="hidden" name="action" value="rwgo_end_test" />
							<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
							<?php wp_nonce_field( 'rwgo_end_test' ); ?>
							<button type="submit" class="button"><?php esc_html_e( 'End Test', 'reactwoo-geo-optimise' ); ?></button>
						</form>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
						<input type="hidden" name="action" value="rwgo_duplicate_test" />
						<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
						<?php wp_nonce_field( 'rwgo_duplicate_test' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Duplicate Test', 'reactwoo-geo-optimise' ); ?></button>
					</form>
				</div>
			</details>
		</article>
			<?php
		endforeach;
		?>
	<?php endif; ?>
	</div>
</div>
