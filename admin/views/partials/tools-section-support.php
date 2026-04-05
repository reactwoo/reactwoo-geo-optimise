<?php
/**
 * Tools tab: Support & troubleshooting.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="rwgc-card rwgc-card--highlight">
	<h2><?php esc_html_e( 'Why numbers may look empty', 'reactwoo-geo-optimise' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'New tests need real traffic to the control URL before assignments appear in Reports.', 'reactwoo-geo-optimise' ); ?></li>
		<li><?php esc_html_e( 'Country targeting excludes visitors whose country does not match — they will not be counted in that test’s assignments.', 'reactwoo-geo-optimise' ); ?></li>
		<li><?php esc_html_e( 'Paused or ended tests stop routing; historical assignment counts may still exist.', 'reactwoo-geo-optimise' ); ?></li>
		<li><?php esc_html_e( '“Goals recorded” in analytics requires you to fire events (e.g. via GTM) using the Measurement & tracking snippets — Geo Optimise does not replace your analytics property.', 'reactwoo-geo-optimise' ); ?></li>
	</ul>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'Duplicate or missing events', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'If the same button click is wired twice (theme + GTM), you can see duplicate goal signals. Use one binding per measurement point, or configure dedupe in your tag setup.', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'Caching and optimisation plugins', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'Full-page caches may cache the control page HTML for anonymous visitors. Exclude the control URL from cache or use your cache plugin’s cookie-based vary-by rules if assignments look wrong.', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'Elementor, Gutenberg, and defined goals', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'Duplicated pages should carry builder data. If a variant looks blank, open it in the same editor as the source and confirm the duplicate completed successfully.', 'reactwoo-geo-optimise' ); ?></p>
	<p><?php esc_html_e( 'Defined goals must be enabled in the builder: Gutenberg blocks expose a Geo Optimise panel in the inspector for supported blocks; the document sidebar has Geo Optimise — destination goal for full-page conversions. Elementor uses Advanced → Geo Optimise — goal on widgets and Settings → destination goal on the document.', 'reactwoo-geo-optimise' ); ?></p>
	<p><?php esc_html_e( 'If a test says the defined goal is missing, edit Control or Variant B in the builder and turn on a goal, then pick it again under Edit Test.', 'reactwoo-geo-optimise' ); ?></p>
	<p>
		<a class="button" href="<?php echo esc_url( RWGO_Admin::help_url( 'rwgo-help-builder-goals' ) ); ?>"><?php esc_html_e( 'Help: builder goals', 'reactwoo-geo-optimise' ); ?></a>
	</p>
</div>

<div class="rwgc-card">
	<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-help' ) ); ?>"><?php esc_html_e( 'Product help (non-technical)', 'reactwoo-geo-optimise' ); ?></a></p>
</div>
