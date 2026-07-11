<?php
/**
 * Bounded prompt context from normalized builder bundles.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats whitelist facts for WordPress AI prompts (never raw Elementor documents).
 */
class RWGA_Prompt_Context_Formatter {

	/**
	 * Keys that must never appear in prompt text.
	 *
	 * @return string[]
	 */
	public static function forbidden_raw_markers() {
		return array(
			'_elementor_data',
			'"elements":[{',
			'"$$type":"styles"',
			'"styles":{"',
		);
	}

	/**
	 * Build a bounded user-prompt string from a context bundle / payload.
	 *
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Generation request (payload / context).
	 * @return string
	 */
	public static function format_user_prompt( $workflow_key, array $request ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		$payload      = isset( $request['payload'] ) && is_array( $request['payload'] ) ? $request['payload'] : $request;
		$slice        = self::whitelist_slice( $payload );

		$lines   = array();
		$lines[] = 'Workflow: ' . $workflow_key;
		$lines[] = 'Respond with JSON only matching the required schema.';
		$lines[] = 'Normalized page context (bounded):';
		$json    = wp_json_encode( $slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$lines[] = is_string( $json ) ? $json : '{}';

		$text = implode( "\n", $lines );
		return self::assert_no_raw_builder_document( $text );
	}

	/**
	 * Whitelist normalized facts for prompts.
	 *
	 * @param array<string, mixed> $payload Context payload.
	 * @return array<string, mixed>
	 */
	public static function whitelist_slice( array $payload ) {
		$out = array();

		foreach ( array( 'page_id', 'page_type', 'page_url', 'geo_target', 'analysis_focus', 'user_request', 'business_goal', 'analysis_run_id', 'analysis_summary' ) as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$out[ $key ] = $payload[ $key ];
			}
		}

		if ( ! empty( $payload['findings'] ) && is_array( $payload['findings'] ) ) {
			$out['findings'] = array_slice( $payload['findings'], 0, 12 );
		}
		if ( ! empty( $payload['selected_categories'] ) && is_array( $payload['selected_categories'] ) ) {
			$out['selected_categories'] = array_values( array_map( 'sanitize_key', $payload['selected_categories'] ) );
		}

		if ( ! empty( $payload['builder_context'] ) && is_array( $payload['builder_context'] ) ) {
			$out['builder_context'] = self::compact_builder_facts( $payload['builder_context'] );
		} elseif ( ! empty( $payload['page_context'] ) && is_array( $payload['page_context'] ) ) {
			$out['builder_context'] = self::compact_builder_facts( $payload['page_context'] );
		}

		if ( ! empty( $payload['intelligence'] ) && is_array( $payload['intelligence'] ) ) {
			$out['intelligence'] = self::bounded_assoc( $payload['intelligence'], 24 );
		}
		if ( ! empty( $payload['reading_context'] ) && is_array( $payload['reading_context'] ) ) {
			$out['reading_context'] = self::bounded_assoc( $payload['reading_context'], 20 );
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $ctx Builder context.
	 * @return array<string, mixed>
	 */
	private static function compact_builder_facts( array $ctx ) {
		$allowed_top = array(
			'builder',
			'builder_type',
			'page_id',
			'title',
			'nodes',
			'sections',
			'headings',
			'paragraphs',
			'ctas',
			'forms',
			'media',
			'images',
			'classes',
			'styles_summary',
			'variables',
			'interactions',
			'components',
			'custom_attributes',
			'unknown_widgets',
			'semantic_keys',
			'word_count',
			'has_form',
			'cta_count',
		);
		$out = array();
		foreach ( $allowed_top as $key ) {
			if ( ! array_key_exists( $key, $ctx ) ) {
				continue;
			}
			$value = $ctx[ $key ];
			if ( is_array( $value ) ) {
				$out[ $key ] = self::bounded_list_or_assoc( $value, 40 );
			} else {
				$out[ $key ] = $value;
			}
		}

		// Preserve common compact_for_api shapes under nested keys.
		if ( empty( $out ) && ! empty( $ctx ) ) {
			$out = self::bounded_assoc( $ctx, 48 );
		}

		return $out;
	}

	/**
	 * @param array<mixed> $value Value.
	 * @param int          $max   Max entries.
	 * @return array<mixed>
	 */
	private static function bounded_list_or_assoc( array $value, $max ) {
		if ( array_values( $value ) === $value ) {
			return array_slice( $value, 0, (int) $max );
		}
		return self::bounded_assoc( $value, $max );
	}

	/**
	 * @param array<string, mixed> $value Assoc.
	 * @param int                  $max   Max keys.
	 * @return array<string, mixed>
	 */
	private static function bounded_assoc( array $value, $max ) {
		$out   = array();
		$count = 0;
		foreach ( $value as $k => $v ) {
			if ( $count >= (int) $max ) {
				break;
			}
			$key = is_string( $k ) ? $k : (string) $k;
			if ( '_elementor_data' === $key || ( 'elements' === $key && self::looks_like_raw_document( $v ) ) ) {
				continue;
			}
			if ( is_array( $v ) ) {
				$out[ $key ] = self::bounded_list_or_assoc( $v, 24 );
			} else {
				$out[ $key ] = $v;
			}
			++$count;
		}
		return $out;
	}

	/**
	 * @param mixed $value Candidate document.
	 * @return bool
	 */
	private static function looks_like_raw_document( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		$json = wp_json_encode( $value );
		if ( ! is_string( $json ) ) {
			return false;
		}
		return false !== strpos( $json, '"elType"' ) && false !== strpos( $json, '"settings"' ) && strlen( $json ) > 4000;
	}

	/**
	 * Strip accidental raw markers; assert for tests.
	 *
	 * @param string $text Prompt text.
	 * @return string
	 */
	public static function assert_no_raw_builder_document( $text ) {
		$text = (string) $text;
		foreach ( self::forbidden_raw_markers() as $marker ) {
			if ( false !== strpos( $text, $marker ) ) {
				$text = str_replace( $marker, '[redacted-raw-builder]', $text );
			}
		}
		return $text;
	}

	/**
	 * Whether prompt text contains forbidden raw builder markers (for tests).
	 *
	 * @param string $text Prompt.
	 * @return bool
	 */
	public static function contains_raw_builder_document( $text ) {
		$text = (string) $text;
		foreach ( self::forbidden_raw_markers() as $marker ) {
			if ( false !== strpos( $text, $marker ) ) {
				return true;
			}
		}
		return false;
	}
}
