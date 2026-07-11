<?php
/**
 * Loads builder-aware architecture classes.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual require chain for builder subsystem.
 */
class RWGA_Builder_Loader {

	/**
	 * @return void
	 */
	public static function load() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$base = RWGA_PATH . 'includes/builders/';
		require_once $base . 'interface-rwga-builder-adapter.php';
		require_once $base . 'class-rwga-builder-normalize.php';
		require_once $base . 'elementor/class-rwga-elementor-node-version.php';
		require_once $base . 'elementor/class-rwga-elementor-atomic-prop-resolver.php';
		require_once $base . 'elementor/class-rwga-elementor-style-summary.php';
		require_once $base . 'elementor/class-rwga-elementor-v3-node-reader.php';
		require_once $base . 'elementor/class-rwga-elementor-v4-node-reader.php';
		require_once $base . 'class-rwga-elementor-adapter.php';
		require_once $base . 'class-rwga-gutenberg-adapter.php';
		require_once $base . 'class-rwga-default-post-content-adapter.php';
		require_once $base . 'class-rwga-builder-registry.php';
		require_once $base . 'class-rwga-section-classifier.php';
		require_once $base . 'class-rwga-ux-structure-scorer.php';
		require_once $base . 'class-rwga-builder-recommendations.php';
		require_once $base . 'class-rwga-elementor-action-planner.php';
		require_once $base . 'class-rwga-section-blueprint.php';
		require_once $base . 'class-rwga-widget-blueprint.php';
		require_once $base . 'class-rwga-page-blueprint.php';
		require_once $base . 'extractors/interface-rwga-context-extractor.php';
		require_once $base . 'extractors/class-rwga-context-extractor-base.php';
		require_once $base . 'extractors/class-rwga-elementor-context-extractor.php';
		require_once $base . 'extractors/class-rwga-gutenberg-context-extractor.php';
		require_once $base . 'extractors/class-rwga-classic-context-extractor.php';
		require_once $base . 'extractors/class-rwga-context-extractor-registry.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-page-context-builder.php';

		RWGA_Builder_Registry::boot();
		RWGA_Context_Extractor_Registry::boot();
	}
}
