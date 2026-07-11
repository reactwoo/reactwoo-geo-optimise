<?php
/**
 * Elementor Atomic V4 node reader (typed props, styles, classes).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads Atomic V4 widget nodes into normalized widget rows.
 */
class RWGA_Elementor_V4_Node_Reader {

	/**
	 * Map Atomic widgetType → semantic type.
	 *
	 * @var array<string, string>
	 */
	private static $semantic_map = array(
		'e-heading'    => 'heading',
		'e-paragraph'  => 'paragraph',
		'e-button'     => 'button',
		'e-image'      => 'image',
		'e-svg'        => 'image',
		'e-divider'    => 'divider',
		'e-video'      => 'video',
		'e-div-block'  => 'container',
		'e-flexbox'    => 'flexbox',
		'e-grid'       => 'grid',
		'e-form'       => 'form',
		'e-tabs'       => 'tabs',
		'e-accordion'  => 'accordion',
	);

	/**
	 * @param array<string, mixed> $node       Elementor node.
	 * @param string               $section_id Section id.
	 * @param string               $parent_id  Parent id.
	 * @return array<string, mixed>|null
	 */
	public static function read_widget( array $node, $section_id, $parent_id ) {
		$widget_type = isset( $node['widgetType'] ) ? sanitize_key( (string) $node['widgetType'] ) : '';
		$el_type     = isset( $node['elType'] ) ? (string) $node['elType'] : '';

		if ( '' === $widget_type ) {
			return null;
		}
		// Atomic content widgets use elType=widget; layout shells use Atomic layout widget types.
		if ( 'widget' !== $el_type && ! RWGA_Elementor_Node_Version::is_atomic_layout( $node ) ) {
			return null;
		}

		$node_id          = isset( $node['id'] ) ? (string) $node['id'] : '';
		$raw_settings     = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$resolved         = RWGA_Elementor_Atomic_Prop_Resolver::resolve_settings( $raw_settings );
		$semantic         = isset( self::$semantic_map[ $widget_type ] ) ? self::$semantic_map[ $widget_type ] : 'unknown';
		$content          = self::extract_content( $widget_type, $resolved, $raw_settings );
		$public           = self::public_settings( $widget_type, $resolved, $raw_settings );
		$classes          = self::extract_classes( $resolved, $raw_settings );
		$style_summary    = array();
		if ( ! empty( $node['styles'] ) && is_array( $node['styles'] ) ) {
			$style_summary = RWGA_Elementor_Style_Summary::summarize( $node['styles'] );
		}

		$is_cta  = in_array( $semantic, array( 'button' ), true );
		$is_form = in_array( $semantic, array( 'form' ), true );

		$row = RWGA_Builder_Normalize::widget_row(
			array(
				'id'         => $node_id,
				'type'       => $widget_type,
				'name'       => $widget_type,
				'section_id' => $section_id,
				'parent_id'  => $parent_id,
				'content'    => $content,
				'settings'   => $public,
				'controls'   => array_keys( $raw_settings ),
				'is_cta'     => $is_cta,
				'is_form'    => $is_form,
			)
		);

		$row['element_version'] = RWGA_Elementor_Node_Version::V4;
		$row['semantic_type']   = $semantic;
		$row['html_tag']        = isset( $public['html_tag'] ) ? (string) $public['html_tag'] : '';
		$row['classes']         = $classes;
		$row['style_summary']   = $style_summary;
		$row['interactions']    = self::bounded_interactions( isset( $node['interactions'] ) ? $node['interactions'] : null );
		$row['custom_attributes'] = self::extract_custom_attributes( $resolved );
		$row['component']       = self::extract_component_meta( $node );
		$row['unknown_meta']    = self::bounded_unknown( $widget_type, $resolved );

		return $row;
	}

	/**
	 * @param string               $widget_type Widget type.
	 * @param array<string, mixed> $resolved    Resolved settings.
	 * @param array<string, mixed> $raw         Raw settings.
	 * @return string
	 */
	private static function extract_content( $widget_type, array $resolved, array $raw ) {
		switch ( $widget_type ) {
			case 'e-heading':
			case 'e-paragraph':
				foreach ( array( 'title', 'text', 'paragraph', 'content' ) as $key ) {
					if ( isset( $raw[ $key ] ) ) {
						$text = RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $raw[ $key ] );
						if ( '' !== $text ) {
							return RWGA_Builder_Normalize::trim_text( $text, 'e-heading' === $widget_type ? 300 : 800 );
						}
					}
					if ( isset( $resolved[ $key ] ) ) {
						$text = RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $resolved[ $key ] );
						if ( '' !== $text ) {
							return RWGA_Builder_Normalize::trim_text( $text, 'e-heading' === $widget_type ? 300 : 800 );
						}
					}
				}
				return '';
			case 'e-button':
				foreach ( array( 'text', 'title', 'label' ) as $key ) {
					if ( isset( $raw[ $key ] ) ) {
						$text = RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $raw[ $key ] );
						if ( '' !== $text ) {
							return RWGA_Builder_Normalize::trim_text( $text, 120 );
						}
					}
				}
				return '';
			case 'e-image':
			case 'e-svg':
				$alt = '';
				if ( isset( $resolved['alt'] ) ) {
					$alt = RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $resolved['alt'] );
				}
				return RWGA_Builder_Normalize::trim_text( $alt, 200 );
			default:
				foreach ( array( 'title', 'text', 'content', 'label' ) as $key ) {
					if ( isset( $raw[ $key ] ) ) {
						$text = RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $raw[ $key ] );
						if ( '' !== $text ) {
							return RWGA_Builder_Normalize::trim_text( $text, 400 );
						}
					}
				}
				return '';
		}
	}

	/**
	 * @param string               $widget_type Widget type.
	 * @param array<string, mixed> $resolved    Resolved settings.
	 * @param array<string, mixed> $raw         Raw settings.
	 * @return array<string, mixed>
	 */
	private static function public_settings( $widget_type, array $resolved, array $raw ) {
		$out = array();

		if ( 'e-heading' === $widget_type ) {
			$tag = '';
			foreach ( array( 'tag', 'header_size', 'html_tag' ) as $key ) {
				if ( isset( $raw[ $key ] ) ) {
					$tag = strtolower( trim( RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $raw[ $key ] ) ) );
					if ( '' === $tag && is_string( RWGA_Elementor_Atomic_Prop_Resolver::resolve( $raw[ $key ] ) ) ) {
						$tag = strtolower( trim( (string) RWGA_Elementor_Atomic_Prop_Resolver::resolve( $raw[ $key ] ) ) );
					}
				}
				if ( '' === $tag && isset( $resolved[ $key ] ) && is_scalar( $resolved[ $key ] ) ) {
					$tag = strtolower( trim( (string) $resolved[ $key ] ) );
				}
				if ( preg_match( '/^h[1-6]$/', $tag ) ) {
					break;
				}
				$tag = '';
			}
			$out['html_tag']    = '' !== $tag ? $tag : 'h2';
			$out['header_size'] = $out['html_tag'];
		}

		if ( 'e-button' === $widget_type ) {
			$link = self::extract_link( $resolved, $raw );
			$out['url'] = $link;
		}

		if ( in_array( $widget_type, array( 'e-image', 'e-svg' ), true ) ) {
			$image = isset( $resolved['image'] ) && is_array( $resolved['image'] ) ? $resolved['image'] : array();
			if ( isset( $raw['image'] ) ) {
				$img_res = RWGA_Elementor_Atomic_Prop_Resolver::resolve( $raw['image'] );
				if ( is_array( $img_res ) ) {
					$image = $img_res;
				}
			}
			$out['url'] = isset( $image['url'] ) ? (string) $image['url'] : ( isset( $image['src'] ) ? (string) $image['src'] : '' );
			$out['alt'] = isset( $image['alt'] ) ? (string) $image['alt'] : ( isset( $resolved['alt'] ) ? RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $resolved['alt'] ) : '' );
			if ( isset( $image['id'] ) ) {
				$out['attachment_id'] = (int) $image['id'];
			}
		}

		if ( 'e-video' === $widget_type ) {
			$out['url'] = isset( $resolved['video_url'] ) ? RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $resolved['video_url'] ) : '';
			if ( '' === $out['url'] && isset( $raw['link'] ) ) {
				$out['url'] = self::extract_link( $resolved, $raw );
			}
		}

		$link_generic = self::extract_link( $resolved, $raw );
		if ( '' !== $link_generic && empty( $out['url'] ) ) {
			$out['url'] = $link_generic;
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $resolved Resolved.
	 * @param array<string, mixed> $raw      Raw.
	 * @return string
	 */
	private static function extract_link( array $resolved, array $raw ) {
		foreach ( array( 'link', 'url', 'href' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$link = RWGA_Elementor_Atomic_Prop_Resolver::resolve( $raw[ $key ] );
				if ( is_array( $link ) && isset( $link['url'] ) ) {
					return (string) $link['url'];
				}
				if ( is_string( $link ) ) {
					return $link;
				}
			}
			if ( isset( $resolved[ $key ] ) ) {
				if ( is_array( $resolved[ $key ] ) && isset( $resolved[ $key ]['url'] ) ) {
					return (string) $resolved[ $key ]['url'];
				}
				if ( is_string( $resolved[ $key ] ) ) {
					return $resolved[ $key ];
				}
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $resolved Resolved settings.
	 * @param array<string, mixed> $raw      Raw settings.
	 * @return array<string, mixed>
	 */
	private static function extract_classes( array $resolved, array $raw ) {
		$ids = array();
		foreach ( array( 'classes', 'css_classes', '_css_classes' ) as $key ) {
			$source = null;
			if ( isset( $raw[ $key ] ) ) {
				$source = RWGA_Elementor_Atomic_Prop_Resolver::resolve( $raw[ $key ] );
			} elseif ( isset( $resolved[ $key ] ) ) {
				$source = $resolved[ $key ];
			}
			if ( null === $source ) {
				continue;
			}
			if ( is_string( $source ) ) {
				$ids = array_merge( $ids, preg_split( '/\s+/', trim( $source ) ) ?: array() );
			} elseif ( is_array( $source ) ) {
				foreach ( $source as $item ) {
					if ( is_string( $item ) || is_numeric( $item ) ) {
						$ids[] = (string) $item;
					} elseif ( is_array( $item ) && isset( $item['id'] ) ) {
						$ids[] = (string) $item['id'];
					} elseif ( is_array( $item ) && isset( $item['value'] ) ) {
						$ids[] = (string) RWGA_Elementor_Atomic_Prop_Resolver::to_plain_text( $item['value'] );
					}
				}
			}
		}

		$ids = array_values( array_filter( array_unique( array_map( 'strval', $ids ) ) ) );
		$out = array();
		foreach ( array_slice( $ids, 0, 40 ) as $id ) {
			$out[] = array(
				'id'    => $id,
				'scope' => RWGA_Elementor_Style_Summary::class_scope( $id ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $interactions Interactions.
	 * @return array<int, array<string, mixed>>
	 */
	private static function bounded_interactions( $interactions ) {
		if ( ! is_array( $interactions ) ) {
			return array();
		}
		$out   = array();
		$count = 0;
		foreach ( $interactions as $item ) {
			if ( $count >= 10 ) {
				break;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$out[] = array(
				'type'   => isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '',
				'trigger'=> isset( $item['trigger'] ) ? sanitize_key( (string) $item['trigger'] ) : ( isset( $item['event'] ) ? sanitize_key( (string) $item['event'] ) : '' ),
			);
			++$count;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $resolved Resolved settings.
	 * @return array<string, string>
	 */
	private static function extract_custom_attributes( array $resolved ) {
		$attrs = array();
		if ( empty( $resolved['attributes'] ) || ! is_array( $resolved['attributes'] ) ) {
			if ( empty( $resolved['custom_attributes'] ) || ! is_array( $resolved['custom_attributes'] ) ) {
				return $attrs;
			}
			$source = $resolved['custom_attributes'];
		} else {
			$source = $resolved['attributes'];
		}
		$count = 0;
		foreach ( $source as $key => $value ) {
			if ( $count >= 20 ) {
				break;
			}
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$attrs[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
			++$count;
		}
		return $attrs;
	}

	/**
	 * @param array<string, mixed> $node Node.
	 * @return array<string, mixed>
	 */
	private static function extract_component_meta( array $node ) {
		$meta = array();
		if ( ! empty( $node['widgetType'] ) && false !== strpos( (string) $node['widgetType'], 'component' ) ) {
			$meta['is_component'] = true;
		}
		if ( isset( $node['editor_settings'] ) && is_array( $node['editor_settings'] ) ) {
			if ( ! empty( $node['editor_settings']['component_id'] ) ) {
				$meta['component_id'] = (string) $node['editor_settings']['component_id'];
			}
			if ( ! empty( $node['editor_settings']['instance_id'] ) ) {
				$meta['instance_id'] = (string) $node['editor_settings']['instance_id'];
			}
		}
		return $meta;
	}

	/**
	 * Preserve a few unknown resolved keys for unsupported Atomic widgets.
	 *
	 * @param string               $widget_type Widget type.
	 * @param array<string, mixed> $resolved    Resolved settings.
	 * @return array<string, mixed>
	 */
	private static function bounded_unknown( $widget_type, array $resolved ) {
		if ( isset( self::$semantic_map[ $widget_type ] ) ) {
			return array();
		}
		$out   = array();
		$count = 0;
		foreach ( $resolved as $key => $value ) {
			if ( $count >= 8 ) {
				break;
			}
			if ( is_scalar( $value ) ) {
				$out[ (string) $key ] = $value;
				++$count;
			}
		}
		return $out;
	}
}
