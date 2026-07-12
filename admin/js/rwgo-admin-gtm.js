/**
 * GTM handoff: copy-to-clipboard, simple/advanced toggle, tests modal.
 */
(function () {
	'use strict';

	var i18n = typeof rwgoAdminGtm === 'object' && rwgoAdminGtm ? rwgoAdminGtm : {};
	function msg(k, fallback) {
		return i18n[k] || fallback;
	}

	function flash(btn, ok) {
		if (!btn || !btn.classList) {
			return;
		}
		var prev = btn.getAttribute('data-rwgo-copy-prev') || btn.textContent;
		if (!btn.getAttribute('data-rwgo-copy-prev')) {
			btn.setAttribute('data-rwgo-copy-prev', prev);
		}
		btn.textContent = ok ? msg('copied', 'Copied') : msg('copyFailed', 'Could not copy');
		btn.classList.toggle('rwgo-copy-btn--ok', ok);
		setTimeout(function () {
			btn.textContent = btn.getAttribute('data-rwgo-copy-prev') || prev;
			btn.classList.remove('rwgo-copy-btn--ok');
		}, 1600);
	}

	function getTextFromButton(btn) {
		var sel = btn.getAttribute('data-rwgo-copy-target');
		if (sel) {
			var el = document.querySelector(sel);
			if (el) {
				if (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && el.type === 'text')) {
					return el.value;
				}
				return el.textContent;
			}
		}
		var t = btn.getAttribute('data-rwgo-copy-text');
		return t ? t.replace(/\\n/g, '\n') : '';
	}

	function copyText(text, btn) {
		if (!text) {
			flash(btn, false);
			return;
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				flash(btn, true);
			}).catch(function () {
				fallbackCopy(text, btn);
			});
		} else {
			fallbackCopy(text, btn);
		}
	}

	function fallbackCopy(text, btn) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try {
			var ok = document.execCommand('copy');
			flash(btn, ok);
		} catch (e) {
			flash(btn, false);
		}
		document.body.removeChild(ta);
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.rwgo-copy-btn');
		if (!btn) {
			return;
		}
		e.preventDefault();
		copyText(getTextFromButton(btn), btn);
	});

	/* Simple / Advanced on Tracking Tools (legacy) */
	document.querySelectorAll('[data-rwgo-gtm-mode]').forEach(function (root) {
		var adv = root.querySelector('.rwgo-gtm-advanced-block');
		var btns = root.querySelectorAll('[data-rwgo-gtm-mode-btn]');
		function setMode(m) {
			root.setAttribute('data-rwgo-gtm-mode', m);
			if (adv) {
				adv.hidden = m !== 'advanced';
			}
			btns.forEach(function (b) {
				b.classList.toggle('is-active', b.getAttribute('data-rwgo-gtm-mode-btn') === m);
			});
		}
		btns.forEach(function (b) {
			b.addEventListener('click', function () {
				setMode(b.getAttribute('data-rwgo-gtm-mode-btn') || 'simple');
			});
		});
		setMode('simple');
	});

	/* Setup Guide | Technical Reference */
	document.querySelectorAll('[data-rwgo-tracking-view]').forEach(function (root) {
		var btns = root.querySelectorAll('[data-rwgo-tracking-view-btn]');
		function setView(v) {
			v = v === 'reference' ? 'reference' : 'guide';
			root.setAttribute('data-rwgo-tracking-view', v);
			btns.forEach(function (b) {
				var on = b.getAttribute('data-rwgo-tracking-view-btn') === v;
				b.classList.toggle('is-active', on);
				b.setAttribute('aria-selected', on ? 'true' : 'false');
			});
			root.querySelectorAll('[data-rwgo-tracking-panel]').forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-rwgo-tracking-panel') !== v;
			});
			try {
				var url = new URL(window.location.href);
				url.searchParams.set('rwgo_view', v);
				window.history.replaceState({}, '', url.toString());
			} catch (e) {
				/* ignore */
			}
		}
		btns.forEach(function (b) {
			b.addEventListener('click', function () {
				setView(b.getAttribute('data-rwgo-tracking-view-btn') || 'guide');
			});
		});
	});

	/* GTM modal on Tests screen */
	var gtmDlg = document.getElementById('rwgo-gtm-modal');
	var gtmBody = document.getElementById('rwgo-gtm-modal-body');
	var gtmTitle = document.getElementById('rwgo-gtm-modal-title');

	function fillGtmModal(payload) {
		if (!gtmBody || !payload || !payload.sections) {
			return;
		}
		if (gtmTitle && payload.title) {
			gtmTitle.textContent = payload.title;
		}
		var html = '';
		if (payload.intro) {
			html += '<p class="rwgo-dialog__body rwgo-gtm-modal__intro">' + escapeHtml(payload.intro) + '</p>';
		}
		if (payload.summary) {
			var s = payload.summary;
			html += '<ul class="rwgo-gtm-modal__summary">';
			html += '<li><strong>Test:</strong> ' + escapeHtml(s.test || '') + '</li>';
			html += '<li><strong>Goal:</strong> ' + escapeHtml(s.goal || '') + '</li>';
			html += '<li><strong>Control:</strong> ' + escapeHtml(s.control || '') + '</li>';
			html += '<li><strong>Variant B:</strong> ' + escapeHtml(s.variant_b || '') + '</li>';
			html += '<li><strong>Event:</strong> <code>' + escapeHtml(s.event || '') + '</code></li>';
			html += '</ul>';
		}
		payload.sections.forEach(function (sec, idx) {
			var tid = 'rwgo-gtm-modal-sec-' + idx;
			html += '<div class="rwgo-gtm-modal__sec"><h4 class="rwgo-gtm-modal__sec-title">' + escapeHtml(sec.label || '') + '</h4>';
			html += '<pre class="rwgo-code-block" id="' + tid + '">' + escapeHtml(sec.body || '') + '</pre>';
			html += '<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#' + tid + '">' + (i18n.copyLabel || 'Copy') + '</button></div>';
		});
		var caId = 'rwgo-gtm-modal-copyall';
		html += '<textarea id="' + caId + '" class="rwgo-copy-source-hidden" readonly></textarea>';
		html += '<div class="rwgo-btn-row rwgo-gtm-modal__copyall"><button type="button" class="button rwgo-btn rwgo-btn--primary rwgo-copy-btn" data-rwgo-copy-target="#' + caId + '">' + (i18n.copyAll || 'Copy all') + '</button></div>';
		gtmBody.innerHTML = html;
		var taAll = document.getElementById(caId);
		if (taAll) {
			taAll.value = payload.copyAll || '';
		}
	}

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	document.querySelectorAll('[data-rwgo-gtm-open]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (btn.disabled) {
				return;
			}
			var raw = btn.getAttribute('data-rwgo-gtm-json');
			if (!raw || !gtmDlg) {
				return;
			}
			try {
				var payload = JSON.parse(raw);
				fillGtmModal(payload);
				if (gtmDlg.showModal) {
					gtmDlg.showModal();
				}
			} catch (err) {
				/* ignore */
			}
		});
	});

	if (gtmDlg) {
		document.querySelectorAll('[data-rwgo-gtm-close]').forEach(function (b) {
			b.addEventListener('click', function () {
				if (gtmDlg.close) {
					gtmDlg.close();
				}
			});
		});
		gtmDlg.addEventListener('click', function (e) {
			if (e.target === gtmDlg && gtmDlg.close) {
				gtmDlg.close();
			}
		});
	}

	/* Live GTM target picker — cascade accounts → containers → workspaces */
	(function initGtmPicker() {
		var form = document.getElementById('rwgo-gtm-target-form');
		if (!form || form.getAttribute('data-rwgo-gtm-picker') !== '1') {
			return;
		}
		var accountEl = document.getElementById('rwgo_gtm_account_id');
		var containerEl = document.getElementById('rwgo_gtm_container_id');
		var workspaceEl = document.getElementById('rwgo_gtm_workspace_id');
		var refreshBtn = document.getElementById('rwgo-gtm-refresh-accounts');
		if (!accountEl || !containerEl || !workspaceEl) {
			return;
		}
		var ajaxUrl = i18n.ajaxUrl || '';
		var nonce = i18n.nonce || '';

		function fillSelect(sel, rows, placeholder, selected) {
			var html = '<option value="">' + escapeHtml(placeholder || '') + '</option>';
			(rows || []).forEach(function (row) {
				if (!row || !row.id) {
					return;
				}
				var selAttr = String(row.id) === String(selected || '') ? ' selected' : '';
				html += '<option value="' + escapeHtml(String(row.id)) + '"' + selAttr + '>' + escapeHtml(String(row.label || row.id)) + '</option>';
			});
			sel.innerHTML = html;
		}

		function fetchJson(action, params) {
			var q = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce);
			Object.keys(params || {}).forEach(function (k) {
				q += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
			});
			return fetch(ajaxUrl + '?' + q, { credentials: 'same-origin' }).then(function (r) {
				return r.json();
			});
		}

		function loadAccounts(keepSelection) {
			accountEl.disabled = true;
			return fetchJson('rwgo_gtm_list_accounts', {}).then(function (data) {
				accountEl.disabled = false;
				if (!data || !data.success) {
					window.alert((data && data.data && data.data.message) || msg('loadFailed', 'Could not load GTM list.'));
					return;
				}
				var selected = keepSelection ? accountEl.value : '';
				fillSelect(accountEl, data.data.accounts || [], msg('selectAccount', '— Select account —'), selected);
				if (!accountEl.value) {
					fillSelect(containerEl, [], msg('selectContainer', '— Select container —'), '');
					containerEl.disabled = true;
					fillSelect(workspaceEl, [], msg('selectWorkspace', '— Default workspace —'), '');
					workspaceEl.disabled = true;
				}
			}).catch(function () {
				accountEl.disabled = false;
				window.alert(msg('loadFailed', 'Could not load GTM list.'));
			});
		}

		function loadContainers() {
			var accountId = accountEl.value;
			fillSelect(containerEl, [], msg('loading', 'Loading…'), '');
			fillSelect(workspaceEl, [], msg('selectWorkspace', '— Default workspace —'), '');
			workspaceEl.disabled = true;
			if (!accountId) {
				containerEl.disabled = true;
				return;
			}
			containerEl.disabled = true;
			fetchJson('rwgo_gtm_list_containers', { account_id: accountId }).then(function (data) {
				containerEl.disabled = false;
				if (!data || !data.success) {
					window.alert((data && data.data && data.data.message) || msg('loadFailed', 'Could not load GTM list.'));
					return;
				}
				fillSelect(containerEl, data.data.containers || [], msg('selectContainer', '— Select container —'), '');
			}).catch(function () {
				containerEl.disabled = false;
				window.alert(msg('loadFailed', 'Could not load GTM list.'));
			});
		}

		function loadWorkspaces() {
			var accountId = accountEl.value;
			var containerId = containerEl.value;
			fillSelect(workspaceEl, [], msg('loading', 'Loading…'), '');
			if (!accountId || !containerId) {
				workspaceEl.disabled = true;
				fillSelect(workspaceEl, [], msg('selectWorkspace', '— Default workspace —'), '');
				return;
			}
			workspaceEl.disabled = true;
			fetchJson('rwgo_gtm_list_workspaces', { account_id: accountId, container_id: containerId }).then(function (data) {
				workspaceEl.disabled = false;
				if (!data || !data.success) {
					window.alert((data && data.data && data.data.message) || msg('loadFailed', 'Could not load GTM list.'));
					return;
				}
				fillSelect(workspaceEl, data.data.workspaces || [], msg('selectWorkspace', '— Default workspace —'), '');
			}).catch(function () {
				workspaceEl.disabled = false;
				window.alert(msg('loadFailed', 'Could not load GTM list.'));
			});
		}

		accountEl.addEventListener('change', loadContainers);
		containerEl.addEventListener('change', loadWorkspaces);
		if (refreshBtn) {
			refreshBtn.addEventListener('click', function () {
				loadAccounts(false);
			});
		}
		if (!accountEl.options || accountEl.options.length <= 1) {
			loadAccounts(true);
		}
	})();
})();
