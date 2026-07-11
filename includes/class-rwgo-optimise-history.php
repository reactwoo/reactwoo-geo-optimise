<?php
/**
 * Unified Optimise activity timeline (experiments, AI analyses, promotions).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merges Optimise + embedded Geo AI audit rows for the History hub tab.
 */
class RWGO_Optimise_History {

	/**
	 * @param int $limit Max merged rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_timeline( $limit = 25 ) {
		$limit   = max( 1, min( 50, (int) $limit ) );
		$entries = array_merge(
			self::entries_from_analysis_runs( $limit ),
			self::entries_from_promotions( $limit ),
			self::entries_from_experiments( $limit )
		);

		usort(
			$entries,
			static function ( $a, $b ) {
				return (int) ( $b['ts'] ?? 0 ) <=> (int) ( $a['ts'] ?? 0 );
			}
		);

		return array_slice( $entries, 0, $limit );
	}

	/**
	 * @param int $limit Source fetch cap per type.
	 * @return array<int, array<string, mixed>>
	 */
	private static function entries_from_analysis_runs( $limit ) {
		if ( ! class_exists( 'RWGA_DB_Analysis_Runs', false ) ) {
			return array();
		}

		$rows    = RWGA_DB_Analysis_Runs::list_recent( $limit );
		$entries = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}

			$workflow = sanitize_key( (string) ( $row['workflow_key'] ?? '' ) );
			$summary  = isset( $row['summary'] ) ? wp_strip_all_tags( (string) $row['summary'] ) : '';
			$title    = self::workflow_label( $workflow );
			if ( '' !== $summary ) {
				$title .= ' — ' . self::truncate( $summary, 72 );
			}

			$entries[] = array(
				'type'        => 'ai_analysis',
				'ts'          => self::mysql_gmt_to_ts( (string) ( $row['created_at'] ?? '' ) ),
				'title'       => $title,
				'detail'      => self::analysis_detail_line( $row ),
				'url'         => RWGO_Optimise_Hub::tab_url( 'history', array( 'run_id' => $id ) ),
				'badge'       => __( 'AI analysis', 'reactwoo-geo-optimise' ),
				'badge_class' => 'rwgo-history-timeline__badge--ai',
			);
		}

		return $entries;
	}

	/**
	 * @param int $limit Source fetch cap per type.
	 * @return array<int, array<string, mixed>>
	 */
	private static function entries_from_promotions( $limit ) {
		if ( ! class_exists( 'RWGO_Promotion_Log', false ) ) {
			return array();
		}

		$rows    = RWGO_Promotion_Log::list_recent( $limit );
		$entries = array();

		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$experiment_id = (int) ( $row->experiment_post_id ?? 0 );
			$title         = __( 'Winner promoted', 'reactwoo-geo-optimise' );
			if ( $experiment_id > 0 ) {
				$exp_title = get_the_title( $experiment_id );
				if ( is_string( $exp_title ) && '' !== $exp_title ) {
					$title = sprintf(
						/* translators: %s: experiment title */
						__( 'Winner promoted — %s', 'reactwoo-geo-optimise' ),
						$exp_title
					);
				}
			}

			$url = $experiment_id > 0 && class_exists( 'RWGO_Admin', false )
				? RWGO_Admin::edit_test_url( $experiment_id, 'tests' )
				: RWGO_Optimise_Hub::tab_url( 'experiments' );

			$entries[] = array(
				'type'        => 'promotion',
				'ts'          => self::mysql_gmt_to_ts( (string) ( $row->created_at_gmt ?? '' ) ),
				'title'       => $title,
				'detail'      => self::promotion_detail_line( $row ),
				'url'         => $url,
				'badge'       => __( 'Promotion', 'reactwoo-geo-optimise' ),
				'badge_class' => 'rwgo-history-timeline__badge--promotion',
			);
		}

		return $entries;
	}

	/**
	 * @param int $limit Source fetch cap per type.
	 * @return array<int, array<string, mixed>>
	 */
	private static function entries_from_experiments( $limit ) {
		if ( ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
			return array();
		}

		$posts   = RWGO_Experiment_Repository::query_experiments(
			array(
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$entries = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$cfg    = RWGO_Experiment_Repository::get_config( $post->ID );
			$status = sanitize_key( (string) ( $cfg['status'] ?? 'draft' ) );
			$title  = sprintf(
				/* translators: 1: experiment title, 2: status label */
				__( 'Experiment %1$s — %2$s', 'reactwoo-geo-optimise' ),
				get_the_title( $post ),
				self::experiment_status_label( $status )
			);

			$entries[] = array(
				'type'        => 'experiment',
				'ts'          => (int) get_post_modified_time( 'U', true, $post ),
				'title'       => $title,
				'detail'      => self::experiment_detail_line( $cfg ),
				'url'         => class_exists( 'RWGO_Admin', false )
					? RWGO_Admin::edit_test_url( $post->ID, 'tests' )
					: RWGO_Optimise_Hub::tab_url( 'experiments' ),
				'badge'       => __( 'Experiment', 'reactwoo-geo-optimise' ),
				'badge_class' => 'rwgo-history-timeline__badge--experiment',
			);
		}

		return $entries;
	}

	/**
	 * @param string $workflow_key Workflow key.
	 * @return string
	 */
	private static function workflow_label( $workflow_key ) {
		$labels = array(
			'ux_analysis'                 => __( 'UX analysis', 'reactwoo-geo-optimise' ),
			'ux_opportunity_review'       => __( 'UX opportunity review', 'reactwoo-geo-optimise' ),
			'optimise_review'             => __( 'Optimise review', 'reactwoo-geo-optimise' ),
			'ux_recommend'                => __( 'UX recommendations', 'reactwoo-geo-optimise' ),
			'optimisation_recommendation' => __( 'Test plan recommendation', 'reactwoo-geo-optimise' ),
			'site_audit'                  => __( 'Site audit', 'reactwoo-geo-optimise' ),
		);
		$key = sanitize_key( (string) $workflow_key );
		return $labels[ $key ] ?? ( '' !== $key ? ucwords( str_replace( '_', ' ', $key ) ) : __( 'AI run', 'reactwoo-geo-optimise' ) );
	}

	/**
	 * @param string $status Experiment status.
	 * @return string
	 */
	private static function experiment_status_label( $status ) {
		$labels = array(
			'draft'    => __( 'draft', 'reactwoo-geo-optimise' ),
			'active'   => __( 'active', 'reactwoo-geo-optimise' ),
			'paused'   => __( 'paused', 'reactwoo-geo-optimise' ),
			'complete' => __( 'complete', 'reactwoo-geo-optimise' ),
			'archived' => __( 'archived', 'reactwoo-geo-optimise' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * @param array<string, mixed> $row Analysis row.
	 * @return string
	 */
	private static function analysis_detail_line( array $row ) {
		$parts = array();
		if ( ! empty( $row['page_url'] ) ) {
			$parts[] = (string) $row['page_url'];
		}
		if ( ! empty( $row['lifecycle_status'] ) ) {
			$parts[] = sanitize_key( (string) $row['lifecycle_status'] );
		}
		if ( isset( $row['score'] ) && '' !== $row['score'] && null !== $row['score'] ) {
			$parts[] = sprintf(
				/* translators: %s: numeric score */
				__( 'Score %s', 'reactwoo-geo-optimise' ),
				(string) $row['score']
			);
		}
		return implode( ' · ', array_filter( $parts ) );
	}

	/**
	 * @param object $row Promotion row.
	 * @return string
	 */
	private static function promotion_detail_line( $row ) {
		$parts = array();
		if ( ! empty( $row->mode ) ) {
			$parts[] = sanitize_key( (string) $row->mode );
		}
		if ( ! empty( $row->variant_url_snapshot ) ) {
			$parts[] = (string) $row->variant_url_snapshot;
		} elseif ( ! empty( $row->target_url_snapshot ) ) {
			$parts[] = (string) $row->target_url_snapshot;
		}
		return implode( ' · ', array_filter( $parts ) );
	}

	/**
	 * @param array<string, mixed> $cfg Experiment config.
	 * @return string
	 */
	private static function experiment_detail_line( array $cfg ) {
		$parts = array();
		if ( ! empty( $cfg['test_type'] ) ) {
			$parts[] = sanitize_key( (string) $cfg['test_type'] );
		}
		$variants = isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? count( $cfg['variants'] ) : 0;
		if ( $variants > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: variant count */
				_n( '%d variant', '%d variants', $variants, 'reactwoo-geo-optimise' ),
				$variants
			);
		}
		return implode( ' · ', array_filter( $parts ) );
	}

	/**
	 * @param string $mysql_gmt MySQL datetime (GMT).
	 * @return int
	 */
	private static function mysql_gmt_to_ts( $mysql_gmt ) {
		$mysql_gmt = trim( (string) $mysql_gmt );
		if ( '' === $mysql_gmt ) {
			return 0;
		}
		$ts = strtotime( $mysql_gmt . ' UTC' );
		return false === $ts ? 0 : (int) $ts;
	}

	/**
	 * @param string $text Text.
	 * @param int    $max  Max chars.
	 * @return string
	 */
	private static function truncate( $text, $max ) {
		$text = trim( (string) $text );
		$max  = max( 8, (int) $max );
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		return rtrim( substr( $text, 0, $max - 1 ) ) . '…';
	}
}
