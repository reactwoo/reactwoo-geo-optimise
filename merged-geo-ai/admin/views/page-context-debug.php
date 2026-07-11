<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current    = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-page-context-debug';
$post_id             = isset( $post_id ) ? (int) $post_id : 0;
$pages               = isset( $pages ) && is_array( $pages ) ? $pages : array();
$detected_builder    = isset( $detected_builder ) ? (string) $detected_builder : '';
$builder_context     = isset( $builder_context ) && is_array( $builder_context ) ? $builder_context : array();
$ai_payload          = isset( $ai_payload ) && is_array( $ai_payload ) ? $ai_payload : array();
$api_bundle          = isset( $api_bundle ) && is_array( $api_bundle ) ? $api_bundle : array();
$recommendations     = isset( $recommendations ) && is_array( $recommendations ) ? $recommendations : array();
$elementor_actions   = isset( $elementor_actions ) && is_array( $elementor_actions ) ? $elementor_actions : array();

$json_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Page context inspector', 'reactwoo-geo-ai' ),
			__( 'See how Geo AI parses Elementor and Gutenberg pages before sending context to workflows.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Page context inspector', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card" style="max-width: 720px; margin-top: 16px;">
		<form method="get" action="">
			<input type="hidden" name="page" value="rwga-page-context-debug" />
			<p>
				<label for="rwga_debug_post_id"><strong><?php esc_html_e( 'Page / post', 'reactwoo-geo-ai' ); ?></strong></label><br />
				<select name="post_id" id="rwga_debug_post_id" style="min-width: 320px;">
					<option value="0"><?php esc_html_e( '— Select —', 'reactwoo-geo-ai' ); ?></option>
					<?php foreach ( $pages as $p ) : ?>
						<?php if ( ! $p instanceof WP_Post ) { continue; } ?>
						<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $post_id, (int) $p->ID ); ?>>
							<?php echo esc_html( sprintf( '#%d — %s (%s)', (int) $p->ID, $p->post_title, $p->post_type ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Inspect', 'reactwoo-geo-ai' ); ?></button>
			</p>
		</form>
	</div>

	<?php if ( $post_id > 0 ) : ?>
		<div class="rwgc-card" style="margin-top: 16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Summary', 'reactwoo-geo-ai' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Detected builder', 'reactwoo-geo-ai' ); ?>:</strong> <code><?php echo esc_html( $detected_builder ); ?></code></li>
				<li><strong><?php esc_html_e( 'Sections', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) count( $builder_context['sections'] ?? array() ) ); ?></li>
				<li><strong><?php esc_html_e( 'Widgets', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) count( $builder_context['widgets'] ?? array() ) ); ?></li>
				<li><strong><?php esc_html_e( 'CTAs', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) count( $builder_context['ctas'] ?? array() ) ); ?></li>
				<li><strong><?php esc_html_e( 'Forms', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) count( $builder_context['forms'] ?? array() ) ); ?></li>
				<?php if ( ! empty( $ai_payload['ux_scores']['overall_score'] ) ) : ?>
					<li><strong><?php esc_html_e( 'UX overall score', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) (int) $ai_payload['ux_scores']['overall_score'] ); ?></li>
				<?php endif; ?>
			</ul>
		</div>

		<?php
		$panels = array(
			__( 'Parsed sections (with classification)', 'reactwoo-geo-ai' ) => $builder_context['sections'] ?? array(),
			__( 'Parsed widgets', 'reactwoo-geo-ai' ) => array_slice( $builder_context['widgets'] ?? array(), 0, 40 ),
			__( 'CTAs', 'reactwoo-geo-ai' ) => $builder_context['ctas'] ?? array(),
			__( 'Forms', 'reactwoo-geo-ai' ) => $builder_context['forms'] ?? array(),
			__( 'UX scores', 'reactwoo-geo-ai' ) => $ai_payload['ux_scores'] ?? array(),
			__( 'Detected issues', 'reactwoo-geo-ai' ) => $ai_payload['detected_issues'] ?? array(),
			__( 'Builder recommendations', 'reactwoo-geo-ai' ) => $recommendations,
			__( 'Elementor action plan (dry-run)', 'reactwoo-geo-ai' ) => $elementor_actions,
			__( 'AI payload preview (trimmed)', 'reactwoo-geo-ai' ) => $api_bundle,
		);
		foreach ( $panels as $title => $data ) :
			?>
			<div class="rwgc-card" style="margin-top: 16px;">
				<h2 style="margin-top:0;"><?php echo esc_html( $title ); ?></h2>
				<pre style="max-height: 360px; overflow: auto; background: #f6f7f7; padding: 12px; border: 1px solid #c3c4c7;"><?php echo esc_html( wp_json_encode( $data, $json_flags ) ); ?></pre>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
