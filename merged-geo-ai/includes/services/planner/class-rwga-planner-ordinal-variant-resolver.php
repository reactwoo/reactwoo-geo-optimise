<?php
/**
 * Split ordinal variant child phrases (first / another / last).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Ordinal_Variant_Resolver {

	/** @var array<int,string> */
	const ORDINAL_MARKERS = array(
		'first one',
		'first',
		'second one',
		'second',
		'third one',
		'third',
		'another',
		'the other',
		'last one',
		'last',
	);

	/**
	 * @param string              $phrase Normalised variant block.
	 * @param array<string,mixed> $parent Parent instruction.
	 * @return array<int,string>
	 */
	public static function split_child_clauses( $phrase, array $parent ) {
		$phrase    = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		$remainder = self::strip_parent_header( $phrase, $parent );
		if ( '' === $remainder ) {
			return array();
		}

		if ( ! preg_match( self::ordinal_lookahead(), $remainder ) ) {
			return array();
		}

		$parts = preg_split(
			'/(?:,\s*and\s+|,\s*|\.\s*and\s+|\s+and\s+|\.\s+)(?=' . self::ordinal_lookahead_body() . ')/i',
			$remainder
		);

		if ( is_array( $parts ) && count( $parts ) >= 2 ) {
			return self::normalise_child_parts( $parts );
		}

		return array();
	}

	/**
	 * @return string Regex (with delimiters) matching any ordinal marker.
	 */
	private static function ordinal_lookahead() {
		return '/' . self::ordinal_lookahead_body() . '/i';
	}

	/**
	 * @return string Bare regex body matching ordinal markers at a clause start.
	 */
	private static function ordinal_lookahead_body() {
		return '(?:the\s+)?(?:first|second|third|fourth|fifth)(?:\s+one)?\b|another\b|the\s+other\b|(?:the\s+)?last(?:\s+one)?\b';
	}

	/**
	 * @param string $clause Child clause.
	 * @return bool
	 */
	public static function is_ordinal_child_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		return (bool) preg_match( '/^(?:first(?:\s+one)?|second(?:\s+one)?|third(?:\s+one)?|another|the\s+other|the\s+last(?:\s+one)?|last(?:\s+one)?)\b/i', $clause );
	}

	/**
	 * @param string              $phrase Normalised phrase.
	 * @param array<string,mixed> $parent Parent row.
	 * @return string
	 */
	private static function strip_parent_header( $phrase, array $parent ) {
		$page_slug = preg_quote( (string) ( $parent['sourcePage'] ?? '' ), '/' );
		$page_alt  = preg_quote( (string) ( $parent['sourcePageLabel'] ?? '' ), '/' );
		$page_alt  = str_replace( '\ ', '\s+', $page_alt );

		return trim(
			(string) preg_replace(
				'/^.*?\b(?:variants?|versions?|variations?)\s+of\s+(?:the\s+)?(?:' . $page_slug . '(?:\s+page)?|' . $page_alt . ')\s*(?:pls|please)?\s*(?:[—–-]\s*|[,:]\s*)/i',
				'',
				$phrase
			)
		);
	}

	/**
	 * @param array<int,string> $parts Raw child parts.
	 * @return array<int,string>
	 */
	private static function normalise_child_parts( array $parts ) {
		$out = array();
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			$part = preg_replace( '/^and\s+/i', '', $part );
			$part = trim( $part, " \t\n\r\0\x0B,." );
			if ( '' !== $part ) {
				$out[] = $part;
			}
		}
		return $out;
	}
}
