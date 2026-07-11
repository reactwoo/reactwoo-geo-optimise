<?php
/**
 * Blueprint → Elementor tree construction tests.
 *
 * @package ReactWooGeoOptimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-element-key.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/builders/class-rwga-widget-blueprint.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/builders/class-rwga-section-blueprint.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/builders/class-rwga-page-blueprint.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/builders/elementor/class-rwga-elementor-blueprint-builder.php';

/**
 * @covers RWGA_Elementor_Blueprint_Builder
 */
class RWGAElementorBlueprintBuilderTest extends PHPUnit\Framework\TestCase {

	public function test_v3_lead_gen_stamps_cta_keys() {
		$bp    = RWGA_Page_Blueprint::lead_generation_landing();
		$built = RWGA_Elementor_Blueprint_Builder::build( $bp, array( 'mode' => 'v3' ) );

		$this->assertSame( 'v3', $built['mode'] );
		$this->assertNotEmpty( $built['tree'] );
		$this->assertSame( 'section', $built['tree'][0]['elType'] );

		$keys = array_column( $built['tracked_elements'], 'semantic_key' );
		$this->assertContains( 'hero.primary_cta', $keys );
		$this->assertContains( 'final_cta.button', $keys );

		$hero_cta = null;
		foreach ( $built['tracked_elements'] as $row ) {
			if ( 'hero.primary_cta' === $row['semantic_key'] ) {
				$hero_cta = $row;
				break;
			}
		}
		$this->assertNotNull( $hero_cta );
		$this->assertNotEmpty( $hero_cta['elementor_id'] );
	}

	public function test_atomic_lead_gen_uses_flexbox_and_typed_content() {
		$bp    = RWGA_Page_Blueprint::lead_generation_landing();
		$built = RWGA_Elementor_Blueprint_Builder::build( $bp, array( 'mode' => 'atomic' ) );

		$this->assertSame( 'atomic', $built['mode'] );
		$this->assertSame( 'e-flexbox', $built['tree'][0]['widgetType'] );

		$hero_button = null;
		$walk        = function ( $nodes ) use ( &$walk, &$hero_button ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				if (
					isset( $n['widgetType'], $n['settings']['rwgo_element_key'] )
					&& 'e-button' === $n['widgetType']
					&& 'hero.primary_cta' === $n['settings']['rwgo_element_key']
				) {
					$hero_button = $n;
				}
				if ( ! empty( $n['elements'] ) ) {
					$walk( $n['elements'] );
				}
			}
		};
		$walk( $built['tree'] );
		$this->assertNotNull( $hero_button );
		$this->assertSame( 'yes', $hero_button['settings']['rwgo_goal_enabled'] );
		$this->assertIsArray( $hero_button['settings']['text'] );
		$this->assertSame( 'string', $hero_button['settings']['text']['$$type'] );
	}

	public function test_content_override() {
		$bp    = RWGA_Page_Blueprint::lead_generation_landing();
		$built = RWGA_Elementor_Blueprint_Builder::build(
			$bp,
			array(
				'mode'    => 'v3',
				'content' => array(
					'hero.primary_cta' => 'Buy now',
				),
			)
		);
		$label = '';
		foreach ( $built['tracked_elements'] as $row ) {
			if ( 'hero.primary_cta' === $row['semantic_key'] ) {
				$label = $row['goal_label'];
				break;
			}
		}
		$this->assertSame( 'Buy now', $label );
	}
}
