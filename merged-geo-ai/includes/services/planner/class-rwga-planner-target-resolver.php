<?php
/**
 * Resolve page, popup, and other targets per clause.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Target_Resolver {

	/**
	 * @param string              $clause  Clause text.
	 * @param string              $phrase  Full phrase for context.
	 * @param array<string,mixed> $context Resolver context.
	 * @param string              $action_type Action type.
	 * @param array<string,mixed> $clause_row  Clause metadata.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	public static function resolve( $clause, $phrase, array $context, $action_type, array $clause_row = array() ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		$session = is_array( $context['session'] ?? null ) ? $context['session'] : array();

		if ( RWGA_Geo_Action_Types::UPDATE_VARIANT === $action_type ) {
			$variant_target = self::existing_variant_target( $clause );
			if ( null !== $variant_target ) {
				return $variant_target;
			}
		}

		if ( RWGA_Geo_Action_Types::UPDATE_RULE === $action_type ) {
			$rule_target = self::existing_rule_target( $clause );
			if ( null !== $rule_target ) {
				return $rule_target;
			}
		}

		if ( class_exists( 'RWGA_Planner_Inherited_Target_Resolver', false ) ) {
			$inherited = RWGA_Planner_Inherited_Target_Resolver::detect_named_target( $clause, $phrase, $session );
			if ( is_array( $inherited ) ) {
				return $inherited;
			}
		}

		$rule_page = self::rule_creation_page_target( $clause );
		if ( is_array( $rule_page ) ) {
			return $rule_page;
		}

		$rule_popup = self::rule_creation_popup_target( $clause );
		if ( is_array( $rule_popup ) ) {
			return $rule_popup;
		}

		if ( class_exists( 'RWGA_Planner_Inherited_Target_Resolver', false ) ) {
			$category = RWGA_Planner_Inherited_Target_Resolver::extract_category_label( $clause );
			if ( null !== $category ) {
				return RWGA_Planner_Inherited_Target_Resolver::category_target( $category );
			}
			$product_page = RWGA_Planner_Inherited_Target_Resolver::extract_product_page_label( $clause );
			if ( null !== $product_page ) {
				return RWGA_Planner_Inherited_Target_Resolver::product_page_target( $product_page );
			}
		}

		if ( preg_match( '/\bbanner\b/i', $clause ) ) {
			$label = self::banner_label( $clause );
			return array(
				'type'   => 'banner',
				'label'  => $label,
				'slug'   => sanitize_title( $label ),
				'source' => 'detected',
			);
		}

		if ( preg_match( '/\bpopup\b/i', $clause ) ) {
			$label = self::popup_label( $clause );
			return array(
				'type'   => 'popup',
				'label'  => $label,
				'slug'   => sanitize_title( $label ),
				'source' => 'detected',
			);
		}

		if ( 'variant_child' === (string) ( $clause_row['type'] ?? '' )
			&& ! empty( $clause_row['parent'] )
			&& is_array( $clause_row['parent'] ) ) {
			$parent = $clause_row['parent'];
			return array(
				'type'   => 'page',
				'label'  => (string) ( $parent['sourcePageLabel'] ?? $parent['sourcePage'] ?? 'page' ),
				'slug'   => (string) ( $parent['sourcePage'] ?? 'page' ),
				'source' => 'parent_variant',
			);
		}

		$page_context = class_exists( 'RWGA_Variant_Plan_Parser', false )
			? RWGA_Variant_Plan_Parser::detect_page_context( $phrase )
			: array();

		if ( RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING === $action_type ) {
			$original = (string) ( $page_context['original_page'] ?? '' );
			if ( '' === $original && preg_match( '/\b(?:homepage|home\s+page|shop(?:\s+page)?|checkout|cart|pricing(?:\s+page)?)\b/i', $clause, $m ) ) {
				$original = self::normalise_page_token( (string) $m[0] );
			}
			if ( '' === $original && preg_match( '/\b(?:homepage|home\s+page)\b/i', $clause ) ) {
				$original = 'homepage';
			}
			if ( '' !== $original ) {
				return self::page_target( $original );
			}
		}

		if ( preg_match( '/\bvariants?\s+of\s+(?:the\s+)?(shop(?:\s+page)?|home\s+page|homepage|landing(?:\s+page)?|checkout|cart|pricing(?:\s+page)?|contact(?:\s+page)?)\b/i', $clause, $m ) ) {
			return self::page_target( self::normalise_page_token( (string) $m[1] ) );
		}

		if ( class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$page = RWGA_Page_Reference_Resolver::detect( $clause );
			if ( is_array( $page ) && ! empty( $page['value'] ) ) {
				return self::page_target( (string) $page['value'] );
			}
		}

		$variant_source = (string) ( $page_context['variant_source'] ?? '' );
		$is_variant     = in_array( $action_type, array( RWGA_Geo_Action_Types::CREATE_VARIANT, RWGA_Geo_Action_Types::UPDATE_VARIANT ), true );
		$generic_source = in_array(
			strtolower( trim( $variant_source ) ),
			array( '', 'page', 'category', 'category page', 'product', 'product page' ),
			true
		);

		// A specific variant source ("shop", "homepage") wins outright.
		if ( $is_variant && '' !== $variant_source && ! $generic_source ) {
			return self::page_target( $variant_source );
		}

		// Variant segments such as "one for mobile users in Finland" carry no
		// target of their own and the phrase only yields a generic token (e.g.
		// "category" from "the same category page"). Inherit the previous named
		// target so the dependency stays linked and is blocked until resolved.
		if ( $is_variant
			&& class_exists( 'RWGA_Planner_Inherited_Target_Resolver', false )
			&& RWGA_Planner_Inherited_Target_Resolver::is_named_target( $session['currentTarget'] ?? null ) ) {
			return RWGA_Planner_Inherited_Target_Resolver::mark_inherited( $session['currentTarget'] );
		}

		if ( $is_variant && '' !== $variant_source ) {
			return self::page_target( $variant_source );
		}

		if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $action_type
			|| RWGA_Geo_Action_Types::UPDATE_VARIANT === $action_type ) {
			if ( '' !== $variant_source ) {
				return self::page_target( $variant_source );
			}
			if ( preg_match( '/\b(?:homepage|home\s+page|shop(?:\s+page)?|landing(?:\s+page)?|pricing(?:\s+page)?|contact(?:\s+page)?)\b/i', $phrase, $m ) ) {
				return self::page_target( self::normalise_page_token( (string) $m[0] ) );
			}
			return array(
				'type'   => 'page',
				'label'  => 'page',
				'slug'   => 'page',
				'source' => 'unknown',
			);
		}

		if ( preg_match( '/\b(?:homepage|home\s+page|shop(?:\s+page)?|landing(?:\s+page)?|pricing(?:\s+page)?|contact(?:\s+page)?)\b/i', $clause, $m ) ) {
			return self::page_target( self::normalise_page_token( (string) $m[0] ) );
		}

		return array(
			'type'   => 'page',
			'label'  => 'page',
			'slug'   => 'page',
			'source' => 'unknown',
		);
	}

	/**
	 * @param string $clause Clause text.
	 * @return array{type:string,label:string,slug:string,source:string}|null
	 */
	private static function rule_creation_page_target( $clause ) {
		if ( preg_match( '/\b(?:create|add)\s+(?:a\s+)?rule\s+for\s+(?:the\s+)?(.+?)\s+page\b/i', $clause, $m ) ) {
			return self::named_page_target( trim( (string) $m[1] ) . ' page' );
		}
		if ( preg_match( '/\b(?:show|display)\s+(?:the\s+)?(.+?)\s+page\s+to\b/i', $clause, $m ) ) {
			return self::named_page_target( trim( (string) $m[1] ) . ' page' );
		}
		return null;
	}

	/**
	 * @param string $clause Clause text.
	 * @return array{type:string,label:string,slug:string,source:string}|null
	 */
	private static function rule_creation_popup_target( $clause ) {
		$patterns = array(
			'/\b(?:create|add|set\s+up|build)\s+(?:a\s+)?(?:targeting\s+)?rule\s+for\s+(?:the\s+)?(.+?)\s+popup\b/i',
			'/\b(?:popup\s+rule|targeting\s+rule)\s+for\s+(?:the\s+)?(.+?)\s+popup\b/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $clause, $m ) ) {
				return self::named_popup_target( trim( (string) ( $m[1] ?? '' ) ) );
			}
		}
		return null;
	}

	/**
	 * @param string $label Popup name without the "popup" suffix.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	private static function named_popup_target( $label ) {
		$label = self::strip_rule_target_preamble( $label );
		$label = self::title_case_label( $label );
		if ( '' === $label ) {
			$label = 'popup';
		}
		$full = preg_match( '/\bpopup$/i', $label ) ? (string) preg_replace( '/\bPopup$/', 'popup', $label ) : $label . ' popup';
		return array(
			'type'   => 'popup',
			'label'  => $full,
			'slug'   => sanitize_title( $full ),
			'source' => 'detected',
		);
	}

	/**
	 * @param string $label Page label.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	private static function named_page_target( $label ) {
		$label = trim( (string) $label );
		return array(
			'type'   => 'page',
			'label'  => $label,
			'slug'   => sanitize_title( $label ),
			'source' => 'detected',
		);
	}

	/**
	 * Resolve an existing/named variant target ("the existing Christmas homepage variant").
	 *
	 * @param string $clause Clause text.
	 * @return array{type:string,label:string,slug:string,sourcePage:string,source:string}|null
	 */
	private static function existing_variant_target( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		if ( ! preg_match( '/\b(?:the\s+)?(?:existing\s+)?([\w\s-]+?)\s+variant\b/i', $clause, $m ) ) {
			return null;
		}

		$name = trim( (string) $m[1] );
		$prev = '';
		while ( $prev !== $name ) {
			$prev = $name;
			$name = trim( (string) preg_replace( '/^(?:update|change|edit|modify|tweak|adjust|the|existing|a|an|new)\s+/i', '', $name ) );
		}
		$name = trim( (string) preg_replace( '/\s+/', ' ', $name ) );
		if ( '' === $name ) {
			return null;
		}

		$label = $name . ' variant';
		return array(
			'type'       => 'variant',
			'label'      => $label,
			'slug'       => sanitize_title( $label ),
			'sourcePage' => self::variant_source_page( $name ),
			'source'     => 'existing_variant',
		);
	}

	/**
	 * Resolve an existing/named rule target ("the existing VIP discount rule").
	 *
	 * @param string $clause Clause text.
	 * @return array{type:string,label:string,slug:string,source:string}|null
	 */
	private static function existing_rule_target( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		if ( ! preg_match( '/\b(?:the\s+)?(?:existing\s+)?([\w\s-]+?)\s+rule\b/i', $clause, $m ) ) {
			return null;
		}

		$name = trim( (string) $m[1] );
		$prev = '';
		while ( $prev !== $name ) {
			$prev = $name;
			$name = trim( (string) preg_replace( '/^(?:update|change|edit|modify|tweak|adjust|the|existing|a|an|new)\s+/i', '', $name ) );
		}
		$name = trim( (string) preg_replace( '/\s+/', ' ', $name ) );
		if ( '' === $name ) {
			return null;
		}

		$label = $name . ' rule';
		return array(
			'type'   => 'rule',
			'label'  => $label,
			'slug'   => sanitize_title( $label ),
			'source' => 'existing_rule',
		);
	}

	/**
	 * @param string $name Variant name.
	 * @return string
	 */
	private static function variant_source_page( $name ) {
		$name = strtolower( (string) $name );
		$map  = array(
			'homepage' => 'homepage',
			'home page' => 'homepage',
			'checkout' => 'checkout',
			'cart'     => 'cart',
			'basket'   => 'cart',
			'shop'     => 'shop',
			'landing'  => 'landing',
			'pricing'  => 'pricing',
			'contact'  => 'contact',
			'product'  => 'product',
		);
		foreach ( $map as $token => $slug ) {
			if ( false !== strpos( $name, $token ) ) {
				return $slug;
			}
		}
		return '';
	}

	/**
	 * @param string $clause Clause text.
	 * @return string
	 */
	private static function banner_label( $clause ) {
		if ( preg_match( '/\b(free[-\s]?shipping)\s+banner\b/i', $clause, $m ) ) {
			return 'free-shipping banner';
		}
		if ( preg_match( '/\b(black friday)\s+banner\b/i', $clause, $m ) ) {
			return trim( (string) $m[1] ) . ' banner';
		}
		if ( preg_match( '/\b(?:the|a|an)\s+([\w\s-]+?)\s+banner\b/i', $clause, $m ) ) {
			return trim( (string) $m[1] ) . ' banner';
		}
		return 'banner';
	}

	/**
	 * @param string $clause Clause text.
	 * @return string
	 */
	private static function popup_label( $clause ) {
		if ( preg_match( '/\b((?:winter|summer|spring|autumn|fall)\s+promo)\s+popup\b/i', $clause, $m ) ) {
			return trim( (string) $m[1] ) . ' popup';
		}
		if ( preg_match( '/\bpromo\s+popup\b/i', $clause ) ) {
			return 'promo popup';
		}
		if ( preg_match( '/\b(summer|winter|spring|autumn|fall)\s+popup\b/i', $clause, $m ) ) {
			return trim( (string) $m[1] ) . ' popup';
		}
		if ( preg_match( '/\b(?:the|a|an)\s+([\w\s-]+?)\s+popup\b/i', $clause, $m ) ) {
			$inner = self::strip_rule_target_preamble( trim( (string) $m[1] ) );
			if ( '' !== $inner && ! preg_match( '/^(?:targeting\s+)?rule\b/i', $inner ) ) {
				return self::title_case_label( $inner ) . ' popup';
			}
		}
		return 'popup';
	}

	/**
	 * Clean a detected target label (strip rule-creation preamble, title-case popups).
	 *
	 * @param string $label Detected label.
	 * @param string $type  Target type (popup, banner, page, …).
	 * @return string
	 */
	public static function clean_detected_label( $label, $type = '' ) {
		$label = self::strip_rule_target_preamble( (string) $label );
		$type  = (string) $type;
		if ( in_array( $type, array( 'popup', 'banner' ), true ) && '' !== $label ) {
			$label = self::title_case_label( $label );
			if ( 'popup' === $type && ! preg_match( '/\bpopup$/i', $label ) ) {
				$label .= ' popup';
			} elseif ( 'banner' === $type && ! preg_match( '/\bbanner$/i', $label ) ) {
				$label .= ' banner';
			} else {
				$label = (string) preg_replace( '/\bPopup$/', 'popup', $label );
				$label = (string) preg_replace( '/\bBanner$/', 'banner', $label );
			}
		}
		return trim( $label );
	}

	/**
	 * Remove leading rule-creation words accidentally captured as the target name.
	 *
	 * @param string $label Raw label fragment.
	 * @return string
	 */
	private static function strip_rule_target_preamble( $label ) {
		$label = trim( (string) $label );
		$patterns = array(
			'/^(?:create|add|set\s+up|build)\s+(?:a\s+)?(?:targeting\s+)?rule\s+for\s+(?:the\s+)?/i',
			'/^(?:targeting\s+)?rule\s+for\s+(?:the\s+)?/i',
			'/^(?:popup\s+rule|targeting\s+rule)\s+for\s+(?:the\s+)?/i',
			'/^(?:show|display)\s+(?:a\s+)?(?:targeting\s+)?rule\s+for\s+(?:the\s+)?/i',
		);
		foreach ( $patterns as $pattern ) {
			$label = trim( (string) preg_replace( $pattern, '', $label ) );
		}
		return $label;
	}

	/**
	 * @param string $label Words to title-case.
	 * @return string
	 */
	private static function title_case_label( $label ) {
		$label = trim( (string) $label );
		if ( '' === $label ) {
			return '';
		}
		$parts = preg_split( '/\s+/', strtolower( $label ) );
		if ( ! is_array( $parts ) ) {
			return ucfirst( strtolower( $label ) );
		}
		return implode( ' ', array_map( 'ucfirst', $parts ) );
	}

	/**
	 * @param string $token Page token.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	private static function page_target( $token ) {
		$slug  = self::normalise_page_token( $token );
		$label = $slug;
		if ( 'shop' === $slug ) {
			$label = 'shop';
		} elseif ( 'homepage' === $slug ) {
			$label = 'homepage';
		} elseif ( 'pricing' === $slug ) {
			$label = 'pricing page';
		} elseif ( 'contact' === $slug ) {
			$label = 'contact page';
		} elseif ( 'landing' === $slug ) {
			$label = 'landing page';
		} elseif ( 'checkout' === $slug ) {
			$label = 'checkout page';
		} elseif ( 'cart' === $slug ) {
			$label = 'cart page';
		} elseif ( preg_match( '/\s+page$/', $token ) ) {
			$label = $token;
		}
		return array(
			'type'   => 'page',
			'label'  => $label,
			'slug'   => $slug,
			'source' => 'detected',
		);
	}

	/**
	 * @param string $token Raw token.
	 * @return string
	 */
	private static function normalise_page_token( $token ) {
		$token = strtolower( trim( (string) $token ) );
		if ( in_array( $token, array( 'shop', 'shop page' ), true ) ) {
			return 'shop';
		}
		if ( in_array( $token, array( 'homepage', 'home page', 'home' ), true ) ) {
			return 'homepage';
		}
		if ( in_array( $token, array( 'pricing', 'pricing page' ), true ) ) {
			return 'pricing';
		}
		if ( in_array( $token, array( 'contact', 'contact page' ), true ) ) {
			return 'contact';
		}
		if ( in_array( $token, array( 'landing', 'landing page' ), true ) ) {
			return 'landing';
		}
		return $token;
	}
}
