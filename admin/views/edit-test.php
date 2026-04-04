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
$rwgo_cfg      = ( $rwgo_exp_post instanceof \WP_Post && RWGO_Experiment_CPT::POST_TYPE === $rwgo_exp_post->post_type )
	? RWGO_Experiment_Repository::get_config( $rwgo_exp_id )
	: array();

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

$rwgo_prefill = array(
	'experiment_id'   => $rwgo_exp_id,
	'name'            => $rwgo_exp_post instanceof \WP_Post ? get_the_title( $rwgo_exp_post ) : '',
	'test_type'       => isset( $rwgo_cfg['test_type'] ) ? (string) $rwgo_cfg['test_type'] : 'page_ab',
	'source_id'       => $src_id,
	'variant_b_id'    => $var_b_id,
	'targeting_mode'  => $tgt_mode,
	'countries_csv'   => $countries,
	'winner_mode'       => $wm,
	'goal_type'         => $goal_type,
	'status'            => isset( $rwgo_cfg['status'] ) ? (string) $rwgo_cfg['status'] : '',
	'experiment_key'    => isset( $rwgo_cfg['experiment_key'] ) ? (string) $rwgo_cfg['experiment_key'] : '',
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
	: array( 'assignment_only' => true, 'leading_variant' => null );
$assign_only = ! empty( $analysis['assignment_only'] );
$lead_slug   = isset( $analysis['leading_variant'] ) ? $analysis['leading_variant'] : null;
$lead_label  = '';
if ( $lead_slug && ! $assign_only ) {
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
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-tests' ) ); ?>"><?php esc_html_e( 'Back to Tests', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	<?php else : ?>

		<?php if ( ! empty( $_GET['rwgo_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible rwgo-notice"><p><?php esc_html_e( 'Test settings saved.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['rwgo_created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible rwgo-notice"><p><?php esc_html_e( 'Test created. Review settings below, then edit Control or Variant B as needed.', 'reactwoo-geo-optimise' ); ?></p></div>
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
				} elseif ( 'missing' === $err ) {
					esc_html_e( 'Please enter a test name.', 'reactwoo-geo-optimise' );
				} else {
					esc_html_e( 'Could not save changes. Try again.', 'reactwoo-geo-optimise' );
				}
				?>
			</p></div>
		<?php endif; ?>

		<div class="rwgo-card rwgo-edit-summary">
			<h2 class="rwgo-section__title"><?php esc_html_e( 'Test summary', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-hint"><?php esc_html_e( 'Internal experiment key (for developers & integrations):', 'reactwoo-geo-optimise' ); ?> <code><?php echo esc_html( $key_for_stats ); ?></code></p>
			<ul class="rwgo-checklist">
				<li><?php esc_html_e( 'Status:', 'reactwoo-geo-optimise' ); ?> <strong><?php echo esc_html( $rwgo_prefill['status'] ); ?></strong></li>
				<li><?php esc_html_e( 'Primary goal:', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( $assign_only ? __( 'Traffic split only', 'reactwoo-geo-optimise' ) : $primary_goal_lab ); ?></li>
				<li><?php esc_html_e( 'Visitors assigned (total):', 'reactwoo-geo-optimise' ); ?> <?php echo esc_html( (string) $vsum ); ?></li>
				<li><?php esc_html_e( 'Leading variant:', 'reactwoo-geo-optimise' ); ?> <?php echo $assign_only ? esc_html__( '— (traffic split only)', 'reactwoo-geo-optimise' ) : ( $lead_label ? esc_html( $lead_label ) : esc_html__( '—', 'reactwoo-geo-optimise' ) ); ?></li>
			</ul>
			<?php if ( $src_id > 0 && get_post( $src_id ) && $var_b_id > 0 && get_post( $var_b_id ) ) : ?>
				<p class="rwgo-cta-row">
					<?php
					$ce = get_edit_post_link( $src_id );
					$ve = get_edit_post_link( $var_b_id );
					?>
					<?php if ( is_string( $ce ) && $ce ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $ce ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
					<?php if ( is_string( $ve ) && $ve ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $ve ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>

		<?php require RWGO_PATH . 'admin/views/partials/test-form.php'; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-duplicate-after-form">
			<input type="hidden" name="action" value="rwgo_duplicate_test" />
			<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) $rwgo_exp_id ); ?>" />
			<?php wp_nonce_field( 'rwgo_duplicate_test' ); ?>
			<p class="rwgo-hint"><?php esc_html_e( 'Need a different structure or source? Duplicate creates a new test draft.', 'reactwoo-geo-optimise' ); ?></p>
			<button type="submit" class="button"><?php esc_html_e( 'Duplicate Test', 'reactwoo-geo-optimise' ); ?></button>
		</form>
	<?php endif; ?>
</div>
