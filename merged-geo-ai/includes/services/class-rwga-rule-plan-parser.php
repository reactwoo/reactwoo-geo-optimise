<?php
/**
 * Detect and guard rule-creation journeys that must stay a single create_rule action.
 *
 * Phrases like "Create a rule for the Winter Sale page. Show it only to mobile…"
 * describe include/exclude conditions inside one rule — they must not be split into
 * separate show/hide/update_original actions by the multi-clause splitter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Rule_Plan_Parser {

	/**
	 * Whether the phrase establishes create_rule as the primary intent.
	 *
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function has_create_rule_primary_intent( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( (string) $phrase );
		if ( '' === $phrase ) {
			return false;
		}

		return (bool) preg_match(
			'/\b(?:create|add|set\s+up|build)\s+(?:a\s+)?(?:(?:targeting|[\w-]+)\s+)?rule\b/i',
			$phrase
		);
	}

	/**
	 * Whether an "also …" segment continues conditions on the same rule (not a new action).
	 *
	 * @param string $segment Clause segment.
	 * @return bool
	 */
	public static function is_rule_condition_also_clause( $segment ) {
		$segment = RWGA_Local_Intent_Interpreter::normalise( (string) $segment );
		if ( '' === $segment ) {
			return false;
		}

		return (bool) preg_match(
			'/^(?:also\s+)?(?:only\s+)?(?:trigger|show|display|apply|when)\b/i',
			$segment
		);
	}

	/**
	 * Explicit boundaries that start a separate action (not an in-rule condition clause).
	 *
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function has_explicit_multi_action_boundary( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( (string) $phrase );
		if ( '' === $phrase ) {
			return false;
		}

		if ( preg_match( '/\b(?:then|and then)\s+(?:create|make|build)\s+(?:\d+|two|three|four|five|\d+|\ba\s+)?(?:variants?|versions?)\b/i', $phrase ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:then|and then)\s+(?:create|make|build)\s+(?:a\s+)?(?:second|third|fourth|fifth|another|new|\d+(?:st|nd|rd|th)?)\s+(?:variants?|versions?)\b/i', $phrase ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:,\s*)?(?:and\s+)?(?:also\s+)?create\s+(?:a\s+)?(?:new\s+)?(?:variants?|versions?)\b/i', $phrase ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:,\s*)?(?:and\s+)?also\s+create\s+(?:a\s+)?rule\b/i', $phrase ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:,\s*)?(?:and\s+)?also\s+(?:hide|show)\s+(?:the\s+)?/i', $phrase )
			&& ! self::is_rule_condition_also_clause( $phrase ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:,\s*)?(?:and\s+)?(?:test\s+what|check\s+what|preview\s+what|diagnose)\b/i', $phrase ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:,\s*)?(?:and\s+)?add\s+a\s+rule\b/i', $phrase )
			&& ! self::has_create_rule_primary_intent( $phrase ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param string $phrase Normalised phrase (confirmation/meta already stripped).
	 * @return bool
	 */
	public static function is_rule_plan_command( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( (string) $phrase );
		if ( '' === $phrase ) {
			return false;
		}

		if ( self::has_create_rule_primary_intent( $phrase ) ) {
			if ( preg_match( '/\b(?:create|make|build)\s+(?:\d+|two|three|four|five|\d+)\s+(?:new\s+)?variants?\b/i', $phrase ) ) {
				return false;
			}
			if ( preg_match( '/\b(?:then|and then)\s+(?:create|make|build)\s+(?:\d+|two|three|four|five|\d+|\ba\s+)?(?:variants?|versions?)\b/i', $phrase ) ) {
				return false;
			}
			if ( self::has_explicit_multi_action_boundary( $phrase ) ) {
				return false;
			}
			return true;
		}

		if ( preg_match( '/^(?:show|display)\b.+\bbut\s+(?:exclude|not)\b/is', $phrase )
			&& ! preg_match( '/\b(?:variants?|versions?|variations?)\s+of\b/i', $phrase )
			&& ! preg_match( '/\b(?:leave|keep|update)\s+(?:the\s+)?original\b/i', $phrase ) ) {
			return true;
		}

		return false;
	}
}
