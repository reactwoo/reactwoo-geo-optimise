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
	var ExternalLink = wp.components.ExternalLink;
	var __ = wp.i18n.__;

	function destinationGoalDocLinks(cfg) {
		cfg = cfg || {};
		if (!cfg.helpUrl && !cfg.supportUrl) {
			return null;
		}
		var Link = ExternalLink || function (props) {
			return createElement('a', { href: props.href, target: '_blank', rel: 'noopener noreferrer' }, props.children);
		};
		return createElement(
			'div',
			{ className: 'rwgo-destination-goal-doclinks', style: { marginTop: '12px', paddingTop: '12px', borderTop: '1px solid #e0e0e0' } },
			cfg.helpUrl
				? createElement(
						'p',
						{ className: 'description', style: { marginBottom: '8px' } },
						createElement(
							Link,
							{ href: cfg.helpUrl },
							__('Documentation: builder goals and destination pages', 'reactwoo-geo-optimise')
						)
				  )
				: null,
			cfg.supportUrl
				? createElement(
						'p',
						{ className: 'description', style: { margin: 0 } },
						createElement(
							Link,
							{ href: cfg.supportUrl },
							__('Support and troubleshooting', 'reactwoo-geo-optimise')
						)
				  )
				: null
		);
	}

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
			createElement(
				'p',
				{ className: 'description', style: { marginTop: 0 } },
				__(
					'Conversion goals: count a visit to this page as a test goal. This is separate from GeoElementor geo routing.',
					'reactwoo-geo-optimise'
				)
			),
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
			destinationGoalDocLinks(cfg)
		);
	}

	registerPlugin('rwgo-page-destination-goal', {
		icon: 'location',
		render: DestinationGoalDocumentPanel,
	});
})(window.wp);
