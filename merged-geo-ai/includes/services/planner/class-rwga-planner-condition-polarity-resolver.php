<?php
/**
 * Split clause text into include vs exclude condition groups.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Condition_Polarity_Resolver {

	/** @var array<int,string> */
	const EXCLUSION_MARKERS = array(
		'except users in',
		'except visitors in',
		'except for users in',
		'except for visitors in',
		'excluding users in',
		'excluding visitors in',
		'excluding',
		'not in',
		'but not',
		'apart from',
	);

	/**
	 * @return array{include:array<string,array>,exclude:array<string,array>}
	 */
	public static function empty_groups() {
		$empty = array(
			'countries'        => array(),
			'regions'          => array(),
			'devices'          => array(),
			'weather'          => array(),
			'urls'             => array(),
			'utm'              => array(),
			'campaigns'        => array(),
			'audiences'        => array(),
			'visitorStates'    => array(),
			'pageTypes'        => array(),
			'condition_groups' => array(),
		);
		return array(
			'include' => $empty,
			'exclude' => $empty,
		);
	}

	/**
	 * @param string $clause Clause text.
	 * @return array{include_text:string,exclude_text:string}
	 */
	public static function split_text( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$include = $clause;
		$exclude = '';

		$patterns = array(
			'/\b(?:but\s+)?exclude\s+(?:visitors?|users?|people|customers?|traffic)\s+from\s+(.+)$/i',
			'/\b(?:but\s+)?exclude\s+traffic\s+from\s+(.+)$/i',
			'/\b(?:but\s+)?exclude\s+(email\s+traffic|[\w-]+\s+traffic)\.?/i',
			'/\bhide\s+from\s+(.+)$/i',
			'/\bexcept\s+when\s+(?:the\s+)?weather\s+is\s+(.+)$/i',
			'/\bnot\s+when\s+(.+)$/i',
			'/\bexcept\s+when\s+(.+)$/i',
			'/\b(?:but\s+)?exclude\s+(?:anyone|everyone|users|visitors|people|those|customers)\s+(?:who\s+)?(?:are\s+)?(?:arriving|coming|landing)\s+from\s+(.+)$/i',
			'/\b(?:but\s+)?exclude\s+(?:anyone|everyone|users|visitors|people|those|customers)\s+(?:in|from)\s+(.+)$/i',
			'/\b(?:except|excluding)\s+(?:users|visitors)\s+in\s+(.+)$/i',
			'/\b(?:except|excluding)\s+for\s+(?:users|visitors)\s+in\s+(.+)$/i',
			'/\bexcluding\s+(.+)$/i',
			'/\bapart\s+from\s+(.+)$/i',
			'/\bnot\s+in\s+(.+)$/i',
			'/\bbut\s+not\s+(.+)$/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $include, $m ) ) {
				$exclude = trim( (string) $m[1] );
				$include = trim( (string) preg_replace( $pattern, '', $include ) );
				break;
			}
		}

		// Pronoun-hide exclusions ("but don't show it to tablet users") only split
		// off when a substantial positive instruction remains; otherwise the clause
		// itself is the hide action and the condition stays in the include group.
		if ( '' === $exclude ) {
			$pronoun_patterns = array(
				'/\bbut\s+(?:don\'t|do not|dont)\s+(?:show|display)\s+it\s+to\s+visitors?\s+from\s+(.+?)(?:\.\s*(?:also\b|and\b)|\.\s*$|$)/is',
				'/\b(?:don\'t|do not|dont)\s+(?:show|display)\s+it\s+to\s+visitors?\s+from\s+(.+?)(?:\.\s*(?:also\b|and\b)|\.\s*$|$)/is',
				'/\bbut\s+(?:don\'t|do not|dont)\s+(?:show|display)\s+it\s+to\s+(.+?)(?:\.\s*(?:also\b|and\b)|\.\s*$|$)/is',
				'/\b(?:don\'t|do not|dont)\s+(?:show|display)\s+it\s+to\s+(.+?)(?:\.\s*(?:also\b|and\b)|\.\s*$|$)/is',
				'/\bbut\s+(?:hide|exclude)\s+it\s+from\s+(.+?)(?:\.\s*(?:also\b|and\b)|\.\s*$|$)/is',
			);
			foreach ( $pronoun_patterns as $pattern ) {
				if ( ! preg_match( $pattern, $include, $m ) ) {
					continue;
				}
				$candidate_exclude = trim( (string) $m[1], " \t\n\r\0\x0B,." );
				$candidate_include = trim( (string) preg_replace( $pattern, '', $include ) );
				if ( self::is_substantial_include( $candidate_include ) ) {
					$exclude = $candidate_exclude;
					$include = $candidate_include;
				}
				break;
			}
		}

		self::reclaim_include_tail_from_exclude( $include, $exclude );
		$exclude = self::normalise_exclude_fragment( $exclude );

		return array(
			'include_text' => trim( $include, " \t\n\r\0\x0B,." ),
			'exclude_text' => trim( $exclude, " \t\n\r\0\x0B,." ),
		);
	}

	/**
	 * Move trailing include/trigger clauses accidentally captured in exclude text.
	 *
	 * @param string $include Include text (by ref).
	 * @param string $exclude Exclude text (by ref).
	 * @return void
	 */
	private static function reclaim_include_tail_from_exclude( &$include, &$exclude ) {
		if ( '' === trim( (string) $exclude ) ) {
			return;
		}
		if ( preg_match( '/^(.+?)(?:\.\s*(?:also\s+)?(?:only\s+trigger|only\s+when|only\s+show|only\s+display)\b.+)$/is', $exclude, $m ) ) {
			$exclude = trim( (string) $m[1], " \t\n\r\0\x0B,." );
			$include = trim( $include . '. Also ' . trim( (string) $m[2] ) );
		}
	}

	/**
	 * @param string $exclude Exclude fragment.
	 * @return string
	 */
	private static function normalise_exclude_fragment( $exclude ) {
		$exclude = trim( (string) $exclude, " \t\n\r\0\x0B,." );
		if ( '' === $exclude ) {
			return '';
		}
		$exclude = (string) preg_replace( '/^(?:visitors?|users?|people|customers?)\s+from\s+/i', '', $exclude );
		return trim( $exclude, " \t\n\r\0\x0B,." );
	}

	/**
	 * @param string $text Candidate include remainder.
	 * @return bool
	 */
	private static function is_substantial_include( $text ) {
		$text = strtolower( (string) $text );
		$text = (string) preg_replace( '/\b(?:but|and|then|so|it|to|the|a|an|that|which|should|will|would)\b/i', ' ', $text );
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		return strlen( $text ) > 3;
	}

	/**
	 * @param array<string,mixed> $conditions Polar conditions.
	 * @return array<string,array>
	 */
	public static function include_group( array $conditions ) {
		if ( isset( $conditions['include'] ) && is_array( $conditions['include'] ) ) {
			return $conditions['include'];
		}
		return $conditions;
	}

	/**
	 * @param array<string,mixed> $conditions Polar conditions.
	 * @return array<string,array>
	 */
	public static function exclude_group( array $conditions ) {
		if ( isset( $conditions['exclude'] ) && is_array( $conditions['exclude'] ) ) {
			return $conditions['exclude'];
		}
		return self::empty_groups()['exclude'];
	}
}
