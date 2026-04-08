<?php
/**
 * Shared Create / Edit Test form body (Geo Optimise — user-facing "Test"; data remains experiment config).
 *
 * Expects:
 * - $rwgo_form_mode 'create'|'edit'
 * - $rwgo_catalog (array) for create JS; edit uses same shape
 * - $rwgo_require_confirm (bool) create only
 * - $rwgo_prefill (array) edit: name, test_type, source_id, variant_b_id, targeting_mode, countries_csv, winner_mode, goal_type, status, experiment_key, return_context (tests|reports)
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_form_mode   = isset( $rwgo_form_mode ) ? $rwgo_form_mode : 'create';
$rwgo_is_edit     = ( 'edit' === $rwgo_form_mode );
$rwgo_prefill     = isset( $rwgo_prefill ) && is_array( $rwgo_prefill ) ? $rwgo_prefill : array();
$rwgo_require_confirm = ! $rwgo_is_edit && class_exists( 'RWGO_Settings', false ) && RWGO_Settings::require_goal_confirm_publish();

$pf_name            = isset( $rwgo_prefill['name'] ) ? (string) $rwgo_prefill['name'] : '';
$pf_test_type       = isset( $rwgo_prefill['test_type'] ) ? sanitize_key( (string) $rwgo_prefill['test_type'] ) : 'page_ab';
$pf_source_id       = isset( $rwgo_prefill['source_id'] ) ? (int) $rwgo_prefill['source_id'] : 0;
$pf_variant_b_id    = isset( $rwgo_prefill['variant_b_id'] ) ? (int) $rwgo_prefill['variant_b_id'] : 0;
$pf_targeting_mode  = isset( $rwgo_prefill['targeting_mode'] ) ? sanitize_key( (string) $rwgo_prefill['targeting_mode'] ) : 'everyone';
$pf_countries       = isset( $rwgo_prefill['countries_csv'] ) ? (string) $rwgo_prefill['countries_csv'] : '';
$pf_winner_mode     = isset( $rwgo_prefill['winner_mode'] ) ? sanitize_key( (string) $rwgo_prefill['winner_mode'] ) : 'goal';
$pf_goal_type       = isset( $rwgo_prefill['goal_type'] ) ? sanitize_key( (string) $rwgo_prefill['goal_type'] ) : 'page_view';
$pf_goal_sel_mode   = isset( $rwgo_prefill['goal_selection_mode'] ) ? sanitize_key( (string) $rwgo_prefill['goal_selection_mode'] ) : 'automatic';
$pf_defined_json    = isset( $rwgo_prefill['defined_goal_json'] ) ? (string) $rwgo_prefill['defined_goal_json'] : '';
if ( 'traffic_only' === $pf_goal_type ) {
	$pf_winner_mode = 'traffic_only';
	$pf_goal_type   = 'page_view';
}
$pf_goal_type_auto = $pf_goal_type;
if ( 'defined' === $pf_goal_type_auto ) {
	$pf_goal_type_auto = 'page_view';
}

$type_labels = array(
	'page_ab'        => __( 'Page A/B test', 'reactwoo-geo-optimise' ),
	'elementor_page' => __( 'Elementor page version', 'reactwoo-geo-optimise' ),
	'gutenberg_page' => __( 'Gutenberg page version', 'reactwoo-geo-optimise' ),
	'woo_product'    => __( 'WooCommerce product page test', 'reactwoo-geo-optimise' ),
	'custom_php'     => __( 'Advanced / custom', 'reactwoo-geo-optimise' ),
);

$form_id = $rwgo_is_edit ? 'rwgo-edit-test-form' : 'rwgo-create-test-form';
$pf_source_id_for_goals = isset( $rwgo_prefill['source_id'] ) ? (int) $rwgo_prefill['source_id'] : 0;
$pf_var_b_for_goals     = isset( $rwgo_prefill['variant_b_id'] ) ? (int) $rwgo_prefill['variant_b_id'] : 0;
?>
<?php if ( $rwgo_is_edit ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-create-form rwgo-create-form--edit-reorder" id="<?php echo esc_attr( $form_id ); ?>" data-rwgo-form-mode="edit" data-rwgo-source-id="<?php echo esc_attr( (string) $pf_source_id_for_goals ); ?>" data-rwgo-variant-b-id="<?php echo esc_attr( (string) $pf_var_b_for_goals ); ?>">
	<input type="hidden" name="action" value="rwgo_update_test" />
	<?php wp_nonce_field( 'rwgo_update_test' ); ?>
	<input type="hidden" name="rwgo_experiment_id" value="<?php echo esc_attr( (string) (int) ( $rwgo_prefill['experiment_id'] ?? 0 ) ); ?>" />
	<?php
	$rwgo_rc = isset( $rwgo_prefill['return_context'] ) ? sanitize_key( (string) $rwgo_prefill['return_context'] ) : '';
	if ( ! in_array( $rwgo_rc, array( 'tests', 'reports' ), true ) ) {
		$rwgo_rc = '';
	}
	?>
	<input type="hidden" name="rwgo_return_context" value="<?php echo esc_attr( $rwgo_rc ); ?>" />
<?php else : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-create-form" id="<?php echo esc_attr( $form_id ); ?>" data-rwgo-form-mode="create" data-rwgo-source-id="0" data-rwgo-variant-b-id="0">
	<input type="hidden" name="action" value="rwgo_create_test" />
	<?php wp_nonce_field( 'rwgo_create_test' ); ?>
<?php endif; ?>

	<section class="rwgo-card rwgo-section rwgo-section--eo-3" aria-labelledby="rwgo-sec-name">
		<h2 class="rwgo-section__title" id="rwgo-sec-name"><?php echo $rwgo_is_edit ? esc_html__( 'Test name', 'reactwoo-geo-optimise' ) : esc_html__( '1. Test name', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="rwgo-section__lead"><?php esc_html_e( 'Give this test a short internal name so it is easy to recognise later.', 'reactwoo-geo-optimise' ); ?></p>
		<div class="rwgo-field">
			<label class="rwgo-field__label" for="rwgo_test_name"><?php esc_html_e( 'Name', 'reactwoo-geo-optimise' ); ?></label>
			<input type="text" class="rwgo-input rwgo-input--lg" id="rwgo_test_name" name="rwgo_test_name" required placeholder="<?php esc_attr_e( 'e.g. Homepage hero', 'reactwoo-geo-optimise' ); ?>" value="<?php echo esc_attr( $pf_name ); ?>" />
		</div>
	</section>

	<section class="rwgo-card rwgo-section rwgo-section--eo-4" aria-labelledby="rwgo-sec-type">
		<h2 class="rwgo-section__title" id="rwgo-sec-type"><?php echo $rwgo_is_edit ? esc_html__( 'Test type', 'reactwoo-geo-optimise' ) : esc_html__( '2. Test type', 'reactwoo-geo-optimise' ); ?></h2>
		<?php if ( $rwgo_is_edit ) : ?>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Test type is fixed after creation to preserve reporting consistency. Duplicate the test if you need a different structure.', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-hint rwgo-hint--accent"><strong><?php echo esc_html( isset( $type_labels[ $pf_test_type ] ) ? $type_labels[ $pf_test_type ] : $pf_test_type ); ?></strong></p>
		<?php else : ?>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Choose how this test is structured. Geo Optimise uses this to filter what you can pick as Control and Variant B.', 'reactwoo-geo-optimise' ); ?></p>
			<div class="rwgo-field">
				<label class="rwgo-field__label" for="rwgo_test_type"><?php esc_html_e( 'What are you testing?', 'reactwoo-geo-optimise' ); ?></label>
				<select name="rwgo_test_type" id="rwgo_test_type" class="rwgo-input">
					<option value="page_ab"><?php esc_html_e( 'Page A/B test', 'reactwoo-geo-optimise' ); ?></option>
					<option value="elementor_page"><?php esc_html_e( 'Elementor page version', 'reactwoo-geo-optimise' ); ?></option>
					<option value="gutenberg_page"><?php esc_html_e( 'Gutenberg page version', 'reactwoo-geo-optimise' ); ?></option>
					<option value="woo_product"><?php esc_html_e( 'WooCommerce product page test', 'reactwoo-geo-optimise' ); ?></option>
					<option value="custom_php"><?php esc_html_e( 'Advanced / custom', 'reactwoo-geo-optimise' ); ?></option>
				</select>
			</div>
			<div id="rwgo-test-type-hint" class="rwgo-hint rwgo-hint--accent" role="status"></div>
		<?php endif; ?>
	</section>

	<section class="rwgo-card rwgo-section rwgo-section--eo-5" aria-labelledby="rwgo-sec-source">
		<h2 class="rwgo-section__title" id="rwgo-sec-source"><?php echo $rwgo_is_edit ? esc_html__( 'Source content (Control)', 'reactwoo-geo-optimise' ) : esc_html__( '3. Source content', 'reactwoo-geo-optimise' ); ?></h2>
		<?php if ( $rwgo_is_edit ) : ?>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Source content is fixed after creation. Duplicate the test if you want to test a different page or product.', 'reactwoo-geo-optimise' ); ?></p>
			<div class="rwgo-summary-bar">
				<?php if ( $pf_source_id > 0 && get_post( $pf_source_id ) ) : ?>
					<strong><?php echo esc_html( class_exists( 'RWGO_Admin_Content_Catalog', false ) ? RWGO_Admin_Content_Catalog::format_page_admin_label( $pf_source_id ) : get_the_title( $pf_source_id ) ); ?></strong>
					<?php
					$sel = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::post_builder_edit_url( $pf_source_id, $pf_test_type ) : get_edit_post_link( $pf_source_id );
					$pl  = get_permalink( $pf_source_id );
					?>
					<p class="rwgo-cta-row">
						<?php if ( is_string( $sel ) && $sel ) : ?>
							<a class="button button-small rwgo-btn rwgo-btn--secondary rwgo-btn--sm" href="<?php echo esc_url( $sel ); ?>"><?php esc_html_e( 'Edit Control', 'reactwoo-geo-optimise' ); ?></a>
						<?php endif; ?>
						<?php if ( is_string( $pl ) && $pl ) : ?>
							<a class="button button-small rwgo-btn rwgo-btn--secondary rwgo-btn--sm" href="<?php echo esc_url( $pl ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View live', 'reactwoo-geo-optimise' ); ?></a>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="notice notice-warning inline"><?php esc_html_e( 'Source content is missing or unavailable.', 'reactwoo-geo-optimise' ); ?></p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<p class="rwgo-section__lead"><?php esc_html_e( 'This becomes your Control (A) version. You’ll choose how Variant B is created next.', 'reactwoo-geo-optimise' ); ?></p>
			<div class="rwgo-field">
				<label class="rwgo-field__label" for="rwgo_source_page"><?php esc_html_e( 'What page, post, or product are you testing?', 'reactwoo-geo-optimise' ); ?></label>
				<select name="rwgo_source_page" id="rwgo_source_page" class="rwgo-input" required>
					<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-optimise' ); ?></option>
				</select>
				<p id="rwgo-source-after" class="rwgo-hint" hidden></p>
			</div>
		<?php endif; ?>
	</section>

	<section class="rwgo-card rwgo-section rwgo-section--eo-6" aria-labelledby="rwgo-sec-variants">
		<h2 class="rwgo-section__title" id="rwgo-sec-variants"><?php echo $rwgo_is_edit ? esc_html__( 'Variant B — advanced setup', 'reactwoo-geo-optimise' ) : esc_html__( '4. Variants', 'reactwoo-geo-optimise' ); ?></h2>
		<?php if ( $rwgo_is_edit ) : ?>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Control (A) is the original version. Variant B is the version compared against it.', 'reactwoo-geo-optimise' ); ?></p>
			<div class="rwgo-callout rwgo-callout--info">
				<p class="rwgo-callout__p"><?php esc_html_e( 'Changing Variant B or relinking it may change what future visitors see. Existing reporting stays at the test level; experiment key is unchanged.', 'reactwoo-geo-optimise' ); ?></p>
			</div>
			<div class="rwgo-summary-bar">
				<strong><?php esc_html_e( 'Control (A)', 'reactwoo-geo-optimise' ); ?></strong>
				<?php if ( $pf_source_id > 0 && get_post( $pf_source_id ) ) : ?>
					<span><?php echo esc_html( get_the_title( $pf_source_id ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="rwgo-summary-bar">
				<strong><?php esc_html_e( 'Variant B', 'reactwoo-geo-optimise' ); ?></strong>
				<?php if ( $pf_variant_b_id > 0 && get_post( $pf_variant_b_id ) ) : ?>
					<span><?php echo esc_html( get_the_title( $pf_variant_b_id ) ); ?></span>
					<?php
					$v_ed = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::post_builder_edit_url( $pf_variant_b_id, $pf_test_type ) : get_edit_post_link( $pf_variant_b_id );
					$v_pl = get_permalink( $pf_variant_b_id );
					?>
					<p class="rwgo-cta-row">
						<?php if ( is_string( $v_ed ) && $v_ed ) : ?>
							<a class="button button-small rwgo-btn rwgo-btn--secondary rwgo-btn--sm" href="<?php echo esc_url( $v_ed ); ?>"><?php esc_html_e( 'Edit Variant B', 'reactwoo-geo-optimise' ); ?></a>
						<?php endif; ?>
						<?php if ( is_string( $v_pl ) && $v_pl ) : ?>
							<a class="button button-small" href="<?php echo esc_url( $v_pl ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View live', 'reactwoo-geo-optimise' ); ?></a>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="notice notice-warning inline"><?php esc_html_e( 'Variant B is missing or unavailable — choose a replacement below before saving.', 'reactwoo-geo-optimise' ); ?></p>
				<?php endif; ?>
			</div>
			<fieldset class="rwgo-fieldset">
				<legend class="rwgo-field__label"><?php esc_html_e( 'Variant B setup', 'reactwoo-geo-optimise' ); ?></legend>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_edit_action" value="keep" checked="checked" class="rwgo-variant-edit-action" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Keep current Variant B', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Continue using the linked page as Variant B.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_edit_action" value="replace_existing" class="rwgo-variant-edit-action" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Replace with an existing page', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Point Variant B at different published content (must match the original test type).', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_edit_action" value="duplicate_source" class="rwgo-variant-edit-action" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Regenerate duplicate from Control', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Create a fresh copy of the source as a new Variant B page.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
			</fieldset>
			<div id="rwgo-edit-variant-b-wrap" class="rwgo-field" hidden>
				<label class="rwgo-field__label" for="rwgo_variant_b_page_edit"><?php esc_html_e( 'Select Variant B page', 'reactwoo-geo-optimise' ); ?></label>
				<select name="rwgo_variant_b_page" id="rwgo_variant_b_page_edit" class="rwgo-input">
					<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-optimise' ); ?></option>
				</select>
			</div>
		<?php else : ?>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Every test compares your source content as Control (A) against a second version, Variant B. Choose how Variant B should be created.', 'reactwoo-geo-optimise' ); ?></p>
			<div class="rwgo-summary-bar" id="rwgo-control-summary">
				<strong><?php esc_html_e( 'Control (A)', 'reactwoo-geo-optimise' ); ?></strong>
				<span class="rwgo-muted"><?php esc_html_e( 'Uses your selected source content.', 'reactwoo-geo-optimise' ); ?></span>
			</div>
			<fieldset class="rwgo-fieldset">
				<legend class="rwgo-field__label"><?php esc_html_e( 'How should Variant B be created?', 'reactwoo-geo-optimise' ); ?></legend>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_mode" value="duplicate" checked="checked" class="rwgo-variant-mode" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Duplicate source automatically (recommended)', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Geo Optimise will create a full editable duplicate of the source page, including supported builder data. If duplication fails, the variant will not be attached and you can choose another recovery option.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_mode" value="existing" class="rwgo-variant-mode" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Use an existing page, post, or product', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Choose content you have already published as Variant B.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
				<label class="rwgo-radio-card">
					<input type="radio" name="rwgo_variant_mode" value="blank" class="rwgo-variant-mode" />
					<span class="rwgo-radio-card__body">
						<strong><?php esc_html_e( 'Create a new blank variant', 'reactwoo-geo-optimise' ); ?></strong>
						<span class="rwgo-radio-card__desc"><?php esc_html_e( 'Create a new draft linked to this test and design Variant B after publishing.', 'reactwoo-geo-optimise' ); ?></span>
					</span>
				</label>
			</fieldset>
			<div id="rwgo-variant-b-existing-wrap" class="rwgo-field" hidden>
				<label class="rwgo-field__label" for="rwgo_variant_b_page"><?php esc_html_e( 'Select existing Variant B', 'reactwoo-geo-optimise' ); ?></label>
				<select name="rwgo_variant_b_page" id="rwgo_variant_b_page" class="rwgo-input">
					<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-optimise' ); ?></option>
				</select>
			</div>
			<div class="rwgo-callout rwgo-callout--info">
				<p class="rwgo-callout__p"><?php esc_html_e( 'After publishing, you can open and edit both versions from the Tests screen.', 'reactwoo-geo-optimise' ); ?></p>
			</div>
		<?php endif; ?>
	</section>

	<section class="rwgo-card rwgo-section rwgo-section--eo-2" aria-labelledby="rwgo-sec-target">
		<h2 class="rwgo-section__title" id="rwgo-sec-target"><?php echo $rwgo_is_edit ? esc_html__( 'Audience & assignment', 'reactwoo-geo-optimise' ) : esc_html__( '5. Targeting', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="rwgo-section__lead"><?php echo esc_html( $rwgo_is_edit ? __( 'Changes here affect which future visitors can enter the test.', 'reactwoo-geo-optimise' ) : __( 'Who should enter this test?', 'reactwoo-geo-optimise' ) ); ?></p>
		<div class="rwgo-field">
			<select name="rwgo_targeting_mode" id="rwgo_targeting_mode" class="rwgo-input">
				<option value="everyone" <?php selected( $pf_targeting_mode, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'reactwoo-geo-optimise' ); ?></option>
				<option value="countries" <?php selected( $pf_targeting_mode, 'countries' ); ?>><?php esc_html_e( 'Selected countries', 'reactwoo-geo-optimise' ); ?></option>
			</select>
		</div>
		<div id="rwgo-countries-wrap" class="rwgo-field" <?php echo 'countries' === $pf_targeting_mode ? '' : ' style="display:none;"'; ?>>
			<label class="rwgo-field__label" for="rwgo_countries"><?php esc_html_e( 'Countries', 'reactwoo-geo-optimise' ); ?></label>
			<input type="text" class="rwgo-input" id="rwgo_countries" name="rwgo_countries" placeholder="<?php esc_attr_e( 'e.g. GB, US, DE', 'reactwoo-geo-optimise' ); ?>" value="<?php echo esc_attr( $pf_countries ); ?>" />
			<p class="rwgo-hint"><?php esc_html_e( 'Only visitors from these countries will enter the test (ISO codes, comma-separated). Geo Core provides the visitor country when available.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
	</section>

	<section class="rwgo-card rwgo-section rwgo-section--eo-1" aria-labelledby="rwgo-sec-goal">
		<h2 class="rwgo-section__title" id="rwgo-sec-goal"><?php echo $rwgo_is_edit ? esc_html__( 'Goal & tracking', 'reactwoo-geo-optimise' ) : esc_html__( '6. What counts as success?', 'reactwoo-geo-optimise' ); ?></h2>
		<?php if ( $rwgo_is_edit ) : ?>
			<div class="rwgo-callout rwgo-callout--info">
				<p class="rwgo-callout__p"><?php esc_html_e( 'Changing success goals or mapping changes how this test is evaluated from now on. Historical data remains; winner interpretation may change. Consider duplicating the test if you need a clean comparison.', 'reactwoo-geo-optimise' ); ?></p>
			</div>
		<?php endif; ?>
		<fieldset class="rwgo-fieldset rwgo-fieldset--tight">
			<label class="rwgo-radio-line">
				<input type="radio" name="rwgo_winner_mode" value="goal" class="rwgo-winner-mode" <?php checked( $pf_winner_mode, 'goal' ); ?> />
				<span><strong><?php esc_html_e( 'Measure conversions (recommended)', 'reactwoo-geo-optimise' ); ?></strong> — <?php esc_html_e( 'Total conversions across your selected success goals determine the leading variant.', 'reactwoo-geo-optimise' ); ?></span>
			</label>
			<label class="rwgo-radio-line">
				<input type="radio" name="rwgo_winner_mode" value="traffic_only" class="rwgo-winner-mode" <?php checked( $pf_winner_mode, 'traffic_only' ); ?> />
				<span><strong><?php esc_html_e( 'Traffic split only', 'reactwoo-geo-optimise' ); ?></strong> — <?php esc_html_e( 'Measure reach per variant without choosing a conversion winner.', 'reactwoo-geo-optimise' ); ?></span>
			</label>
		</fieldset>
		<input type="hidden" name="rwgo_defined_goal" id="rwgo_defined_goal" value="<?php echo esc_attr( $pf_defined_json ); ?>" />
		<div id="rwgo-goal-wrap" class="rwgo-field rwgo-goal-wrap" <?php echo 'traffic_only' === $pf_winner_mode ? ' style="display:none;"' : ''; ?>>
			<p class="rwgo-section__lead"><?php esc_html_e( 'CTA goals can be defined directly in Elementor and Gutenberg on buttons, links, forms, and other conversion points. Destination goals are set per page. Automatic detection is available when you prefer not to use builder markers yet.', 'reactwoo-geo-optimise' ); ?></p>
			<fieldset class="rwgo-fieldset rwgo-fieldset--tight">
				<legend class="rwgo-field__label"><?php esc_html_e( 'Goal source', 'reactwoo-geo-optimise' ); ?></legend>
				<label class="rwgo-radio-line">
					<input type="radio" name="rwgo_goal_selection_mode" value="defined" class="rwgo-goal-sel-mode" <?php checked( $pf_goal_sel_mode, 'defined' ); ?> />
					<span><?php esc_html_e( 'Use a defined CTA, form, or destination goal', 'reactwoo-geo-optimise' ); ?></span>
				</label>
				<label class="rwgo-radio-line">
					<input type="radio" name="rwgo_goal_selection_mode" value="automatic" class="rwgo-goal-sel-mode" <?php checked( $pf_goal_sel_mode, 'automatic' ); ?> />
					<span><?php esc_html_e( 'Use automatic detection', 'reactwoo-geo-optimise' ); ?></span>
				</label>
			</fieldset>
			<div id="rwgo-defined-goal-panel" class="rwgo-field" <?php echo 'defined' !== $pf_goal_sel_mode ? 'hidden' : ''; ?>>
				<p class="rwgo-hint"><?php esc_html_e( 'Pick which marked goal counts as a conversion for each version. Labels do not need to match — the test links explicit goal IDs per page.', 'reactwoo-geo-optimise' ); ?></p>
				<div class="rwgo-field rwgo-field--tight">
					<label class="rwgo-field__label" for="rwgo_defined_goal_control_select"><?php esc_html_e( 'Which marked goal counts as success on Control?', 'reactwoo-geo-optimise' ); ?></label>
					<select id="rwgo_defined_goal_control_select" class="rwgo-input rwgo-defined-goal-select" data-rwgo-mapping-role="control" <?php echo 'traffic_only' === $pf_winner_mode ? ' disabled="disabled"' : ''; ?>>
						<option value=""><?php esc_html_e( '— Loading…', 'reactwoo-geo-optimise' ); ?></option>
					</select>
				</div>
				<div class="rwgo-field rwgo-field--tight" id="rwgo-defined-goal-varb-wrap">
					<label class="rwgo-field__label" for="rwgo_defined_goal_var_b_select"><?php esc_html_e( 'Which marked goal counts as success on Variant B?', 'reactwoo-geo-optimise' ); ?></label>
					<select id="rwgo_defined_goal_var_b_select" class="rwgo-input rwgo-defined-goal-select" data-rwgo-mapping-role="var_b" <?php echo 'traffic_only' === $pf_winner_mode ? ' disabled="disabled"' : ''; ?>>
						<option value=""><?php esc_html_e( '— Loading…', 'reactwoo-geo-optimise' ); ?></option>
					</select>
					<p class="rwgo-hint"><?php esc_html_e( 'You can choose different goals for each version.', 'reactwoo-geo-optimise' ); ?></p>
				</div>
				<p class="rwgo-hint"><?php esc_html_e( 'If a list is empty, open the page in Elementor or Gutenberg and add a Geo Optimise goal to the CTA, form, or destination you want to measure.', 'reactwoo-geo-optimise' ); ?></p>
			</div>
			<div id="rwgo-automatic-goal-panel" class="rwgo-field" <?php echo 'automatic' !== $pf_goal_sel_mode ? 'hidden' : ''; ?>>
				<label class="rwgo-field__label" for="rwgo_goal_type"><?php esc_html_e( 'Winning metric (automatic)', 'reactwoo-geo-optimise' ); ?></label>
				<select name="rwgo_goal_type" id="rwgo_goal_type" class="rwgo-input" <?php echo 'traffic_only' === $pf_winner_mode ? ' disabled="disabled"' : ''; ?>>
					<option value="page_view" <?php selected( $pf_goal_type_auto, 'page_view' ); ?>><?php esc_html_e( 'Page view', 'reactwoo-geo-optimise' ); ?></option>
					<option value="cta_click" <?php selected( $pf_goal_type_auto, 'cta_click' ); ?>><?php esc_html_e( 'CTA click', 'reactwoo-geo-optimise' ); ?></option>
					<option value="form_submit" <?php selected( $pf_goal_type_auto, 'form_submit' ); ?>><?php esc_html_e( 'Form submission', 'reactwoo-geo-optimise' ); ?></option>
					<option value="add_to_cart" <?php selected( $pf_goal_type_auto, 'add_to_cart' ); ?>><?php esc_html_e( 'Add to cart', 'reactwoo-geo-optimise' ); ?></option>
					<option value="begin_checkout" <?php selected( $pf_goal_type_auto, 'begin_checkout' ); ?>><?php esc_html_e( 'Begin checkout', 'reactwoo-geo-optimise' ); ?></option>
					<option value="purchase" <?php selected( $pf_goal_type_auto, 'purchase' ); ?>><?php esc_html_e( 'Purchase', 'reactwoo-geo-optimise' ); ?></option>
					<option value="custom_event" <?php selected( $pf_goal_type_auto, 'custom_event' ); ?>><?php esc_html_e( 'Custom event', 'reactwoo-geo-optimise' ); ?></option>
				</select>
				<p class="rwgo-hint"><?php esc_html_e( 'This automatic goal type is counted toward total conversions. Defined goals (above) are preferred when you need per-variant mapping; labels are display-only.', 'reactwoo-geo-optimise' ); ?></p>
			</div>
		</div>
	</section>

	<section class="rwgo-card rwgo-section rwgo-section--publish rwgo-section--eo-7" aria-labelledby="rwgo-sec-publish">
		<h2 class="rwgo-section__title" id="rwgo-sec-publish"><?php echo esc_html( $rwgo_is_edit ? __( 'Save', 'reactwoo-geo-optimise' ) : __( '7. Publish', 'reactwoo-geo-optimise' ) ); ?></h2>
		<?php if ( $rwgo_is_edit ) : ?>
			<p class="rwgo-hint"><?php esc_html_e( 'Changes apply to future visits. Existing visitor assignments stay sticky unless you relink Variant B.', 'reactwoo-geo-optimise' ); ?></p>
			<div class="rwgo-btn-row rwgo-btn-row--wrap">
				<?php submit_button( __( 'Save changes', 'reactwoo-geo-optimise' ), 'primary', 'submit', false, array( 'class' => 'button button-primary rwgo-btn rwgo-btn--primary' ) ); ?>
				<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-tests' ) ); ?>"><?php esc_html_e( 'Back to Tests', 'reactwoo-geo-optimise' ); ?></a>
				<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-reports#exp-' . (int) ( $rwgo_prefill['experiment_id'] ?? 0 ) ) ); ?>"><?php esc_html_e( 'View report', 'reactwoo-geo-optimise' ); ?></a>
			</div>
		<?php else : ?>
			<div class="rwgo-callout rwgo-callout--muted">
				<p class="rwgo-callout__title"><?php esc_html_e( 'When you publish this test:', 'reactwoo-geo-optimise' ); ?></p>
				<ul class="rwgo-checklist">
					<li><?php esc_html_e( 'Control (A) uses your selected source content.', 'reactwoo-geo-optimise' ); ?></li>
					<li><?php esc_html_e( 'Variant B is created or linked using the option above.', 'reactwoo-geo-optimise' ); ?></li>
					<li><?php esc_html_e( 'Visitors are split between versions automatically.', 'reactwoo-geo-optimise' ); ?></li>
					<li><?php esc_html_e( 'Returning visitors stay on the same version when they come back.', 'reactwoo-geo-optimise' ); ?></li>
				</ul>
			</div>
			<?php if ( $rwgo_require_confirm ) : ?>
				<div id="rwgo-confirm-wrap" class="rwgo-confirm-wrap rwgo-field">
					<input type="hidden" name="rwgo_confirm_publish" value="0" />
					<label class="rwgo-checkbox-line">
						<input type="checkbox" id="rwgo_confirm_publish" name="rwgo_confirm_publish" value="1" />
						<?php esc_html_e( 'I confirm the audience, success metric, and content choices for this test.', 'reactwoo-geo-optimise' ); ?>
					</label>
				</div>
			<?php endif; ?>
			<p class="rwgo-hint"><?php esc_html_e( 'After publishing, edit both versions from the Tests screen and track results in Reports.', 'reactwoo-geo-optimise' ); ?></p>
			<div class="rwgo-cta-row">
				<?php submit_button( __( 'Publish test', 'reactwoo-geo-optimise' ), 'primary rwgo-btn-primary', 'submit', false ); ?>
			</div>
		<?php endif; ?>
	</section>
</form>
<script type="application/json" id="rwgo-test-form-goals-config"><?php
echo wp_json_encode(
	array(
		'restUrl'            => esc_url_raw( rest_url( 'rwgo/v1/defined-goals' ) ),
		'nonce'              => wp_create_nonce( 'wp_rest' ),
		'initialDefinedJson' => $pf_defined_json,
		'initialMode'        => $pf_goal_sel_mode,
	)
);
?></script>
<?php if ( ! $rwgo_is_edit ) : ?>
<script type="text/javascript">
(function () {
	var catalog = <?php echo wp_json_encode( $rwgo_catalog ); ?>;
	var typeSelect = document.getElementById('rwgo_test_type');
	var sourceSelect = document.getElementById('rwgo_source_page');
	var variantSelect = document.getElementById('rwgo_variant_b_page');
	var hintEl = document.getElementById('rwgo-test-type-hint');
	var sourceAfter = document.getElementById('rwgo-source-after');
	var countriesWrap = document.getElementById('rwgo-countries-wrap');
	var variantExistingWrap = document.getElementById('rwgo-variant-b-existing-wrap');
	var modeRadios = document.querySelectorAll('.rwgo-variant-mode');
	var hints = {
		page_ab: <?php echo wp_json_encode( __( 'Best for comparing two versions of a normal WordPress page.', 'reactwoo-geo-optimise' ) ); ?>,
		elementor_page: <?php echo wp_json_encode( __( 'Best when the page is primarily built with Elementor. If no Elementor entries exist, all pages and posts are listed.', 'reactwoo-geo-optimise' ) ); ?>,
		gutenberg_page: <?php echo wp_json_encode( __( 'Best for the block editor. When needed, all pages and posts are listed.', 'reactwoo-geo-optimise' ) ); ?>,
		woo_product: <?php echo wp_json_encode( __( 'Best for comparing product experiences and store goals (add to cart, purchase).', 'reactwoo-geo-optimise' ) ); ?>,
		custom_php: <?php echo wp_json_encode( __( 'Use when you need a flexible or custom setup; more content types are available.', 'reactwoo-geo-optimise' ) ); ?>
	};
	function fillSelect(sel, items) {
		var keep = sel.value;
		sel.innerHTML = '';
		var opt0 = document.createElement('option');
		opt0.value = '';
		opt0.textContent = <?php echo wp_json_encode( __( '— Select —', 'reactwoo-geo-optimise' ) ); ?>;
		sel.appendChild(opt0);
		var hasKeep = false;
		items.forEach(function (row) {
			var o = document.createElement('option');
			o.value = String(row.id);
			o.textContent = row.text;
			sel.appendChild(o);
			if (keep && String(row.id) === String(keep)) { hasKeep = true; }
		});
		if (hasKeep) { sel.value = keep; }
	}
	function fillVariantB(items, excludeId) {
		var keep = variantSelect.value;
		variantSelect.innerHTML = '';
		var opt0 = document.createElement('option');
		opt0.value = '';
		opt0.textContent = <?php echo wp_json_encode( __( '— Select —', 'reactwoo-geo-optimise' ) ); ?>;
		variantSelect.appendChild(opt0);
		var hasKeep = false;
		items.forEach(function (row) {
			if (excludeId && String(row.id) === String(excludeId)) { return; }
			var o = document.createElement('option');
			o.value = String(row.id);
			o.textContent = row.text;
			variantSelect.appendChild(o);
			if (keep && String(row.id) === String(keep)) { hasKeep = true; }
		});
		if (hasKeep) { variantSelect.value = keep; }
	}
	function currentType() { return typeSelect ? typeSelect.value : 'page_ab'; }
	function refreshLists() {
		var t = currentType();
		var items = catalog[t] || [];
		fillSelect(sourceSelect, items);
		var sid = sourceSelect.value;
		fillVariantB(items, sid);
		if (hintEl) { hintEl.textContent = hints[t] || ''; }
		if (sourceAfter) {
			if (sid) {
				sourceAfter.hidden = false;
				sourceAfter.textContent = <?php echo wp_json_encode( __( 'Selected content will be used as Control (A). Choose how Variant B is created in the next section.', 'reactwoo-geo-optimise' ) ); ?>;
			} else {
				sourceAfter.hidden = true;
				sourceAfter.textContent = '';
			}
		}
		syncVariantUi();
	}
	function syncVariantUi() {
		var mode = 'duplicate';
		modeRadios.forEach(function (r) { if (r.checked) mode = r.value; });
		if (variantExistingWrap) { variantExistingWrap.hidden = mode !== 'existing'; }
		if (variantSelect) { variantSelect.required = mode === 'existing'; }
		if (mode === 'existing') {
			var sid = sourceSelect.value;
			fillVariantB(catalog[currentType()] || [], sid);
		}
	}
	if (typeSelect) {
		typeSelect.addEventListener('change', function () {
			if (sourceSelect) { sourceSelect.value = ''; }
			refreshLists();
		});
	}
	if (sourceSelect) {
		sourceSelect.addEventListener('change', function () {
			var sid = sourceSelect.value;
			if (sourceAfter) {
				if (sid) {
					sourceAfter.hidden = false;
					sourceAfter.textContent = <?php echo wp_json_encode( __( 'Selected content will be used as Control (A). Choose how Variant B is created in the next section.', 'reactwoo-geo-optimise' ) ); ?>;
				} else { sourceAfter.hidden = true; }
			}
			var mode = 'duplicate';
			modeRadios.forEach(function (r) { if (r.checked) mode = r.value; });
			if (mode === 'existing') { fillVariantB(catalog[currentType()] || [], sid); }
		});
	}
	modeRadios.forEach(function (r) { r.addEventListener('change', syncVariantUi); });
	var radios = document.querySelectorAll('.rwgo-winner-mode');
	var wrap = document.getElementById('rwgo-goal-wrap');
	var confirmWrap = document.getElementById('rwgo-confirm-wrap');
	var confirmCb = document.getElementById('rwgo_confirm_publish');
	function syncGoal() {
		var traffic = false;
		radios.forEach(function (r) { if (r.checked && r.value === 'traffic_only') traffic = true; });
		if (wrap) wrap.style.display = traffic ? 'none' : '';
		var sel = document.getElementById('rwgo_goal_type');
		if (sel) sel.disabled = traffic;
		if (confirmWrap) confirmWrap.style.display = traffic ? 'none' : '';
		if (confirmCb) confirmCb.required = !traffic && !!confirmWrap;
	}
	radios.forEach(function (r) { r.addEventListener('change', syncGoal); });
	syncGoal();
	var tm = document.getElementById('rwgo_targeting_mode');
	function syncCountries() {
		if (!countriesWrap || !tm) return;
		countriesWrap.style.display = tm.value === 'countries' ? '' : 'none';
	}
	if (tm) { tm.addEventListener('change', syncCountries); syncCountries(); }
	refreshLists();
})();
</script>
<?php else : ?>
<script type="text/javascript">
(function () {
	var catalog = <?php echo wp_json_encode( $rwgo_catalog ); ?>;
	var fixedType = <?php echo wp_json_encode( $pf_test_type ); ?>;
	var sourceId = <?php echo (int) $pf_source_id; ?>;
	var currentVarB = <?php echo (int) $pf_variant_b_id; ?>;
	var variantSelect = document.getElementById('rwgo_variant_b_page_edit');
	var countriesWrap = document.getElementById('rwgo-countries-wrap');
	var editWrap = document.getElementById('rwgo-edit-variant-b-wrap');
	var actionRadios = document.querySelectorAll('.rwgo-variant-edit-action');
	function fillVariantEdit() {
		if (!variantSelect) return;
		var items = catalog[fixedType] || [];
		var keep = variantSelect.value;
		variantSelect.innerHTML = '';
		var opt0 = document.createElement('option');
		opt0.value = '';
		opt0.textContent = <?php echo wp_json_encode( __( '— Select —', 'reactwoo-geo-optimise' ) ); ?>;
		variantSelect.appendChild(opt0);
		items.forEach(function (row) {
			if (String(row.id) === String(sourceId)) return;
			var o = document.createElement('option');
			o.value = String(row.id);
			o.textContent = row.text;
			variantSelect.appendChild(o);
		});
		if (keep) {
			variantSelect.value = keep;
		} else if (currentVarB) {
			variantSelect.value = String(currentVarB);
		}
	}
	function syncVariantEdit() {
		var mode = 'keep';
		actionRadios.forEach(function (r) { if (r.checked) mode = r.value; });
		if (editWrap) {
			editWrap.hidden = mode !== 'replace_existing';
		}
		if (variantSelect) {
			variantSelect.required = mode === 'replace_existing';
		}
		if (mode === 'replace_existing') fillVariantEdit();
	}
	actionRadios.forEach(function (r) { r.addEventListener('change', syncVariantEdit); });
	syncVariantEdit();
	var radios = document.querySelectorAll('#rwgo-edit-test-form .rwgo-winner-mode');
	var wrap = document.getElementById('rwgo-goal-wrap');
	var goalSel = document.getElementById('rwgo_goal_type');
	function syncGoalEdit() {
		var traffic = false;
		radios.forEach(function (r) { if (r.checked && r.value === 'traffic_only') traffic = true; });
		if (wrap) wrap.style.display = traffic ? 'none' : '';
		if (goalSel) goalSel.disabled = traffic;
	}
	radios.forEach(function (r) { r.addEventListener('change', syncGoalEdit); });
	syncGoalEdit();
	var formEl = document.getElementById('rwgo-edit-test-form');
	if (formEl) {
		formEl.addEventListener('submit', function () {
			if (goalSel) goalSel.disabled = false;
		});
	}
	var tm = document.getElementById('rwgo_targeting_mode');
	if (tm && countriesWrap) {
		tm.addEventListener('change', function () {
			countriesWrap.style.display = tm.value === 'countries' ? '' : 'none';
		});
	}
})();
</script>
<?php endif; ?>
