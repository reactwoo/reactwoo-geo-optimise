<?php
/**
 * Measurement / tracking readiness checklist before GTM handoff.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-experiment tracking preflight checks.
 */
class RWGO_Tracking_Preflight {

	/**
	 * Run checks for one experiment.
	 *
	 * @param \WP_Post             $exp_post Experiment post.
	 * @param array<string, mixed> $cfg      Config.
	 * @return array<string, mixed>
	 */
	public static function run( \WP_Post $exp_post, array $cfg ) {
		$checks = array();
		$key    = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
		$checks[] = self::check(
			'experiment_key',
			__( 'Experiment key', 'reactwoo-geo-optimise' ),
			'' !== $key,
			'' !== $key ? $key : __( 'Missing experiment key.', 'reactwoo-geo-optimise' )
		);

		$source = (int) ( $cfg['source_page_id'] ?? 0 );
		$var_b  = self::variant_b_page_id( $cfg );
		$checks[] = self::check(
			'control_page',
			__( 'Control page', 'reactwoo-geo-optimise' ),
			$source > 0 && get_post( $source ),
			$source > 0 ? (string) get_the_title( $source ) : __( 'No Control page bound.', 'reactwoo-geo-optimise' )
		);
		$checks[] = self::check(
			'variant_b_page',
			__( 'Variant B page', 'reactwoo-geo-optimise' ),
			$var_b > 0 && get_post( $var_b ),
			$var_b > 0 ? (string) get_the_title( $var_b ) : __( 'No Variant B page bound.', 'reactwoo-geo-optimise' )
		);

		$gtm_ready = class_exists( 'RWGO_GTM_Handoff', false ) && RWGO_GTM_Handoff::is_gtm_ready( $cfg );
		$checks[]  = self::check(
			'goal_handler',
			__( 'Goal + handler', 'reactwoo-geo-optimise' ),
			$gtm_ready,
			$gtm_ready
				? __( 'At least one goal/handler pair is configured.', 'reactwoo-geo-optimise' )
				: __( 'Configure a measurable goal with a handler.', 'reactwoo-geo-optimise' )
		);

		$manifest = class_exists( 'RWGO_Tracking_Manifest', false )
			? RWGO_Tracking_Manifest::build( $cfg, (int) $exp_post->ID )
			: array();
		$tracked  = isset( $manifest['tracked_elements'] ) && is_array( $manifest['tracked_elements'] )
			? $manifest['tracked_elements']
			: array();
		$with_keys = 0;
		foreach ( $tracked as $row ) {
			if ( is_array( $row ) && ! empty( $row['semantic_key'] ) ) {
				++$with_keys;
			}
		}
		$checks[] = self::check(
			'element_keys',
			__( 'Element keys', 'reactwoo-geo-optimise' ),
			$with_keys > 0,
			$with_keys > 0
				? sprintf(
					/* translators: %d: count of tracked elements with semantic keys */
					_n( '%d tracked element has a semantic key.', '%d tracked elements have semantic keys.', $with_keys, 'reactwoo-geo-optimise' ),
					$with_keys
				)
				: __( 'No semantic element keys found yet — stamp goals in Elementor or sync keys.', 'reactwoo-geo-optimise' )
		);

		$js_ok = defined( 'RWGO_PATH' ) && is_readable( RWGO_PATH . 'assets/js/rwgo-tracking.js' );
		$checks[] = self::check(
			'tracking_js',
			__( 'Front-end tracking script', 'reactwoo-geo-optimise' ),
			$js_ok,
			$js_ok
				? __( 'rwgo-tracking.js is present.', 'reactwoo-geo-optimise' )
				: __( 'Tracking script file is missing.', 'reactwoo-geo-optimise' )
		);

		$status = isset( $cfg['status'] ) ? sanitize_key( (string) $cfg['status'] ) : '';
		$checks[] = self::check(
			'status',
			__( 'Test status', 'reactwoo-geo-optimise' ),
			in_array( $status, array( 'running', 'active', 'paused' ), true ) || '' !== $status,
			'' !== $status ? $status : __( 'Status not set.', 'reactwoo-geo-optimise' ),
			! in_array( $status, array( 'draft', '' ), true )
		);

		$pass = 0;
		$warn = 0;
		$fail = 0;
		foreach ( $checks as $c ) {
			if ( empty( $c['ok'] ) ) {
				++$fail;
			} elseif ( ! empty( $c['warn'] ) ) {
				++$warn;
			} else {
				++$pass;
			}
		}
		$ready = 0 === $fail && $gtm_ready && $with_keys > 0;

		$result = array(
			'experiment_id'  => (int) $exp_post->ID,
			'experiment_key' => $key,
			'ready'          => $ready,
			'pass'           => $pass,
			'warn'           => $warn,
			'fail'           => $fail,
			'checks'         => $checks,
			'manifest'       => $manifest,
		);

		/**
		 * @param array<string, mixed> $result Preflight result.
		 * @param array<string, mixed> $cfg    Config.
		 */
		return apply_filters( 'rwgo_tracking_preflight', $result, $cfg );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return int
	 */
	public static function variant_b_page_id( array $cfg ) {
		if ( ! empty( $cfg['variant_b_page_id'] ) ) {
			return (int) $cfg['variant_b_page_id'];
		}
		if ( empty( $cfg['variants'] ) || ! is_array( $cfg['variants'] ) ) {
			return 0;
		}
		foreach ( $cfg['variants'] as $row ) {
			if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
				return (int) ( $row['page_id'] ?? 0 );
			}
		}
		return 0;
	}

	/**
	 * @param string $id      Check id.
	 * @param string $label   Label.
	 * @param bool   $ok      Pass.
	 * @param string $detail  Detail.
	 * @param bool   $strict_ok When false and ok, treat as warn.
	 * @return array<string, mixed>
	 */
	private static function check( $id, $label, $ok, $detail, $strict_ok = true ) {
		$ok = (bool) $ok;
		return array(
			'id'     => sanitize_key( (string) $id ),
			'label'  => (string) $label,
			'ok'     => $ok,
			'warn'   => $ok && ! $strict_ok,
			'detail' => (string) $detail,
		);
	}
}
