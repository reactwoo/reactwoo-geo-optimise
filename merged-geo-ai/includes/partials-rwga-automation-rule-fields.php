<?php
/**
 * Shared fields for automation rule forms (loaded before admin views).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rwga_render_automation_rule_fields' ) ) {
	/**
	 * @param array<string, mixed> $r               Row or defaults.
	 * @param string               $notes           Notes for rule_config.
	 * @param array<int, string>   $wkeys           Workflow keys.
	 * @param string               $page_url        Optional page URL for ux_analysis when page ID is empty.
	 * @param string               $competitor_url  Optional competitor URL for competitor_research.
	 * @param string               $analysis_focus  inherit|messaging|layout|both for ux_analysis automation.
	 * @return void
	 */
	function rwga_render_automation_rule_fields( $r, $notes, $wkeys, $page_url = '', $competitor_url = '', $analysis_focus = 'inherit' ) {
		$wkeys = is_array( $wkeys ) ? $wkeys : array();
		$name  = isset( $r['name'] ) ? (string) $r['name'] : '';
		$wk    = isset( $r['workflow_key'] ) ? (string) $r['workflow_key'] : '';
		$tt    = isset( $r['trigger_type'] ) ? (string) $r['trigger_type'] : 'manual';
		$ts    = isset( $r['target_scope'] ) ? (string) $r['target_scope'] : 'site';
		$pid   = isset( $r['page_id'] ) ? (int) $r['page_id'] : 0;
		$geo   = isset( $r['geo_target'] ) && $r['geo_target'] ? (string) $r['geo_target'] : '';
		$st    = isset( $r['status'] ) ? (string) $r['status'] : 'active';
		?>
		<p>
			<label for="rwga_ar_name"><?php esc_html_e( 'Name', 'reactwoo-geo-ai' ); ?></label><br />
			<input type="text" class="regular-text" name="name" id="rwga_ar_name" required value="<?php echo esc_attr( $name ); ?>" />
		</p>
		<p>
			<label for="rwga_ar_wk"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></label><br />
			<select name="workflow_key" id="rwga_ar_wk" required>
				<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-ai' ); ?></option>
				<?php foreach ( $wkeys as $key ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $wk, $key ); ?>><?php echo esc_html( $key ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="rwga_ar_tt"><?php esc_html_e( 'Trigger', 'reactwoo-geo-ai' ); ?></label><br />
			<select name="trigger_type" id="rwga_ar_tt">
				<option value="manual" <?php selected( $tt, 'manual' ); ?>><?php esc_html_e( 'Manual', 'reactwoo-geo-ai' ); ?></option>
				<option value="schedule" <?php selected( $tt, 'schedule' ); ?>><?php esc_html_e( 'Schedule (WP-Cron)', 'reactwoo-geo-ai' ); ?></option>
			</select>
		</p>
		<p>
			<label for="rwga_ar_ts"><?php esc_html_e( 'Target scope', 'reactwoo-geo-ai' ); ?></label><br />
			<select name="target_scope" id="rwga_ar_ts">
				<option value="site" <?php selected( $ts, 'site' ); ?>><?php esc_html_e( 'Site', 'reactwoo-geo-ai' ); ?></option>
				<option value="page" <?php selected( $ts, 'page' ); ?>><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></option>
			</select>
		</p>
		<p>
			<label for="rwga_ar_pid"><?php esc_html_e( 'Page (if scope is page)', 'reactwoo-geo-ai' ); ?></label><br />
			<?php
			wp_dropdown_pages(
				array(
					'name'              => 'page_id',
					'id'                => 'rwga_ar_pid',
					'selected'          => $pid > 0 ? $pid : 0,
					'show_option_none'  => __( '— Select page —', 'reactwoo-geo-ai' ),
					'option_none_value' => '0',
					'class'             => 'rwgc-select rwgc-input',
				)
			);
			?>
		</p>
		<p>
			<label for="rwga_ar_geo"><?php esc_html_e( 'Target country (optional)', 'reactwoo-geo-ai' ); ?></label><br />
			<?php
			if ( class_exists( 'RWGC_Admin', false ) ) {
				RWGC_Admin::render_country_select(
					'geo_target',
					$geo,
					array(
						'id'                => 'rwga_ar_geo',
						'class'             => 'rwgc-select rwgc-input regular-text',
						'show_option_none'  => __( '— Any —', 'reactwoo-geo-ai' ),
						'option_none_value' => '',
					)
				);
			} else {
				echo '<input type="text" maxlength="2" class="small-text" name="geo_target" id="rwga_ar_geo" value="' . esc_attr( $geo ) . '" />';
			}
			?>
		</p>
		<p>
			<label for="rwga_ar_st"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></label><br />
			<select name="status" id="rwga_ar_st">
				<option value="active" <?php selected( $st, 'active' ); ?>><?php esc_html_e( 'Active', 'reactwoo-geo-ai' ); ?></option>
				<option value="paused" <?php selected( $st, 'paused' ); ?>><?php esc_html_e( 'Paused', 'reactwoo-geo-ai' ); ?></option>
			</select>
		</p>
		<p>
			<label for="rwga_ar_purl"><?php esc_html_e( 'Automation page URL (optional)', 'reactwoo-geo-ai' ); ?></label><br />
			<input type="url" class="regular-text" name="rwga_auto_page_url" id="rwga_ar_purl" value="<?php echo esc_attr( $page_url ); ?>" placeholder="https://…" />
			<span class="description"><?php esc_html_e( 'For UX analysis when no page ID is set (site scope).', 'reactwoo-geo-ai' ); ?></span>
		</p>
		<p>
			<label for="rwga_ar_af"><?php esc_html_e( 'UX analysis focus (for UX workflow)', 'reactwoo-geo-ai' ); ?></label><br />
			<select name="rwga_auto_analysis_focus" id="rwga_ar_af">
				<option value="inherit" <?php selected( $analysis_focus, 'inherit' ); ?>><?php esc_html_e( 'Site default (Advanced)', 'reactwoo-geo-ai' ); ?></option>
				<option value="messaging" <?php selected( $analysis_focus, 'messaging' ); ?>><?php esc_html_e( 'Messaging', 'reactwoo-geo-ai' ); ?></option>
				<option value="layout" <?php selected( $analysis_focus, 'layout' ); ?>><?php esc_html_e( 'Layout', 'reactwoo-geo-ai' ); ?></option>
				<option value="both" <?php selected( $analysis_focus, 'both' ); ?>><?php esc_html_e( 'Messaging + layout', 'reactwoo-geo-ai' ); ?></option>
			</select>
			<span class="description"><?php esc_html_e( 'Messaging-only scans typically use fewer API tokens; layout and combined scans ask for more detail (see Advanced).', 'reactwoo-geo-ai' ); ?></span>
		</p>
		<p>
			<label for="rwga_ar_curl"><?php esc_html_e( 'Competitor URL (optional)', 'reactwoo-geo-ai' ); ?></label><br />
			<input type="url" class="regular-text" name="rwga_auto_competitor_url" id="rwga_ar_curl" value="<?php echo esc_attr( $competitor_url ); ?>" placeholder="https://…" />
			<span class="description"><?php esc_html_e( 'Required in rule options for scheduled competitor research.', 'reactwoo-geo-ai' ); ?></span>
		</p>
		<p>
			<label for="rwga_ar_notes"><?php esc_html_e( 'Notes (stored in rule_config)', 'reactwoo-geo-ai' ); ?></label><br />
			<textarea name="rule_notes" id="rwga_ar_notes" class="large-text" rows="3"><?php echo esc_textarea( $notes ); ?></textarea>
		</p>
		<?php
	}
}
