<?php
/**
 * Site-wide test screen markup.
 *
 * @package ReactWooGeoOptimise
 *
 * @var array<string, mixed> $view
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rwgo_sw_combo' ) ) {
	/**
	 * Searchable selector. The panel is moved to document.body while open.
	 *
	 * @param array<string, mixed> $choice Selected post.
	 * @param array<string, mixed> $args   name, kind, placeholder, label, chip.
	 * @return void
	 */
	function rwgo_sw_combo( array $choice, array $args ) {
		static $n = 0;
		$n++;
		$id      = 'rwgo-sw-combo-' . $n;
		$list_id = $id . '-list';
		$name    = isset( $args['name'] ) ? (string) $args['name'] : '';
		$kind    = isset( $args['kind'] ) ? (string) $args['kind'] : 'page';
		$placeholder = isset( $args['placeholder'] ) ? (string) $args['placeholder'] : __( 'Search', 'reactwoo-geo-optimise' );
		$label   = isset( $args['label'] ) ? (string) $args['label'] : $placeholder;
		$chip    = ! empty( $args['chip'] );
		$empty   = empty( $choice['id'] );
		$title   = $empty ? $placeholder : (string) $choice['title'];
		$meta    = $empty ? '' : (string) $choice['meta'];
		$full    = $empty ? $placeholder : trim( $title . ( '' !== $meta ? ' ' . $meta : '' ) );
		echo '<div class="rwgo-sw-combo' . ( $empty ? ' is-empty' : '' ) . '" data-kind="' . esc_attr( $kind ) . '" data-placeholder="' . esc_attr( $placeholder ) . '" data-status="' . esc_attr( (string) $choice['status'] ) . '" data-preview="' . esc_attr( (string) $choice['preview_url'] ) . '" data-hint="' . esc_attr( (string) $choice['hint'] ) . '"' . ( $chip ? ' data-chip="1"' : '' ) . '>';
		if ( ! $chip && '' !== $name ) {
			echo '<input type="hidden" class="rwgo-sw-combo__value" name="' . esc_attr( $name ) . '" value="' . esc_attr( $empty ? '' : (string) (int) $choice['id'] ) . '" />';
		}
		$button_class = $chip ? 'rwgo-sw-chip-add' : 'rwgo-sw-combo__button';
		echo '<button type="button" class="' . esc_attr( $button_class ) . '" id="' . esc_attr( $id ) . '" aria-haspopup="listbox" aria-expanded="false" aria-controls="' . esc_attr( $list_id ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $full ) . '">';
		if ( $chip ) {
			echo '<span class="rwgo-sw-add__plus" aria-hidden="true">+</span>';
			echo '<span>' . esc_html( $placeholder ) . '</span>';
		} else {
			echo '<span class="rwgo-sw-combo__text">';
			echo '<span class="rwgo-sw-combo__title">' . esc_html( $title ) . '</span>';
			if ( '' !== $meta ) {
				echo '<span class="rwgo-sw-combo__meta">' . esc_html( $meta ) . '</span>';
			}
			echo '</span>';
			echo '<span class="rwgo-sw-combo__chevron" aria-hidden="true">' . $GLOBALS['rwgo_sw_icons']['chevron'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</button>';
		echo '<div class="rwgo-sw-panel" hidden>';
		echo '<label class="rwgo-sw-panel__search">';
		echo '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		echo '<span class="rwgo-sw-panel__icon" aria-hidden="true">' . $GLOBALS['rwgo_sw_icons']['search'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<input type="search" class="rwgo-sw-panel__input" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" />';
		echo '</label>';
		echo '<ul class="rwgo-sw-panel__list" id="' . esc_attr( $list_id ) . '" role="listbox"></ul>';
		if ( 'variant' === $kind ) {
			echo '<button type="button" class="rwgo-sw-panel__duplicate">' . $GLOBALS['rwgo_sw_icons']['plus'] . '<span>' . esc_html__( 'Duplicate this page as variant', 'reactwoo-geo-optimise' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></div>';
	}
}

if ( ! function_exists( 'rwgo_sw_badge' ) ) {
	/**
	 * @param array<string, mixed> $choice Choice.
	 * @return void
	 */
	function rwgo_sw_badge( array $choice ) {
		$status = isset( $choice['status'] ) ? (string) $choice['status'] : '';
		$label  = isset( $choice['status_label'] ) ? (string) $choice['status_label'] : '';
		$class  = '' === $status ? 'rwgo-sw-badge rwgo-sw-badge--empty' : 'rwgo-sw-badge rwgo-sw-badge--' . sanitize_html_class( $status );
		echo '<span class="rwgo-sw-match__status"><span class="' . esc_attr( $class ) . '" data-badge>' . esc_html( $label ) . '</span></span>';
	}
}

$GLOBALS['rwgo_sw_icons'] = isset( $view['icons'] ) && is_array( $view['icons'] ) ? $view['icons'] : array();
$icons                     = $GLOBALS['rwgo_sw_icons'];
$status                    = (string) $view['status'];
$status_label              = 'active' === $status ? __( 'Active', 'reactwoo-geo-optimise' ) : ( 'ended' === $status ? __( 'Ended', 'reactwoo-geo-optimise' ) : __( 'Paused', 'reactwoo-geo-optimise' ) );
$empty                     = ! empty( $view['empty_pages'] );
$empty_choice              = array(
	'id'           => 0,
	'title'        => '',
	'slug'         => '',
	'status'       => '',
	'status_label' => '',
	'hint'         => '',
	'meta'         => '',
	'preview_url'  => '',
);
$form_action = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '';
?>
<div class="rwgo-sw" id="rwgo-sw" data-status="<?php echo esc_attr( $status ); ?>">
	<header class="rwgo-sw-head">
		<p class="rwgo-sw-crumb">
			<?php if ( ! empty( $view['optimise_url'] ) ) : ?>
				<a href="<?php echo esc_url( (string) $view['optimise_url'] ); ?>"><?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-optimise' ); ?></a>
			<?php else : ?>
				<?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-optimise' ); ?>
			<?php endif; ?>
			<span aria-hidden="true"> / </span>
			<?php if ( ! empty( $view['experiences_url'] ) ) : ?>
				<a href="<?php echo esc_url( (string) $view['experiences_url'] ); ?>"><?php esc_html_e( 'Experiences', 'reactwoo-geo-optimise' ); ?></a>
			<?php else : ?>
				<?php esc_html_e( 'Experiences', 'reactwoo-geo-optimise' ); ?>
			<?php endif; ?>
			<span aria-hidden="true"> / </span>
			<span><?php esc_html_e( 'Site-wide test', 'reactwoo-geo-optimise' ); ?></span>
		</p>
		<div class="rwgo-sw-titleline">
			<h1><?php esc_html_e( 'Site-wide design test', 'reactwoo-geo-optimise' ); ?></h1>
			<span class="rwgo-sw-pill" data-pill="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
		</div>
	</header>

	<form id="rwgo-sw-form" class="rwgo-sw-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'rwgo_save_sitewide' ); ?>
		<input type="hidden" name="action" value="rwgo_save_sitewide" />
		<input type="hidden" name="rwgo_sw_ui" value="1" />
		<input type="hidden" name="rwgo_sw_revision" value="<?php echo esc_attr( (string) (int) $view['revision'] ); ?>" />
		<input type="hidden" name="rwgo_sw_bulk_scope" value="selected" />
		<?php if ( ! empty( $view['map_merge'] ) ) : ?>
			<input type="hidden" name="rwgo_sw_map_merge" value="1" />
		<?php endif; ?>

		<div class="rwgo-sw-bar rwgo-sw-bar--top">
			<div class="rwgo-sw-bar__state">
				<p class="rwgo-sw-clean"><?php esc_html_e( 'No unsaved changes', 'reactwoo-geo-optimise' ); ?></p>
				<p class="rwgo-sw-dirty"><span class="rwgo-sw-dirty__dot" aria-hidden="true"></span><?php esc_html_e( 'Unsaved changes', 'reactwoo-geo-optimise' ); ?></p>
			</div>
			<div class="rwgo-sw-bar__actions">
				<?php if ( ! empty( $view['preview_control'] ) ) : ?>
					<a class="rwgo-sw-btn rwgo-sw-btn--ghost" href="<?php echo esc_url( (string) $view['preview_control'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Preview control', 'reactwoo-geo-optimise' ); ?></span></a>
				<?php endif; ?>
				<?php if ( ! empty( $view['preview_variant'] ) ) : ?>
					<a class="rwgo-sw-btn rwgo-sw-btn--ghost" href="<?php echo esc_url( (string) $view['preview_variant'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Preview variant B', 'reactwoo-geo-optimise' ); ?></span></a>
				<?php endif; ?>
				<button type="submit" class="rwgo-sw-btn rwgo-sw-btn--primary"><?php esc_html_e( 'Save site-wide test', 'reactwoo-geo-optimise' ); ?></button>
			</div>
		</div>

		<div class="rwgo-sw-layout">
			<nav class="rwgo-sw-nav" aria-label="<?php esc_attr_e( 'Site-wide test sections', 'reactwoo-geo-optimise' ); ?>">
				<a href="#rwgo-sw-overview" class="is-active"><span class="rwgo-sw-nav__num">1</span><span><?php esc_html_e( 'Overview and status', 'reactwoo-geo-optimise' ); ?></span></a>
				<a href="#rwgo-sw-coverage"><span class="rwgo-sw-nav__num">2</span><span><?php esc_html_e( 'Coverage and exclusions', 'reactwoo-geo-optimise' ); ?></span></a>
				<a href="#rwgo-sw-pages"><span class="rwgo-sw-nav__num">3</span><span><?php esc_html_e( 'Page variants', 'reactwoo-geo-optimise' ); ?></span></a>
				<a href="#rwgo-sw-header"><span class="rwgo-sw-nav__num">4</span><span><?php esc_html_e( 'Header and footer', 'reactwoo-geo-optimise' ); ?></span></a>
				<a href="#rwgo-sw-products"><span class="rwgo-sw-nav__num">5</span><span><?php esc_html_e( 'Product templates', 'reactwoo-geo-optimise' ); ?></span></a>
				<a href="#rwgo-sw-goals"><span class="rwgo-sw-nav__num">6</span><span><?php esc_html_e( 'Goals and tracking', 'reactwoo-geo-optimise' ); ?></span></a>
			</nav>

			<div class="rwgo-sw-main">
				<?php if ( ! empty( $view['notices'] ) && is_array( $view['notices'] ) ) : ?>
					<div class="rwgo-sw-notices">
						<?php foreach ( $view['notices'] as $notice ) : ?>
							<?php if ( ! is_array( $notice ) ) { continue; } ?>
							<div class="rwgo-sw-notice rwgo-sw-notice--<?php echo esc_attr( isset( $notice['type'] ) ? (string) $notice['type'] : 'info' ); ?>" role="status">
								<?php
								if ( ! empty( $notice['html'] ) ) {
									echo wp_kses( (string) $notice['html'], array( 'a' => array( 'href' => array() ) ) );
								} else {
									echo esc_html( isset( $notice['text'] ) ? (string) $notice['text'] : '' );
								}
								?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $view['rejected_html'] ) ) : ?>
					<div class="rwgo-sw-rejected"><?php echo $view['rejected_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $view['audit_html'] ) ) : ?>
					<div class="rwgo-sw-audit"><?php echo $view['audit_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>

				<section class="rwgo-sw-card" id="rwgo-sw-overview">
					<div class="rwgo-sw-card__head">
						<h2><?php esc_html_e( '1. Overview and status', 'reactwoo-geo-optimise' ); ?></h2>
						<p class="rwgo-sw-lede" data-overview-lede>
							<?php
							echo esc_html(
								'active' === $status
									? __( 'Turn the test on when the variants are ready. Saving does not publish drafts.', 'reactwoo-geo-optimise' )
									: __( 'Keep the test paused while you set up the first page matches.', 'reactwoo-geo-optimise' )
							);
							?>
						</p>
					</div>
					<div class="rwgo-sw-card__body">
						<div class="rwgo-sw-overview">
							<div class="rwgo-sw-field">
								<span class="rwgo-sw-label" id="rwgo-sw-status-label"><?php esc_html_e( 'Status', 'reactwoo-geo-optimise' ); ?></span>
								<div class="rwgo-sw-status">
									<button type="button" class="rwgo-sw-switch" role="switch" aria-checked="<?php echo 'active' === $status ? 'true' : 'false'; ?>" aria-labelledby="rwgo-sw-status-label">
										<span class="rwgo-sw-switch__knob"></span>
									</button>
									<span class="rwgo-sw-status__word" data-status-word><?php echo esc_html( $status_label ); ?></span>
									<label class="screen-reader-text" for="rwgo_sw_status"><?php esc_html_e( 'Status', 'reactwoo-geo-optimise' ); ?></label>
									<select name="rwgo_sw_status" id="rwgo_sw_status" class="rwgo-sw-status__select">
										<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'reactwoo-geo-optimise' ); ?></option>
										<option value="paused" <?php selected( $status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'reactwoo-geo-optimise' ); ?></option>
										<option value="ended" <?php selected( $status, 'ended' ); ?>><?php esc_html_e( 'Ended', 'reactwoo-geo-optimise' ); ?></option>
									</select>
								</div>
								<p class="rwgo-sw-help"><?php esc_html_e( 'Paused keeps visitors on the original pages.', 'reactwoo-geo-optimise' ); ?></p>
							</div>
							<div class="rwgo-sw-field">
								<label class="rwgo-sw-label" for="rwgo_sw_key">
									<?php esc_html_e( 'Experiment key', 'reactwoo-geo-optimise' ); ?>
									<button type="button" class="rwgo-sw-info" aria-describedby="rwgo-sw-key-help"><?php echo $icons['info']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php esc_html_e( 'About the experiment key', 'reactwoo-geo-optimise' ); ?></span></button>
								</label>
								<input id="rwgo_sw_key" class="rwgo-sw-input" name="rwgo_sw_key" type="text" value="<?php echo esc_attr( (string) $view['experiment_key'] ); ?>" autocomplete="off" spellcheck="false" />
								<p class="rwgo-sw-help" id="rwgo-sw-key-help"><?php esc_html_e( 'Used in reports. Change this only if you are starting a new test.', 'reactwoo-geo-optimise' ); ?></p>
							</div>
							<div class="rwgo-sw-field">
								<label class="rwgo-sw-label" for="rwgo_sw_days"><?php esc_html_e( 'Cookie lifetime', 'reactwoo-geo-optimise' ); ?></label>
								<span class="rwgo-sw-days">
									<input id="rwgo_sw_days" name="rwgo_sw_days" type="number" min="1" step="1" value="<?php echo esc_attr( (string) (int) $view['cookie_days'] ); ?>" />
									<span><?php esc_html_e( 'days', 'reactwoo-geo-optimise' ); ?></span>
								</span>
								<p class="rwgo-sw-help"><?php esc_html_e( 'How long a visitor stays in the same arm.', 'reactwoo-geo-optimise' ); ?></p>
							</div>
						</div>
						<div class="rwgo-sw-split">
							<span class="rwgo-sw-label" id="rwgo-sw-split-label"><?php esc_html_e( 'Traffic split', 'reactwoo-geo-optimise' ); ?></span>
							<div class="rwgo-sw-split__box">
								<div class="rwgo-sw-split__labels">
									<span data-split-control><?php echo esc_html( sprintf( /* translators: %d: control percent */ __( 'Control %d%%', 'reactwoo-geo-optimise' ), (int) $view['weight_percent'] ) ); ?></span>
									<span data-split-variant><?php echo esc_html( sprintf( /* translators: %d: variant percent */ __( 'Variant B %d%%', 'reactwoo-geo-optimise' ), 100 - (int) $view['weight_percent'] ) ); ?></span>
								</div>
								<input id="rwgo_sw_weight" name="rwgo_sw_weight" type="range" min="0" max="100" step="1" value="<?php echo esc_attr( (string) (int) $view['weight_percent'] ); ?>" aria-labelledby="rwgo-sw-split-label" style="--rwgo-split: <?php echo esc_attr( (string) (int) $view['weight_percent'] ); ?>%;" />
								<p class="rwgo-sw-help"><?php esc_html_e( 'Control is the share of visitors who stay on the original design.', 'reactwoo-geo-optimise' ); ?></p>
							</div>
						</div>
					</div>
				</section>

				<section class="rwgo-sw-card" id="rwgo-sw-coverage">
					<div class="rwgo-sw-card__head">
						<h2><?php esc_html_e( '2. Coverage and exclusions', 'reactwoo-geo-optimise' ); ?></h2>
						<p class="rwgo-sw-lede"><?php esc_html_e( 'Choose which published pages can enter the test, then exclude anything that should always stay on the original.', 'reactwoo-geo-optimise' ); ?></p>
					</div>
					<div class="rwgo-sw-card__body">
						<div class="rwgo-sw-choices" role="radiogroup" aria-label="<?php esc_attr_e( 'Coverage', 'reactwoo-geo-optimise' ); ?>">
							<label class="rwgo-sw-choice">
								<input type="radio" name="rwgo_sw_mapping" value="explicit" <?php checked( (string) $view['mapping_mode'], 'explicit' ); ?> />
								<span class="rwgo-sw-choice__copy">
									<span class="rwgo-sw-choice__title"><?php esc_html_e( 'Only pages in the map', 'reactwoo-geo-optimise' ); ?></span>
									<span class="rwgo-sw-choice__text"><?php esc_html_e( 'Only the page matches below are tested. Everything else stays on the original.', 'reactwoo-geo-optimise' ); ?></span>
								</span>
							</label>
							<label class="rwgo-sw-choice">
								<input type="radio" name="rwgo_sw_mapping" value="all_published" <?php checked( (string) $view['mapping_mode'], 'all_published' ); ?> />
								<span class="rwgo-sw-choice__copy">
									<span class="rwgo-sw-choice__title"><?php esc_html_e( 'All published pages', 'reactwoo-geo-optimise' ); ?></span>
									<span class="rwgo-sw-choice__text"><?php esc_html_e( 'Any published page can be tested, except the exclusions you set here.', 'reactwoo-geo-optimise' ); ?></span>
								</span>
							</label>
						</div>
						<label class="rwgo-sw-checkline">
							<input type="checkbox" name="rwgo_sw_exclude_system" value="1" <?php checked( ! empty( $view['exclude_system'] ) ); ?> />
							<span>
								<span class="rwgo-sw-checkline__title"><?php esc_html_e( 'Exclude system pages', 'reactwoo-geo-optimise' ); ?></span>
								<span class="rwgo-sw-help"><?php esc_html_e( 'Shop, cart, checkout, my account, WooCommerce terms, and the posts page stay on the original.', 'reactwoo-geo-optimise' ); ?></span>
							</span>
						</label>
						<div class="rwgo-sw-field">
							<span class="rwgo-sw-label"><?php esc_html_e( 'Also exclude these pages', 'reactwoo-geo-optimise' ); ?></span>
							<div class="rwgo-sw-chips" data-chip-list data-name="rwgo_sw_exclude_ids[]">
								<ul class="rwgo-sw-chips__list">
									<?php foreach ( $view['exclude_pages'] as $page ) : ?>
										<?php if ( empty( $page['id'] ) ) { continue; } ?>
										<li class="rwgo-sw-chip">
											<span><?php echo esc_html( (string) $page['title'] ); ?></span>
											<button type="button" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: page title */ __( 'Remove %s', 'reactwoo-geo-optimise' ), (string) $page['title'] ) ); ?>">&times;</button>
											<input type="hidden" name="rwgo_sw_exclude_ids[]" value="<?php echo esc_attr( (string) (int) $page['id'] ); ?>" />
										</li>
									<?php endforeach; ?>
								</ul>
								<?php
								rwgo_sw_combo(
									$empty_choice,
									array(
										'kind'        => 'exclude',
										'placeholder' => __( 'Add page', 'reactwoo-geo-optimise' ),
										'label'       => __( 'Add an excluded page', 'reactwoo-geo-optimise' ),
										'chip'        => true,
									)
								);
								?>
							</div>
						</div>
						<div class="rwgo-sw-field">
							<span class="rwgo-sw-label"><?php esc_html_e( 'Pages published later', 'reactwoo-geo-optimise' ); ?></span>
							<div class="rwgo-sw-choices" role="radiogroup" aria-label="<?php esc_attr_e( 'Pages published later', 'reactwoo-geo-optimise' ); ?>">
								<label class="rwgo-sw-choice">
									<input type="radio" name="rwgo_sw_future" value="skip" <?php checked( (string) $view['future_pages'], 'skip' ); ?> />
									<span class="rwgo-sw-choice__copy">
										<span class="rwgo-sw-choice__title"><?php esc_html_e( 'Skip until I add them', 'reactwoo-geo-optimise' ); ?></span>
										<span class="rwgo-sw-choice__text"><?php esc_html_e( 'New pages stay out of the test until you add a match.', 'reactwoo-geo-optimise' ); ?></span>
									</span>
								</label>
								<label class="rwgo-sw-choice">
									<input type="radio" name="rwgo_sw_future" value="include" <?php checked( (string) $view['future_pages'], 'include' ); ?> />
									<span class="rwgo-sw-choice__copy">
										<span class="rwgo-sw-choice__title"><?php esc_html_e( 'Include automatically', 'reactwoo-geo-optimise' ); ?></span>
										<span class="rwgo-sw-choice__text"><?php esc_html_e( 'New published pages join the coverage rules above.', 'reactwoo-geo-optimise' ); ?></span>
									</span>
								</label>
							</div>
						</div>
					</div>
				</section>

				<section class="rwgo-sw-card" id="rwgo-sw-pages">
					<div class="rwgo-sw-card__head">
						<h2><?php esc_html_e( '3. Page variants', 'reactwoo-geo-optimise' ); ?></h2>
						<p class="rwgo-sw-lede">
							<?php
							echo esc_html(
								$empty
									? __( 'Start with one original page, then add more rows as you go.', 'reactwoo-geo-optimise' )
									: __( 'Match an original page to its variant. Search by title or slug. No IDs to type.', 'reactwoo-geo-optimise' )
							);
							?>
						</p>
					</div>
					<div class="rwgo-sw-card__body">
						<?php if ( $empty ) : ?>
							<div class="rwgo-sw-empty">
								<strong><?php esc_html_e( 'No page matches yet', 'reactwoo-geo-optimise' ); ?></strong>
								<span><?php esc_html_e( 'Choose an original page, then pick an existing variant or duplicate that page.', 'reactwoo-geo-optimise' ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $view['map_merge'] ) ) : ?>
							<p class="rwgo-sw-help rwgo-sw-help--block">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: stored pair count */
										__( '%d page matches are already stored. Add a row to append a pair. The full list stays out of this editor.', 'reactwoo-geo-optimise' ),
										(int) $view['map_count']
									)
								);
								?>
							</p>
						<?php endif; ?>
						<div class="rwgo-sw-rows" data-rows="match">
							<?php
							$rows = $view['matches'];
							if ( $empty ) {
								$rows = array(
									array(
										'index'   => 0,
										'source'  => $empty_choice,
										'variant' => $empty_choice,
									),
								);
							}
							foreach ( $rows as $row ) :
								$index  = (int) $row['index'];
								$source = $row['source'];
								$variant = $row['variant'];
								?>
								<div class="rwgo-sw-match">
									<label class="rwgo-sw-tick">
										<input type="checkbox" name="rwgo_sw_bulk_pick[]" value="<?php echo esc_attr( empty( $source['id'] ) ? '' : (string) (int) $source['id'] ); ?>" <?php disabled( empty( $source['id'] ) ); ?> />
										<span class="screen-reader-text"><?php esc_html_e( 'Select this page for variant creation', 'reactwoo-geo-optimise' ); ?></span>
									</label>
									<div class="rwgo-sw-match__source">
										<?php
										rwgo_sw_combo(
											$source,
											array(
												'name'        => 'rwgo_sw_match[' . $index . '][source]',
												'kind'        => 'page',
												'placeholder' => __( 'Search for a page', 'reactwoo-geo-optimise' ),
												'label'       => __( 'Original page', 'reactwoo-geo-optimise' ),
											)
										);
										?>
									</div>
									<span class="rwgo-sw-arrow" aria-hidden="true"><?php echo $icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<div class="rwgo-sw-match__variant">
										<?php
										rwgo_sw_combo(
											$variant,
											array(
												'name'        => 'rwgo_sw_match[' . $index . '][variant]',
												'kind'        => 'variant',
												'placeholder' => __( 'Select a variant', 'reactwoo-geo-optimise' ),
												'label'       => __( 'Variant page', 'reactwoo-geo-optimise' ),
											)
										);
										?>
									</div>
									<?php rwgo_sw_badge( $variant ); ?>
									<a class="rwgo-sw-iconbtn" data-preview href="<?php echo esc_url( (string) $variant['preview_url'] ); ?>" target="_blank" rel="noopener noreferrer" <?php echo empty( $variant['preview_url'] ) ? 'hidden' : ''; ?> aria-label="<?php esc_attr_e( 'Preview variant', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
									<button type="button" class="rwgo-sw-iconbtn rwgo-sw-iconbtn--remove" data-remove aria-label="<?php esc_attr_e( 'Remove page match', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['trash']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="rwgo-sw-add" data-add="match">
							<span class="rwgo-sw-add__plus" aria-hidden="true">+</span>
							<span><?php esc_html_e( 'Add page match', 'reactwoo-geo-optimise' ); ?></span>
						</button>
						<div class="rwgo-sw-warnings" data-draft-warnings <?php echo empty( $view['page_warnings'] ) ? 'hidden' : ''; ?>>
							<?php foreach ( $view['page_warnings'] as $warning ) : ?>
								<p><?php echo $icons['warning']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( (string) $warning ); ?></span></p>
							<?php endforeach; ?>
						</div>
						<div class="rwgo-sw-bulkline">
							<button type="submit" class="rwgo-sw-btn rwgo-sw-btn--small" name="rwgo_sw_do_bulk" value="1"><?php esc_html_e( 'Create variants for selected pages', 'reactwoo-geo-optimise' ); ?></button>
							<p class="rwgo-sw-help"><?php esc_html_e( 'Duplicates the selected originals and fills the variant side of each row.', 'reactwoo-geo-optimise' ); ?></p>
						</div>
					</div>
				</section>

				<section class="rwgo-sw-card" id="rwgo-sw-header">
					<div class="rwgo-sw-card__head">
						<h2><?php esc_html_e( '4. Header and footer', 'reactwoo-geo-optimise' ); ?></h2>
						<p class="rwgo-sw-lede">
							<?php
							echo esc_html(
								( empty( $view['header_set'] ) && empty( $view['footer_set'] ) )
									? __( 'Optional. Add a header or footer pair only if those templates should change with the arm.', 'reactwoo-geo-optimise' )
									: __( 'Pick an Elementor template for each arm. Leave a side blank if that location should not change.', 'reactwoo-geo-optimise' )
							);
							?>
						</p>
					</div>
					<div class="rwgo-sw-card__body" data-theme-pairs>
						<?php
						$pairs = array(
							'header' => array(
								'label' => __( 'Header', 'reactwoo-geo-optimise' ),
								'set'   => ! empty( $view['header_set'] ),
								'left'  => 'rwgo_sw_header_a',
								'right' => 'rwgo_sw_header_b',
								'data'  => $view['header'],
								'kind'  => 'header',
							),
							'footer' => array(
								'label' => __( 'Footer', 'reactwoo-geo-optimise' ),
								'set'   => ! empty( $view['footer_set'] ),
								'left'  => 'rwgo_sw_footer_a',
								'right' => 'rwgo_sw_footer_b',
								'data'  => $view['footer'],
								'kind'  => 'footer',
							),
						);
						foreach ( $pairs as $key => $pair ) :
							if ( empty( $pair['set'] ) ) {
								continue;
							}
							?>
							<div class="rwgo-sw-location" data-location="<?php echo esc_attr( $key ); ?>">
								<h3><?php echo esc_html( (string) $pair['label'] ); ?></h3>
								<div class="rwgo-sw-pair">
									<?php
									rwgo_sw_combo(
										$pair['data']['control'],
										array(
											'name'        => $pair['left'],
											'kind'        => $pair['kind'],
											'placeholder' => __( 'Select a template', 'reactwoo-geo-optimise' ),
											'label'       => sprintf( /* translators: %s: Header or Footer */ __( 'Control %s', 'reactwoo-geo-optimise' ), $pair['label'] ),
										)
									);
									?>
									<span class="rwgo-sw-arrow" aria-hidden="true"><?php echo $icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php
									rwgo_sw_combo(
										$pair['data']['variant'],
										array(
											'name'        => $pair['right'],
											'kind'        => $pair['kind'],
											'placeholder' => __( 'Select a template', 'reactwoo-geo-optimise' ),
											'label'       => sprintf( /* translators: %s: Header or Footer */ __( 'Variant %s', 'reactwoo-geo-optimise' ), $pair['label'] ),
										)
									);
									?>
									<?php rwgo_sw_badge( $pair['data']['variant'] ); ?>
									<a class="rwgo-sw-iconbtn" data-preview href="<?php echo esc_url( (string) $pair['data']['variant']['preview_url'] ); ?>" target="_blank" rel="noopener noreferrer" <?php echo empty( $pair['data']['variant']['preview_url'] ) ? 'hidden' : ''; ?> aria-label="<?php esc_attr_e( 'Preview variant', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
									<button type="button" class="rwgo-sw-iconbtn rwgo-sw-iconbtn--remove" data-remove-location="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Header or Footer */ __( 'Remove %s match', 'reactwoo-geo-optimise' ), $pair['label'] ) ); ?>"><?php echo $icons['trash']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								</div>
							</div>
						<?php endforeach; ?>
						<div class="rwgo-sw-addrow">
							<button type="button" class="rwgo-sw-add" data-add-location="header" <?php echo ! empty( $view['header_set'] ) ? 'hidden' : ''; ?>><span class="rwgo-sw-add__plus" aria-hidden="true">+</span><span><?php esc_html_e( 'Add header match', 'reactwoo-geo-optimise' ); ?></span></button>
							<button type="button" class="rwgo-sw-add" data-add-location="footer" <?php echo ! empty( $view['footer_set'] ) ? 'hidden' : ''; ?>><span class="rwgo-sw-add__plus" aria-hidden="true">+</span><span><?php esc_html_e( 'Add footer match', 'reactwoo-geo-optimise' ); ?></span></button>
							<button type="button" class="rwgo-sw-add" data-add-location="next" <?php echo ( empty( $view['header_set'] ) || empty( $view['footer_set'] ) ) ? 'hidden' : ''; ?>><span class="rwgo-sw-add__plus" aria-hidden="true">+</span><span><?php esc_html_e( 'Add template location', 'reactwoo-geo-optimise' ); ?></span></button>
						</div>
						<?php if ( empty( $view['locations_on'] ) && ( ! empty( $view['header_set'] ) || ! empty( $view['footer_set'] ) ) ) : ?>
							<p class="rwgo-sw-help rwgo-sw-help--block"><?php esc_html_e( 'Elementor Pro theme locations are not active. These templates are saved and are not shown until Elementor Pro is active.', 'reactwoo-geo-optimise' ); ?></p>
						<?php endif; ?>
					</div>
				</section>

				<section class="rwgo-sw-card" id="rwgo-sw-products">
					<div class="rwgo-sw-card__head">
						<h2><?php esc_html_e( '5. Product templates', 'reactwoo-geo-optimise' ); ?></h2>
						<p class="rwgo-sw-lede"><?php esc_html_e( 'For Elementor Theme Builder product bodies. Match a source single-product template to its variant.', 'reactwoo-geo-optimise' ); ?></p>
					</div>
					<div class="rwgo-sw-card__body">
						<div class="rwgo-sw-rows" data-rows="product">
							<?php foreach ( $view['products'] as $row ) : ?>
								<div class="rwgo-sw-pair">
									<?php
									rwgo_sw_combo(
										$row['source'],
										array(
											'name'        => 'rwgo_sw_product[' . (int) $row['index'] . '][source]',
											'kind'        => 'product',
											'placeholder' => __( 'Select a template', 'reactwoo-geo-optimise' ),
											'label'       => __( 'Source product template', 'reactwoo-geo-optimise' ),
										)
									);
									?>
									<span class="rwgo-sw-arrow" aria-hidden="true"><?php echo $icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php
									rwgo_sw_combo(
										$row['variant'],
										array(
											'name'        => 'rwgo_sw_product[' . (int) $row['index'] . '][variant]',
											'kind'        => 'product',
											'placeholder' => __( 'Select a template', 'reactwoo-geo-optimise' ),
											'label'       => __( 'Variant product template', 'reactwoo-geo-optimise' ),
										)
									);
									?>
									<?php rwgo_sw_badge( $row['variant'] ); ?>
									<a class="rwgo-sw-iconbtn" data-preview href="<?php echo esc_url( (string) $row['variant']['preview_url'] ); ?>" target="_blank" rel="noopener noreferrer" <?php echo empty( $row['variant']['preview_url'] ) ? 'hidden' : ''; ?> aria-label="<?php esc_attr_e( 'Preview variant', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
									<button type="button" class="rwgo-sw-iconbtn rwgo-sw-iconbtn--remove" data-remove aria-label="<?php esc_attr_e( 'Remove product template match', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['trash']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<?php if ( empty( $view['products'] ) ) : ?>
							<p class="rwgo-sw-empty rwgo-sw-empty--slim"><span><?php esc_html_e( 'No product template matches yet.', 'reactwoo-geo-optimise' ); ?></span></p>
						<?php endif; ?>
						<button type="button" class="rwgo-sw-add" data-add="product">
							<span class="rwgo-sw-add__plus" aria-hidden="true">+</span>
							<span><?php esc_html_e( 'Add product template match', 'reactwoo-geo-optimise' ); ?></span>
						</button>
					</div>
				</section>

				<section class="rwgo-sw-card" id="rwgo-sw-goals">
					<div class="rwgo-sw-card__head">
						<h2><?php esc_html_e( '6. Goals and tracking', 'reactwoo-geo-optimise' ); ?></h2>
						<p class="rwgo-sw-lede"><?php esc_html_e( 'Count a conversion when a visitor reaches one of these pages, or completes a WooCommerce purchase.', 'reactwoo-geo-optimise' ); ?></p>
					</div>
					<div class="rwgo-sw-card__body">
						<div class="rwgo-sw-field">
							<span class="rwgo-sw-label"><?php esc_html_e( 'Goal pages', 'reactwoo-geo-optimise' ); ?></span>
							<div class="rwgo-sw-chips" data-chip-list data-name="rwgo_sw_goal_ids[]">
								<ul class="rwgo-sw-chips__list">
									<?php foreach ( $view['goals'] as $page ) : ?>
										<?php if ( empty( $page['id'] ) ) { continue; } ?>
										<li class="rwgo-sw-chip">
											<span><?php echo esc_html( (string) $page['title'] ); ?></span>
											<button type="button" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: page title */ __( 'Remove %s', 'reactwoo-geo-optimise' ), (string) $page['title'] ) ); ?>">&times;</button>
											<input type="hidden" name="rwgo_sw_goal_ids[]" value="<?php echo esc_attr( (string) (int) $page['id'] ); ?>" />
										</li>
									<?php endforeach; ?>
								</ul>
								<?php
								rwgo_sw_combo(
									$empty_choice,
									array(
										'kind'        => 'goal',
										'placeholder' => __( 'Add goal page', 'reactwoo-geo-optimise' ),
										'label'       => __( 'Add a goal page', 'reactwoo-geo-optimise' ),
										'chip'        => true,
									)
								);
								?>
							</div>
						</div>
						<label class="rwgo-sw-checkline">
							<input type="checkbox" name="rwgo_sw_purchase" value="1" <?php checked( ! empty( $view['track_purchase'] ) ); ?> />
							<span>
								<span class="rwgo-sw-checkline__title"><?php esc_html_e( 'Attribute WooCommerce purchases', 'reactwoo-geo-optimise' ); ?></span>
								<span class="rwgo-sw-help"><?php esc_html_e( 'Count a completed order as a conversion for the visitor\'s assigned arm.', 'reactwoo-geo-optimise' ); ?></span>
							</span>
						</label>
					</div>
				</section>
			</div>
		</div>

		<div class="rwgo-sw-bar rwgo-sw-bar--bottom">
			<div class="rwgo-sw-bar__state">
				<p class="rwgo-sw-clean"><?php esc_html_e( 'No unsaved changes', 'reactwoo-geo-optimise' ); ?></p>
				<p class="rwgo-sw-dirty"><span class="rwgo-sw-dirty__dot" aria-hidden="true"></span><?php esc_html_e( 'Unsaved changes', 'reactwoo-geo-optimise' ); ?></p>
			</div>
			<div class="rwgo-sw-bar__actions">
				<?php if ( ! empty( $view['preview_control'] ) ) : ?>
					<a class="rwgo-sw-btn rwgo-sw-btn--ghost" href="<?php echo esc_url( (string) $view['preview_control'] ); ?>" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e( 'Preview control', 'reactwoo-geo-optimise' ); ?></span></a>
				<?php endif; ?>
				<?php if ( ! empty( $view['preview_variant'] ) ) : ?>
					<a class="rwgo-sw-btn rwgo-sw-btn--ghost" href="<?php echo esc_url( (string) $view['preview_variant'] ); ?>" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e( 'Preview variant B', 'reactwoo-geo-optimise' ); ?></span></a>
				<?php endif; ?>
				<button type="submit" class="rwgo-sw-btn rwgo-sw-btn--primary"><?php esc_html_e( 'Save site-wide test', 'reactwoo-geo-optimise' ); ?></button>
			</div>
		</div>
		<p class="screen-reader-text" aria-live="polite" id="rwgo-sw-live"></p>
	</form>

	<template id="rwgo-sw-tpl-match">
		<div class="rwgo-sw-match">
			<label class="rwgo-sw-tick">
				<input type="checkbox" name="rwgo_sw_bulk_pick[]" value="" disabled />
				<span class="screen-reader-text"><?php esc_html_e( 'Select this page for variant creation', 'reactwoo-geo-optimise' ); ?></span>
			</label>
			<div class="rwgo-sw-match__source" data-slot="source"></div>
			<span class="rwgo-sw-arrow" aria-hidden="true"><?php echo $icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div class="rwgo-sw-match__variant" data-slot="variant"></div>
			<span class="rwgo-sw-match__status"><span class="rwgo-sw-badge rwgo-sw-badge--empty" data-badge></span></span>
			<a class="rwgo-sw-iconbtn" data-preview href="" hidden target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Preview variant', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<button type="button" class="rwgo-sw-iconbtn rwgo-sw-iconbtn--remove" data-remove aria-label="<?php esc_attr_e( 'Remove page match', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['trash']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</div>
	</template>
	<template id="rwgo-sw-tpl-product">
		<div class="rwgo-sw-pair">
			<div data-slot="source"></div>
			<span class="rwgo-sw-arrow" aria-hidden="true"><?php echo $icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div data-slot="variant"></div>
			<span class="rwgo-sw-match__status"><span class="rwgo-sw-badge rwgo-sw-badge--empty" data-badge></span></span>
			<a class="rwgo-sw-iconbtn" data-preview href="" hidden target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Preview variant', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<button type="button" class="rwgo-sw-iconbtn rwgo-sw-iconbtn--remove" data-remove aria-label="<?php esc_attr_e( 'Remove product template match', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['trash']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</div>
	</template>
	<template id="rwgo-sw-tpl-header">
		<div class="rwgo-sw-location" data-location="header">
			<h3><?php esc_html_e( 'Header', 'reactwoo-geo-optimise' ); ?></h3>
			<div class="rwgo-sw-pair">
				<div data-slot="control"></div>
				<span class="rwgo-sw-arrow" aria-hidden="true"><?php echo $icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div data-slot="variant"></div>
				<span class="rwgo-sw-match__status"><span class="rwgo-sw-badge rwgo-sw-badge--empty" data-badge></span></span>
				<a class="rwgo-sw-iconbtn" data-preview href="" hidden target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Preview variant', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<button type="button" class="rwgo-sw-iconbtn rwgo-sw-iconbtn--remove" data-remove-location="header" aria-label="<?php esc_attr_e( 'Remove header match', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['trash']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</div>
		</div>
	</template>
	<template id="rwgo-sw-tpl-footer">
		<div class="rwgo-sw-location" data-location="footer">
			<h3><?php esc_html_e( 'Footer', 'reactwoo-geo-optimise' ); ?></h3>
			<div class="rwgo-sw-pair">
				<div data-slot="control"></div>
				<span class="rwgo-sw-arrow" aria-hidden="true"><?php echo $icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div data-slot="variant"></div>
				<span class="rwgo-sw-match__status"><span class="rwgo-sw-badge rwgo-sw-badge--empty" data-badge></span></span>
				<a class="rwgo-sw-iconbtn" data-preview href="" hidden target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Preview variant', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['eye']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<button type="button" class="rwgo-sw-iconbtn rwgo-sw-iconbtn--remove" data-remove-location="footer" aria-label="<?php esc_attr_e( 'Remove footer match', 'reactwoo-geo-optimise' ); ?>"><?php echo $icons['trash']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</div>
		</div>
	</template>
</div>
<?php
unset( $GLOBALS['rwgo_sw_icons'] );
