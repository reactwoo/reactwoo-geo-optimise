<?php
/**
 * Elementor _elementor_data parser adapter.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses Elementor JSON layout trees without requiring Elementor to be active.
 */
class RWGA_Elementor_Adapter implements RWGA_Builder_Adapter_Interface {

	/**
	 * Layout element types (not widgets).
	 *
	 * @var array<int, string>
	 */
	private static $layout_types = array( 'section', 'container', 'column' );

	/**
	 * Top-level section-like roots (legacy + Atomic layouts).
	 *
	 * @param array<string, mixed> $node Node.
	 * @return bool
	 */
	private static function is_section_root( array $node ) {
		$el_type = isset( $node['elType'] ) ? (string) $node['elType'] : '';
		if ( in_array( $el_type, array( 'section', 'container' ), true ) ) {
			return true;
		}
		return class_exists( 'RWGA_Elementor_Node_Version', false )
			&& RWGA_Elementor_Node_Version::is_atomic_layout( $node );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function post_has_elementor_data( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return false;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) && array() !== $decoded;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function supports( $post_id ) {
		return self::post_has_elementor_data( $post_id );
	}

	/**
	 * @return string
	 */
	public function get_builder_name() {
		return 'elementor';
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function extract_page_context( $post_id ) {
		$ctx = RWGA_Builder_Normalize::empty_page_context( 'elementor', $post_id );
		$ctx['raw_builder_meta_available'] = true;
		$ctx['sections']       = $this->extract_sections( $post_id );
		$ctx['widgets']        = $this->extract_widgets( $post_id );
		$ctx['content_blocks'] = $this->extract_content( $post_id );
		$ctx['ctas']           = $this->collect_ctas( $ctx['widgets'] );
		$ctx['forms']          = $this->collect_forms( $ctx['widgets'] );
		$ctx['media']          = $this->collect_media( $ctx['widgets'] );
		return $ctx;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_sections( $post_id ) {
		$tree = $this->get_element_tree( $post_id );
		if ( array() === $tree ) {
			return array();
		}

		$sections = array();
		$index    = 0;
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) || ! self::is_section_root( $node ) ) {
				continue;
			}
			$el_type    = isset( $node['elType'] ) ? (string) $node['elType'] : '';
			$widget_type = isset( $node['widgetType'] ) ? sanitize_key( (string) $node['widgetType'] ) : '';
			$section_type = '' !== $widget_type ? $widget_type : $el_type;
			$section_id = isset( $node['id'] ) ? (string) $node['id'] : 'el-sec-' . $index;
			$widgets    = $this->walk_elements( array( $node ), $section_id, '', true );
			$widget_ids = array_column( $widgets, 'id' );

			$heading  = '';
			$has_h1   = false;
			$has_cta  = false;
			$has_form = false;
			$has_media = false;
			foreach ( $widgets as $w ) {
				if ( ! empty( $w['is_cta'] ) ) {
					$has_cta = true;
				}
				if ( ! empty( $w['is_form'] ) ) {
					$has_form = true;
				}
				$semantic = isset( $w['semantic_type'] ) ? (string) $w['semantic_type'] : '';
				if ( in_array( $w['type'], array( 'image', 'image-gallery', 'video', 'icon-box', 'e-image', 'e-svg', 'e-video' ), true )
					|| in_array( $semantic, array( 'image', 'video' ), true ) ) {
					$has_media = true;
				}
				$is_heading = ( 'heading' === $w['type'] || 'e-heading' === $w['type'] || 'heading' === $semantic );
				if ( $is_heading ) {
					$size = isset( $w['settings']['header_size'] ) ? (string) $w['settings']['header_size'] : ( isset( $w['html_tag'] ) ? (string) $w['html_tag'] : 'h2' );
					if ( 'h1' === $size && '' === $heading ) {
						$heading = $w['content'];
						$has_h1  = true;
					} elseif ( '' === $heading ) {
						$heading = $w['content'];
					}
				}
			}

			$sections[] = RWGA_Builder_Normalize::section_row(
				array(
					'id'           => $section_id,
					'type'         => $section_type,
					'index'        => $index,
					'widget_ids'   => $widget_ids,
					'heading'      => RWGA_Builder_Normalize::trim_text( $heading, 160 ),
					'has_h1'       => $has_h1,
					'has_cta'      => $has_cta,
					'has_form'     => $has_form,
					'has_media'    => $has_media,
					'widget_count' => count( $widgets ),
				)
			);
			++$index;
		}
		return $sections;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_widgets( $post_id ) {
		$tree = $this->get_element_tree( $post_id );
		if ( array() === $tree ) {
			return array();
		}
		$all   = array();
		$index = 0;
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) || ! self::is_section_root( $node ) ) {
				continue;
			}
			$section_id = isset( $node['id'] ) ? (string) $node['id'] : 'el-sec-' . $index;
			$all        = array_merge( $all, $this->walk_elements( array( $node ), $section_id, '', true ) );
			++$index;
		}
		return $all;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_content( $post_id ) {
		$widgets = $this->extract_widgets( $post_id );
		$blocks  = array();
		foreach ( $widgets as $w ) {
			if ( '' === trim( (string) $w['content'] ) && empty( $w['settings'] ) ) {
				continue;
			}
			$blocks[] = array(
				'id'       => $w['id'],
				'type'     => $w['type'],
				'text'     => $w['content'],
				'settings' => $w['settings'],
			);
		}
		return $blocks;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, mixed>
	 */
	public function get_element_tree( $post_id ) {
		$post_id = (int) $post_id;
		$raw     = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		return $decoded;
	}

	/**
	 * @param array<int, mixed> $nodes      Elementor nodes.
	 * @param string            $section_id Section id.
	 * @param string            $parent_id  Parent id.
	 * @param bool              $widgets_only Skip layout nodes in output when false.
	 * @return array<int, array<string, mixed>>
	 */
	public function walk_elements( array $nodes, $section_id, $parent_id, $widgets_only = true ) {
		$widgets = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$el_type     = isset( $node['elType'] ) ? (string) $node['elType'] : '';
			$node_id     = isset( $node['id'] ) ? (string) $node['id'] : '';
			$widget_type = isset( $node['widgetType'] ) ? sanitize_key( (string) $node['widgetType'] ) : '';
			$version     = RWGA_Elementor_Node_Version::detect( $node );

			if ( '' !== $widget_type && ( 'widget' === $el_type || RWGA_Elementor_Node_Version::is_atomic_layout( $node ) ) ) {
				$row = null;
				if ( RWGA_Elementor_Node_Version::V4 === $version ) {
					$row = RWGA_Elementor_V4_Node_Reader::read_widget( $node, $section_id, $parent_id );
				} else {
					$row = RWGA_Elementor_V3_Node_Reader::read_widget( $node, $section_id, $parent_id, $this );
				}
				if ( is_array( $row ) ) {
					// Skip emitting Atomic layout shells as content widgets when they only wrap children.
					$semantic = isset( $row['semantic_type'] ) ? (string) $row['semantic_type'] : '';
					$is_layout_shell = in_array( $semantic, array( 'container', 'flexbox', 'grid' ), true );
					if ( ! $is_layout_shell || ! $widgets_only || '' !== trim( (string) $row['content'] ) ) {
						if ( ! $is_layout_shell ) {
							$widgets[] = $row;
						} elseif ( ! $widgets_only ) {
							$widgets[] = $row;
						}
					}
				}
			}

			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$next_parent = ( 'widget' === $el_type || RWGA_Elementor_Node_Version::is_atomic_layout( $node ) ) ? $node_id : $parent_id;
				if ( in_array( $el_type, self::$layout_types, true ) && '' !== $node_id ) {
					$next_parent = $node_id;
				}
				if ( RWGA_Elementor_Node_Version::is_atomic_layout( $node ) && '' !== $node_id ) {
					$next_parent = $node_id;
				}
				$widgets = array_merge(
					$widgets,
					$this->walk_elements( $node['elements'], $section_id, $next_parent, $widgets_only )
				);
			}
		}
		return $widgets;
	}

	/**
	 * @param string               $widget_type Widget type.
	 * @param array<string, mixed> $settings    Settings.
	 * @return string
	 */
	public function widget_text_content( $widget_type, array $settings ) {
		$widget_type = sanitize_key( (string) $widget_type );

		switch ( $widget_type ) {
			case 'heading':
				return RWGA_Builder_Normalize::trim_text( isset( $settings['title'] ) ? (string) $settings['title'] : '', 300 );
			case 'button':
				return RWGA_Builder_Normalize::trim_text( isset( $settings['text'] ) ? (string) $settings['text'] : '', 120 );
			case 'text-editor':
			case 'theme-post-content':
				return RWGA_Builder_Normalize::trim_text( wp_strip_all_tags( isset( $settings['editor'] ) ? (string) $settings['editor'] : '' ), 800 );
			case 'icon-box':
				$title = isset( $settings['title_text'] ) ? (string) $settings['title_text'] : '';
				$desc  = isset( $settings['description_text'] ) ? (string) $settings['description_text'] : '';
				return RWGA_Builder_Normalize::trim_text( trim( $title . ' ' . $desc ), 400 );
			case 'testimonial':
			case 'testimonial-carousel':
				$content = isset( $settings['testimonial_content'] ) ? (string) $settings['testimonial_content'] : '';
				if ( '' === $content && isset( $settings['content'] ) ) {
					$content = (string) $settings['content'];
				}
				return RWGA_Builder_Normalize::trim_text( wp_strip_all_tags( $content ), 500 );
			case 'accordion':
			case 'toggle':
				return $this->repeater_titles( $settings, array( 'tab_title', 'item_title', 'title' ) );
			case 'tabs':
				return $this->repeater_titles( $settings, array( 'tab_title', 'title' ) );
			case 'price-table':
				$heading = isset( $settings['heading'] ) ? (string) $settings['heading'] : '';
				$price   = isset( $settings['price'] ) ? (string) $settings['price'] : '';
				return RWGA_Builder_Normalize::trim_text( trim( $heading . ' ' . $price ), 200 );
			case 'html':
			case 'shortcode':
				$key = 'html' === $widget_type ? 'html' : 'shortcode';
				return RWGA_Builder_Normalize::trim_text( wp_strip_all_tags( isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '' ), 400 );
			case 'form':
				return isset( $settings['form_name'] ) ? (string) $settings['form_name'] : __( 'Form', 'reactwoo-geo-ai' );
			default:
				foreach ( array( 'title', 'text', 'editor', 'caption', 'description' ) as $key ) {
					if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
						return RWGA_Builder_Normalize::trim_text( wp_strip_all_tags( $settings[ $key ] ), 400 );
					}
				}
				return '';
		}
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @param array<int, string>   $keys     Title keys in repeater items.
	 * @return string
	 */
	private function repeater_titles( array $settings, array $keys ) {
		$items = array();
		foreach ( array( 'tabs', 'accordion', 'toggle' ) as $rep_key ) {
			if ( empty( $settings[ $rep_key ] ) || ! is_array( $settings[ $rep_key ] ) ) {
				continue;
			}
			foreach ( $settings[ $rep_key ] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				foreach ( $keys as $k ) {
					if ( ! empty( $item[ $k ] ) ) {
						$items[] = (string) $item[ $k ];
						break;
					}
				}
			}
		}
		return RWGA_Builder_Normalize::trim_text( implode( ' | ', $items ), 400 );
	}

	/**
	 * Public settings for V3 widgets (exposed for V3 node reader).
	 *
	 * @param string               $widget_type Widget type.
	 * @param array<string, mixed> $settings    Settings.
	 * @return array<string, mixed>
	 */
	public function public_settings_for_widget( $widget_type, array $settings ) {
		return $this->public_settings( $widget_type, $settings );
	}

	/**
	 * @param string               $widget_type Widget type.
	 * @param array<string, mixed> $settings    Settings.
	 * @return array<string, mixed>
	 */
	private function public_settings( $widget_type, array $settings ) {
		$widget_type = sanitize_key( (string) $widget_type );
		$out         = array();

		if ( 'heading' === $widget_type ) {
			$out['header_size'] = isset( $settings['header_size'] ) ? (string) $settings['header_size'] : 'h2';
		}
		if ( 'button' === $widget_type ) {
			$out['url'] = isset( $settings['link']['url'] ) ? (string) $settings['link']['url'] : ( isset( $settings['url'] ) ? (string) $settings['url'] : '' );
			$bg = '';
			foreach ( array( 'background_color', 'button_background_color', '_background_color' ) as $key ) {
				if ( ! empty( $settings[ $key ] ) ) {
					$bg = RWGA_Builder_Normalize::normalize_color_value( (string) $settings[ $key ] );
					if ( '' !== $bg ) {
						break;
					}
				}
			}
			$text = '';
			foreach ( array( 'button_text_color', 'text_color', 'color' ) as $key ) {
				if ( ! empty( $settings[ $key ] ) ) {
					$text = RWGA_Builder_Normalize::normalize_color_value( (string) $settings[ $key ] );
					if ( '' !== $text ) {
						break;
					}
				}
			}
			if ( '' !== $bg ) {
				$out['background_color'] = $bg;
				$out['background_role']  = RWGA_Builder_Normalize::interpret_color_role( $bg );
			}
			if ( '' !== $text ) {
				$out['text_color'] = $text;
				$out['text_role']  = RWGA_Builder_Normalize::interpret_color_role( $text );
			}
		}
		if ( in_array( $widget_type, array( 'image', 'image-gallery' ), true ) ) {
			$out['url'] = isset( $settings['image']['url'] ) ? (string) $settings['image']['url'] : '';
			$out['alt'] = isset( $settings['image']['alt'] ) ? (string) $settings['image']['alt'] : '';
		}
		if ( 'form' === $widget_type ) {
			$out['form_name'] = isset( $settings['form_name'] ) ? (string) $settings['form_name'] : '';
			$out['button_text'] = isset( $settings['button_text'] ) ? (string) $settings['button_text'] : '';
		}
		if ( 'icon-box' === $widget_type ) {
			$out['title_text'] = isset( $settings['title_text'] ) ? (string) $settings['title_text'] : '';
		}

		return $out;
	}

	/**
	 * @param string               $widget_type Widget type.
	 * @param array<string, mixed> $settings    Settings.
	 * @return bool
	 */
	public function is_cta_widget( $widget_type, array $settings = array() ) {
		$widget_type = sanitize_key( (string) $widget_type );
		unset( $settings );
		return in_array(
			$widget_type,
			array_merge( RWGA_Builder_Normalize::cta_widget_types(), array( 'call-to-action', 'e-button' ) ),
			true
		);
	}

	/**
	 * @param string $widget_type Widget type.
	 * @return bool
	 */
	public function is_form_widget( $widget_type ) {
		$widget_type = sanitize_key( (string) $widget_type );
		return in_array( $widget_type, RWGA_Builder_Normalize::form_widget_types(), true );
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_ctas( array $widgets ) {
		$out = array();
		foreach ( $widgets as $w ) {
			if ( empty( $w['is_cta'] ) ) {
				continue;
			}
			$out[] = array(
				'widget_id'  => (string) $w['id'],
				'section_id' => (string) $w['section_id'],
				'label'      => (string) $w['content'],
				'url'        => isset( $w['settings']['url'] ) ? (string) $w['settings']['url'] : '',
			);
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_forms( array $widgets ) {
		$out = array();
		foreach ( $widgets as $w ) {
			if ( empty( $w['is_form'] ) ) {
				continue;
			}
			$out[] = array(
				'widget_id'  => (string) $w['id'],
				'section_id' => (string) $w['section_id'],
				'type'       => (string) $w['type'],
				'name'       => isset( $w['settings']['form_name'] ) ? (string) $w['settings']['form_name'] : '',
			);
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_media( array $widgets ) {
		$out = array();
		foreach ( $widgets as $w ) {
			$semantic = isset( $w['semantic_type'] ) ? (string) $w['semantic_type'] : '';
			if ( ! in_array( $w['type'], array( 'image', 'image-gallery', 'video', 'icon-box', 'e-image', 'e-svg', 'e-video' ), true )
				&& ! in_array( $semantic, array( 'image', 'video' ), true ) ) {
				continue;
			}
			$out[] = array(
				'widget_id' => (string) $w['id'],
				'url'       => isset( $w['settings']['url'] ) ? (string) $w['settings']['url'] : '',
				'alt'       => isset( $w['settings']['alt'] ) ? (string) $w['settings']['alt'] : '',
			);
		}
		return $out;
	}
}
