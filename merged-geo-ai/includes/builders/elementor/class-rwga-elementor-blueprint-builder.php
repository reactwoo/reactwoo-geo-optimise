<?php
/**
 * Build Elementor document trees from intent-level page blueprints.
 *
 * Supports classic V3 (section/column/widget) and a bounded Atomic V4 layout
 * (e-flexbox shells + e-heading / e-paragraph / e-button / e-image). Measurement
 * keys are stamped as plain rwgo_* settings on interactive widgets.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts {@see RWGA_Page_Blueprint} into Elementor JSON nodes.
 */
class RWGA_Elementor_Blueprint_Builder {

	const MODE_V3     = 'v3';
	const MODE_ATOMIC = 'atomic';

	/**
	 * Build a full elements tree.
	 *
	 * @param RWGA_Page_Blueprint  $blueprint Page blueprint.
	 * @param array<string, mixed> $options   mode (v3|atomic), content (map of section.element => text).
	 * @return array{tree: array<int, mixed>, tracked_elements: list<array<string, mixed>>, mode: string}
	 */
	public static function build( RWGA_Page_Blueprint $blueprint, array $options = array() ) {
		$mode = isset( $options['mode'] ) ? sanitize_key( (string) $options['mode'] ) : self::MODE_V3;
		if ( self::MODE_ATOMIC !== $mode ) {
			$mode = self::MODE_V3;
		}
		$content = isset( $options['content'] ) && is_array( $options['content'] ) ? $options['content'] : array();

		$tree     = array();
		$tracked  = array();
		$sections = $blueprint->get_sections();
		foreach ( $sections as $section ) {
			if ( ! ( $section instanceof RWGA_Section_Blueprint ) ) {
				continue;
			}
			$built = self::MODE_ATOMIC === $mode
				? self::build_atomic_section( $section, $content )
				: self::build_v3_section( $section, $content );
			if ( empty( $built['node'] ) || ! is_array( $built['node'] ) ) {
				continue;
			}
			$tree[] = $built['node'];
			if ( ! empty( $built['tracked'] ) && is_array( $built['tracked'] ) ) {
				foreach ( $built['tracked'] as $row ) {
					$tracked[] = $row;
				}
			}
		}

		/**
		 * Filter constructed Elementor tree from a page blueprint.
		 *
		 * @param array{tree: array<int, mixed>, tracked_elements: list<array<string, mixed>>, mode: string} $out Built payload.
		 * @param RWGA_Page_Blueprint $blueprint Blueprint.
		 * @param array<string, mixed> $options Options.
		 */
		$out = apply_filters(
			'rwga_elementor_blueprint_build',
			array(
				'tree'             => $tree,
				'tracked_elements' => $tracked,
				'mode'             => $mode,
			),
			$blueprint,
			$options
		);

		return is_array( $out ) ? $out : array(
			'tree'             => $tree,
			'tracked_elements' => $tracked,
			'mode'             => $mode,
		);
	}

	/**
	 * @param RWGA_Section_Blueprint $section Section.
	 * @param array<string, mixed>   $content Content map.
	 * @return array{node: array<string, mixed>, tracked: list<array<string, mixed>>}
	 */
	private static function build_v3_section( RWGA_Section_Blueprint $section, array $content ) {
		$widgets = array();
		$tracked = array();
		foreach ( $section->get_required_elements() as $element_key ) {
			$w = self::make_v3_widget( $section->get_type(), $element_key, $content );
			if ( empty( $w['node'] ) ) {
				continue;
			}
			$widgets[] = $w['node'];
			if ( ! empty( $w['tracked'] ) ) {
				$tracked[] = $w['tracked'];
			}
		}

		$column = array(
			'id'       => self::new_id(),
			'elType'   => 'column',
			'settings' => array(
				'_column_size' => 100,
			),
			'elements' => $widgets,
			'isInner'  => false,
		);

		$node = array(
			'id'       => self::new_id(),
			'elType'   => 'section',
			'settings' => array(
				'structure' => '10',
			),
			'elements' => array( $column ),
			'isInner'  => false,
		);

		return array(
			'node'    => $node,
			'tracked' => $tracked,
		);
	}

	/**
	 * @param RWGA_Section_Blueprint $section Section.
	 * @param array<string, mixed>   $content Content map.
	 * @return array{node: array<string, mixed>, tracked: list<array<string, mixed>>}
	 */
	private static function build_atomic_section( RWGA_Section_Blueprint $section, array $content ) {
		$children = array();
		$tracked  = array();
		foreach ( $section->get_required_elements() as $element_key ) {
			$w = self::make_atomic_widget( $section->get_type(), $element_key, $content );
			if ( empty( $w['node'] ) ) {
				continue;
			}
			$children[] = $w['node'];
			if ( ! empty( $w['tracked'] ) ) {
				$tracked[] = $w['tracked'];
			}
		}

		$node = array(
			'id'         => self::new_id(),
			'elType'     => 'widget',
			'widgetType' => 'e-flexbox',
			'settings'   => array(),
			'styles'     => array(),
			'elements'   => $children,
		);

		return array(
			'node'    => $node,
			'tracked' => $tracked,
		);
	}

	/**
	 * @param string               $section_type Section type.
	 * @param string               $element_key  Element key.
	 * @param array<string, mixed> $content      Content map.
	 * @return array{node?: array<string, mixed>, tracked?: array<string, mixed>}
	 */
	private static function make_v3_widget( $section_type, $element_key, array $content ) {
		$spec = self::element_spec( $section_type, $element_key, $content );
		if ( empty( $spec ) ) {
			return array();
		}

		$id   = self::new_id();
		$node = array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $spec['v3_type'],
			'settings'   => $spec['v3_settings'],
			'elements'   => array(),
		);

		$out = array( 'node' => $node );
		if ( ! empty( $spec['is_goal'] ) ) {
			$node['settings'] = array_merge( $node['settings'], self::goal_settings( $spec ) );
			$out['node']      = $node;
			$out['tracked']   = self::tracked_row( $id, $spec );
		}

		return $out;
	}

	/**
	 * @param string               $section_type Section type.
	 * @param string               $element_key  Element key.
	 * @param array<string, mixed> $content      Content map.
	 * @return array{node?: array<string, mixed>, tracked?: array<string, mixed>}
	 */
	private static function make_atomic_widget( $section_type, $element_key, array $content ) {
		$spec = self::element_spec( $section_type, $element_key, $content );
		if ( empty( $spec ) ) {
			return array();
		}

		$id   = self::new_id();
		$node = array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $spec['atomic_type'],
			'settings'   => $spec['atomic_settings'],
			'styles'     => array(),
			'elements'   => array(),
		);

		$out = array( 'node' => $node );
		if ( ! empty( $spec['is_goal'] ) ) {
			// Measurement keys stay plain (Document Writer / Elementor Goals convention).
			$node['settings'] = array_merge( $node['settings'], self::goal_settings( $spec ) );
			$out['node']      = $node;
			$out['tracked']   = self::tracked_row( $id, $spec );
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $spec Spec.
	 * @return array<string, string>
	 */
	private static function goal_settings( array $spec ) {
		return array(
			'rwgo_goal_enabled' => 'yes',
			'rwgo_goal_label'   => (string) $spec['goal_label'],
			'rwgo_goal_type'    => (string) $spec['goal_type'],
			'rwgo_element_key'  => (string) $spec['semantic_key'],
		);
	}

	/**
	 * @param string               $id   Elementor id.
	 * @param array<string, mixed> $spec Spec.
	 * @return array<string, mixed>
	 */
	private static function tracked_row( $id, array $spec ) {
		return array(
			'semantic_key'  => (string) $spec['semantic_key'],
			'element_key'   => (string) $spec['semantic_key'],
			'elementor_id'  => (string) $id,
			'widget_id'     => (string) $id,
			'goal_label'    => (string) $spec['goal_label'],
			'ui_goal_type'  => (string) $spec['goal_type'],
			'goal_type'     => 'click',
		);
	}

	/**
	 * @param string               $section_type Section type.
	 * @param string               $element_key  Element key.
	 * @param array<string, mixed> $content      Content map.
	 * @return array<string, mixed>
	 */
	private static function element_spec( $section_type, $element_key, array $content ) {
		$section_type = sanitize_key( (string) $section_type );
		$element_key  = sanitize_key( (string) $element_key );
		$semantic     = self::semantic_key( $section_type, $element_key );
		$text         = self::resolve_content( $section_type, $element_key, $content );

		$is_cta = in_array( $element_key, array( 'primary_cta', 'button' ), true );

		switch ( $element_key ) {
			case 'headline':
				return array(
					'semantic_key'     => $semantic,
					'is_goal'          => false,
					'goal_label'       => '',
					'goal_type'        => 'cta_click',
					'v3_type'          => 'heading',
					'v3_settings'      => array(
						'title' => $text,
						'header_size' => 'h2',
					),
					'atomic_type'      => 'e-heading',
					'atomic_settings'  => array(
						'title' => self::typed_string( $text ),
					),
				);
			case 'subheading':
				return array(
					'semantic_key'     => $semantic,
					'is_goal'          => false,
					'goal_label'       => '',
					'goal_type'        => 'cta_click',
					'v3_type'          => 'text-editor',
					'v3_settings'      => array(
						'editor' => '<p>' . self::escape_html( $text ) . '</p>',
					),
					'atomic_type'      => 'e-paragraph',
					'atomic_settings'  => array(
						'paragraph' => self::typed_string( $text ),
					),
				);
			case 'primary_cta':
			case 'button':
				return array(
					'semantic_key'     => $semantic,
					'is_goal'          => true,
					'goal_label'       => $text,
					'goal_type'        => 'cta_click',
					'v3_type'          => 'button',
					'v3_settings'      => array(
						'text' => $text,
						'link' => array(
							'url' => '#',
						),
					),
					'atomic_type'      => 'e-button',
					'atomic_settings'  => array(
						'text' => self::typed_string( $text ),
					),
				);
			case 'image':
				return array(
					'semantic_key'     => $semantic,
					'is_goal'          => false,
					'goal_label'       => '',
					'goal_type'        => 'cta_click',
					'v3_type'          => 'image',
					'v3_settings'      => array(
						'image' => array(
							'url' => '',
							'id'  => '',
						),
						'caption' => $text,
					),
					'atomic_type'      => 'e-image',
					'atomic_settings'  => array(
						'alt' => self::typed_string( $text ),
					),
				);
			case 'cards':
			case 'logos':
			case 'testimonials':
				return array(
					'semantic_key'     => $semantic,
					'is_goal'          => false,
					'goal_label'       => '',
					'goal_type'        => 'cta_click',
					'v3_type'          => 'text-editor',
					'v3_settings'      => array(
						'editor' => '<p>' . self::escape_html( $text ) . '</p>',
					),
					'atomic_type'      => 'e-paragraph',
					'atomic_settings'  => array(
						'paragraph' => self::typed_string( $text ),
					),
				);
			case 'accordion':
				return array(
					'semantic_key'     => $semantic,
					'is_goal'          => false,
					'goal_label'       => '',
					'goal_type'        => 'cta_click',
					'v3_type'          => 'accordion',
					'v3_settings'      => array(
						'tabs' => array(
							array(
								'tab_title'   => __( 'Question', 'reactwoo-geo-ai' ),
								'tab_content' => $text,
							),
						),
					),
					'atomic_type'      => 'e-accordion',
					'atomic_settings'  => array(),
				);
			default:
				if ( $is_cta ) {
					return array();
				}
				return array(
					'semantic_key'     => $semantic,
					'is_goal'          => false,
					'goal_label'       => '',
					'goal_type'        => 'cta_click',
					'v3_type'          => 'text-editor',
					'v3_settings'      => array(
						'editor' => '<p>' . self::escape_html( $text ) . '</p>',
					),
					'atomic_type'      => 'e-paragraph',
					'atomic_settings'  => array(
						'paragraph' => self::typed_string( $text ),
					),
				);
		}
	}

	/**
	 * @param string $section_type Section.
	 * @param string $element_key  Element.
	 * @return string
	 */
	public static function semantic_key( $section_type, $element_key ) {
		$raw = sanitize_key( (string) $section_type ) . '.' . sanitize_key( (string) $element_key );
		if ( class_exists( 'RWGO_Element_Key', false ) ) {
			return RWGO_Element_Key::sanitize( $raw );
		}
		return $raw;
	}

	/**
	 * @param string               $section_type Section.
	 * @param string               $element_key  Element.
	 * @param array<string, mixed> $content      Map.
	 * @return string
	 */
	private static function resolve_content( $section_type, $element_key, array $content ) {
		$compound = $section_type . '.' . $element_key;
		if ( isset( $content[ $compound ] ) && is_scalar( $content[ $compound ] ) ) {
			return sanitize_text_field( (string) $content[ $compound ] );
		}
		if ( isset( $content[ $element_key ] ) && is_scalar( $content[ $element_key ] ) ) {
			return sanitize_text_field( (string) $content[ $element_key ] );
		}
		$defaults = array(
			'headline'     => __( 'Headline', 'reactwoo-geo-ai' ),
			'subheading'   => __( 'Supporting copy goes here.', 'reactwoo-geo-ai' ),
			'primary_cta'  => __( 'Get started', 'reactwoo-geo-ai' ),
			'button'       => __( 'Learn more', 'reactwoo-geo-ai' ),
			'image'        => __( 'Image', 'reactwoo-geo-ai' ),
			'cards'        => __( 'Benefit cards placeholder', 'reactwoo-geo-ai' ),
			'logos'        => __( 'Trust logos placeholder', 'reactwoo-geo-ai' ),
			'testimonials' => __( 'Testimonials placeholder', 'reactwoo-geo-ai' ),
			'accordion'    => __( 'FAQ answer placeholder', 'reactwoo-geo-ai' ),
		);
		return isset( $defaults[ $element_key ] ) ? (string) $defaults[ $element_key ] : ucwords( str_replace( '_', ' ', $element_key ) );
	}

	/**
	 * @param string $value Plain string.
	 * @return array{$$type: string, value: string}
	 */
	private static function typed_string( $value ) {
		return array(
			'$$type' => 'string',
			'value'  => (string) $value,
		);
	}

	/**
	 * @return string
	 */
	private static function new_id() {
		try {
			$hex = bin2hex( random_bytes( 4 ) );
		} catch ( Exception $e ) {
			$hex = substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 8 );
		}
		return substr( $hex, 0, 7 );
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	private static function escape_html( $text ) {
		if ( function_exists( 'esc_html' ) ) {
			return esc_html( $text );
		}
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
