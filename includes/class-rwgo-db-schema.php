<?php
/**
 * Custom tables for Geo Optimise (redirects, promotion audit).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs / upgrades DB tables (dbDelta).
 */
class RWGO_DB_Schema {

	const VERSION_OPTION = 'rwgo_db_schema_version';
	const VERSION        = '1.1.0';

	/**
	 * @return void
	 */
	public static function activate() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$redirects = $wpdb->prefix . 'rwgo_redirects';
		$sql_r     = "CREATE TABLE {$redirects} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_path varchar(500) NOT NULL,
			target_url text NOT NULL,
			source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			experiment_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			promotion_event_id bigint(20) unsigned NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at_gmt datetime NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			meta_json longtext NULL,
			PRIMARY KEY (id),
			UNIQUE KEY source_path (source_path(191)),
			KEY target_post (target_post_id),
			KEY experiment (experiment_post_id),
			KEY active_path (active, source_path(191))
		) {$charset};";
		dbDelta( $sql_r );

		$promos = $wpdb->prefix . 'rwgo_promotions';
		$sql_p  = "CREATE TABLE {$promos} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			experiment_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			variant_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mode varchar(32) NOT NULL DEFAULT 'replace_content',
			variant_action varchar(48) NOT NULL DEFAULT '',
			redirect_id bigint(20) unsigned NOT NULL DEFAULT 0,
			acting_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at_gmt datetime NOT NULL,
			source_url_snapshot text NULL,
			target_url_snapshot text NULL,
			variant_url_snapshot text NULL,
			meta_json longtext NULL,
			PRIMARY KEY (id),
			KEY experiment (experiment_post_id),
			KEY variant (variant_post_id)
		) {$charset};";
		dbDelta( $sql_p );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}
}
