<?php
/**
 * High-level experiment lifecycle: keys, publish, variant resolution.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Experiment service.
 */
class RWGO_Experiment_Service {

	/**
	 * Heal stale page IDs from stored slugs/paths when experiment post id is known (persists); else in-memory only.
	 *
	 * @param array<string, mixed> $config             Experiment config.
	 * @param int                  $experiment_post_id Experiment CPT id (0 = no persist).
	 * @return array<string, mixed>
	 */
	private static function with_normalized_bindings( array $config, $experiment_post_id = 0 ) {
		if ( ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
			return $config;
		}
		$e = (int) $experiment_post_id;
		return RWGO_Experiment_Repository::normalize_page_bindings( $config, $e, false );
	}

	/**
	 * @param int $source_page_id Source post ID.
	 * @return string
	 */
	public static function generate_experiment_key( $source_page_id ) {
		$source_page_id = (int) $source_page_id;
		$hash           = strtolower( wp_generate_password( 8, false, false ) );
		return 'rwgo_page_' . $source_page_id . '_ab_' . $hash;
	}

	/**
	 * @param string $prefix e.g. goal_
	 * @return string
	 */
	public static function generate_uid( $prefix = 'id' ) {
		return $prefix . strtolower( wp_generate_password( 10, false, false ) );
	}

	/**
	 * Default variant structure for page A/B.
	 *
	 * @param int $control_page_id Control (source) page.
	 * @param int $challenger_page_id Variant B page.
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_variants( $control_page_id, $challenger_page_id ) {
		return array(
			array(
				'variant_id'    => 'control',
				'variant_label' => __( 'Control (A)', 'reactwoo-geo-optimise' ),
				'page_id'       => (int) $control_page_id,
				'weight'        => 0.5,
			),
			array(
				'variant_id'    => 'var_b',
				'variant_label' => __( 'Variant B', 'reactwoo-geo-optimise' ),
				'page_id'       => (int) $challenger_page_id,
				'weight'        => 0.5,
			),
		);
	}

	/**
	 * Map variant_id to assignment slug for RWGO_Assignment (control | var_b).
	 *
	 * @param array<string, mixed> $config Repository config.
	 * @return list<string>
	 */
	public static function assignment_variant_slugs( array $config ) {
		$out = array( 'control', 'var_b' );
		/**
		 * Filters variant slugs passed to RWGO_Assignment::get_variant for an experiment config.
		 *
		 * @param list<string>         $out    Default control/var_b.
		 * @param array<string, mixed> $config Experiment config.
		 */
		return apply_filters( 'rwgo_assignment_variant_slugs', $out, $config );
	}

	/**
	 * Weights aligned with {@see assignment_variant_slugs()} order — resolved by variant_id, not array index.
	 *
	 * @param array<string, mixed> $config Config.
	 * @return list<float>
	 */
	public static function assignment_weights( array $config ) {
		$slugs = self::assignment_variant_slugs( $config );
		$vars  = isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array();
		$by_id = array();
		foreach ( $vars as $row ) {
			if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
				continue;
			}
			$vid = sanitize_key( (string) $row['variant_id'] );
			if ( isset( $row['weight'] ) && is_numeric( $row['weight'] ) ) {
				$by_id[ $vid ] = (float) $row['weight'];
			}
		}
		$n     = count( $slugs );
		$equal = $n > 0 ? 1.0 / $n : 0.5;
		$out   = array();
		foreach ( $slugs as $slug ) {
			$sk = sanitize_key( (string) $slug );
			if ( isset( $by_id[ $sk ] ) ) {
				$out[] = (float) $by_id[ $sk ];
			} else {
				$out[] = $equal;
			}
		}
		return $out;
	}

	/**
	 * Whether the experiment’s control or a variant page is the given post.
	 *
	 * @param array<string, mixed> $config          Experiment config.
	 * @param int                  $queried_post_id Post ID.
	 * @return bool
	 */
	public static function experiment_includes_page( array $config, $queried_post_id, $experiment_post_id = 0 ) {
		$config          = self::with_normalized_bindings( $config, $experiment_post_id );
		$queried_post_id = (int) $queried_post_id;
		if ( $queried_post_id <= 0 ) {
			return false;
		}
		$source = (int) ( $config['source_page_id'] ?? 0 );
		if ( $source === $queried_post_id ) {
			return true;
		}
		foreach ( isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array() as $row ) {
			if ( is_array( $row ) && (int) ( $row['page_id'] ?? 0 ) === $queried_post_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sum of configured weights for known variant rows (sanity check for admin warnings).
	 *
	 * @param array<string, mixed> $config Config.
	 * @return float
	 */
	public static function configured_weight_sum( array $config ) {
		$vars = isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array();
		$sum  = 0.0;
		foreach ( $vars as $row ) {
			if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
				continue;
			}
			if ( isset( $row['weight'] ) && is_numeric( $row['weight'] ) ) {
				$sum += (float) $row['weight'];
			}
		}
		return $sum;
	}

	/**
	 * Resolve page ID for a variant_id.
	 *
	 * @param array<string, mixed> $config Repository config.
	 * @param string               $variant_id control|var_b.
	 * @return int 0 if unknown.
	 */
	public static function page_id_for_variant( array $config, $variant_id, $experiment_post_id = 0 ) {
		$config     = self::with_normalized_bindings( $config, $experiment_post_id );
		$variant_id = sanitize_key( (string) $variant_id );
		$vars       = isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array();
		foreach ( $vars as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['variant_id'] ) && sanitize_key( (string) $row['variant_id'] ) === $variant_id ) {
				return (int) ( $row['page_id'] ?? 0 );
			}
		}
		return 0;
	}

	/**
	 * Draft, pending, and private variants are not served to visitors.
	 *
	 * Publishing is left to the merchant. A running test keeps every visitor on
	 * Control until Variant B is public.
	 *
	 * @param string $post_status Post status.
	 * @return bool
	 */
	public static function variant_status_blocks_traffic( $post_status ) {
		$status = sanitize_key( (string) $post_status );
		if ( '' === $status ) {
			return false;
		}
		return 'publish' !== $status;
	}

	/**
	 * Whether Variant B exists and can be used for assignment / redirects (incomplete tests stay on Control only).
	 *
	 * @param array<string, mixed> $config Repository config.
	 * @return bool
	 */
	public static function variant_b_is_routable( array $config, $experiment_post_id = 0 ) {
		$config = self::with_normalized_bindings( $config, $experiment_post_id );
		$id     = self::page_id_for_variant( $config, 'var_b', 0 );
		if ( $id <= 0 ) {
			return false;
		}
		return (bool) is_post_publicly_viewable( $id );
	}

	/**
	 * Resolve variant slug for the current singular request (cookie on control URL; explicit match on variant URLs).
	 *
	 * @param array<string, mixed> $config          Experiment config.
	 * @param int                  $queried_post_id Current post ID.
	 * @return string control|var_b|…
	 */
	public static function resolve_variant_for_context( array $config, $queried_post_id, $experiment_post_id = 0 ) {
		$config          = self::with_normalized_bindings( $config, $experiment_post_id );
		$queried_post_id = (int) $queried_post_id;
		$key             = isset( $config['experiment_key'] ) ? sanitize_key( (string) $config['experiment_key'] ) : '';
		if ( '' === $key ) {
			return '';
		}
		$source = (int) ( $config['source_page_id'] ?? 0 );
		if ( $queried_post_id === $source ) {
			if ( is_user_logged_in() && ! empty( $_GET['rwgo_no_swap'] ) && class_exists( 'RWGO_Admin', false ) && current_user_can( RWGO_Admin::required_capability() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return 'control';
			}
			if ( ! self::variant_b_is_routable( $config, 0 ) ) {
				return 'control';
			}
			$slugs   = self::assignment_variant_slugs( $config );
			$weights = self::assignment_weights( $config );
			return (string) RWGO_Assignment::get_variant( $key, $slugs, $weights );
		}
		foreach ( isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array() as $row ) {
			if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
				continue;
			}
			if ( (int) ( $row['page_id'] ?? 0 ) === $queried_post_id ) {
				return sanitize_key( (string) $row['variant_id'] );
			}
		}
		return '';
	}

	/**
	 * Admin completeness for the experiments list, Edit Test, and Reports.
	 *
	 * Site-wide delivery reads page_map, theme_context, and template_map. The
	 * stored control and var_b page IDs stay 0. This does not assign visitors
	 * or change what is saved.
	 *
	 * Pass $posts in tests as post ID => array{status?:string,title?:string,public?:bool,exists?:bool}.
	 * Null uses the current WordPress posts.
	 *
	 * @param array<string, mixed>                                              $config Experiment config.
	 * @param array<int, array<string, mixed>|null>|null                       $posts  Optional post catalog.
	 * @return array{
	 *   sitewide: bool,
	 *   complete: bool,
	 *   missing_variant: bool,
	 *   warnings: list<array{code:string,kind:string,post_id:int,label:string,message:string}>,
	 *   summary: string,
	 *   slots: int,
	 *   page_pairs: int,
	 *   variant_header: int,
	 *   variant_footer: int,
	 *   product_pairs: int
	 * }
	 */
	public static function admin_variant_health( array $config, $posts = null ) {
		if ( self::config_is_sitewide( $config ) ) {
			return self::sitewide_admin_variant_health( $config, $posts );
		}
		$id       = self::page_id_for_variant( $config, 'var_b', 0 );
		$state    = self::admin_post_state( $id, $posts );
		$complete = $id > 0 && $state['exists'] && $state['public'];
		return array(
			'sitewide'        => false,
			'complete'        => $complete,
			'missing_variant' => ! $complete,
			'warnings'        => array(),
			'summary'         => '',
			'slots'           => $id > 0 ? 1 : 0,
			'page_pairs'      => 0,
			'variant_header'  => 0,
			'variant_footer'  => 0,
			'product_pairs'   => 0,
		);
	}

	/**
	 * @param array<string, mixed> $config Experiment config.
	 * @return bool
	 */
	private static function config_is_sitewide( array $config ) {
		if ( class_exists( 'RWGO_Sitewide', false ) ) {
			return RWGO_Sitewide::is_sitewide_config( $config );
		}
		$type  = isset( $config['test_type'] ) ? sanitize_key( (string) $config['test_type'] ) : '';
		$scope = isset( $config['scope'] ) ? sanitize_key( (string) $config['scope'] ) : '';
		return 'sitewide' === $type || 'site' === $scope;
	}

	/**
	 * @param array<string, mixed>                        $config Experiment config.
	 * @param array<int, array<string, mixed>|null>|null $posts  Post catalog or null.
	 * @return array<string, mixed>
	 */
	private static function sitewide_admin_variant_health( array $config, $posts ) {
		$warnings      = array();
		$seen          = array();
		$page_pairs    = 0;
		$rows          = isset( $config['page_map'] ) && is_array( $config['page_map'] ) ? $config['page_map'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = (int) ( $row['source_page_id'] ?? 0 );
			$variant = (int) ( $row['variant_page_id'] ?? 0 );
			if ( $source <= 0 || $variant <= 0 ) {
				continue;
			}
			++$page_pairs;
			$key = 'page:' . $variant;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$warning      = self::mapped_target_warning( 'page', $variant, $posts );
			if ( null !== $warning ) {
				$warnings[] = $warning;
			}
		}

		$theme  = isset( $config['theme_context']['variant'] ) && is_array( $config['theme_context']['variant'] )
			? $config['theme_context']['variant']
			: array();
		$header = (int) ( $theme['header'] ?? 0 );
		$footer = (int) ( $theme['footer'] ?? 0 );
		if ( $header > 0 ) {
			$warning = self::mapped_target_warning( 'header', $header, $posts );
			if ( null !== $warning ) {
				$warnings[] = $warning;
			}
		}
		if ( $footer > 0 ) {
			$warning = self::mapped_target_warning( 'footer', $footer, $posts );
			if ( null !== $warning ) {
				$warnings[] = $warning;
			}
		}

		$product_pairs = 0;
		$templates     = isset( $config['template_map'] ) && is_array( $config['template_map'] ) ? $config['template_map'] : array();
		foreach ( $templates as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = (int) ( $row['source_template_id'] ?? 0 );
			$variant = (int) ( $row['variant_template_id'] ?? 0 );
			if ( $source <= 0 || $variant <= 0 ) {
				continue;
			}
			++$product_pairs;
			$key = 'product_template:' . $variant;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$warning      = self::mapped_target_warning( 'product_template', $variant, $posts );
			if ( null !== $warning ) {
				$warnings[] = $warning;
			}
		}

		$complete = ( $page_pairs > 0 || $header > 0 || $footer > 0 || $product_pairs > 0 );
		$slots    = $page_pairs + ( $header > 0 ? 1 : 0 ) + ( $footer > 0 ? 1 : 0 ) + $product_pairs;
		return array(
			'sitewide'        => true,
			'complete'        => $complete,
			'missing_variant' => ! $complete,
			'warnings'        => $warnings,
			'summary'         => self::sitewide_health_summary( $page_pairs, $header, $footer, $product_pairs ),
			'slots'           => $slots,
			'page_pairs'      => $page_pairs,
			'variant_header'  => $header,
			'variant_footer'  => $footer,
			'product_pairs'   => $product_pairs,
		);
	}

	/**
	 * @param int                                             $post_id Post ID.
	 * @param array<int, array<string, mixed>|null>|null     $posts   Catalog or null for WordPress.
	 * @return array{exists:bool,public:bool,title:string}
	 */
	private static function admin_post_state( $post_id, $posts ) {
		$post_id = (int) $post_id;
		$empty   = array(
			'exists' => false,
			'public' => false,
			'title'  => '',
		);
		if ( $post_id <= 0 ) {
			return $empty;
		}
		if ( is_array( $posts ) ) {
			if ( ! isset( $posts[ $post_id ] ) || ! is_array( $posts[ $post_id ] ) ) {
				return $empty;
			}
			$row = $posts[ $post_id ];
			if ( array_key_exists( 'exists', $row ) && empty( $row['exists'] ) ) {
				return $empty;
			}
			$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
			$public = array_key_exists( 'public', $row ) ? (bool) $row['public'] : ( 'publish' === $status );
			return array(
				'exists' => true,
				'public' => $public,
				'title'  => isset( $row['title'] ) ? trim( (string) $row['title'] ) : '',
			);
		}
		if ( ! function_exists( 'get_post' ) ) {
			return $empty;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $empty;
		}
		$title = '';
		if ( function_exists( 'get_the_title' ) ) {
			$title = trim( (string) get_the_title( $post ) );
		} elseif ( isset( $post->post_title ) ) {
			$title = trim( (string) $post->post_title );
		}
		$public = function_exists( 'is_post_publicly_viewable' )
			? (bool) is_post_publicly_viewable( $post )
			: ( isset( $post->post_status ) && 'publish' === (string) $post->post_status );
		return array(
			'exists' => true,
			'public' => $public,
			'title'  => $title,
		);
	}

	/**
	 * @param string                                          $kind    page|header|footer|product_template.
	 * @param int                                             $post_id Mapped B id.
	 * @param array<int, array<string, mixed>|null>|null     $posts   Catalog or null.
	 * @return array{code:string,kind:string,post_id:int,label:string,message:string}|null
	 */
	private static function mapped_target_warning( $kind, $post_id, $posts ) {
		$state = self::admin_post_state( $post_id, $posts );
		if ( $state['exists'] && $state['public'] ) {
			return null;
		}
		$missing = ! $state['exists'];
		$label   = '' !== $state['title'] ? $state['title'] : (string) (int) $post_id;
		return array(
			'code'    => $missing ? 'missing' : 'unpublished',
			'kind'    => (string) $kind,
			'post_id' => (int) $post_id,
			'label'   => $label,
			'message' => self::mapped_target_message( (string) $kind, $label, $missing, '' !== $state['title'] ),
		);
	}

	/**
	 * @param string $kind    page|header|footer|product_template.
	 * @param string $label   Title or numeric id.
	 * @param bool   $missing Post is not on the site.
	 * @param bool   $named   Label is a title.
	 * @return string
	 */
	private static function mapped_target_message( $kind, $label, $missing, $named ) {
		if ( 'header' === $kind ) {
			if ( $missing ) {
				return $named
					? sprintf( /* translators: %s: template title */ __( 'Variant header “%s” is missing.', 'reactwoo-geo-optimise' ), $label )
					: sprintf( /* translators: %s: template ID */ __( 'Variant header %s is missing.', 'reactwoo-geo-optimise' ), $label );
			}
			return $named
				? sprintf( /* translators: %s: template title */ __( 'Variant header “%s” is not published.', 'reactwoo-geo-optimise' ), $label )
				: sprintf( /* translators: %s: template ID */ __( 'Variant header %s is not published.', 'reactwoo-geo-optimise' ), $label );
		}
		if ( 'footer' === $kind ) {
			if ( $missing ) {
				return $named
					? sprintf( /* translators: %s: template title */ __( 'Variant footer “%s” is missing.', 'reactwoo-geo-optimise' ), $label )
					: sprintf( /* translators: %s: template ID */ __( 'Variant footer %s is missing.', 'reactwoo-geo-optimise' ), $label );
			}
			return $named
				? sprintf( /* translators: %s: template title */ __( 'Variant footer “%s” is not published.', 'reactwoo-geo-optimise' ), $label )
				: sprintf( /* translators: %s: template ID */ __( 'Variant footer %s is not published.', 'reactwoo-geo-optimise' ), $label );
		}
		if ( 'product_template' === $kind ) {
			if ( $missing ) {
				return $named
					? sprintf( /* translators: %s: template title */ __( 'Variant product template “%s” is missing.', 'reactwoo-geo-optimise' ), $label )
					: sprintf( /* translators: %s: template ID */ __( 'Variant product template %s is missing.', 'reactwoo-geo-optimise' ), $label );
			}
			return $named
				? sprintf( /* translators: %s: template title */ __( 'Variant product template “%s” is not published.', 'reactwoo-geo-optimise' ), $label )
				: sprintf( /* translators: %s: template ID */ __( 'Variant product template %s is not published.', 'reactwoo-geo-optimise' ), $label );
		}
		if ( $missing ) {
			return $named
				? sprintf( /* translators: %s: page title */ __( 'Variant page “%s” is missing. Visitors keep the original page.', 'reactwoo-geo-optimise' ), $label )
				: sprintf( /* translators: %s: page ID */ __( 'Variant page %s is missing. Visitors keep the original page.', 'reactwoo-geo-optimise' ), $label );
		}
		return $named
			? sprintf( /* translators: %s: page title */ __( 'Variant page “%s” is not published. Visitors keep the original page until you publish it.', 'reactwoo-geo-optimise' ), $label )
			: sprintf( /* translators: %s: page ID */ __( 'Variant page %s is not published. Visitors keep the original page until you publish it.', 'reactwoo-geo-optimise' ), $label );
	}

	/**
	 * @param int $page_pairs    Mapped page pairs.
	 * @param int $header_id     Variant header ID.
	 * @param int $footer_id     Variant footer ID.
	 * @param int $product_pairs Product template pairs.
	 * @return string
	 */
	private static function sitewide_health_summary( $page_pairs, $header_id, $footer_id, $product_pairs ) {
		$parts = array();
		if ( $page_pairs > 0 ) {
			$parts[] = sprintf(
				1 === $page_pairs
					? __( '%d page pair', 'reactwoo-geo-optimise' )
					: __( '%d page pairs', 'reactwoo-geo-optimise' ),
				$page_pairs
			);
		}
		if ( $header_id > 0 ) {
			$parts[] = __( 'variant header', 'reactwoo-geo-optimise' );
		}
		if ( $footer_id > 0 ) {
			$parts[] = __( 'variant footer', 'reactwoo-geo-optimise' );
		}
		if ( $product_pairs > 0 ) {
			$parts[] = sprintf(
				1 === $product_pairs
					? __( '%d product template pair', 'reactwoo-geo-optimise' )
					: __( '%d product template pairs', 'reactwoo-geo-optimise' ),
				$product_pairs
			);
		}
		return implode( ', ', $parts );
	}
}
