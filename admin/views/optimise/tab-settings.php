<?php
/**
 * Settings tab — licence + site defaults (embedded forms).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::get_view_data() : array();
foreach ( $data as $k => $v ) {
	${$k} = $v;
}
$rwgc_nav_current        = 'rwgo-optimise';
$rwgo_optimise_hub_embed = true;
?>
<div class="rwgo-optimise-hub__settings-stack">
	<?php include RWGO_PATH . 'admin/views/settings-optimisation.php'; ?>
	<?php include RWGO_PATH . 'admin/views/license-settings.php'; ?>

	<div class="rwgo-panel">
		<h2 class="rwgo-section__title"><?php esc_html_e( 'More options', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="rwgo-section__lead"><?php esc_html_e( 'Tracking snippets, developer hooks, and product help remain on dedicated screens during the merge.', 'reactwoo-geo-optimise' ); ?></p>
		<p class="rwgo-cta-row">
			<?php if ( class_exists( 'RWGO_Admin', false ) ) : ?>
				<a class="button button-secondary" href="<?php echo esc_url( RWGO_Admin::tracking_tools_url() ); ?>"><?php esc_html_e( 'Tracking tools', 'reactwoo-geo-optimise' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( RWGO_Admin::developer_url() ); ?>"><?php esc_html_e( 'Developer', 'reactwoo-geo-optimise' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( RWGO_Admin::help_url() ); ?>"><?php esc_html_e( 'Help', 'reactwoo-geo-optimise' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</div>
