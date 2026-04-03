<?php
/**
 * Public helpers for Geo Optimise.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return sticky experiment variant (cookie); assigns on first call per experiment.
 *
 * @param string          $experiment_slug Key for the experiment.
 * @param list<string>|null    $variants Allowed values; default A and B.
 * @param list<float|int>|null $weights  Optional weights (same length as variants).
 * @return string
 */
function rwgo_get_variant( $experiment_slug, $variants = null, $weights = null ) {
	if ( ! class_exists( 'RWGO_Assignment', false ) ) {
		if ( null === $variants ) {
			$variants = array( 'A', 'B' );
		}
		return is_array( $variants ) && isset( $variants[0] ) ? (string) $variants[0] : '';
	}
	return RWGO_Assignment::get_variant( $experiment_slug, $variants, $weights );
}

/**
 * All sticky experiment assignments (cookie). No admin UI.
 *
 * @return array<string, string>
 */
function rwgo_get_assignment_map() {
	if ( ! class_exists( 'RWGO_Assignment', false ) ) {
		return array();
	}
	return RWGO_Assignment::get_map();
}
