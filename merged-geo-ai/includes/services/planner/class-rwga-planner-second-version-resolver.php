<?php
/**
 * Resolve "second version of the same product page" variant instructions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Second_Version_Resolver {

	/**
	 * @param string $clause Clause text.
	 * @return array{index:int,sourceLabel:string}|null
	 */
	public static function detect( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		if ( ! preg_match(
			'/\bcreate\s+(?:a\s+)?(second|third|fourth|one|two|three|\d+(?:st|nd|rd|th)?)\s+(?:version|variant)\s+of\s+(?:the\s+)?(?:same\s+)?(?:product\s+page|[\w\s-]+\s+product\s+page)\b/i',
			$clause,
			$m
		) ) {
			return null;
		}

		$index_key = strtolower( (string) $m[1] );
		$map       = array(
			'one'    => 1,
			'first'  => 1,
			'1'      => 1,
			'1st'    => 1,
			'two'    => 2,
			'second' => 2,
			'2'      => 2,
			'2nd'    => 2,
			'three'  => 3,
			'third'  => 3,
			'3'      => 3,
			'3rd'    => 3,
			'four'   => 4,
			'fourth' => 4,
			'4'      => 4,
			'4th'    => 4,
		);
		$index = $map[ $index_key ] ?? (int) $index_key;
		if ( $index < 1 ) {
			$index = 2;
		}

		$source = 'product page';
		if ( preg_match( '/\b([\w\s-]+?)\s+product\s+page\b/i', $clause, $label_match ) ) {
			$source = trim( (string) $label_match[1] ) . ' product page';
		}

		return array(
			'index'       => $index,
			'sourceLabel' => $source,
		);
	}
}
