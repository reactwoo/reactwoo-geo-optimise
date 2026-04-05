/**
 * Geo Optimise — Inspector panel for CTA-capable blocks.
 */
(function (wp) {
	'use strict';
	if (!wp || !wp.hooks || !wp.compose || !wp.element || !wp.blockEditor || !wp.components || !wp.i18n) {
		return;
	}

	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var Fragment = wp.element.Fragment;
	var createElement = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var __ = wp.i18n.__;

	function supportedBlocks() {
		if (typeof rwgoBlockGoals !== 'undefined' && rwgoBlockGoals.blockNames && rwgoBlockGoals.blockNames.length) {
			return rwgoBlockGoals.blockNames;
		}
		return ['core/button'];
	}

	function isSupported(name) {
		return supportedBlocks().indexOf(name) !== -1;
	}

	function goalTypeOptions(blockName) {
		var n = blockName || '';
		if (n.indexOf('woocommerce/') === 0) {
			return [
				{ label: __('Add to cart', 'reactwoo-geo-optimise'), value: 'add_to_cart' },
				{ label: __('Begin checkout', 'reactwoo-geo-optimise'), value: 'begin_checkout' },
				{ label: __('CTA click', 'reactwoo-geo-optimise'), value: 'cta_click' },
				{ label: __('Custom', 'reactwoo-geo-optimise'), value: 'custom' }
			];
		}
		if (n.indexOf('form') !== -1 || n === 'core/file' || n === 'woocommerce/add-to-cart-form') {
			return [
				{ label: __('Form submit', 'reactwoo-geo-optimise'), value: 'form_submit' },
				{ label: __('Custom', 'reactwoo-geo-optimise'), value: 'custom' }
			];
		}
		return [
			{ label: __('CTA click', 'reactwoo-geo-optimise'), value: 'cta_click' },
			{ label: __('Navigation click', 'reactwoo-geo-optimise'), value: 'navigation_click' },
			{ label: __('Form submit', 'reactwoo-geo-optimise'), value: 'form_submit' },
			{ label: __('Checkbox / opt-in interaction', 'reactwoo-geo-optimise'), value: 'checkbox_optin' },
			{ label: __('Add to cart', 'reactwoo-geo-optimise'), value: 'add_to_cart' },
			{ label: __('Custom', 'reactwoo-geo-optimise'), value: 'custom' }
		];
	}

	var withRwgoBlockGoals = createHigherOrderComponent(function (BlockEdit) {
		return function (props) {
			if (!isSupported(props.name)) {
				return createElement(BlockEdit, props);
			}
			var attrs = props.attributes || {};
			var setAttributes = props.setAttributes;
			var gopts = goalTypeOptions(props.name);
			return createElement(
				Fragment,
				null,
				createElement(BlockEdit, props),
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{
							title: __('Geo Optimise', 'reactwoo-geo-optimise'),
							initialOpen: false
						},
						createElement(ToggleControl, {
							label: __('Use as Geo Optimise goal', 'reactwoo-geo-optimise'),
							checked: !!attrs.rwgoGoalEnabled,
							onChange: function (v) {
								var next = { rwgoGoalEnabled: !!v };
								if (v) {
									var r = '';
									try {
										if (window.crypto && window.crypto.getRandomValues) {
											var a = new Uint8Array(8);
											window.crypto.getRandomValues(a);
											r = Array.prototype.map.call(a, function (b) {
												return ('0' + b.toString(16)).slice(-2);
											}).join('');
										}
									} catch (e1) {
										r = '';
									}
									if (!r) {
										r = 'x' + String(Date.now()) + Math.random().toString(16).slice(2, 10);
									}
									next.rwgoGoalId = attrs.rwgoGoalId || 'goal_' + r.slice(0, 14);
									next.rwgoHandlerId = attrs.rwgoHandlerId || 'hdl_' + r.slice(0, 14);
								}
								setAttributes(next);
							},
							help: __(
								'Turn this on if this block should be available as a measurable goal in Geo Optimise tests (CTAs, links, forms, and store actions).',
								'reactwoo-geo-optimise'
							)
						}),
						attrs.rwgoGoalEnabled
							? createElement(TextControl, {
									label: __('Goal label', 'reactwoo-geo-optimise'),
									placeholder: __('e.g. Primary hero CTA', 'reactwoo-geo-optimise'),
									value: attrs.rwgoGoalLabel || '',
									onChange: function (v) {
										setAttributes({ rwgoGoalLabel: v });
									},
									help: __('Used in test setup and reports to identify this goal clearly.', 'reactwoo-geo-optimise')
							  })
							: null,
						attrs.rwgoGoalEnabled
							? createElement(SelectControl, {
									label: __('Goal type', 'reactwoo-geo-optimise'),
									value: attrs.rwgoGoalType || 'cta_click',
									options: gopts,
									onChange: function (v) {
										setAttributes({ rwgoGoalType: v });
									}
							  })
							: null,
						attrs.rwgoGoalEnabled
							? createElement(TextareaControl, {
									label: __('Goal note', 'reactwoo-geo-optimise'),
									value: attrs.rwgoGoalNote || '',
									onChange: function (v) {
										setAttributes({ rwgoGoalNote: v });
									},
									rows: 2
							  })
							: null,
						attrs.rwgoGoalEnabled
							? createElement('p', { className: 'rwgo-goal-status', style: { color: '#00a32a', fontSize: '12px', marginTop: '8px' } }, __('Goal enabled', 'reactwoo-geo-optimise'))
							: null
					)
				)
			);
		};
	}, 'withRwgoBlockGoals');

	addFilter('editor.BlockEdit', 'rwgo/block-goals', withRwgoBlockGoals);
})(window.wp);
