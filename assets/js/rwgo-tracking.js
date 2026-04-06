/**
 * Geo Optimise front-end: experiment-scoped goals, data-* stamps, optional page-view.
 */
(function () {
	'use strict';

	var cfg = typeof rwgoTracking === 'undefined' ? null : rwgoTracking;
	if (!cfg || !cfg.experiments || !cfg.experiments.length) {
		return;
	}

	if (typeof window !== 'undefined') {
		window.rwgoTrackingStrictBinding = !!cfg.strictBinding;
	}

	var strictBinding = !!cfg.strictBinding;
	var pageContextId = cfg.pageContextId || 0;

	function findExperiment(experimentKey) {
		var k = experimentKey || '';
		for (var i = 0; i < cfg.experiments.length; i++) {
			if (cfg.experiments[i].experimentKey === k) {
				return cfg.experiments[i];
			}
		}
		return null;
	}

	function getAttr(el, name) {
		return el && el.getAttribute ? el.getAttribute(name) : null;
	}

	function resolveVariantId(el, exp) {
		var v = getAttr(el, 'data-rwgo-variant-id');
		if (v) {
			return v;
		}
		return exp && exp.resolvedVariant ? exp.resolvedVariant : '';
	}

	function pushDataLayer(detail) {
		var exp = findExperiment(detail.experiment_key || '');
		var testName = '';
		var builder = '';
		var variantLabel = '';
		var goalLabel = '';
		var pubGoalId = detail.goal_id || '';
		if (exp) {
			testName = exp.testName || '';
			builder = exp.builder || '';
			pubGoalId = resolvePublicGoalId(exp, detail.goal_id || '', detail.handler_id || '');
			goalLabel = findGoalLabel(exp, pubGoalId) || '';
			var vid = detail.variant_id || '';
			if (vid && exp.variantLabels && typeof exp.variantLabels === 'object') {
				variantLabel = exp.variantLabels[vid] || '';
			}
		}
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: 'rwgo_goal_fired',
			rwgo_test_name: testName,
			rwgo_experiment_key: detail.experiment_key || '',
			rwgo_variant_id: detail.variant_id || '',
			rwgo_variant_label: variantLabel,
			rwgo_goal_id: pubGoalId,
			rwgo_goal_label: goalLabel,
			rwgo_handler_id: detail.handler_id || '',
			rwgo_page_context_id: detail.page_context_id || 0,
			rwgo_builder: builder
		});
	}

	function dispatchInternal(detail) {
		try {
			window.dispatchEvent(new CustomEvent('rwgo:goal', { detail: detail }));
		} catch (e) {
			/* ignore */
		}
		if (typeof window.jQuery !== 'undefined') {
			window.jQuery(document).trigger('rwgo:goal', [detail]);
		}
	}

	function newEventInstanceId() {
		return 'evt_' + (Date.now().toString(36) + Math.random().toString(36).slice(2, 14)).slice(0, 48);
	}

	function findGoalType(exp, goalId) {
		if (!exp || !exp.goals || !goalId) {
			return '';
		}
		var i;
		for (i = 0; i < exp.goals.length; i++) {
			var g = exp.goals[i];
			if (!g) {
				continue;
			}
			if (g.goal_id === goalId) {
				return g.goal_type || '';
			}
			if (g.logical_goal_id === goalId) {
				return g.goal_type || '';
			}
		}
		return '';
	}

	function findGoalLabel(exp, goalId) {
		if (!exp || !exp.goals || !goalId) {
			return '';
		}
		var i;
		for (i = 0; i < exp.goals.length; i++) {
			var g = exp.goals[i];
			if (!g) {
				continue;
			}
			if (g.goal_id === goalId) {
				return g.label || g.goal_id || '';
			}
			if (g.logical_goal_id === goalId) {
				return g.label || g.logical_goal_id || '';
			}
		}
		return '';
	}

	/** When using per-variant mapping, use logical primary id in dataLayer (matches stored conversions). */
	function resolvePublicGoalId(exp, goalId, handlerId) {
		if (!exp || !goalId || !handlerId) {
			return goalId;
		}
		var i;
		for (i = 0; i < (exp.goals || []).length; i++) {
			var g = exp.goals[i];
			if (!g || g.goal_id !== goalId) {
				continue;
			}
			var handlers = g.handlers || [];
			var j;
			for (j = 0; j < handlers.length; j++) {
				var h = handlers[j];
				if (h && h.handler_id === handlerId && g.logical_goal_id) {
					return g.logical_goal_id;
				}
			}
		}
		return goalId;
	}

	function persistToRest(detail) {
		if (!cfg.persistClientGoals || !cfg.restUrl || !cfg.nonce) {
			return;
		}
		var body = {
			nonce: cfg.nonce,
			experiment_key: detail.experiment_key || '',
			goal_id: detail.goal_id || '',
			handler_id: detail.handler_id || '',
			variant_id: detail.variant_id || '',
			page_context_id: detail.page_context_id || 0,
			page_variant_post_id: detail.page_variant_post_id != null ? detail.page_variant_post_id : (detail.page_context_id || 0),
			goal_type: detail.goal_type || '',
			element_fingerprint: detail.element_fingerprint || '',
			event_instance_id: detail.event_instance_id || ''
		};
		var json = JSON.stringify(body);
		// Use fetch first: sendBeacon returns true when queued, not when the server returns 201, so failures were silent and fetch never ran.
		if (typeof fetch !== 'undefined') {
			fetch(cfg.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: json,
				keepalive: true
			}).catch(function () {
				/* ignore */
			});
			return;
		}
		if (typeof navigator !== 'undefined' && navigator.sendBeacon) {
			try {
				navigator.sendBeacon(cfg.restUrl, new Blob([json], { type: 'application/json' }));
			} catch (e1) {
				/* ignore */
			}
		}
	}

	window.rwgoFireGoal = function (experimentKey, goalId, handlerId, variantId, extra) {
		var exp = findExperiment(experimentKey);
		var detail = {
			experiment_key: experimentKey,
			goal_id: goalId,
			handler_id: handlerId,
			variant_id: variantId || '',
			page_context_id: pageContextId,
			source: 'geo_optimise'
		};
		if (extra && typeof extra === 'object') {
			for (var k in extra) {
				if (Object.prototype.hasOwnProperty.call(extra, k)) {
					detail[k] = extra[k];
				}
			}
		}
		if (!detail.event_instance_id) {
			detail.event_instance_id = newEventInstanceId();
		}
		if (!detail.goal_type && exp) {
			detail.goal_type = findGoalType(exp, resolvePublicGoalId(exp, goalId, handlerId));
		}
		if (detail.page_variant_post_id == null) {
			detail.page_variant_post_id = pageContextId;
		}
		if (exp && exp.experimentId) {
			detail.experiment_id = exp.experimentId;
		}
		pushDataLayer(detail);
		dispatchInternal(detail);
		persistToRest(detail);
	};

	function pvDedupeKey(experimentKey, goalId, handlerId) {
		return 'rwgo_pv_' + experimentKey + '_' + goalId + '_' + handlerId;
	}

	function escapeAttr(s) {
		if (s == null) {
			return '';
		}
		return String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
	}

	function stampExperimentBindings() {
		cfg.experiments.forEach(function (exp) {
			var expKey = exp.experimentKey;
			if (!expKey || !exp.goals) {
				return;
			}
			exp.goals.forEach(function (goal) {
				if (!goal) {
					return;
				}
				var handlers = goal.handlers || [];
				handlers.forEach(function (h) {
					if (!h || (h.handler_type !== 'click' && h.handler_type !== 'form_submit')) {
						return;
					}
					var gid = goal.goal_id;
					var hid = h.handler_id;
					if (!gid || !hid) {
						return;
					}
					var sel = '[data-rwgo-goal-id="' + escapeAttr(gid) + '"][data-rwgo-handler-id="' + escapeAttr(hid) + '"]';
					var nodes = document.querySelectorAll(sel);
					var i;
					for (i = 0; i < nodes.length; i++) {
						nodes[i].setAttribute('data-rwgo-experiment-key', expKey);
					}
				});
			});
		});
	}

	function maybeFirePageViews() {
		if (strictBinding) {
			return;
		}
		try {
			if (!window.sessionStorage) {
				return;
			}
		} catch (e) {
			return;
		}
		cfg.experiments.forEach(function (exp) {
			if (!exp.goals || !exp.goals.length) {
				return;
			}
			exp.goals.forEach(function (goal) {
				if (!goal || goal.goal_type !== 'page_view') {
					return;
				}
				var handlers = goal.handlers || [];
				var h = handlers[0] || {};
				var hid = h.handler_id || '';
				var gid = goal.goal_id || '';
				if (!hid || !gid) {
					return;
				}
				var dest = h.destination_page_id;
				if (dest != null && dest !== '') {
					if (parseInt(dest, 10) !== parseInt(pageContextId, 10)) {
						return;
					}
				}
				var sk = pvDedupeKey(exp.experimentKey, gid, hid);
				try {
					if (window.sessionStorage.getItem(sk)) {
						return;
					}
					window.sessionStorage.setItem(sk, '1');
				} catch (e2) {
					return;
				}
				var vid = exp.resolvedVariant || '';
				window.rwgoFireGoal(exp.experimentKey, gid, hid, vid, {
					element_fingerprint: getAttr(document.body, 'data-rwgo-element-fingerprint') || 'page_view'
				});
			});
		});
	}

	function stampAllowed(el) {
		if (!strictBinding) {
			return true;
		}
		var fp = getAttr(el, 'data-rwgo-element-fingerprint');
		return fp !== null && fp !== '';
	}

	function onClick(e) {
		var el = e.target.closest ? e.target.closest('[data-rwgo-experiment-key]') : null;
		if (!el) {
			return;
		}
		var expKey = getAttr(el, 'data-rwgo-experiment-key');
		var goalId = getAttr(el, 'data-rwgo-goal-id');
		var handlerId = getAttr(el, 'data-rwgo-handler-id');
		if (!expKey || !goalId || !handlerId) {
			return;
		}
		if (strictBinding && !stampAllowed(el)) {
			return;
		}
		var exp = findExperiment(expKey);
		if (!exp) {
			return;
		}
		var variantId = resolveVariantId(el, exp);
		var fp = getAttr(el, 'data-rwgo-element-fingerprint') || '';
		window.rwgoFireGoal(expKey, goalId, handlerId, variantId, {
			element_fingerprint: fp
		});
	}

	function onSubmit(e) {
		var form = e.target;
		if (!form || form.tagName !== 'FORM' || !form.getAttribute('data-rwgo-experiment-key')) {
			return;
		}
		if (getAttr(form, 'data-rwgo-form-strategy') === 'elementor_success' && getAttr(form, 'data-rwgo-goal-type') === 'form_submit') {
			return;
		}
		var expKey = getAttr(form, 'data-rwgo-experiment-key');
		var goalId = getAttr(form, 'data-rwgo-goal-id');
		var handlerId = getAttr(form, 'data-rwgo-handler-id');
		if (!expKey || !goalId || !handlerId) {
			return;
		}
		if (strictBinding && !stampAllowed(form)) {
			return;
		}
		var exp = findExperiment(expKey);
		if (!exp) {
			return;
		}
		var variantId = resolveVariantId(form, exp);
		var fp = getAttr(form, 'data-rwgo-element-fingerprint') || '';
		window.rwgoFireGoal(expKey, goalId, handlerId, variantId, {
			element_fingerprint: fp
		});
	}

	function stampElementorFormWidgets() {
		document.querySelectorAll('.elementor-widget-form[data-rwgo-goal-id]').forEach(function (wrap) {
			var form = wrap.querySelector('form.elementor-form');
			if (!form) {
				form = wrap.querySelector('form');
			}
			if (!form) {
				return;
			}
			[
				'data-rwgo-goal-id',
				'data-rwgo-goal-label',
				'data-rwgo-goal-type',
				'data-rwgo-handler-id',
				'data-rwgo-builder',
				'data-rwgo-element-fingerprint'
			].forEach(function (a) {
				var v = wrap.getAttribute(a);
				if (v && !form.getAttribute(a)) {
					form.setAttribute(a, v);
				}
			});
			if (getAttr(form, 'data-rwgo-goal-type') === 'form_submit') {
				form.setAttribute('data-rwgo-form-strategy', 'elementor_success');
			}
		});
	}

	function bindElementorFormAjaxSuccess() {
		if (typeof window.jQuery === 'undefined') {
			return;
		}
		var $ = window.jQuery;
		$(document).on('submit_success', '.elementor-form', function () {
			var form = this;
			if (getAttr(form, 'data-rwgo-form-strategy') !== 'elementor_success') {
				return;
			}
			if (!form.getAttribute('data-rwgo-experiment-key')) {
				return;
			}
			var expKey = getAttr(form, 'data-rwgo-experiment-key');
			var goalId = getAttr(form, 'data-rwgo-goal-id');
			var handlerId = getAttr(form, 'data-rwgo-handler-id');
			if (!expKey || !goalId || !handlerId) {
				return;
			}
			if (strictBinding && !stampAllowed(form)) {
				return;
			}
			var exp = findExperiment(expKey);
			if (!exp) {
				return;
			}
			var variantId = resolveVariantId(form, exp);
			var fp = getAttr(form, 'data-rwgo-element-fingerprint') || '';
			window.rwgoFireGoal(expKey, goalId, handlerId, variantId, {
				element_fingerprint: fp,
				source: 'elementor_form_success'
			});
		});
	}

	function runDomReady() {
		stampElementorFormWidgets();
		stampExperimentBindings();
		bindElementorFormAjaxSuccess();
		maybeFirePageViews();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', runDomReady);
	} else {
		runDomReady();
	}

	document.addEventListener('click', onClick, true);
	document.addEventListener('submit', onSubmit, true);
})();
