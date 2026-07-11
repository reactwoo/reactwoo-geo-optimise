<?php
/**
 * Journey redirect URL helper.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Journey_Router {
	/**
	 * @param int $run_id Analysis run id.
	 * @return string
	 */
	public static function analysis_detail_url( $run_id ) {
		return admin_url( 'admin.php?page=rwga-analyses&run_id=' . (int) $run_id . '&journey=1' );
	}

	/**
	 * @param int $analysis_run_id Analysis run id.
	 * @return string
	 */
	public static function recommendation_report_url( $analysis_run_id ) {
		return admin_url( 'admin.php?page=rwga-recommendations&analysis_run=' . (int) $analysis_run_id . '&view=report&journey=1' );
	}

	/**
	 * @param array<int, int> $draft_ids Draft ids.
	 * @param int             $analysis_run_id Analysis run id.
	 * @return string
	 */
	public static function implementation_review_url( array $draft_ids = array(), $analysis_run_id = 0 ) {
		$url = admin_url( 'admin.php?page=rwga-implementation-drafts&view=review&journey=1' );
		if ( ! empty( $draft_ids ) ) {
			$url = add_query_arg( 'draft_ids', implode( ',', array_map( 'intval', $draft_ids ) ), $url );
		}
		if ( (int) $analysis_run_id > 0 ) {
			$url = add_query_arg( 'analysis_run', (int) $analysis_run_id, $url );
		}
		return $url;
	}

	/**
	 * @return string
	 */
	public static function start_url() {
		return admin_url( 'admin.php?page=rwga-dashboard' );
	}

	/**
	 * @return string
	 */
	public static function reports_url() {
		return admin_url( 'admin.php?page=rwga-analyses' );
	}
}

