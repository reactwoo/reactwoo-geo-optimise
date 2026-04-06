<?php
/**
 * Collects builder- and page-defined Geo Optimise goals for test setup and validation.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes Elementor, Gutenberg, and destination-page goals into a shared shape.
 */
class RWGO_Defined_Goal_Service {

	const META_DEST_ENABLED    = '_rwgo_dest_goal_enabled';
	const META_DEST_LABEL      = '_rwgo_dest_goal_label';
	const META_DEST_TYPE       = '_rwgo_dest_goal_type';
	const META_DEST_GOAL_ID    = '_rwgo_dest_goal_id';
	const META_DEST_HANDLER_ID = '_rwgo_dest_handler_id';

	/**
	 * Stable IDs for an Elementor widget instance (matches PHP render + JSON scan).
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $element_id Elementor element id.
	 * @return array{goal_id: string, handler_id: string}
	 */
	public static function elementor_element_ids( $post_id, $element_id ) {
		$post_id    = (int) $post_id;
		$element_id = (string) $element_id;
		$h          = hash( 'sha256', 'rwgo|elm|' . $post_id . '|' . $element_id );
		return array(
			'goal_id'    => 'goal_' . substr( $h, 0, 14 ),
			'handler_id' => 'hdl_' . substr( $h, 14, 14 ),
		);
	}

	/**
	 * Map Elementor / UI goal type keys to experiment goal_type + handler notes.
	 *
	 * @param string $ui_type cta_click|navigation_click|add_to_cart|custom|page_visit|...
	 * @return string click|page_view|add_to_cart
	 */
	public static function map_ui_goal_type_to_experiment( $ui_type ) {
		$ui_type = sanitize_key( (string) $ui_type );
		if ( in_array( $ui_type, array( 'page_visit', 'thank_you', 'lead_confirmation', 'checkout_success', 'custom_destination' ), true ) ) {
			return 'page_view';
		}
		if ( in_array( $ui_type, array( 'form_submit', 'checkbox_optin' ), true ) ) {
			return 'form_submit';
		}
		return 'click';
	}

	/**
	 * Collect all defined goals for a single post (Elementor + blocks + destination meta).
	 *
	 * @param int $post_id Post ID.
	 * @return list<array<string, mixed>>
	 */
	public static function collect_for_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array();
		}
		$out = array();
		self::collect_elementor_goals( $post_id, $out );
		self::collect_block_goals( $post_id, $out );
		self::collect_destination_goal( $post_id, $out );
		/**
		 * @param list<array<string, mixed>> $out     Collected goals.
		 * @param int                          $post_id Post ID.
		 */
		return apply_filters( 'rwgo_defined_goals_for_post', $out, $post_id );
	}

	/**
	 * Union of goals across control + variant pages (deduped by goal_id).
	 *
	 * @param list<int> $post_ids Post IDs.
	 * @return list<array<string, mixed>>
	 */
	public static function collect_for_posts( array $post_ids ) {
		$seen = array();
		$all  = array();
		foreach ( $post_ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid <= 0 ) {
				continue;
			}
			foreach ( self::collect_for_post( $pid ) as $row ) {
				if ( ! is_array( $row ) || empty( $row['goal_id'] ) ) {
					continue;
				}
				$gk = (string) $row['goal_id'];
				if ( isset( $seen[ $gk ] ) ) {
					continue;
				}
				$seen[ $gk ] = true;
				$all[]       = $row;
			}
		}
		return $all;
	}

	/**
	 * @param int $post_id Post ID.
	 * @param list<array<string, mixed>> $out Output by ref.
	 * @return void
	 */
	private static function collect_elementor_goals( $post_id, array &$out ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return;
		}
		self::walk_elementor_elements( $post_id, $data, $out );
	}

	/**
	 * @param int                          $post_id Post ID.
	 * @param array<int, mixed>            $elements Elementor elements tree.
	 * @param list<array<string, mixed>>   $out Output.
	 * @return void
	 */
	private static function walk_elementor_elements( $post_id, $elements, array &$out ) {
		if ( ! is_array( $elements ) ) {
			return;
		}
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type = isset( $el['elType'] ) ? (string) $el['elType'] : '';
			if ( 'widget' === $el_type ) {
				$settings = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
				if ( ! empty( $settings['rwgo_goal_enabled'] ) && 'yes' === (string) $settings['rwgo_goal_enabled'] ) {
					$eid    = isset( $el['id'] ) ? (string) $el['id'] : '';
					$ids    = self::elementor_element_ids( $post_id, $eid );
					$label  = isset( $settings['rwgo_goal_label'] ) ? sanitize_text_field( (string) $settings['rwgo_goal_label'] ) : '';
					$ui_t   = isset( $settings['rwgo_goal_type'] ) ? sanitize_key( (string) $settings['rwgo_goal_type'] ) : 'cta_click';
					if ( '' === $label ) {
						$label = __( 'Elementor CTA', 'reactwoo-geo-optimise' );
					}
					$note = isset( $settings['rwgo_goal_note'] ) ? sanitize_text_field( (string) $settings['rwgo_goal_note'] ) : '';
					$out[] = array(
						'goal_id'           => $ids['goal_id'],
						'goal_label'        => $label,
						'goal_type'         => self::map_ui_goal_type_to_experiment( $ui_t ),
						'ui_goal_type'      => $ui_t,
						'source_type'       => 'elementor_widget',
						'goal_origin'       => 'elementor_widget',
						'goal_origin_label' => __( 'Elementor widget', 'reactwoo-geo-optimise' ),
						'source_post_id'  => $post_id,
						'handler_id'      => $ids['handler_id'],
						'builder'         => 'elementor',
						'is_defined'      => true,
						'elementor_id'    => $eid,
						'goal_note'       => $note,
					);
				}
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_elementor_elements( $post_id, $el['elements'], $out );
			}
		}
	}

	/**
	 * @param int $post_id Post ID.
	 * @param list<array<string, mixed>> $out Output.
	 * @return void
	 */
	private static function collect_block_goals( $post_id, array &$out ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		if ( ! function_exists( 'parse_blocks' ) ) {
			return;
		}
		$blocks = parse_blocks( (string) $post->post_content );
		self::walk_blocks( $post_id, $blocks, $out );
	}

	/**
	 * @param int                        $post_id Post ID.
	 * @param array<int, array>          $blocks Parsed blocks.
	 * @param list<array<string, mixed>> $out Output.
	 * @return void
	 */
	private static function walk_blocks( $post_id, $blocks, array &$out ) {
		if ( ! is_array( $blocks ) ) {
			return;
		}
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$a    = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if ( $name && ! empty( $a['rwgoGoalEnabled'] ) ) {
				$gid = isset( $a['rwgoGoalId'] ) ? sanitize_key( (string) $a['rwgoGoalId'] ) : '';
				$hid = isset( $a['rwgoHandlerId'] ) ? sanitize_key( (string) $a['rwgoHandlerId'] ) : '';
				if ( '' === $gid || '' === $hid ) {
					$sig = wp_json_encode( array( 'n' => $name, 'a' => $a ) );
					$h   = hash( 'sha256', 'rwgo|gb|' . $post_id . '|' . ( is_string( $sig ) ? $sig : '' ) );
					if ( '' === $gid ) {
						$gid = 'goal_' . substr( $h, 0, 14 );
					}
					if ( '' === $hid ) {
						$hid = 'hdl_' . substr( $h, 14, 14 );
					}
				}
				$label = isset( $a['rwgoGoalLabel'] ) ? sanitize_text_field( (string) $a['rwgoGoalLabel'] ) : '';
				$ui_t  = isset( $a['rwgoGoalType'] ) ? sanitize_key( (string) $a['rwgoGoalType'] ) : 'cta_click';
				if ( '' === $label ) {
					$label = self::default_label_for_block( $name );
				}
				$note = isset( $a['rwgoGoalNote'] ) ? sanitize_text_field( (string) $a['rwgoGoalNote'] ) : '';
				$out[] = array(
					'goal_id'           => $gid,
					'goal_label'        => $label,
					'goal_type'         => self::map_ui_goal_type_to_experiment( $ui_t ),
					'ui_goal_type'      => $ui_t,
					'source_type'       => 'gutenberg_block',
					'goal_origin'       => 'gutenberg_block',
					'goal_origin_label' => __( 'Gutenberg block', 'reactwoo-geo-optimise' ),
					'block_name'        => $name,
					'source_post_id'    => $post_id,
					'handler_id'        => $hid,
					'builder'           => 'gutenberg',
					'is_defined'        => true,
					'goal_note'         => $note,
				);
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::walk_blocks( $post_id, $block['innerBlocks'], $out );
			}
		}
	}

	/**
	 * @param string $block_name Block name.
	 * @return string
	 */
	private static function default_label_for_block( $block_name ) {
		$block_name = (string) $block_name;
		$map        = array(
			'core/button'          => __( 'Button', 'reactwoo-geo-optimise' ),
			'core/read-more'       => __( 'Read more link', 'reactwoo-geo-optimise' ),
			'core/navigation-link' => __( 'Navigation link', 'reactwoo-geo-optimise' ),
			'core/image'           => __( 'Image link', 'reactwoo-geo-optimise' ),
			'core/media-text'      => __( 'Media & text CTA', 'reactwoo-geo-optimise' ),
			'core/cover'           => __( 'Cover CTA', 'reactwoo-geo-optimise' ),
			'core/file'            => __( 'File download', 'reactwoo-geo-optimise' ),
			'core/social-link'     => __( 'Social link', 'reactwoo-geo-optimise' ),
		);
		if ( isset( $map[ $block_name ] ) ) {
			return $map[ $block_name ];
		}
		if ( 0 === strpos( $block_name, 'woocommerce/' ) ) {
			return __( 'Store CTA', 'reactwoo-geo-optimise' );
		}
		return __( 'Block goal', 'reactwoo-geo-optimise' );
	}

	/**
	 * @param int $post_id Post ID.
	 * @param list<array<string, mixed>> $out Output.
	 * @return void
	 */
	private static function collect_destination_goal( $post_id, array &$out ) {
		if ( ! get_post( $post_id ) instanceof \WP_Post ) {
			return;
		}
		$en = get_post_meta( $post_id, self::META_DEST_ENABLED, true );
		if ( '1' !== (string) $en && 'yes' !== (string) $en ) {
			return;
		}
		$label  = sanitize_text_field( (string) get_post_meta( $post_id, self::META_DEST_LABEL, true ) );
		$ui_t   = sanitize_key( (string) get_post_meta( $post_id, self::META_DEST_TYPE, true ) );
		if ( '' === $ui_t ) {
			$ui_t = 'page_visit';
		}
		$gid = sanitize_key( (string) get_post_meta( $post_id, self::META_DEST_GOAL_ID, true ) );
		$hid = sanitize_key( (string) get_post_meta( $post_id, self::META_DEST_HANDLER_ID, true ) );
		if ( '' === $gid ) {
			$gid = 'goal_' . substr( hash( 'sha256', 'rwgo|dest|' . $post_id ), 0, 14 );
		}
		if ( '' === $hid ) {
			$hid = 'hdl_' . substr( hash( 'sha256', 'rwgoh|dest|' . $post_id ), 0, 14 );
		}
		if ( '' === $label ) {
			$label = get_the_title( $post_id );
			if ( '' === $label ) {
				$label = __( 'Destination page', 'reactwoo-geo-optimise' );
			}
		}
		$out[] = array(
			'goal_id'             => $gid,
			'goal_label'          => $label,
			'goal_type'           => 'page_view',
			'ui_goal_type'        => $ui_t,
			'source_type'         => 'page_destination',
			'goal_origin'         => 'page_destination',
			'goal_origin_label'   => __( 'Page destination', 'reactwoo-geo-optimise' ),
			'source_post_id'      => $post_id,
			'handler_id'          => $hid,
			'builder'             => '',
			'is_defined'          => true,
			'destination_page_id' => $post_id,
		);
	}

	/**
	 * Ensure destination goal meta has IDs after save.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function maybe_fill_destination_ids( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		$en = get_post_meta( $post_id, self::META_DEST_ENABLED, true );
		if ( '1' !== (string) $en && 'yes' !== (string) $en ) {
			return;
		}
		$gid = (string) get_post_meta( $post_id, self::META_DEST_GOAL_ID, true );
		$hid = (string) get_post_meta( $post_id, self::META_DEST_HANDLER_ID, true );
		if ( '' === $gid ) {
			update_post_meta( $post_id, self::META_DEST_GOAL_ID, 'goal_' . substr( hash( 'sha256', 'rwgo|dest|' . $post_id ), 0, 14 ) );
		}
		if ( '' === $hid ) {
			update_post_meta( $post_id, self::META_DEST_HANDLER_ID, 'hdl_' . substr( hash( 'sha256', 'rwgoh|dest|' . $post_id ), 0, 14 ) );
		}
	}

	/**
	 * Non-fatal readiness checks for the Edit Test screen.
	 *
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return list<array{code: string, message: string}>
	 */
	public static function validate_experiment_config( array $cfg ) {
		$warnings = array();
		if ( ! empty( $cfg['assignment_only'] ) || ( isset( $cfg['winner_mode'] ) && 'traffic_only' === $cfg['winner_mode'] ) ) {
			return $warnings;
		}
		if ( empty( $cfg['goal_selection_mode'] ) || 'defined' !== $cfg['goal_selection_mode'] ) {
			return $warnings;
		}
		$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();

		if ( class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg ) ) {
			$source_page = (int) ( $cfg['source_page_id'] ?? 0 );
			$var_b       = 0;
			if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
				foreach ( $cfg['variants'] as $row ) {
					if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
						$var_b = (int) ( $row['page_id'] ?? 0 );
						break;
					}
				}
			}
			foreach ( $goals as $g ) {
				if ( ! is_array( $g ) || empty( $g['mapping_variant'] ) || empty( $g['goal_id'] ) ) {
					continue;
				}
				$mv  = sanitize_key( (string) $g['mapping_variant'] );
				$pid = 'control' === $mv ? $source_page : ( 'var_b' === $mv ? $var_b : 0 );
				if ( $pid <= 0 ) {
					continue;
				}
				$found = false;
				foreach ( self::collect_for_post( $pid ) as $row ) {
					if ( ! empty( $row['goal_id'] ) && (string) $row['goal_id'] === (string) $g['goal_id'] ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$warnings[] = array(
						'code'    => 'defined_goal_missing',
						'message' => __( 'A mapped defined goal was not found on the expected page. Edit the Control or Variant page or update the test mapping.', 'reactwoo-geo-optimise' ),
					);
					return $warnings;
				}
			}
			return $warnings;
		}

		$want  = isset( $cfg['primary_goal_id'] ) ? sanitize_key( (string) $cfg['primary_goal_id'] ) : '';
		$primary = null;
		if ( '' !== $want ) {
			foreach ( $goals as $g ) {
				if ( is_array( $g ) && ! empty( $g['goal_id'] ) && sanitize_key( (string) $g['goal_id'] ) === $want ) {
					$primary = $g;
					break;
				}
				if ( is_array( $g ) && ! empty( $g['logical_goal_id'] ) && sanitize_key( (string) $g['logical_goal_id'] ) === $want ) {
					$primary = $g;
					break;
				}
			}
		}
		if ( ! $primary ) {
			foreach ( $goals as $g ) {
				if ( is_array( $g ) && ! empty( $g['is_primary'] ) ) {
					$primary = $g;
					break;
				}
			}
		}
		if ( ! $primary ) {
			foreach ( $goals as $g ) {
				if ( is_array( $g ) && ! empty( $g['goal_id'] ) ) {
					$primary = $g;
					break;
				}
			}
		}
		if ( ! is_array( $primary ) || empty( $primary['goal_id'] ) ) {
			return $warnings;
		}
		$goal_id = (string) $primary['goal_id'];
		$src     = isset( $primary['source_type'] ) ? sanitize_key( (string) $primary['source_type'] ) : '';

		$source_page = (int) ( $cfg['source_page_id'] ?? 0 );
		$var_b       = 0;
		if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
			foreach ( $cfg['variants'] as $row ) {
				if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
					$var_b = (int) ( $row['page_id'] ?? 0 );
					break;
				}
			}
		}

		if ( 'page_destination' === $src ) {
			$dest = (int) ( $primary['destination_page_id'] ?? 0 );
			if ( $dest > 0 && ! get_post( $dest ) instanceof \WP_Post ) {
				$warnings[] = array(
					'code'    => 'destination_missing',
					'message' => __( 'The destination page for this goal no longer exists.', 'reactwoo-geo-optimise' ),
				);
			}
			return $warnings;
		}

		$pages_to_scan = array_unique( array_filter( array( $source_page, $var_b ) ) );
		$found         = false;
		foreach ( $pages_to_scan as $page_id ) {
			foreach ( self::collect_for_post( $page_id ) as $row ) {
				if ( ! empty( $row['goal_id'] ) && (string) $row['goal_id'] === $goal_id ) {
					$found = true;
					break 2;
				}
			}
		}
		if ( ! $found ) {
			$warnings[] = array(
				'code'    => 'defined_goal_missing',
				'message' => __( 'The selected defined goal was not found on Control or Variant B content. Edit the pages or pick another goal.', 'reactwoo-geo-optimise' ),
			);
		}
		return $warnings;
	}
}
