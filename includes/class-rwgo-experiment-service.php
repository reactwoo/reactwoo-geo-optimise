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
	 * Weights aligned with assignment_variant_slugs default.
	 *
	 * @param array<string, mixed> $config Config.
	 * @return list<float>
	 */
	public static function assignment_weights( array $config ) {
		$v = isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array();
		$w = array( 0.5, 0.5 );
		if ( isset( $v[0]['weight'] ) ) {
			$w[0] = (float) $v[0]['weight'];
		}
		if ( isset( $v[1]['weight'] ) ) {
			$w[1] = (float) $v[1]['weight'];
		}
		return $w;
	}

	/**
	 * Resolve page ID for a variant_id.
	 *
	 * @param array<string, mixed> $config Repository config.
	 * @param string               $variant_id control|var_b.
	 * @return int 0 if unknown.
	 */
	public static function page_id_for_variant( array $config, $variant_id ) {
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
	 * Whether Variant B exists and can be used for assignment / redirects (incomplete tests stay on Control only).
	 *
	 * @param array<string, mixed> $config Repository config.
	 * @return bool
	 */
	public static function variant_b_is_routable( array $config ) {
		$id = self::page_id_for_variant( $config, 'var_b' );
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
	public static function resolve_variant_for_context( array $config, $queried_post_id ) {
		$queried_post_id = (int) $queried_post_id;
		$key             = isset( $config['experiment_key'] ) ? sanitize_key( (string) $config['experiment_key'] ) : '';
		if ( '' === $key ) {
			return '';
		}
		$source = (int) ( $config['source_page_id'] ?? 0 );
		if ( $queried_post_id === $source ) {
			if ( ! self::variant_b_is_routable( $config ) ) {
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
}
