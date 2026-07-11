<?php
/**
 * Draft / queue rows for Geo AI (UX shell; extend when backend persists drafts).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Placeholder queue API for the Drafts / Queue admin screen.
 */
class RWGA_Drafts {

	/**
	 * Table rows for the queue list (stub until drafts are stored in WordPress).
	 *
	 * Each row: source_label, context_label, type_label, status_label, created_gmt, actions_html (optional).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function get_queue_rows() {
		$rows = array();

		/**
		 * Filter draft queue rows for Geo AI → Drafts / Queue.
		 *
		 * @param array<int, array<string, string>> $rows Rows.
		 */
		return apply_filters( 'rwga_draft_queue_rows', $rows );
	}
}
