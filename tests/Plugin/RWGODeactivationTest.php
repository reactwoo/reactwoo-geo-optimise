<?php
/**
 * Deactivation clears each cron this plugin schedules.
 *
 * @package ReactWoo_Geo_Optimise
 */

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return void
	 */
	function wp_clear_scheduled_hook( $hook ) {
		if ( ! isset( $GLOBALS['rwgo_cleared_hooks'] ) || ! is_array( $GLOBALS['rwgo_cleared_hooks'] ) ) {
			$GLOBALS['rwgo_cleared_hooks'] = array();
		}
		$GLOBALS['rwgo_cleared_hooks'][] = (string) $hook;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-deactivation.php';

/**
 * @covers RWGO_Deactivation
 */
class RWGODeactivationTest extends PHPUnit\Framework\TestCase {

	public function test_deactivation_clears_each_scheduled_hook(): void {
		$hooks = RWGO_Deactivation::cron_hooks();
		$this->assertContains( 'rwga_automation_cron_tick', $hooks );
		$this->assertContains( 'rwga_weather_catalog_audit_tick', $hooks );
		$this->assertCount( 2, $hooks );

		$GLOBALS['rwgo_cleared_hooks'] = array();
		RWGO_Deactivation::clear_scheduled_crons();
		$this->assertSame( $hooks, $GLOBALS['rwgo_cleared_hooks'] );

		$main = file_get_contents( dirname( __DIR__, 2 ) . '/reactwoo-geo-optimise.php' );
		$this->assertIsString( $main );
		$this->assertStringContainsString( 'register_deactivation_hook', $main );
		$this->assertStringContainsString( 'function rwgo_deactivate()', $main );
		$this->assertStringContainsString( 'RWGO_Deactivation::clear_scheduled_crons()', $main );
	}

	public function test_commerce_weather_script_matches_the_enqueue(): void {
		$root   = dirname( __DIR__, 2 );
		$php    = file_get_contents( $root . '/merged-geo-ai/includes/integrations/class-rwga-commerce-weather.php' );
		$script = $root . '/merged-geo-ai/assets/js/commerce-weather-suggest.js';
		$this->assertIsString( $php );
		$this->assertStringContainsString( 'assets/js/commerce-weather-suggest.js', $php );
		$this->assertStringContainsString( 'is_readable', $php );
		$this->assertFileExists( $script );
		$js = file_get_contents( $script );
		$this->assertIsString( $js );
		$this->assertStringContainsString( 'suggest-weather-facets', $js );
		$this->assertStringContainsString( 'rwga-suggest-weather-facets', $js );
		$this->assertStringContainsString( 'rwgcm_weather_facet_', $js );
		$this->assertStringContainsString( 'rwgcm_weather_facets[]', $js );
	}
}
