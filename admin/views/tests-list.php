<?php
/**
 * Tests list.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current  = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-tests';
$rwgo_experiments  = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();
$exp_dist          = isset( $exp_dist ) && is_array( $exp_dist ) ? $exp_dist : array();

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

?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--tests">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Tests', 'reactwoo-geo-optimise' ),
			__( 'See every test, open variants, and change status.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Tests', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['rwgo_created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test created and published.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test updated.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_duplicated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test duplicated as a draft.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<?php if ( empty( $rwgo_experiments ) ) : ?>
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'No tests yet', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Create your first page test to start routing visitors and collecting assignments.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create first test', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	<?php else : ?>
		<table class="widefat striped rwgo-table-comfortable">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Test name', 'reactwoo-geo-optimise' ); ?></th>
					<th><?php esc_html_e( 'Type', 'reactwoo-geo-optimise' ); ?></th>
					<th><?php esc_html_e( 'Source', 'reactwoo-geo-optimise' ); ?></th>
					<th><?php esc_html_e( 'Builder', 'reactwoo-geo-optimise' ); ?></th>
					<th><?php esc_html_e( 'Primary goal', 'reactwoo-geo-optimise' ); ?></th>
					<th><?php esc_html_e( 'Status', 'reactwoo-geo-optimise' ); ?></th>
					<th><?php esc_html_e( 'Assignments', 'reactwoo-geo-optimise' ); ?></th>
					<th><?php esc_html_e( 'Leading (goal)', 'reactwoo-geo-optimise' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'reactwoo-geo-optimise' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rwgo_experiments as $exp_post ) :
					if ( ! $exp_post instanceof \WP_Post ) {
						continue;
					}
					$cfg  = RWGO_Experiment_Repository::get_config( $exp_post->ID );
					$key  = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
					$st   = isset( $cfg['status'] ) ? (string) $cfg['status'] : 'draft';
					$src  = (int) ( $cfg['source_page_id'] ?? 0 );
					$type     = isset( $cfg['test_type'] ) ? (string) $cfg['test_type'] : '';
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
					$var_b_id = 0;
					if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
						foreach ( $cfg['variants'] as $row ) {
							if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
								$var_b_id = (int) ( $row['page_id'] ?? 0 );
								break;
							}
						}
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( get_the_title( $exp_post ) ); ?></strong><br /><code class="rwgo-muted-key"><?php echo esc_html( $key ); ?></code></td>
						<td><?php echo esc_html( $type ? $type_lab : '—' ); ?></td>
						<td>
							<?php if ( $src > 0 ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $src, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $src ) ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $builder_lab ); ?></td>
						<td><?php echo esc_html( $primary_goal_lab ); ?></td>
						<td><?php echo esc_html( $st ); ?></td>
						<td><?php echo esc_html( (string) ( $vsum ) ); ?></td>
						<td>
							<?php if ( $assign_only ) : ?>
								<em><?php esc_html_e( 'Traffic split only', 'reactwoo-geo-optimise' ); ?></em>
							<?php else : ?>
								<?php echo $lead_disp ? esc_html( $lead_disp ) : esc_html__( '—', 'reactwoo-geo-optimise' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $src > 0 ) : ?>
								<a href="<?php echo esc_url( get_permalink( $src ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open source', 'reactwoo-geo-optimise' ); ?></a>
							<?php endif; ?>
							<?php if ( $var_b_id > 0 ) : ?>
								| <a href="<?php echo esc_url( get_permalink( $var_b_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open variant', 'reactwoo-geo-optimise' ); ?></a>
							<?php endif; ?>
							| <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-reports#exp-' . (int) $exp_post->ID ) ); ?>"><?php esc_html_e( 'Report', 'reactwoo-geo-optimise' ); ?></a>
							<br />
							<?php if ( 'active' === $st ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="rwgo_pause_test" />
									<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
									<?php wp_nonce_field( 'rwgo_pause_test' ); ?>
									<button type="submit" class="button-link"><?php esc_html_e( 'Pause', 'reactwoo-geo-optimise' ); ?></button>
								</form>
							<?php elseif ( 'paused' === $st ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="rwgo_resume_test" />
									<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
									<?php wp_nonce_field( 'rwgo_resume_test' ); ?>
									<button type="submit" class="button-link"><?php esc_html_e( 'Resume', 'reactwoo-geo-optimise' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( 'completed' !== $st ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="rwgo_end_test" />
									<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
									<?php wp_nonce_field( 'rwgo_end_test' ); ?>
									<button type="submit" class="button-link" onclick="return confirm('<?php echo esc_js( __( 'End this test?', 'reactwoo-geo-optimise' ) ); ?>');"><?php esc_html_e( 'End test', 'reactwoo-geo-optimise' ); ?></button>
								</form>
							<?php endif; ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<input type="hidden" name="action" value="rwgo_duplicate_test" />
								<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
								<?php wp_nonce_field( 'rwgo_duplicate_test' ); ?>
								<button type="submit" class="button-link"><?php esc_html_e( 'Duplicate', 'reactwoo-geo-optimise' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
