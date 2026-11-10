(function () {
	'use strict';

	var root = document.getElementById('rwgo-sw');
	var cfg = window.rwgoSitewideScreen || {};
	var i18n = cfg.i18n || {};
	if (!root) {
		return;
	}

	var form = document.getElementById('rwgo-sw-form');
	var openCombo = null;
	var searchTimer = 0;
	var rowIndex = 100;

	root.classList.add('rwgo-sw--ready');

	function live(message) {
		var node = document.getElementById('rwgo-sw-live');
		if (node) {
			node.textContent = message;
		}
	}

	function icon(name) {
		var found = root.querySelector('.rwgo-sw-svg');
		return found ? found.cloneNode(true) : document.createElement('span');
	}

	function makeCombo(kind, name, placeholder, label) {
		var combo = document.createElement('div');
		combo.className = 'rwgo-sw-combo is-empty';
		combo.dataset.kind = kind;
		combo.dataset.placeholder = placeholder;
		combo.dataset.status = '';
		combo.dataset.preview = '';
		combo.dataset.hint = '';
		var input = document.createElement('input');
		input.type = 'hidden';
		input.className = 'rwgo-sw-combo__value';
		input.name = name;
		input.value = '';
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'rwgo-sw-combo__button';
		button.setAttribute('aria-haspopup', 'listbox');
		button.setAttribute('aria-expanded', 'false');
		button.setAttribute('aria-label', label);
		var text = document.createElement('span');
		text.className = 'rwgo-sw-combo__text';
		var title = document.createElement('span');
		title.className = 'rwgo-sw-combo__title';
		title.textContent = placeholder;
		text.appendChild(title);
		var chevron = document.createElement('span');
		chevron.className = 'rwgo-sw-combo__chevron';
		chevron.setAttribute('aria-hidden', 'true');
		var sample = root.querySelector('.rwgo-sw-combo__chevron');
		if (sample) {
			chevron.innerHTML = sample.innerHTML;
		}
		button.appendChild(text);
		button.appendChild(chevron);
		var panel = document.createElement('div');
		panel.className = 'rwgo-sw-panel';
		panel.hidden = true;
		var searchLabel = document.createElement('label');
		searchLabel.className = 'rwgo-sw-panel__search';
		var sr = document.createElement('span');
		sr.className = 'screen-reader-text';
		sr.textContent = label;
		var searchIcon = document.createElement('span');
		searchIcon.className = 'rwgo-sw-panel__icon';
		searchIcon.setAttribute('aria-hidden', 'true');
		var sampleIcon = root.querySelector('.rwgo-sw-panel__icon');
		if (sampleIcon) {
			searchIcon.innerHTML = sampleIcon.innerHTML;
		}
		var field = document.createElement('input');
		field.type = 'search';
		field.className = 'rwgo-sw-panel__input';
		field.placeholder = placeholder;
		field.autocomplete = 'off';
		searchLabel.appendChild(sr);
		searchLabel.appendChild(searchIcon);
		searchLabel.appendChild(field);
		var list = document.createElement('ul');
		list.className = 'rwgo-sw-panel__list';
		list.setAttribute('role', 'listbox');
		panel.appendChild(searchLabel);
		panel.appendChild(list);
		if (kind === 'variant') {
			var dup = document.createElement('button');
			dup.type = 'button';
			dup.className = 'rwgo-sw-panel__duplicate';
			dup.textContent = i18n.duplicate || 'Duplicate this page as variant';
			panel.appendChild(dup);
		}
		combo.appendChild(input);
		combo.appendChild(button);
		combo.appendChild(panel);
		return combo;
	}

	function panelOf(combo) {
		return combo.querySelector('.rwgo-sw-panel') || combo._panel || null;
	}

	function closeCombo(combo) {
		if (!combo) {
			return;
		}
		var panel = panelOf(combo);
		var button = combo.querySelector('.rwgo-sw-combo__button, .rwgo-sw-chip-add');
		if (panel) {
			panel.hidden = true;
			panel.classList.remove('is-floating');
			panel.style.position = '';
			panel.style.top = '';
			panel.style.left = '';
			panel.style.width = '';
			panel.style.maxHeight = '';
			panel.style.zIndex = '';
			combo.appendChild(panel);
			combo._panel = panel;
		}
		if (button) {
			button.setAttribute('aria-expanded', 'false');
		}
		combo.classList.remove('is-open');
		if (openCombo === combo) {
			openCombo = null;
		}
	}

	function place(combo) {
		var panel = panelOf(combo);
		var anchor = combo.querySelector('.rwgo-sw-combo__button, .rwgo-sw-chip-add');
		if (!panel || !anchor) {
			return;
		}
		var rect = anchor.getBoundingClientRect();
		var width = rect.width;
		if (combo.hasAttribute('data-chip')) {
			width = Math.max(width, 280);
		}
		document.body.appendChild(panel);
		combo._panel = panel;
		panel.hidden = false;
		panel.classList.add('is-floating');
		panel.style.position = 'fixed';
		panel.style.width = Math.round(width) + 'px';
		panel.style.zIndex = '100000';
		panel.style.maxHeight = '360px';
		var height = Math.min(panel.scrollHeight, 360);
		var below = window.innerHeight - rect.bottom - 8;
		var above = rect.top - 8;
		var flip = below < Math.min(height, 180) && above > below;
		var available = Math.max(140, flip ? above : below);
		height = Math.min(panel.scrollHeight, 360, available);
		panel.style.maxHeight = Math.round(height) + 'px';
		var used = panel.offsetHeight;
		var top = flip ? rect.top - used - 4 : rect.bottom + 4;
		if (top < 8) {
			top = 8;
		}
		if (top + used > window.innerHeight - 8) {
			top = Math.max(8, window.innerHeight - used - 8);
		}
		var left = rect.left;
		if (left + width > window.innerWidth - 8) {
			left = window.innerWidth - 8 - width;
		}
		if (left < 8) {
			left = 8;
		}
		panel.style.top = Math.round(top) + 'px';
		panel.style.left = Math.round(left) + 'px';
	}

	function sourceIdFor(combo) {
		var row = combo.closest('.rwgo-sw-match');
		if (!row) {
			return '0';
		}
		var input = row.querySelector('.rwgo-sw-match__source .rwgo-sw-combo__value');
		return input && input.value ? input.value : '0';
	}

	function renderItems(panel, items) {
		var list = panel.querySelector('.rwgo-sw-panel__list');
		list.innerHTML = '';
		if (!items.length) {
			var empty = document.createElement('li');
			empty.className = 'rwgo-sw-panel__empty';
			empty.textContent = i18n.noResults || 'No matches.';
			list.appendChild(empty);
			return;
		}
		items.forEach(function (item, index) {
			var li = document.createElement('li');
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'rwgo-sw-option' + (index === 0 ? ' is-active' : '');
			button.setAttribute('role', 'option');
			button.dataset.id = String(item.id);
			button.dataset.title = item.title || '';
			button.dataset.meta = item.meta || '';
			button.dataset.hint = item.hint || '';
			button.dataset.status = item.status || '';
			button.dataset.statusLabel = item.status_label || '';
			button.dataset.preview = item.preview_url || '';
			var copy = document.createElement('span');
			copy.className = 'rwgo-sw-option__copy';
			var title = document.createElement('span');
			title.className = 'rwgo-sw-option__title';
			title.textContent = item.title || '';
			var hint = document.createElement('span');
			hint.className = 'rwgo-sw-option__hint';
			hint.textContent = item.hint || '';
			copy.appendChild(title);
			copy.appendChild(hint);
			button.appendChild(copy);
			if (item.status_label) {
				var badge = document.createElement('span');
				badge.className = 'rwgo-sw-badge rwgo-sw-badge--' + (item.status || 'draft');
				badge.textContent = item.status_label;
				button.appendChild(badge);
			}
			li.appendChild(button);
			list.appendChild(li);
		});
	}

	function search(combo, term) {
		var panel = panelOf(combo);
		var kind = combo.dataset.kind || 'page';
		if (cfg.demoItems && cfg.demoItems[kind]) {
			var needle = term.toLowerCase();
			var found = cfg.demoItems[kind].filter(function (item) {
				var hay = ((item.title || '') + ' ' + (item.slug || '') + ' ' + (item.hint || '')).toLowerCase();
				return needle === '' || hay.indexOf(needle) !== -1;
			});
			renderItems(panel, found);
			place(combo);
			return;
		}
		var body = new URLSearchParams();
		body.set('action', cfg.searchAction || 'rwgo_sitewide_search');
		body.set('nonce', cfg.searchNonce || '');
		body.set('kind', kind);
		body.set('q', term);
		body.set('source_id', sourceIdFor(combo));
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (payload) {
			var items = payload && payload.success && payload.data && payload.data.items ? payload.data.items : [];
			renderItems(panel, items);
			place(combo);
		}).catch(function () {
			renderItems(panel, []);
			place(combo);
		});
	}

	function openCombo(combo) {
		if (openCombo && openCombo !== combo) {
			closeCombo(openCombo);
		}
		openCombo = combo;
		combo.classList.add('is-open');
		var button = combo.querySelector('.rwgo-sw-combo__button, .rwgo-sw-chip-add');
		if (button) {
			button.setAttribute('aria-expanded', 'true');
		}
		var panel = panelOf(combo);
		var field = panel.querySelector('.rwgo-sw-panel__input');
		if (field) {
			field.value = '';
		}
		search(combo, '');
		place(combo);
		if (field) {
			field.focus();
		}
	}

	function applyChoice(combo, item) {
		if (combo.hasAttribute('data-chip')) {
			addChip(combo.closest('[data-chip-list]'), item);
			closeCombo(combo);
			markDirty();
			return;
		}
		var input = combo.querySelector('.rwgo-sw-combo__value');
		var title = combo.querySelector('.rwgo-sw-combo__title');
		var text = combo.querySelector('.rwgo-sw-combo__text');
		if (input) {
			input.value = String(item.id || '');
		}
		combo.dataset.status = item.status || '';
		combo.dataset.preview = item.preview_url || '';
		combo.dataset.hint = item.hint || '';
		combo.classList.toggle('is-empty', !item.id);
		if (title) {
			title.textContent = item.title || combo.dataset.placeholder || '';
		}
		var meta = text.querySelector('.rwgo-sw-combo__meta');
		if (item.meta) {
			if (!meta) {
				meta = document.createElement('span');
				meta.className = 'rwgo-sw-combo__meta';
				text.appendChild(meta);
			}
			meta.textContent = item.meta;
		} else if (meta) {
			meta.remove();
		}
		var button = combo.querySelector('.rwgo-sw-combo__button');
		if (button) {
			button.title = ((item.title || '') + ' ' + (item.meta || '')).trim();
		}
		var row = combo.closest('.rwgo-sw-match, .rwgo-sw-pair');
		if (row && combo.closest('.rwgo-sw-match__source')) {
			var box = row.querySelector('.rwgo-sw-tick input');
			if (box) {
				box.value = item.id ? String(item.id) : '';
				box.disabled = !item.id;
			}
		}
		if (row && (combo.closest('.rwgo-sw-match__variant') || combo.parentElement === row && row.classList.contains('rwgo-sw-pair') && combo === row.querySelectorAll('.rwgo-sw-combo')[1])) {
			paintBadge(row, item);
			var link = row.querySelector('[data-preview]');
			if (link) {
				if (item.preview_url) {
					link.href = item.preview_url;
					link.hidden = false;
				} else {
					link.hidden = true;
				}
			}
		}
		closeCombo(combo);
		refreshWarnings();
		markDirty();
	}

	function paintBadge(row, item) {
		var badge = row.querySelector('[data-badge]');
		if (!badge) {
			return;
		}
		badge.className = 'rwgo-sw-badge' + (item.status ? ' rwgo-sw-badge--' + item.status : ' rwgo-sw-badge--empty');
		badge.textContent = item.status_label || '';
	}

	function addChip(list, item) {
		if (!list || !item.id) {
			return;
		}
		var name = list.getAttribute('data-name');
		var existing = list.querySelector('input[value="' + String(item.id) + '"]');
		if (existing) {
			return;
		}
		var li = document.createElement('li');
		li.className = 'rwgo-sw-chip';
		var label = document.createElement('span');
		label.textContent = item.title || '';
		var button = document.createElement('button');
		button.type = 'button';
		button.setAttribute('aria-label', (i18n.removeChip || 'Remove %s').replace('%s', item.title || ''));
		button.innerHTML = '&times;';
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = name;
		input.value = String(item.id);
		li.appendChild(label);
		li.appendChild(button);
		li.appendChild(input);
		list.querySelector('.rwgo-sw-chips__list').appendChild(li);
	}

	function activeOption(panel) {
		return panel.querySelector('.rwgo-sw-option.is-active') || panel.querySelector('.rwgo-sw-option');
	}

	function moveOption(panel, delta) {
		var options = Array.prototype.slice.call(panel.querySelectorAll('.rwgo-sw-option'));
		if (!options.length) {
			return;
		}
		var current = panel.querySelector('.rwgo-sw-option.is-active');
		var index = current ? options.indexOf(current) : -1;
		index = Math.max(0, Math.min(options.length - 1, index + delta));
		options.forEach(function (option) {
			option.classList.remove('is-active');
		});
		options[index].classList.add('is-active');
		options[index].scrollIntoView({ block: 'nearest' });
	}

	function choiceFromButton(button) {
		return {
			id: button.dataset.id,
			title: button.dataset.title,
			meta: button.dataset.meta,
			hint: button.dataset.hint,
			status: button.dataset.status,
			status_label: button.dataset.statusLabel,
			preview_url: button.dataset.preview
		};
	}

	function duplicate(combo) {
		var source = sourceIdFor(combo);
		var button = panelOf(combo).querySelector('.rwgo-sw-panel__duplicate');
		if (!source || source === '0') {
			live(i18n.duplicateFailed || 'Choose an original page first.');
			return;
		}
		if (button) {
			button.disabled = true;
		}
		var body = new URLSearchParams();
		body.set('action', cfg.duplicateAction || 'rwgo_sitewide_duplicate');
		body.set('nonce', cfg.duplicateNonce || '');
		body.set('source_id', source);
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (payload) {
			if (button) {
				button.disabled = false;
			}
			if (!payload || !payload.success || !payload.data || !payload.data.item) {
				var message = payload && payload.data && payload.data.message ? payload.data.message : (i18n.duplicateFailed || 'The variant page was not created.');
				live(message);
				return;
			}
			applyChoice(combo, payload.data.item);
		}).catch(function () {
			if (button) {
				button.disabled = false;
			}
			live(i18n.duplicateFailed || 'The variant page was not created.');
		});
	}

	function fillSlot(slot, kind, name, placeholder, label) {
		slot.appendChild(makeCombo(kind, name, placeholder, label));
	}

	function addMatch() {
		var tpl = document.getElementById('rwgo-sw-tpl-match');
		var host = root.querySelector('[data-rows="match"]');
		if (!tpl || !host) {
			return;
		}
		var node = tpl.content.firstElementChild.cloneNode(true);
		var index = rowIndex++;
		fillSlot(node.querySelector('[data-slot="source"]'), 'page', 'rwgo_sw_match[' + index + '][source]', i18n.selectPage || 'Search for a page', i18n.selectPage || 'Original page');
		fillSlot(node.querySelector('[data-slot="variant"]'), 'variant', 'rwgo_sw_match[' + index + '][variant]', i18n.selectVariant || 'Select a variant', i18n.selectVariant || 'Variant page');
		host.appendChild(node);
		var empty = root.querySelector('.rwgo-sw-empty');
		if (empty && host.children.length > 1) {
			empty.hidden = true;
		}
		markDirty();
	}

	function addProduct() {
		var tpl = document.getElementById('rwgo-sw-tpl-product');
		var host = root.querySelector('[data-rows="product"]');
		if (!tpl || !host) {
			return;
		}
		var node = tpl.content.firstElementChild.cloneNode(true);
		var index = rowIndex++;
		fillSlot(node.querySelector('[data-slot="source"]'), 'product', 'rwgo_sw_product[' + index + '][source]', i18n.selectTemplate || 'Select a template', 'Source product template');
		fillSlot(node.querySelector('[data-slot="variant"]'), 'product', 'rwgo_sw_product[' + index + '][variant]', i18n.selectTemplate || 'Select a template', 'Variant product template');
		host.appendChild(node);
		var note = root.querySelector('#rwgo-sw-products .rwgo-sw-empty');
		if (note) {
			note.hidden = true;
		}
		markDirty();
	}

	function syncLocationButtons() {
		var host = root.querySelector('[data-theme-pairs]');
		if (!host) {
			return;
		}
		var header = !!host.querySelector('[data-location="header"]');
		var footer = !!host.querySelector('[data-location="footer"]');
		var headerBtn = root.querySelector('[data-add-location="header"]');
		var footerBtn = root.querySelector('[data-add-location="footer"]');
		var nextBtn = root.querySelector('[data-add-location="next"]');
		if (headerBtn) {
			headerBtn.hidden = header;
		}
		if (footerBtn) {
			footerBtn.hidden = footer;
		}
		if (nextBtn) {
			nextBtn.hidden = !(header && footer);
		}
	}

	function addLocation(which) {
		var host = root.querySelector('[data-theme-pairs]');
		if (!host) {
			return;
		}
		if (which === 'next') {
			if (!host.querySelector('[data-location="header"]')) {
				which = 'header';
			} else if (!host.querySelector('[data-location="footer"]')) {
				which = 'footer';
			} else {
				live(i18n.locationsFull || 'Header and footer are already listed.');
				return;
			}
		}
		if (host.querySelector('[data-location="' + which + '"]')) {
			return;
		}
		var tpl = document.getElementById('rwgo-sw-tpl-' + which);
		if (!tpl) {
			return;
		}
		var node = tpl.content.firstElementChild.cloneNode(true);
		var names = which === 'footer'
			? ['rwgo_sw_footer_a', 'rwgo_sw_footer_b']
			: ['rwgo_sw_header_a', 'rwgo_sw_header_b'];
		fillSlot(node.querySelector('[data-slot="control"]'), which, names[0], i18n.selectTemplate || 'Select a template', 'Control');
		fillSlot(node.querySelector('[data-slot="variant"]'), which, names[1], i18n.selectTemplate || 'Select a template', 'Variant');
		var addRow = host.querySelector('.rwgo-sw-addrow');
		host.insertBefore(node, addRow);
		syncLocationButtons();
		markDirty();
	}

	function refreshWarnings() {
		var box = root.querySelector('[data-draft-warnings]');
		if (!box) {
			return;
		}
		box.innerHTML = '';
		root.querySelectorAll('.rwgo-sw-match').forEach(function (row) {
			var variant = row.querySelector('.rwgo-sw-match__variant .rwgo-sw-combo');
			var source = row.querySelector('.rwgo-sw-match__source .rwgo-sw-combo');
			if (!variant || !source) {
				return;
			}
			var status = variant.dataset.status || '';
			if (!status || status === 'publish') {
				return;
			}
			var pattern = status === 'draft' ? (i18n.draftWarning || '%1$s is still a draft. Visitors in variant B will see the original %2$s until you publish it.') : (i18n.unpublishedWarning || '%1$s is not published. Visitors in variant B will see the original %2$s until you publish it.');
			var text = pattern.replace('%1$s', variant.querySelector('.rwgo-sw-combo__title').textContent).replace('%2$s', source.querySelector('.rwgo-sw-combo__title').textContent);
			var p = document.createElement('p');
			var sample = root.querySelector('.rwgo-sw-warnings .rwgo-sw-svg, .rwgo-sw-info .rwgo-sw-svg');
			if (sample) {
				p.appendChild(sample.cloneNode(true));
			}
			var span = document.createElement('span');
			span.textContent = text;
			p.appendChild(span);
			box.appendChild(p);
		});
		box.hidden = box.children.length === 0;
	}

	function snapshot() {
		if (!form) {
			return '';
		}
		var data = new FormData(form);
		var parts = [];
		data.forEach(function (value, key) {
			if (key === 'rwgo_sw_do_bulk') {
				return;
			}
			parts.push(key + '=' + value);
		});
		parts.sort();
		return parts.join('&');
	}

	var cleanSnapshot = snapshot();

	function markDirty() {
		root.classList.toggle('is-dirty', snapshot() !== cleanSnapshot);
	}

	function paintStatus() {
		var select = document.getElementById('rwgo_sw_status');
		var toggle = root.querySelector('.rwgo-sw-switch');
		var word = root.querySelector('[data-status-word]');
		var pill = root.querySelector('[data-pill]');
		if (!select || !toggle) {
			return;
		}
		var value = select.value;
		var active = value === 'active';
		toggle.setAttribute('aria-checked', active ? 'true' : 'false');
		select.classList.toggle('is-visible-select', !active);
		if (word) {
			word.hidden = !active;
			word.dataset.state = value;
			word.textContent = i18n.active || 'Active';
		}
		if (pill) {
			pill.dataset.pill = value;
			pill.textContent = value === 'active' ? (i18n.active || 'Active') : (value === 'ended' ? (i18n.ended || 'Ended') : (i18n.paused || 'Paused'));
		}
		var lede = root.querySelector('[data-overview-lede]');
		if (lede) {
			lede.textContent = active
				? 'Turn the test on when the variants are ready. Saving does not publish drafts.'
				: 'Keep the test paused while you set up the first page matches.';
		}
	}

	function percentText(template, value) {
		return String(template || '').replace('%d%%', value + '%').replace('%d', String(value));
	}

	var range = document.getElementById('rwgo_sw_weight');
	if (range) {
		range.addEventListener('input', function () {
			var value = Number(range.value);
			range.style.setProperty('--rwgo-split', value + '%');
			var control = root.querySelector('[data-split-control]');
			var variant = root.querySelector('[data-split-variant]');
			if (control) {
				control.textContent = percentText(i18n.controlPercent, value);
			}
			if (variant) {
				variant.textContent = percentText(i18n.variantPercent, 100 - value);
			}
			markDirty();
		});
	}

	var statusSelect = document.getElementById('rwgo_sw_status');
	var statusSwitch = root.querySelector('.rwgo-sw-switch');
	if (statusSwitch && statusSelect) {
		statusSwitch.addEventListener('click', function () {
			statusSelect.value = statusSelect.value === 'active' ? 'paused' : 'active';
			paintStatus();
			markDirty();
		});
		statusSelect.addEventListener('change', function () {
			paintStatus();
			markDirty();
		});
		paintStatus();
	}

	document.addEventListener('click', function (event) {
		var chipRemove = event.target.closest('.rwgo-sw-chip button');
		if (chipRemove && root.contains(chipRemove)) {
			chipRemove.parentElement.remove();
			markDirty();
			return;
		}
		var option = event.target.closest('.rwgo-sw-option');
		if (option && openCombo) {
			applyChoice(openCombo, choiceFromButton(option));
			return;
		}
		var duplicateButton = event.target.closest('.rwgo-sw-panel__duplicate');
		if (duplicateButton && openCombo) {
			duplicate(openCombo);
			return;
		}
		var opener = event.target.closest('.rwgo-sw-combo__button, .rwgo-sw-chip-add');
		if (opener && root.contains(opener)) {
			var combo = opener.closest('.rwgo-sw-combo');
			if (combo.classList.contains('is-open')) {
				closeCombo(combo);
			} else {
				openCombo(combo);
			}
			return;
		}
		if (openCombo && !event.target.closest('.rwgo-sw-panel') && !event.target.closest('.rwgo-sw-combo')) {
			closeCombo(openCombo);
		}
		var remove = event.target.closest('[data-remove]');
		if (remove && root.contains(remove)) {
			var row = remove.closest('.rwgo-sw-match, .rwgo-sw-pair');
			if (row) {
				row.remove();
				refreshWarnings();
				markDirty();
			}
			return;
		}
		var removeLocation = event.target.closest('[data-remove-location]');
		if (removeLocation && root.contains(removeLocation)) {
			var block = removeLocation.closest('.rwgo-sw-location');
			if (block) {
				block.remove();
				syncLocationButtons();
				markDirty();
			}
			return;
		}
		var add = event.target.closest('[data-add]');
		if (add && root.contains(add)) {
			if (add.getAttribute('data-add') === 'product') {
				addProduct();
			} else {
				addMatch();
			}
			return;
		}
		var addLocationButton = event.target.closest('[data-add-location]');
		if (addLocationButton && root.contains(addLocationButton)) {
			addLocation(addLocationButton.getAttribute('data-add-location'));
		}
	});

	document.addEventListener('input', function (event) {
		if (event.target.classList && event.target.classList.contains('rwgo-sw-panel__input') && openCombo) {
			window.clearTimeout(searchTimer);
			var term = event.target.value;
			searchTimer = window.setTimeout(function () {
				search(openCombo, term);
			}, 180);
			return;
		}
		if (root.contains(event.target)) {
			markDirty();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (!openCombo) {
			return;
		}
		var panel = panelOf(openCombo);
		if (event.key === 'Escape') {
			var button = openCombo.querySelector('.rwgo-sw-combo__button, .rwgo-sw-chip-add');
			closeCombo(openCombo);
			if (button) {
				button.focus();
			}
			event.preventDefault();
		} else if (event.key === 'ArrowDown') {
			moveOption(panel, 1);
			event.preventDefault();
		} else if (event.key === 'ArrowUp') {
			moveOption(panel, -1);
			event.preventDefault();
		} else if (event.key === 'Enter') {
			var current = activeOption(panel);
			if (current) {
				applyChoice(openCombo, choiceFromButton(current));
				event.preventDefault();
			}
		}
	});

	window.addEventListener('resize', function () {
		if (openCombo) {
			place(openCombo);
		}
	});
	window.addEventListener('scroll', function () {
		if (openCombo) {
			place(openCombo);
		}
	}, true);

	var sections = ['overview', 'coverage', 'pages', 'header', 'products', 'goals'].map(function (id) {
		return document.getElementById('rwgo-sw-' + id);
	}).filter(Boolean);
	var navLinks = root.querySelectorAll('.rwgo-sw-nav a');

	function spy() {
		var current = sections[0];
		sections.forEach(function (section) {
			if (section.getBoundingClientRect().top < 150) {
				current = section;
			}
		});
		navLinks.forEach(function (link) {
			link.classList.toggle('is-active', current && link.getAttribute('href') === '#' + current.id);
		});
	}
	window.addEventListener('scroll', spy, { passive: true });
	spy();
	syncLocationButtons();
	refreshWarnings();
	cleanSnapshot = snapshot();
	root.classList.remove('is-dirty');
})();
