<?php
/**
 * Hosted AI stays off unless a cached Decision Cloud token covers Geo Optimise.
 *
 * @package ReactWoo_Geo_Optimise
 */

$GLOBALS['rwgo_remote_calls'] = array();

if ( ! function_exists( 'wp_remote_request' ) ) {
	/**
	 * @param string               $url  URL.
	 * @param array<string, mixed> $args Args.
	 * @return \WP_Error
	 */
	function wp_remote_request( $url, $args = array() ) {
		$GLOBALS['rwgo_remote_calls'][] = array(
			'url'  => (string) $url,
			'args' => $args,
		);
		return new WP_Error( 'rwgo_test_http', 'mocked' );
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * @param string               $url  URL.
	 * @param array<string, mixed> $args Args.
	 * @return \WP_Error
	 */
	function wp_remote_post( $url, $args = array() ) {
		return wp_remote_request( $url, $args );
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-decision-cloud.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/class-rwga-platform-client.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/services/class-rwga-remote-client.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/services/class-rwga-ai-usage-guard.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/workflows/interface-rwga-workflow.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/workflows/class-rwga-workflow-base.php';
require_once dirname( __DIR__, 2 ) . '/merged-geo-ai/includes/workflows/class-rwga-workflow-intelligence.php';

/**
 * Records dispatch calls for router tests in this file.
 */
class RWGO_Recording_Transport implements RWGA_Generation_Transport {

	/** @var string */
	private $key;

	/** @var true|\WP_Error */
	private $availability;

	/** @var array<string, mixed> */
	private $result;

	/** @var int */
	public $dispatch_calls = 0;

	/**
	 * @param string               $key          Key.
	 * @param true|\WP_Error       $availability Availability.
	 * @param array<string, mixed> $result       Result.
	 */
	public function __construct( $key, $availability, array $result ) {
		$this->key          = (string) $key;
		$this->availability = $availability;
		$this->result       = $result;
	}

	/** @return string */
	public function get_key() {
		return $this->key;
	}

	/**
	 * @param string               $workflow_key Workflow.
	 * @param array<string, mixed> $request      Request.
	 * @return bool
	 */
	public function supports( $workflow_key, array $request ) {
		unset( $workflow_key, $request );
		return true;
	}

	/**
	 * @param string               $workflow_key Workflow.
	 * @param array<string, mixed> $request      Request.
	 * @return true|\WP_Error
	 */
	public function availability( $workflow_key, array $request ) {
		unset( $workflow_key, $request );
		return $this->availability;
	}

	/**
	 * @param string               $workflow_key Workflow.
	 * @param array<string, mixed> $request      Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function dispatch( $workflow_key, array $request ) {
		unset( $workflow_key, $request );
		++$this->dispatch_calls;
		return $this->result;
	}
}

/**
 * @covers RWGO_Decision_Cloud
 * @covers RWGA_Engine
 * @covers RWGA_Generation_Router
 * @covers RWGA_Managed_AI_Transport
 * @covers RWGA_AI_Usage_Guard
 * @covers RWGA_Workflow_Intelligence
 */
class RWGODecisionCloudHostedAITest extends PHPUnit\Framework\TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['rwgo_remote_calls']    = array();
		$GLOBALS['rwga_test_options']    = array();
		$GLOBALS['rwga_test_transients'] = array();
		$GLOBALS['rwga_test_filters']    = array();
		RWGA_Generation_Router::set_transport_overrides( null );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		RWGA_Generation_Router::set_transport_overrides( null );
		$GLOBALS['rwgo_remote_calls']    = array();
		$GLOBALS['rwga_test_options']    = array();
		$GLOBALS['rwga_test_transients'] = array();
		$GLOBALS['rwga_test_filters']    = array();
	}

	/**
	 * @return void
	 */
	public function test_default_chain_excludes_managed(): void {
		$this->assertSame( array( 'wordpress_ai', 'local' ), RWGA_Generation_Router::resolve_chain( 'automatic' ) );
		$this->assertNotContains( 'managed', RWGA_Generation_Router::resolve_chain( 'automatic' ) );
		$this->assertNotContains( 'managed', RWGA_Generation_Router::resolve_chain( 'remote_fallback' ) );
		$this->assertSame( array( 'wordpress_ai', 'local' ), RWGA_Generation_Router::resolve_chain( 'managed' ) );
	}

	/**
	 * @return void
	 */
	public function test_claims_require_decision_cloud_growth_or_scale_covering_optimise(): void {
		$this->assertTrue(
			RWGO_Decision_Cloud::claims_allow_hosted_ai(
				array(
					'packageType'   => 'reactwoo-decision-cloud',
					'plan_code'     => 'growth',
					'product_slugs' => array( 'reactwoo-geocore-pro', 'reactwoo-geo-optimise' ),
					'exp'           => time() + 3600,
				)
			)
		);
		$this->assertTrue(
			RWGO_Decision_Cloud::claims_allow_hosted_ai(
				array(
					'packageType'  => 'reactwoo-decision-cloud',
					'plan_code'    => 'scale',
					'productSlugs' => 'reactwoo-geocore-pro, reactwoo-geo-optimise',
					'exp'          => time() + 3600,
				)
			)
		);
		$this->assertFalse(
			RWGO_Decision_Cloud::claims_allow_hosted_ai(
				array(
					'packageType' => 'reactwoo-geo-optimise',
					'plan_code'   => 'pro',
					'exp'         => time() + 3600,
				)
			)
		);
		$this->assertFalse(
			RWGO_Decision_Cloud::claims_allow_hosted_ai(
				array(
					'packageType'   => 'reactwoo-decision-cloud',
					'plan_code'     => 'starter',
					'product_slugs' => array( 'reactwoo-geocore-pro' ),
					'exp'           => time() + 3600,
				)
			)
		);
		$this->assertFalse(
			RWGO_Decision_Cloud::claims_allow_hosted_ai(
				array(
					'packageType'   => 'reactwoo-decision-cloud',
					'plan_code'     => 'growth',
					'product_slugs' => array( 'reactwoo-geo-optimise' ),
					'exp'           => time() - 10,
				)
			)
		);
		$this->assertFalse( RWGO_Decision_Cloud::is_ai_api_path( '/api/v5/auth/login' ) );
		$this->assertFalse( RWGO_Decision_Cloud::is_ai_api_path( '/api/v5/updates/check' ) );
		$this->assertTrue( RWGO_Decision_Cloud::is_ai_api_path( '/api/v5/ai/assistant/usage' ) );
		$this->assertTrue( RWGO_Decision_Cloud::is_ai_api_path( '/api/v5/geo-optimise/workflow' ) );
	}

	/**
	 * @return void
	 */
	public function test_no_remote_ai_http_without_entitlement(): void {
		$this->cache_token(
			$this->jwt(
				array(
					'packageType' => 'reactwoo-geo-optimise',
					'exp'         => time() + 3600,
				)
			)
		);
		update_option( 'rwga_settings', array( 'workflow_engine' => 'managed' ) );

		$usage = RWGA_Platform_Client::get_usage();
		$this->assertInstanceOf( WP_Error::class, $usage );
		$this->assertSame( 'rwgo_decision_cloud_required', $usage->get_error_code() );

		$health = RWGA_Platform_Client::ai_health();
		$this->assertInstanceOf( WP_Error::class, $health );

		$workflow = RWGA_Platform_Client::request( 'POST', '/api/v5/geo-optimise/workflow', array( 'workflow_key' => 'ux_analysis' ), true );
		$this->assertInstanceOf( WP_Error::class, $workflow );

		$remote = RWGA_Remote_Client::dispatch( 'ux_analysis', array( 'page_id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $remote );
		$this->assertSame( RWGO_Decision_Cloud::NOTICE, $remote->get_error_message() );

		$transport = new RWGA_Managed_AI_Transport();
		$dispatch  = $transport->dispatch( 'ux_analysis', array( 'payload' => array( 'page_id' => 1 ) ) );
		$this->assertInstanceOf( WP_Error::class, $dispatch );

		$sync = RWGA_AI_Usage_Guard::can_sync_snapshot();
		$this->assertFalse( $sync['allowed'] );
		$this->assertSame( RWGO_Decision_Cloud::NOTICE, $sync['reason'] );

		$this->assertSame( array(), $this->ai_calls() );

		$GLOBALS['rwgo_remote_calls'] = array();
		RWGA_Platform_Client::request( 'GET', '/api/v5/updates/check', null, true );
		$this->assertNotEmpty( $GLOBALS['rwgo_remote_calls'] );
		$this->assertStringContainsString( '/api/v5/updates/check', (string) $GLOBALS['rwgo_remote_calls'][0]['url'] );
		$this->assertSame( array(), $this->ai_calls() );
	}

	/**
	 * @return void
	 */
	public function test_settings_migration_keeps_saved_values_and_is_idempotent(): void {
		update_option(
			'rwga_settings',
			array(
				'workflow_engine'      => 'managed',
				'reactwoo_license_key' => 'keep-me',
			)
		);
		update_option( RWGO_Decision_Cloud::MIGRATION_OPTION, '' );

		RWGO_Decision_Cloud::migrate_saved_engine();
		RWGO_Decision_Cloud::migrate_saved_engine();

		$stored = get_option( 'rwga_settings' );
		$this->assertSame( 'managed', $stored['workflow_engine'] );
		$this->assertSame( 'keep-me', $stored['reactwoo_license_key'] );
		$this->assertSame( RWGO_Decision_Cloud::MIGRATION_VERSION, get_option( RWGO_Decision_Cloud::MIGRATION_OPTION ) );
		$this->assertSame( 'automatic', RWGA_Engine::get_effective_mode() );
		$this->assertSame( array( 'wordpress_ai', 'local' ), RWGA_Generation_Router::resolve_chain( RWGA_Engine::get_mode() ) );

		update_option( 'rwga_settings', array( 'workflow_engine' => 'remote_fallback', 'reactwoo_license_key' => 'keep-me' ) );
		RWGO_Decision_Cloud::migrate_saved_engine();
		$this->assertSame( 'remote_fallback', get_option( 'rwga_settings' )['workflow_engine'] );
		$this->assertSame( 'automatic', RWGA_Engine::get_effective_mode() );

		update_option( 'rwga_settings', array( 'workflow_engine' => 'remote', 'reactwoo_license_key' => 'keep-me' ) );
		$this->assertSame( 'remote', get_option( 'rwga_settings' )['workflow_engine'] );
		$this->assertSame( 'automatic', RWGA_Engine::get_effective_mode() );
	}

	/**
	 * @return void
	 */
	public function test_managed_is_allowed_with_decision_cloud_entitlement(): void {
		$this->cache_token( $this->growth_jwt(), 'rwgo' );
		update_option( 'rwga_settings', array( 'workflow_engine' => 'managed' ) );

		$this->assertTrue( RWGO_Decision_Cloud::hosted_ai_allowed() );
		$this->assertSame( 'managed', RWGA_Engine::get_effective_mode() );
		$this->assertSame( array( 'managed' ), RWGA_Generation_Router::resolve_chain( 'managed' ) );
		$this->assertSame( array( 'wordpress_ai', 'local' ), RWGA_Generation_Router::resolve_chain( 'automatic' ) );
		$this->assertTrue( RWGA_Engine::should_try_remote() );

		update_option( 'rwga_settings', array( 'workflow_engine' => 'automatic' ) );
		$this->assertNotContains( 'managed', RWGA_Generation_Router::resolve_chain( RWGA_Engine::get_effective_mode() ) );

		update_option( 'rwga_settings', array( 'workflow_engine' => 'remote_fallback' ) );
		$this->assertSame( 'automatic', RWGA_Engine::get_effective_mode() );
		$this->assertNotContains( 'managed', RWGA_Generation_Router::resolve_chain( 'remote_fallback' ) );

		update_option( 'rwga_settings', array( 'workflow_engine' => 'managed' ) );
		$managed = new RWGO_Recording_Transport(
			'managed',
			true,
			array(
				'transport'       => 'managed',
				'engine_response' => array( 'summary' => 'hosted' ),
				'remote_run_id'   => 'run',
				'usage'           => array(),
				'meta'            => array(),
			)
		);
		$local = new RWGO_Recording_Transport(
			'local',
			true,
			array(
				'transport'       => 'local',
				'engine_response' => array( 'summary' => 'local' ),
				'remote_run_id'   => null,
				'usage'           => array(),
				'meta'            => array(),
			)
		);
		RWGA_Generation_Router::set_transport_overrides(
			array(
				'managed' => $managed,
				'local'   => $local,
			)
		);
		$out = RWGA_Generation_Router::generate( 'ux_analysis', array( 'payload' => array( 'page_id' => 4 ) ) );
		$this->assertIsArray( $out );
		$this->assertSame( 'managed', $out['transport'] );
		$this->assertSame( 1, $managed->dispatch_calls );
		$this->assertSame( 0, $local->dispatch_calls );
		$this->assertSame( array(), $this->ai_calls() );

		$GLOBALS['rwgo_remote_calls'] = array();
		$this->cache_token( $this->growth_jwt(), 'rwga' );
		RWGA_Platform_Client::get_usage();
		$ai = $this->ai_calls();
		$this->assertCount( 1, $ai );
		$this->assertStringContainsString( '/api/v5/ai/assistant/usage', $ai[0]['url'] );

		$guard = RWGA_AI_Usage_Guard::can_run_managed_generation( 'ux_analysis' );
		$this->assertArrayHasKey( 'allowed', $guard );
		$this->assertNotSame( RWGO_Decision_Cloud::NOTICE, $guard['reason'] );
	}

	/**
	 * @return void
	 */
	public function test_intelligence_workflows_are_gated_without_entitlement(): void {
		update_option( 'rwga_settings', array( 'workflow_engine' => 'automatic' ) );
		$workflow = new RWGA_Workflow_Intelligence( 'site_audit', 'Site audit', 'site_auditor' );

		$validated = $workflow->validate_input( array() );
		$this->assertInstanceOf( WP_Error::class, $validated );
		$this->assertSame( 'rwgo_decision_cloud_required', $validated->get_error_code() );
		$this->assertSame( RWGO_Decision_Cloud::NOTICE, $validated->get_error_message() );

		$executed = $workflow->execute( array() );
		$this->assertInstanceOf( WP_Error::class, $executed );
		$this->assertSame( RWGO_Decision_Cloud::NOTICE, $executed->get_error_message() );

		$guard = RWGA_AI_Usage_Guard::can_run_workflow( 'site_audit' );
		$this->assertFalse( $guard['allowed'] );
		$this->assertSame( RWGO_Decision_Cloud::NOTICE, $guard['reason'] );
		$this->assertSame( array(), $this->ai_calls() );
	}

	/**
	 * @return void
	 */
	public function test_automatic_does_not_fall_through_to_hosted_ai(): void {
		$this->cache_token( $this->growth_jwt() );
		update_option( 'rwga_settings', array( 'workflow_engine' => 'automatic' ) );
		$managed = new RWGO_Recording_Transport(
			'managed',
			true,
			array(
				'transport'       => 'managed',
				'engine_response' => array( 'summary' => 'hosted' ),
				'remote_run_id'   => 'run',
				'usage'           => array(),
				'meta'            => array(),
			)
		);
		RWGA_Generation_Router::set_transport_overrides(
			array(
				'wordpress_ai' => new RWGO_Recording_Transport(
					'wordpress_ai',
					new WP_Error( 'rwga_transport_unavailable', 'no provider' ),
					array( 'transport' => 'wordpress_ai', 'engine_response' => array() )
				),
				'managed'      => $managed,
				'local'        => new RWGA_Local_AI_Transport(),
			)
		);
		$out = RWGA_Generation_Router::generate(
			'ux_analysis',
			array(
				'payload'        => array( 'page_id' => 9 ),
				'local_callback' => static function () {
					return array( 'summary' => 'local' );
				},
			)
		);
		$this->assertIsArray( $out );
		$this->assertSame( 'local', $out['transport'] );
		$this->assertSame( 0, $managed->dispatch_calls );
	}

	/**
	 * @param array<string, mixed> $claims Claims.
	 * @return string
	 */
	private function jwt( array $claims ) {
		$header = rtrim( strtr( base64_encode( '{"alg":"none","typ":"JWT"}' ), '+/', '-_' ), '=' );
		$body   = rtrim( strtr( base64_encode( (string) wp_json_encode( $claims ) ), '+/', '-_' ), '=' );
		return $header . '.' . $body . '.sig';
	}

	/**
	 * @return string
	 */
	private function growth_jwt() {
		return $this->jwt(
			array(
				'packageType'   => 'reactwoo-decision-cloud',
				'plan_code'     => 'growth',
				'product_slugs' => array( 'reactwoo-geocore-pro', 'reactwoo-geo-commerce', 'reactwoo-geo-optimise' ),
				'exp'           => time() + 86400,
			)
		);
	}

	/**
	 * @param string $jwt   JWT.
	 * @param string $store rwga or rwgo.
	 * @return void
	 */
	private function cache_token( $jwt, $store = 'rwga' ) {
		$key = 'rwgo' === $store ? 'rwgo_rw_jwt_cache' : 'rwga_rw_jwt_cache';
		set_transient(
			$key,
			array(
				'token'   => $jwt,
				'expires' => time() + 86400,
			)
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function ai_calls() {
		$out = array();
		foreach ( $GLOBALS['rwgo_remote_calls'] as $call ) {
			$url = isset( $call['url'] ) ? (string) $call['url'] : '';
			if ( RWGO_Decision_Cloud::is_ai_api_path( $url ) ) {
				$out[] = $call;
			}
		}
		return $out;
	}
}
