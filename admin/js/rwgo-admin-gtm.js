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

	/* Simple / Advanced on Tracking Tools */
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
})();
