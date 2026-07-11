<?php
/**
 * Stamp Geo Optimise measurement keys into Elementor documents from a blueprint.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies tracking-manifest / defined-goal element keys onto Elementor widgets (V3 + Atomic V4).
 */
class RWGO_Measurement_Stamper {

	/**
	 * Stamp tracked elements onto a page (by Elementor widget id when provided).
	 *
	 * @param int                              $post_id  Target post.
	 * @param array<int, array<string, mixed>> $tracked  Rows with semantic_key + elementor_id (or widget_id).
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply_tracked_elements( $post_id, array $tracked ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'rwgo_stamp_bad_post', __( 'Invalid page for measurement stamp.', 'reactwoo-geo-optimise' ) );
		}
		if ( ! self::ensure_writer() ) {
			return new WP_Error( 'rwgo_stamp_no_writer', __( 'Elementor document writer is unavailable.', 'reactwoo-geo-optimise' ) );
		}

		$patches = array();
		foreach ( $tracked as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$wid = '';
			if ( ! empty( $row['elementor_id'] ) ) {
				$wid = (string) $row['elementor_id'];
			} elseif ( ! empty( $row['widget_id'] ) ) {
				$wid = (string) $row['widget_id'];
			}
			$key = isset( $row['semantic_key'] ) ? (string) $row['semantic_key'] : ( isset( $row['element_key'] ) ? (string) $row['element_key'] : '' );
			if ( class_exists( 'RWGO_Element_Key', false ) ) {
				$key = RWGO_Element_Key::sanitize( $key );
			} else {
				$key = sanitize_text_field( $key );
			}
			if ( '' === $wid || '' === $key ) {
				continue;
			}
			$label = isset( $row['goal_label'] ) ? sanitize_text_field( (string) $row['goal_label'] ) : $key;
			$type  = isset( $row['ui_goal_type'] ) ? sanitize_key( (string) $row['ui_goal_type'] ) : ( isset( $row['goal_type'] ) ? sanitize_key( (string) $row['goal_type'] ) : 'cta_click' );
			if ( '' === $type || 'click' === $type ) {
				$type = 'cta_click';
			}
			$patches[] = array(
				'widget_id' => $wid,
				'settings'  => array(
					'rwgo_goal_enabled' => 'yes',
					'rwgo_goal_label'   => $label,
					'rwgo_goal_type'    => $type,
					'rwgo_element_key'  => $key,
				),
			);
		}

		if ( empty( $patches ) ) {
			return array(
				'patched' => 0,
				'missing' => array(),
				'post_id' => $post_id,
				'note'    => 'no_patches',
			);
		}

		return RWGA_Elementor_Document_Writer::patch_many( $post_id, $patches );
	}

	/**
	 * Apply tracking manifest tracked_elements to a post.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param array<string, mixed> $manifest Manifest from RWGO_Tracking_Manifest::build().
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply_manifest( $post_id, array $manifest ) {
		$tracked = isset( $manifest['tracked_elements'] ) && is_array( $manifest['tracked_elements'] )
			? $manifest['tracked_elements']
			: array();
		return self::apply_tracked_elements( $post_id, $tracked );
	}

	/**
	 * Copy measurement keys from Control onto Variant B by widget-type order pairing.
	 *
	 * Used after duplicate when Elementor regenerates element ids but structure matches.
	 *
	 * @param int $source_post_id Control page.
	 * @param int $target_post_id Variant page.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function sync_keys_source_to_target( $source_post_id, $target_post_id ) {
		$source_post_id = (int) $source_post_id;
		$target_post_id = (int) $target_post_id;
		if ( $source_post_id <= 0 || $target_post_id <= 0 ) {
			return new WP_Error( 'rwgo_stamp_bad_pair', __( 'Source and target pages are required.', 'reactwoo-geo-optimise' ) );
		}
		if ( ! self::ensure_writer() ) {
			return new WP_Error( 'rwgo_stamp_no_writer', __( 'Elementor document writer is unavailable.', 'reactwoo-geo-optimise' ) );
		}

		$src_data = RWGA_Elementor_Document_Writer::load_data( $source_post_id );
		if ( is_wp_error( $src_data ) ) {
			return $src_data;
		}
		$tgt_data = RWGA_Elementor_Document_Writer::load_data( $target_post_id );
		if ( is_wp_error( $tgt_data ) ) {
			return $tgt_data;
		}

		$src_widgets = array();
		$tgt_widgets = array();
		self::flatten_widgets( $src_data, $src_widgets );
		self::flatten_widgets( $tgt_data, $tgt_widgets );

		$src_by_type = array();
		foreach ( $src_widgets as $w ) {
			if ( empty( $w['goal_enabled'] ) ) {
				continue;
			}
			$t = $w['widget_type'];
			if ( ! isset( $src_by_type[ $t ] ) ) {
				$src_by_type[ $t ] = array();
			}
			$src_by_type[ $t ][] = $w;
		}

		$tgt_by_type = array();
		foreach ( $tgt_widgets as $w ) {
			$t = $w['widget_type'];
			if ( ! isset( $tgt_by_type[ $t ] ) ) {
				$tgt_by_type[ $t ] = array();
			}
			$tgt_by_type[ $t ][] = $w;
		}

		$patches = array();
		foreach ( $src_by_type as $type => $rows ) {
			if ( empty( $tgt_by_type[ $type ] ) ) {
				continue;
			}
			foreach ( $rows as $i => $src ) {
				if ( ! isset( $tgt_by_type[ $type ][ $i ] ) ) {
					break;
				}
				$tgt = $tgt_by_type[ $type ][ $i ];
				$key = $src['element_key'];
				if ( '' === $key && class_exists( 'RWGO_Element_Key', false ) ) {
					$key = RWGO_Element_Key::resolve( '', $src['goal_label'], $src['goal_type'] );
				}
				if ( '' === $key ) {
					continue;
				}
				$patches[] = array(
					'widget_id' => $tgt['id'],
					'settings'  => array(
						'rwgo_goal_enabled' => 'yes',
						'rwgo_goal_label'   => $src['goal_label'],
						'rwgo_goal_type'    => $src['goal_type'],
						'rwgo_element_key'  => $key,
					),
				);
			}
		}

		if ( empty( $patches ) ) {
			return array(
				'patched' => 0,
				'missing' => array(),
				'post_id' => $target_post_id,
				'note'    => 'no_goal_pairs',
			);
		}

		return RWGA_Elementor_Document_Writer::patch_many( $target_post_id, $patches );
	}

	/**
	 * Sync keys for an experiment's Control → Variant B pages.
	 *
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function sync_experiment_config( array $cfg ) {
		$source = (int) ( $cfg['source_page_id'] ?? 0 );
		$var_b  = (int) ( $cfg['variant_b_page_id'] ?? 0 );
		if ( $var_b <= 0 && ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
			foreach ( $cfg['variants'] as $row ) {
				if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
					$var_b = (int) ( $row['page_id'] ?? 0 );
					break;
				}
			}
		}
		if ( $source <= 0 || $var_b <= 0 ) {
			return new WP_Error( 'rwgo_stamp_no_pages', __( 'Experiment is missing Control or Variant B page.', 'reactwoo-geo-optimise' ) );
		}
		return self::sync_keys_source_to_target( $source, $var_b );
	}

	/**
	 * After variant duplicate: ensure measurement keys exist on the new page.
	 *
	 * @param int $new_id         New post ID.
	 * @param int $source_post_id Source post ID.
	 * @return void
	 */
	public static function on_post_duplicate_variant( $new_id, $source_post_id ) {
		$result = self::sync_keys_source_to_target( (int) $source_post_id, (int) $new_id );
		/**
		 * @param array<string, mixed>|\WP_Error $result Result.
		 * @param int                            $new_id New page.
		 * @param int                            $source_post_id Source.
		 */
		do_action( 'rwgo_measurement_keys_synced', $result, (int) $new_id, (int) $source_post_id );
	}

	/**
	 * @return bool
	 */
	private static function ensure_writer() {
		if ( class_exists( 'RWGA_Elementor_Document_Writer', false ) ) {
			return true;
		}
		if ( defined( 'RWGA_PATH' ) ) {
			$ver = RWGA_PATH . 'includes/builders/elementor/class-rwga-elementor-node-version.php';
			$wri = RWGA_PATH . 'includes/builders/elementor/class-rwga-elementor-document-writer.php';
			if ( is_readable( $ver ) ) {
				require_once $ver;
			}
			if ( is_readable( $wri ) ) {
				require_once $wri;
			}
		}
		return class_exists( 'RWGA_Elementor_Document_Writer', false );
	}

	/**
	 * Flatten widget nodes with goal settings.
	 *
	 * @param array<int, mixed>            $elements Elements.
	 * @param list<array<string, mixed>>   $out      Output.
	 * @return void
	 */
	private static function flatten_widgets( array $elements, array &$out ) {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type = isset( $el['elType'] ) ? (string) $el['elType'] : '';
			if ( 'widget' === $el_type || ( isset( $el['widgetType'] ) && '' !== (string) $el['widgetType'] ) ) {
				$settings = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
				$enabled  = self::setting_string( $settings, 'rwgo_goal_enabled' );
				$out[]    = array(
					'id'          => isset( $el['id'] ) ? (string) $el['id'] : '',
					'widget_type' => isset( $el['widgetType'] ) ? sanitize_key( (string) $el['widgetType'] ) : '',
					'goal_enabled'=> ( 'yes' === $enabled || '1' === $enabled || 'true' === strtolower( $enabled ) ),
					'goal_label'  => self::setting_string( $settings, 'rwgo_goal_label', __( 'Elementor CTA', 'reactwoo-geo-optimise' ) ),
					'goal_type'   => self::setting_string( $settings, 'rwgo_goal_type', 'cta_click' ) ?: 'cta_click',
					'element_key' => class_exists( 'RWGO_Element_Key', false )
						? RWGO_Element_Key::sanitize( self::setting_string( $settings, 'rwgo_element_key' ) )
						: self::setting_string( $settings, 'rwgo_element_key' ),
				);
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::flatten_widgets( $el['elements'], $out );
			}
		}
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $key      Key.
	 * @param string               $default  Default.
	 * @return string
	 */
	public static function setting_string( array $settings, $key, $default = '' ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return $default;
		}
		$v = $settings[ $key ];
		if ( is_array( $v ) && isset( $v['$$type'] ) && array_key_exists( 'value', $v ) ) {
			$v = $v['value'];
		}
		if ( is_bool( $v ) ) {
			return $v ? 'yes' : '';
		}
		if ( is_scalar( $v ) ) {
			return (string) $v;
		}
		return $default;
	}
}
