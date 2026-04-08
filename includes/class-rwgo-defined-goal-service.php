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
		return $warnings;
	}

	/**
	 * Match key for aligning saved experiment goals with {@see collect_for_post()} rows (label + UI type + builder).
	 *
	 * @param array<string, mixed> $row Discovered or saved goal-shaped array.
	 * @param bool                 $saved_row When true, read `label`; when false, read `goal_label`.
	 * @return string Empty if not comparable (e.g. page_destination).
	 */
	private static function goal_physical_match_key( array $row, $saved_row = false ) {
		$builder = isset( $row['builder'] ) ? sanitize_key( (string) $row['builder'] ) : '';
		$st      = isset( $row['source_type'] ) ? sanitize_key( (string) $row['source_type'] ) : '';
		$prefix  = '';
		if ( 'elementor' === $builder && ( 'elementor_widget' === $st || ( $saved_row && ( '' === $st || 'defined' === $st ) ) ) ) {
			$prefix = 'el:';
		} elseif ( 'gutenberg' === $builder && ( 'gutenberg_block' === $st || ( $saved_row && ( '' === $st || 'defined' === $st ) ) ) ) {
			$prefix = 'gb:';
		} else {
			return '';
		}
		$label = $saved_row
			? ( isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '' )
			: ( isset( $row['goal_label'] ) ? sanitize_text_field( (string) $row['goal_label'] ) : '' );
		$ui    = isset( $row['ui_goal_type'] ) ? sanitize_key( (string) $row['ui_goal_type'] ) : '';
		return $prefix . $label . "\x1e" . $ui;
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
		$want = self::goal_physical_match_key( $saved, true );
		if ( '' === $want ) {
			return null;
		}
		foreach ( $live as $row ) {
			if ( ! is_array( $row ) || empty( $row['goal_id'] ) ) {
				continue;
			}
			if ( self::goal_physical_match_key( $row, false ) !== $want ) {
				continue;
			}
			$pk = (string) $row['goal_id'] . '|' . sanitize_key( (string) ( $row['handler_id'] ?? '' ) );
			if ( isset( $used_keys[ $pk ] ) ) {
				continue;
			}
			return $row;
		}
		return null;
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

		foreach ( $goals as $i => $g ) {
			if ( ! is_array( $g ) || empty( $g['is_defined'] ) ) {
				continue;
			}
			if ( isset( $g['source_type'] ) && 'page_destination' === sanitize_key( (string) $g['source_type'] ) ) {
				continue;
			}
			$pid = self::page_id_for_saved_defined_goal( $out, $g );
			if ( $pid <= 0 || empty( $live_by_page[ $pid ] ) ) {
				continue;
			}
			$live = $live_by_page[ $pid ];
			$pick = self::pick_live_row_for_saved_goal( $live, $g, $used_by_page[ $pid ] );
			if ( ! is_array( $pick ) || empty( $pick['goal_id'] ) || empty( $pick['handler_id'] ) ) {
				continue;
			}
			$pk = (string) $pick['goal_id'] . '|' . sanitize_key( (string) $pick['handler_id'] );
			$used_by_page[ $pid ][ $pk ] = true;

			$old_gid = isset( $g['goal_id'] ) ? sanitize_key( (string) $g['goal_id'] ) : '';
			$h0      = isset( $g['handlers'][0] ) && is_array( $g['handlers'][0] ) ? $g['handlers'][0] : array();
			$old_hid = isset( $h0['handler_id'] ) ? sanitize_key( (string) $h0['handler_id'] ) : '';
			$new_gid = sanitize_key( (string) $pick['goal_id'] );
			$new_hid = sanitize_key( (string) $pick['handler_id'] );
			if ( $old_gid === $new_gid && $old_hid === $new_hid ) {
				continue;
			}
			$goals[ $i ]['goal_id'] = $new_gid;
			if ( ! isset( $goals[ $i ]['handlers'] ) || ! is_array( $goals[ $i ]['handlers'] ) ) {
				$goals[ $i ]['handlers'] = array();
			}
			if ( ! isset( $goals[ $i ]['handlers'][0] ) || ! is_array( $goals[ $i ]['handlers'][0] ) ) {
				$goals[ $i ]['handlers'][0] = array();
			}
			$goals[ $i ]['handlers'][0]['handler_id'] = $new_hid;
			$changed                                   = true;
			++$updated_n;
		}

		$out['goals'] = $goals;

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
			foreach ( $goals as $g ) {
				if ( is_array( $g ) && ! empty( $g['is_primary'] ) && ! empty( $g['goal_id'] ) ) {
					$out['primary_goal_id'] = sanitize_key( (string) $g['goal_id'] );
					break;
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
