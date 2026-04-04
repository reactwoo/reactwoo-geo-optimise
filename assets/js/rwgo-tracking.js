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
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: 'rwgo_goal_fired',
			rwgo_experiment_key: detail.experiment_key || '',
			rwgo_goal_id: detail.goal_id || '',
			rwgo_handler_id: detail.handler_id || '',
			rwgo_variant_id: detail.variant_id || '',
			rwgo_page_context_id: detail.page_context_id || 0
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
			if (g && g.goal_id === goalId) {
				return g.goal_type || '';
			}
		}
		return '';
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
		if (typeof navigator !== 'undefined' && navigator.sendBeacon) {
			try {
				var ok = navigator.sendBeacon(cfg.restUrl, new Blob([json], { type: 'application/json' }));
				if (ok) {
					return;
				}
			} catch (e1) {
				/* fall through */
			}
		}
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
			detail.goal_type = findGoalType(exp, goalId);
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

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			maybeFirePageViews();
		});
	} else {
		maybeFirePageViews();
	}

	document.addEventListener('click', onClick, true);
	document.addEventListener('submit', onSubmit, true);
})();
