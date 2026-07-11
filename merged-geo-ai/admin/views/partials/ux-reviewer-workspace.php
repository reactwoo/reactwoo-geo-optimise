<?php
/**
 * AI UX Reviewer workspace layout.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ctx              = isset( $ctx ) && is_array( $ctx ) ? $ctx : array();
$capabilities     = isset( $capabilities ) && is_array( $capabilities ) ? $capabilities : array();
$cards            = isset( $cards ) && is_array( $cards ) ? $cards : array();
$score_summary    = isset( $score_summary ) && is_array( $score_summary ) ? $score_summary : array();
$has_findings     = ! empty( $has_findings );
$can_run          = ! empty( $can_run );
$geo_ai_licensed  = ! empty( $geo_ai_licensed );
$remote_available = ! empty( $remote_available );
$usage_available  = ! empty( $usage_available );
$license_url      = isset( $license_url ) ? (string) $license_url : admin_url( 'admin.php?page=rwga-license' );
$has_run_cap      = ! empty( $has_run_cap );
$history_url      = isset( $history_url ) ? (string) $history_url : '';
$export_url       = isset( $export_url ) ? (string) $export_url : '';
$engine_label     = isset( $engine_label ) ? (string) $engine_label : '';
$pages            = isset( $pages ) && is_array( $pages ) ? $pages : array();
$front_page_id    = isset( $front_page_id ) ? (int) $front_page_id : 0;
$flash            = isset( $flash ) ? (string) $flash : '';
$flash_error      = isset( $flash_error ) ? (string) $flash_error : '';
$show_inner_nav   = ! empty( $ctx['show_inner_nav'] );
$nav_current      = isset( $ctx['nav_current'] ) ? (string) $ctx['nav_current'] : 'rwga-ux-opportunity-review';
$form_page        = isset( $ctx['form_action_page'] ) ? (string) $ctx['form_action_page'] : 'rwga-ux-opportunity-review';
$wrap_extra       = isset( $ctx['wrap_class'] ) ? (string) $ctx['wrap_class'] : '';
$source           = isset( $ctx['source'] ) ? (string) $ctx['source'] : 'dashboard';
$page_id          = (int) ( $ctx['page_id'] ?? 0 );
$product_id       = (int) ( $ctx['product_id'] ?? 0 );
$variant_id       = (int) ( $ctx['variant_page_id'] ?? 0 );
$rule_id          = isset( $ctx['rule_id'] ) ? (string) $ctx['rule_id'] : '';
$action_count     = (int) ( $ctx['action_count'] ?? 0 );
$pro_weather      = ! empty( $capabilities['geocore_pro_licensed'] );
$category_summaries = isset( $category_summaries ) && is_array( $category_summaries ) ? $category_summaries : array();
$audit_type_defs    = isset( $audit_type_defs ) && is_array( $audit_type_defs ) ? $audit_type_defs : array();
$selected_scopes    = isset( $selected_scopes ) && is_array( $selected_scopes ) ? $selected_scopes : array();
$has_review         = ! empty( $has_review );
$score_delta        = isset( $score_delta ) ? $score_delta : null;
$display_mode       = isset( $display_mode ) ? sanitize_key( (string) $display_mode ) : ( $has_review ? 'result' : 'fresh' );
if ( 'result' !== $display_mode ) {
	$display_mode = 'fresh';
}
$recent_activity    = isset( $recent_activity ) && is_array( $recent_activity ) ? $recent_activity : array();
$all_scope_slugs    = class_exists( 'RWGA_UX_Reviewer_UI', false ) ? RWGA_UX_Reviewer_UI::all_audit_scope_slugs() : array_keys( $audit_type_defs );
$is_full_scope      = empty( $selected_scopes ) || count( array_intersect( $selected_scopes, $all_scope_slugs ) ) === count( $all_scope_slugs );
$is_embed         = ! empty( $ctx['embed'] );
$outer_class = $is_embed
	? 'rwga-ux-reviewer ' . trim( $wrap_extra )
	: 'wrap rwgc-wrap rwgc-suite rwga-wrap rwga-ux-reviewer ' . trim( $wrap_extra );
$outer_class .= ' rwga-ux-reviewer--' . $display_mode;

$review_target_label = __( 'Homepage', 'reactwoo-geo-ai' );
if ( $page_id > 0 ) {
	$title = get_the_title( $page_id );
	$review_target_label = $title ? $title : sprintf( __( 'Page #%d', 'reactwoo-geo-ai' ), $page_id );
} elseif ( $product_id > 0 ) {
	$title = get_the_title( $product_id );
	$review_target_label = $title ? $title : sprintf( __( 'Product #%d', 'reactwoo-geo-ai' ), $product_id );
} elseif ( $variant_id > 0 ) {
	$title = get_the_title( $variant_id );
	$review_target_label = $title ? $title : sprintf( __( 'Variant #%d', 'reactwoo-geo-ai' ), $variant_id );
} elseif ( '' !== $rule_id ) {
	$review_target_label = sprintf( __( 'Rule #%s', 'reactwoo-geo-ai' ), $rule_id );
} elseif ( $front_page_id > 0 ) {
	$title = get_the_title( $front_page_id );
	if ( $title ) {
		$review_target_label = $title;
	}
}

$review_type_label = __( 'Full review', 'reactwoo-geo-ai' );
if ( ! $is_full_scope && ! empty( $selected_scopes ) ) {
	$labels = array();
	foreach ( $selected_scopes as $scope_slug ) {
		if ( isset( $audit_type_defs[ $scope_slug ]['label'] ) ) {
			$labels[] = (string) $audit_type_defs[ $scope_slug ]['label'];
		}
	}
	if ( ! empty( $labels ) ) {
		$review_type_label = implode( ', ', $labels );
	}
}

$setup_class = 'rwgc-card rwga-ux-reviewer__setup';
if ( $has_review ) {
	$setup_class .= ' rwga-ux-reviewer__setup--has-results';
}
?>
<div class="<?php echo esc_attr( $outer_class ); ?>">
	<?php if ( ! $is_embed ) : ?>
	<header class="rwga-ux-reviewer__header">
		<div class="rwga-ux-reviewer__header-copy">
			<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
				<?php
				RWGC_Admin_UI::render_page_header(
					__( 'AI UX Reviewer', 'reactwoo-geo-ai' ),
					__( 'Review targeted pages, variants, products, and rules for UX, copy, conversion, accessibility, and geo-personalisation opportunities.', 'reactwoo-geo-ai' )
				);
				?>
			<?php else : ?>
				<h1><?php esc_html_e( 'AI UX Reviewer', 'reactwoo-geo-ai' ); ?></h1>
			<?php endif; ?>
		</div>
		<div class="rwga-ux-reviewer__header-actions">
			<a class="rwgc-btn rwgc-btn--primary" href="#rwga-ux-review-setup"><?php esc_html_e( 'Run AI Review', 'reactwoo-geo-ai' ); ?></a>
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export report', 'reactwoo-geo-ai' ); ?></a>
			<a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( $history_url ); ?>"><?php esc_html_e( 'View history', 'reactwoo-geo-ai' ); ?></a>
		</div>
	</header>
	<?php endif; ?>

	<?php if ( $show_inner_nav && class_exists( 'RWGA_Admin', false ) ) : ?>
		<?php RWGA_Admin::render_inner_nav( $nav_current ); ?>
	<?php endif; ?>

	<div class="rwga-ux-reviewer__status-bar" aria-label="<?php esc_attr_e( 'AI connection status', 'reactwoo-geo-ai' ); ?>">
		<span class="rwgc-geo-badge rwgc-geo-badge--success"><?php esc_html_e( 'Local deterministic review available', 'reactwoo-geo-ai' ); ?></span>
		<span class="rwgc-geo-badge rwgc-geo-badge--<?php echo $remote_available ? 'success' : 'locked'; ?>">
			<?php echo $remote_available ? esc_html__( 'Remote Geo AI connected', 'reactwoo-geo-ai' ) : esc_html__( 'Remote Geo AI requires licence', 'reactwoo-geo-ai' ); ?>
		</span>
		<?php if ( ! $geo_ai_licensed ) : ?>
			<span class="rwgc-geo-badge rwgc-geo-badge--locked"><?php esc_html_e( 'Licence required', 'reactwoo-geo-ai' ); ?></span>
		<?php elseif ( ! $usage_available ) : ?>
			<span class="rwgc-geo-badge rwgc-geo-badge--locked"><?php esc_html_e( 'Usage unavailable', 'reactwoo-geo-ai' ); ?></span>
		<?php endif; ?>
		<?php if ( $engine_label ) : ?>
			<span class="rwgc-geo-badge rwgc-geo-badge--neutral"><?php echo esc_html( $engine_label ); ?></span>
		<?php endif; ?>
	</div>

	<?php if ( 'ran' === $flash ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: engine label, 2: pending action count */
					_n(
						'AI UX review completed (%1$s). %2$d pending action is ready for approval.',
						'AI UX review completed (%1$s). %2$d pending actions are ready for approval.',
						$action_count,
						'reactwoo-geo-ai'
					),
					$engine_label ? $engine_label : __( 'analysis complete', 'reactwoo-geo-ai' ),
					$action_count
				)
			);
			?>
			<?php if ( $action_count > 0 ) : ?>
				<a href="<?php echo esc_url( $history_url . '&status=pending' ); ?>"><?php esc_html_e( 'Review pending actions', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
		</p></div>
	<?php elseif ( 'error' === $flash ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $flash_error ? $flash_error : __( 'Review failed.', 'reactwoo-geo-ai' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( $has_review ) : ?>
		<div class="rwga-ux-reviewer__current-review" id="rwga-ux-current-review">
			<p class="rwga-ux-reviewer__current-review-text">
				<strong><?php esc_html_e( 'Current review:', 'reactwoo-geo-ai' ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: review type, 2: target */
						__( '%1$s of %2$s · All visitors · Desktop', 'reactwoo-geo-ai' ),
						$review_type_label,
						$review_target_label
					)
				);
				?>
			</p>
			<p class="rwga-ux-reviewer__current-review-actions">
				<button type="button" class="button-link" id="rwga-ux-run-another"><?php esc_html_e( 'Run another review', 'reactwoo-geo-ai' ); ?></button>
				<span aria-hidden="true"> · </span>
				<button type="button" class="button-link" id="rwga-ux-adjust-setup" aria-controls="rwga-ux-refine-setup" aria-expanded="false"><?php esc_html_e( 'Adjust setup', 'reactwoo-geo-ai' ); ?></button>
			</p>
		</div>
	<?php endif; ?>

	<div class="rwga-ux-reviewer__workspace">
		<div class="rwga-ux-reviewer__primary">
	<form id="rwga-ux-review-setup" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( $setup_class ); ?>"<?php echo $has_review ? ' hidden' : ''; ?> data-display-mode="<?php echo esc_attr( $display_mode ); ?>">
		<input type="hidden" name="action" value="rwga_run_ux_opportunity_review" />
		<?php wp_nonce_field( 'rwga_run_ux_opportunity_review' ); ?>
		<input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>" />
		<input type="hidden" name="page_id" id="rwga_ux_hidden_page_id" value="<?php echo esc_attr( (string) $page_id ); ?>" />
		<input type="hidden" name="product_id" id="rwga_ux_hidden_product_id" value="<?php echo esc_attr( (string) $product_id ); ?>" />
		<input type="hidden" name="variant_page_id" id="rwga_ux_hidden_variant_id" value="<?php echo esc_attr( (string) $variant_id ); ?>" />
		<input type="hidden" name="rule_id" id="rwga_ux_hidden_rule_id" value="<?php echo esc_attr( $rule_id ); ?>" />

		<div class="rwga-ux-reviewer__assistant">
			<div class="rwgc-geo-assistant-panel">
				<div class="rwgc-geo-assistant-panel__head"><?php esc_html_e( 'Tell us what to review', 'reactwoo-geo-ai' ); ?></div>
				<div class="rwgc-geo-assistant-panel__body">
					<div class="rwgc-targeting-assistant__thread" id="rwga-ux-assistant-thread" aria-live="polite"></div>
					<div class="rwgc-targeting-assistant__hints" id="rwga-ux-assistant-hints" aria-label="<?php esc_attr_e( 'Example review prompts', 'reactwoo-geo-ai' ); ?>"></div>
					<div class="rwgc-targeting-assistant__composer" id="rwga-ux-assistant-composer">
						<div class="rwgc-targeting-assistant__input-wrap">
							<textarea
								id="rwga-ux-assistant-phrase"
								class="rwgc-targeting-assistant__phrase"
								rows="2"
								placeholder="<?php esc_attr_e( 'Describe the UX review you want to run…', 'reactwoo-geo-ai' ); ?>"
								aria-label="<?php esc_attr_e( 'UX review request', 'reactwoo-geo-ai' ); ?>"
							></textarea>
							<button type="button" class="rwgc-targeting-assistant__send" id="rwga-ux-assistant-send" aria-label="<?php esc_attr_e( 'Send', 'reactwoo-geo-ai' ); ?>">
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</button>
						</div>
						<div class="rwgc-targeting-assistant__composer-meta">
							<span class="rwgc-targeting-assistant__detecting rwgc-is-hidden" id="rwga-ux-assistant-detecting"><?php esc_html_e( 'Detecting…', 'reactwoo-geo-ai' ); ?></span>
							<button type="button" class="button-link rwgc-targeting-assistant__reset rwga-ux-assistant-reset--hidden" id="rwga-ux-assistant-reset" hidden><?php esc_html_e( 'Start over', 'reactwoo-geo-ai' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<section class="rwga-ux-reviewer__review-types" aria-labelledby="rwga-ux-review-types-heading">
			<h2 id="rwga-ux-review-types-heading" class="rwga-ux-reviewer__section-title"><?php esc_html_e( 'Review types', 'reactwoo-geo-ai' ); ?></h2>
			<div class="rwga-ux-reviewer__audit-scopes">
				<label class="rwga-ux-reviewer__scope-option rwga-ux-reviewer__scope-option--full<?php echo $is_full_scope ? ' is-selected' : ''; ?>">
					<input type="checkbox" id="rwga_ux_scope_full" <?php checked( $is_full_scope ); ?> />
					<span class="rwga-ux-reviewer__scope-label"><?php esc_html_e( 'Full review', 'reactwoo-geo-ai' ); ?></span>
					<span class="description"><?php esc_html_e( 'All categories in one pass.', 'reactwoo-geo-ai' ); ?></span>
				</label>
				<div class="rwga-ux-reviewer__scope-grid">
					<?php foreach ( $audit_type_defs as $slug => $def ) : ?>
						<?php
						$item_checked = ! $is_full_scope && in_array( $slug, $selected_scopes, true );
						?>
						<label class="rwga-ux-reviewer__scope-option<?php echo $item_checked ? ' is-selected' : ''; ?>">
							<input type="checkbox" name="audit_scopes[]" value="<?php echo esc_attr( $slug ); ?>" class="rwga-ux-reviewer__scope-item" <?php checked( $item_checked ); ?> />
							<span class="rwga-ux-reviewer__scope-label"><?php echo esc_html( (string) ( $def['label'] ?? $slug ) ); ?></span>
							<span class="description"><?php echo esc_html( (string) ( $def['description'] ?? '' ) ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<div class="rwga-ux-reviewer__setup-summary" id="rwga-ux-setup-summary" aria-live="polite">
			<span class="rwga-ux-reviewer__setup-summary-label"><?php esc_html_e( 'Detected setup', 'reactwoo-geo-ai' ); ?></span>
			<span class="rwga-ux-reviewer__setup-summary-value" id="rwga-ux-setup-summary-value"><?php echo esc_html( $review_type_label . ' · ' . $review_target_label . ' · ' . __( 'All visitors', 'reactwoo-geo-ai' ) . ' · ' . __( 'Desktop', 'reactwoo-geo-ai' ) ); ?></span>
		</div>

		<p class="rwga-ux-reviewer__setup-actions">
			<button type="submit" class="rwgc-btn rwgc-btn--primary" <?php disabled( ! $can_run ); ?>><?php esc_html_e( 'Run review', 'reactwoo-geo-ai' ); ?></button>
			<button type="button" class="button-link rwga-ux-reviewer__adjust-link" id="rwga-ux-open-refine" aria-controls="rwga-ux-refine-setup" aria-expanded="false"><?php esc_html_e( 'Adjust setup', 'reactwoo-geo-ai' ); ?></button>
			<?php if ( ! $can_run ) : ?>
				<span class="description">
					<?php if ( ! $has_run_cap ) : ?>
						<?php esc_html_e( 'You need permission to run Geo AI workflows.', 'reactwoo-geo-ai' ); ?>
					<?php elseif ( ! $geo_ai_licensed ) : ?>
						<?php esc_html_e( 'Valid Geo AI licence required.', 'reactwoo-geo-ai' ); ?>
						<a href="<?php echo esc_url( $license_url ); ?>"><?php esc_html_e( 'Open Settings', 'reactwoo-geo-ai' ); ?></a>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</p>

		<details class="rwga-ux-reviewer__refine" id="rwga-ux-refine-setup">
			<summary><?php esc_html_e( 'Refine setup', 'reactwoo-geo-ai' ); ?></summary>
			<p class="description"><?php esc_html_e( 'Choose where to review and optionally refine audience or device.', 'reactwoo-geo-ai' ); ?></p>

			<h3 class="rwga-ux-reviewer__section-title"><?php esc_html_e( 'Where should we review?', 'reactwoo-geo-ai' ); ?></h3>
			<div class="rwga-ux-reviewer__setup-grid">
				<div class="rwgc-field">
					<label class="rwgc-field__label" for="rwga_ux_target_type"><?php esc_html_e( 'Content type', 'reactwoo-geo-ai' ); ?></label>
					<select id="rwga_ux_target_type" class="rwgc-select rwgc-input">
						<option value="page"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></option>
						<option value="variant"><?php esc_html_e( 'Variant', 'reactwoo-geo-ai' ); ?></option>
						<option value="product"><?php esc_html_e( 'Product', 'reactwoo-geo-ai' ); ?></option>
						<option value="rule"><?php esc_html_e( 'Rule', 'reactwoo-geo-ai' ); ?></option>
						<option value="site"><?php esc_html_e( 'Site-wide (homepage)', 'reactwoo-geo-ai' ); ?></option>
					</select>
				</div>
				<div class="rwgc-field rwga-ux-target-field" data-target="page">
					<label class="rwgc-field__label" for="rwga_ux_page_select"><?php esc_html_e( 'Choose page', 'reactwoo-geo-ai' ); ?></label>
					<select id="rwga_ux_page_select" class="rwgc-select rwgc-input">
						<option value="0"><?php esc_html_e( '— Select page —', 'reactwoo-geo-ai' ); ?></option>
						<?php foreach ( $pages as $p ) : ?>
							<?php if ( ! ( $p instanceof WP_Post ) || 'page' !== $p->post_type ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $page_id, (int) $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="rwgc-field rwga-ux-target-field" data-target="variant" hidden>
					<label class="rwgc-field__label" for="rwga_ux_variant_select"><?php esc_html_e( 'Variant page ID', 'reactwoo-geo-ai' ); ?></label>
					<input type="number" id="rwga_ux_variant_select" class="rwgc-input" value="<?php echo esc_attr( (string) $variant_id ); ?>" min="0" />
				</div>
				<div class="rwgc-field rwga-ux-target-field" data-target="product" hidden>
					<label class="rwgc-field__label" for="rwga_ux_product_select"><?php esc_html_e( 'Choose product', 'reactwoo-geo-ai' ); ?></label>
					<select id="rwga_ux_product_select" class="rwgc-select rwgc-input">
						<option value="0"><?php esc_html_e( '— Select product —', 'reactwoo-geo-ai' ); ?></option>
						<?php foreach ( $pages as $p ) : ?>
							<?php if ( ! ( $p instanceof WP_Post ) || 'product' !== $p->post_type ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( $product_id, (int) $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="rwgc-field rwga-ux-target-field" data-target="rule" hidden>
					<label class="rwgc-field__label" for="rwga_ux_rule_select"><?php esc_html_e( 'Rule ID', 'reactwoo-geo-ai' ); ?></label>
					<input type="text" id="rwga_ux_rule_select" class="rwgc-input" value="<?php echo esc_attr( $rule_id ); ?>" />
				</div>
			</div>

			<details class="rwga-ux-reviewer__audience-device">
				<summary><?php esc_html_e( 'Audience & device — Optional', 'reactwoo-geo-ai' ); ?></summary>
				<div class="rwga-ux-reviewer__setup-grid">
					<div class="rwgc-field">
						<label class="rwgc-field__label" for="rwga_ux_audience"><?php esc_html_e( 'Audience', 'reactwoo-geo-ai' ); ?></label>
						<select id="rwga_ux_audience" name="audience_label" class="rwgc-select rwgc-input">
							<option value="all"><?php esc_html_e( 'All visitors', 'reactwoo-geo-ai' ); ?></option>
							<option value="country"><?php esc_html_e( 'Country segment', 'reactwoo-geo-ai' ); ?></option>
							<option value="rule"><?php esc_html_e( 'Rule audience', 'reactwoo-geo-ai' ); ?></option>
							<option value="campaign"><?php esc_html_e( 'Campaign', 'reactwoo-geo-ai' ); ?></option>
							<option value="device"><?php esc_html_e( 'Device', 'reactwoo-geo-ai' ); ?></option>
							<?php if ( $pro_weather ) : ?>
								<option value="weather"><?php esc_html_e( 'Weather', 'reactwoo-geo-ai' ); ?></option>
							<?php endif; ?>
						</select>
					</div>
					<div class="rwgc-field">
						<label class="rwgc-field__label" for="rwga_ux_device"><?php esc_html_e( 'Device', 'reactwoo-geo-ai' ); ?></label>
						<select id="rwga_ux_device" name="device_type" class="rwgc-select rwgc-input">
							<option value="desktop"><?php esc_html_e( 'Desktop', 'reactwoo-geo-ai' ); ?></option>
							<option value="mobile"><?php esc_html_e( 'Mobile', 'reactwoo-geo-ai' ); ?></option>
							<option value="tablet"><?php esc_html_e( 'Tablet', 'reactwoo-geo-ai' ); ?></option>
						</select>
					</div>
				</div>
			</details>
		</details>
	</form>

	<?php if ( $has_review && ! empty( $category_summaries ) ) : ?>
		<div class="rwga-ux-reviewer__results-summary" id="rwga-ux-results-summary">
			<?php if ( null !== ( $score_summary['score'] ?? null ) ) : ?>
				<div class="rwga-ux-reviewer__overall-score" aria-label="<?php esc_attr_e( 'Overall performance score', 'reactwoo-geo-ai' ); ?>">
					<div class="rwga-ux-score-ring rwga-ux-score-ring--compact" style="--rwga-score: <?php echo esc_attr( (string) (int) $score_summary['score'] ); ?>;">
						<div class="rwga-ux-score-ring__inner">
							<span class="rwga-ux-score-ring__value"><?php echo esc_html( (string) $score_summary['score'] ); ?></span>
							<?php if ( null !== $score_delta && 0 !== (int) $score_delta ) : ?>
								<span class="rwga-ux-score-ring__delta"><?php echo esc_html( ( (int) $score_delta > 0 ? '+' : '' ) . (string) (int) $score_delta . '%' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<div class="rwga-ux-reviewer__overall-score-copy">
						<strong><?php esc_html_e( 'Performance score', 'reactwoo-geo-ai' ); ?></strong>
						<span>
							<?php
							if ( null !== $score_delta && 0 !== (int) $score_delta ) {
								echo esc_html( (int) $score_delta > 0 ? __( 'Improving', 'reactwoo-geo-ai' ) : __( 'Needs attention', 'reactwoo-geo-ai' ) );
							} else {
								esc_html_e( 'Stable', 'reactwoo-geo-ai' );
							}
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>

			<div class="rwga-ux-reviewer__categories" aria-label="<?php esc_attr_e( 'Audit category summary', 'reactwoo-geo-ai' ); ?>">
				<?php foreach ( $category_summaries as $summary ) : ?>
					<?php
					if ( ! is_array( $summary ) ) {
						continue;
					}
					$slug    = (string) ( $summary['slug'] ?? '' );
					$status  = (string) ( $summary['status'] ?? 'pending' );
					$percent = isset( $summary['percent'] ) ? (int) $summary['percent'] : null;
					$issues  = (int) ( $summary['issues'] ?? 0 );
					$desc    = (string) ( $summary['description'] ?? '' );
					?>
					<button type="button" class="rwga-ux-category-card rwga-ux-category-card--compact rwga-ux-category-card--<?php echo esc_attr( $status ); ?>" data-audit-filter="<?php echo esc_attr( $slug ); ?>" title="<?php echo esc_attr( $desc ); ?>" <?php disabled( in_array( $status, array( 'pending', 'skipped' ), true ) ); ?>>
						<span class="rwga-ux-category-card__label"><?php echo esc_html( (string) ( $summary['label'] ?? '' ) ); ?></span>
						<span class="rwga-ux-category-card__score">
							<?php if ( null !== $percent ) : ?>
								<?php echo esc_html( (string) $percent ); ?>%
							<?php else : ?>
								<?php echo esc_html( (string) ( $summary['status_label'] ?? '—' ) ); ?>
							<?php endif; ?>
						</span>
						<span class="rwga-ux-category-card__status"><?php echo esc_html( (string) ( $summary['status_label'] ?? '' ) ); ?></span>
						<span class="rwga-ux-category-card__issues">
							<?php
							if ( 'skipped' === $status ) {
								esc_html_e( 'Not included', 'reactwoo-geo-ai' );
							} elseif ( 'pending' === $status ) {
								esc_html_e( 'Awaiting review', 'reactwoo-geo-ai' );
							} else {
								echo esc_html(
									sprintf(
										/* translators: %d: issue count */
										_n( '%d issue', '%d issues', $issues, 'reactwoo-geo-ai' ),
										$issues
									)
								);
							}
							?>
						</span>
						<span class="screen-reader-text"><?php echo esc_html( $desc ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $has_review ) : ?>
	<div class="rwga-ux-reviewer__layout rwga-ux-reviewer__layout--results" id="rwga-ux-results-layout">
		<main class="rwga-ux-reviewer__feed">
			<div class="rwga-ux-reviewer__feed-toolbar">
				<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
					<?php RWGC_Admin_UI::render_section_header( __( 'Findings', 'reactwoo-geo-ai' ) ); ?>
				<?php else : ?>
					<h2><?php esc_html_e( 'Findings', 'reactwoo-geo-ai' ); ?></h2>
				<?php endif; ?>
				<div class="rwga-ux-reviewer__filters">
					<label class="screen-reader-text" for="rwga_ux_filter_search"><?php esc_html_e( 'Search findings', 'reactwoo-geo-ai' ); ?></label>
					<input type="search" id="rwga_ux_filter_search" class="rwgc-input" placeholder="<?php esc_attr_e( 'Search findings…', 'reactwoo-geo-ai' ); ?>" />
					<select id="rwga_ux_filter_priority" class="rwgc-select rwgc-input" aria-label="<?php esc_attr_e( 'Filter by priority', 'reactwoo-geo-ai' ); ?>">
						<option value=""><?php esc_html_e( 'All priorities', 'reactwoo-geo-ai' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'reactwoo-geo-ai' ); ?></option>
						<option value="medium"><?php esc_html_e( 'Medium', 'reactwoo-geo-ai' ); ?></option>
						<option value="low"><?php esc_html_e( 'Low', 'reactwoo-geo-ai' ); ?></option>
					</select>
					<select id="rwga_ux_filter_category" class="rwgc-select rwgc-input" aria-label="<?php esc_attr_e( 'Filter by audit category', 'reactwoo-geo-ai' ); ?>">
						<option value=""><?php esc_html_e( 'All categories', 'reactwoo-geo-ai' ); ?></option>
						<?php foreach ( $audit_type_defs as $slug => $def ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( (string) ( $def['label'] ?? $slug ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<?php foreach ( $cards as $idx => $card ) : ?>
				<?php
				if ( ! is_array( $card ) ) {
					continue;
				}
				$priority = RWGA_UX_Reviewer_UI::priority_from_card( $card );
				$category = RWGA_UX_Reviewer_UI::category_label( $card );
				$audit_scope = RWGA_UX_Reviewer_UI::audit_scope_for_card( $card );
				$actions  = RWGA_UX_Reviewer_UI::get_card_actions( $card );
				$target   = RWGA_UX_Reviewer_UI::affected_target_label( $card );
				$body     = ! empty( $card['problem'] ) ? (string) $card['problem'] : ( ! empty( $card['recommendation'] ) ? (string) $card['recommendation'] : '' );
				$primary_action = null;
				$secondary_actions = array();
				foreach ( $actions as $action ) {
					if ( null === $primary_action && ! empty( $action['active'] ) && ! empty( $action['url'] ) ) {
						$primary_action = $action;
					} else {
						$secondary_actions[] = $action;
					}
				}
				?>
				<article id="rwga-finding-<?php echo esc_attr( (string) $idx ); ?>" class="rwga-ux-finding" data-priority="<?php echo esc_attr( $priority ); ?>" data-audit-scope="<?php echo esc_attr( $audit_scope ); ?>" data-search="<?php echo esc_attr( strtolower( (string) ( $card['title'] ?? '' ) . ' ' . $body ) ); ?>">
					<header class="rwga-ux-finding__meta">
						<span class="rwga-ux-finding__priority rwga-ux-finding__priority--<?php echo esc_attr( $priority ); ?>"><?php echo esc_html( RWGA_UX_Reviewer_UI::priority_label( $priority ) ); ?></span>
						<span class="rwga-ux-finding__category"><?php echo esc_html( $category ); ?></span>
					</header>
					<h3 class="rwga-ux-finding__title"><?php echo esc_html( (string) ( $card['title'] ?? '' ) ); ?></h3>
					<p class="rwga-ux-finding__target description"><?php echo esc_html( $target ); ?></p>
					<?php if ( ! empty( $card['audience'] ) ) : ?>
						<p class="description"><?php echo esc_html( sprintf( __( 'Audience: %s', 'reactwoo-geo-ai' ), (string) $card['audience'] ) ); ?></p>
					<?php endif; ?>
					<?php if ( $body ) : ?>
						<p class="rwga-ux-finding__body"><?php echo wp_kses_post( $body ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $card['recommendation'] ) && ! empty( $card['problem'] ) ) : ?>
						<p class="rwga-ux-finding__rec"><strong><?php esc_html_e( 'Suggestion', 'reactwoo-geo-ai' ); ?></strong> <?php echo wp_kses_post( (string) $card['recommendation'] ); ?></p>
					<?php endif; ?>
					<footer class="rwga-ux-finding__actions">
						<?php if ( $primary_action ) : ?>
							<a class="<?php echo esc_attr( (string) $primary_action['class'] ); ?>" href="<?php echo esc_url( (string) $primary_action['url'] ); ?>"><?php echo esc_html( (string) $primary_action['label'] ); ?></a>
						<?php endif; ?>
						<?php foreach ( $secondary_actions as $action ) : ?>
							<?php if ( ! empty( $action['active'] ) && ! empty( $action['url'] ) ) : ?>
								<a class="button-link rwga-ux-finding__secondary-action" href="<?php echo esc_url( (string) $action['url'] ); ?>"><?php echo esc_html( (string) $action['label'] ); ?></a>
							<?php elseif ( ! empty( $action['label'] ) ) : ?>
								<span class="<?php echo esc_attr( (string) ( $action['class'] ?? 'rwgc-geo-badge rwgc-geo-badge--locked' ) ); ?>"><?php echo esc_html( (string) $action['label'] ); ?></span>
							<?php endif; ?>
						<?php endforeach; ?>
						<a class="button-link rwga-ux-finding__dismiss" href="<?php echo esc_url( $history_url ); ?>"><?php esc_html_e( 'Dismiss to actions', 'reactwoo-geo-ai' ); ?></a>
					</footer>
				</article>
			<?php endforeach; ?>
		</main>
	</div>
	<?php endif; ?>
		</div><!-- .rwga-ux-reviewer__primary -->

		<aside class="rwga-ux-reviewer__sidebar rwga-ux-reviewer__recent" aria-labelledby="rwga-ux-recent-heading">
			<h2 id="rwga-ux-recent-heading" class="rwga-ux-reviewer__section-title"><?php esc_html_e( 'Recent activity', 'reactwoo-geo-ai' ); ?></h2>
			<?php if ( empty( $recent_activity ) ) : ?>
				<p class="description rwga-ux-reviewer__recent-empty"><?php esc_html_e( 'Your completed reviews will appear here.', 'reactwoo-geo-ai' ); ?></p>
			<?php else : ?>
				<ul class="rwga-ux-reviewer__recent-list">
					<?php foreach ( $recent_activity as $i => $entry ) : ?>
						<?php
						if ( ! is_array( $entry ) ) {
							continue;
						}
						$is_latest = ( 0 === (int) $i );
						?>
						<li class="rwga-ux-reviewer__recent-item<?php echo $is_latest ? ' is-latest' : ''; ?>">
							<?php if ( $is_latest ) : ?>
								<span class="rwga-ux-reviewer__recent-kicker"><?php esc_html_e( 'Last review', 'reactwoo-geo-ai' ); ?></span>
							<?php elseif ( 1 === (int) $i ) : ?>
								<span class="rwga-ux-reviewer__recent-kicker"><?php esc_html_e( 'Earlier', 'reactwoo-geo-ai' ); ?></span>
							<?php endif; ?>
							<a class="rwga-ux-reviewer__recent-link" href="<?php echo esc_url( (string) ( $entry['url'] ?? $history_url ) ); ?>">
								<span class="rwga-ux-reviewer__recent-title"><?php echo esc_html( (string) ( $entry['title'] ?? '' ) ); ?></span>
								<?php if ( isset( $entry['score'] ) && null !== $entry['score'] ) : ?>
									<span class="rwga-ux-reviewer__recent-score">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: score */
												__( 'Score %d', 'reactwoo-geo-ai' ),
												(int) $entry['score']
											)
										);
										?>
									</span>
								<?php endif; ?>
								<?php if ( ! empty( $entry['time_label'] ) ) : ?>
									<span class="rwga-ux-reviewer__recent-time"><?php echo esc_html( (string) $entry['time_label'] ); ?></span>
								<?php endif; ?>
								<span class="rwga-ux-reviewer__recent-open"><?php esc_html_e( 'Open result', 'reactwoo-geo-ai' ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="rwga-ux-reviewer__recent-footer">
				<a href="<?php echo esc_url( $history_url ); ?>"><?php esc_html_e( 'View all history', 'reactwoo-geo-ai' ); ?></a>
			</p>
			<?php if ( $engine_label || $remote_available || $geo_ai_licensed ) : ?>
				<p class="description rwga-ux-reviewer__recent-status">
					<?php
					if ( $remote_available ) {
						esc_html_e( 'Remote Geo AI connected', 'reactwoo-geo-ai' );
					} else {
						esc_html_e( 'Local review available', 'reactwoo-geo-ai' );
					}
					?>
				</p>
			<?php endif; ?>
		</aside>
	</div><!-- .rwga-ux-reviewer__workspace -->
</div>
<script>
(function () {
	var search = document.getElementById('rwga_ux_filter_search');
	var priority = document.getElementById('rwga_ux_filter_priority');
	var category = document.getElementById('rwga_ux_filter_category');
	var categoryCards = document.querySelectorAll('.rwga-ux-category-card[data-audit-filter]');
	var findings = document.querySelectorAll('.rwga-ux-finding:not(.rwga-ux-finding--example)');
	function filterFindings() {
		var q = search ? search.value.toLowerCase() : '';
		var p = priority ? priority.value : '';
		var c = category ? category.value : '';
		findings.forEach(function (el) {
			var matchQ = !q || (el.getAttribute('data-search') || '').indexOf(q) !== -1;
			var matchP = !p || el.getAttribute('data-priority') === p;
			var matchC = !c || el.getAttribute('data-audit-scope') === c;
			el.style.display = matchQ && matchP && matchC ? '' : 'none';
		});
	}
	if (search) { search.addEventListener('input', filterFindings); }
	if (priority) { priority.addEventListener('change', filterFindings); }
	if (category) { category.addEventListener('change', filterFindings); }
	categoryCards.forEach(function (card) {
		card.addEventListener('click', function () {
			var slug = card.getAttribute('data-audit-filter') || '';
			if (!slug || !category) { return; }
			category.value = category.value === slug ? '' : slug;
			filterFindings();
			categoryCards.forEach(function (el) {
				el.classList.toggle('is-active', el.getAttribute('data-audit-filter') === category.value && category.value !== '');
			});
		});
	});
})();
</script>
