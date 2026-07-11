<?php
/**
 * Track and resolve inherited targets across multi-clause plans.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Inherited_Target_Resolver {

	/**
	 * @param string              $clause  Clause text.
	 * @param string              $phrase  Full phrase.
	 * @param array<string,mixed> $session Session state.
	 * @return array<string,mixed>|null
	 */
	public static function detect_named_target( $clause, $phrase, array $session = array() ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );

		if ( self::needs_pronoun_target( $clause ) && ! empty( $session['currentTarget'] ) ) {
			return self::mark_inherited( $session['currentTarget'] );
		}

		if ( preg_match( '/\b(?:same|the)\s+product\s+page\b/i', $clause ) && ! empty( $session['currentTarget'] ) ) {
			return self::mark_inherited( $session['currentTarget'] );
		}

		$category = self::extract_category_label( $clause );
		if ( null !== $category ) {
			return self::category_target( $category );
		}

		$product_page = self::extract_product_page_label( $clause );
		if ( null !== $product_page ) {
			if ( preg_match( '/\b(?:same|the)\s+product\s+page\b/i', $product_page ) && ! empty( $session['currentTarget'] ) ) {
				return $session['currentTarget'];
			}
			return self::product_page_target( $product_page );
		}

		if ( self::needs_pronoun_target( $clause ) ) {
			$phrase_category = self::extract_category_label( $phrase );
			if ( null !== $phrase_category ) {
				return self::category_target( $phrase_category );
			}
			$phrase_product = self::extract_product_page_label( $phrase );
			if ( null !== $phrase_product ) {
				return self::product_page_target( $phrase_product );
			}
		}

		return null;
	}

	/**
	 * Flag a target row as inherited from a previous action and record its origin.
	 *
	 * @param array<string,mixed> $target Target row from session state.
	 * @return array<string,mixed>
	 */
	public static function mark_inherited( array $target ) {
		$origin            = (string) ( $target['inheritedFrom'] ?? $target['label'] ?? '' );
		$target['source']  = 'inherited';
		$target['inheritedFrom'] = $origin;
		return $target;
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function needs_pronoun_target( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		return (bool) preg_match(
			'/^(?:but\s+)?(?:don\'t|do not)\s+show\s+it\b|\b(?:don\'t|do not)\s+show\s+it\s+to\b|\b(?:hide|show)\s+it\s+from\b|\bsame\s+(?:category(?:\s+page)?|product(?:\s+page)?|page)\b/i',
			$clause
		);
	}

	/**
	 * @param array<string,mixed>|null $target Target row.
	 * @return bool
	 */
	public static function is_named_target( $target ) {
		if ( ! is_array( $target ) ) {
			return false;
		}
		$label = strtolower( trim( (string) ( $target['label'] ?? '' ) ) );
		if ( '' === $label || in_array( $label, array( 'page', 'product', 'product page', 'category' ), true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $label Product page label.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	public static function product_page_target( $label ) {
		$label = trim( (string) $label );
		return array(
			'type'   => 'product_page',
			'label'  => $label,
			'slug'   => sanitize_title( $label ),
			'source' => 'detected',
		);
	}

	/**
	 * @param string $label Category label.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	public static function category_target( $label ) {
		$label = trim( (string) $label );
		$type  = preg_match( '/\bcategory\s+page$/i', $label ) ? 'category_page' : 'category';
		return array(
			'type'   => $type,
			'label'  => $label,
			'slug'   => sanitize_title( $label ),
			'source' => 'detected',
		);
	}

	/**
	 * @param array<string,mixed> $session Session.
	 * @param array<string,mixed> $target  Target row.
	 * @return array<string,mixed>
	 */
	public static function remember_target( array $session, array $target ) {
		if ( self::is_named_target( $target ) ) {
			$session['currentTarget'] = $target;
		}
		return $session;
	}

	/**
	 * @param string $text Clause or phrase text.
	 * @return string|null
	 */
	public static function extract_category_label( $text ) {
		$text = RWGA_Local_Intent_Interpreter::normalise( $text );
		$suffix = preg_match( '/\bcategory\s+page\b/i', $text ) ? ' category page' : ' category';
		if ( preg_match( '/\bthe\s+([\w\s-]+?)\s+category(?:\s+page)?\b/i', $text, $m ) ) {
			return trim( (string) $m[1] ) . $suffix;
		}
		if ( preg_match( '/\b([\w\s-]+?)\s+category(?:\s+page)?\b/i', $text, $m ) ) {
			$label = trim( (string) $m[1] );
			$label = preg_replace( '/^(?:show|hide|display|target|update|create|make)\s+(?:the\s+)?/i', '', $label );
			$label = preg_replace( '/^(?:the|a|an)\s+/i', '', $label );
			$label = trim( (string) $label );
			if ( '' === $label || preg_match( '/^(?:only|just)$/i', $label ) ) {
				return null;
			}
			return $label . $suffix;
		}
		return null;
	}

	/**
	 * @param string $text Clause or phrase text.
	 * @return string|null
	 */
	public static function extract_product_page_label( $text ) {
		$text = RWGA_Local_Intent_Interpreter::normalise( $text );
		if ( preg_match( '/\bvariant\s+of\s+(?:the\s+)?([\w\s-]+?)\s+product\s+page\b/i', $text, $m ) ) {
			return trim( (string) $m[1] ) . ' product page';
		}
		if ( preg_match( '/\bthe\s+([\w\s-]+?)\s+product\s+page\b/i', $text, $m ) ) {
			return trim( (string) $m[1] ) . ' product page';
		}
		if ( preg_match( '/\b([\w\s-]+?)\s+product\s+page\b/i', $text, $m ) ) {
			$label = trim( (string) $m[1] );
			$label = preg_replace( '/^(?:create|make|build|duplicate|copy|clone|a|an|the)\s+/i', '', $label );
			$label = preg_replace( '/^(?:variant|version)\s+of\s+(?:the\s+)?/i', '', $label );
			$label = trim( (string) $label );
			if ( '' === $label || preg_match( '/^(?:create|make|variant)$/i', $label ) ) {
				return null;
			}
			return $label . ' product page';
		}
		return null;
	}
}
