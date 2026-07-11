<?php
/**
 * Legacy Elementor V3 node reader (flat settings).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads legacy V3 widget nodes into normalized widget rows.
 */
class RWGA_Elementor_V3_Node_Reader {

	/**
	 * @param array<string, mixed> $node       Elementor widget node.
	 * @param string               $section_id Section id.
	 * @param string               $parent_id  Parent id.
	 * @param RWGA_Elementor_Adapter $adapter  Adapter for shared helpers.
	 * @return array<string, mixed>|null
	 */
	public static function read_widget( array $node, $section_id, $parent_id, RWGA_Elementor_Adapter $adapter ) {
		$el_type     = isset( $node['elType'] ) ? (string) $node['elType'] : '';
		$widget_type = isset( $node['widgetType'] ) ? sanitize_key( (string) $node['widgetType'] ) : '';
		if ( 'widget' !== $el_type || '' === $widget_type ) {
			return null;
		}

		$node_id  = isset( $node['id'] ) ? (string) $node['id'] : '';
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$content  = $adapter->widget_text_content( $widget_type, $settings );
		$is_cta   = $adapter->is_cta_widget( $widget_type, $settings );
		$is_form  = $adapter->is_form_widget( $widget_type );

		$row = RWGA_Builder_Normalize::widget_row(
			array(
				'id'         => $node_id,
				'type'       => $widget_type,
				'name'       => $widget_type,
				'section_id' => $section_id,
				'parent_id'  => $parent_id,
				'content'    => $content,
				'settings'   => $adapter->public_settings_for_widget( $widget_type, $settings ),
				'controls'   => array_keys( $settings ),
				'is_cta'     => $is_cta,
				'is_form'    => $is_form,
			)
		);

		$row['element_version'] = RWGA_Elementor_Node_Version::V3;
		$row['semantic_type']   = self::semantic_type( $widget_type );
		if ( 'heading' === $widget_type ) {
			$row['html_tag'] = isset( $settings['header_size'] ) ? (string) $settings['header_size'] : 'h2';
		}

		return $row;
	}

	/**
	 * @param string $widget_type Widget type.
	 * @return string
	 */
	private static function semantic_type( $widget_type ) {
		$map = array(
			'heading'     => 'heading',
			'button'      => 'button',
			'text-editor' => 'paragraph',
			'image'       => 'image',
			'video'       => 'video',
			'form'        => 'form',
			'divider'     => 'divider',
		);
		return isset( $map[ $widget_type ] ) ? $map[ $widget_type ] : 'unknown';
	}
}
