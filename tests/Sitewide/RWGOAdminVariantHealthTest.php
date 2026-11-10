<?php
/**
 * Admin health for site-wide experiments.
 *
 * Delivery still reads page_map and templates. These checks only describe
 * the experiments list, Edit Test, and Reports.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-experiment-service.php';

/**
 * @covers RWGO_Experiment_Service::admin_variant_health
 */
class RWGOAdminVariantHealthTest extends PHPUnit\Framework\TestCase {

	/**
	 * @param array<int, array<string, int>> $pairs Source => variant.
	 * @return array<int, array<string, int>>
	 */
	private function page_map( array $pairs ) {
		$rows = array();
		foreach ( $pairs as $source => $variant ) {
			$rows[] = array(
				'source_page_id'  => (int) $source,
				'variant_page_id' => (int) $variant,
			);
		}
		return $rows;
	}

	/**
	 * Stored shape from a site-wide save: variant page IDs stay 0.
	 *
	 * @param array<string, mixed> $extra Extra fields.
	 * @return array<string, mixed>
	 */
	private function sitewide( array $extra = array() ) {
		return array_merge(
			array(
				'experiment_key' => 'rev3-sitewide',
				'revision'       => 4,
				'test_type'      => 'sitewide',
				'scope'          => 'site',
				'status'         => 'active',
				'source_page_id' => 0,
				'page_map'       => array(),
				'template_map'   => array(),
				'theme_context'  => array(
					'control' => array( 'header' => 0, 'footer' => 0 ),
					'variant' => array( 'header' => 0, 'footer' => 0 ),
				),
				'variants'       => array(
					array( 'variant_id' => 'control', 'page_id' => 0 ),
					array( 'variant_id' => 'var_b', 'page_id' => 0 ),
				),
			),
			$extra
		);
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return array<string, mixed>
	 */
	private function health( array $cfg, array $posts ) {
		$before = $cfg;
		$health = RWGO_Experiment_Service::admin_variant_health( $cfg, $posts );
		$this->assertSame( $before, $cfg );
		return $health;
	}

	public function test_sitewide_with_page_map_and_published_chrome_is_complete(): void {
		$posts = array(
			4040 => array( 'status' => 'publish', 'title' => 'Variant header' ),
			4041 => array( 'status' => 'publish', 'title' => 'Variant footer' ),
		);
		$map = array();
		for ( $i = 1; $i <= 9; $i++ ) {
			$posts[ 1000 + $i ] = array( 'status' => 'publish', 'title' => 'Page ' . $i );
			$posts[ 2000 + $i ] = array( 'status' => 'publish', 'title' => 'Variant ' . $i );
			$map[]              = array(
				'source_page_id'  => 1000 + $i,
				'variant_page_id' => 2000 + $i,
			);
		}
		$cfg    = $this->sitewide(
			array(
				'source_page_id' => 1001,
				'page_map'       => $map,
				'theme_context'  => array(
					'control' => array( 'header' => 10, 'footer' => 11 ),
					'variant' => array( 'header' => 4040, 'footer' => 4041 ),
				),
			)
		);
		$health = $this->health( $cfg, $posts );

		$this->assertTrue( $health['sitewide'] );
		$this->assertTrue( $health['complete'] );
		$this->assertFalse( $health['missing_variant'] );
		$this->assertSame( array(), $health['warnings'] );
		$this->assertSame( 9, $health['page_pairs'] );
		$this->assertSame( 11, $health['slots'] );
		$this->assertStringContainsString( '9 page pairs', $health['summary'] );
		$this->assertFalse( RWGO_Experiment_Service::variant_b_is_routable( $cfg, 0 ) );
	}

	public function test_sitewide_header_or_product_template_alone_is_complete(): void {
		$header = $this->health(
			$this->sitewide(
				array(
					'theme_context' => array(
						'variant' => array( 'header' => 4040, 'footer' => 0 ),
					),
				)
			),
			array(
				4040 => array( 'status' => 'publish', 'title' => 'Variant header' ),
			)
		);
		$this->assertTrue( $header['complete'] );
		$this->assertFalse( $header['missing_variant'] );
		$this->assertSame( array(), $header['warnings'] );

		$product = $this->health(
			$this->sitewide(
				array(
					'template_map' => array(
						array(
							'location'            => 'single',
							'source_template_id'  => 50,
							'variant_template_id' => 51,
						),
					),
				)
			),
			array(
				51 => array( 'status' => 'publish', 'title' => 'Product body B' ),
			)
		);
		$this->assertTrue( $product['complete'] );
		$this->assertFalse( $product['missing_variant'] );
		$this->assertSame( 1, $product['product_pairs'] );
		$this->assertSame( array(), $product['warnings'] );
	}

	public function test_sitewide_draft_variant_page_warns_by_name_and_stays_complete(): void {
		$cfg    = $this->sitewide(
			array(
				'page_map'      => $this->page_map(
					array(
						10 => 11,
						20 => 21,
					)
				),
				'theme_context' => array(
					'variant' => array( 'header' => 4040, 'footer' => 4041 ),
				),
			)
		);
		$health = $this->health(
			$cfg,
			array(
				11   => array( 'status' => 'publish', 'title' => 'Home B' ),
				21   => array( 'status' => 'draft', 'title' => 'Pricing B' ),
				4040 => array( 'status' => 'publish', 'title' => 'Variant header' ),
				4041 => array( 'status' => 'publish', 'title' => 'Variant footer' ),
			)
		);

		$this->assertTrue( $health['complete'] );
		$this->assertFalse( $health['missing_variant'] );
		$this->assertCount( 1, $health['warnings'] );
		$this->assertSame( 'unpublished', $health['warnings'][0]['code'] );
		$this->assertSame( 'page', $health['warnings'][0]['kind'] );
		$this->assertSame( 21, $health['warnings'][0]['post_id'] );
		$this->assertSame( 'Pricing B', $health['warnings'][0]['label'] );
		$this->assertStringContainsString( 'Pricing B', $health['warnings'][0]['message'] );
		$this->assertStringNotContainsString( 'Home B', $health['warnings'][0]['message'] );
	}

	public function test_sitewide_missing_template_is_named(): void {
		$health = $this->health(
			$this->sitewide(
				array(
					'page_map'      => $this->page_map( array( 10 => 11 ) ),
					'theme_context' => array(
						'variant' => array( 'header' => 4040, 'footer' => 0 ),
					),
					'template_map'  => array(
						array(
							'location'            => 'single',
							'source_template_id'  => 50,
							'variant_template_id' => 88,
						),
					),
				)
			),
			array(
				11 => array( 'status' => 'publish', 'title' => 'Home B' ),
			)
		);

		$this->assertTrue( $health['complete'] );
		$this->assertFalse( $health['missing_variant'] );
		$messages = array_column( $health['warnings'], 'message' );
		$this->assertCount( 2, $messages );
		$this->assertStringContainsString( '4040', implode( ' ', $messages ) );
		$this->assertStringContainsString( '88', implode( ' ', $messages ) );
	}

	public function test_sitewide_with_nothing_mapped_is_incomplete(): void {
		$health = $this->health( $this->sitewide(), array() );

		$this->assertTrue( $health['sitewide'] );
		$this->assertFalse( $health['complete'] );
		$this->assertTrue( $health['missing_variant'] );
		$this->assertSame( array(), $health['warnings'] );
		$this->assertSame( 0, $health['slots'] );
	}

	public function test_single_page_health_is_unchanged(): void {
		$published = array(
			'test_type' => 'page_ab',
			'variants'  => array(
				array( 'variant_id' => 'control', 'page_id' => 10 ),
				array( 'variant_id' => 'var_b', 'page_id' => 11 ),
			),
		);
		$ready = $this->health(
			$published,
			array(
				11 => array( 'status' => 'publish', 'title' => 'Variant B' ),
			)
		);
		$this->assertFalse( $ready['sitewide'] );
		$this->assertTrue( $ready['complete'] );
		$this->assertFalse( $ready['missing_variant'] );
		$this->assertSame( array(), $ready['warnings'] );

		$draft = $this->health(
			$published,
			array(
				11 => array( 'status' => 'draft', 'title' => 'Variant B' ),
			)
		);
		$this->assertFalse( $draft['complete'] );
		$this->assertTrue( $draft['missing_variant'] );
		$this->assertSame( array(), $draft['warnings'] );

		$missing = $this->health(
			array(
				'test_type' => 'page_ab',
				'variants'  => array(
					array( 'variant_id' => 'control', 'page_id' => 10 ),
					array( 'variant_id' => 'var_b', 'page_id' => 0 ),
				),
			),
			array()
		);
		$this->assertTrue( $missing['missing_variant'] );
		$this->assertSame( array(), $missing['warnings'] );
	}
}
