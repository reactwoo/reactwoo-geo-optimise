<?php
/**
 * Widget intent blueprint.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget-level intent (type + content slots).
 */
class RWGA_Widget_Blueprint {

	/**
	 * @var string
	 */
	private $element_key;

	/**
	 * @var string
	 */
	private $widget_type;

	/**
	 * @var array<string, string>
	 */
	private $content_slots;

	/**
	 * @param string               $element_key  Element key (headline, primary_cta, …).
	 * @param string               $widget_type  Builder widget type hint.
	 * @param array<string, string> $content_slots Placeholder content.
	 */
	public function __construct( $element_key, $widget_type = '', array $content_slots = array() ) {
		$this->element_key   = sanitize_key( (string) $element_key );
		$this->widget_type   = sanitize_key( (string) $widget_type );
		$this->content_slots = $content_slots;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return array(
			'element_key'   => $this->element_key,
			'widget_type'   => $this->widget_type,
			'content_slots' => $this->content_slots,
		);
	}
}
