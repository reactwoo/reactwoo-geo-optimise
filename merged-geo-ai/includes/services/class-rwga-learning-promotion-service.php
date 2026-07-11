<?php
/**
 * Track phrase shapes eligible for parser/pattern promotion.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Learning_Promotion_Service {

	const OPTION_STATS = 'geo_ai_learning_promotion_stats';

	/**
	 * @param string $phrase_shape Phrase shape.
	 * @param string $outcome      accepted|executed|corrected|rejected.
	 * @return void
	 */
	public static function record_outcome( $phrase_shape, $outcome ) {
		$shape = trim( (string) $phrase_shape );
		if ( '' === $shape ) {
			return;
		}
		$stats = get_option( self::OPTION_STATS, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		if ( ! isset( $stats[ $shape ] ) || ! is_array( $stats[ $shape ] ) ) {
			$stats[ $shape ] = array(
				'success_count'    => 0,
				'rejection_count'  => 0,
				'correction_count' => 0,
			);
		}
		if ( in_array( $outcome, array( 'accepted', 'executed' ), true ) ) {
			++$stats[ $shape ]['success_count'];
		} elseif ( 'rejected' === $outcome ) {
			++$stats[ $shape ]['rejection_count'];
		} elseif ( 'corrected' === $outcome ) {
			++$stats[ $shape ]['correction_count'];
		}
		$stats[ $shape ]['last_used_at'] = gmdate( 'c' );
		update_option( self::OPTION_STATS, $stats, false );
	}

	/**
	 * @param string $phrase_shape Phrase shape.
	 * @return bool
	 */
	public static function is_promotion_candidate( $phrase_shape ) {
		$shape = trim( (string) $phrase_shape );
		if ( '' === $shape ) {
			return false;
		}
		$stats = get_option( self::OPTION_STATS, array() );
		if ( ! is_array( $stats ) || empty( $stats[ $shape ] ) ) {
			return false;
		}
		$row = $stats[ $shape ];
		return (int) ( $row['success_count'] ?? 0 ) >= 3
			&& (int) ( $row['rejection_count'] ?? 0 ) === 0
			&& (int) ( $row['correction_count'] ?? 0 ) <= 1;
	}

	/**
	 * @param string $phrase_shape Phrase shape.
	 * @return array<string,mixed>
	 */
	public static function stats_for( $phrase_shape ) {
		$stats = get_option( self::OPTION_STATS, array() );
		if ( ! is_array( $stats ) ) {
			return array();
		}
		return is_array( $stats[ $phrase_shape ] ?? null ) ? $stats[ $phrase_shape ] : array();
	}
}
