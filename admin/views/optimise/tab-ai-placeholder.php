<?php
/**
 * AI tab placeholder (Phase 2 — module import in Phase 3).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tab = isset( $rwgo_optimise_placeholder_tab ) ? sanitize_key( (string) $rwgo_optimise_placeholder_tab ) : 'ai-review';
?>
<div class="rwgo-panel rwgo-panel--hero rwgo-optimise-hub__placeholder rwgo-optimise-hub__placeholder--<?php echo esc_attr( $tab ); ?>">
	<h2 class="rwgo-section__title">
		<?php
		echo esc_html(
			isset( $rwgo_optimise_placeholder_title )
				? (string) $rwgo_optimise_placeholder_title
				: __( 'Coming soon', 'reactwoo-geo-optimise' )
		);
		?>
	</h2>
	<p class="rwgo-section__lead">
		<?php
		echo esc_html(
			isset( $rwgo_optimise_placeholder_body )
				? (string) $rwgo_optimise_placeholder_body
				: __( 'This area will be available after the Geo AI merge.', 'reactwoo-geo-optimise' )
		);
		?>
	</p>

	<?php if ( 'ai-review' === $tab ) : ?>
		<div class="rwgo-optimise-hub__chat-preview" aria-hidden="true">
			<p class="rwgo-optimise-hub__chat-prompt"><?php esc_html_e( 'What would you like to optimise?', 'reactwoo-geo-optimise' ); ?></p>
			<p class="description"><?php esc_html_e( 'Example: Review the homepage CTA for mobile visitors in Ireland.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
	<?php endif; ?>

	<p class="rwgo-optimise-hub__placeholder-meta description">
		<?php
		esc_html_e( 'Geo AI functionality is being consolidated into this plugin. Standalone Geo AI will be deprecated before public launch.', 'reactwoo-geo-optimise' );
		?>
	</p>
	<p class="rwgo-cta-row">
		<a class="button button-primary" href="<?php echo esc_url( RWGO_Optimise_Hub::tab_url( 'experiments' ) ); ?>">
			<?php esc_html_e( 'Open experiments', 'reactwoo-geo-optimise' ); ?>
		</a>
		<?php if ( defined( 'RWGO_PATH' ) && file_exists( RWGO_PATH . 'docs/MERGE-GEO-AI-INTO-OPTIMISE.md' ) ) : ?>
			<span class="description" style="margin-left:8px;">
				<?php esc_html_e( 'See docs/MERGE-GEO-AI-INTO-OPTIMISE.md in this plugin for the merge plan.', 'reactwoo-geo-optimise' ); ?>
			</span>
		<?php endif; ?>
	</p>
</div>
