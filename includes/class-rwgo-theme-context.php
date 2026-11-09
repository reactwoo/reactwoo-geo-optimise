<?php
/**
 * Assignment-driven Elementor locations for an existing experiment.
 *
 * Page variants stay on the site-wide page map. Header, footer, and single
 * (product body) templates are Elementor locations. Elementor Pro walks every
 * template in a location, passes its ID through
 * `elementor/theme/get_location_templates/template_id`, then records that
 * template's include and exclude rules against the ID the filter returned.
 * Rewriting the control template's ID to the variant ID copies those excludes
 * onto the variant and removes it, so the theme prints its fallback and only
 * the variant CSS was queued. The variant arm skips the control template
 * instead, and queues the variant template on the header and footer locations
 * so it prints even when its own display conditions do not match the URL.
 * Product bodies still swap the template Elementor already matched. Woo
 * products are not copied. Elementor Pro older than 3.9 does not pass the
 * location into the filter.
 *
 * Draft and private variant pages are not published and are not served.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure decisions for theme chrome, preview, measurement, and redirect safety.
 */
class RWGO_Theme_Context {

	const PREVIEW_ACTION = 'rwgo_preview';

	/**
	 * @var bool
	 */
	private static $preview = false;

	/**
	 * Stops document asset enqueue from re-entering the location filter.
	 *
	 * @var bool
	 */
	private static $enqueueing_runtime = false;

	/**
	 * Elementor Theme Builder location for a single product or post body.
	 */
	const LOCATION_SINGLE = 'single';

	/**
	 * @var array<string, mixed>|null
	 */
	private static $plan = null;

	/**
	 * Variant template ID => source template ID. Used when no arm is being served.
	 *
	 * @var array<string, mixed>
	 */
	private static $guard = array();

	/**
	 * @return array<string, array<string, int>>
	 */
	public static function empty_context() {
		return array(
			'control' => array(
				'header' => 0,
				'footer' => 0,
			),
			'variant' => array(
				'header' => 0,
				'footer' => 0,
			),
		);
	}

	/**
	 * @param mixed $raw Stored or posted theme context.
	 * @return array<string, array<string, int>>
	 */
	public static function normalize( $raw ) {
		$out = self::empty_context();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( array( 'control', 'variant' ) as $arm ) {
			if ( empty( $raw[ $arm ] ) || ! is_array( $raw[ $arm ] ) ) {
				continue;
			}
			foreach ( array( 'header', 'footer' ) as $location ) {
				$out[ $arm ][ $location ] = max( 0, (int) ( $raw[ $arm ][ $location ] ?? 0 ) );
			}
		}
		return $out;
	}

	/**
	 * Locations whose printed template follows a source-to-variant map.
	 *
	 * `single` is the Theme Builder location Elementor uses for a product
	 * body. `product` is accepted as that same location.
	 *
	 * @return array<int, string>
	 */
	public static function body_locations() {
		return array( self::LOCATION_SINGLE );
	}

	/**
	 * @param mixed $raw Rows from config or a proposal.
	 * @return array<int, array{location:string,source_template_id:int,variant_template_id:int}>
	 */
	public static function normalize_template_map( $raw ) {
		$out  = array();
		$seen = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$location = self::canonical_body_location( $row['location'] ?? '' );
			$source   = max( 0, (int) ( $row['source_template_id'] ?? 0 ) );
			$variant  = max( 0, (int) ( $row['variant_template_id'] ?? 0 ) );
			if ( '' === $location || $source <= 0 || $variant <= 0 || $source === $variant ) {
				continue;
			}
			$key = $location . ':' . $source;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = array(
				'location'            => $location,
				'source_template_id'  => $source,
				'variant_template_id' => $variant,
			);
		}
		return $out;
	}

	/**
	 * @param string $text Admin textarea. One `location,source,variant` line.
	 * @return array<int, array{location:string,source_template_id:int,variant_template_id:int}>
	 */
	public static function parse_template_map_text( $text ) {
		return self::normalize_template_map( self::template_map_rows_from_text( $text ) );
	}

	/**
	 * Rows as typed, including rows validation still has to reject.
	 *
	 * @param string $text Textarea.
	 * @return array<int, array<string, mixed>>
	 */
	public static function template_map_rows_from_text( $text ) {
		$rows = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
				continue;
			}
			$parts  = array_map( 'trim', explode( ',', $line ) );
			$rows[] = array(
				'location'            => $parts[0] ?? '',
				'source_template_id'  => $parts[1] ?? 0,
				'variant_template_id' => $parts[2] ?? 0,
			);
		}
		return $rows;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Normalized rows.
	 * @return string
	 */
	public static function template_map_text( array $rows ) {
		$lines = array();
		foreach ( self::normalize_template_map( $rows ) as $row ) {
			$lines[] = $row['location'] . ',' . $row['source_template_id'] . ',' . $row['variant_template_id'];
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param mixed $location Raw location.
	 * @return string single or empty.
	 */
	public static function canonical_body_location( $location ) {
		$location = sanitize_key( (string) $location );
		if ( in_array( $location, array( 'product', 'single-product' ), true ) ) {
			$location = self::LOCATION_SINGLE;
		}
		return in_array( $location, self::body_locations(), true ) ? $location : '';
	}

	/**
	 * Elementor template types that belong on the single/product body location.
	 *
	 * @return array<int, string>
	 */
	public static function single_template_types() {
		return array( 'single', 'single-post', 'single-page', 'product', 'single-product' );
	}

	/**
	 * @param string $template_type `_elementor_template_type`.
	 * @param string $location      Canonical location.
	 * @return bool True when the type is known and does not belong on this location.
	 */
	public static function template_type_conflicts( $template_type, $location ) {
		$type     = sanitize_key( (string) $template_type );
		$location = self::canonical_body_location( $location );
		if ( '' === $type || '' === $location ) {
			return false;
		}
		if ( self::LOCATION_SINGLE === $location ) {
			return ! in_array( $type, self::single_template_types(), true );
		}
		return false;
	}

	/**
	 * Elementor stores Theme Builder rules in `_elementor_conditions` and in
	 * the option `elementor_pro_theme_builder_conditions`.
	 *
	 * @param mixed $raw Meta value or one cache bucket.
	 * @return array<int, string>
	 */
	public static function condition_rules_from_meta( $raw ) {
		if ( is_string( $raw ) ) {
			$trimmed = trim( $raw );
			if ( '' === $trimmed ) {
				return array();
			}
			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			} else {
				$raw = array( $trimmed );
			}
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$rules = array();
		foreach ( $raw as $rule ) {
			if ( ! is_string( $rule ) ) {
				continue;
			}
			$rule = trim( $rule );
			if ( '' !== $rule ) {
				$rules[] = $rule;
			}
		}
		return $rules;
	}

	/**
	 * A template with any saved rule is eligible for Elementor's own matcher.
	 *
	 * @param mixed $raw Rules.
	 * @return bool
	 */
	public static function template_is_independently_matched( $raw ) {
		return array() !== self::condition_rules_from_meta( $raw );
	}

	/**
	 * Templates Elementor can select for a location without this plugin.
	 *
	 * `Locations_Manager` asks the conditions manager for documents that have
	 * rules, then uses the first document (`key()` / `reset()`). A published
	 * library template with no rules is not in that list. Two templates that
	 * share a rule can both match, and the first one is printed. Putting the
	 * variant first is the leak this guard is for.
	 *
	 * @param array<string, mixed> $cache    Location => template ID => rules.
	 * @param string               $location Theme Builder location.
	 * @return array<int, int> Candidate IDs in cache order.
	 */
	public static function elementor_location_candidates( array $cache, $location ) {
		$location = sanitize_key( (string) $location );
		if ( 'product' === $location || 'single-product' === $location ) {
			$location = self::LOCATION_SINGLE;
		}
		$bucket = array();
		if ( isset( $cache[ $location ] ) && is_array( $cache[ $location ] ) ) {
			$bucket = $cache[ $location ];
		}
		$ids = array();
		foreach ( $bucket as $template_id => $rules ) {
			if ( ! self::template_is_independently_matched( $rules ) ) {
				continue;
			}
			$id = (int) $template_id;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * @param array<int, int> $candidates Ordered condition matches.
	 * @return int First ID Elementor prints, or 0 when nothing matches.
	 */
	public static function elementor_first_candidate( array $candidates ) {
		foreach ( $candidates as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * @param array<string, mixed> $cfg Stored config.
	 * @return array{restore:array<int, int>,map:array<int, array<string, int|string>>}
	 */
	public static function condition_guard_from_config( array $cfg ) {
		$restore = array();
		$map     = self::normalize_template_map( isset( $cfg['template_map'] ) ? $cfg['template_map'] : array() );
		foreach ( $map as $row ) {
			$restore[ (int) $row['variant_template_id'] ] = (int) $row['source_template_id'];
		}
		$theme = self::normalize( isset( $cfg['theme_context'] ) ? $cfg['theme_context'] : array() );
		foreach ( array( 'header', 'footer' ) as $location ) {
			$variant = (int) $theme['variant'][ $location ];
			if ( $variant > 0 ) {
				$restore[ $variant ] = (int) $theme['control'][ $location ];
			}
		}
		return array(
			'restore' => $restore,
			'map'     => $map,
		);
	}

	/**
	 * @param array<string, mixed> $guard Guard from condition_guard_from_config().
	 * @return void
	 */
	public static function set_condition_guard( array $guard ) {
		self::$guard = $guard;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function condition_guard() {
		return self::$guard;
	}

	/**
	 * When this response is not serving the variant arm, a variant template
	 * Elementor already chose is replaced with its source. Zero hides the
	 * location rather than printing the variant.
	 *
	 * @param int                  $elementor_id ID from the condition matcher.
	 * @param array<string, mixed> $guard        Restore map.
	 * @param bool                 $allow_variant This response may print variants.
	 * @return int
	 */
	public static function restore_unserved_template( $elementor_id, array $guard, $allow_variant ) {
		$elementor_id = (int) $elementor_id;
		if ( $allow_variant || $elementor_id <= 0 ) {
			return $elementor_id;
		}
		$restore = isset( $guard['restore'] ) && is_array( $guard['restore'] ) ? $guard['restore'] : array();
		if ( ! array_key_exists( $elementor_id, $restore ) ) {
			return $elementor_id;
		}
		return max( 0, (int) $restore[ $elementor_id ] );
	}

	/**
	 * Elementor Pro owns theme locations. Elementor free can store
	 * the library post, and it does not print those locations.
	 *
	 * @return bool
	 */
	public static function elementor_pro_locations_loaded() {
		return class_exists( '\ElementorPro\Modules\ThemeBuilder\Module', false )
			|| class_exists( '\ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager', false );
	}

	/**
	 * @param array<string, mixed> $theme Theme context.
	 * @return bool
	 */
	public static function theme_split_configured( $theme ) {
		$theme = self::normalize( $theme );
		foreach ( array( 'control', 'variant' ) as $arm ) {
			foreach ( array( 'header', 'footer' ) as $location ) {
				if ( $theme[ $arm ][ $location ] > 0 ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Header and footer post IDs referenced by a config.
	 *
	 * @param array<string, mixed> $cfg Config.
	 * @return array<int, int>
	 */
	public static function referenced_post_ids( array $cfg ) {
		$theme = self::normalize( isset( $cfg['theme_context'] ) ? $cfg['theme_context'] : array() );
		$ids   = array();
		foreach ( $theme as $arm ) {
			foreach ( $arm as $id ) {
				$id = (int) $id;
				if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
		foreach ( self::normalize_template_map( isset( $cfg['template_map'] ) ? $cfg['template_map'] : array() ) as $row ) {
			foreach ( array( 'source_template_id', 'variant_template_id' ) as $key ) {
				$id = (int) $row[ $key ];
				if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
		return $ids;
	}

	/**
	 * @return array<int, string>
	 */
	public static function template_post_types() {
		$types = array( 'elementor_library' );
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgo_theme_template_post_types', $types );
			if ( is_array( $filtered ) && ! empty( $filtered ) ) {
				$types = array_values( array_map( 'strval', $filtered ) );
			}
		}
		return $types;
	}

	/**
	 * A template can be printed only when it is a published Elementor library document.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<int, string>   $status  Post ID => status.
	 * @param array<int, string>   $types   Post ID => post type.
	 * @return int Usable ID, or 0.
	 */
	public static function usable_template_id( $post_id, array $status, array $types ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return 0;
		}
		$post_status = isset( $status[ $post_id ] ) ? sanitize_key( (string) $status[ $post_id ] ) : '';
		$post_type   = isset( $types[ $post_id ] ) ? sanitize_key( (string) $types[ $post_id ] ) : '';
		if ( 'publish' !== $post_status ) {
			return 0;
		}
		if ( ! in_array( $post_type, self::template_post_types(), true ) ) {
			return 0;
		}
		return $post_id;
	}

	/**
	 * Cart, checkout, and account never take variant chrome. Other system pages
	 * stay on control chrome when coverage already excludes them.
	 *
	 * @param string             $slug             Page slug.
	 * @param int                $page_id          Page ID.
	 * @param array<int, int>    $transaction_ids  WooCommerce cart, checkout, and account IDs.
	 * @param bool               $exclude_system   Coverage excludes system pages.
	 * @param array<int, int>    $system_ids       Reserved system page IDs.
	 * @param array<int, string> $system_slugs     Reserved slugs.
	 * @return bool
	 */
	public static function variant_theme_locked( $slug, $page_id, array $transaction_ids, $exclude_system, array $system_ids, array $system_slugs ) {
		$page_id = (int) $page_id;
		$slug    = self::normalize_slug( $slug );
		foreach ( $transaction_ids as $id ) {
			if ( $page_id > 0 && $page_id === (int) $id ) {
				return true;
			}
		}
		if ( in_array( $slug, array( 'cart', 'checkout', 'my-account' ), true ) ) {
			return true;
		}
		if ( ! $exclude_system ) {
			return false;
		}
		foreach ( $system_ids as $id ) {
			if ( $page_id > 0 && $page_id === (int) $id ) {
				return true;
			}
		}
		return in_array( $slug, array_map( array( __CLASS__, 'normalize_slug' ), $system_slugs ), true );
	}

	/**
	 * What this response is allowed to show, independent of Elementor conditions.
	 *
	 * The assignment cookie is not rewritten here. An unpublished variant page
	 * keeps the visitor on the control body. A published variant header or footer
	 * can still be the delivered experience.
	 *
	 * @param string               $assigned_arm    control|variant.
	 * @param int                  $variant_page_id Variant page for this URL, or 0.
	 * @param string               $variant_status  Post status.
	 * @param bool                 $theme_locked    Commerce or excluded system page.
	 * @param array<string, mixed> $theme           Theme context.
	 * @param array<int, string>   $template_status Template ID => status.
	 * @param array<int, string>   $template_type   Template ID => post type.
	 * @param bool                 $locations_available Elementor Pro locations are loaded.
	 * @param array<int, mixed>    $template_map    Source-to-variant body templates.
	 * @param array<int, bool>     $template_empty  Template ID => Elementor document is empty.
	 * @return array<string, mixed>
	 */
	public static function delivery_plan( $assigned_arm, $variant_page_id, $variant_status, $theme_locked, $theme, array $template_status, array $template_type, $locations_available = true, array $template_map = array(), array $template_empty = array() ) {
		$assigned        = in_array( $assigned_arm, array( 'control', 'variant' ), true ) ? $assigned_arm : 'control';
		$theme           = self::normalize( $theme );
		$variant_page_id = (int) $variant_page_id;
		$owned           = array();
		foreach ( array( 'header', 'footer' ) as $location ) {
			$id = (int) $theme['variant'][ $location ];
			if ( $id > 0 ) {
				$owned[] = $id;
			}
		}
		$plan = array(
			'assigned'                  => $assigned,
			'body'                      => 'control',
			'delivered'                 => 'control',
			'swap_page_id'              => 0,
			'header'                    => 0,
			'footer'                    => 0,
			'allow_variant_templates'   => false,
			'owned_variant_template_ids'=> $owned,
			'template_map'              => array(),
			'usable_template_ids'       => array(),
			'preload_template_ids'      => array(),
			'suppress_template_ids'     => array(),
			'reasons'                   => array(),
		);
		if ( $theme_locked ) {
			$plan['reasons'][] = 'theme_locked';
			$body = self::body_template_plan( 'control', $template_map, $template_status, $template_type, $template_empty );
			$plan['template_map']        = $body['template_map'];
			$plan['usable_template_ids'] = $body['usable_template_ids'];
			$plan['owned_variant_template_ids'] = array_values(
				array_unique( array_merge( $owned, $body['owned_variant_template_ids'] ) )
			);
			$plan['preload_template_ids'] = array();
			$plan['allow_variant_templates'] = false;
			return $plan;
		}
		$arm = $assigned;
		if ( 'variant' === $assigned ) {
			$published = $variant_page_id > 0 && 'publish' === sanitize_key( (string) $variant_status );
			if ( $published ) {
				$plan['body']         = 'variant';
				$plan['swap_page_id'] = $variant_page_id;
			} else {
				$plan['reasons'][] = 'unpublished_variant_page';
			}
		}
		foreach ( array( 'header', 'footer' ) as $location ) {
			$plan[ $location ] = self::usable_template_id( (int) $theme[ $arm ][ $location ], $template_status, $template_type );
			$configured        = (int) $theme[ $arm ][ $location ];
			if ( $configured > 0 && 0 === (int) $plan[ $location ] ) {
				$plan['reasons'][] = 'theme_template_unavailable';
			}
		}
		if ( ! $locations_available ) {
			$plan['header']                  = 0;
			$plan['footer']                  = 0;
			$plan['allow_variant_templates'] = false;
			$plan['reasons'][]               = 'elementor_pro_locations_missing';
			$plan['delivered']               = ( 'variant' === $plan['body'] ) ? 'variant' : 'control';
			return $plan;
		}
		$body_templates = self::body_template_plan( $assigned, $template_map, $template_status, $template_type, $template_empty );
		$plan['template_map']         = $body_templates['template_map'];
		$plan['usable_template_ids']  = $body_templates['usable_template_ids'];
		$plan['preload_template_ids'] = $body_templates['preload_template_ids'];
		$plan['owned_variant_template_ids'] = array_values(
			array_unique(
				array_merge( $owned, $body_templates['owned_variant_template_ids'] )
			)
		);
		foreach ( $body_templates['reasons'] as $reason ) {
			$plan['reasons'][] = $reason;
		}
		$suppress = array();
		if ( 'variant' === $assigned ) {
			foreach ( array( 'header', 'footer' ) as $location ) {
				$control_id = (int) $theme['control'][ $location ];
				$printed    = (int) $plan[ $location ];
				if ( $control_id > 0 && $printed > 0 && $control_id !== $printed ) {
					$suppress[] = $control_id;
				}
			}
		}
		$plan['suppress_template_ids'] = array_values( array_unique( $suppress ) );
		$chrome = ( (int) $plan['header'] > 0 || (int) $plan['footer'] > 0 );
		if ( 'variant' === $assigned && ( 'variant' === $plan['body'] || $chrome || ! empty( $plan['preload_template_ids'] ) ) ) {
			$plan['delivered']               = 'variant';
			$plan['allow_variant_templates'] = true;
		}
		return $plan;
	}

	/**
	 * Usable single/product templates for this arm. Unpublished variants stay
	 * out of the preload list so the source template keeps printing.
	 *
	 * @param string             $assigned        control|variant.
	 * @param array<int, mixed>  $template_map    Raw or normalized rows.
	 * @param array<int, string> $template_status Template ID => status.
	 * @param array<int, string> $template_type   Template ID => post type.
	 * @param array<int, bool>   $template_empty  Template ID => empty document.
	 * @return array{template_map:array<int,array<string,int|string>>,usable_template_ids:array<int,int>,preload_template_ids:array<int,int>,owned_variant_template_ids:array<int,int>,reasons:array<int,string>}
	 */
	public static function body_template_plan( $assigned, array $template_map, array $template_status, array $template_type, array $template_empty = array() ) {
		$map     = self::normalize_template_map( $template_map );
		$usable  = array();
		$preload = array();
		$owned   = array();
		$reasons = array();
		foreach ( $map as $row ) {
			$source  = (int) $row['source_template_id'];
			$variant = (int) $row['variant_template_id'];
			$owned[] = $variant;
			$source_ok  = self::printable_template_id( $source, $template_status, $template_type, $template_empty );
			$variant_ok = self::printable_template_id( $variant, $template_status, $template_type, $template_empty );
			if ( $source_ok > 0 ) {
				$usable[] = $source_ok;
			}
			if ( $variant_ok > 0 ) {
				$usable[] = $variant_ok;
			} else {
				$reasons[] = 'template_variant_unavailable';
			}
			if ( 'variant' === $assigned && $variant_ok > 0 ) {
				$preload[] = $variant_ok;
			}
			if ( 'control' === $assigned && $source_ok > 0 ) {
				$preload[] = $source_ok;
			}
		}
		return array(
			'template_map'               => $map,
			'usable_template_ids'        => array_values( array_unique( $usable ) ),
			'preload_template_ids'       => array_values( array_unique( $preload ) ),
			'owned_variant_template_ids' => array_values( array_unique( $owned ) ),
			'reasons'                    => $reasons,
		);
	}

	/**
	 * @param int                $post_id ID.
	 * @param array<int, string> $status  Status map.
	 * @param array<int, string> $types   Type map.
	 * @param array<int, bool>   $empty   Empty map.
	 * @return int
	 */
	public static function printable_template_id( $post_id, array $status, array $types, array $empty ) {
		$post_id = self::usable_template_id( $post_id, $status, $types );
		if ( $post_id <= 0 || ! empty( $empty[ $post_id ] ) ) {
			return 0;
		}
		return $post_id;
	}

	/**
	 * True when at least one mapped variant page or variant template can be shown.
	 *
	 * @param array<int, string>   $variant_page_status Variant page ID => status.
	 * @param array<string, mixed> $theme               Theme context.
	 * @param array<int, string>   $template_status     Template ID => status.
	 * @param array<int, string>   $template_type       Template ID => post type.
	 * @return bool
	 */
	public static function experience_is_deliverable( array $variant_page_status, $theme, array $template_status, array $template_type, array $template_map = array(), array $template_empty = array() ) {
		foreach ( $variant_page_status as $status ) {
			if ( 'publish' === sanitize_key( (string) $status ) ) {
				return true;
			}
		}
		$theme = self::normalize( $theme );
		foreach ( array( 'header', 'footer' ) as $location ) {
			if ( self::usable_template_id( (int) $theme['variant'][ $location ], $template_status, $template_type ) > 0 ) {
				return true;
			}
		}
		foreach ( self::normalize_template_map( $template_map ) as $row ) {
			$id = (int) $row['variant_template_id'];
			if ( $id > 0 && empty( $template_empty[ $id ] ) && self::usable_template_id( $id, $template_status, $template_type ) > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Elementor location ID for this response.
	 *
	 * Elementor applies the candidate template's display rules to whatever ID
	 * this returns. On the variant arm the control template is skipped, so its
	 * excludes are not recorded against the variant. Any other candidate still
	 * names the variant template. That template is also queued on the location,
	 * because a candidate whose rules do not match the URL never reaches this
	 * filter. On the control arm a positive override replaces a variant
	 * template Elementor selected. With no control template configured, that
	 * removal leaves the location empty rather than printing variant chrome
	 * for every visitor.
	 *
	 * @param string         $location      header|footer.
	 * @param int            $elementor_id  ID Elementor is visiting in the condition cache.
	 * @param int            $override_id   ID this arm should print, or 0.
	 * @param array<int,int> $owned         Variant template IDs owned by the test.
	 * @param bool           $allow_variant Variant arm may keep a variant template.
	 * @param array<int,int> $skip_ids      Candidates whose rules must not be copied onto the override.
	 * @return int
	 */
	public static function location_template_id( $location, $elementor_id, $override_id, array $owned, $allow_variant, array $skip_ids = array() ) {
		$location = sanitize_key( (string) $location );
		if ( ! in_array( $location, array( 'header', 'footer' ), true ) ) {
			return (int) $elementor_id;
		}
		$override_id  = (int) $override_id;
		$elementor_id = (int) $elementor_id;
		if ( $override_id > 0 && $allow_variant ) {
			foreach ( $skip_ids as $id ) {
				if ( $elementor_id === (int) $id ) {
					return 0;
				}
			}
			return $override_id;
		}
		if ( $override_id > 0 ) {
			return $override_id;
		}
		$owned_ids = array();
		foreach ( $owned as $id ) {
			$owned_ids[] = (int) $id;
		}
		if ( ! $allow_variant && in_array( $elementor_id, $owned_ids, true ) ) {
			return 0;
		}
		return $elementor_id;
	}

	/**
	 * Header and footer documents this response prints even when Elementor
	 * display conditions do not select them.
	 *
	 * @param array<string, mixed>|null $plan Delivery plan.
	 * @return array{header:int,footer:int}
	 */
	public static function forced_location_templates( $plan ) {
		$out = array(
			'header' => 0,
			'footer' => 0,
		);
		if ( ! is_array( $plan ) ) {
			return $out;
		}
		foreach ( array( 'header', 'footer' ) as $location ) {
			$out[ $location ] = max( 0, (int) ( $plan[ $location ] ?? 0 ) );
		}
		return $out;
	}

	/**
	 * Whether one Theme Builder rule matches the queried page.
	 *
	 * `include/general` matches every page. `singular/page/{id}` matches that
	 * page only. The include or exclude prefix is not part of the match.
	 *
	 * @param string $rule    Condition string from `_elementor_conditions`.
	 * @param int    $page_id Queried page.
	 * @return bool
	 */
	public static function theme_rule_matches( $rule, $page_id ) {
		$rule    = trim( (string) $rule );
		$page_id = (int) $page_id;
		if ( preg_match( '/^(include|exclude)\/general$/', $rule ) ) {
			return true;
		}
		if ( preg_match( '/^(include|exclude)\/singular\/page\/(\d+)$/', $rule, $matches ) ) {
			return $page_id > 0 && $page_id === (int) $matches[2];
		}
		return false;
	}

	/**
	 * Template IDs Elementor Pro keeps after the location condition walk.
	 *
	 * This is the contract in Conditions_Manager::get_location_templates().
	 * The template_id filter runs once per cached template. Include and exclude
	 * are recorded against the filtered ID. Excludes are applied after every
	 * template has been visited, so a control exclude can delete a variant ID
	 * that the variant template's own include had added. A filtered ID that is
	 * not published is ignored, which is how a skipped control template stays
	 * out of the result.
	 *
	 * @param array<int, array<int, string>> $rules_by_template Template ID => rules.
	 * @param string                         $location          header|footer.
	 * @param int                            $page_id           Queried page.
	 * @param array<int, int>                $published         Published template IDs.
	 * @return array<int, int>
	 */
	public static function elementor_condition_matches( array $rules_by_template, $location, $page_id, array $published ) {
		$selected  = array();
		$excludes  = array();
		$live      = array();
		foreach ( $published as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$live[ $id ] = true;
			}
		}
		foreach ( $rules_by_template as $template_id => $rules ) {
			if ( ! is_array( $rules ) ) {
				continue;
			}
			$filtered = (int) self::filter_location_template( (int) $template_id, $location );
			if ( $filtered <= 0 || empty( $live[ $filtered ] ) ) {
				continue;
			}
			foreach ( $rules as $rule ) {
				if ( ! self::theme_rule_matches( $rule, $page_id ) ) {
					continue;
				}
				if ( 0 === strpos( (string) $rule, 'exclude/' ) ) {
					$excludes[] = $filtered;
				} else {
					$selected[ $filtered ] = true;
				}
			}
		}
		foreach ( $excludes as $id ) {
			unset( $selected[ $id ] );
		}
		return array_map( 'intval', array_keys( $selected ) );
	}

	/**
	 * IDs Locations_Manager::do_location() prints.
	 *
	 * Condition matches are printed. A published template queued with
	 * add_doc_to_location is printed as well when the condition walk did not
	 * select it. That is the manual-document path, and it is what makes a
	 * variant header appear on a URL its display conditions do not name.
	 *
	 * @param array<int, array<int, string>> $rules_by_template Template ID => rules.
	 * @param string                         $location          header|footer.
	 * @param int                            $page_id           Queried page.
	 * @param array<int, int>                $published         Published template IDs.
	 * @param array<string, mixed>|null      $plan              Delivery plan.
	 * @return array<int, int>
	 */
	public static function printed_location_template_ids( array $rules_by_template, $location, $page_id, array $published, $plan ) {
		$printed = self::elementor_condition_matches( $rules_by_template, $location, $page_id, $published );
		$forced  = self::forced_location_templates( $plan );
		$location = sanitize_key( (string) $location );
		$extra    = isset( $forced[ $location ] ) ? (int) $forced[ $location ] : 0;
		$live     = array();
		foreach ( $published as $id ) {
			$live[ (int) $id ] = true;
		}
		if ( $extra > 0 && ! empty( $live[ $extra ] ) && ! in_array( $extra, $printed, true ) ) {
			$printed[] = $extra;
		}
		return $printed;
	}

	/**
	 * Markup Elementor prints for one theme location. Empty when the location
	 * has no document, which is when a theme such as Hello prints its fallback.
	 *
	 * @param string          $location     header|footer.
	 * @param array<int, int> $template_ids Documents do_location will print.
	 * @return string
	 */
	public static function theme_location_markup( $location, array $template_ids ) {
		$location = sanitize_key( (string) $location );
		if ( ! in_array( $location, array( 'header', 'footer' ), true ) ) {
			return '';
		}
		$html = '';
		foreach ( $template_ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$html .= '<div data-elementor-type="' . $location . '" data-elementor-id="' . $id . '" class="elementor elementor-' . $id . ' elementor-location-' . $location . '"></div>';
		}
		return $html;
	}

	/**
	 * Header and footer markup for one response.
	 *
	 * The request plan must already be the plan whose filter decisions this
	 * render uses.
	 *
	 * @param array<string, array<int, array<int, string>>> $rules      Location => template ID => rules.
	 * @param int                                           $page_id    Queried page.
	 * @param array<int, int>                               $published  Published template IDs.
	 * @param array<string, mixed>|null                     $plan       Delivery plan.
	 * @return string
	 */
	public static function theme_chrome_markup( array $rules, $page_id, array $published, $plan ) {
		$html = '';
		foreach ( array( 'header', 'footer' ) as $location ) {
			$bucket = isset( $rules[ $location ] ) && is_array( $rules[ $location ] ) ? $rules[ $location ] : array();
			$ids    = self::printed_location_template_ids( $bucket, $location, $page_id, $published, $plan );
			$html  .= self::theme_location_markup( $location, $ids );
		}
		return $html;
	}

	/**
	 * @param int                  $template_id Chosen ID.
	 * @param string               $location    Location.
	 * @return int
	 */
	public static function filter_location_template( $template_id, $location = '' ) {
		$template_id = (int) $template_id;
		$location    = sanitize_key( (string) $location );
		if ( ! is_array( self::$plan ) ) {
			$id = self::restore_unserved_template( $template_id, self::$guard, false );
			if ( $id !== $template_id ) {
				self::sync_printed_document_css( $template_id, $id );
			}
			return $id;
		}
		$selected = (int) $template_id;
		$override = 0;
		if ( isset( self::$plan[ $location ] ) ) {
			$override = (int) self::$plan[ $location ];
		}
		if ( empty( self::$plan['allow_variant_templates'] ) ) {
			$template_id = self::restore_unserved_template( $template_id, self::$guard, false );
		}
		$owned = isset( self::$plan['owned_variant_template_ids'] ) && is_array( self::$plan['owned_variant_template_ids'] )
			? self::$plan['owned_variant_template_ids']
			: array();
		$skip = isset( self::$plan['suppress_template_ids'] ) && is_array( self::$plan['suppress_template_ids'] )
			? self::$plan['suppress_template_ids']
			: array();
		$id = self::location_template_id(
			$location,
			(int) $template_id,
			$override,
			$owned,
			! empty( self::$plan['allow_variant_templates'] ),
			$skip
		);
		$id = self::translate_body_template(
			$location,
			$id,
			isset( self::$plan['assigned'] ) ? (string) self::$plan['assigned'] : 'control',
			isset( self::$plan['template_map'] ) && is_array( self::$plan['template_map'] ) ? self::$plan['template_map'] : array(),
			isset( self::$plan['usable_template_ids'] ) && is_array( self::$plan['usable_template_ids'] ) ? self::$plan['usable_template_ids'] : array(),
			! empty( self::$plan['allow_variant_templates'] ),
			isset( self::$plan['reasons'] ) && is_array( self::$plan['reasons'] ) && in_array( 'theme_locked', self::$plan['reasons'], true )
		);
		if ( function_exists( 'apply_filters' ) ) {
			$id = (int) apply_filters( 'rwgo_theme_location_template_id', $id, $location, self::$plan );
		}
		self::sync_printed_document_css( $selected, (int) $id );
		return $id;
	}

	/**
	 * Replace the condition-matched source template with its variant, or the
	 * reverse for the control arm. The queried product is not changed.
	 *
	 * @param string             $location      Theme Builder location.
	 * @param int                $elementor_id  ID Elementor selected.
	 * @param string             $arm           control|variant.
	 * @param array<int, mixed>  $template_map  Normalized rows.
	 * @param array<int, int>    $usable_ids    Published non-empty library IDs.
	 * @param bool               $allow_variant Variant arm may print a variant template.
	 * @param bool               $theme_locked  Cart, checkout, account, or excluded system page.
	 * @return int
	 */
	public static function translate_body_template( $location, $elementor_id, $arm, array $template_map, array $usable_ids, $allow_variant, $theme_locked ) {
		$elementor_id = (int) $elementor_id;
		$location     = self::canonical_body_location( $location );
		if ( '' === $location ) {
			return $elementor_id;
		}
		$usable = array();
		foreach ( $usable_ids as $id ) {
			$usable[] = (int) $id;
		}
		$serve_variant = ( 'variant' === $arm && $allow_variant && ! $theme_locked );
		foreach ( self::normalize_template_map( $template_map ) as $row ) {
			if ( $row['location'] !== $location ) {
				continue;
			}
			$source  = (int) $row['source_template_id'];
			$variant = (int) $row['variant_template_id'];
			if ( $elementor_id !== $source && $elementor_id !== $variant ) {
				continue;
			}
			if ( ! $serve_variant ) {
				return in_array( $source, $usable, true ) ? $source : 0;
			}
			if ( in_array( $variant, $usable, true ) ) {
				return $variant;
			}
			return in_array( $source, $usable, true ) ? $source : 0;
		}
		return $elementor_id;
	}

	/**
	 * Stylesheets for one response. Only Elementor document handles are
	 * listed. A retained post is never suppressed, including the canonical
	 * page when only the header changes and the product post when its body
	 * template changes.
	 *
	 * @param array<int, int> $printed IDs that will be printed.
	 * @param array<int, int> $replaced IDs Elementor selected and this arm replaced.
	 * @param array<int, int> $retain IDs whose document CSS must stay.
	 * @return array{enqueue:array<int,int>,suppress:array<int,int>,retain:array<int,int>}
	 */
	public static function head_style_plan( array $printed, array $replaced, array $retain ) {
		$printed = self::positive_ids( $printed );
		$retain  = self::positive_ids( array_merge( $retain, $printed ) );
		$suppress = array();
		foreach ( self::positive_ids( $replaced ) as $id ) {
			if ( ! in_array( $id, $retain, true ) ) {
				$suppress[] = $id;
			}
		}
		return array(
			'enqueue'  => $printed,
			'suppress' => $suppress,
			'retain'   => $retain,
		);
	}

	/**
	 * @param array<int, mixed> $ids IDs.
	 * @return array<int, int>
	 */
	private static function positive_ids( array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Stylesheet handle Elementor registers for one library document.
	 *
	 * @param int $post_id Template or page ID.
	 * @return string
	 */
	public static function elementor_style_handle( $post_id ) {
		return 'elementor-post-' . max( 0, (int) $post_id );
	}

	/**
	 * Which document styles to load for the template that will actually print.
	 *
	 * The location filter runs while the header is printed, which is after
	 * `wp_head`. The variant document's CSS has to be queued earlier and, when
	 * the head has already closed, printed before that document's HTML. The
	 * replaced document's handle is removed so its rules are not the ones that
	 * paint the swapped markup.
	 *
	 * @param int  $printed_id  Template that will print.
	 * @param int  $replaced_id Template Elementor selected before the swap.
	 * @param bool $head_done   wp_head has already run.
	 * @return array{enqueue:array<int,int>,suppress:array<int,int>,print_late:bool}
	 */
	public static function document_asset_actions( $printed_id, $replaced_id, $head_done ) {
		$printed_id  = (int) $printed_id;
		$replaced_id = (int) $replaced_id;
		$enqueue     = $printed_id > 0 ? array( $printed_id ) : array();
		$suppress    = ( $replaced_id > 0 && $replaced_id !== $printed_id ) ? array( $replaced_id ) : array();
		return array(
			'enqueue'    => $enqueue,
			'suppress'   => $suppress,
			'print_late' => (bool) $head_done && $printed_id > 0,
		);
	}

	/**
	 * @param int $replaced_id Elementor’s original template.
	 * @param int $printed_id  Template this arm prints.
	 * @return void
	 */
	public static function sync_printed_document_css( $replaced_id, $printed_id ) {
		$head_done = function_exists( 'did_action' ) && did_action( 'wp_head' );
		$actions   = self::document_asset_actions( $printed_id, $replaced_id, $head_done );
		foreach ( $actions['suppress'] as $id ) {
			self::suppress_document_css( (int) $id );
		}
		foreach ( $actions['enqueue'] as $id ) {
			self::enqueue_printed_document( (int) $id, ! empty( $actions['print_late'] ) );
		}
	}

	/**
	 * Queue CSS for the templates this response will print. Runs on
	 * `wp_enqueue_scripts`, before `wp_head`.
	 *
	 * @return void
	 */
	public static function enqueue_plan_documents() {
		if ( ! is_array( self::$plan ) ) {
			return;
		}
		if ( ! empty( self::$plan['header'] ) || ! empty( self::$plan['footer'] ) ) {
			self::enqueue_elementor_frontend_stack();
		}
		$ids = array();
		foreach ( array( 'swap_page_id', 'header', 'footer' ) as $key ) {
			if ( ! empty( self::$plan[ $key ] ) ) {
				$ids[] = (int) self::$plan[ $key ];
			}
		}
		if ( ! empty( self::$plan['preload_template_ids'] ) && is_array( self::$plan['preload_template_ids'] ) ) {
			foreach ( self::$plan['preload_template_ids'] as $id ) {
				$ids[] = (int) $id;
			}
		}
		foreach ( array_unique( $ids ) as $id ) {
			if ( $id > 0 ) {
				self::enqueue_printed_document( $id, false );
			}
		}
		self::suppress_replaced_documents();
	}

	/**
	 * Drop stylesheets for templates this arm replaced. Runs before
	 * `wp_print_styles` so a control document queued earlier does not paint
	 * the variant markup.
	 *
	 * @return void
	 */
	public static function suppress_replaced_documents() {
		if ( ! is_array( self::$plan ) ) {
			return;
		}
		$printed = array();
		foreach ( array( 'swap_page_id', 'header', 'footer' ) as $key ) {
			if ( ! empty( self::$plan[ $key ] ) ) {
				$printed[] = (int) self::$plan[ $key ];
			}
		}
		if ( ! empty( self::$plan['preload_template_ids'] ) && is_array( self::$plan['preload_template_ids'] ) ) {
			foreach ( self::$plan['preload_template_ids'] as $id ) {
				$printed[] = (int) $id;
			}
		}
		$replaced = array();
		if ( ! empty( self::$plan['suppress_template_ids'] ) && is_array( self::$plan['suppress_template_ids'] ) ) {
			foreach ( self::$plan['suppress_template_ids'] as $id ) {
				$replaced[] = (int) $id;
			}
		}
		if ( ! empty( self::$plan['replaced_body_id'] ) ) {
			$replaced[] = (int) self::$plan['replaced_body_id'];
		}
		$retain = array();
		if ( empty( self::$plan['swap_page_id'] ) && ! empty( self::$plan['retain_post_id'] ) ) {
			$retain[] = (int) self::$plan['retain_post_id'];
		}
		$plan = self::head_style_plan( $printed, $replaced, $retain );
		foreach ( $plan['suppress'] as $id ) {
			self::suppress_document_css( (int) $id );
		}
	}

	/**
	 * @param int  $post_id   Document ID.
	 * @param bool $print_now Print the handle when the head has closed.
	 * @return bool
	 */
	public static function enqueue_printed_document( $post_id, $print_now = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( function_exists( 'apply_filters' ) && apply_filters( 'rwgo_enqueue_document_css', false, $post_id, (bool) $print_now ) ) {
			return true;
		}
		if ( ! class_exists( '\Elementor\Core\Files\CSS\Post', false ) ) {
			return false;
		}
		$css = new \Elementor\Core\Files\CSS\Post( $post_id );
		if ( ! is_object( $css ) || ! method_exists( $css, 'enqueue' ) ) {
			return false;
		}
		$css->enqueue();
		$handle = self::elementor_style_handle( $post_id );
		if ( $print_now && function_exists( 'wp_print_styles' ) ) {
			wp_print_styles( $handle );
		}
		self::enqueue_document_runtime( $post_id );
		return true;
	}

	/**
	 * Register Elementor's frontend styles and scripts for a header or footer
	 * that is printed from a location rather than from the current post.
	 *
	 * @return void
	 */
	private static function enqueue_elementor_frontend_stack() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return;
		}
		$frontend = isset( \Elementor\Plugin::$instance->frontend ) ? \Elementor\Plugin::$instance->frontend : null;
		if ( ! is_object( $frontend ) ) {
			return;
		}
		foreach ( array( 'register_styles', 'register_scripts', 'enqueue_styles', 'enqueue_scripts' ) as $method ) {
			if ( method_exists( $frontend, $method ) ) {
				$frontend->{$method}();
			}
		}
	}

	/**
	 * Ask the document for widget and Atomic runtime assets. The post CSS file
	 * does not register those scripts.
	 *
	 * @param int $post_id Template ID.
	 * @return void
	 */
	private static function enqueue_document_runtime( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || self::$enqueueing_runtime || ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return;
		}
		self::$enqueueing_runtime = true;
		try {
			$documents = isset( \Elementor\Plugin::$instance->documents ) ? \Elementor\Plugin::$instance->documents : null;
			if ( is_object( $documents ) && method_exists( $documents, 'get' ) ) {
				$document = $documents->get( $post_id );
				if ( is_object( $document ) ) {
					foreach ( array( 'enqueue_scripts', 'enqueue_styles' ) as $method ) {
						if ( method_exists( $document, $method ) ) {
							$document->{$method}();
						}
					}
				}
			}
		} finally {
			self::$enqueueing_runtime = false;
		}
	}

	/**
	 * Put this arm's header and footer onto the Elementor location queue.
	 *
	 * do_location() prints a published document that was added with
	 * add_doc_to_location even when display conditions did not select it.
	 * The control template is removed so a matching "entire site" rule cannot
	 * print it beside the variant.
	 *
	 * @return void
	 */
	public static function prime_theme_locations() {
		if ( ! is_array( self::$plan ) ) {
			return;
		}
		$manager = self::locations_manager();
		if ( ! is_object( $manager ) ) {
			return;
		}
		$forced = self::forced_location_templates( self::$plan );
		$skip   = isset( self::$plan['suppress_template_ids'] ) && is_array( self::$plan['suppress_template_ids'] )
			? self::$plan['suppress_template_ids']
			: array();
		foreach ( array( 'header', 'footer' ) as $location ) {
			$keep = (int) $forced[ $location ];
			if ( method_exists( $manager, 'remove_doc_from_location' ) ) {
				foreach ( $skip as $id ) {
					$id = (int) $id;
					if ( $id > 0 && $id !== $keep ) {
						$manager->remove_doc_from_location( $location, $id );
					}
				}
			}
			if ( $keep > 0 && method_exists( $manager, 'add_doc_to_location' ) ) {
				$manager->add_doc_to_location( $location, $keep );
			}
		}
	}

	/**
	 * @return object|null
	 */
	private static function locations_manager() {
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgo_elementor_locations_manager', null );
			if ( is_object( $filtered ) ) {
				return $filtered;
			}
		}
		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module', false ) || ! method_exists( '\ElementorPro\Modules\ThemeBuilder\Module', 'instance' ) ) {
			return null;
		}
		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( ! is_object( $module ) || ! method_exists( $module, 'get_locations_manager' ) ) {
			return null;
		}
		$manager = $module->get_locations_manager();
		return is_object( $manager ) ? $manager : null;
	}

	/**
	 * @param int $post_id Document whose stylesheet must not paint this response.
	 * @return void
	 */
	public static function suppress_document_css( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		$handle = self::elementor_style_handle( $post_id );
		if ( function_exists( 'wp_dequeue_style' ) ) {
			wp_dequeue_style( $handle );
		}
		if ( function_exists( 'wp_deregister_style' ) ) {
			wp_deregister_style( $handle );
		}
	}

	/**
	 * @param array<string, mixed>|null $plan Plan.
	 * @return void
	 */
	public static function set_request_plan( $plan ) {
		self::$plan = is_array( $plan ) ? $plan : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function request_plan() {
		return self::$plan;
	}

	/**
	 * Signed preview for a manager. A bare query argument is ignored.
	 *
	 * @param string $preview_value rwgo_preview.
	 * @param string $legacy_value  rwgo_arm, accepted only with the same nonce.
	 * @param bool   $can_manage    Current user can manage the test.
	 * @param bool   $nonce_valid   Nonce matches the current user.
	 * @return string
	 */
	public static function preview_arm( $preview_value, $legacy_value, $can_manage, $nonce_valid ) {
		if ( ! $can_manage || ! $nonce_valid ) {
			return '';
		}
		$arm = sanitize_key( (string) $preview_value );
		if ( ! in_array( $arm, array( 'control', 'variant' ), true ) ) {
			$arm = sanitize_key( (string) $legacy_value );
		}
		return in_array( $arm, array( 'control', 'variant' ), true ) ? $arm : '';
	}

	/**
	 * A failed rwgo nonce is reported to a manager and does not change the page.
	 *
	 * Anonymous visitors get no code. A matching nonce gets no code. The code
	 * never means the draft was served.
	 *
	 * @param bool   $requested   rwgo_preview or rwgo_arm was sent.
	 * @param bool   $nonce_valid Nonce matches this logged-in session.
	 * @param bool   $can_manage  Current user can manage the test.
	 * @param string $resolved_arm Arm preview_arm() returned.
	 * @return string
	 */
	public static function preview_diagnostic_code( $requested, $nonce_valid, $can_manage, $resolved_arm ) {
		if ( ! $requested || ! $can_manage || '' !== (string) $resolved_arm || $nonce_valid ) {
			return '';
		}
		return 'rwgo_preview_session_mismatch';
	}

	/**
	 * Skip the variant-URL canonical redirect only for a preview that already
	 * proved itself. A query parameter on its own is not proof.
	 *
	 * @param bool   $wp_is_preview     WordPress is_preview() for this user.
	 * @param string $signed_arm        rwgo preview arm after nonce and capability checks.
	 * @param bool   $shareable_granted mosmcp token matched a stored hash and has not expired.
	 * @return bool
	 */
	public static function canonical_redirect_blocked( $wp_is_preview, $signed_arm, $shareable_granted ) {
		if ( $wp_is_preview ) {
			return true;
		}
		if ( in_array( (string) $signed_arm, array( 'control', 'variant' ), true ) ) {
			return true;
		}
		return (bool) $shareable_granted;
	}

	/**
	 * Hash-compare a shareable preview token. Presence of the parameter is not enough.
	 *
	 * The stored rows use the same shape as miniOrange preview links: sha256
	 * `hash` and unix `expires`. This does not read or write that plugin’s state.
	 *
	 * @param mixed              $token Token from the query.
	 * @param mixed              $links Stored link rows.
	 * @param int                $now   Current unix time.
	 * @return bool
	 */
	public static function shareable_preview_granted( $token, $links, $now ) {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			return false;
		}
		if ( ! is_array( $links ) ) {
			return false;
		}
		$hash = hash( 'sha256', $token );
		$now  = (int) $now;
		foreach ( $links as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$stored  = isset( $link['hash'] ) ? (string) $link['hash'] : '';
			$expires = isset( $link['expires'] ) ? (int) $link['expires'] : 0;
			if ( '' === $stored || $expires <= $now ) {
				continue;
			}
			if ( hash_equals( $stored, $hash ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param bool $active Preview request.
	 * @return void
	 */
	public static function set_preview( $active ) {
		self::$preview = (bool) $active;
	}

	/**
	 * @return bool
	 */
	public static function is_preview() {
		return self::$preview;
	}

	/**
	 * Preview and a refused statistics consent do not enter exposure or goals.
	 *
	 * @param bool $is_preview          Preview response.
	 * @param bool $consent_api_active  WordPress Consent API is loaded.
	 * @param bool $statistics_consent  Statistics consent when the API is active.
	 * @return bool
	 */
	public static function measurement_allowed( $is_preview, $consent_api_active, $statistics_consent ) {
		if ( $is_preview ) {
			return false;
		}
		if ( $consent_api_active && ! $statistics_consent ) {
			return false;
		}
		return true;
	}

	/**
	 * Runtime gate. The assignment cookie is not part of this decision.
	 *
	 * @return bool
	 */
	public static function allow_measurement() {
		$api   = function_exists( 'wp_has_consent' );
		$stats = $api ? (bool) wp_has_consent( 'statistics' ) : true;
		$allow = self::measurement_allowed( self::$preview, $api, $stats );
		if ( function_exists( 'apply_filters' ) ) {
			$allow = (bool) apply_filters( 'rwgo_measurement_allowed', $allow, self::$preview );
		}
		return $allow;
	}

	/**
	 * IP and user-agent are not stored. They are also not hashed into an
	 * exposure key when statistics consent is refused.
	 *
	 * @return bool
	 */
	public static function may_use_network_fingerprint() {
		if ( self::$preview ) {
			return false;
		}
		if ( function_exists( 'wp_has_consent' ) && ! wp_has_consent( 'statistics' ) ) {
			return false;
		}
		$allow = true;
		if ( function_exists( 'apply_filters' ) ) {
			$allow = (bool) apply_filters( 'rwgo_measurement_fingerprint', true );
		}
		return $allow;
	}

	/**
	 * Conversion arm follows the experience that can actually be delivered.
	 *
	 * @param string $assigned              Assigned arm.
	 * @param bool   $experience_available  A published variant page or template exists.
	 * @param bool   $measure               Measurement is allowed.
	 * @return string Empty when the event must be dropped.
	 */
	public static function conversion_arm( $assigned, $experience_available, $measure ) {
		if ( ! $measure ) {
			return '';
		}
		$assigned = in_array( $assigned, array( 'control', 'variant' ), true ) ? $assigned : '';
		if ( '' === $assigned ) {
			return '';
		}
		if ( 'variant' === $assigned && ! $experience_available ) {
			return 'control';
		}
		return $assigned;
	}

	/**
	 * @param string $status Experiment status.
	 * @return bool
	 */
	public static function experiment_serves_arms( $status ) {
		return 'active' === sanitize_key( (string) $status );
	}

	/**
	 * Paused and ended tests still send a variant URL back to the original once.
	 *
	 * @param string $status Experiment status.
	 * @return bool
	 */
	public static function experiment_canonicalizes_variant_urls( $status ) {
		return in_array( sanitize_key( (string) $status ), array( 'active', 'paused', 'ended' ), true );
	}

	/**
	 * One hop back to the original. No target when the original is itself a variant URL.
	 *
	 * @param int               $source_id     Original page.
	 * @param int               $variant_id    Variant page.
	 * @param string            $source_url    Original permalink.
	 * @param string            $source_path   Original path.
	 * @param array<int, int>   $variant_ids   Every variant page ID in the map.
	 * @param array<string,bool> $variant_paths Variant paths.
	 * @return string
	 */
	public static function safe_variant_redirect( $source_id, $variant_id, $source_url, $source_path, array $variant_ids, array $variant_paths ) {
		$source_id  = (int) $source_id;
		$variant_id = (int) $variant_id;
		if ( $source_id <= 0 || $variant_id <= 0 || $source_id === $variant_id ) {
			return '';
		}
		if ( ! is_string( $source_url ) || '' === $source_url ) {
			return '';
		}
		foreach ( $variant_ids as $id ) {
			if ( $source_id === (int) $id ) {
				return '';
			}
		}
		$source_path = self::normalize_path( $source_path );
		if ( '' !== $source_path && '/' !== $source_path && isset( $variant_paths[ $source_path ] ) ) {
			return '';
		}
		return $source_url;
	}

	/**
	 * Drop pairs that would redirect in a circle. Stored config is left unchanged.
	 *
	 * @param array<int, array<string, mixed>> $rows Map rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function page_map_without_loops( array $rows ) {
		$as_source  = array();
		$as_variant = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = (int) ( $row['source_page_id'] ?? 0 );
			$variant = (int) ( $row['variant_page_id'] ?? 0 );
			if ( $source > 0 ) {
				$as_source[ $source ] = true;
			}
			if ( $variant > 0 ) {
				$as_variant[ $variant ] = true;
			}
		}
		$clean = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = (int) ( $row['source_page_id'] ?? 0 );
			$variant = (int) ( $row['variant_page_id'] ?? 0 );
			if ( $source <= 0 || $variant <= 0 || $source === $variant ) {
				continue;
			}
			if ( isset( $as_variant[ $source ] ) || isset( $as_source[ $variant ] ) ) {
				continue;
			}
			$clean[] = $row;
		}
		return $clean;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Raw map rows.
	 * @return array<int, int>
	 */
	public static function map_conflict_ids( array $rows ) {
		$kept = array();
		foreach ( self::page_map_without_loops( $rows ) as $row ) {
			$kept[ (int) $row['source_page_id'] . ':' . (int) $row['variant_page_id'] ] = true;
		}
		$ids = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = (int) ( $row['source_page_id'] ?? 0 );
			$variant = (int) ( $row['variant_page_id'] ?? 0 );
			$key     = $source . ':' . $variant;
			if ( $source <= 0 || $variant <= 0 || isset( $kept[ $key ] ) ) {
				continue;
			}
			$ids[] = $source;
			$ids[] = $variant;
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Read-only problems. Nothing here publishes a page or template.
	 *
	 * @param array<int, array<string, mixed>> $rows            Raw page map.
	 * @param array<int, string>               $page_status     Page ID => status.
	 * @param array<string, mixed>             $theme           Theme context.
	 * @param array<int, string>               $template_status Template ID => status.
	 * @param array<int, string>               $template_type   Template ID => post type.
	 * @return array<int, array{code:string,post_id:int}>
	 */
	public static function audit( array $rows, array $page_status, $theme, array $template_status, array $template_type, array $template_map = array(), array $template_empty = array(), array $template_elementor_type = array() ) {
		$warnings = array();
		foreach ( self::map_conflict_ids( $rows ) as $id ) {
			$warnings[] = array(
				'code'    => 'redirect_loop',
				'post_id' => (int) $id,
			);
		}
		$seen_variant = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$variant = (int) ( $row['variant_page_id'] ?? 0 );
			if ( $variant <= 0 || isset( $seen_variant[ $variant ] ) ) {
				continue;
			}
			$seen_variant[ $variant ] = true;
			$status = isset( $page_status[ $variant ] ) ? sanitize_key( (string) $page_status[ $variant ] ) : '';
			if ( 'publish' !== $status ) {
				$warnings[] = array(
					'code'    => 'variant_page_unpublished',
					'post_id' => $variant,
				);
			}
		}
		$theme = self::normalize( $theme );
		foreach ( array( 'control', 'variant' ) as $arm ) {
			foreach ( array( 'header', 'footer' ) as $location ) {
				$id = (int) $theme[ $arm ][ $location ];
				if ( $id <= 0 ) {
					if ( 'variant' === $arm ) {
						continue;
					}
					$variant_id = (int) $theme['variant'][ $location ];
					if ( $variant_id > 0 ) {
						$warnings[] = array(
							'code'    => 'control_template_missing',
							'post_id' => 0,
						);
					}
					continue;
				}
				if ( self::usable_template_id( $id, $template_status, $template_type ) > 0 ) {
					continue;
				}
				$warnings[] = array(
					'code'    => 'theme_template_unavailable',
					'post_id' => $id,
				);
			}
		}
		foreach ( self::normalize_template_map( $template_map ) as $row ) {
			foreach ( array( 'source_template_id', 'variant_template_id' ) as $key ) {
				$id = (int) $row[ $key ];
				if ( self::printable_template_id( $id, $template_status, $template_type, $template_empty ) > 0 ) {
					$elementor_type = isset( $template_elementor_type[ $id ] ) ? (string) $template_elementor_type[ $id ] : '';
					if ( self::template_type_conflicts( $elementor_type, (string) $row['location'] ) ) {
						$warnings[] = array(
							'code'    => 'template_location_mismatch',
							'post_id' => $id,
						);
					}
					continue;
				}
				$warnings[] = array(
					'code'    => 'template_variant_unavailable',
					'post_id' => $id,
				);
			}
		}
		return $warnings;
	}

	/**
	 * Cache bypass for a theme split on a URL that is not in the page map.
	 *
	 * Mapped URLs already bypass. An unmapped URL that prints a different
	 * header per visitor must not be stored as one shared page.
	 *
	 * @param string $role               source|variant|none.
	 * @param bool   $serves_or_assigns  This response assigns or serves an arm.
	 * @param bool   $theme_split        Header or footer IDs are configured.
	 * @param bool   $is_bot             Known bot.
	 * @return bool
	 */
	public static function request_needs_cache_bypass( $role, $serves_or_assigns, $theme_split, $is_bot ) {
		$role = (string) $role;
		if ( $is_bot ) {
			return 'source' === $role || 'variant' === $role || ( $theme_split && 'none' === $role );
		}
		if ( 'variant' === $role ) {
			return true;
		}
		if ( 'source' === $role && $serves_or_assigns ) {
			return true;
		}
		return $theme_split && 'none' === $role && $serves_or_assigns;
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	public static function normalize_path( $path ) {
		$path = (string) $path;
		$q    = strpos( $path, '?' );
		if ( false !== $q ) {
			$path = substr( $path, 0, $q );
		}
		$path = '/' . trim( $path, '/' );
		return '/' === $path ? '/' : $path;
	}

	/**
	 * @param string $slug Slug.
	 * @return string
	 */
	private static function normalize_slug( $slug ) {
		$slug = strtolower( (string) $slug );
		$slug = str_replace( '_', '-', $slug );
		$slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );
		return is_string( $slug ) ? $slug : '';
	}
}
