<?php
/**
 * Intent-level page blueprint (builder-agnostic).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes page intent, not builder markup.
 */
class RWGA_Page_Blueprint {

	/**
	 * @var string
	 */
	private $page_type = 'landing_page';

	/**
	 * @var string
	 */
	private $goal = 'lead_generation';

	/**
	 * @var array<int, RWGA_Section_Blueprint>
	 */
	private $sections = array();

	/**
	 * @param string $page_type Page type slug.
	 * @param string $goal      Goal slug.
	 */
	public function __construct( $page_type = 'landing_page', $goal = 'lead_generation' ) {
		$this->page_type = sanitize_key( (string) $page_type );
		$this->goal      = sanitize_key( (string) $goal );
	}

	/**
	 * @param RWGA_Section_Blueprint $section Section blueprint.
	 * @return void
	 */
	public function add_section( RWGA_Section_Blueprint $section ) {
		$this->sections[] = $section;
	}

	/**
	 * Standard lead-gen landing blueprint.
	 *
	 * @return self
	 */
	public static function lead_generation_landing() {
		$bp = new self( 'landing_page', 'lead_generation' );
		$bp->add_section( new RWGA_Section_Blueprint( 'hero', array( 'headline', 'subheading', 'primary_cta', 'image' ) ) );
		$bp->add_section( new RWGA_Section_Blueprint( 'benefits', array( 'cards' ) ) );
		$bp->add_section( new RWGA_Section_Blueprint( 'trust', array( 'logos', 'testimonials' ) ) );
		$bp->add_section( new RWGA_Section_Blueprint( 'faq', array( 'accordion' ) ) );
		$bp->add_section( new RWGA_Section_Blueprint( 'final_cta', array( 'headline', 'button' ) ) );
		return $bp;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		$sections = array();
		foreach ( $this->sections as $section ) {
			$sections[] = $section->to_array();
		}
		return array(
			'page_type' => $this->page_type,
			'goal'      => $this->goal,
			'sections'  => $sections,
		);
	}
}
