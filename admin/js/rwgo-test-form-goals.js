/**
 * Geo Optimise — defined goals: Control + Variant B mapping (Create / Edit Test).
 */
(function () {
	'use strict';

	var cfgEl = document.getElementById('rwgo-test-form-goals-config');
	var cfg = {};
	if (cfgEl && cfgEl.textContent) {
		try {
			cfg = JSON.parse(cfgEl.textContent);
		} catch (e) {
			cfg = {};
		}
	}

	var form =
		document.getElementById('rwgo-create-test-form') || document.getElementById('rwgo-edit-test-form');
	if (!form || !cfg.restUrl) {
		return;
	}

	var hidden = document.getElementById('rwgo_defined_goal');
	var selControl = document.getElementById('rwgo_defined_goal_control_select');
	var selVarB = document.getElementById('rwgo_defined_goal_var_b_select');
	var varBWrap = document.getElementById('rwgo-defined-goal-varb-wrap');
	var modeRadios = document.querySelectorAll('.rwgo-goal-sel-mode');
	var defPanel = document.getElementById('rwgo-defined-goal-panel');
	var autoPanel = document.getElementById('rwgo-automatic-goal-panel');
	var goalType = document.getElementById('rwgo_goal_type');
	var sourceSel = document.getElementById('rwgo_source_page');
	var variantSel = document.getElementById('rwgo_variant_b_page');
	var variantRadios = document.querySelectorAll('.rwgo-variant-mode');

	function getMode() {
		var m = 'automatic';
		modeRadios.forEach(function (r) {
			if (r.checked) {
				m = r.value;
			}
		});
		return m;
	}

	function syncPanels() {
		var m = getMode();
		var traffic = false;
		document.querySelectorAll('.rwgo-winner-mode').forEach(function (r) {
			if (r.checked && r.value === 'traffic_only') {
				traffic = true;
			}
		});
		if (defPanel) {
			defPanel.hidden = traffic || m !== 'defined';
		}
		if (autoPanel) {
			autoPanel.hidden = traffic || m !== 'automatic';
		}
		if (goalType) {
			goalType.disabled = traffic || m !== 'automatic';
		}
		[selControl, selVarB].forEach(function (sel) {
			if (sel) {
				sel.disabled = traffic || m !== 'defined';
			}
		});
	}

	function collectPostIds() {
		var ids = [];
		var mode = form.getAttribute('data-rwgo-form-mode') || '';
		if (mode === 'edit') {
			var s = parseInt(form.getAttribute('data-rwgo-source-id') || '0', 10);
			var v = parseInt(form.getAttribute('data-rwgo-variant-b-id') || '0', 10);
			if (s) {
				ids.push(s);
			}
			if (v && v !== s) {
				ids.push(v);
			}
			return ids;
		}
		if (sourceSel && sourceSel.value) {
			ids.push(parseInt(sourceSel.value, 10));
		}
		var vm = 'duplicate';
		variantRadios.forEach(function (r) {
			if (r.checked) {
				vm = r.value;
			}
		});
		if (vm === 'existing' && variantSel && variantSel.value) {
			var vx = parseInt(variantSel.value, 10);
			if (vx && ids.indexOf(vx) === -1) {
				ids.push(vx);
			}
		}
		return ids;
	}

	function getSourceAndVariantIds() {
		var mode = form.getAttribute('data-rwgo-form-mode') || '';
		if (mode === 'edit') {
			return {
				sourceId: parseInt(form.getAttribute('data-rwgo-source-id') || '0', 10),
				varBId: parseInt(form.getAttribute('data-rwgo-variant-b-id') || '0', 10)
			};
		}
		var s = sourceSel && sourceSel.value ? parseInt(sourceSel.value, 10) : 0;
		var v = 0;
		var vm = 'duplicate';
		variantRadios.forEach(function (r) {
			if (r.checked) {
				vm = r.value;
			}
		});
		if (vm === 'existing' && variantSel && variantSel.value) {
			v = parseInt(variantSel.value, 10);
		}
		return { sourceId: s, varBId: v };
	}

	function splitGoalsByPage(goals, sourceId, varBId) {
		var control = [];
		var variant = [];
		if (!goals || !goals.length) {
			return { control: control, variant: variant };
		}
		goals.forEach(function (g) {
			if (!g) {
				return;
			}
			var sp = parseInt(String(g.source_post_id != null ? g.source_post_id : 0), 10);
			if (sourceId && sp === sourceId) {
				control.push(g);
			}
			if (varBId && sp === varBId) {
				variant.push(g);
			}
		});
		return { control: control, variant: variant };
	}

	function fillMappingSelect(sel, items, placeholderEmpty) {
		if (!sel) {
			return;
		}
		var keep = sel.value;
		sel.innerHTML = '';
		var opt0 = document.createElement('option');
		opt0.value = '';
		opt0.textContent = placeholderEmpty;
		sel.appendChild(opt0);
		items.forEach(function (g) {
			if (!g || !g.goal_id) {
				return;
			}
			var opt = document.createElement('option');
			opt.value = JSON.stringify(g);
			opt.textContent = g.goal_label || g.goal_id;
			sel.appendChild(opt);
		});
		if (keep) {
			sel.value = keep;
		}
	}

	function fetchGoals() {
		if (!selControl) {
			return;
		}
		var ids = collectPostIds();
		var pv = getSourceAndVariantIds();
		if (!ids.length) {
			var msg =
				typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.pickSource
					? rwgoTestFormGoalsI18n.pickSource
					: 'Select a source page first (and Variant B if needed).';
			fillMappingSelect(selControl, [], msg);
			if (selVarB) {
				fillMappingSelect(selVarB, [], msg);
			}
			if (varBWrap) {
				varBWrap.hidden = false;
			}
			return;
		}
		var url = cfg.restUrl + (cfg.restUrl.indexOf('?') === -1 ? '?' : '&') + 'post_ids=' + encodeURIComponent(ids.join(','));
		var headers = { Accept: 'application/json' };
		if (cfg.nonce) {
			headers['X-WP-Nonce'] = cfg.nonce;
		}
		if (selControl) {
			selControl.disabled = true;
		}
		if (selVarB) {
			selVarB.disabled = true;
		}
		fetch(url, { credentials: 'same-origin', headers: headers })
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				var goals = data && data.goals ? data.goals : [];
				var split = splitGoalsByPage(goals, pv.sourceId, pv.varBId);
				var pickGoal =
					typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.pickGoal
						? rwgoTestFormGoalsI18n.pickGoal
						: '— Choose a goal —';
				var noneFound =
					typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.noneFound
						? rwgoTestFormGoalsI18n.noneFound
						: 'No goals on this page yet.';
				fillMappingSelect(selControl, split.control, split.control.length ? pickGoal : noneFound);
				var showVar = pv.varBId > 0 && pv.varBId !== pv.sourceId;
				if (varBWrap) {
					varBWrap.hidden = !showVar;
				}
				if (selVarB) {
					if (showVar) {
						fillMappingSelect(selVarB, split.variant, split.variant.length ? pickGoal : noneFound);
					} else {
						selVarB.innerHTML = '';
						var o = document.createElement('option');
						o.value = '';
						o.textContent =
							typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.varBAfterPublish
								? rwgoTestFormGoalsI18n.varBAfterPublish
								: 'Publish the test first or pick an existing Variant B page, then map Variant B.';
						selVarB.appendChild(o);
					}
				}
				if (selControl) {
					selControl.disabled = false;
				}
				if (selVarB) {
					selVarB.disabled = false;
				}
				syncPanels();
				matchInitial();
			})
			.catch(function () {
				var err =
					typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.loadFailed
						? rwgoTestFormGoalsI18n.loadFailed
						: 'Could not load goals.';
				fillMappingSelect(selControl, [], err);
				if (selVarB) {
					fillMappingSelect(selVarB, [], err);
				}
				if (selControl) {
					selControl.disabled = false;
				}
				if (selVarB) {
					selVarB.disabled = false;
				}
			});
	}

	function payloadFromSelect(sel) {
		if (!sel || !sel.value) {
			return {};
		}
		try {
			return JSON.parse(sel.value);
		} catch (e) {
			return {};
		}
	}

	function syncHiddenFromSelects() {
		if (!hidden) {
			return;
		}
		var c = payloadFromSelect(selControl);
		var v = payloadFromSelect(selVarB);
		var pv = getSourceAndVariantIds();
		if (pv.varBId > 0 && pv.varBId !== pv.sourceId) {
			hidden.value = JSON.stringify({ version: 2, control: c, var_b: v });
		} else {
			hidden.value = JSON.stringify({ version: 2, control: c, var_b: {} });
		}
	}

	function matchOptionByGoalId(sel, payload) {
		if (!sel || !payload || !payload.goal_id) {
			return;
		}
		var gid = payload.goal_id;
		var hid = payload.handler_id || '';
		var i;
		for (i = 0; i < sel.options.length; i++) {
			var o = sel.options[i];
			if (!o.value) {
				continue;
			}
			try {
				var g = JSON.parse(o.value);
				if (g.goal_id === gid && (!hid || !g.handler_id || g.handler_id === hid)) {
					sel.selectedIndex = i;
					return;
				}
			} catch (e2) {
				/* ignore */
			}
		}
	}

	function matchInitial() {
		if (!hidden || getMode() !== 'defined') {
			return;
		}
		var want = hidden.value;
		if (!want) {
			want = cfg.initialDefinedJson || '';
		}
		if (!want) {
			return;
		}
		var h;
		try {
			h = JSON.parse(want);
		} catch (e) {
			return;
		}
		if (h && parseInt(h.version, 10) === 2) {
			if (h.control && selControl) {
				matchOptionByGoalId(selControl, h.control);
			}
			if (h.var_b && selVarB) {
				matchOptionByGoalId(selVarB, h.var_b);
			}
			return;
		}
		if (h && h.goal_id && selControl) {
			matchOptionByGoalId(selControl, h);
		}
	}

	function onSelectChange() {
		syncHiddenFromSelects();
	}

	modeRadios.forEach(function (r) {
		r.addEventListener('change', function () {
			syncPanels();
			if (getMode() === 'defined') {
				fetchGoals();
			}
		});
	});
	[selControl, selVarB].forEach(function (sel) {
		if (sel) {
			sel.addEventListener('change', onSelectChange);
		}
	});
	if (sourceSel) {
		sourceSel.addEventListener('change', function () {
			if (getMode() === 'defined') {
				fetchGoals();
			}
		});
	}
	if (variantSel) {
		variantSel.addEventListener('change', function () {
			if (getMode() === 'defined') {
				fetchGoals();
			}
		});
	}
	variantRadios.forEach(function (r) {
		r.addEventListener('change', function () {
			if (getMode() === 'defined') {
				fetchGoals();
			}
		});
	});

	document.querySelectorAll('.rwgo-winner-mode').forEach(function (r) {
		r.addEventListener('change', syncPanels);
	});

	form.addEventListener('submit', function () {
		var mode = getMode();
		if (goalType) {
			goalType.disabled = false;
		}
		if (mode === 'defined') {
			if (goalType) {
				goalType.disabled = true;
			}
			syncHiddenFromSelects();
		} else if (hidden) {
			hidden.value = '';
		}
	});

	syncPanels();
	if (getMode() === 'defined') {
		fetchGoals();
	} else {
		matchInitial();
	}
})();
