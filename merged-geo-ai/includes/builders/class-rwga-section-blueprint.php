<?php
/**
 * Section intent blueprint.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section-level intent for future page generation.
 */
class RWGA_Section_Blueprint {

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @var array<int, string>
	 */
	private $required_elements;

	/**
	 * @param string           $type              Section type.
	 * @param array<int, string> $required_elements Required element keys.
	 */
	public function __construct( $type, array $required_elements = array() ) {
		$this->type              = sanitize_key( (string) $type );
		$this->required_elements = array_values( array_map( 'sanitize_key', $required_elements ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return array(
			'type'              => $this->type,
			'required_elements' => $this->required_elements,
		);
	}
}
