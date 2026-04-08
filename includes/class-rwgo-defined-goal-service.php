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
		$visited_templates = array(
			(int) $post_id => true,
		);
		self::walk_elementor_elements( $post_id, $data, $out, $post_id, $visited_templates );
	}

	/**
	 * @param int                          $post_id Post ID.
	 * @param array<int, mixed>            $elements Elementor elements tree.
	 * @param list<array<string, mixed>>   $out Output.
	 * @return void
	 */
	private static function walk_elementor_elements( $post_id, $elements, array &$out, $root_post_id = 0, array &$visited_templates = array() ) {
		if ( ! is_array( $elements ) ) {
			return;
		}
		$post_id      = (int) $post_id;
		$root_post_id = (int) $root_post_id;
		if ( $root_post_id <= 0 ) {
			$root_post_id = $post_id;
		}
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type = isset( $el['elType'] ) ? (string) $el['elType'] : '';
			if ( 'widget' === $el_type ) {
				$widget_type = isset( $el['widgetType'] ) ? sanitize_key( (string) $el['widgetType'] ) : '';
				$settings = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
				if ( ! empty( $settings['rwgo_goal_enabled'] ) && 'yes' === (string) $settings['rwgo_goal_enabled'] ) {
					$eid    = isset( $el['id'] ) ? (string) $el['id'] : '';
					$ids    = self::elementor_element_ids( $root_post_id, $eid );
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
						'source_post_id'  => $root_post_id,
						'handler_id'      => $ids['handler_id'],
						'builder'         => 'elementor',
						'is_defined'      => true,
						'elementor_id'    => $eid,
						'goal_note'       => $note,
					);
				}
				if ( 'template' === $widget_type ) {
					$template_id = 0;
					if ( ! empty( $settings['template_id'] ) ) {
						$template_id = (int) $settings['template_id'];
					} elseif ( ! empty( $settings['saved_template'] ) ) {
						$template_id = (int) $settings['saved_template'];
					}
					if ( $template_id > 0 && empty( $visited_templates[ $template_id ] ) ) {
						$template_raw = get_post_meta( $template_id, '_elementor_data', true );
						if ( is_string( $template_raw ) && '' !== $template_raw ) {
							$template_data = json_decode( $template_raw, true );
							if ( is_array( $template_data ) ) {
								$visited_templates[ $template_id ] = true;
								self::walk_elementor_elements( $template_id, $template_data, $out, $root_post_id, $visited_templates );
							}
						}
					}
				}
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_elementor_elements( $post_id, $el['elements'], $out, $root_post_id, $visited_templates );
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
	 * @return list<array{code: string, message: string, mapping_variant?: string, page_id?: int, goal_label?: string}>
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
				if ( $found ) {
					continue;
				}
				$glab = isset( $g['label'] ) ? sanitize_text_field( (string) $g['label'] ) : '';
				if ( '' === $glab ) {
					$glab = __( '(unnamed goal)', 'reactwoo-geo-optimise' );
				}
				$side_name   = 'control' === $mv ? __( 'Control', 'reactwoo-geo-optimise' ) : __( 'Variant B', 'reactwoo-geo-optimise' );
				$page_title  = get_the_title( $pid );
				if ( '' === (string) $page_title ) {
					$page_title = __( '(page)', 'reactwoo-geo-optimise' );
				}
				$warnings[] = array(
					'code'            => 'defined_goal_missing',
					'message'         => sprintf(
						/* translators: 1: saved goal label, 2: Control or Variant B, 3: page title */
						__( 'The mapped goal “%1$s” is no longer present on %2$s (%3$s). It may have been removed in the editor. Restore the marker or choose another goal under Goal & tracking.', 'reactwoo-geo-optimise' ),
						$glab,
						$side_name,
						$page_title
					),
					'mapping_variant' => $mv,
					'page_id'         => $pid,
					'goal_label'      => $glab,
				);
			}
			$control_goal = null;
			$var_b_goal   = null;
			foreach ( $goals as $g ) {
				if ( ! is_array( $g ) || empty( $g['mapping_variant'] ) ) {
					continue;
				}
				$mv = sanitize_key( (string) $g['mapping_variant'] );
				if ( 'control' === $mv && null === $control_goal ) {
					$control_goal = $g;
				} elseif ( 'var_b' === $mv && null === $var_b_goal ) {
					$var_b_goal = $g;
				}
			}
			if ( is_array( $control_goal ) && is_array( $var_b_goal ) ) {
				$control_key = self::preferred_physical_goal_match_key( $control_goal, true );
				$var_b_key   = self::preferred_physical_goal_match_key( $var_b_goal, true );
				if ( '' !== $control_key && '' !== $var_b_key && $control_key !== $var_b_key ) {
					$warnings[] = array(
						'code'    => 'defined_goal_reselect_needed',
						'message' => __( 'Control and Variant B no longer describe the same defined goal label/type. Re-select the goal under Goal & tracking before publishing changes or relying on conversion totals.', 'reactwoo-geo-optimise' ),
					);
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
			$glab = isset( $primary['label'] ) ? sanitize_text_field( (string) $primary['label'] ) : '';
			if ( '' === $glab ) {
				$glab = __( '(unnamed goal)', 'reactwoo-geo-optimise' );
			}
			$warnings[] = array(
				'code'    => 'defined_goal_missing',
				'message' => sprintf(
					/* translators: %s: saved goal label */
					__( 'The defined goal “%s” was not found on Control or Variant B. It may have been removed — edit those pages or pick another goal under Goal & tracking.', 'reactwoo-geo-optimise' ),
					$glab
				),
				'goal_label' => $glab,
			);
		}
		$primary   = self::enrich_saved_goal_with_live_identity( $primary, self::collect_for_posts( $pages_to_scan ) );
		$match_key = self::preferred_physical_goal_match_key( $primary, true );
		if ( '' !== $match_key && $source_page > 0 && $var_b > 0 ) {
			$source_has_match = self::post_has_live_goal_match_key( $source_page, $match_key );
			$var_b_has_match  = self::post_has_live_goal_match_key( $var_b, $match_key );
			if ( $source_has_match xor $var_b_has_match ) {
				$warnings[] = array(
					'code'    => 'defined_goal_reselect_needed',
					'message' => __( 'The selected defined goal now matches only one side of the test. If you changed, replaced, or removed the CTA on Control or Variant B, re-select the goal under Goal & tracking before publishing.', 'reactwoo-geo-optimise' ),
				);
			}
		}
		return $warnings;
	}

	/**
	 * Whether a post still contains a comparable builder-defined goal for the saved match key.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $match_key  Match key from {@see preferred_physical_goal_match_key()}.
	 * @return bool
	 */
	private static function post_has_live_goal_match_key( $post_id, $match_key ) {
		$post_id   = (int) $post_id;
		$match_key = (string) $match_key;
		if ( $post_id <= 0 || '' === $match_key ) {
			return false;
		}
		foreach ( self::collect_for_post( $post_id ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( self::preferred_physical_goal_match_key( $row, false ) === $match_key ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * When `ui_goal_type` is missing in saved meta, match Elementor/Gutenberg defaults (same as {@see collect_elementor_goals()}).
	 *
	 * @param string $raw_ui     Sanitized or empty.
	 * @param string $goal_type  Experiment goal_type (click|form_submit|…).
	 * @return string Non-empty key segment for pairing.
	 */
	public static function normalize_ui_goal_type_for_physical_match( $raw_ui, $goal_type ) {
		$raw_ui = sanitize_key( (string) $raw_ui );
		if ( '' !== $raw_ui ) {
			return $raw_ui;
		}
		$gt = sanitize_key( (string) $goal_type );
		if ( 'form_submit' === $gt ) {
			return 'form_submit';
		}
		return 'cta_click';
	}

	/**
	 * Stable key for pairing saved experiment goals with {@see collect_for_post()} rows (label + UI type + builder).
	 *
	 * @param array<string, mixed> $row            Discovered row (`goal_label`) or saved config row (`label`).
	 * @param bool                   $from_saved_meta When true, read `label`; when false, read `goal_label`.
	 * @return string Empty if not comparable (e.g. page_destination).
	 */
	public static function physical_goal_match_key( array $row, $from_saved_meta = false ) {
		$builder = isset( $row['builder'] ) ? sanitize_key( (string) $row['builder'] ) : '';
		$st      = isset( $row['source_type'] ) ? sanitize_key( (string) $row['source_type'] ) : '';
		$prefix  = '';
		if ( 'elementor' === $builder && ( 'elementor_widget' === $st || ( $from_saved_meta && ( '' === $st || 'defined' === $st ) ) ) ) {
			$prefix = 'el:';
		} elseif ( 'gutenberg' === $builder && ( 'gutenberg_block' === $st || ( $from_saved_meta && ( '' === $st || 'defined' === $st ) ) ) ) {
			$prefix = 'gb:';
		} else {
			return '';
		}
		$label = $from_saved_meta
			? ( isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '' )
			: ( isset( $row['goal_label'] ) ? sanitize_text_field( (string) $row['goal_label'] ) : '' );
		$gt    = isset( $row['goal_type'] ) ? sanitize_key( (string) $row['goal_type'] ) : 'click';
		$raw_ui = isset( $row['ui_goal_type'] ) ? (string) $row['ui_goal_type'] : '';
		$ui     = self::normalize_ui_goal_type_for_physical_match( $raw_ui, $gt );
		return $prefix . $label . "\x1e" . $ui;
	}

	/**
	 * Preferred cross-page identity for a defined goal row.
	 *
	 * Elementor duplicates preserve widget `elementor_id` across source and variant pages, while
	 * physical goal_id / handler_id hashes differ per post. Prefer that stable identity when present;
	 * otherwise fall back to the label + UI-type matcher.
	 *
	 * @param array<string, mixed> $row Saved or discovered goal row.
	 * @param bool                 $from_saved_meta Whether row comes from saved config.
	 * @return string
	 */
	public static function preferred_physical_goal_match_key( array $row, $from_saved_meta = false ) {
		$builder = isset( $row['builder'] ) ? sanitize_key( (string) $row['builder'] ) : '';
		if ( 'elementor' === $builder && ! empty( $row['elementor_id'] ) ) {
			return 'elid:' . sanitize_key( (string) $row['elementor_id'] );
		}
		return self::physical_goal_match_key( $row, $from_saved_meta );
	}

	/**
	 * Enrich an older saved goal row with stable live identity metadata when it still matches a live pair.
	 *
	 * @param array<string, mixed>            $saved Saved goal row.
	 * @param list<array<string, mixed>> $rows  Live discovered rows across relevant pages.
	 * @return array<string, mixed>
	 */
	public static function enrich_saved_goal_with_live_identity( array $saved, array $rows ) {
		if ( ! empty( $saved['elementor_id'] ) || ( isset( $saved['builder'] ) && 'elementor' !== sanitize_key( (string) $saved['builder'] ) ) ) {
			return $saved;
		}
		$gid = isset( $saved['goal_id'] ) ? sanitize_key( (string) $saved['goal_id'] ) : '';
		$h0  = isset( $saved['handlers'][0] ) && is_array( $saved['handlers'][0] ) ? $saved['handlers'][0] : array();
		$hid = isset( $h0['handler_id'] ) ? sanitize_key( (string) $h0['handler_id'] ) : '';
		if ( '' === $gid || '' === $hid ) {
			return $saved;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( sanitize_key( (string) ( $row['goal_id'] ?? '' ) ) !== $gid ) {
				continue;
			}
			if ( sanitize_key( (string) ( $row['handler_id'] ?? '' ) ) !== $hid ) {
				continue;
			}
			if ( ! empty( $row['elementor_id'] ) ) {
				$saved['elementor_id'] = sanitize_key( (string) $row['elementor_id'] );
			}
			if ( empty( $saved['builder'] ) && ! empty( $row['builder'] ) ) {
				$saved['builder'] = sanitize_key( (string) $row['builder'] );
			}
			if ( empty( $saved['source_type'] ) && ! empty( $row['source_type'] ) ) {
				$saved['source_type'] = sanitize_key( (string) $row['source_type'] );
			}
			return $saved;
		}
		return $saved;
	}

	/**
	 * Which page a saved goal row belongs to for resync (Control vs Variant B).
	 *
	 * @param array<string, mixed> $cfg Full experiment config (normalized).
	 * @param array<string, mixed> $g   One goal row.
	 * @return int 0 if unknown.
	 */
	private static function page_id_for_saved_defined_goal( array $cfg, array $g ) {
		$mv = isset( $g['mapping_variant'] ) ? sanitize_key( (string) $g['mapping_variant'] ) : '';
		$src = (int) ( $cfg['source_page_id'] ?? 0 );
		$var_b = 0;
		if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
			foreach ( $cfg['variants'] as $row ) {
				if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
					$var_b = (int) ( $row['page_id'] ?? 0 );
					break;
				}
			}
		}
		if ( 'control' === $mv ) {
			return $src;
		}
		if ( 'var_b' === $mv ) {
			return $var_b;
		}
		if ( '' === $mv && ! empty( $g['is_primary'] ) ) {
			return $src;
		}
		if ( '' === $mv ) {
			return $src > 0 ? $src : $var_b;
		}
		return 0;
	}

	/**
	 * Pick the next live builder row for a saved goal: prefer still-valid IDs, else first unused label+UI match.
	 *
	 * @param list<array<string, mixed>> $live       Goals from {@see collect_for_post()}.
	 * @param array<string, mixed>       $saved      Saved goal row.
	 * @param array<string, true>        $used_keys  Consumed pool keys (mutated).
	 * @return array<string, mixed>|null
	 */
	private static function pick_live_row_for_saved_goal( array $live, array $saved, array &$used_keys ) {
		$sgid = isset( $saved['goal_id'] ) ? sanitize_key( (string) $saved['goal_id'] ) : '';
		$h0   = isset( $saved['handlers'][0] ) && is_array( $saved['handlers'][0] ) ? $saved['handlers'][0] : array();
		$shid = isset( $h0['handler_id'] ) ? sanitize_key( (string) $h0['handler_id'] ) : '';
		foreach ( $live as $row ) {
			if ( ! is_array( $row ) || empty( $row['goal_id'] ) ) {
				continue;
			}
			if ( $sgid !== '' && $shid !== ''
				&& (string) $row['goal_id'] === $sgid
				&& isset( $row['handler_id'] ) && (string) $row['handler_id'] === $shid ) {
				return $row;
			}
		}
		$want = self::preferred_physical_goal_match_key( $saved, true );
		if ( '' !== $want ) {
			foreach ( $live as $row ) {
				if ( ! is_array( $row ) || empty( $row['goal_id'] ) ) {
					continue;
				}
				if ( self::preferred_physical_goal_match_key( $row, false ) !== $want ) {
					continue;
				}
				$pk = (string) $row['goal_id'] . '|' . sanitize_key( (string) ( $row['handler_id'] ?? '' ) );
				if ( isset( $used_keys[ $pk ] ) ) {
					continue;
				}
				return $row;
			}
		}

		$best       = null;
		$best_score = -1;
		foreach ( $live as $row ) {
			if ( ! is_array( $row ) || empty( $row['goal_id'] ) ) {
				continue;
			}
			$pk = (string) $row['goal_id'] . '|' . sanitize_key( (string) ( $row['handler_id'] ?? '' ) );
			if ( isset( $used_keys[ $pk ] ) ) {
				continue;
			}
			$score = self::loose_live_match_score( $saved, $row );
			if ( $score > $best_score ) {
				$best       = $row;
				$best_score = $score;
			}
		}
		return $best;
	}

	/**
	 * Score a live builder goal row for a saved goal when older configs lack full builder metadata.
	 *
	 * @param array<string, mixed> $saved Saved experiment goal row.
	 * @param array<string, mixed> $row   Live discovered builder goal row.
	 * @return int Negative when not comparable; larger is better.
	 */
	public static function loose_live_match_score( array $saved, array $row ) {
		$saved_label = isset( $saved['label'] ) ? sanitize_text_field( (string) $saved['label'] ) : '';
		$live_label  = isset( $row['goal_label'] ) ? sanitize_text_field( (string) $row['goal_label'] ) : '';
		$saved_gt    = isset( $saved['goal_type'] ) ? sanitize_key( (string) $saved['goal_type'] ) : 'click';
		$live_gt     = isset( $row['goal_type'] ) ? sanitize_key( (string) $row['goal_type'] ) : 'click';
		$saved_ui    = self::normalize_ui_goal_type_for_physical_match( isset( $saved['ui_goal_type'] ) ? (string) $saved['ui_goal_type'] : '', $saved_gt );
		$live_ui     = self::normalize_ui_goal_type_for_physical_match( isset( $row['ui_goal_type'] ) ? (string) $row['ui_goal_type'] : '', $live_gt );
		$saved_b     = isset( $saved['builder'] ) ? sanitize_key( (string) $saved['builder'] ) : '';
		$live_b      = isset( $row['builder'] ) ? sanitize_key( (string) $row['builder'] ) : '';
		$saved_eid   = isset( $saved['elementor_id'] ) ? sanitize_key( (string) $saved['elementor_id'] ) : '';
		$live_eid    = isset( $row['elementor_id'] ) ? sanitize_key( (string) $row['elementor_id'] ) : '';
		if ( '' === $saved_label && '' === $saved_ui && '' === $saved_b ) {
			return -1;
		}
		if ( '' !== $saved_eid ) {
			if ( '' === $live_eid || $saved_eid !== $live_eid ) {
				return -1;
			}
		}
		if ( '' !== $saved_label && '' !== $live_label && $saved_label !== $live_label ) {
			return -1;
		}
		if ( '' !== $saved_ui && '' !== $live_ui && $saved_ui !== $live_ui ) {
			return -1;
		}
		if ( '' !== $saved_b && '' !== $live_b && $saved_b !== $live_b ) {
			return -1;
		}
		$score = 0;
		if ( '' !== $saved_eid && $saved_eid === $live_eid ) {
			$score += 50;
		}
		if ( '' !== $saved_label && $saved_label === $live_label ) {
			$score += 10;
		}
		if ( '' !== $saved_ui && $saved_ui === $live_ui ) {
			$score += 5;
		}
		if ( '' !== $saved_b && $saved_b === $live_b ) {
			$score += 3;
		} elseif ( '' === $saved_b && '' !== $live_b ) {
			$score += 1;
		}
		if ( '' === ( isset( $saved['ui_goal_type'] ) ? sanitize_key( (string) $saved['ui_goal_type'] ) : '' ) && $saved_gt === $live_gt ) {
			++$score;
		}
		return $score;
	}

	/**
	 * Rebuild a saved defined-goal row from the current live builder metadata.
	 *
	 * Keeps stable logical/mapping identifiers for reporting while refreshing physical ids, labels,
	 * builders, and goal types so future resync/front-end expansion uses current page truth.
	 *
	 * @param array<string, mixed> $saved Saved experiment goal row.
	 * @param array<string, mixed> $pick  Live discovered builder goal row.
	 * @return array<string, mixed>|null
	 */
	public static function rebuild_saved_defined_goal_from_live( array $saved, array $pick ) {
		if ( empty( $pick['goal_id'] ) || empty( $pick['handler_id'] ) ) {
			return null;
		}
		$def = array(
			'goal_id'             => sanitize_key( (string) $pick['goal_id'] ),
			'handler_id'          => sanitize_key( (string) $pick['handler_id'] ),
			'goal_label'          => isset( $pick['goal_label'] ) ? sanitize_text_field( (string) $pick['goal_label'] ) : '',
			'source_type'         => isset( $pick['source_type'] ) ? sanitize_key( (string) $pick['source_type'] ) : sanitize_key( (string) ( $saved['source_type'] ?? 'defined' ) ),
			'ui_goal_type'        => isset( $pick['ui_goal_type'] ) ? sanitize_key( (string) $pick['ui_goal_type'] ) : sanitize_key( (string) ( $saved['ui_goal_type'] ?? '' ) ),
			'builder'             => isset( $pick['builder'] ) ? sanitize_key( (string) $pick['builder'] ) : sanitize_key( (string) ( $saved['builder'] ?? '' ) ),
			'destination_page_id' => (int) ( $pick['destination_page_id'] ?? 0 ),
			'source_post_id'      => (int) ( $pick['source_post_id'] ?? 0 ),
			'elementor_id'        => isset( $pick['elementor_id'] ) ? sanitize_key( (string) $pick['elementor_id'] ) : sanitize_key( (string) ( $saved['elementor_id'] ?? '' ) ),
		);
		$built = self::build_goals_from_defined_selection( $def );
		if ( empty( $built['goals'][0] ) || ! is_array( $built['goals'][0] ) ) {
			return null;
		}
		$row = $built['goals'][0];
		if ( ! empty( $saved['logical_goal_id'] ) ) {
			$row['logical_goal_id'] = sanitize_key( (string) $saved['logical_goal_id'] );
		}
		if ( ! empty( $saved['mapping_variant'] ) ) {
			$row['mapping_variant'] = sanitize_key( (string) $saved['mapping_variant'] );
		}
		$row['is_primary'] = ! empty( $saved['is_primary'] );

		$note = '';
		if ( isset( $pick['goal_note'] ) ) {
			$note = sanitize_text_field( (string) $pick['goal_note'] );
		} elseif ( isset( $saved['goal_note'] ) ) {
			$note = sanitize_text_field( (string) $saved['goal_note'] );
		}
		if ( '' !== $note ) {
			$row['goal_note'] = $note;
		}
		return $row;
	}

	/**
	 * Refresh saved `goal_id` / `handler_id` from live Elementor/Gutenberg definitions (after widget duplicate, import, etc.).
	 *
	 * Matches by label + ui_goal_type + builder (same strategy as frontend goal expansion). Updates
	 * `defined_goal_mapping.targets` when logical mapping is active.
	 *
	 * @param array<string, mixed> $cfg                 Experiment config.
	 * @param int                  $experiment_post_id Experiment CPT id for binding normalize (0 = skip persist path only).
	 * @return array{ changed: bool, config: array<string, mixed>, goals_updated: int }
	 */
	public static function resync_physical_goal_ids_for_config( array $cfg, $experiment_post_id = 0 ) {
		$experiment_post_id = (int) $experiment_post_id;
		if ( class_exists( 'RWGO_Experiment_Repository', false ) ) {
			$cfg = RWGO_Experiment_Repository::normalize_page_bindings( $cfg, $experiment_post_id, false );
		}
		$out       = $cfg;
		$changed   = false;
		$updated_n = 0;
		if ( empty( $out['goal_selection_mode'] ) || 'defined' !== $out['goal_selection_mode'] ) {
			return array(
				'changed'       => false,
				'config'        => $out,
				'goals_updated' => 0,
			);
		}
		$goals = isset( $out['goals'] ) && is_array( $out['goals'] ) ? $out['goals'] : array();
		if ( empty( $goals ) ) {
			return array(
				'changed'       => false,
				'config'        => $out,
				'goals_updated' => 0,
			);
		}

		$pids = array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['is_defined'] ) ) {
				continue;
			}
			if ( isset( $g['source_type'] ) && 'page_destination' === sanitize_key( (string) $g['source_type'] ) ) {
				continue;
			}
			$pid = self::page_id_for_saved_defined_goal( $out, $g );
			if ( $pid > 0 ) {
				$pids[ $pid ] = true;
			}
		}
		$live_by_page = array();
		foreach ( array_keys( $pids ) as $pid ) {
			$live_by_page[ (int) $pid ] = self::collect_for_post( (int) $pid );
		}

		$used_by_page = array();
		foreach ( array_keys( $pids ) as $pid ) {
			$used_by_page[ (int) $pid ] = array();
		}

		$next_goals = array();
		foreach ( $goals as $i => $g ) {
			if ( ! is_array( $g ) ) {
				continue;
			}
			if ( empty( $g['is_defined'] ) ) {
				$next_goals[] = $g;
				continue;
			}
			if ( isset( $g['source_type'] ) && 'page_destination' === sanitize_key( (string) $g['source_type'] ) ) {
				$next_goals[] = $g;
				continue;
			}
			$pid = self::page_id_for_saved_defined_goal( $out, $g );
			if ( $pid <= 0 || empty( $live_by_page[ $pid ] ) ) {
				$next_goals[] = $g;
				continue;
			}
			$live = $live_by_page[ $pid ];
			$g    = self::enrich_saved_goal_with_live_identity( $g, $live );
			$pick = self::pick_live_row_for_saved_goal( $live, $g, $used_by_page[ $pid ] );
			if ( ! is_array( $pick ) || empty( $pick['goal_id'] ) || empty( $pick['handler_id'] ) ) {
				$changed = true;
				++$updated_n;
				continue;
			}
			$pk = (string) $pick['goal_id'] . '|' . sanitize_key( (string) $pick['handler_id'] );
			$used_by_page[ $pid ][ $pk ] = true;
			$rebuilt = self::rebuild_saved_defined_goal_from_live( $g, $pick );
			if ( ! is_array( $rebuilt ) ) {
				$next_goals[] = $g;
				continue;
			}
			if ( wp_json_encode( $g ) !== wp_json_encode( $rebuilt ) ) {
				$changed = true;
				++$updated_n;
			}
			$next_goals[] = $rebuilt;
		}

		$goals = array_values( $next_goals );
		$out['goals'] = $goals;

		$first_goal_index = -1;
		$has_primary      = false;
		foreach ( $goals as $idx => $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			if ( $first_goal_index < 0 ) {
				$first_goal_index = (int) $idx;
			}
			if ( ! empty( $g['is_primary'] ) ) {
				$has_primary = true;
				break;
			}
		}
		if ( ! $has_primary && $first_goal_index >= 0 ) {
			$out['goals'][ $first_goal_index ]['is_primary'] = true;
			$goals[ $first_goal_index ]['is_primary']        = true;
			$changed                                         = true;
		}

		if ( $changed && class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $out ) ) {
			$targets = array(
				'control' => array(),
				'var_b'   => array(),
			);
			foreach ( $goals as $g ) {
				if ( ! is_array( $g ) || empty( $g['mapping_variant'] ) || empty( $g['goal_id'] ) ) {
					continue;
				}
				$vk = sanitize_key( (string) $g['mapping_variant'] );
				if ( ! in_array( $vk, array( 'control', 'var_b' ), true ) ) {
					continue;
				}
				$h0 = isset( $g['handlers'][0] ) && is_array( $g['handlers'][0] ) ? $g['handlers'][0] : array();
				$hid = isset( $h0['handler_id'] ) ? sanitize_key( (string) $h0['handler_id'] ) : '';
				if ( '' === $hid ) {
					continue;
				}
				$targets[ $vk ][] = array(
					'goal_id'    => sanitize_key( (string) $g['goal_id'] ),
					'handler_id' => $hid,
				);
			}
			if ( ! isset( $out['defined_goal_mapping'] ) || ! is_array( $out['defined_goal_mapping'] ) ) {
				$out['defined_goal_mapping'] = array();
			}
			$out['defined_goal_mapping']['targets'] = $targets;
		}

		if ( $changed && ( ! class_exists( 'RWGO_Goal_Mapping', false ) || ! RWGO_Goal_Mapping::is_active( $out ) ) ) {
			$out['primary_goal_id'] = '';
			foreach ( $goals as $g ) {
				if ( is_array( $g ) && ! empty( $g['is_primary'] ) && ! empty( $g['goal_id'] ) ) {
					$out['primary_goal_id'] = sanitize_key( (string) $g['goal_id'] );
					break;
				}
			}
			if ( '' === (string) $out['primary_goal_id'] ) {
				foreach ( $goals as $g ) {
					if ( is_array( $g ) && ! empty( $g['goal_id'] ) ) {
						$out['primary_goal_id'] = sanitize_key( (string) $g['goal_id'] );
						break;
					}
				}
			}
		}

		return array(
			'changed'       => $changed,
			'config'        => $out,
			'goals_updated' => $updated_n,
		);
	}
}
