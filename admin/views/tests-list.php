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

	<?php if ( ! empty( $_GET['rwgo_promoted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="rwgo-page-notices">
			<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success"><p class="rwgo-alert__text"><?php esc_html_e( 'Variant B was copied into Control and this test was marked completed.', 'reactwoo-geo-optimise' ); ?></p></div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_detached'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="rwgo-page-notices">
			<div class="notice notice-warning rwgo-alert"><p class="rwgo-alert__text"><?php esc_html_e( 'Variant B was removed from this test. The test is paused until you link a new variant.', 'reactwoo-geo-optimise' ); ?></p></div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_regenerated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php
		$rwgo_regen_exp = isset( $_GET['rwgo_experiment_id'] ) ? absint( wp_unslash( $_GET['rwgo_experiment_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rwgo_regen_vid = 0;
		if ( $rwgo_regen_exp > 0 && class_exists( 'RWGO_Experiment_Repository', false ) ) {
			$rwgo_regen_cfg = RWGO_Experiment_Repository::get_config( $rwgo_regen_exp );
			if ( ! empty( $rwgo_regen_cfg['variants'] ) && is_array( $rwgo_regen_cfg['variants'] ) ) {
				foreach ( $rwgo_regen_cfg['variants'] as $row ) {
					if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
						$rwgo_regen_vid = (int) ( $row['page_id'] ?? 0 );
						break;
					}
				}
			}
		}
		$rwgo_regen_vpost = ( $rwgo_regen_vid > 0 ) ? get_post( $rwgo_regen_vid ) : null;
		$rwgo_regen_url   = ( $rwgo_regen_vpost instanceof \WP_Post ) ? get_permalink( $rwgo_regen_vid ) : '';
		if ( $rwgo_regen_url && function_exists( 'wp_make_link_relative' ) ) {
			$rwgo_regen_url = wp_make_link_relative( $rwgo_regen_url );
		}
		?>
		<div class="rwgo-page-notices">
			<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success">
				<?php if ( $rwgo_regen_vpost instanceof \WP_Post && is_string( $rwgo_regen_url ) && '' !== $rwgo_regen_url ) : ?>
					<p class="rwgo-alert__text">
						<?php
						printf(
							/* translators: %s: variant page title */
							esc_html__( 'Variant created: %s', 'reactwoo-geo-optimise' ),
							esc_html( get_the_title( $rwgo_regen_vpost ) )
						);
						?>
					</p>
					<p class="rwgo-alert__text">
						<?php
						printf(
							/* translators: %s: relative or full URL path to variant */
							esc_html__( 'URL: %s', 'reactwoo-geo-optimise' ),
							esc_html( $rwgo_regen_url )
						);
						?>
					</p>
				<?php else : ?>
					<p class="rwgo-alert__text"><?php esc_html_e( 'Variant B was recreated from Control.', 'reactwoo-geo-optimise' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="rwgo-page-notices">
			<div class="notice notice-error rwgo-alert rwgo-alert--error"><p class="rwgo-alert__text">
				<?php
				$err = sanitize_key( (string) wp_unslash( $_GET['rwgo_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( 'promote' === $err ) {
					esc_html_e( 'Could not promote Variant B to Control. Check permissions and that both pages exist.', 'reactwoo-geo-optimise' );
				} elseif ( 'detach' === $err ) {
					esc_html_e( 'Could not remove Variant B from this test.', 'reactwoo-geo-optimise' );
				} elseif ( 'regen' === $err ) {
					esc_html_e( 'Could not regenerate Variant B from Control.', 'reactwoo-geo-optimise' );
				} elseif ( 'dup_invalid' === $err ) {
					esc_html_e( 'Variant B could not be created as a valid duplicate. The test was not updated. Try again, use an existing page, or create a blank variant.', 'reactwoo-geo-optimise' );
				} elseif ( 'regen_invalid' === $err ) {
					esc_html_e( 'Variant B could not be regenerated as a valid duplicate. The previous variant was left unchanged.', 'reactwoo-geo-optimise' );
				} elseif ( 'delete' === $err ) {
					esc_html_e( 'Could not delete this test.', 'reactwoo-geo-optimise' );
				} else {
					esc_html_e( 'Something went wrong. Try again.', 'reactwoo-geo-optimise' );
				}
				?>
			</p></div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['rwgo_test_created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php
		$rwgo_tc_exp = isset( $_GET['rwgo_experiment_id'] ) ? absint( wp_unslash( $_GET['rwgo_experiment_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rwgo_tc_need_goal  = ! empty( $_GET['rwgo_needs_defined_goal'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rwgo_tc_bind_q     = ! empty( $_GET['rwgo_binding_warn'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rwgo_tc_cfg        = ( $rwgo_tc_exp > 0 && class_exists( 'RWGO_Experiment_Repository', false ) )
			? RWGO_Experiment_Repository::get_config( $rwgo_tc_exp )
			: array();
		if ( ! empty( $rwgo_tc_cfg ) && $rwgo_tc_exp > 0 && class_exists( 'RWGO_Experiment_Repository', false ) ) {
			$rwgo_tc_cfg = RWGO_Experiment_Repository::normalize_page_bindings( $rwgo_tc_cfg, $rwgo_tc_exp, false );
		}
		$rwgo_tc_src        = (int) ( $rwgo_tc_cfg['source_page_id'] ?? 0 );
		$rwgo_tc_vb         = 0;
		if ( ! empty( $rwgo_tc_cfg['variants'] ) && is_array( $rwgo_tc_cfg['variants'] ) ) {
			foreach ( $rwgo_tc_cfg['variants'] as $row ) {
				if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
					$rwgo_tc_vb = (int) ( $row['page_id'] ?? 0 );
					break;
				}
			}
		}
		$rwgo_tc_tt = isset( $rwgo_tc_cfg['test_type'] ) ? (string) $rwgo_tc_cfg['test_type'] : 'page_ab';
		$rwgo_tc_edit = ( $rwgo_tc_exp > 0 && class_exists( 'RWGO_Admin', false ) ) ? RWGO_Admin::edit_test_url( $rwgo_tc_exp, 'tests' ) : '';
		$rwgo_tc_ce   = ( $rwgo_tc_src > 0 && class_exists( 'RWGO_Admin', false ) ) ? RWGO_Admin::post_builder_edit_url( $rwgo_tc_src, $rwgo_tc_tt ) : '';
		$rwgo_tc_ve   = ( $rwgo_tc_vb > 0 && class_exists( 'RWGO_Admin', false ) ) ? RWGO_Admin::post_builder_edit_url( $rwgo_tc_vb, $rwgo_tc_tt ) : '';
		?>
		<div class="rwgo-page-notices">
			<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success">
				<p class="rwgo-alert__text"><strong><?php esc_html_e( 'Test created.', 'reactwoo-geo-optimise' ); ?></strong>
				<?php esc_html_e( 'It appears in the list below. Open Edit Test to review goals and targeting.', 'reactwoo-geo-optimise' ); ?></p>
				<?php if ( $rwgo_tc_need_goal ) : ?>
					<div class="notice notice-warning inline" style="margin:10px 0 0;padding:8px 12px;">
						<p class="rwgo-alert__text" style="margin:0;">
							<?php esc_html_e( 'This test still needs a defined goal. Edit Control or Variant B in Elementor or Gutenberg and enable a Geo Optimise goal on the CTA, form, checkbox, or destination page — then return to Edit Test and pick it under Goal & tracking.', 'reactwoo-geo-optimise' ); ?>
						</p>
					</div>
				<?php endif; ?>
				<?php if ( $rwgo_tc_bind_q && class_exists( 'RWGO_Experiment_Repository', false ) && $rwgo_tc_exp > 0 ) : ?>
					<?php
					foreach ( RWGO_Experiment_Repository::binding_health_warnings( $rwgo_tc_cfg ) as $rwgo_tc_bh ) :
						if ( ! is_array( $rwgo_tc_bh ) || empty( $rwgo_tc_bh['message'] ) ) {
							continue;
						}
						?>
					<div class="notice notice-warning inline" style="margin:10px 0 0;padding:8px 12px;">
						<p class="rwgo-alert__text" style="margin:0;"><?php echo esc_html( (string) $rwgo_tc_bh['message'] ); ?></p>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
				<div class="rwgo-alert__actions rwgo-actions rwgo-actions--primary-secondary rwgo-actions--stack-mobile" style="margin-top:12px;">
					<?php if ( $rwgo_tc_edit ) : ?>
						<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $rwgo_tc_edit ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( is_string( $rwgo_tc_ce ) && $rwgo_tc_ce ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $rwgo_tc_ce ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
					<?php elseif ( $rwgo_tc_src > 0 ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( get_edit_post_link( $rwgo_tc_src, 'raw' ) ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( is_string( $rwgo_tc_ve ) && $rwgo_tc_ve ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $rwgo_tc_ve ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
					<?php elseif ( $rwgo_tc_vb > 0 ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( get_edit_post_link( $rwgo_tc_vb, 'raw' ) ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="rwgo-stack">
	<?php if ( ! empty( $_GET['rwgo_created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="rwgo-page-notices">
		<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success">
			<p class="rwgo-alert__text"><strong><?php esc_html_e( 'Test published successfully.', 'reactwoo-geo-optimise' ); ?></strong>
			<?php esc_html_e( 'Control and Variant B are ready. Open Edit Test to review targeting and goals.', 'reactwoo-geo-optimise' ); ?></p>
			<div class="rwgo-alert__actions rwgo-actions rwgo-actions--primary-secondary rwgo-actions--stack-mobile">
				<?php
				$c_edit = $rwgo_control_id > 0 ? get_edit_post_link( $rwgo_control_id ) : false;
				$v_edit = $rwgo_var_b_id > 0 ? get_edit_post_link( $rwgo_var_b_id ) : false;
				$exp_h  = $rwgo_created_id > 0 ? admin_url( 'admin.php?page=rwgo-reports#exp-' . $rwgo_created_id ) : admin_url( 'admin.php?page=rwgo-reports' );
				$edit_t = $rwgo_created_id > 0 && class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::edit_test_url( $rwgo_created_id, 'tests' ) : '';
				?>
				<?php if ( $edit_t ) : ?>
					<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $edit_t ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a>
				<?php endif; ?>
				<?php if ( is_string( $c_edit ) && $c_edit ) : ?>
					<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $c_edit ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
				<?php endif; ?>
				<?php if ( is_string( $v_edit ) && $v_edit ) : ?>
					<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $v_edit ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
				<?php endif; ?>
				<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $exp_h ); ?>"><?php esc_html_e( 'View report', 'reactwoo-geo-optimise' ); ?></a>
			</div>
		</div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="rwgo-page-notices">
			<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success"><p class="rwgo-alert__text"><?php esc_html_e( 'Test status updated.', 'reactwoo-geo-optimise' ); ?></p></div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_duplicated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="rwgo-page-notices">
			<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success"><p class="rwgo-alert__text"><?php esc_html_e( 'Test duplicated as a draft.', 'reactwoo-geo-optimise' ); ?></p></div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="rwgo-page-notices">
			<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success"><p class="rwgo-alert__text"><?php esc_html_e( 'Test deleted.', 'reactwoo-geo-optimise' ); ?></p></div>
		</div>
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
				: array( 'assignment_only' => true, 'conversion_mode' => false, 'leading_variant' => null );
			$assign_only = ! empty( $analysis['assignment_only'] );
			$conv_mode   = ! empty( $analysis['conversion_mode'] );
			$lead_slug   = isset( $analysis['leading_variant'] ) ? $analysis['leading_variant'] : null;
			$lead_disp   = ( $conv_mode && $lead_slug ) ? $rwgo_variant_label( $cfg, (string) $lead_slug ) : '';
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
			$edit_url = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::edit_test_url( (int) $exp_post->ID, 'tests' ) : '';
			$report_u = admin_url( 'admin.php?page=rwgo-reports#exp-' . (int) $exp_post->ID );
			$src_title = $src > 0 ? get_the_title( $src ) : '';
			$vb_title  = $var_b_id > 0 ? get_the_title( $var_b_id ) : '';
			$st_key    = sanitize_key( (string) $st );
			$variant_incomplete = ( 'completed' !== $st_key ) && ( $var_b_id <= 0 || ! is_post_publicly_viewable( $var_b_id ) );
			$goal_pending       = ! empty( $cfg['defined_goal_pending'] ) && empty( $cfg['assignment_only'] ) && ( isset( $cfg['winner_mode'] ) ? 'traffic_only' !== $cfg['winner_mode'] : true );
			$goal_mapping_broken = false;
			if ( ! $assign_only && isset( $cfg['goal_selection_mode'] ) && 'defined' === sanitize_key( (string) $cfg['goal_selection_mode'] ) ) {
				$rwgo_gw_chk = class_exists( 'RWGO_Defined_Goal_Service', false )
					? RWGO_Defined_Goal_Service::validate_experiment_config( $cfg )
					: array();
				$goal_mapping_broken = ! empty( $rwgo_gw_chk );
			}
			$fidelity_status    = 'neutral';
			$fidelity_label     = '';
			if ( class_exists( 'RWGO_Page_Duplicator', false ) && $src > 0 && $var_b_id > 0 ) {
				$fidelity_status = RWGO_Page_Duplicator::get_variant_fidelity_status( $src, $var_b_id );
				$fidelity_map    = array(
					'ready'            => __( 'Ready', 'reactwoo-geo-optimise' ),
					'missing_builder'  => __( 'Missing builder data', 'reactwoo-geo-optimise' ),
					'builder_mismatch' => __( 'Builder mismatch', 'reactwoo-geo-optimise' ),
					'duplicate_failed' => __( 'Duplicate validation failed', 'reactwoo-geo-optimise' ),
				);
				$fidelity_label = isset( $fidelity_map[ $fidelity_status ] ) ? $fidelity_map[ $fidelity_status ] : '';
			}
			$fidelity_bad = $var_b_id > 0 && $fidelity_status && ! in_array( $fidelity_status, array( 'ready', 'neutral' ), true );
			$any_incomplete = $variant_incomplete || $goal_pending || $goal_mapping_broken || $fidelity_bad;
			$can_edit_exp       = current_user_can( 'edit_post', $exp_post->ID );
			$can_delete_exp     = current_user_can( 'delete_post', $exp_post->ID );
			$tests_list_url     = admin_url( 'admin.php?page=rwgo-tests' );
			$tt_for_builder     = isset( $cfg['test_type'] ) ? (string) $cfg['test_type'] : 'page_ab';
			$c_builder          = $src > 0 && class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::post_builder_edit_url( $src, $tt_for_builder ) : $c_edit;
			$v_builder          = $var_b_id > 0 && class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::post_builder_edit_url( $var_b_id, $tt_for_builder ) : $v_edit;
			$c_live             = $src > 0 && class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::post_public_view_url( $src ) : '';
			$v_live             = $var_b_id > 0 && class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::post_public_view_url( $var_b_id ) : '';
			$control_ok         = $src > 0 && get_post( $src ) && is_post_publicly_viewable( $src );
			$variant_b_ok       = $var_b_id > 0 && get_post( $var_b_id ) && is_post_publicly_viewable( $var_b_id );
			$edit_variants_url  = '' !== $edit_url ? $edit_url . '#rwgo-sec-variants' : '';
			$gtm_payload        = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::build_modal_payload_for_test( $exp_post, $cfg ) : null;
			$gtm_json             = $gtm_payload ? wp_json_encode( $gtm_payload, JSON_UNESCAPED_UNICODE ) : '';
			?>
		<article class="rwgo-test-card rwgo-panel rwgo-test-card--detail" id="<?php echo esc_attr( 'exp-row-' . (int) $exp_post->ID ); ?>">
			<header class="rwgo-test-card__header rwgo-test-card__header--detail">
				<div class="rwgo-test-card__title-block">
					<h2 class="rwgo-test-card__title"><?php echo esc_html( get_the_title( $exp_post ) ); ?></h2>
					<code class="rwgo-muted-key"><?php echo esc_html( $key ); ?></code>
				</div>
				<div class="rwgo-test-card__pills">
					<span class="rwgo-pill <?php echo esc_attr( $rwgo_status_pill_class( $st ) ); ?>"><?php echo esc_html( $st ); ?></span>
					<?php if ( $any_incomplete ) : ?>
						<span class="rwgo-pill rwgo-pill--incomplete"><?php esc_html_e( 'Incomplete', 'reactwoo-geo-optimise' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $any_incomplete ) : ?>
					<div class="rwgo-test-card__reason-tags" role="list" aria-label="<?php esc_attr_e( 'Incomplete reasons', 'reactwoo-geo-optimise' ); ?>">
						<?php if ( $goal_pending ) : ?>
							<span class="rwgo-tag rwgo-tag--reason" role="listitem"><?php esc_html_e( 'Missing goal', 'reactwoo-geo-optimise' ); ?></span>
						<?php endif; ?>
						<?php if ( $goal_mapping_broken ) : ?>
							<span class="rwgo-tag rwgo-tag--reason" role="listitem"><?php esc_html_e( 'Goal not on page', 'reactwoo-geo-optimise' ); ?></span>
						<?php endif; ?>
						<?php if ( $variant_incomplete ) : ?>
							<span class="rwgo-tag rwgo-tag--reason" role="listitem"><?php esc_html_e( 'Missing variant', 'reactwoo-geo-optimise' ); ?></span>
						<?php endif; ?>
						<?php if ( $fidelity_bad ) : ?>
							<span class="rwgo-tag rwgo-tag--reason" role="listitem"><?php esc_html_e( 'Invalid builder data', 'reactwoo-geo-optimise' ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</header>

			<div class="rwgo-status-strip" role="status">
				<span class="rwgo-status-strip__item"><?php esc_html_e( 'Status:', 'reactwoo-geo-optimise' ); ?> <strong><?php echo esc_html( $st ); ?></strong></span>
				<span class="rwgo-status-strip__sep" aria-hidden="true">·</span>
				<span class="rwgo-status-strip__item"><?php esc_html_e( 'Builder:', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( $builder_lab ); ?></span>
				<span class="rwgo-status-strip__sep" aria-hidden="true">·</span>
				<span class="rwgo-status-strip__item"><?php esc_html_e( 'Success focus:', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( $primary_goal_lab ); ?></span>
				<span class="rwgo-status-strip__sep" aria-hidden="true">·</span>
				<span class="rwgo-status-strip__item"><?php esc_html_e( 'Variants:', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( $var_b_id > 0 ? '1' : '0' ); ?></span>
				<span class="rwgo-status-strip__sep" aria-hidden="true">·</span>
				<span class="rwgo-status-strip__item rwgo-status-strip__health <?php echo $variant_incomplete || $goal_pending || $goal_mapping_broken ? 'rwgo-status-strip__health--bad' : 'rwgo-status-strip__health--ok'; ?>"><?php esc_html_e( 'Health:', 'reactwoo-geo-optimise' ); ?> <?php
				if ( $variant_incomplete ) {
					esc_html_e( 'Missing variant', 'reactwoo-geo-optimise' );
				} elseif ( $goal_pending ) {
					esc_html_e( 'Missing goal', 'reactwoo-geo-optimise' );
				} elseif ( $goal_mapping_broken ) {
					esc_html_e( 'Goal not on page', 'reactwoo-geo-optimise' );
				} else {
					esc_html_e( 'Ready', 'reactwoo-geo-optimise' );
				}
				?></span>
				<?php if ( $var_b_id > 0 && '' !== $fidelity_label && 'neutral' !== $fidelity_status ) : ?>
					<span class="rwgo-status-strip__sep" aria-hidden="true">·</span>
					<span class="rwgo-status-strip__item rwgo-status-strip__fidelity rwgo-status-strip__fidelity--<?php echo esc_attr( str_replace( '_', '-', $fidelity_status ) ); ?>"><?php esc_html_e( 'Fidelity:', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( $fidelity_label ); ?></span>
				<?php endif; ?>
			</div>

			<div class="rwgo-meta-strip rwgo-meta-strip--tests" role="list">
				<span class="rwgo-meta-pill" role="listitem"><?php echo esc_html( $type ? $type_lab : '—' ); ?></span>
				<span class="rwgo-meta-pill" role="listitem"><?php esc_html_e( 'Source:', 'reactwoo-geo-optimise' ); ?> <?php if ( $src > 0 ) : ?><a href="<?php echo esc_url( get_edit_post_link( $src ) ); ?>"><?php echo esc_html( get_the_title( $src ) ); ?></a><?php else : ?>—<?php endif; ?></span>
			</div>

			<div class="rwgo-test-card__action-rows">
				<div class="rwgo-btn-row rwgo-test-card__toolbar rwgo-test-card__toolbar--primary rwgo-btn-row--wrap">
					<?php if ( '' !== $edit_url ) : ?>
						<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $report_u ); ?>"><?php esc_html_e( 'View Report', 'reactwoo-geo-optimise' ); ?></a>
					<?php if ( $gtm_payload && '' !== $gtm_json ) : ?>
						<button type="button" class="rwgo-btn rwgo-btn--icon" data-rwgo-gtm-open="1" data-rwgo-gtm-json="<?php echo esc_attr( $gtm_json ); ?>" title="<?php esc_attr_e( 'Generate GTM setup', 'reactwoo-geo-optimise' ); ?>" aria-label="<?php esc_attr_e( 'GTM setup — copy trigger, variables, GA4 mapping, and example payload for this test', 'reactwoo-geo-optimise' ); ?>">
							<span class="dashicons dashicons-tag" aria-hidden="true"></span>
						</button>
					<?php else : ?>
						<button type="button" class="rwgo-btn rwgo-btn--icon rwgo-btn--icon-disabled" disabled title="<?php esc_attr_e( 'Tracking setup unavailable — configure a goal first', 'reactwoo-geo-optimise' ); ?>" aria-label="<?php esc_attr_e( 'Tracking setup unavailable — configure a goal first', 'reactwoo-geo-optimise' ); ?>">
							<span class="dashicons dashicons-tag" aria-hidden="true"></span>
						</button>
					<?php endif; ?>
					<span class="rwgo-btn-row__spacer" aria-hidden="true"></span>
					<?php if ( $can_delete_exp ) : ?>
						<button type="button" class="button rwgo-btn rwgo-btn--danger-outline" data-rwgo-open-delete="1" data-rwgo-exp-id="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>"><?php esc_html_e( 'Delete Test', 'reactwoo-geo-optimise' ); ?></button>
					<?php endif; ?>
				</div>
				<div class="rwgo-btn-row rwgo-test-card__toolbar rwgo-test-card__toolbar--secondary rwgo-btn-row--wrap">
					<?php if ( is_string( $c_builder ) && $c_builder ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( $c_builder ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( $var_b_id > 0 && is_string( $v_builder ) && $v_builder ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( $v_builder ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( 'active' === $st ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
							<input type="hidden" name="action" value="rwgo_pause_test" />
							<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
							<?php wp_nonce_field( 'rwgo_pause_test' ); ?>
							<button type="submit" class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact"><?php esc_html_e( 'Pause Test', 'reactwoo-geo-optimise' ); ?></button>
						</form>
					<?php elseif ( 'paused' === $st ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
							<input type="hidden" name="action" value="rwgo_resume_test" />
							<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
							<?php wp_nonce_field( 'rwgo_resume_test' ); ?>
							<button type="submit" class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact"><?php esc_html_e( 'Resume Test', 'reactwoo-geo-optimise' ); ?></button>
						</form>
					<?php endif; ?>
					<?php if ( 'completed' !== $st ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'End this test?', 'reactwoo-geo-optimise' ) ); ?>');">
							<input type="hidden" name="action" value="rwgo_end_test" />
							<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
							<?php wp_nonce_field( 'rwgo_end_test' ); ?>
							<button type="submit" class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact"><?php esc_html_e( 'End Test', 'reactwoo-geo-optimise' ); ?></button>
						</form>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
						<input type="hidden" name="action" value="rwgo_duplicate_test" />
						<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
						<?php wp_nonce_field( 'rwgo_duplicate_test' ); ?>
						<button type="submit" class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact"><?php esc_html_e( 'Duplicate Test', 'reactwoo-geo-optimise' ); ?></button>
					</form>
					<?php if ( $can_edit_exp && 'completed' !== $st_key && $var_b_id > 0 && get_post( $var_b_id ) && class_exists( 'RWGO_Admin', false ) ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( RWGO_Admin::promote_winner_url( (int) $exp_post->ID, 'tests' ) ); ?>"><?php esc_html_e( 'Promote Winner', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<section class="rwgo-variants-section" aria-labelledby="rwgo-variants-heading-<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>">
				<h3 class="rwgo-section__title rwgo-variants-section__title" id="rwgo-variants-heading-<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>"><?php esc_html_e( 'Variants', 'reactwoo-geo-optimise' ); ?></h3>
				<div class="rwgo-variant-grid">
					<div class="rwgo-variant-card">
						<p class="rwgo-variant-card__label"><?php esc_html_e( 'Control (A)', 'reactwoo-geo-optimise' ); ?></p>
						<p class="rwgo-variant-card__title"><?php echo $src > 0 ? esc_html( $src_title ) : '—'; ?></p>
						<p class="rwgo-variant-card__meta"><?php esc_html_e( 'Builder:', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( $builder_lab ); ?></p>
						<p class="rwgo-variant-card__status <?php echo $control_ok ? 'rwgo-variant-card__status--ok' : 'rwgo-variant-card__status--bad'; ?>"><?php echo $control_ok ? '✓ ' . esc_html__( 'Ready', 'reactwoo-geo-optimise' ) : esc_html__( 'Unavailable', 'reactwoo-geo-optimise' ); ?></p>
						<div class="rwgo-btn-row rwgo-btn-row--wrap">
							<?php if ( is_string( $c_builder ) && $c_builder ) : ?>
								<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( $c_builder ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geo-optimise' ); ?></a>
							<?php endif; ?>
							<?php if ( is_string( $c_live ) && $c_live ) : ?>
								<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( $c_live ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'reactwoo-geo-optimise' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<div class="rwgo-variant-card<?php echo $var_b_id <= 0 ? ' rwgo-variant-card--empty' : ''; ?>">
						<p class="rwgo-variant-card__label"><?php esc_html_e( 'Variant B', 'reactwoo-geo-optimise' ); ?></p>
						<?php if ( $var_b_id > 0 ) : ?>
							<p class="rwgo-variant-card__title"><?php echo esc_html( $vb_title ); ?></p>
							<p class="rwgo-variant-card__meta"><?php esc_html_e( 'Builder:', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( $builder_lab ); ?></p>
							<p class="rwgo-variant-card__status <?php echo $variant_b_ok ? 'rwgo-variant-card__status--ok' : 'rwgo-variant-card__status--bad'; ?>"><?php echo $variant_b_ok ? '✓ ' . esc_html__( 'Ready', 'reactwoo-geo-optimise' ) : esc_html__( 'Unavailable', 'reactwoo-geo-optimise' ); ?></p>
							<div class="rwgo-btn-row rwgo-btn-row--wrap">
								<?php if ( is_string( $v_builder ) && $v_builder ) : ?>
									<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( $v_builder ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geo-optimise' ); ?></a>
								<?php endif; ?>
								<?php if ( is_string( $v_live ) && $v_live ) : ?>
									<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( $v_live ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'reactwoo-geo-optimise' ); ?></a>
								<?php endif; ?>
								<?php if ( '' !== $edit_variants_url ) : ?>
									<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( $edit_variants_url ); ?>"><?php esc_html_e( 'Replace', 'reactwoo-geo-optimise' ); ?></a>
								<?php endif; ?>
								<?php if ( $can_edit_exp && $var_b_id > 0 ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Remove Variant B from this test only? The page will stay in the site.', 'reactwoo-geo-optimise' ) ); ?>');">
										<?php wp_nonce_field( 'rwgo_detach_variant' ); ?>
										<input type="hidden" name="action" value="rwgo_detach_variant" />
										<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
										<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $tests_list_url ); ?>" />
										<input type="hidden" name="rwgo_detach_mode" value="keep" />
										<button type="submit" class="button rwgo-btn rwgo-btn--danger-outline rwgo-btn--compact"><?php esc_html_e( 'Remove', 'reactwoo-geo-optimise' ); ?></button>
									</form>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<p class="rwgo-variant-card__empty-title"><?php esc_html_e( 'No variant attached', 'reactwoo-geo-optimise' ); ?></p>
							<p class="rwgo-variant-card__empty-body"><?php esc_html_e( 'This test needs a variant to run or compare results.', 'reactwoo-geo-optimise' ); ?></p>
							<div class="rwgo-btn-row rwgo-btn-row--wrap">
								<?php if ( $src > 0 && get_post( $src ) && class_exists( 'RWGO_Variant_Lifecycle', false ) && $can_edit_exp ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Create Variant B as a new duplicate of Control?', 'reactwoo-geo-optimise' ) ); ?>');">
										<?php wp_nonce_field( 'rwgo_regenerate_variant' ); ?>
										<input type="hidden" name="action" value="rwgo_regenerate_variant" />
										<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $exp_post->ID ); ?>" />
										<input type="hidden" name="rwgo_redirect_to" value="<?php echo esc_url( $tests_list_url ); ?>" />
										<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary rwgo-btn--compact"><?php esc_html_e( 'Create from Control', 'reactwoo-geo-optimise' ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( '' !== $edit_variants_url ) : ?>
									<a class="button rwgo-btn rwgo-btn--secondary rwgo-btn--compact" href="<?php echo esc_url( $edit_variants_url ); ?>"><?php esc_html_e( 'Use Existing Page', 'reactwoo-geo-optimise' ); ?></a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<?php if ( ( is_string( $c_live ) && $c_live ) || ( is_string( $v_live ) && $v_live ) ) : ?>
				<p class="rwgo-test-card__live-links">
					<?php if ( is_string( $c_live ) && $c_live ) : ?>
						<a href="<?php echo esc_url( $c_live ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Control live', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( is_string( $c_live ) && $c_live && is_string( $v_live ) && $v_live ) : ?>
						<span class="rwgo-test-card__live-sep" aria-hidden="true">·</span>
					<?php endif; ?>
					<?php if ( is_string( $v_live ) && $v_live ) : ?>
						<a href="<?php echo esc_url( $v_live ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Variant B live', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</article>
			<?php
		endforeach;
		?>
	<?php endif; ?>
	</div>

	<?php if ( ! empty( $rwgo_experiments ) && class_exists( 'RWGO_Admin', false ) && current_user_can( RWGO_Admin::required_capability() ) ) : ?>
		<dialog class="rwgo-dialog" id="rwgo-delete-test-dialog" aria-labelledby="rwgo-delete-test-dialog-title">
			<div class="rwgo-dialog__inner">
				<h2 class="rwgo-dialog__title" id="rwgo-delete-test-dialog-title"><?php esc_html_e( 'Delete this test?', 'reactwoo-geo-optimise' ); ?></h2>
				<p class="rwgo-dialog__body"><?php esc_html_e( 'This will permanently remove the test, its variants, and associated data. This cannot be undone.', 'reactwoo-geo-optimise' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-dialog__form">
					<?php wp_nonce_field( 'rwgo_delete_test' ); ?>
					<input type="hidden" name="action" value="rwgo_delete_test" />
					<input type="hidden" name="rwgo_experiment_id" id="rwgo-delete-test-exp-id" value="0" />
					<label class="rwgo-checkbox-line rwgo-dialog__checkbox">
						<input type="checkbox" name="rwgo_delete_variant_pages" value="1" />
						<?php esc_html_e( 'Also delete associated variant pages', 'reactwoo-geo-optimise' ); ?>
					</label>
					<div class="rwgo-btn-row rwgo-dialog__actions">
						<button type="button" class="button rwgo-btn rwgo-btn--secondary" data-rwgo-close-delete="1"><?php esc_html_e( 'Cancel', 'reactwoo-geo-optimise' ); ?></button>
						<button type="submit" class="button rwgo-btn rwgo-btn--danger"><?php esc_html_e( 'Delete test', 'reactwoo-geo-optimise' ); ?></button>
					</div>
				</form>
			</div>
		</dialog>
		<dialog class="rwgo-dialog rwgo-dialog--wide" id="rwgo-gtm-modal" aria-labelledby="rwgo-gtm-modal-title">
			<div class="rwgo-dialog__inner rwgo-gtm-modal__wrap">
				<h2 class="rwgo-dialog__title" id="rwgo-gtm-modal-title"></h2>
				<div id="rwgo-gtm-modal-body" class="rwgo-gtm-modal__body"></div>
				<div class="rwgo-btn-row rwgo-gtm-modal__footer">
					<button type="button" class="button rwgo-btn rwgo-btn--secondary" data-rwgo-gtm-close="1"><?php esc_html_e( 'Close', 'reactwoo-geo-optimise' ); ?></button>
				</div>
			</div>
		</dialog>
		<script>
		(function () {
			var dlg = document.getElementById('rwgo-delete-test-dialog');
			var inp = document.getElementById('rwgo-delete-test-exp-id');
			if (!dlg || !inp) return;
			var delCb = dlg.querySelector('input[name="rwgo_delete_variant_pages"]');
			document.querySelectorAll('[data-rwgo-open-delete]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var id = btn.getAttribute('data-rwgo-exp-id');
					if (id) inp.value = id;
					if (delCb) delCb.checked = false;
					if (dlg.showModal) dlg.showModal();
				});
			});
			document.querySelectorAll('[data-rwgo-close-delete]').forEach(function (btn) {
				btn.addEventListener('click', function () { if (dlg.close) dlg.close(); });
			});
			dlg.addEventListener('click', function (e) {
				if (e.target === dlg && dlg.close) dlg.close();
			});
		})();
		</script>
	<?php endif; ?>
</div>
