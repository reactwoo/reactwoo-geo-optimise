<?php
/**
 * Cache vary and purge planning. No cache plugin is loaded.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-assignment.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-cache-compat.php';

/**
 * @covers RWGO_Cache_Compat
 */
class RWGOCacheCompatTest extends PHPUnit\Framework\TestCase {

	public function test_assignment_cookies_are_added_to_litespeed_vary(): void {
		$this->assertSame( RWGO_Assignment::COOKIE, 'rwgo_ab' );
		$this->assertSame( RWGO_Sitewide::COOKIE, 'rwgo_site' );
		$this->assertSame(
			array( 'rwgo_ab', 'rwgo_site' ),
			RWGO_Cache_Compat::assignment_cookie_names()
		);

		$merged = RWGO_Cache_Compat::filter_litespeed_vary_cookies( array( 'woocommerce_cart_hash' ) );
		$this->assertSame(
			array( 'woocommerce_cart_hash', 'rwgo_ab', 'rwgo_site' ),
			$merged
		);
		$again = RWGO_Cache_Compat::filter_litespeed_vary_cookies( $merged );
		$this->assertSame( $merged, $again );

		$this->assertSame(
			'cookie=rwgo_ab,cookie=rwgo_site',
			RWGO_Cache_Compat::litespeed_vary_header_value()
		);
		$this->assertSame(
			'https,cookie=rwgo_ab,cookie=rwgo_site',
			RWGO_Cache_Compat::merge_litespeed_vary( 'https,cookie=rwgo_ab', 'cookie=rwgo_ab,cookie=rwgo_site' )
		);
	}

	public function test_vary_header_is_not_sent_without_litespeed(): void {
		$this->assertFalse( RWGO_Cache_Compat::litespeed_active() );
		$this->assertFalse( RWGO_Cache_Compat::should_send_litespeed_vary_header( false, false, false ) );
		$this->assertFalse( RWGO_Cache_Compat::should_send_litespeed_vary_header( true, true, false ) );
		$this->assertFalse( RWGO_Cache_Compat::should_send_litespeed_vary_header( true, false, true ) );
		$this->assertTrue( RWGO_Cache_Compat::should_send_litespeed_vary_header( true, false, false ) );
	}

	public function test_purge_plan_is_empty_when_no_cache_plugin_is_active(): void {
		$this->assertFalse( RWGO_Cache_Compat::any_page_cache_active() );
		$this->assertSame( array(), RWGO_Cache_Compat::active_adapter_labels() );
		$available = RWGO_Cache_Compat::adapter_availability();
		foreach ( $available as $on ) {
			$this->assertFalse( $on );
		}
		$plan = RWGO_Cache_Compat::purge_plan( array( 10, 20 ), array( 'https://example.com/a/' ), $available );
		$this->assertSame( array(), $plan );
		$this->assertSame( array(), RWGO_Cache_Compat::execute_plan( $plan ) );
		$this->assertSame(
			__( 'No page-cache plugin is active. Public caching was left unchanged.', 'reactwoo-geo-optimise' ),
			RWGO_Cache_Compat::clear_notice_message( array( 'available' => $available, 'ran' => array() ) )
		);
	}

	public function test_purge_plan_lists_guarded_adapters_for_original_and_variant(): void {
		$available = array(
			'litespeed'  => true,
			'rocket'     => true,
			'w3tc'       => true,
			'supercache' => true,
			'cloudflare' => true,
			'elementor'  => true,
		);
		$plan = RWGO_Cache_Compat::purge_plan(
			array( 10, 20, 10 ),
			array( 'https://example.com/control/', 'https://example.com/variant-b/' ),
			$available
		);
		$names = array();
		foreach ( $plan as $step ) {
			$names[] = $step[0] . ':' . $step[1];
		}
		$this->assertContains( 'litespeed_purge_url:https://example.com/control/', $names );
		$this->assertContains( 'litespeed_purge_url:https://example.com/variant-b/', $names );
		$this->assertContains( 'rocket_clean_files:https://example.com/control/', $names );
		$this->assertContains( 'w3tc_flush_url:https://example.com/variant-b/', $names );
		$this->assertContains( 'wpsc_delete_url_cache:https://example.com/control/', $names );
		$this->assertContains( 'cloudflare_purge_url:https://example.com/variant-b/', $names );
		$this->assertContains( 'litespeed_purge_post:10', $names );
		$this->assertContains( 'litespeed_purge_post:20', $names );
		$this->assertContains( 'rocket_clean_post:10', $names );
		$this->assertContains( 'w3tc_flush_post:20', $names );
		$this->assertContains( 'wp_cache_post_change:10', $names );
		$this->assertContains( 'cloudflare_purge_post:20', $names );
		$this->assertContains( 'elementor_css:10', $names );
		$this->assertContains( 'elementor_css:20', $names );
		$this->assertNotContains( 'litespeed_purge_post:10', array_slice( $names, 0, 1 ) );
		$post_steps = array_values(
			array_filter(
				$names,
				static function ( $name ) {
					return 'litespeed_purge_post:10' === $name;
				}
			)
		);
		$this->assertCount( 1, $post_steps );

		// Plugins are not loaded in this process, so the plan must not call them.
		$this->assertSame( array(), RWGO_Cache_Compat::execute_plan( $plan ) );
	}

	public function test_save_start_stop_promote_and_page_map_select_both_urls(): void {
		$draft = array(
			'status'         => 'draft',
			'source_page_id' => 10,
			'variants'       => array(
				array( 'variant_id' => 'control', 'page_id' => 10 ),
				array( 'variant_id' => 'var_b', 'page_id' => 20 ),
			),
		);
		$active = $draft;
		$active['status'] = 'active';
		$this->assertSame( 'started', RWGO_Cache_Compat::change_reason( $draft, $active ) );

		$paused = $active;
		$paused['status'] = 'paused';
		$this->assertSame( 'stopped', RWGO_Cache_Compat::change_reason( $active, $paused ) );

		$promoted = $active;
		$promoted['status'] = 'completed';
		$promoted['promoted_at'] = '2026-10-29T12:00:00+00:00';
		$promoted['promoted_from_id'] = 20;
		$this->assertSame( 'promoted', RWGO_Cache_Compat::change_reason( $active, $promoted ) );

		$before = array(
			'status'   => 'active',
			'page_map' => array(
				array(
					'source_page_id'  => 10,
					'variant_page_id' => 20,
					'source_path'     => '/original/',
					'variant_path'    => '/variant-b/',
				),
			),
		);
		$after = array(
			'status'   => 'active',
			'page_map' => array(
				array(
					'source_page_id'  => 10,
					'variant_page_id' => 21,
					'source_path'     => '/original/',
					'variant_path'    => '/variant-b-2/',
				),
				array(
					'source_page_id'  => 12,
					'variant_page_id' => 22,
					'source_path'     => '/about/',
					'variant_path'    => '/about-b/',
				),
			),
		);
		$targets = RWGO_Cache_Compat::targets_for_change( $before, $after );
		$this->assertSame( 'page_map', $targets['reason'] );
		$this->assertSame( array( 10, 20, 21, 12, 22 ), $targets['post_ids'] );
		$this->assertContains( '/original/', $targets['paths'] );
		$this->assertContains( '/variant-b/', $targets['paths'] );
		$this->assertContains( '/variant-b-2/', $targets['paths'] );
		$this->assertContains( '/about-b/', $targets['paths'] );

		$site = $after;
		$site['status'] = 'paused';
		$this->assertSame( 'stopped', RWGO_Cache_Compat::change_reason( $after, $site ) );
		$this->assertSame( 'rwgo_sitewide_experiment', RWGO_Sitewide::OPTION );
	}

	public function test_tracking_config_does_not_embed_a_nonce(): void {
		$fields = RWGO_Cache_Compat::public_tracking_fields(
			'https://example.com/wp-json/rwgo/v1/goal',
			'https://example.com/wp-json/rwgo/v1/tracking-nonce'
		);
		$this->assertArrayNotHasKey( 'nonce', $fields );
		$this->assertSame( 'https://example.com/wp-json/rwgo/v1/goal', $fields['restUrl'] );
		$this->assertSame( 'https://example.com/wp-json/rwgo/v1/tracking-nonce', $fields['restNonceUrl'] );

		$headers = RWGO_Cache_Compat::nonce_response_headers( false );
		$this->assertStringContainsString( 'no-store', $headers['Cache-Control'] );
		$this->assertSame( 'no-store', $headers['CDN-Cache-Control'] );
		$this->assertSame( 'no-store', $headers['Cloudflare-CDN-Cache-Control'] );
		$this->assertArrayNotHasKey( 'X-LiteSpeed-Cache-Control', $headers );
		$this->assertSame( 'no-cache', RWGO_Cache_Compat::nonce_response_headers( true )['X-LiteSpeed-Cache-Control'] );
		$this->assertArrayNotHasKey( 'nonce', $headers );
	}

	public function test_promoted_and_page_map_purges_run_once_and_sitewide_save_purges_the_map(): void {
		RWGO_Cache_Compat::reset_purge_state();
		$before = array(
			'status'   => 'active',
			'page_map' => array(
				array(
					'source_page_id'  => 10,
					'variant_page_id' => 20,
					'source_path'     => '/a/',
					'variant_path'    => '/b/',
				),
			),
		);
		$after = $before;
		$after['page_map'][0]['variant_page_id'] = 21;
		$after['page_map'][0]['variant_path']    = '/c/';
		RWGO_Cache_Compat::purge_experiment_change( $before, $after, 'page_map' );
		RWGO_Cache_Compat::purge_experiment_change( $before, $after, 'page_map' );
		$this->assertCount( 1, RWGO_Cache_Compat::purge_log() );

		$promoted = array(
			'status'           => 'completed',
			'promoted_at'      => '2026-10-29T00:00:00+00:00',
			'source_page_id'   => 10,
			'promoted_from_id' => 21,
		);
		RWGO_Cache_Compat::purge_experiment_change( array(), $promoted, 'promoted' );
		$this->assertCount( 1, RWGO_Cache_Compat::purge_log() );

		$site = array(
			'status'   => 'active',
			'page_map' => array(
				array(
					'source_page_id'  => 30,
					'variant_page_id' => 31,
					'source_path'     => '/original/',
					'variant_path'    => '/original-b/',
				),
			),
		);
		RWGO_Cache_Compat::purge_experiment_change( array(), $site, 'saved' );
		$log = RWGO_Cache_Compat::purge_log();
		$this->assertCount( 2, $log );
		$this->assertSame( 'saved', $log[1]['reason'] );
		$this->assertContains( 30, $log[1]['post_ids'] );
		$this->assertContains( 31, $log[1]['post_ids'] );
		$this->assertContains( '/original/', $log[1]['paths'] );
		$this->assertContains( '/original-b/', $log[1]['paths'] );
	}

	public function test_clear_notice_names_only_adapters_that_ran(): void {
		$this->assertSame(
			__( 'No original or variant URLs were found to clear. Public caching was left unchanged.', 'reactwoo-geo-optimise' ),
			RWGO_Cache_Compat::clear_notice_message(
				array(
					'available' => array( 'litespeed' => true ),
					'ran'       => array(),
				)
			)
		);
		$this->assertSame(
			__( 'Cleared the original and variant URLs in LiteSpeed Cache, WP Rocket.', 'reactwoo-geo-optimise' ),
			RWGO_Cache_Compat::clear_notice_message(
				array(
					'ran' => array( 'litespeed_purge_url', 'litespeed_purge_post', 'rocket_clean_files' ),
				)
			)
		);
	}
}
