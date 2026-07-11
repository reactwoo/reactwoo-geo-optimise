<?php
/**
 * Parse variant plans: source/original page targeting + duplicate variants.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Variant_Plan_Interpreter {

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Resolved context.
	 * @return array<string,mixed>
	 */
	public static function parse( $phrase, array $entities, array $context = array() ) {
		if ( class_exists( 'RWGA_Variant_Plan_Parser', false ) ) {
			return RWGA_Variant_Plan_Parser::parse( $phrase, $entities, $context );
		}
		return array( 'matched' => false );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_duplicate_count( $phrase ) {
		return class_exists( 'RWGA_Variant_Plan_Parser', false )
			? RWGA_Variant_Plan_Parser::detect_duplicate_count( $phrase )
			: 0;
	}
}
