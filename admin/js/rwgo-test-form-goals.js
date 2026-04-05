/**
 * Geo Optimise — defined goals picker on Create / Edit Test.
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
	var sel = document.getElementById('rwgo_defined_goal_select');
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
		if (sel) {
			sel.disabled = traffic || m !== 'defined';
		}
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

	function fetchGoals() {
		if (!sel) {
			return;
		}
		var ids = collectPostIds();
		if (!ids.length) {
			sel.innerHTML = '';
			var o0 = document.createElement('option');
			o0.value = '';
			o0.textContent =
				typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.pickSource
					? rwgoTestFormGoalsI18n.pickSource
					: 'Select a source page first.';
			sel.appendChild(o0);
			return;
		}
		var url = cfg.restUrl + (cfg.restUrl.indexOf('?') === -1 ? '?' : '&') + 'post_ids=' + encodeURIComponent(ids.join(','));
		var headers = { Accept: 'application/json' };
		if (cfg.nonce) {
			headers['X-WP-Nonce'] = cfg.nonce;
		}
		sel.disabled = true;
		fetch(url, { credentials: 'same-origin', headers: headers })
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				var goals = data && data.goals ? data.goals : [];
				sel.innerHTML = '';
				var placeholder = document.createElement('option');
				placeholder.value = '';
				placeholder.textContent =
					goals.length
						? typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.pickGoal
							? rwgoTestFormGoalsI18n.pickGoal
							: '— Choose a goal —'
						: typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.noneFound
							? rwgoTestFormGoalsI18n.noneFound
							: 'No defined goals found — add markers in Elementor, Gutenberg, or a destination page.';
				sel.appendChild(placeholder);
				goals.forEach(function (g) {
					if (!g || !g.goal_id) {
						return;
					}
					var opt = document.createElement('option');
					opt.value = JSON.stringify(g);
					opt.textContent = g.goal_label || g.goal_id;
					sel.appendChild(opt);
				});
				sel.disabled = false;
				syncPanels();
				matchInitial();
			})
			.catch(function () {
				sel.innerHTML = '';
				var err = document.createElement('option');
				err.value = '';
				err.textContent =
					typeof rwgoTestFormGoalsI18n !== 'undefined' && rwgoTestFormGoalsI18n.loadFailed
						? rwgoTestFormGoalsI18n.loadFailed
						: 'Could not load goals.';
				sel.appendChild(err);
				sel.disabled = false;
			});
	}

	function matchInitial() {
		if (!hidden || !sel || getMode() !== 'defined') {
			return;
		}
		var want = hidden.value;
		if (!want) {
			want = cfg.initialDefinedJson || '';
		}
		if (!want) {
			return;
		}
		var i;
		for (i = 0; i < sel.options.length; i++) {
			var o = sel.options[i];
			if (!o.value) {
				continue;
			}
			try {
				var g = JSON.parse(o.value);
				var h = JSON.parse(want);
				if (g.goal_id && h.goal_id && g.goal_id === h.goal_id) {
					sel.selectedIndex = i;
					return;
				}
			} catch (e2) {
				/* ignore */
			}
		}
	}

	function onSelectChange() {
		if (!hidden || !sel) {
			return;
		}
		hidden.value = sel.value || '';
	}

	modeRadios.forEach(function (r) {
		r.addEventListener('change', function () {
			syncPanels();
			if (getMode() === 'defined') {
				fetchGoals();
			}
		});
	});
	if (sel) {
		sel.addEventListener('change', onSelectChange);
	}
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
