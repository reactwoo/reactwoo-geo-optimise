<?php
/**
 * Winner policy gates: sample size, exposure denominator, two-proportion significance.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Statistical / operational gates before recommending promotion.
 */
class RWGO_Winner_Policy {

	const DEFAULT_MIN_SAMPLE = 100;
	const DEFAULT_MIN_CONVERSIONS = 10;
	const DEFAULT_ALPHA = 0.05;

	/**
	 * Resolve policy settings from experiment config + filters.
	 *
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return array{min_sample:int,min_conversions:int,alpha:float,use_exposures:bool,enforce:bool}
	 */
	public static function settings( array $cfg ) {
		$raw = isset( $cfg['winner_policy'] ) && is_array( $cfg['winner_policy'] ) ? $cfg['winner_policy'] : array();
		$min_sample = isset( $raw['min_sample'] ) ? (int) $raw['min_sample'] : self::DEFAULT_MIN_SAMPLE;
		$min_conv   = isset( $raw['min_conversions'] ) ? (int) $raw['min_conversions'] : self::DEFAULT_MIN_CONVERSIONS;
		$alpha      = isset( $raw['alpha'] ) ? (float) $raw['alpha'] : self::DEFAULT_ALPHA;
		$use_exp    = array_key_exists( 'use_exposures', $raw ) ? (bool) $raw['use_exposures'] : true;
		$enforce    = ! empty( $raw['enforce'] );

		$min_sample = max( 1, (int) apply_filters( 'rwgo_winner_min_sample', $min_sample, $cfg ) );
		$min_conv   = max( 0, (int) apply_filters( 'rwgo_winner_min_conversions', $min_conv, $cfg ) );
		$alpha      = (float) apply_filters( 'rwgo_winner_alpha', $alpha, $cfg );
		if ( $alpha <= 0 || $alpha >= 1 ) {
			$alpha = self::DEFAULT_ALPHA;
		}
		$use_exp = (bool) apply_filters( 'rwgo_winner_use_exposures', $use_exp, $cfg );
		$enforce = (bool) apply_filters( 'rwgo_winner_policy_enforce', $enforce, $cfg );

		return array(
			'min_sample'      => $min_sample,
			'min_conversions' => $min_conv,
			'alpha'           => $alpha,
			'use_exposures'   => $use_exp,
			'enforce'         => $enforce,
		);
	}

	/**
	 * Evaluate gates from a Winner_Service analysis payload (variants rows already filled).
	 *
	 * @param array<string, mixed> $analysis From RWGO_Winner_Service::analyze().
	 * @param array<string, mixed> $cfg      Experiment config.
	 * @param string               $experiment_key Key.
	 * @return array<string, mixed>
	 */
	public static function evaluate( array $analysis, array $cfg, $experiment_key ) {
		$settings = self::settings( $cfg );
		$variants = isset( $analysis['variants'] ) && is_array( $analysis['variants'] ) ? $analysis['variants'] : array();
		$control  = isset( $variants['control'] ) && is_array( $variants['control'] ) ? $variants['control'] : null;
		$var_b    = isset( $variants['var_b'] ) && is_array( $variants['var_b'] ) ? $variants['var_b'] : null;

		$gates = array();
		$ready = true;

		if ( empty( $analysis['conversion_mode'] ) ) {
			$ready = false;
			$gates[] = self::gate( 'conversion_mode', false, __( 'Conversion measurement is not active for this test.', 'reactwoo-geo-optimise' ) );
		} else {
			$gates[] = self::gate( 'conversion_mode', true, __( 'Conversion measurement is active.', 'reactwoo-geo-optimise' ) );
		}

		$denom_source = 'assignments';
		$n_c = $control ? (int) ( $control['assignments'] ?? 0 ) : 0;
		$n_b = $var_b ? (int) ( $var_b['assignments'] ?? 0 ) : 0;
		$c_c = $control ? (int) ( $control['completions'] ?? 0 ) : 0;
		$c_b = $var_b ? (int) ( $var_b['completions'] ?? 0 ) : 0;

		if ( $settings['use_exposures'] && class_exists( 'RWGO_Event_Store', false ) && '' !== (string) $experiment_key ) {
			$exp_counts = RWGO_Event_Store::count_exposures_by_variant( (string) $experiment_key );
			$e_c = isset( $exp_counts['control'] ) ? (int) $exp_counts['control'] : 0;
			$e_b = isset( $exp_counts['var_b'] ) ? (int) $exp_counts['var_b'] : 0;
			if ( $e_c > 0 && $e_b > 0 ) {
				$n_c          = $e_c;
				$n_b          = $e_b;
				$denom_source = 'exposures';
			}
		}

		$sample_ok = ( null !== $control && null !== $var_b && $n_c >= $settings['min_sample'] && $n_b >= $settings['min_sample'] );
		if ( ! $sample_ok ) {
			$ready = false;
		}
		$gates[] = self::gate(
			'sample_size',
			$sample_ok,
			sprintf(
				/* translators: 1: control n, 2: variant B n, 3: required min, 4: denominator source */
				__( 'Sample — Control: %1$d, Variant B: %2$d (need ≥ %3$d each, via %4$s).', 'reactwoo-geo-optimise' ),
				$n_c,
				$n_b,
				$settings['min_sample'],
				'exposures' === $denom_source ? __( 'exposures', 'reactwoo-geo-optimise' ) : __( 'assignments', 'reactwoo-geo-optimise' )
			)
		);

		$total_conv = $c_c + $c_b;
		$conv_ok    = $total_conv >= $settings['min_conversions'];
		if ( ! $conv_ok ) {
			$ready = false;
		}
		$gates[] = self::gate(
			'min_conversions',
			$conv_ok,
			sprintf(
				/* translators: 1: total conversions, 2: required min */
				__( 'Conversions — %1$d total (need ≥ %2$d).', 'reactwoo-geo-optimise' ),
				$total_conv,
				$settings['min_conversions']
			)
		);

		$lead = isset( $analysis['leading_variant'] ) ? sanitize_key( (string) $analysis['leading_variant'] ) : '';
		$lead_ok = ( 'var_b' === $lead || 'control' === $lead );
		if ( ! $lead_ok ) {
			$ready = false;
		}
		$gates[] = self::gate(
			'leader',
			$lead_ok && '' !== $lead,
			'' !== $lead
				? sprintf(
					/* translators: %s: variant slug */
					__( 'Leading variant: %s.', 'reactwoo-geo-optimise' ),
					$lead
				)
				: __( 'No leading variant yet.', 'reactwoo-geo-optimise' )
		);

		$sig = null;
		$sig_ok = false;
		if ( $n_c > 0 && $n_b > 0 && $sample_ok ) {
			$sig = self::two_proportion_test( $c_c, $n_c, $c_b, $n_b, $settings['alpha'] );
			$sig_ok = ! empty( $sig['significant'] );
		}
		if ( ! $sig_ok ) {
			$ready = false;
		}
		$p_txt = is_array( $sig ) && isset( $sig['p_value'] )
			? number_format_i18n( (float) $sig['p_value'], 4 )
			: '—';
		$gates[] = self::gate(
			'significance',
			$sig_ok,
			sprintf(
				/* translators: 1: p-value, 2: alpha */
				__( 'Two-proportion significance — p=%1$s (α=%2$s).', 'reactwoo-geo-optimise' ),
				$p_txt,
				(string) $settings['alpha']
			)
		);

		// Ready to promote when Variant B leads and all gates pass (Control lead = keep Control; no promote needed).
		$ready_to_promote = $ready && 'var_b' === $lead;
		$ready_to_declare = $ready && ( 'var_b' === $lead || 'control' === $lead );

		$result = array(
			'settings'           => $settings,
			'denominator_source' => $denom_source,
			'n_control'          => $n_c,
			'n_var_b'            => $n_b,
			'conversions_control'=> $c_c,
			'conversions_var_b'  => $c_b,
			'rate_control'       => $n_c > 0 ? ( $c_c / $n_c ) : 0.0,
			'rate_var_b'         => $n_b > 0 ? ( $c_b / $n_b ) : 0.0,
			'significance'       => $sig,
			'gates'              => $gates,
			'ready'              => $ready_to_declare,
			'ready_to_promote'   => $ready_to_promote,
			'enforce'            => $settings['enforce'],
			'summary'            => self::summary_line( $ready_to_declare, $ready_to_promote, $lead, $sig_ok ),
		);

		/**
		 * @param array<string, mixed> $result Policy result.
		 * @param array<string, mixed> $analysis Analysis.
		 * @param array<string, mixed> $cfg Config.
		 */
		return apply_filters( 'rwgo_winner_policy_result', $result, $analysis, $cfg );
	}

	/**
	 * Two-proportion z-test (two-sided).
	 *
	 * @param int   $s1 Successes A.
	 * @param int   $n1 Trials A.
	 * @param int   $s2 Successes B.
	 * @param int   $n2 Trials B.
	 * @param float $alpha Alpha.
	 * @return array{z:float,p_value:float,significant:bool,alpha:float}
	 */
	public static function two_proportion_test( $s1, $n1, $s2, $n2, $alpha = 0.05 ) {
		$s1 = max( 0, (int) $s1 );
		$n1 = max( 1, (int) $n1 );
		$s2 = max( 0, (int) $s2 );
		$n2 = max( 1, (int) $n2 );
		$alpha = (float) $alpha;
		if ( $alpha <= 0 || $alpha >= 1 ) {
			$alpha = self::DEFAULT_ALPHA;
		}

		$p1 = $s1 / $n1;
		$p2 = $s2 / $n2;
		$p  = ( $s1 + $s2 ) / ( $n1 + $n2 );
		if ( $p <= 0.0 || $p >= 1.0 ) {
			return array(
				'z'           => 0.0,
				'p_value'     => 1.0,
				'significant' => false,
				'alpha'       => $alpha,
			);
		}
		$se = sqrt( $p * ( 1.0 - $p ) * ( ( 1.0 / $n1 ) + ( 1.0 / $n2 ) ) );
		if ( $se <= 0.0 ) {
			return array(
				'z'           => 0.0,
				'p_value'     => 1.0,
				'significant' => false,
				'alpha'       => $alpha,
			);
		}
		$z       = ( $p2 - $p1 ) / $se;
		$p_value = 2.0 * ( 1.0 - self::standard_normal_cdf( abs( $z ) ) );
		if ( $p_value < 0.0 ) {
			$p_value = 0.0;
		}
		if ( $p_value > 1.0 ) {
			$p_value = 1.0;
		}

		return array(
			'z'           => $z,
			'p_value'     => $p_value,
			'significant' => ( $p_value < $alpha ),
			'alpha'       => $alpha,
		);
	}

	/**
	 * Standard normal CDF via erf approximation.
	 *
	 * @param float $x Value.
	 * @return float
	 */
	public static function standard_normal_cdf( $x ) {
		$x = (float) $x;
		return 0.5 * ( 1.0 + self::erf( $x / sqrt( 2.0 ) ) );
	}

	/**
	 * Error function approximation (Abramowitz & Stegun 7.1.26).
	 *
	 * @param float $x Value.
	 * @return float
	 */
	public static function erf( $x ) {
		$x = (float) $x;
		$sign = $x < 0 ? -1.0 : 1.0;
		$x    = abs( $x );
		$a1 = 0.254829592;
		$a2 = -0.284496736;
		$a3 = 1.421413741;
		$a4 = -1.453152027;
		$a5 = 1.061405429;
		$p  = 0.3275911;
		$t  = 1.0 / ( 1.0 + $p * $x );
		$y  = 1.0 - ( ( ( ( ( $a5 * $t + $a4 ) * $t ) + $a3 ) * $t + $a2 ) * $t + $a1 ) * $t * exp( -$x * $x );
		return $sign * $y;
	}

	/**
	 * @param string $id     Gate id.
	 * @param bool   $ok     Pass.
	 * @param string $detail Detail.
	 * @return array{id:string,ok:bool,detail:string}
	 */
	private static function gate( $id, $ok, $detail ) {
		return array(
			'id'     => sanitize_key( (string) $id ),
			'ok'     => (bool) $ok,
			'detail' => (string) $detail,
		);
	}

	/**
	 * @param bool   $ready_declare Ready to declare.
	 * @param bool   $ready_promote Ready to promote B.
	 * @param string $lead          Lead slug.
	 * @param bool   $sig_ok        Significant.
	 * @return string
	 */
	private static function summary_line( $ready_declare, $ready_promote, $lead, $sig_ok ) {
		if ( $ready_promote ) {
			return __( 'Winner policy: Variant B is significant — ready to promote.', 'reactwoo-geo-optimise' );
		}
		if ( $ready_declare && 'control' === $lead ) {
			return __( 'Winner policy: Control is significant — keep primary; no Variant B promotion needed.', 'reactwoo-geo-optimise' );
		}
		if ( ! $sig_ok ) {
			return __( 'Winner policy: not yet significant — keep collecting traffic.', 'reactwoo-geo-optimise' );
		}
		return __( 'Winner policy: gates incomplete.', 'reactwoo-geo-optimise' );
	}
}
