<?php
/**
 * Generation router + transport unit tests (fake transports; no live provider).
 *
 * @package ReactWoo_Geo_Optimise
 */

/**
 * Fake transport for router tests.
 */
class RWGA_Fake_Generation_Transport implements RWGA_Generation_Transport {

	/** @var string */
	private $key;
	/** @var bool */
	private $supports;
	/** @var true|\WP_Error */
	private $availability;
	/** @var array<string, mixed>|\WP_Error */
	private $result;
	/** @var int */
	public $dispatch_calls = 0;

	/**
	 * @param string                           $key          Key.
	 * @param bool                             $supports     Supports.
	 * @param true|\WP_Error                   $availability Availability.
	 * @param array<string, mixed>|\WP_Error   $result       Dispatch result.
	 */
	public function __construct( $key, $supports, $availability, $result ) {
		$this->key          = (string) $key;
		$this->supports     = (bool) $supports;
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
		return $this->supports;
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
 * @covers RWGA_Generation_Router
 * @covers RWGA_Prompt_Context_Formatter
 * @covers RWGA_WordPress_AI_Transport
 * @covers RWGA_Engine
 */
class RWGAGenerationRouterTest extends PHPUnit\Framework\TestCase {

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		RWGA_Generation_Router::set_transport_overrides( null );
		RWGA_WordPress_AI_Transport::$test_prompt_executor = null;
		$GLOBALS['rwga_test_options'] = array();
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_legacy_remote_maps_to_managed_chain() {
		$this->assertSame( array( 'managed' ), RWGA_Generation_Router::resolve_chain( 'remote' ) );
		$this->assertSame( array( 'managed', 'local' ), RWGA_Generation_Router::resolve_chain( 'remote_fallback' ) );
		$this->assertSame( array( 'wordpress_ai', 'managed', 'local' ), RWGA_Generation_Router::resolve_chain( 'automatic' ) );
	}

	/**
	 * @return void
	 */
	public function test_automatic_uses_wordpress_ai_when_available() {
		update_option( 'rwga_settings', array( 'workflow_engine' => 'automatic' ) );
		$wp = new RWGA_Fake_Generation_Transport(
			'wordpress_ai',
			true,
			true,
			array(
				'transport'       => 'wordpress_ai',
				'engine_response' => array( 'score' => 70, 'confidence' => 0.8, 'summary' => 'ok', 'findings' => array() ),
				'remote_run_id'   => null,
				'usage'           => array(),
				'meta'            => array( 'engine_source' => 'wordpress_ai', 'provider' => 'wordpress_ai' ),
			)
		);
		$mg = new RWGA_Fake_Generation_Transport(
			'managed',
			true,
			true,
			array(
				'transport'       => 'managed',
				'engine_response' => array( 'score' => 1 ),
				'remote_run_id'   => 'x',
				'usage'           => array(),
				'meta'            => array(),
			)
		);
		RWGA_Generation_Router::set_transport_overrides(
			array(
				'wordpress_ai' => $wp,
				'managed'      => $mg,
				'local'        => new RWGA_Local_AI_Transport(),
			)
		);

		$out = RWGA_Generation_Router::generate(
			'ux_analysis',
			array(
				'payload'        => array( 'page_id' => 1 ),
				'local_callback' => static function () {
					return array( 'score' => 0 );
				},
			)
		);

		$this->assertIsArray( $out );
		$this->assertSame( 'wordpress_ai', $out['transport'] );
		$this->assertSame( 1, $wp->dispatch_calls );
		$this->assertSame( 0, $mg->dispatch_calls );
	}

	/**
	 * @return void
	 */
	public function test_automatic_falls_to_managed_when_wordpress_unavailable() {
		update_option( 'rwga_settings', array( 'workflow_engine' => 'automatic' ) );
		$wp = new RWGA_Fake_Generation_Transport(
			'wordpress_ai',
			true,
			new WP_Error( 'rwga_transport_unavailable', 'no wp ai' ),
			array( 'transport' => 'wordpress_ai', 'engine_response' => array() )
		);
		$mg = new RWGA_Fake_Generation_Transport(
			'managed',
			true,
			true,
			array(
				'transport'       => 'managed',
				'engine_response' => array( 'score' => 88, 'confidence' => 0.9, 'summary' => 'm', 'findings' => array() ),
				'remote_run_id'   => 'run-1',
				'usage'           => array(),
				'meta'            => array( 'provider' => 'reactwoo' ),
			)
		);
		RWGA_Generation_Router::set_transport_overrides(
			array(
				'wordpress_ai' => $wp,
				'managed'      => $mg,
				'local'        => new RWGA_Local_AI_Transport(),
			)
		);

		$out = RWGA_Generation_Router::generate(
			'ux_analysis',
			array(
				'payload'        => array( 'page_id' => 2 ),
				'local_callback' => static function () {
					return array( 'score' => 0 );
				},
			)
		);

		$this->assertIsArray( $out );
		$this->assertSame( 'managed', $out['transport'] );
		$this->assertSame( 0, $wp->dispatch_calls );
		$this->assertSame( 1, $mg->dispatch_calls );
		$meta = RWGA_Generation_Router::last_meta();
		$this->assertNotEmpty( $meta['fallback_reason'] );
	}

	/**
	 * @return void
	 */
	public function test_no_fallback_after_generation_failure() {
		update_option( 'rwga_settings', array( 'workflow_engine' => 'automatic' ) );
		$wp = new RWGA_Fake_Generation_Transport(
			'wordpress_ai',
			true,
			true,
			new WP_Error( 'rwga_generation_invalid_response', 'bad json' )
		);
		$mg = new RWGA_Fake_Generation_Transport(
			'managed',
			true,
			true,
			array(
				'transport'       => 'managed',
				'engine_response' => array( 'score' => 1 ),
				'remote_run_id'   => null,
				'usage'           => array(),
				'meta'            => array(),
			)
		);
		RWGA_Generation_Router::set_transport_overrides(
			array(
				'wordpress_ai' => $wp,
				'managed'      => $mg,
				'local'        => new RWGA_Local_AI_Transport(),
			)
		);

		$out = RWGA_Generation_Router::generate(
			'ux_analysis',
			array(
				'payload'        => array( 'page_id' => 3 ),
				'local_callback' => static function () {
					return array( 'score' => 0 );
				},
			)
		);

		$this->assertInstanceOf( WP_Error::class, $out );
		$this->assertSame( 'rwga_generation_invalid_response', $out->get_error_code() );
		$this->assertSame( 1, $wp->dispatch_calls );
		$this->assertSame( 0, $mg->dispatch_calls );
	}

	/**
	 * @return void
	 */
	public function test_explicit_wordpress_ai_unsupported_workflow() {
		update_option( 'rwga_settings', array( 'workflow_engine' => 'wordpress_ai' ) );
		RWGA_Generation_Router::set_transport_overrides(
			array(
				'wordpress_ai' => new RWGA_WordPress_AI_Transport(),
			)
		);

		$out = RWGA_Generation_Router::generate(
			'competitor_research',
			array( 'payload' => array() )
		);

		$this->assertInstanceOf( WP_Error::class, $out );
		$this->assertSame( 'rwga_transport_unsupported', $out->get_error_code() );
	}

	/**
	 * @return void
	 */
	public function test_wordpress_ai_fake_executor_and_prompt_has_no_raw_elementor() {
		update_option( 'rwga_settings', array( 'workflow_engine' => 'wordpress_ai' ) );
		RWGA_WordPress_AI_Transport::$test_prompt_executor = static function ( $user_prompt, $spec ) {
			unset( $spec );
			if ( RWGA_Prompt_Context_Formatter::contains_raw_builder_document( $user_prompt ) ) {
				return new WP_Error( 'rwga_generation_failed', 'raw document leaked' );
			}
			return wp_json_encode(
				array(
					'score'      => 61,
					'confidence' => 0.7,
					'summary'    => 'Bounded analysis',
					'findings'   => array(
						array(
							'finding_key' => 'hero_clarity',
							'category'    => 'messaging',
							'severity'    => 'medium',
							'title'       => 'Hero',
						),
					),
				)
			);
		};

		RWGA_Generation_Router::set_transport_overrides(
			array(
				'wordpress_ai' => new RWGA_WordPress_AI_Transport(),
			)
		);

		$payload = array(
			'page_id'         => 10,
			'builder_context' => array(
				'builder'  => 'elementor',
				'headings' => array( array( 'text' => 'Hello', 'level' => 1 ) ),
				'ctas'     => array( array( 'label' => 'Buy', 'url' => '/buy' ) ),
			),
			// Attempt to sneak raw document markers — formatter must not emit them.
			'_elementor_data' => '[{"id":"abc","elType":"section","settings":{},"elements":[]}]',
		);

		$out = RWGA_Generation_Router::generate(
			'ux_analysis',
			array( 'payload' => $payload )
		);

		$this->assertIsArray( $out );
		$this->assertSame( 'wordpress_ai', $out['transport'] );
		$this->assertSame( 61.0, (float) $out['engine_response']['score'] );
		$prompt = RWGA_Prompt_Context_Formatter::format_user_prompt( 'ux_analysis', array( 'payload' => $payload ) );
		$this->assertFalse( RWGA_Prompt_Context_Formatter::contains_raw_builder_document( $prompt ) );
		$this->assertStringNotContainsString( '_elementor_data', $prompt );
	}

	/**
	 * @return void
	 */
	public function test_invalid_json_from_wordpress_ai_is_contract_error() {
		update_option( 'rwga_settings', array( 'workflow_engine' => 'wordpress_ai' ) );
		RWGA_WordPress_AI_Transport::$test_prompt_executor = static function () {
			return 'not-json';
		};
		RWGA_Generation_Router::set_transport_overrides(
			array( 'wordpress_ai' => new RWGA_WordPress_AI_Transport() )
		);

		$out = RWGA_Generation_Router::generate(
			'ux_analysis',
			array( 'payload' => array( 'page_id' => 1 ) )
		);

		$this->assertInstanceOf( WP_Error::class, $out );
		$this->assertSame( 'rwga_generation_invalid_response', $out->get_error_code() );
	}

	/**
	 * @return void
	 */
	public function test_engine_public_mode_maps_remote() {
		update_option( 'rwga_settings', array( 'workflow_engine' => 'remote' ) );
		$this->assertSame( 'remote', RWGA_Engine::get_mode() );
		$this->assertSame( 'managed', RWGA_Engine::get_public_mode() );
		$this->assertTrue( RWGA_Engine::should_try_remote() );
	}

	/**
	 * @return void
	 */
	public function test_remote_fallback_never_includes_wordpress_ai() {
		$this->assertNotContains( 'wordpress_ai', RWGA_Generation_Router::resolve_chain( 'remote_fallback' ) );
	}
}
