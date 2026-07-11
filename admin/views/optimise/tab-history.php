<?php
/**
 * History tab placeholder — merged AI + experiment timeline (Phase 3+).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reports_url = class_exists( 'RWGO_Optimise_Hub', false )
	? RWGO_Optimise_Hub::tab_url( 'reports' )
	: admin_url( 'admin.php?page=rwgo-reports' );
?>
<div class="rwgo-panel rwgo-optimise-hub__placeholder rwgo-optimise-hub__placeholder--history">
	<h2 class="rwgo-section__title"><?php esc_html_e( 'Optimisation history', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="rwgo-section__lead">
		<?php esc_html_e( 'A unified timeline of AI review runs, recommendations, experiment lifecycle events, and winner promotions will appear here after the Geo AI module merge.', 'reactwoo-geo-optimise' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Until then, use Reports for experiment outcomes and the standalone Geo AI plugin (if active) for review history.', 'reactwoo-geo-optimise' ); ?>
	</p>
	<p class="rwgo-cta-row">
		<a class="button button-primary" href="<?php echo esc_url( $reports_url ); ?>"><?php esc_html_e( 'Open reports', 'reactwoo-geo-optimise' ); ?></a>
	</p>
</div>
