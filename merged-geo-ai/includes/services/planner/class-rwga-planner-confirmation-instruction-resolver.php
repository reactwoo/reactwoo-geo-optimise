<?php
/**
 * Detect confirmation / meta phrases that must not become executable actions.
 *
 * Phrases like "show me the split before creating it" are control instructions —
 * they should surface as confirmation_instruction metadata, not as update_rule
 * or update_original_targeting actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Confirmation_Instruction_Resolver {

	/**
	 * @var array<int,string>
	 */
	const PATTERNS = array(
		'show me the split',
		'show the split first',
		'show me the split before creating',
		'before creating it',
		'before you create it',
		'before creating anything',
		'do not create anything until i confirm',
		"don't create anything until i confirm",
		'dont create anything until i confirm',
		'do not create until i confirm',
		"don't create until i confirm",
		'dont create until i confirm',
		'ask me before creating',
		'show me before applying',
		'show me the setup first',
		'confirm before creating',
		'wait for confirmation',
		'review before creating',
		'show me before creating',
		'show me the plan before creating',
		'show me the full rule before creating',
		'show full rule before creating',
		'show me the full rule',
	);

	/**
	 * Strip confirmation/meta phrases from the phrase and return structured data.
	 *
	 * @param string $phrase Normalised phrase.
	 * @return array{phrase:string,confirmation_instruction:array|null,ignored:array<int,string>}
	 */
	public static function extract( $phrase ) {
		$phrase   = RWGA_Local_Intent_Interpreter::normalise( (string) $phrase );
		$ignored  = array();
		$matched  = null;

		foreach ( self::PATTERNS as $pattern ) {
			$regex = '/(?:[,.]\s*|\s+)(?:and\s+)?' . preg_quote( $pattern, '/' ) . '\b.*$/i';
			if ( preg_match( $regex, $phrase, $m ) ) {
				$raw     = trim( (string) $m[0], " \t\n\r\0\x0B,." );
				$ignored[] = $raw;
				$matched   = $raw;
				$phrase    = trim( (string) preg_replace( $regex, '', $phrase ) );
				continue;
			}
			if ( preg_match( '/^' . preg_quote( $pattern, '/' ) . '\b.*$/i', $phrase, $m ) ) {
				$raw       = trim( (string) $m[0] );
				$ignored[] = $raw;
				$matched   = $raw;
				$phrase    = '';
				break;
			}
		}

		$phrase = trim( (string) preg_replace( '/\s+/', ' ', $phrase ) );
		$phrase = trim( $phrase, ' ,.' );

		$instruction = null;
		if ( null !== $matched && '' !== $matched ) {
			$instruction = array(
				'raw'                   => $matched,
				'requires_confirmation' => true,
				'display_text'          => self::display_text( $matched ),
				'detail'                => __( 'Manual confirmation required before execution.', 'reactwoo-geocore' ),
			);
		}

		return array(
			'phrase'                   => $phrase,
			'confirmation_instruction' => $instruction,
			'ignored'                  => array_values( array_unique( $ignored ) ),
		);
	}

	/**
	 * Human-readable confirmation line for Action Review.
	 *
	 * @param string $raw Matched phrase fragment.
	 * @return string
	 */
	public static function display_text( $raw ) {
		$text = trim( (string) $raw );
		if ( '' === $text ) {
			return '';
		}
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		if ( ! preg_match( '/[.!?]$/', $text ) ) {
			$text .= '.';
		}
		return ucfirst( $text );
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function is_confirmation_only( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( (string) $clause );
		if ( '' === $clause ) {
			return false;
		}
		foreach ( self::PATTERNS as $pattern ) {
			if ( preg_match( '/^' . preg_quote( $pattern, '/' ) . '\b/i', $clause ) ) {
				return true;
			}
		}
		if ( preg_match( '/\b(?:show\s+me\s+(?:the\s+)?(?:split|full\s+rule)|before\s+creat(?:e|ing)|wait\s+for\s+confirm|confirm\s+before\s+creat|review\s+before\s+creat)\b/i', $clause )
			&& ! preg_match( '/\b(?:portugal|germany|russia|uk|homepage|variant|version|original|country|weather|rain|sunny)\b/i', $clause ) ) {
			return true;
		}
		return false;
	}
}
