<?php
/**
 * Elementor semantic context — template meta and layout meaning.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Elementor document metadata to shared semantics.
 */
class RWGA_Elementor_Context_Extractor extends RWGA_Context_Extractor_Base {

	/**
	 * @return string
	 */
	public function get_builder_slug() {
		return 'elementor';
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Builder payload.
	 * @return array<string, mixed>
	 */
	protected function extract_builder_meta( $post_id, array $payload ) {
		$post_id = (int) $post_id;
		$meta    = array(
			'template_type' => sanitize_key( (string) get_post_meta( $post_id, '_elementor_template_type', true ) ),
			'edit_mode'     => sanitize_key( (string) get_post_meta( $post_id, '_elementor_edit_mode', true ) ),
			'version'       => (string) get_post_meta( $post_id, '_elementor_version', true ),
		);

		$page_settings = get_post_meta( $post_id, '_elementor_page_settings', true );
		if ( is_array( $page_settings ) ) {
			$meta['hide_title'] = ! empty( $page_settings['hide_title'] );
			if ( ! empty( $page_settings['page_title'] ) ) {
				$meta['elementor_page_title'] = RWGA_Builder_Normalize::trim_text( (string) $page_settings['page_title'], 120 );
			}
		}

		$widget_types = array();
		foreach ( isset( $payload['widgets'] ) && is_array( $payload['widgets'] ) ? $payload['widgets'] : array() as $w ) {
			if ( is_array( $w ) && ! empty( $w['type'] ) ) {
				$widget_types[] = sanitize_key( (string) $w['type'] );
			}
		}
		$meta['widget_families'] = array_values( array_unique( $widget_types ) );

		return array_filter( $meta, static function ( $value ) {
			if ( is_array( $value ) ) {
				return array() !== $value;
			}
			return '' !== $value && false !== $value;
		} );
	}
}
