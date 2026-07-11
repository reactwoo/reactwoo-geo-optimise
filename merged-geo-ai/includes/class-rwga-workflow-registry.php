<?php
/**
 * Registered bounded workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Workflow registry singleton map.
 */
class RWGA_Workflow_Registry {

	/**
	 * @var array<string, RWGA_Workflow_Interface>
	 */
	private static $workflows = array();

	/**
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Register workflows after textdomains load (WP 6.7+).
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		self::$workflows['ux_analysis']      = new RWGA_Workflow_UX_Analysis();
		self::$workflows['ux_recommend']     = new RWGA_Workflow_UX_Recommend();
		self::$workflows['ux_opportunity_review'] = new RWGA_Workflow_UX_Opportunity_Review();
		self::$workflows['copy_implement']   = new RWGA_Workflow_Copy_Implement();
		self::$workflows['seo_implement']    = new RWGA_Workflow_SEO_Implement();
		self::$workflows['competitor_research'] = new RWGA_Workflow_Competitor_Research();

		if ( class_exists( 'RWGA_Workflow_Intelligence_Definitions', false ) ) {
			foreach ( RWGA_Workflow_Intelligence_Definitions::build_workflows() as $key => $wf ) {
				self::$workflows[ $key ] = $wf;
			}
		}

		/**
		 * Register additional Geo AI workflows.
		 *
		 * @param array<string, RWGA_Workflow_Interface> $workflows Workflow instances keyed by workflow key.
		 */
		$extra = apply_filters( 'rwga_register_workflows', array() );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $key => $wf ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || ! $wf instanceof RWGA_Workflow_Interface ) {
					continue;
				}
				self::$workflows[ $key ] = $wf;
			}
		}
	}

	/**
	 * @param string $key Workflow key.
	 * @return RWGA_Workflow_Interface|null
	 */
	public static function get( $key ) {
		self::init();
		$key = sanitize_key( (string) $key );
		return isset( self::$workflows[ $key ] ) ? self::$workflows[ $key ] : null;
	}

	/**
	 * @return array<string, RWGA_Workflow_Interface>
	 */
	public static function all() {
		self::init();
		return self::$workflows;
	}
}
