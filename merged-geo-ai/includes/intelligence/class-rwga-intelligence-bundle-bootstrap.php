<?php
/**
 * Essential intelligence entities/patterns merged into every local bundle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Intelligence_Bundle_Bootstrap {

	/**
	 * @param array<string,mixed>|null $bundle Raw bundle.
	 * @return array<string,mixed>|null
	 */
	public static function augment( $bundle ) {
		if ( ! is_array( $bundle ) ) {
			$bundle = self::minimal_shell();
		}
		$bundle['entities']        = self::merge_entities( $bundle['entities'] ?? array() );
		$bundle['actions']         = self::merge_actions( $bundle['actions'] ?? array() );
		$bundle['intents']         = self::merge_intents( $bundle['intents'] ?? array() );
		$bundle['phrase_patterns'] = self::merge_patterns( $bundle['phrase_patterns'] ?? array() );
		if ( empty( $bundle['suite'] ) ) {
			$bundle['suite'] = 'geocore';
		}
		if ( empty( $bundle['bundle_version'] ) ) {
			$bundle['bundle_version'] = 'bootstrap-1.0';
		}
		if ( empty( $bundle['schema_version'] ) ) {
			$bundle['schema_version'] = '1.0';
		}
		return $bundle;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function minimal_shell() {
		return array(
			'suite'           => 'geocore',
			'bundle_version'  => 'bootstrap-1.0',
			'schema_version'  => '1.0',
			'actions'         => array(),
			'intents'         => array(),
			'phrase_patterns' => array(),
			'entities'        => array(),
		);
	}

	/**
	 * @param array<int,mixed> $existing Existing entity rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function merge_entities( array $existing ) {
		$by_key = array();
		foreach ( $existing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = (string) ( $row['entity_type'] ?? '' ) . ':' . (string) ( $row['entity_key'] ?? '' );
			if ( '' !== trim( $key, ':' ) ) {
				$by_key[ $key ] = $row;
			}
		}
		foreach ( self::essential_countries() as $row ) {
			$key = $row['entity_type'] . ':' . $row['entity_key'];
			if ( ! isset( $by_key[ $key ] ) ) {
				$by_key[ $key ] = $row;
			}
		}
		foreach ( self::essential_weather() as $row ) {
			$key = $row['entity_type'] . ':' . $row['entity_key'];
			if ( ! isset( $by_key[ $key ] ) ) {
				$by_key[ $key ] = $row;
			}
		}
		return array_values( $by_key );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function essential_countries() {
		$rows = array(
			array( 'AU', 'Australia', array( 'Australia', 'Australian', 'AU', 'australia' ) ),
			array( 'GB', 'United Kingdom', array( 'UK', 'Britain', 'Great Britain', 'United Kingdom', 'GB', 'England', 'england' ) ),
			array( 'IE', 'Ireland', array( 'Ireland', 'Irish', 'IE', 'ireland' ) ),
			array( 'CA', 'Canada', array( 'Canada', 'Canadian', 'CA' ) ),
			array( 'US', 'United States', array( 'US', 'USA', 'America', 'United States', 'American' ) ),
			array( 'ZA', 'South Africa', array( 'South Africa', 'South African', 'ZA' ) ),
			array( 'DE', 'Germany', array( 'Germany', 'German', 'DE' ) ),
			array( 'AT', 'Austria', array( 'Austria', 'Austrian', 'AT' ) ),
			array( 'FR', 'France', array( 'France', 'French', 'FR' ) ),
			array( 'RU', 'Russia', array( 'Russia', 'Russian', 'RU', 'russia' ) ),
			array( 'PT', 'Portugal', array( 'Portugal', 'Portuguese', 'PT' ) ),
			array( 'ES', 'Spain', array( 'Spain', 'Spanish', 'ES' ) ),
			array( 'IT', 'Italy', array( 'Italy', 'Italian', 'IT' ) ),
			array( 'NL', 'Netherlands', array( 'Netherlands', 'Holland', 'Dutch', 'NL' ) ),
			array( 'BE', 'Belgium', array( 'Belgium', 'Belgian', 'BE' ) ),
			array( 'NO', 'Norway', array( 'Norway', 'Norwegian', 'NO' ) ),
			array( 'SE', 'Sweden', array( 'Sweden', 'Swedish', 'SE' ) ),
			array( 'DK', 'Denmark', array( 'Denmark', 'Danish', 'DK' ) ),
			array( 'FI', 'Finland', array( 'Finland', 'Finnish', 'FI' ) ),
			array( 'PL', 'Poland', array( 'Poland', 'Polish', 'PL' ) ),
			array( 'CH', 'Switzerland', array( 'Switzerland', 'Swiss', 'CH' ) ),
			array( 'GR', 'Greece', array( 'Greece', 'Greek', 'GR' ) ),
			array( 'CZ', 'Czechia', array( 'Czechia', 'Czech Republic', 'Czech', 'CZ' ) ),
			array( 'NZ', 'New Zealand', array( 'New Zealand', 'NZ' ) ),
			array( 'JP', 'Japan', array( 'Japan', 'Japanese', 'JP' ) ),
			array( 'BR', 'Brazil', array( 'Brazil', 'Brazilian', 'BR' ) ),
			array( 'MX', 'Mexico', array( 'Mexico', 'Mexican', 'MX' ) ),
			array( 'IN', 'India', array( 'India', 'Indian', 'IN' ) ),
		);
		$out = array();
		foreach ( $rows as $idx => $row ) {
			$out[] = array(
				'id'           => 'bootstrap-country-' . $idx,
				'suite'        => 'geocore',
				'entity_type'  => 'country',
				'entity_key'   => $row[0],
				'display_name' => $row[1],
				'aliases'      => $row[2],
				'value'        => $row[0],
				'status'       => 'active',
			);
		}
		return $out;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function essential_weather() {
		$rows = array(
			array(
				'entity_key'   => 'rain',
				'display_name' => 'Rain',
				'aliases'      => array( 'rain', 'raining', 'rainy', 'wet weather', 'when its raining', "when it's raining" ),
				'value'        => 'rain',
			),
			array(
				'entity_key'   => 'sunny',
				'display_name' => 'Sunny',
				'aliases'      => array( 'sun', 'sunny', 'sunshine', 'when it is sunny', "when it's sunny", 'when its sunny' ),
				'value'        => 'sunny',
			),
			array(
				'entity_key'   => 'snow',
				'display_name' => 'Snow',
				'aliases'      => array( 'snow', 'snowing', 'snowy', 'when it is snowing', "when it's snowing", 'when its snowing' ),
				'value'        => 'snow',
			),
			array(
				'entity_key'   => 'cloudy',
				'display_name' => 'Cloudy',
				'aliases'      => array( 'cloud', 'cloudy', 'overcast', 'when it is cloudy', "when it's cloudy" ),
				'value'        => 'cloudy',
			),
			array(
				'entity_key'   => 'any',
				'display_name' => 'Any weather',
				'aliases'      => array( 'all weather', 'all weather conditions', 'any weather', 'regardless of weather' ),
				'value'        => 'any',
			),
		);
		$out = array();
		foreach ( $rows as $idx => $row ) {
			$out[] = array(
				'id'           => 'bootstrap-weather-' . $idx,
				'suite'        => 'geocore',
				'entity_type'  => 'weather_condition',
				'entity_key'   => $row['entity_key'],
				'display_name' => $row['display_name'],
				'aliases'      => $row['aliases'],
				'value'        => $row['value'],
				'status'       => 'active',
			);
		}
		return $out;
	}

	/**
	 * @param array<int,mixed> $existing Existing actions.
	 * @return array<int,array<string,mixed>>
	 */
	private static function merge_actions( array $existing ) {
		$by_key = array();
		foreach ( $existing as $row ) {
			if ( is_array( $row ) && ! empty( $row['action_key'] ) ) {
				$by_key[ (string) $row['action_key'] ] = $row;
			}
		}
		if ( ! isset( $by_key['geocore_create_variant_plan_with_country_rules'] ) ) {
			$by_key['geocore_create_variant_plan_with_country_rules'] = array(
				'action_key'            => 'geocore_create_variant_plan_with_country_rules',
				'name'                  => 'Create Variant Plan With Country Rules',
				'description'           => 'Apply source page targeting and create duplicate variants with country rules.',
				'category'              => 'variant_creation',
				'target_types'          => array( 'page', 'variant' ),
				'required_params'       => array( 'source_page_ref' ),
				'optional_params'       => array( 'duplicate_count', 'source_targeting', 'variants' ),
				'requires_confirmation' => true,
				'is_destructive'        => false,
				'status'                => 'active',
			);
		}
		if ( ! isset( $by_key['geocore_create_variants_with_country_rules'] ) ) {
			$by_key['geocore_create_variants_with_country_rules'] = array(
				'action_key'              => 'geocore_create_variants_with_country_rules',
				'name'                    => 'Create Country Variants',
				'description'             => 'Create multiple page variants with country targeting.',
				'category'                => 'targeting',
				'target_types'            => array( 'page', 'variant' ),
				'required_params'         => array( 'page_ref', 'variants' ),
				'optional_params'         => array( 'target_id' ),
				'requires_confirmation'   => true,
				'is_destructive'          => false,
				'status'                  => 'active',
			);
		}
		if ( ! isset( $by_key['geocore_create_country_rule'] ) ) {
			$by_key['geocore_create_country_rule'] = array(
				'action_key'            => 'geocore_create_country_rule',
				'name'                  => 'Create Country Rule',
				'required_params'       => array( 'mode', 'countries' ),
				'requires_confirmation' => true,
				'status'                => 'active',
			);
		}
		if ( ! isset( $by_key['geocore_create_variant_plan_with_conditions'] ) ) {
			$by_key['geocore_create_variant_plan_with_conditions'] = array(
				'action_key'            => 'geocore_create_variant_plan_with_conditions',
				'name'                  => 'Create Variant Plan With Conditions',
				'description'           => 'Apply source targeting and create variants with country and weather rules.',
				'category'              => 'variant_creation',
				'target_types'          => array( 'page', 'variant' ),
				'required_params'       => array( 'source_page_ref' ),
				'optional_params'       => array( 'duplicate_count', 'source_targeting', 'variants' ),
				'requires_confirmation' => true,
				'is_destructive'        => false,
				'status'                => 'active',
			);
		}
		if ( ! isset( $by_key['geocore_create_weather_rule'] ) ) {
			$by_key['geocore_create_weather_rule'] = array(
				'action_key'            => 'geocore_create_weather_rule',
				'name'                  => 'Create Weather Rule',
				'required_params'       => array( 'weather' ),
				'optional_params'       => array( 'countries' ),
				'requires_confirmation' => true,
				'status'                => 'active',
			);
		}
		return array_values( $by_key );
	}

	/**
	 * @param array<int,mixed> $existing Existing intents.
	 * @return array<int,array<string,mixed>>
	 */
	private static function merge_intents( array $existing ) {
		$by_key = array();
		foreach ( $existing as $row ) {
			if ( is_array( $row ) && ! empty( $row['intent_key'] ) ) {
				$by_key[ (string) $row['intent_key'] ] = $row;
			}
		}
		if ( ! isset( $by_key['create_geo_variant_plan'] ) ) {
			$by_key['create_geo_variant_plan'] = array(
				'intent_key'      => 'create_geo_variant_plan',
				'name'            => 'Create geo variant plan',
				'min_confidence'  => 0.7,
				'requires_context'=> false,
				'status'          => 'active',
			);
		}
		if ( ! isset( $by_key['create_geo_variants'] ) ) {
			$by_key['create_geo_variants'] = array(
				'intent_key'      => 'create_geo_variants',
				'name'            => 'Create geo variants',
				'min_confidence'  => 0.55,
				'requires_context'=> false,
				'status'          => 'active',
			);
		}
		if ( ! isset( $by_key['weather_rule'] ) ) {
			$by_key['weather_rule'] = array(
				'intent_key'       => 'weather_rule',
				'name'             => 'Weather targeting rule',
				'min_confidence'   => 0.7,
				'requires_context' => false,
				'status'           => 'active',
			);
		}
		return array_values( $by_key );
	}

	/**
	 * @param array<int,mixed> $existing Existing patterns.
	 * @return array<int,array<string,mixed>>
	 */
	private static function merge_patterns( array $existing ) {
		$patterns = $existing;
		$keys     = array();
		foreach ( $patterns as $row ) {
			if ( is_array( $row ) && ! empty( $row['pattern'] ) ) {
				$keys[ (string) $row['pattern'] ] = true;
			}
		}
		$bootstrap = array(
			array(
				'pattern'            => 'create two variants of {page} one will display in {country} only the other in {country_list}',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_geo_variants',
				'action_key'         => 'geocore_create_variants_with_country_rules',
				'confidence_weight'  => 0.92,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'create two variants of {page} one for {country} and one for {country_list}',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_geo_variants',
				'action_key'         => 'geocore_create_variants_with_country_rules',
				'confidence_weight'  => 0.9,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'duplicate the {page} twice with a version for {country} only and a version which works in both {country_list}',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_geo_variants',
				'action_key'         => 'geocore_create_variants_with_country_rules',
				'confidence_weight'  => 0.95,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'duplicate {page} twice',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_geo_variants',
				'action_key'         => 'geocore_create_variants_with_country_rules',
				'confidence_weight'  => 0.9,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'create two variants of {page}',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_geo_variants',
				'action_key'         => 'geocore_create_variants_with_country_rules',
				'confidence_weight'  => 0.9,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'make one version for {country} and another for {country_list}',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_geo_variants',
				'action_key'         => 'geocore_create_variants_with_country_rules',
				'confidence_weight'  => 0.88,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'one for {country} and one for {country_list}',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_geo_variants',
				'action_key'         => 'geocore_create_variants_with_country_rules',
				'confidence_weight'  => 0.86,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'show this only in {country}',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_country_rule',
				'action_key'         => 'geocore_create_country_rule',
				'confidence_weight'  => 0.85,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'hide this from {country}',
				'pattern_type'       => 'template',
				'intent_key'         => 'create_country_rule',
				'action_key'         => 'geocore_create_country_rule',
				'confidence_weight'  => 0.85,
				'status'             => 'active',
			),
			array(
				'pattern'            => 'why is this popup not firing',
				'pattern_type'       => 'contains',
				'intent_key'         => 'diagnose_popup',
				'action_key'         => 'geocore_run_popup_diagnostics',
				'confidence_weight'  => 0.8,
				'status'             => 'active',
			),
		);
		foreach ( $bootstrap as $row ) {
			if ( empty( $keys[ $row['pattern'] ] ) ) {
				$patterns[] = $row;
			}
		}
		return $patterns;
	}
}
