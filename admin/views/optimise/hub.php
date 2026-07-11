<?php
/**
 * Optimise hub — tabbed shell (merged Geo AI + experiments).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_use_platform_shell = class_exists( 'RWGO_Admin', false ) && RWGO_Admin::uses_platform_shell();
$rwgo_current_tab        = class_exists( 'RWGO_Optimise_Hub', false ) ? RWGO_Optimise_Hub::current_tab() : 'ai-review';
$rwgo_tab_meta           = class_exists( 'RWGO_Optimise_Hub', false ) ? RWGO_Optimise_Hub::tab_definitions() : array();
$rwgo_tab_lead           = isset( $rwgo_tab_meta[ $rwgo_current_tab ]['description'] )
	? (string) $rwgo_tab_meta[ $rwgo_current_tab ]['description']
	: '';
?>
<div class="wrap rwgc-wrap rwgc-suite rwgo-wrap rwgo-wrap--optimise-hub">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			$rwgo_use_platform_shell
				? __( 'Optimise', 'reactwoo-geo-optimise' )
				: __( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			__( 'AI-assisted conversion optimisation for geo-targeted pages, products, variants, and campaigns.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-optimise' ); ?></h1>
		<p class="description"><?php esc_html_e( 'AI-assisted conversion optimisation for geo-targeted pages, products, variants, and campaigns.', 'reactwoo-geo-optimise' ); ?></p>
	<?php endif; ?>

	<?php if ( class_exists( 'RWGO_Optimise_Hub', false ) ) : ?>
		<?php RWGO_Optimise_Hub::render_tab_nav( $rwgo_current_tab ); ?>
	<?php endif; ?>

	<?php if ( '' !== $rwgo_tab_lead ) : ?>
		<p class="description rwgo-optimise-hub__tab-lead"><?php echo esc_html( $rwgo_tab_lead ); ?></p>
	<?php endif; ?>

	<div class="rwgo-stack rwgo-optimise-hub__content">
		<?php
		if ( class_exists( 'RWGO_Admin', false ) ) {
			RWGO_Admin::render_suite_handoff_panel();
		}
		if ( class_exists( 'RWGO_Optimise_Hub', false ) ) {
			RWGO_Optimise_Hub::render_tab_content( $rwgo_current_tab );
		}
		?>
	</div>
</div>
