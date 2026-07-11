<?php
/**
 * Single implementation draft detail.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_draft       = isset( $rwga_draft ) && is_array( $rwga_draft ) ? $rwga_draft : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-implementation-drafts';

$draft_id = isset( $rwga_draft['id'] ) ? (int) $rwga_draft['id'] : 0;
$list_url = admin_url( 'admin.php?page=rwga-implementation-drafts' );

$payload_raw  = isset( $rwga_draft['draft_payload'] ) ? (string) $rwga_draft['draft_payload'] : '';
$payload_dec  = json_decode( $payload_raw, true );
$payload_text = is_array( $payload_dec )
	? wp_json_encode( $payload_dec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
	: $payload_raw;

$ctx_raw = isset( $rwga_draft['input_context'] ) ? (string) $rwga_draft['input_context'] : '';

$draft_title = isset( $rwga_draft['title'] ) ? (string) $rwga_draft['title'] : '';
$rec_id      = isset( $rwga_draft['recommendation_id'] ) ? (int) $rwga_draft['recommendation_id'] : 0;
$rec_label   = '';
if ( $rec_id > 0 && class_exists( 'RWGA_DB_Recommendations', false ) ) {
	$rec_row = RWGA_DB_Recommendations::get( $rec_id );
	if ( is_array( $rec_row ) && ! empty( $rec_row['title'] ) ) {
		$rec_label = sprintf(
			/* translators: 1: recommendation id, 2: title */
			__( '#%1$d — %2$s', 'reactwoo-geo-ai' ),
			$rec_id,
			(string) $rec_row['title']
		);
	}
}
if ( '' === $rec_label && $rec_id > 0 ) {
	$rec_label = sprintf(
		/* translators: %d: recommendation id */
		__( 'Recommendation #%d', 'reactwoo-geo-ai' ),
		$rec_id
	);
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--implementation-draft-detail">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			'' !== $draft_title ? $draft_title : sprintf(
				/* translators: %d: draft id */
				__( 'Draft #%d', 'reactwoo-geo-ai' ),
				$draft_id
			),
			__( 'Review generated content before using it in the editor. Raw JSON is available for developers.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php echo esc_html( sprintf( __( 'Implementation draft #%d', 'reactwoo-geo-ai' ), $draft_id ) ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-actions rwgc-actions--stack-mobile" style="margin-bottom: 16px;">
		<a href="<?php echo esc_url( $list_url ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( '← All drafts', 'reactwoo-geo-ai' ); ?></a>
		<?php
		$pid = isset( $rwga_draft['page_id'] ) ? (int) $rwga_draft['page_id'] : 0;
		if ( $pid > 0 && current_user_can( 'edit_post', $pid ) ) {
			$edit = get_edit_post_link( $pid, 'raw' );
			if ( is_string( $edit ) && '' !== $edit ) {
				echo '<a class="rwgc-btn rwgc-btn--primary" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit page', 'reactwoo-geo-ai' ) . '</a>';
			}
		}
		if ( $rec_id > 0 ) {
			echo '<a class="rwgc-btn rwgc-btn--secondary" href="' . esc_url( add_query_arg( array( 'page' => 'rwga-recommendations', 'rec_id' => $rec_id ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'View recommendation', 'reactwoo-geo-ai' ) . '</a>';
		}
		?>
	</div>

	<div class="rwgc-card rwga-analysis-meta">
		<h2><?php esc_html_e( 'Draft report', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( ! empty( $rwga_draft['report_html'] ) ) : ?>
			<div class="rwga-report-html"><?php echo wp_kses_post( (string) $rwga_draft['report_html'] ); ?></div>
		<?php endif; ?>
		<h3><?php esc_html_e( 'Details', 'reactwoo-geo-ai' ); ?></h3>
		<dl class="rwga-license-dl">
			<dt><?php esc_html_e( 'Type', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_draft['draft_type'] ) ? esc_html( (string) $rwga_draft['draft_type'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></dt>
			<dd><code><?php echo isset( $rwga_draft['workflow_key'] ) ? esc_html( (string) $rwga_draft['workflow_key'] ) : '—'; ?></code></dd>
			<dt><?php esc_html_e( 'Target country', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_draft['geo_target'] ) && $rwga_draft['geo_target'] ? esc_html( (string) $rwga_draft['geo_target'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_draft['status'] ) ? esc_html( (string) $rwga_draft['status'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Route', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo ! empty( $rwga_draft['implementation_route'] ) ? esc_html( (string) $rwga_draft['implementation_route'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Created (UTC)', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_draft['created_at'] ) ? esc_html( (string) $rwga_draft['created_at'] ) : '—'; ?></dd>
			<?php if ( $rec_id > 0 ) : ?>
			<dt><?php esc_html_e( 'Recommendation', 'reactwoo-geo-ai' ); ?></dt>
			<dd>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-recommendations', 'rec_id' => $rec_id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $rec_label ); ?></a>
			</dd>
			<?php endif; ?>
		</dl>
	</div>

	<?php if ( '' !== $ctx_raw ) : ?>
	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Context', 'reactwoo-geo-ai' ); ?></h2>
		<div class="rwga-pre-wrap"><?php echo wp_kses_post( wpautop( $ctx_raw ) ); ?></div>
	</div>
	<?php endif; ?>

	<?php if ( empty( $rwga_draft['report_html'] ) ) : ?><div class="rwgc-card">
		<h2><?php esc_html_e( 'Generated content', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Copy sections you need into the block editor or SEO plugin.', 'reactwoo-geo-ai' ); ?></p>
		<details class="rwga-dev-details" open>
			<summary><?php esc_html_e( 'Structured output (JSON)', 'reactwoo-geo-ai' ); ?></summary>
			<pre class="rwga-code-block"><?php echo esc_html( (string) $payload_text ); ?></pre>
		</details>
	</div><?php endif; ?>
	<div class="rwgc-card rwgc-card--highlight">
		<h3><?php esc_html_e( 'Continue journey', 'reactwoo-geo-ai' ); ?></h3>
		<p><?php esc_html_e( 'Next step: apply this draft to live content or test it as a variant.', 'reactwoo-geo-ai' ); ?></p>
	</div>
</div>
