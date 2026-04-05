/**
 * Gutenberg document sidebar — Geo Optimise destination goal (same meta as classic meta box).
 */
(function (wp) {
	'use strict';
	if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.data || !wp.coreData) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var createElement = wp.element.createElement;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var __ = wp.i18n.__;

	var META_E = '_rwgo_dest_goal_enabled';
	var META_L = '_rwgo_dest_goal_label';
	var META_T = '_rwgo_dest_goal_type';

	function DestinationGoalDocumentPanel() {
		var cfg = typeof rwgoPageGoalDocument !== 'undefined' ? rwgoPageGoalDocument : {};
		var supported = cfg.supportedPostTypes || ['post', 'page'];

		var postType = useSelect(function (select) {
			return select('core/editor').getCurrentPostType();
		}, []);

		if (!postType || supported.indexOf(postType) === -1) {
			return null;
		}

		var tuple = useEntityProp('postType', postType, 'meta');
		var meta = tuple[0];
		var setMeta = tuple[1];

		if (!meta || typeof meta !== 'object') {
			return null;
		}

		var enabled = meta[META_E] === '1' || meta[META_E] === 'yes';
		var label = meta[META_L] || '';
		var type = meta[META_T] || 'page_visit';

		function patch(partial) {
			var next = {};
			for (var k in meta) {
				if (Object.prototype.hasOwnProperty.call(meta, k)) {
					next[k] = meta[k];
				}
			}
			for (var p in partial) {
				if (Object.prototype.hasOwnProperty.call(partial, p)) {
					next[p] = partial[p];
				}
			}
			setMeta(next);
		}

		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'rwgo-page-destination-goal',
				title: __('Geo Optimise — destination goal', 'reactwoo-geo-optimise'),
				className: 'rwgo-document-panel-destination-goal',
			},
			createElement(ToggleControl, {
				label: __('Use this page as a Geo Optimise goal destination', 'reactwoo-geo-optimise'),
				help: __('Turn on if visiting this page should count as a conversion in Geo Optimise tests.', 'reactwoo-geo-optimise'),
				checked: !!enabled,
				onChange: function (v) {
					var o = {};
					o[META_E] = v ? '1' : '0';
					patch(o);
				},
			}),
			enabled
				? createElement(TextControl, {
						label: __('Goal label', 'reactwoo-geo-optimise'),
						placeholder: __('e.g. Thank you page', 'reactwoo-geo-optimise'),
						value: label,
						onChange: function (v) {
							var o = {};
							o[META_L] = v;
							patch(o);
						},
				  })
				: null,
			enabled
				? createElement(SelectControl, {
						label: __('Goal type', 'reactwoo-geo-optimise'),
						value: type,
						options: [
							{ label: __('Page visit', 'reactwoo-geo-optimise'), value: 'page_visit' },
							{ label: __('Thank-you / confirmation page', 'reactwoo-geo-optimise'), value: 'thank_you' },
							{ label: __('Lead confirmation', 'reactwoo-geo-optimise'), value: 'lead_confirmation' },
							{ label: __('Checkout success', 'reactwoo-geo-optimise'), value: 'checkout_success' },
							{ label: __('Custom destination', 'reactwoo-geo-optimise'), value: 'custom_destination' },
						],
						onChange: function (v) {
							var o = {};
							o[META_T] = v;
							patch(o);
						},
				  })
				: null,
			enabled
				? createElement(
						'p',
						{
							className: 'rwgo-goal-status',
							style: { color: '#00a32a', fontSize: '12px', marginTop: '8px' },
						},
						__('Destination goal set', 'reactwoo-geo-optimise')
				  )
				: null
		);
	}

	registerPlugin('rwgo-page-destination-goal', {
		icon: 'location',
		render: DestinationGoalDocumentPanel,
	});
})(window.wp);
