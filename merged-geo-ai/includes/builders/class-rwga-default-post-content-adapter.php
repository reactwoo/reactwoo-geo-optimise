<?php
/**
 * Classic / hybrid post content adapter (fallback).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses plain post_content when no builder meta is present.
 */
class RWGA_Default_Post_Content_Adapter implements RWGA_Builder_Adapter_Interface {

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function supports( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		// Lower priority adapters handle Elementor/Gutenberg; this is always the fallback.
		return true;
	}

	/**
	 * @return string
	 */
	public function get_builder_name() {
		return 'classic';
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function extract_page_context( $post_id ) {
		$ctx = RWGA_Builder_Normalize::empty_page_context( 'classic', $post_id );
		$ctx['sections']       = $this->extract_sections( $post_id );
		$ctx['widgets']        = $this->extract_widgets( $post_id );
		$ctx['content_blocks'] = $this->extract_content( $post_id );
		$ctx['ctas']           = $this->collect_ctas_from_widgets( $ctx['widgets'] );
		$ctx['forms']          = $this->collect_forms_from_widgets( $ctx['widgets'] );
		$ctx['media']          = $this->collect_media_from_blocks( $ctx['content_blocks'] );
		return $ctx;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_sections( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$plain = function_exists( 'rwga_extract_text_for_ai' )
			? rwga_extract_text_for_ai( (string) $post->post_content, array( 'max_chars' => 4000 ) )
			: wp_strip_all_tags( (string) $post->post_content );

		$widgets = $this->extract_widgets( $post_id );
		$has_h1  = false;
		foreach ( $widgets as $w ) {
			if ( ! empty( $w['settings']['level'] ) && 1 === (int) $w['settings']['level'] ) {
				$has_h1 = true;
				break;
			}
		}

		return array(
			RWGA_Builder_Normalize::section_row(
				array(
					'id'           => 'classic-main',
					'type'         => 'section',
					'index'        => 0,
					'widget_ids'   => array_column( $widgets, 'id' ),
					'heading'      => RWGA_Builder_Normalize::trim_text( $plain, 120 ),
					'has_h1'       => $has_h1,
					'has_cta'      => $this->widgets_have_flag( $widgets, 'is_cta' ),
					'has_form'     => $this->widgets_have_flag( $widgets, 'is_form' ),
					'has_media'    => false,
					'widget_count' => count( $widgets ),
				)
			),
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_widgets( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$html   = (string) $post->post_content;
		$blocks = $this->extract_content( $post_id );
		$widgets = array();
		$i       = 0;
		foreach ( $blocks as $block ) {
			$type = isset( $block['type'] ) ? (string) $block['type'] : 'paragraph';
			$is_cta  = ( 'button' === $type || 'link' === $type );
			$is_form = ( 'form' === $type );
			$widgets[] = RWGA_Builder_Normalize::widget_row(
				array(
					'id'         => 'classic-' . $i,
					'type'       => $type,
					'name'       => $type,
					'section_id' => 'classic-main',
					'parent_id'  => '',
					'content'    => isset( $block['text'] ) ? (string) $block['text'] : '',
					'settings'   => isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array(),
					'is_cta'     => $is_cta,
					'is_form'    => $is_form,
				)
			);
			++$i;
		}

		// Shortcode buttons/links in classic HTML.
		if ( preg_match_all( '/\[button[^\]]*text=["\']([^"\']+)["\'][^\]]*\]/i', $html, $btn_m ) ) {
			foreach ( $btn_m[1] as $label ) {
				$widgets[] = RWGA_Builder_Normalize::widget_row(
					array(
						'id'         => 'classic-btn-' . $i,
						'type'       => 'button',
						'name'       => 'shortcode-button',
						'section_id' => 'classic-main',
						'content'    => (string) $label,
						'is_cta'     => true,
					)
				);
				++$i;
			}
		}

		return $widgets;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_content( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$html   = (string) $post->post_content;
		$blocks = array();
		$idx    = 0;

		foreach ( RWGA_Builder_Normalize::headings_from_html( $html ) as $h ) {
			$blocks[] = array(
				'id'       => 'classic-h-' . $idx,
				'type'     => 'heading',
				'text'     => $h['text'],
				'settings' => array( 'level' => $h['level'] ),
			);
			++$idx;
		}

		$plain = wp_strip_all_tags( $html );
		if ( '' !== trim( $plain ) ) {
			$chunks = preg_split( '/\n{2,}/', $plain );
			if ( is_array( $chunks ) ) {
				foreach ( $chunks as $chunk ) {
					$chunk = trim( (string) $chunk );
					if ( '' === $chunk ) {
						continue;
					}
					$blocks[] = array(
						'id'   => 'classic-p-' . $idx,
						'type' => 'paragraph',
						'text' => RWGA_Builder_Normalize::trim_text( $chunk, 800 ),
					);
					++$idx;
					if ( $idx > 40 ) {
						break;
					}
				}
			}
		}

		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $img_m ) ) {
			foreach ( $img_m[1] as $src ) {
				$blocks[] = array(
					'id'       => 'classic-img-' . $idx,
					'type'     => 'image',
					'text'     => '',
					'settings' => array( 'url' => esc_url_raw( (string) $src ) ),
				);
				++$idx;
			}
		}

		return $blocks;
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_ctas_from_widgets( array $widgets ) {
		$out = array();
		foreach ( $widgets as $w ) {
			if ( empty( $w['is_cta'] ) ) {
				continue;
			}
			$out[] = array(
				'widget_id'  => isset( $w['id'] ) ? (string) $w['id'] : '',
				'section_id' => isset( $w['section_id'] ) ? (string) $w['section_id'] : '',
				'label'      => isset( $w['content'] ) ? (string) $w['content'] : '',
				'url'        => isset( $w['settings']['url'] ) ? (string) $w['settings']['url'] : '',
			);
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_forms_from_widgets( array $widgets ) {
		$out = array();
		foreach ( $widgets as $w ) {
			if ( empty( $w['is_form'] ) ) {
				continue;
			}
			$out[] = array(
				'widget_id'  => isset( $w['id'] ) ? (string) $w['id'] : '',
				'section_id' => isset( $w['section_id'] ) ? (string) $w['section_id'] : '',
				'type'       => isset( $w['type'] ) ? (string) $w['type'] : 'form',
			);
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Content blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_media_from_blocks( array $blocks ) {
		$out = array();
		foreach ( $blocks as $b ) {
			if ( ! isset( $b['type'] ) || 'image' !== $b['type'] ) {
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

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @param string                           $flag    Flag key.
	 * @return bool
	 */
	private function widgets_have_flag( array $widgets, $flag ) {
		foreach ( $widgets as $w ) {
			if ( ! empty( $w[ $flag ] ) ) {
				return true;
			}
		}
		return false;
	}
}
