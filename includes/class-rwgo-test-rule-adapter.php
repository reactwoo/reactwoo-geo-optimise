<?php
/**
 * Adapt portable visibility rules for A/B test audience targeting.
 *
 * Page-bound conditions (e.g. page version URLs) apply to Geo routing, not who
 * enters a test on Control's URL. This adapter strips them for evaluation and UI hints.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule analysis and audience-only transforms for Create / Edit Test.
 */
class RWGO_Test_Rule_Adapter {

	/**
	 * Condition types that bind to a specific page URL / version (not visitor audience).
	 *
	 * @return string[]
	 */
	public static function get_page_bound_condition_types() {
		$types = array( 'page_version_url' );
		/**
		 * Condition types ignored when evaluating saved rules for experiment entry.
		 *
		 * @param string[] $types Default page-bound slugs.
		 */
		return array_values( array_unique( array_map( 'sanitize_key', (array) apply_filters( 'rwgo_test_page_bound_condition_types', $types ) ) ) );
	}

	/**
	 * @param int $rule_id Visibility rule post ID.
	 * @return array<string, mixed>
	 */
	public static function analyze_rule( $rule_id ) {
		$rule_id = absint( $rule_id );
		$empty   = array(
			'rule_id'              => $rule_id,
			'has_page_bound'       => false,
			'page_bound_labels'    => array(),
			'audience_summary'     => '',
			'has_audience'         => false,
			'fit_for_test'         => false,
			'audience_only_default'=> true,
		);
		if ( $rule_id <= 0 || ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return $empty;
		}
		$set = RWGC_Visibility_Rule_Repository::get_rule_set( $rule_id );
		if ( ! is_array( $set ) ) {
			return $empty;
		}
		$page_bound = self::collect_page_bound_labels( $set );
		$audience   = self::to_audience_rule_set( $set );
		$summary    = '';
		if ( class_exists( 'RWGC_Experience_Workflow', false ) ) {
			$summary = RWGC_Experience_Workflow::summarize_rule_set( $audience );
		}
		$has_audience = self::rule_set_has_conditions( $audience );
		return array(
			'rule_id'               => $rule_id,
			'has_page_bound'        => ! empty( $page_bound ),
			'page_bound_labels'     => $page_bound,
			'audience_summary'      => $summary,
			'has_audience'          => $has_audience,
			'fit_for_test'          => $has_audience,
			'audience_only_default' => true,
		);
	}

	/**
	 * @param array<int, array{id:int,title:string,summary:string}> $rows Library rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function enrich_library_rows( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$id       = (int) $row['id'];
			$analysis = self::analyze_rule( $id );
			$out[]    = array_merge(
				$row,
				array(
					'has_page_bound'    => ! empty( $analysis['has_page_bound'] ),
					'audience_summary'  => (string) ( $analysis['audience_summary'] ?? '' ),
					'fit_for_test'      => ! empty( $analysis['fit_for_test'] ),
					'page_bound_labels' => isset( $analysis['page_bound_labels'] ) && is_array( $analysis['page_bound_labels'] ) ? $analysis['page_bound_labels'] : array(),
				)
			);
		}
		return $out;
	}

	/**
	 * Rule set for experiment targeting evaluation.
	 *
	 * @param array<string, mixed> $targeting Experiment targeting config.
	 * @return array<string, mixed>|null Null when rule missing or empty audience slice.
	 */
	public static function get_evaluation_rule_set( array $targeting ) {
		$rule_id = isset( $targeting['visibility_rule_id'] ) ? absint( $targeting['visibility_rule_id'] ) : 0;
		if ( $rule_id <= 0 || ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return null;
		}
		$set = RWGC_Visibility_Rule_Repository::get_rule_set( $rule_id );
		if ( ! is_array( $set ) || empty( $set['enabled'] ) ) {
			return null;
		}
		$audience_only = ! isset( $targeting['audience_only'] ) || ! empty( $targeting['audience_only'] );
		if ( ! $audience_only ) {
			return $set;
		}
		$audience = self::to_audience_rule_set( $set );
		return self::rule_set_has_conditions( $audience ) ? $audience : null;
	}

	/**
	 * @param array<string, mixed>|null $set Rule set.
	 * @return bool
	 */
	public static function rule_set_has_page_bound( $set ) {
		return ! empty( self::collect_page_bound_labels( is_array( $set ) ? $set : array() ) );
	}

	/**
	 * @param array<string, mixed> $set Portable rule set.
	 * @return array<string, mixed>
	 */
	public static function to_audience_rule_set( array $set ) {
		$page_types = self::get_page_bound_condition_types();
		$rules      = isset( $set['rules'] ) && is_array( $set['rules'] ) ? $set['rules'] : array();
		$new_rules  = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
			$kept       = array();
			foreach ( $conditions as $cond ) {
				if ( ! is_array( $cond ) || empty( $cond['type'] ) ) {
					continue;
				}
				$type = sanitize_key( (string) $cond['type'] );
				if ( in_array( $type, $page_types, true ) ) {
					continue;
				}
				$kept[] = $cond;
			}
			if ( empty( $kept ) ) {
				continue;
			}
			$rule['conditions'] = $kept;
			$new_rules[]          = $rule;
		}

		$out = $set;
		$out['rules'] = $new_rules;
		if ( empty( $new_rules ) ) {
			$out['enabled'] = false;
		}
		return $out;
	}

	/**
	 * Save an audience-only copy of a library rule for reuse in tests.
	 *
	 * @param int    $source_rule_id Source rule post ID.
	 * @param string $suffix         Optional title suffix.
	 * @return int|\WP_Error New rule post ID.
	 */
	public static function save_audience_copy( $source_rule_id, $suffix = '' ) {
		$source_rule_id = absint( $source_rule_id );
		if ( $source_rule_id <= 0 || ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return new WP_Error( 'rwgo_rule_copy', __( 'Source rule not found.', 'reactwoo-geo-optimise' ) );
		}
		$post = RWGC_Visibility_Rule_Repository::get_post( $source_rule_id );
		if ( ! $post ) {
			return new WP_Error( 'rwgo_rule_copy', __( 'Source rule not found.', 'reactwoo-geo-optimise' ) );
		}
		$set = RWGC_Visibility_Rule_Repository::get_rule_set( $source_rule_id );
		if ( ! is_array( $set ) ) {
			return new WP_Error( 'rwgo_rule_copy', __( 'Could not read the source rule.', 'reactwoo-geo-optimise' ) );
		}
		$audience = self::to_audience_rule_set( $set );
		if ( ! self::rule_set_has_conditions( $audience ) ) {
			return new WP_Error(
				'rwgo_rule_copy_empty',
				__( 'This rule has no visitor conditions after removing page URL rules. Create a new audience rule instead.', 'reactwoo-geo-optimise' )
			);
		}
		if ( class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			$san = RWGC_Targeting_Rule_Set_Schema::sanitize( $audience );
			if ( ! is_array( $san ) ) {
				return new WP_Error( 'rwgo_rule_copy', __( 'Audience rule could not be sanitized.', 'reactwoo-geo-optimise' ) );
			}
			$audience = $san;
		}
		$json = wp_json_encode( $audience );
		if ( ! is_string( $json ) || '' === $json ) {
			return new WP_Error( 'rwgo_rule_copy', __( 'Audience rule could not be encoded.', 'reactwoo-geo-optimise' ) );
		}
		$title = get_the_title( $post );
		if ( '' === trim( $suffix ) ) {
			$suffix = __( '(test audience)', 'reactwoo-geo-optimise' );
		}
		$title = trim( $title . ' ' . $suffix );
		$new_id = RWGC_Visibility_Rule_Repository::save( $title, 'publish', $json, 0 );
		if ( $new_id <= 0 ) {
			return new WP_Error( 'rwgo_rule_copy', __( 'Could not save the audience rule copy.', 'reactwoo-geo-optimise' ) );
		}
		if ( class_exists( 'RWGC_Variant_Rule_Applications', false ) ) {
			RWGC_Variant_Rule_Applications::save_provenance(
				$new_id,
				array(
					'sourceType'   => 'geo_optimise_test',
					'createdFrom'  => 'audience_rule_copy',
					'sourceRuleId' => $source_rule_id,
				)
			);
		}
		return $new_id;
	}

	/**
	 * @param array<string, mixed> $set Rule set.
	 * @return bool
	 */
	private static function rule_set_has_conditions( array $set ) {
		$rules = isset( $set['rules'] ) && is_array( $set['rules'] ) ? $set['rules'] : array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
				continue;
			}
			foreach ( $rule['conditions'] as $cond ) {
				if ( is_array( $cond ) && ! empty( $cond['type'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $set Rule set.
	 * @return string[]
	 */
	private static function collect_page_bound_labels( array $set ) {
		$page_types = self::get_page_bound_condition_types();
		$labels     = array();
		$rules      = isset( $set['rules'] ) && is_array( $set['rules'] ) ? $set['rules'] : array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
				continue;
			}
			foreach ( $rule['conditions'] as $cond ) {
				if ( ! is_array( $cond ) || empty( $cond['type'] ) ) {
					continue;
				}
				$type = sanitize_key( (string) $cond['type'] );
				if ( ! in_array( $type, $page_types, true ) ) {
					continue;
				}
				$labels[] = self::human_condition_label( $type, $cond );
			}
		}
		return array_values( array_unique( array_filter( $labels ) ) );
	}

	/**
	 * @param string               $type Condition type.
	 * @param array<string, mixed> $cond Condition row.
	 * @return string
	 */
	private static function human_condition_label( $type, array $cond ) {
		if ( 'page_version_url' === $type && class_exists( 'RWGC_Page_Version', false ) ) {
			$val = RWGC_Page_Version::sanitize_condition_value( $cond['value'] ?? null );
			if ( is_array( $val ) && ! empty( $val['page_id'] ) ) {
				$page_title = get_the_title( (int) $val['page_id'] );
				$version    = isset( $val['version'] ) ? (string) $val['version'] : '';
				if ( '' !== $page_title && '' !== $version ) {
					return sprintf(
						/* translators: 1: page title, 2: version slug */
						__( 'Page version: %1$s / %2$s', 'reactwoo-geo-optimise' ),
						$page_title,
						$version
					);
				}
			}
		}
		return ucfirst( str_replace( '_', ' ', $type ) );
	}
}
