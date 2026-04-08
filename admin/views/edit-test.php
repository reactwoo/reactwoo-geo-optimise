<?php
/**
 * Edit Test — update managed test settings (experiment config in storage).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_exp_id = isset( $_GET['rwgo_experiment_id'] ) ? absint( wp_unslash( $_GET['rwgo_experiment_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgc_nav_current = 'rwgo-tests';

$rwgo_test_types = array( 'page_ab', 'elementor_page', 'gutenberg_page', 'woo_product', 'custom_php' );
$rwgo_catalog      = array();
foreach ( $rwgo_test_types as $tt ) {
	$rwgo_catalog[ $tt ] = class_exists( 'RWGO_Admin_Content_Catalog', false )
		? RWGO_Admin_Content_Catalog::get_choices( $tt )
		: array();
}

$rwgo_exp_post = $rwgo_exp_id > 0 ? get_post( $rwgo_exp_id ) : null;
$rwgo_cfg = ( $rwgo_exp_post instanceof \WP_Post && RWGO_Experiment_CPT::POST_TYPE === $rwgo_exp_post->post_type )
	? RWGO_Experiment_Repository::get_config( $rwgo_exp_id )
	: array();
if ( ! empty( $rwgo_cfg ) && class_exists( 'RWGO_Experiment_Repository', false ) && $rwgo_exp_id > 0 ) {
	$rwgo_cfg = RWGO_Experiment_Repository::normalize_page_bindings( $rwgo_cfg, $rwgo_exp_id, false );
}

$rwgo_load_error = false;
if ( ! $rwgo_exp_post instanceof \WP_Post || RWGO_Experiment_CPT::POST_TYPE !== $rwgo_exp_post->post_type ) {
	$rwgo_load_error = 'notfound';
} elseif ( ! current_user_can( 'edit_post', $rwgo_exp_id ) ) {
	$rwgo_load_error = 'perm';
} elseif ( empty( $rwgo_cfg['experiment_key'] ) ) {
	$rwgo_load_error = 'config';
}

$src_id   = (int) ( $rwgo_cfg['source_page_id'] ?? 0 );
$var_b_id = 0;
if ( ! empty( $rwgo_cfg['variants'] ) && is_array( $rwgo_cfg['variants'] ) ) {
	foreach ( $rwgo_cfg['variants'] as $row ) {
		if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
			$var_b_id = (int) ( $row['page_id'] ?? 0 );
			break;
		}
	}
}

$tgt       = isset( $rwgo_cfg['targeting'] ) && is_array( $rwgo_cfg['targeting'] ) ? $rwgo_cfg['targeting'] : array( 'mode' => 'everyone' );
$tgt_mode  = isset( $tgt['mode'] ) ? sanitize_key( (string) $tgt['mode'] ) : 'everyone';
$countries = ( 'countries' === $tgt_mode && ! empty( $tgt['countries'] ) && is_array( $tgt['countries'] ) )
	? implode( ', ', $tgt['countries'] )
	: '';

$inferred_goal = class_exists( 'RWGO_Admin_Wizard', false )
	? RWGO_Admin_Wizard::infer_goal_type_from_config( $rwgo_cfg )
	: 'page_view';
if ( 'traffic_only' === $inferred_goal ) {
	$wm        = 'traffic_only';
	$goal_type = 'page_view';
} else {
	$wm        = 'goal';
	$goal_type = $inferred_goal;
}

$rwgo_return_ctx = isset( $_GET['rwgo_return'] ) ? sanitize_key( wp_unslash( $_GET['rwgo_return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $rwgo_return_ctx, array( 'tests', 'reports' ), true ) ) {
	$rwgo_return_ctx = '';
}

$rwgo_goal_sel_mode = isset( $rwgo_cfg['goal_selection_mode'] ) ? sanitize_key( (string) $rwgo_cfg['goal_selection_mode'] ) : 'automatic';
$rwgo_def_goal_json = class_exists( 'RWGO_Admin_Wizard', false ) ? RWGO_Admin_Wizard::defined_goal_json_from_config( $rwgo_cfg ) : '';

$rwgo_prefill = array(
	'experiment_id'      => $rwgo_exp_id,
	'name'               => $rwgo_exp_post instanceof \WP_Post ? get_the_title( $rwgo_exp_post ) : '',
	'test_type'          => isset( $rwgo_cfg['test_type'] ) ? (string) $rwgo_cfg['test_type'] : 'page_ab',
	'source_id'          => $src_id,
	'variant_b_id'       => $var_b_id,
	'targeting_mode'     => $tgt_mode,
	'countries_csv'      => $countries,
	'winner_mode'        => $wm,
	'goal_type'          => $goal_type,
	'goal_selection_mode'=> $rwgo_goal_sel_mode,
	'defined_goal_json'  => $rwgo_def_goal_json,
	'status'             => isset( $rwgo_cfg['status'] ) ? (string) $rwgo_cfg['status'] : '',
	'experiment_key'     => isset( $rwgo_cfg['experiment_key'] ) ? (string) $rwgo_cfg['experiment_key'] : '',
	'return_context'     => $rwgo_return_ctx,
);

$key_for_stats = $rwgo_prefill['experiment_key'];
$exp_dist      = isset( $exp_dist ) && is_array( $exp_dist ) ? $exp_dist : array();
$vsum          = 0;
if ( '' !== $key_for_stats && isset( $exp_dist[ $key_for_stats ] ) && is_array( $exp_dist[ $key_for_stats ] ) ) {
	foreach ( $exp_dist[ $key_for_stats ] as $c ) {
		$vsum += (int) $c;
	}
}
$primary_goal_lab = class_exists( 'RWGO_Goal_Service', false ) ? RWGO_Goal_Service::get_primary_goal_label( $rwgo_cfg ) : '—';
$analysis         = class_exists( 'RWGO_Winner_Service', false ) && '' !== $key_for_stats
	? RWGO_Winner_Service::analyze( $key_for_stats, $rwgo_cfg, $exp_dist )
	: array( 'assignment_only' => true, 'conversion_mode' => false, 'leading_variant' => null );
$assign_only = ! empty( $analysis['assignment_only'] );
$conv_mode   = ! empty( $analysis['conversion_mode'] );
$lead_slug   = isset( $analysis['leading_variant'] ) ? $analysis['leading_variant'] : null;
$lead_label  = '';
if ( $lead_slug && $conv_mode ) {
	foreach ( $rwgo_cfg['variants'] ?? array() as $row ) {
		if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
			continue;
		}
		if ( sanitize_key( (string) $row['variant_id'] ) === sanitize_key( (string) $lead_slug ) ) {
			$lead_label = isset( $row['variant_label'] ) ? (string) $row['variant_label'] : (string) $lead_slug;
			break;
		}
	}
}

$rwgo_fidelity_status = 'neutral';
$rwgo_fidelity_label  = '—';
if ( class_exists( 'RWGO_Page_Duplicator', false ) && $src_id > 0 && $var_b_id > 0 ) {
	$rwgo_fidelity_status = RWGO_Page_Duplicator::get_variant_fidelity_status( $src_id, $var_b_id );
	$fidelity_labels      = array(
		'ready'            => __( 'Ready', 'reactwoo-geo-optimise' ),
		'missing'          => __( 'Missing builder data', 'reactwoo-geo-optimise' ),
		'missing_builder'  => __( 'Missing builder data', 'reactwoo-geo-optimise' ),
		'builder_mismatch' => __( 'Builder mismatch', 'reactwoo-geo-optimise' ),
		'duplicate_failed' => __( 'Duplicate validation failed', 'reactwoo-geo-optimise' ),
		'neutral'          => '—',
	);
	$rwgo_fidelity_label = isset( $fidelity_labels[ $rwgo_fidelity_status ] ) ? $fidelity_labels[ $rwgo_fidelity_status ] : $rwgo_fidelity_status;
}

$rwgo_defined_goal_pending = ! empty( $rwgo_cfg['defined_goal_pending'] );

$rwgo_goal_warnings = class_exists( 'RWGO_Defined_Goal_Service', false )
	? RWGO_Defined_Goal_Service::validate_experiment_config( $rwgo_cfg )
	: array();
$rwgo_goal_mapping_broken = ! empty( $rwgo_goal_warnings );

$rwgo_variant_title_matches_control = false;
if ( $src_id > 0 && $var_b_id > 0 && class_exists( 'RWGO_Page_Naming_Service', false ) ) {
	$rwgo_variant_title_matches_control = RWGO_Page_Naming_Service::variant_title_matches_control_title( $src_id, $var_b_id );
}

$rwgo_form_mode = 'edit';
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--edit-test">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Edit Test', 'reactwoo-geo-optimise' ),
			__( 'Update audience, goals, or variant setup for this test. Changes apply to future visitors unless stated otherwise.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( $rwgo_load_error ) : ?>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Test not found', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'This test could not be loaded or you do not have permission to edit it.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-tests' ) ); ?>"><?php esc_html_e( 'Back to Tests', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	<?php else : ?>

		<?php if ( $rwgo_variant_title_matches_control ) : ?>
			<div class="notice notice-warning rwgo-notice"><p><?php esc_html_e( 'Variant B uses the same title as Control. Rename the variant in the editor so visitors and reports can tell them apart.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>

		<?php
		$rwgo_bind_health = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::binding_health_warnings( $rwgo_cfg )
			: array();
		?>
		<?php foreach ( $rwgo_bind_health as $rwgo_bh ) : ?>
			<?php if ( is_array( $rwgo_bh ) && ! empty( $rwgo_bh['message'] ) ) : ?>
				<div class="notice notice-warning rwgo-notice"><p><?php echo esc_html( (string) $rwgo_bh['message'] ); ?></p></div>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if ( ! empty( $_GET['rwgo_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible rwgo-notice"><p><?php esc_html_e( 'Test settings saved.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['rwgo_promoted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible rwgo-notice"><p><?php esc_html_e( 'Variant B was copied into Control and this test was marked completed.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['rwgo_regenerated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php
			$rwgo_regen_vpost = ( $var_b_id > 0 ) ? get_post( $var_b_id ) : null;
			$rwgo_regen_url   = ( $rwgo_regen_vpost instanceof \WP_Post ) ? get_permalink( $var_b_id ) : '';
			if ( $rwgo_regen_url && function_exists( 'wp_make_link_relative' ) ) {
				$rwgo_regen_url = wp_make_link_relative( $rwgo_regen_url );
			}
			?>
			<div class="notice notice-success is-dismissible rwgo-notice">
				<?php if ( $rwgo_regen_vpost instanceof \WP_Post && is_string( $rwgo_regen_url ) && '' !== $rwgo_regen_url ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: variant page title */
							esc_html__( 'Variant created: %s', 'reactwoo-geo-optimise' ),
							esc_html( get_the_title( $rwgo_regen_vpost ) )
						);
						?>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: relative or full URL path to variant */
							esc_html__( 'URL: %s', 'reactwoo-geo-optimise' ),
							esc_html( $rwgo_regen_url )
						);
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'Variant B was recreated from Control.', 'reactwoo-geo-optimise' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['rwgo_detached'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-warning rwgo-notice"><p><?php esc_html_e( 'Variant B was removed from this test. The test is paused until you link a new variant.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['rwgo_created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php
			$rwgo_vc_mode = isset( $rwgo_cfg['variant_creation'] ) ? sanitize_key( (string) $rwgo_cfg['variant_creation'] ) : '';
			$rwgo_created_vpost = ( $var_b_id > 0 && in_array( $rwgo_vc_mode, array( 'duplicate', 'blank' ), true ) ) ? get_post( $var_b_id ) : null;
			$rwgo_created_url   = ( $rwgo_created_vpost instanceof \WP_Post ) ? get_permalink( $var_b_id ) : '';
			if ( $rwgo_created_url && function_exists( 'wp_make_link_relative' ) ) {
				$rwgo_created_url = wp_make_link_relative( $rwgo_created_url );
			}
			?>
			<div class="notice notice-success is-dismissible rwgo-notice">
				<p><?php esc_html_e( 'Test created. Review settings below, then edit Control or Variant B as needed.', 'reactwoo-geo-optimise' ); ?></p>
				<?php if ( $rwgo_created_vpost instanceof \WP_Post && is_string( $rwgo_created_url ) && '' !== $rwgo_created_url ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: variant page title */
							esc_html__( 'Variant created: %s', 'reactwoo-geo-optimise' ),
							esc_html( get_the_title( $rwgo_created_vpost ) )
						);
						?>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: relative or full URL path to variant */
							esc_html__( 'URL: %s', 'reactwoo-geo-optimise' ),
							esc_html( $rwgo_created_url )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['rwgo_needs_defined_goal'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-warning rwgo-notice"><p><?php esc_html_e( 'Test created. This test still needs a defined goal. Edit the page or variant in Elementor or Gutenberg and enable a Geo Optimise goal on the CTA, form, opt-in, or destination page you want to measure — then return here and pick it under Goal & tracking.', 'reactwoo-geo-optimise' ); ?></p>
				<p class="rwgo-notice__actions">
					<?php
					$rwgo_s = $src_id > 0 ? $src_id : 0;
					$rwgo_v = $var_b_id > 0 ? $var_b_id : 0;
					$rwgo_tt = isset( $rwgo_cfg['test_type'] ) ? (string) $rwgo_cfg['test_type'] : 'page_ab';
					if ( $rwgo_s > 0 && class_exists( 'RWGO_Admin', false ) ) {
						$ce = RWGO_Admin::post_builder_edit_url( $rwgo_s, $rwgo_tt );
						if ( is_string( $ce ) && $ce ) {
							echo '<a class="button rwgo-btn rwgo-btn--secondary" href="' . esc_url( $ce ) . '">' . esc_html__( 'Edit Control', 'reactwoo-geo-optimise' ) . '</a> ';
						}
					}
					if ( $rwgo_v > 0 && class_exists( 'RWGO_Admin', false ) ) {
						$ve = RWGO_Admin::post_builder_edit_url( $rwgo_v, $rwgo_tt );
						if ( is_string( $ve ) && $ve ) {
							echo '<a class="button rwgo-btn rwgo-btn--secondary" href="' . esc_url( $ve ) . '">' . esc_html__( 'Edit Variant B', 'reactwoo-geo-optimise' ) . '</a> ';
						}
					}
					?>
					<span class="description"><?php esc_html_e( 'Or choose automatic goal detection in Goal & tracking below.', 'reactwoo-geo-optimise' ); ?></span>
				</p>
			</div>
		<?php endif; ?>
		<?php if ( $rwgo_defined_goal_pending && empty( $_GET['rwgo_needs_defined_goal'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-warning rwgo-notice"><p><?php esc_html_e( 'Defined goal not set: this test is using automatic goal detection until you select a builder-defined goal below.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['rwgo_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-error rwgo-notice"><p>
				<?php
				$err = sanitize_key( (string) wp_unslash( $_GET['rwgo_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( 'variant_b' === $err ) {
					esc_html_e( 'Choose a valid Variant B that matches this test type and is not the Control.', 'reactwoo-geo-optimise' );
				} elseif ( 'variant_missing' === $err ) {
					esc_html_e( 'Variant B is missing — pick a replacement in the Variants section.', 'reactwoo-geo-optimise' );
				} elseif ( 'dup' === $err ) {
					esc_html_e( 'Could not duplicate the source for a new Variant B.', 'reactwoo-geo-optimise' );
				} elseif ( 'dup_invalid' === $err ) {
					esc_html_e( 'Variant B could not be created as a valid Elementor duplicate. The page was not attached to the test. Try again, use an existing page, or create a blank variant.', 'reactwoo-geo-optimise' );
				} elseif ( 'regen_invalid' === $err ) {
					esc_html_e( 'Variant B could not be regenerated as a valid duplicate. The previous variant was left unchanged.', 'reactwoo-geo-optimise' );
				} elseif ( 'promote' === $err ) {
					esc_html_e( 'Could not promote Variant B to Control. Check permissions and that both pages exist.', 'reactwoo-geo-optimise' );
				} elseif ( 'detach' === $err ) {
					esc_html_e( 'Could not remove Variant B from this test.', 'reactwoo-geo-optimise' );
				} elseif ( 'regen' === $err ) {
					esc_html_e( 'Could not regenerate Variant B from Control.', 'reactwoo-geo-optimise' );
				} elseif ( 'missing' === $err ) {
					esc_html_e( 'Please enter a test name.', 'reactwoo-geo-optimise' );
				} else {
					esc_html_e( 'Could not save changes. Try again.', 'reactwoo-geo-optimise' );
				}
				?>
			</p></div>
		<?php endif; ?>

		<?php if ( $rwgo_goal_mapping_broken ) : ?>
			<div class="notice notice-warning rwgo-notice rwgo-notice--goal-mapping" id="rwgo-goal-readiness">
				<p class="rwgo-notice__title"><strong><?php esc_html_e( 'Defined goal needs review before publishing', 'reactwoo-geo-optimise' ); ?></strong></p>
				<ul class="rwgo-notice__list">
					<?php foreach ( $rwgo_goal_warnings as $rwgo_gw ) : ?>
						<li><?php echo esc_html( isset( $rwgo_gw['message'] ) ? (string) $rwgo_gw['message'] : '' ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="rwgo-notice__actions">
					<?php
					$rwgo_tt_warn = isset( $rwgo_cfg['test_type'] ) ? (string) $rwgo_cfg['test_type'] : 'page_ab';
					$rwgo_show_c  = $src_id > 0 && class_exists( 'RWGO_Admin', false );
					$rwgo_show_v  = $var_b_id > 0 && class_exists( 'RWGO_Admin', false );
					$rwgo_ce      = $rwgo_show_c ? RWGO_Admin::post_builder_edit_url( $src_id, $rwgo_tt_warn ) : '';
					$rwgo_ve      = $rwgo_show_v ? RWGO_Admin::post_builder_edit_url( $var_b_id, $rwgo_tt_warn ) : '';
					$rwgo_goal_h  = class_exists( 'RWGO_Admin', false )
						? RWGO_Admin::edit_test_url( (int) $rwgo_exp_id, '' )
						: admin_url( 'admin.php?page=rwgo-edit-test&rwgo_experiment_id=' . (int) $rwgo_exp_id );
					$rwgo_goal_h  = is_string( $rwgo_goal_h ) && '' !== $rwgo_goal_h ? $rwgo_goal_h . '#rwgo-sec-goal' : '';
					if ( is_string( $rwgo_ce ) && $rwgo_ce ) :
						?>
						<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $rwgo_ce ); ?>"><?php esc_html_e( 'Open Control in builder', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( is_string( $rwgo_ve ) && $rwgo_ve ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $rwgo_ve ); ?>"><?php esc_html_e( 'Open Variant B in builder', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( is_string( $rwgo_goal_h ) && $rwgo_goal_h ) : ?>
						<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $rwgo_goal_h ); ?>"><?php esc_html_e( 'Review goal mapping', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
				</p>
				<p class="description"><?php esc_html_e( 'This test’s saved defined goal no longer cleanly matches the live markers on Control and Variant B. Re-select the goal under Goal & tracking before publishing further edits or trusting conversion totals.', 'reactwoo-geo-optimise' ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		$rwgo_mgmt_status    = isset( $rwgo_cfg['status'] ) ? sanitize_key( (string) $rwgo_cfg['status'] ) : '';
		$rwgo_mgmt_completed = ( 'completed' === $rwgo_mgmt_status );
		$rwgo_edit_redirect  = class_exists( 'RWGO_Admin', false )
			? RWGO_Admin::edit_test_action_fallback_url( $rwgo_exp_id )
			: admin_url( 'admin.php?page=rwgo-edit-test&rwgo_experiment_id=' . (int) $rwgo_exp_id );
		$rwgo_builder_lab      = '';
		if ( ! empty( $rwgo_cfg['builder_detection']['user_builder_label'] ) ) {
			$rwgo_builder_lab = (string) $rwgo_cfg['builder_detection']['user_builder_label'];
		} else {
			$bt = isset( $rwgo_cfg['builder_type'] ) ? sanitize_key( (string) $rwgo_cfg['builder_type'] ) : '';
			$bmap = array(
				'elementor'   => __( 'Elementor', 'reactwoo-geo-optimise' ),
				'gutenberg'   => __( 'Gutenberg', 'reactwoo-geo-optimise' ),
				'classic'     => __( 'Classic', 'reactwoo-geo-optimise' ),
				'woocommerce' => __( 'WooCommerce', 'reactwoo-geo-optimise' ),
			);
			$rwgo_builder_lab = isset( $bmap[ $bt ] ) ? $bmap[ $bt ] : ( $bt ? $bt : '—' );
		}
		$rwgo_variant_incomplete = ( 'completed' !== $rwgo_mgmt_status ) && ( $var_b_id <= 0 || ! is_post_publicly_viewable( $var_b_id ) );
		?>
		<div class="rwgo-card rwgo-edit-summary">
			<div class="rwgo-edit-summary__head">
				<h2 class="rwgo-section__title"><?php esc_html_e( 'Test summary', 'reactwoo-geo-optimise' ); ?></h2>
				<span class="rwgo-status-strip__status"><?php esc_html_e( 'Status:', 'reactwoo-geo-optimise' ); ?> <strong><?php echo esc_html( $rwgo_prefill['status'] ); ?></strong></span>
			</div>
			<div class="rwgo-meta-strip" role="list">
				<span class="rwgo-meta-pill" role="listitem"><span class="rwgo-meta-pill__k"><?php esc_html_e( 'Builder', 'reactwoo-geo-optimise' ); ?></span> <?php echo esc_html( $rwgo_builder_lab ); ?></span>
				<span class="rwgo-meta-pill" role="listitem"><span class="rwgo-meta-pill__k"><?php esc_html_e( 'Success focus', 'reactwoo-geo-optimise' ); ?></span> <?php echo esc_html( $assign_only ? __( 'Traffic split only', 'reactwoo-geo-optimise' ) : $primary_goal_lab ); ?></span>
				<span class="rwgo-meta-pill" role="listitem"><span class="rwgo-meta-pill__k"><?php esc_html_e( 'Variants', 'reactwoo-geo-optimise' ); ?></span> <?php echo esc_html( $var_b_id > 0 ? '1' : '0' ); ?></span>
				<span class="rwgo-meta-pill rwgo-meta-pill--health <?php echo ( $rwgo_variant_incomplete || $rwgo_goal_mapping_broken ) ? 'rwgo-meta-pill--bad' : 'rwgo-meta-pill--ok'; ?>" role="listitem">
					<span class="rwgo-meta-pill__k"><?php esc_html_e( 'Health', 'reactwoo-geo-optimise' ); ?></span>
					<?php
					if ( $rwgo_variant_incomplete ) {
						esc_html_e( 'Missing variant', 'reactwoo-geo-optimise' );
					} elseif ( $rwgo_goal_mapping_broken ) {
						esc_html_e( 'Goal missing on page', 'reactwoo-geo-optimise' );
					} else {
						esc_html_e( 'Ready', 'reactwoo-geo-optimise' );
					}
					?>
				</span>
				<?php if ( $var_b_id > 0 && 'neutral' !== $rwgo_fidelity_status ) : ?>
					<span class="rwgo-meta-pill rwgo-meta-pill--fidelity rwgo-meta-pill--fidelity-<?php echo esc_attr( $rwgo_fidelity_status ); ?>" role="listitem">
						<span class="rwgo-meta-pill__k"><?php esc_html_e( 'Variant fidelity', 'reactwoo-geo-optimise' ); ?></span>
						<?php echo esc_html( $rwgo_fidelity_label ); ?>
					</span>
				<?php endif; ?>
			</div>
			<?php if ( $var_b_id > 0 && in_array( $rwgo_fidelity_status, array( 'missing_builder', 'builder_mismatch', 'duplicate_failed' ), true ) ) : ?>
				<div class="notice notice-warning rwgo-notice rwgo-notice--fidelity">
					<p><strong><?php esc_html_e( 'Variant B is missing or invalid', 'reactwoo-geo-optimise' ); ?></strong></p>
					<p><?php esc_html_e( 'Use Regenerate from Control, pick an existing page, or create a blank variant in the Variants section below.', 'reactwoo-geo-optimise' ); ?></p>
				</div>
			<?php endif; ?>
			<p class="rwgo-hint rwgo-hint--tight"><?php esc_html_e( 'Internal experiment key (for developers & integrations):', 'reactwoo-geo-optimise' ); ?> <code><?php echo esc_html( $key_for_stats ); ?></code></p>
			<ul class="rwgo-checklist rwgo-checklist--compact">
				<li><?php esc_html_e( 'Visitors assigned (total):', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( (string) $vsum ); ?></li>
				<li><?php esc_html_e( 'Leading variant:', 'reactwoo-geo-optimise' ); ?> <?php echo $assign_only ? esc_html__( '— (traffic split only)', 'reactwoo-geo-optimise' ) : ( $lead_label ? esc_html( $lead_label ) : esc_html__( '—', 'reactwoo-geo-optimise' ) ); ?></li>
			</ul>
			<?php if ( $src_id > 0 && get_post( $src_id ) && $var_b_id > 0 && get_post( $var_b_id ) ) : ?>
				<div class="rwgo-btn-row rwgo-btn-row--wrap">
					<?php
					$rwgo_tt = isset( $rwgo_cfg['test_type'] ) ? (string) $rwgo_cfg['test_type'] : 'page_ab';
					$ce      = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::post_builder_edit_url( $src_id, $rwgo_tt ) : get_edit_post_link( $src_id );
					$ve      = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::post_builder_edit_url( $var_b_id, $rwgo_tt ) : get_edit_post_link( $var_b_id );
					?>
					<?php if ( is_string( $ce ) && $ce ) : ?>
						<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $ce ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( is_string( $ve ) && $ve ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( $ve ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( class_exists( 'RWGO_Variant_Lifecycle', false ) && ! $rwgo_load_error ) : ?>
			<?php require RWGO_PATH . 'admin/views/partials/test-variant-management-edit.php'; ?>
		<?php endif; ?>

		<?php require RWGO_PATH . 'admin/views/partials/test-form.php'; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-duplicate-after-form">
			<input type="hidden" name="action" value="rwgo_duplicate_test" />
			<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $rwgo_exp_id ); ?>" />
			<?php wp_nonce_field( 'rwgo_duplicate_test' ); ?>
			<p class="rwgo-hint"><?php esc_html_e( 'Need a different structure or source? Duplicate creates a new test draft.', 'reactwoo-geo-optimise' ); ?></p>
			<button type="submit" class="button rwgo-btn rwgo-btn--secondary"><?php esc_html_e( 'Duplicate Test', 'reactwoo-geo-optimise' ); ?></button>
		</form>
	<?php endif; ?>
</div>
