<?php
/**
 * Dry-run Elementor mutation planning from builder recommendations.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translates recommendations into proposed Elementor actions (preview only).
 */
class RWGA_Elementor_Action_Planner {

	/**
	 * @param int                              $post_id Post ID.
	 * @param array<int, array<string, mixed>> $recommendations Builder recommendations.
	 * @return array<int, array<string, mixed>>
	 */
	public static function plan( $post_id, array $recommendations ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! RWGA_Elementor_Adapter::post_has_elementor_data( $post_id ) ) {
			return array();
		}

		$adapter = new RWGA_Elementor_Adapter();
		$widgets = $adapter->extract_widgets( $post_id );
		$by_id   = array();
		foreach ( $widgets as $w ) {
			if ( ! empty( $w['id'] ) ) {
				$by_id[ (string) $w['id'] ] = $w;
			}
		}

		$actions = array();
		foreach ( $recommendations as $rec ) {
			if ( ! is_array( $rec ) || 'elementor' !== ( isset( $rec['builder'] ) ? $rec['builder'] : '' ) ) {
				continue;
			}
			$planned = self::plan_one( $rec, $by_id );
			if ( array() !== $planned ) {
				$actions[] = $planned;
			}
		}

		/**
		 * Filter planned Elementor actions (dry-run).
		 *
		 * @param array<int, array<string, mixed>> $actions Planned actions.
		 * @param int                              $post_id Post ID.
		 */
		$actions = apply_filters( 'rwga_elementor_action_plan', $actions, $post_id );
		return is_array( $actions ) ? $actions : array();
	}

	/**
	 * @param array<string, mixed>             $rec     Recommendation.
	 * @param array<string, array<string, mixed>> $by_id Widgets by id.
	 * @return array<string, mixed>
	 */
	private static function plan_one( array $rec, array $by_id ) {
		$type   = isset( $rec['recommendation_type'] ) ? sanitize_key( (string) $rec['recommendation_type'] ) : '';
		$target = isset( $rec['target'] ) && is_array( $rec['target'] ) ? $rec['target'] : array();
		$wid    = isset( $target['widget_id'] ) ? (string) $target['widget_id'] : '';

		switch ( $type ) {
			case 'update_cta':
			case 'update_text':
				if ( '' === $wid || ! isset( $by_id[ $wid ] ) ) {
					return array();
				}
				$w = $by_id[ $wid ];
				$key = 'button' === $w['type'] ? 'text' : 'title';
				return array(
					'action'                     => 'update_widget_setting',
					'widget_id'                  => $wid,
					'setting_key'                => $key,
					'old_value'                  => isset( $w['content'] ) ? (string) $w['content'] : '',
					'new_value'                  => isset( $rec['suggested_change'] ) ? (string) $rec['suggested_change'] : '',
					'requires_backup'            => true,
					'requires_user_confirmation' => true,
					'dry_run'                    => true,
				);
			case 'add_widget':
				return array(
					'action'                     => 'add_widget',
					'section_id'                 => isset( $target['section_id'] ) ? (string) $target['section_id'] : '',
					'widget_type'                => isset( $target['widget_type'] ) ? (string) $target['widget_type'] : 'button',
					'requires_backup'            => true,
					'requires_user_confirmation' => true,
					'dry_run'                    => true,
				);
			case 'add_trust_signal':
			case 'add_section':
				return array(
					'action'                     => 'add_section',
					'section_type'               => 'trust',
					'after_section_id'           => isset( $target['section_id'] ) ? (string) $target['section_id'] : '',
					'requires_backup'            => true,
					'requires_user_confirmation' => true,
					'dry_run'                    => true,
				);
			case 'move_widget':
				return array(
					'action'                     => 'reorder_widget',
					'widget_id'                  => $wid,
					'target_section_id'          => isset( $target['section_id'] ) ? (string) $target['section_id'] : '',
					'requires_backup'            => true,
					'requires_user_confirmation' => true,
					'dry_run'                    => true,
				);
			default:
				return array(
					'action'                     => 'review_manual',
					'recommendation_type'        => $type,
					'requires_backup'            => false,
					'requires_user_confirmation' => true,
					'dry_run'                    => true,
				);
		}
	}
}
