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

	/** When data-rwgo-experiment-key was not stamped (timing/CSS), resolve from localized goals. */
	function findExperimentKeyForGoalHandler(goalId, handlerId) {
		if (!goalId || !handlerId || !cfg.experiments || !cfg.experiments.length) {
			return '';
		}
		var gi = String(goalId);
		var hi = String(handlerId);
		var i;
		var j;
		var k;
		for (i = 0; i < cfg.experiments.length; i++) {
			var exp = cfg.experiments[i];
			if (!exp || !exp.goals) {
				continue;
			}
			for (j = 0; j < exp.goals.length; j++) {
				var g = exp.goals[j];
				if (!g || String(g.goal_id) !== gi) {
					continue;
				}
				var handlers = g.handlers || [];
				for (k = 0; k < handlers.length; k++) {
					var h = handlers[k];
					if (h && String(h.handler_id) === hi) {
						return exp.experimentKey || '';
					}
				}
			}
		}
		return '';
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

	function rwgoLog() {
		if (typeof window === 'undefined' || !window.console) {
			return;
		}
		var fn = cfg.trackClientDebug ? window.console.log : function () {};
		try {
			fn.apply(window.console, arguments);
		} catch (e1) {
			/* ignore */
		}
	}

	function rwgoWarn() {
		if (typeof window === 'undefined' || !window.console || !window.console.warn) {
			return;
		}
		try {
			window.console.warn.apply(window.console, arguments);
		} catch (e2) {
			/* ignore */
		}
	}

	function persistToRest(detail) {
		if (!cfg.persistClientGoals || !cfg.restUrl || !cfg.nonce) {
			if (cfg.trackClientDebug) {
				rwgoWarn('[RWGO] Client goal not sent — check persistClientGoals, restUrl, nonce.', {
					persistClientGoals: cfg.persistClientGoals,
					hasRestUrl: !!cfg.restUrl,
					hasNonce: !!cfg.nonce
				});
			}
			return;
		}
		var vid = detail.variant_id != null ? String(detail.variant_id).trim() : '';
		if (!vid) {
			rwgoWarn(
				'[RWGO] Goal not sent: empty variant_id. Server must set resolvedVariant for this URL (experiment page IDs / assignment). Enable RWGO_TRACKING_DEBUG or check Reports → diagnostics.',
				detail.experiment_key,
				detail.goal_id
			);
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
			goal_label: detail.goal_label || '',
			element_fingerprint: detail.element_fingerprint || '',
			event_instance_id: detail.event_instance_id || ''
		};
		var json = JSON.stringify(body);
		// Use fetch first: check response.ok — WordPress returns 4xx JSON bodies without throwing.
		if (typeof fetch !== 'undefined') {
			fetch(cfg.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: json,
				keepalive: true
			})
				.then(function (response) {
					if (response.ok) {
						if (cfg.trackClientDebug) {
							rwgoLog('[RWGO] Goal stored (HTTP ' + response.status + ')', detail.experiment_key, detail.goal_id);
						}
						return;
					}
					return response
						.json()
						.then(function (errBody) {
							var code = errBody && errBody.code ? errBody.code : '';
							var msg = errBody && errBody.message ? errBody.message : response.statusText || 'Error';
							rwgoWarn('[RWGO] Goal REST rejected (' + response.status + ')', code || 'unknown_code', msg, errBody);
						})
						.catch(function () {
							rwgoWarn('[RWGO] Goal REST rejected (' + response.status + ')', 'non_json_body');
						});
				})
				.catch(function (err) {
					rwgoWarn('[RWGO] Goal REST network/fetch error', err);
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
					var ht = h.handler_type;
					if (!ht && goal.goal_type === 'click') {
						ht = 'click';
					}
					if (!ht && goal.goal_type === 'form_submit') {
						ht = 'form_submit';
					}
					// Experiment meta uses goal_type "click"; some paths may still send UI keys (e.g. cta_click) on goals.
					if (!ht && goal.goal_type && goal.goal_type !== 'page_view') {
						ht = goal.goal_type === 'form_submit' ? 'form_submit' : 'click';
					}
					h = Object.assign({}, h, { handler_type: ht });
					if (!h.handler_type || (h.handler_type !== 'click' && h.handler_type !== 'form_submit')) {
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

	/**
	 * Stamp data-rwgo-experiment-key on any element that already has goal+handler from the builder
	 * but did not match stampExperimentBindings() (e.g. selector order / Variant B pair present in cfg).
	 */
	function stampMissingExperimentKeysFromDom() {
		try {
			document.querySelectorAll('[data-rwgo-goal-id][data-rwgo-handler-id]').forEach(function (el) {
				if (el.getAttribute('data-rwgo-experiment-key')) {
					return;
				}
				var g = el.getAttribute('data-rwgo-goal-id');
				var h = el.getAttribute('data-rwgo-handler-id');
				var k = findExperimentKeyForGoalHandler(g, h);
				if (k) {
					el.setAttribute('data-rwgo-experiment-key', k);
				}
			});
		} catch (e1) {
			/* ignore */
		}
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
			el = e.target.closest ? e.target.closest('[data-rwgo-goal-id][data-rwgo-handler-id]') : null;
		}
		if (!el) {
			return;
		}
		var goalId = getAttr(el, 'data-rwgo-goal-id');
		var handlerId = getAttr(el, 'data-rwgo-handler-id');
		var expKey = getAttr(el, 'data-rwgo-experiment-key');
		if (!goalId || !handlerId) {
			return;
		}
		if (!expKey) {
			expKey = findExperimentKeyForGoalHandler(goalId, handlerId);
			if (expKey) {
				try {
					el.setAttribute('data-rwgo-experiment-key', expKey);
				} catch (e0) {
					/* ignore */
				}
			}
		}
		if (!expKey) {
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
		var gl = getAttr(el, 'data-rwgo-goal-label') || '';
		window.rwgoFireGoal(expKey, goalId, handlerId, variantId, {
			element_fingerprint: fp,
			goal_label: gl
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
		var gl = getAttr(form, 'data-rwgo-goal-label') || '';
		window.rwgoFireGoal(expKey, goalId, handlerId, variantId, {
			element_fingerprint: fp,
			goal_label: gl
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
			var gl = getAttr(form, 'data-rwgo-goal-label') || '';
			window.rwgoFireGoal(expKey, goalId, handlerId, variantId, {
				element_fingerprint: fp,
				goal_label: gl,
				source: 'elementor_form_success'
			});
		});
	}

	function initTracking() {
		stampElementorFormWidgets();
		stampExperimentBindings();
		stampMissingExperimentKeysFromDom();
		bindElementorFormAjaxSuccess();
		maybeFirePageViews();
		document.addEventListener('click', onClick, true);
		document.addEventListener('submit', onSubmit, true);
	}

	function bootstrapTracking() {
		function go() {
			initTracking();
		}
		if (cfg.restNonceUrl) {
			fetch(cfg.restNonceUrl, { credentials: 'same-origin', cache: 'no-store' })
				.then(function (r) {
					return r.json();
				})
				.then(function (data) {
					if (data && data.nonce) {
						cfg.nonce = data.nonce;
					}
					if (data && data.restUrl) {
						cfg.restUrl = data.restUrl;
					}
				})
				.catch(function (err) {
					if (cfg.trackClientDebug) {
						rwgoWarn('[RWGO] Fresh nonce fetch failed; using embedded nonce (may 403 if cached HTML).', err);
					}
				})
				.finally(go);
		} else {
			go();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootstrapTracking);
	} else {
		bootstrapTracking();
	}
})();
