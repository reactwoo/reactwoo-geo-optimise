/**
 * Product editor: suggest shopping-weather facets and tick the Geo Commerce boxes.
 *
 * Pairs with RWGA_Commerce_Weather and
 * POST /geo-ai/v1/products/{id}/suggest-weather-facets.
 * Suggestions stay in the form. The merchant reviews them and saves the product.
 */
(function () {
	'use strict';

	var cfg = window.rwgaCommerceWeather || {};
	var button = document.getElementById('rwga-suggest-weather-facets');
	if (!button || !cfg.restUrl || !window.wp || !window.wp.apiFetch) {
		return;
	}

	var statusEl = document.getElementById('rwga-suggest-weather-facets-status');
	var i18n = cfg.i18n || {};

	function setStatus(text) {
		if (statusEl) {
			statusEl.textContent = text || '';
		}
	}

	function endpoint(productId) {
		var base = String(cfg.restUrl);
		if (base.charAt(base.length - 1) !== '/') {
			base += '/';
		}
		return base + encodeURIComponent(String(productId)) + '/suggest-weather-facets';
	}

	function applyFacet(slug) {
		var safe = String(slug || '').toLowerCase().replace(/[^a-z0-9_\-]/g, '');
		if (!safe) {
			return;
		}
		var box = document.getElementById('rwgcm_weather_facet_' + safe);
		if (!box) {
			var named = document.querySelectorAll('input[name="rwgcm_weather_facets[]"]');
			for (var i = 0; i < named.length; i++) {
				if (named[i].value === safe) {
					box = named[i];
					break;
				}
			}
		}
		if (!box) {
			return;
		}
		box.checked = true;
		box.dispatchEvent(new Event('change', { bubbles: true }));
	}

	button.addEventListener('click', function () {
		var productId = button.getAttribute('data-product-id') || '';
		if (!productId) {
			setStatus(i18n.error || '');
			return;
		}
		button.disabled = true;
		setStatus(i18n.working || '');
		window.wp
			.apiFetch({
				url: endpoint(productId),
				method: 'POST',
				headers: cfg.nonce ? { 'X-WP-Nonce': cfg.nonce } : {},
			})
			.then(function (data) {
				var facets = data && Array.isArray(data.facets) ? data.facets : [];
				if (data && data.error && !facets.length) {
					setStatus(i18n.error || '');
					return;
				}
				if (!facets.length) {
					setStatus(i18n.none || '');
					return;
				}
				facets.forEach(applyFacet);
				setStatus(i18n.applied || '');
			})
			.catch(function () {
				setStatus(i18n.error || '');
			})
			.finally(function () {
				button.disabled = false;
			});
	});
})();
