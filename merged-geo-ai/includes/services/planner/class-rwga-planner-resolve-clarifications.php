<?php
/**
 * Detect plan-level clarifications (page mismatch, variant grouping).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Resolve_Clarifications {

	/**
	 * @param array<int,array<string,mixed>> $actions      Built actions.
	 * @param string                         $phrase         Full phrase.
	 * @param array<string,mixed>            $page_context   Page context.
	 * @param array<int,array>               $entities       Entities.
	 * @return array<string,mixed>|null
	 */
	public static function detect( array $actions, $phrase, array $page_context, array $entities ) {
		if ( ! empty( $page_context['has_mismatch'] ) ) {
			$variant_page  = self::page_label( (string) ( $page_context['variant_source'] ?? '' ) );
			$original_page = self::page_label( (string) ( $page_context['original_page'] ?? 'homepage' ) );
			return array(
				'type'    => 'page_mismatch',
				'message' => sprintf(
					/* translators: 1: variant page, 2: original page */
					__( 'You mentioned variants of the %1$s, but then said the original %2$s should show in England. Should the original page also be the %1$s, or did you mean the %2$s?', 'reactwoo-geo-ai' ),
					$variant_page,
					$original_page
				),
				'options' => array(
					array(
						'label' => sprintf(
							/* translators: %s: page label */
							__( 'Use original %s', 'reactwoo-geo-ai' ),
							$variant_page
						),
						'value' => 'original_' . sanitize_key( str_replace( ' ', '_', $variant_page ) ),
					),
					array(
						'label' => sprintf(
							/* translators: %s: page label */
							__( 'Use %s', 'reactwoo-geo-ai' ),
							$original_page
						),
						'value' => sanitize_key( str_replace( ' ', '_', $original_page ) ),
					),
				),
			);
		}

		if ( class_exists( 'RWGA_Variant_Group_Extractor', false )
			&& RWGA_Variant_Group_Extractor::is_ambiguous_grouping( $phrase, $entities )
			&& empty( $actions ) ) {
			return array(
				'type'    => 'variant_grouping',
				'message' => __( 'User did not say whether this means one shared variant or three separate variants.', 'reactwoo-geo-ai' ),
				'options' => array(
					array(
						'label' => __( 'One shared variant', 'reactwoo-geocore' ),
						'value' => 'shared_variant',
					),
					array(
						'label' => __( 'Separate variants per country', 'reactwoo-geocore' ),
						'value' => 'separate_variants',
					),
				),
			);
		}

		return null;
	}

	/**
	 * @param string $page_ref Page ref.
	 * @return string
	 */
	private static function page_label( $page_ref ) {
		if ( 'shop' === $page_ref ) {
			return __( 'shop page', 'reactwoo-geocore' );
		}
		if ( 'homepage' === $page_ref ) {
			return __( 'homepage', 'reactwoo-geocore' );
		}
		return $page_ref;
	}
}
