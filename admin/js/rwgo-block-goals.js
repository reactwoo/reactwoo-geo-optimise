/**
 * Geo Optimise — Inspector panel for core/button.
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

	function ensureIds(setAttributes, attrs) {
		if (!attrs.rwgoGoalEnabled) {
			return;
		}
		if (attrs.rwgoGoalId && attrs.rwgoHandlerId) {
			return;
		}
		var r = '';
		try {
			if (window.crypto && window.crypto.getRandomValues) {
				var a = new Uint8Array(8);
				window.crypto.getRandomValues(a);
				r = Array.prototype.map.call(a, function (b) {
					return ('0' + b.toString(16)).slice(-2);
				}).join('');
			}
		} catch (e) {
			r = '';
		}
		if (!r) {
			r = 'x' + String(Date.now()) + Math.random().toString(16).slice(2, 10);
		}
		setAttributes({
			rwgoGoalId: attrs.rwgoGoalId || 'goal_' + r.slice(0, 14),
			rwgoHandlerId: attrs.rwgoHandlerId || 'hdl_' + r.slice(0, 14)
		});
	}

	var withRwgoButtonGoals = createHigherOrderComponent(function (BlockEdit) {
		return function (props) {
			if (props.name !== 'core/button') {
				return createElement(BlockEdit, props);
			}
			var attrs = props.attributes || {};
			var setAttributes = props.setAttributes;
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
								'Turn this on if clicks on this block should be available as a measurable goal in Geo Optimise tests.',
								'reactwoo-geo-optimise'
							)
						}),
						attrs.rwgoGoalEnabled
							? createElement(TextControl, {
									label: __('Goal label', 'reactwoo-geo-optimise'),
									placeholder: __('e.g. Main CTA', 'reactwoo-geo-optimise'),
									value: attrs.rwgoGoalLabel || '',
									onChange: function (v) {
										setAttributes({ rwgoGoalLabel: v });
									},
									help: __('Used in Geo Optimise when selecting the winning goal for a test.', 'reactwoo-geo-optimise')
							  })
							: null,
						attrs.rwgoGoalEnabled
							? createElement(SelectControl, {
									label: __('Goal type', 'reactwoo-geo-optimise'),
									value: attrs.rwgoGoalType || 'cta_click',
									options: [
										{ label: __('CTA click', 'reactwoo-geo-optimise'), value: 'cta_click' },
										{ label: __('Navigation click', 'reactwoo-geo-optimise'), value: 'navigation_click' },
										{ label: __('Add to cart', 'reactwoo-geo-optimise'), value: 'add_to_cart' },
										{ label: __('Custom', 'reactwoo-geo-optimise'), value: 'custom' }
									],
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
	}, 'withRwgoButtonGoals');

	addFilter('editor.BlockEdit', 'rwgo/core-button-goals', withRwgoButtonGoals);
})(window.wp);
