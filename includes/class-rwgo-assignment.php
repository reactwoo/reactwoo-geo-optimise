<?php
/**
 * Sticky A/B-style variant assignment (cookie-backed; no duplicate geo engine).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pick and persist a variant slug for experiments / CRO.
 */
class RWGO_Assignment {

	const COOKIE = 'rwgo_ab';

	/**
	 * Current experiment → variant map from cookie (empty if none).
	 *
	 * @return array<string, string>
	 */
	public static function get_map() {
		return self::read_map();
	}

	/**
	 * @param string               $experiment_slug Alphanumeric key (sanitized).
	 * @param list<string>|null    $variants Allowed variants; default A/B.
	 * @param list<float|int>|null $weights  Optional weights (same count as $variants); first assignment only.
	 * @return string One of $variants.
	 */
	public static function get_variant( $experiment_slug, $variants = null, $weights = null ) {
		if ( null === $variants ) {
			$variants = array( 'A', 'B' );
		}
		if ( ! is_array( $variants ) || empty( $variants ) ) {
			return '';
		}
		$variants = array_values( array_filter( array_map( 'strval', $variants ) ) );
		if ( empty( $variants ) ) {
			return '';
		}
		$slug = sanitize_key( (string) $experiment_slug );
		if ( '' === $slug ) {
			return $variants[0];
		}

		$map = self::read_map();
		if ( isset( $map[ $slug ] ) && in_array( (string) $map[ $slug ], $variants, true ) ) {
			return (string) $map[ $slug ];
		}

		$pick = self::pick_variant( $variants, $weights );
		$map[ $slug ] = $pick;
		self::persist( $map );
		self::bump_assignment_count();
		self::bump_experiment_distribution( $slug, $pick );

		/**
		 * Visitor was assigned to a variant for an experiment.
		 *
		 * @param string       $slug      Experiment slug.
		 * @param string       $variant   Chosen variant.
		 * @param list<string> $variants  Pool.
		 */
		do_action( 'rwgo_variant_assigned', $slug, $pick, $variants );

		return $pick;
	}

	/**
	 * @return array<string, string>
	 */
	private static function read_map() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) || ! is_string( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}
		$raw = wp_unslash( $_COOKIE[ self::COOKIE ] );
		$dec = json_decode( $raw, true );
		if ( ! is_array( $dec ) ) {
			return array();
		}
		$out = array();
		foreach ( $dec as $k => $v ) {
			if ( is_string( $k ) && is_string( $v ) ) {
				$out[ sanitize_key( $k ) ] = $v;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, string> $map Experiment => variant.
	 * @return void
	 */
	private static function persist( $map ) {
		if ( headers_sent() ) {
			return;
		}
		$json = wp_json_encode( $map );
		if ( ! is_string( $json ) ) {
			return;
		}
		$path = COOKIEPATH ? COOKIEPATH : '/';
		$dom  = COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
		setcookie( self::COOKIE, $json, time() + 30 * DAY_IN_SECONDS, $path, $dom, is_ssl(), true );
		// Same-request reads in PHP.
		$_COOKIE[ self::COOKIE ] = $json;
	}

	/**
	 * @return void
	 */
	private static function bump_assignment_count() {
		$n = (int) get_option( 'rwgo_assignment_count', 0 );
		update_option( 'rwgo_assignment_count', $n + 1, false );
	}

	/**
	 * Aggregate first-time assignments per experiment and variant (wp-admin reporting).
	 *
	 * @param string $slug    Sanitized experiment slug.
	 * @param string $variant Assigned variant.
	 * @return void
	 */
	private static function bump_experiment_distribution( $slug, $variant ) {
		$slug    = sanitize_key( (string) $slug );
		$variant = is_string( $variant ) ? $variant : (string) $variant;
		if ( '' === $slug || '' === $variant ) {
			return;
		}
		$opt = get_option( 'rwgo_experiment_variant_counts', array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}
		if ( ! isset( $opt[ $slug ] ) || ! is_array( $opt[ $slug ] ) ) {
			$opt[ $slug ] = array();
		}
		$cur = isset( $opt[ $slug ][ $variant ] ) ? (int) $opt[ $slug ][ $variant ] : 0;
		$opt[ $slug ][ $variant ] = $cur + 1;
		update_option( 'rwgo_experiment_variant_counts', $opt, false );
	}

	/**
	 * @param list<string>         $variants Variants.
	 * @param list<float|int>|null $weights  Same length as variants, or null for uniform random.
	 * @return string
	 */
	private static function pick_variant( array $variants, $weights ) {
		$n = count( $variants );
		if ( $n === 1 ) {
			return $variants[0];
		}
		if ( ! is_array( $weights ) || count( $weights ) !== $n ) {
			return $variants[ wp_rand( 0, $n - 1 ) ];
		}
		$w = array_map( 'floatval', $weights );
		$total = array_sum( $w );
		if ( $total <= 0 ) {
			return $variants[0];
		}
		// Random in (0, total] with cumulative weights.
		$r = ( wp_rand( 1, 1000000 ) / 1000000 ) * $total;
		$cum = 0.0;
		foreach ( $variants as $i => $v ) {
			$cum += isset( $w[ $i ] ) ? (float) $w[ $i ] : 0.0;
			if ( $r <= $cum ) {
				return $v;
			}
		}
		return $variants[ $n - 1 ];
	}
}
