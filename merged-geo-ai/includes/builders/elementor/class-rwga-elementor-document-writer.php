<?php
/**
 * Elementor document writer (V3 legacy + Atomic V4 setting patches).
 *
 * Does not construct full Atomic pages — only patches existing nodes and persists
 * `_elementor_data`. Content props on Atomic nodes use typed { $$type, value } wrappers
 * when the node is detected as V4; Geo Optimise measurement keys stay plain strings.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Low-level Elementor JSON document mutations.
 */
class RWGA_Elementor_Document_Writer {

	/**
	 * Load decoded Elementor elements tree.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, mixed>|\WP_Error
	 */
	public static function load_data( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'rwga_el_bad_post', __( 'Invalid post for Elementor write.', 'reactwoo-geo-ai' ) );
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return new WP_Error( 'rwga_el_no_data', __( 'This page has no Elementor data.', 'reactwoo-geo-ai' ) );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'rwga_el_bad_json', __( 'Elementor data could not be decoded.', 'reactwoo-geo-ai' ) );
		}
		return $data;
	}

	/**
	 * Persist Elementor tree and clear CSS cache when possible.
	 *
	 * @param int               $post_id Post ID.
	 * @param array<int, mixed> $data    Elements tree.
	 * @param array<string, mixed> $options Optional: mark_builder (bool, default true).
	 * @return true|\WP_Error
	 */
	public static function save_data( $post_id, array $data, array $options = array() ) {
		$post_id = (int) $post_id;
		$encoded = wp_json_encode( $data );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return new WP_Error( 'rwga_el_encode', __( 'Could not encode Elementor data.', 'reactwoo-geo-ai' ) );
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( $encoded ) );

		$mark_builder = ! isset( $options['mark_builder'] ) || ! empty( $options['mark_builder'] );
		if ( $mark_builder ) {
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
			if ( '' === (string) get_post_meta( $post_id, '_elementor_template_type', true ) ) {
				update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
			}
			if ( '' === (string) get_post_meta( $post_id, '_elementor_version', true ) ) {
				$ver = defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '3.0.0';
				update_post_meta( $post_id, '_elementor_version', $ver );
			}
		}

		if ( class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Non-fatal: meta is already saved.
			}
		}

		/**
		 * After Elementor document JSON was written.
		 *
		 * @param int               $post_id Post ID.
		 * @param array<int, mixed> $data    Saved tree.
		 */
		do_action( 'rwga_elementor_document_saved', $post_id, $data );

		return true;
	}

	/**
	 * Replace (or create) the full Elementor document tree on a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<int, mixed>    $data    Full elements tree.
	 * @param array<string, mixed> $options Passed to save_data().
	 * @return true|\WP_Error
	 */
	public static function write_document( $post_id, array $data, array $options = array() ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'rwga_el_bad_post', __( 'Invalid post for Elementor write.', 'reactwoo-geo-ai' ) );
		}
		if ( empty( $data ) ) {
			return new WP_Error( 'rwga_el_empty_doc', __( 'Elementor document tree is empty.', 'reactwoo-geo-ai' ) );
		}
		return self::save_data( $post_id, $data, $options );
	}

	/**
	 * Patch settings on a widget node by Elementor element id.
	 *
	 * @param int                  $post_id    Post ID.
	 * @param string               $widget_id  Element id.
	 * @param array<string, mixed> $settings   Key => value (plain PHP).
	 * @param array<string, mixed> $options    Optional: force_typed (bool), plain_keys (list).
	 * @return array<string, mixed>|\WP_Error { patched: bool, widget_id: string }
	 */
	public static function patch_widget_settings( $post_id, $widget_id, array $settings, array $options = array() ) {
		$widget_id = (string) $widget_id;
		if ( '' === $widget_id || empty( $settings ) ) {
			return new WP_Error( 'rwga_el_bad_patch', __( 'Widget id and settings are required.', 'reactwoo-geo-ai' ) );
		}

		$data = self::load_data( $post_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$patched = false;
		self::walk_and_patch( $data, $widget_id, $settings, $options, $patched );
		if ( ! $patched ) {
			return new WP_Error(
				'rwga_el_widget_missing',
				sprintf(
					/* translators: %s: Elementor element id */
					__( 'Elementor widget %s was not found on this page.', 'reactwoo-geo-ai' ),
					$widget_id
				)
			);
		}

		$saved = self::save_data( $post_id, $data );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'patched'   => true,
			'widget_id' => $widget_id,
			'post_id'   => (int) $post_id,
		);
	}

	/**
	 * Apply multiple widget patches in one load/save cycle.
	 *
	 * @param int                              $post_id Post ID.
	 * @param array<int, array<string, mixed>> $patches Each: widget_id + settings (+ optional options).
	 * @return array<string, mixed>|\WP_Error { patched: int, missing: list<string> }
	 */
	public static function patch_many( $post_id, array $patches ) {
		$data = self::load_data( $post_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$patched_count = 0;
		$missing       = array();
		foreach ( $patches as $patch ) {
			if ( ! is_array( $patch ) ) {
				continue;
			}
			$wid = isset( $patch['widget_id'] ) ? (string) $patch['widget_id'] : '';
			$set = isset( $patch['settings'] ) && is_array( $patch['settings'] ) ? $patch['settings'] : array();
			$opt = isset( $patch['options'] ) && is_array( $patch['options'] ) ? $patch['options'] : array();
			if ( '' === $wid || empty( $set ) ) {
				continue;
			}
			$hit = false;
			self::walk_and_patch( $data, $wid, $set, $opt, $hit );
			if ( $hit ) {
				++$patched_count;
			} else {
				$missing[] = $wid;
			}
		}

		if ( $patched_count > 0 ) {
			$saved = self::save_data( $post_id, $data );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		return array(
			'patched' => $patched_count,
			'missing' => $missing,
			'post_id' => (int) $post_id,
		);
	}

	/**
	 * Encode a plain value for storage on a node.
	 *
	 * @param array<string, mixed> $node    Node.
	 * @param string               $key     Setting key.
	 * @param mixed                $value   Plain value.
	 * @param array<string, mixed> $options Options.
	 * @return mixed
	 */
	public static function encode_setting_value( array $node, $key, $value, array $options = array() ) {
		$key        = (string) $key;
		$plain_keys = isset( $options['plain_keys'] ) && is_array( $options['plain_keys'] )
			? array_map( 'strval', $options['plain_keys'] )
			: array( 'rwgo_goal_enabled', 'rwgo_goal_label', 'rwgo_goal_type', 'rwgo_element_key', 'rwgo_goal_note' );

		if ( in_array( $key, $plain_keys, true ) ) {
			return $value;
		}

		$force_typed = ! empty( $options['force_typed'] );
		$is_v4       = class_exists( 'RWGA_Elementor_Node_Version', false )
			&& RWGA_Elementor_Node_Version::V4 === RWGA_Elementor_Node_Version::detect( $node );

		if ( ! $force_typed && ! $is_v4 ) {
			return $value;
		}

		if ( is_bool( $value ) ) {
			return array(
				'$$type' => 'boolean',
				'value'  => $value,
			);
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return array(
				'$$type' => 'number',
				'value'  => $value,
			);
		}
		if ( is_string( $value ) ) {
			return array(
				'$$type' => 'string',
				'value'  => $value,
			);
		}

		return $value;
	}

	/**
	 * @param array<int, mixed>    $elements Elements (by ref).
	 * @param string               $widget_id Target id.
	 * @param array<string, mixed> $settings Settings to merge.
	 * @param array<string, mixed> $options Options.
	 * @param bool                 $patched Patched flag (by ref).
	 * @return void
	 */
	private static function walk_and_patch( array &$elements, $widget_id, array $settings, array $options, &$patched ) {
		foreach ( $elements as &$el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$id = isset( $el['id'] ) ? (string) $el['id'] : '';
			if ( $id === $widget_id ) {
				if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
					$el['settings'] = array();
				}
				foreach ( $settings as $k => $v ) {
					$el['settings'][ (string) $k ] = self::encode_setting_value( $el, (string) $k, $v, $options );
				}
				$patched = true;
				return;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_and_patch( $el['elements'], $widget_id, $settings, $options, $patched );
				if ( $patched ) {
					return;
				}
			}
		}
		unset( $el );
	}
}
