<?php
/**
 * Gutenberg block parser adapter.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes Gutenberg block trees via parse_blocks().
 */
class RWGA_Gutenberg_Adapter implements RWGA_Builder_Adapter_Interface {

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function supports( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( RWGA_Elementor_Adapter::post_has_elementor_data( $post_id ) ) {
			return false;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		return function_exists( 'has_blocks' ) && has_blocks( $post->post_content );
	}

	/**
	 * @return string
	 */
	public function get_builder_name() {
		return 'gutenberg';
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function extract_page_context( $post_id ) {
		$ctx = RWGA_Builder_Normalize::empty_page_context( 'gutenberg', $post_id );
		$ctx['raw_builder_meta_available'] = true;
		$ctx['sections']       = $this->extract_sections( $post_id );
		$ctx['widgets']        = $this->extract_widgets( $post_id );
		$ctx['content_blocks'] = $this->extract_content( $post_id );
		$ctx['ctas']           = $this->collect_ctas( $ctx['widgets'] );
		$ctx['forms']          = $this->collect_forms( $ctx['widgets'] );
		$ctx['media']          = $this->collect_media( $ctx['content_blocks'] );
		return $ctx;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_sections( $post_id ) {
		$parsed = $this->get_parsed_blocks( $post_id );
		if ( array() === $parsed ) {
			return array();
		}

		$sections = array();
		$index    = 0;
		foreach ( $parsed as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( '' === $name && empty( $block['innerBlocks'] ) ) {
				continue;
			}
			$section_widgets = $this->walk_widgets( array( $block ), 'gb-sec-' . $index, '' );
			$widget_ids      = array_column( $section_widgets, 'id' );
			$heading         = '';
			$has_h1          = false;
			$has_cta         = false;
			$has_form        = false;
			$has_media       = false;
			foreach ( $section_widgets as $w ) {
				if ( ! empty( $w['is_cta'] ) ) {
					$has_cta = true;
				}
				if ( ! empty( $w['is_form'] ) ) {
					$has_form = true;
				}
				if ( in_array( $w['type'], array( 'image', 'cover', 'gallery' ), true ) ) {
					$has_media = true;
				}
				if ( 'heading' === $w['type'] ) {
					$lvl = isset( $w['settings']['level'] ) ? (int) $w['settings']['level'] : 2;
					if ( 1 === $lvl && '' === $heading ) {
						$heading = $w['content'];
						$has_h1  = true;
					}
				}
			}

			$sections[] = RWGA_Builder_Normalize::section_row(
				array(
					'id'           => 'gb-sec-' . $index,
					'type'         => $this->section_type_from_block( $name ),
					'index'        => $index,
					'widget_ids'   => $widget_ids,
					'heading'      => RWGA_Builder_Normalize::trim_text( $heading, 160 ),
					'has_h1'       => $has_h1,
					'has_cta'      => $has_cta,
					'has_form'     => $has_form,
					'has_media'    => $has_media,
					'widget_count' => count( $section_widgets ),
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
		$parsed = $this->get_parsed_blocks( $post_id );
		$all    = array();
		$index  = 0;
		foreach ( $parsed as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$section_id = 'gb-sec-' . $index;
			$all        = array_merge( $all, $this->walk_widgets( array( $block ), $section_id, '' ) );
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
	private function get_parsed_blocks( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post || ! function_exists( 'parse_blocks' ) ) {
			return array();
		}
		$parsed = parse_blocks( (string) $post->post_content );
		return is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * @param string $block_name Full block name.
	 * @return string
	 */
	private function section_type_from_block( $block_name ) {
		$block_name = (string) $block_name;
		if ( str_contains( $block_name, 'group' ) || str_contains( $block_name, 'columns' ) || str_contains( $block_name, 'cover' ) ) {
			return 'layout';
		}
		return 'section';
	}

	/**
	 * @param array<int, mixed> $blocks     Blocks.
	 * @param string            $section_id Section id.
	 * @param string            $parent_id  Parent widget id.
	 * @return array<int, array<string, mixed>>
	 */
	private function walk_widgets( array $blocks, $section_id, $parent_id, &$counter = null ) {
		$widgets = array();
		if ( null === $counter ) {
			$counter = 0;
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( '' === $name && empty( $block['innerBlocks'] ) ) {
				continue;
			}

			$short    = $this->short_block_name( $name );
			$attrs    = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$content  = $this->block_text_content( $block, $short );
			$is_cta   = in_array( $short, array( 'button', 'buttons' ), true );
			$is_form  = in_array( $short, array( 'form', 'contact-form', 'wpforms-form' ), true ) || str_contains( $name, 'form' );
			$wid      = 'gb-' . ( ++$counter );

			$settings = $attrs;
			if ( 'heading' === $short && isset( $attrs['level'] ) ) {
				$settings['level'] = (int) $attrs['level'];
			}
			if ( in_array( $short, array( 'button', 'buttons' ), true ) ) {
				$settings['url'] = isset( $attrs['url'] ) ? (string) $attrs['url'] : '';
			}
			if ( 'image' === $short ) {
				$settings['url'] = isset( $attrs['url'] ) ? (string) $attrs['url'] : '';
				$settings['alt'] = isset( $attrs['alt'] ) ? (string) $attrs['alt'] : '';
			}

			$widgets[] = RWGA_Builder_Normalize::widget_row(
				array(
					'id'         => $wid,
					'type'       => $short ? $short : 'block',
					'name'       => $name ? $name : 'core/freeform',
					'section_id' => $section_id,
					'parent_id'  => $parent_id,
					'content'    => $content,
					'settings'   => $settings,
					'is_cta'     => $is_cta,
					'is_form'    => $is_form,
				)
			);

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$widgets = array_merge( $widgets, $this->walk_widgets( $block['innerBlocks'], $section_id, $wid ) );
			}
		}
		return $widgets;
	}

	/**
	 * @param string $block_name Block name.
	 * @return string
	 */
	private function short_block_name( $block_name ) {
		$block_name = (string) $block_name;
		if ( '' === $block_name ) {
			return '';
		}
		$parts = explode( '/', $block_name );
		return sanitize_key( end( $parts ) );
	}

	/**
	 * @param array<string, mixed> $block Block.
	 * @param string               $short Short name.
	 * @return string
	 */
	private function block_text_content( array $block, $short ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( 'heading' === $short && isset( $attrs['content'] ) ) {
			return RWGA_Builder_Normalize::trim_text( wp_strip_all_tags( (string) $attrs['content'] ), 300 );
		}
		if ( in_array( $short, array( 'button', 'buttons' ), true ) ) {
			$text = isset( $attrs['text'] ) ? (string) $attrs['text'] : '';
			if ( '' === $text && isset( $attrs['content'] ) ) {
				$text = wp_strip_all_tags( (string) $attrs['content'] );
			}
			return RWGA_Builder_Normalize::trim_text( $text, 120 );
		}
		if ( isset( $attrs['content'] ) && is_string( $attrs['content'] ) ) {
			return RWGA_Builder_Normalize::trim_text( wp_strip_all_tags( $attrs['content'] ), 500 );
		}
		if ( ! empty( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
			return RWGA_Builder_Normalize::trim_text( wp_strip_all_tags( $block['innerHTML'] ), 500 );
		}
		if ( function_exists( 'rwga_blocks_inner_text' ) && ! empty( $block['innerBlocks'] ) ) {
			return RWGA_Builder_Normalize::trim_text( rwga_blocks_inner_text( $block['innerBlocks'] ), 500 );
		}
		return '';
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
			);
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_media( array $blocks ) {
		$out = array();
		foreach ( $blocks as $b ) {
			if ( ! in_array( $b['type'], array( 'image', 'cover', 'gallery', 'media-text' ), true ) ) {
				continue;
			}
			$out[] = array(
				'block_id' => isset( $b['id'] ) ? (string) $b['id'] : '',
				'url'      => isset( $b['settings']['url'] ) ? (string) $b['settings']['url'] : '',
				'alt'      => isset( $b['settings']['alt'] ) ? (string) $b['settings']['alt'] : '',
			);
		}
		return $out;
	}
}
