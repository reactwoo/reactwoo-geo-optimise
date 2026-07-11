/**
 * AI UX Reviewer — chat-style setup assistant (local phrase detection).
 */
(function () {
	'use strict';

	var cfg = window.rwgaUxReviewerAssistant || {};
	var i18n = cfg.i18n || {};
	var hasInteracted = false;

	function esc( text ) {
		return String( text || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text != null ) {
			node.textContent = text;
		}
		return node;
	}

	function assistantBubble( html ) {
		var wrap = el( 'div', 'rwgc-targeting-assistant__bubble rwgc-targeting-assistant__bubble--assistant' );
		wrap.appendChild( el( 'span', 'rwgc-targeting-assistant__avatar', 'AI' ) );
		var body = el( 'div', 'rwgc-targeting-assistant__bubble-body' );
		body.innerHTML = html;
		wrap.appendChild( body );
		return wrap;
	}

	function userBubble( text, chips ) {
		var wrap = el( 'div', 'rwgc-targeting-assistant__bubble rwgc-targeting-assistant__bubble--user' );
		wrap.appendChild( el( 'div', 'rwgc-targeting-assistant__bubble-body', text ) );
		if ( chips && chips.length ) {
			wrap.appendChild( el( 'div', 'rwgc-targeting-assistant__detected-label', i18n.detectedLabel || 'Detected:' ) );
			var chipWrap = el( 'div', 'rwgc-targeting-assistant__chips' );
			chips.forEach( function ( chip ) {
				var chipEl = el( 'span', 'rwgc-targeting-chip rwgc-targeting-chip--' + ( chip.type || 'intent' ), chip.label || '' );
				chipWrap.appendChild( chipEl );
			} );
			wrap.appendChild( chipWrap );
		}
		return wrap;
	}

	function phraseContainsTerm( lower, term ) {
		term = String( term || '' ).toLowerCase().trim();
		if ( ! term ) {
			return false;
		}
		if ( term.indexOf( ' ' ) !== -1 ) {
			return lower.indexOf( term ) !== -1;
		}
		return new RegExp( '\\b' + term.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '\\b' ).test( lower );
	}

	function phraseMatchesAny( lower, terms ) {
		if ( ! Array.isArray( terms ) ) {
			return false;
		}
		var i;
		for ( i = 0; i < terms.length; i++ ) {
			if ( phraseContainsTerm( lower, terms[ i ] ) ) {
				return true;
			}
		}
		return false;
	}

	function detectAuditScopes( lower, rules, allScopes ) {
		if ( phraseMatchesAny( lower, rules.fullAudit ) ) {
			return allScopes.slice();
		}
		var scopes = [];
		var scopeKeys = rules.scopes ? Object.keys( rules.scopes ) : [];
		scopeKeys.forEach( function ( slug ) {
			if ( phraseMatchesAny( lower, rules.scopes[ slug ] ) ) {
				scopes.push( slug );
			}
		} );
		if ( ! scopes.length ) {
			if (
				phraseMatchesAny( lower, rules.genericReview )
				|| phraseMatchesAny( lower, rules.genericUx )
			) {
				return allScopes.slice();
			}
		}
		return scopes;
	}

	function detectTarget( lower, rules, pages, frontPageId, targetLabels ) {
		var targetType = 'page';
		var pageId = 0;
		var productId = 0;
		var chips = [];
		var matched = false;
		var pairs = [];

		if ( rules.targets ) {
			Object.keys( rules.targets ).forEach( function ( slug ) {
				( rules.targets[ slug ] || [] ).forEach( function ( term ) {
					pairs.push( { slug: slug, term: term } );
				} );
			} );
		}
		pairs.sort( function ( a, b ) {
			return String( b.term ).length - String( a.term ).length;
		} );

		pairs.some( function ( row ) {
			if ( ! phraseContainsTerm( lower, row.term ) ) {
				return false;
			}
			var slug = row.slug;
			var label = ( targetLabels && targetLabels[ slug ] ) ? targetLabels[ slug ] : slug;
			if ( slug === 'site' || slug === 'site_wide' ) {
				targetType = 'site';
				pageId = frontPageId;
			} else if ( slug === 'product' ) {
				targetType = 'product';
			} else if ( slug === 'variant' ) {
				targetType = 'variant';
			} else if ( slug === 'rule' ) {
				targetType = 'rule';
			}
			chips.push( { label: label, type: 'target' } );
			matched = true;
			return true;
		} );

		if ( phraseContainsTerm( lower, 'landing page' ) && ! matched ) {
			chips.push( { label: 'Landing page', type: 'page' } );
		}

		var i;
		for ( i = 0; i < pages.length; i++ ) {
			var page = pages[ i ];
			var title = String( page.title || '' ).trim();
			if ( ! title ) {
				continue;
			}
			if ( lower.indexOf( title.toLowerCase() ) !== -1 ) {
				if ( page.type === 'product' ) {
					targetType = 'product';
					productId = parseInt( page.id, 10 ) || 0;
				} else {
					targetType = 'page';
					pageId = parseInt( page.id, 10 ) || 0;
				}
				chips.push( { label: title, type: 'page' } );
				break;
			}
		}

		return {
			targetType: targetType,
			pageId: pageId,
			productId: productId,
			chips: chips,
		};
	}

	function parsePhrase( text ) {
		var phrase = String( text || '' ).trim();
		var lower = phrase.toLowerCase();
		var allScopes = Array.isArray( cfg.allScopes ) ? cfg.allScopes.slice() : [];
		var auditLabels = cfg.auditLabels || {};
		var pages = Array.isArray( cfg.pages ) ? cfg.pages : [];
		var frontPageId = parseInt( cfg.frontPageId, 10 ) || 0;
		var rules = cfg.detectionRules || {};
		var targetLabels = cfg.targetLabels || {};

		if ( /https?:\/\//i.test( phrase ) ) {
			return {
				blocked: true,
				blockReason: i18n.externalBlocked || 'UX reviews run on pages inside your WordPress site only — not external URLs.',
				chips: [],
				auditScopes: [],
				targetType: '',
				pageId: 0,
				productId: 0,
			};
		}

		var scopes = detectAuditScopes( lower, rules, allScopes );
		var target = detectTarget( lower, rules, pages, frontPageId, targetLabels );
		var chips = target.chips ? target.chips.slice() : [];

		scopes.forEach( function ( scope ) {
			if ( auditLabels[ scope ] ) {
				chips.push( { label: auditLabels[ scope ], type: 'audit' } );
			}
		} );

		return {
			blocked: false,
			blockReason: '',
			targetType: target.targetType,
			pageId: target.pageId,
			productId: target.productId,
			auditScopes: scopes,
			chips: chips,
		};
	}

	function getFormEls() {
		return {
			targetType: document.getElementById( 'rwga_ux_target_type' ),
			pageSelect: document.getElementById( 'rwga_ux_page_select' ),
			productSelect: document.getElementById( 'rwga_ux_product_select' ),
			variantInput: document.getElementById( 'rwga_ux_variant_select' ),
			ruleInput: document.getElementById( 'rwga_ux_rule_select' ),
			audience: document.getElementById( 'rwga_ux_audience' ),
			device: document.getElementById( 'rwga_ux_device' ),
			hiddenPage: document.getElementById( 'rwga_ux_hidden_page_id' ),
			hiddenProduct: document.getElementById( 'rwga_ux_hidden_product_id' ),
			hiddenVariant: document.getElementById( 'rwga_ux_hidden_variant_id' ),
			hiddenRule: document.getElementById( 'rwga_ux_hidden_rule_id' ),
			fullScope: document.getElementById( 'rwga_ux_scope_full' ),
			scopeItems: document.querySelectorAll( '.rwga-ux-reviewer__scope-item' ),
			refine: document.getElementById( 'rwga-ux-refine-setup' ),
			summaryValue: document.getElementById( 'rwga-ux-setup-summary-value' ),
			form: document.getElementById( 'rwga-ux-review-setup' ),
			resetBtn: document.getElementById( 'rwga-ux-assistant-reset' ),
		};
	}

	function markInteracted() {
		hasInteracted = true;
		var els = getFormEls();
		if ( els.resetBtn ) {
			els.resetBtn.hidden = false;
			els.resetBtn.classList.remove( 'rwga-ux-assistant-reset--hidden' );
		}
	}

	function setAdjustExpanded( expanded ) {
		document.querySelectorAll( '[aria-controls="rwga-ux-refine-setup"]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		} );
	}

	function openRefine( focusField ) {
		var els = getFormEls();
		if ( ! els.refine ) {
			return;
		}
		els.refine.open = true;
		setAdjustExpanded( true );
		if ( els.form && els.form.hidden ) {
			els.form.hidden = false;
			els.form.classList.remove( 'rwga-ux-reviewer__setup--has-results' );
		}
		window.setTimeout( function () {
			var target = focusField || ( els.targetType || els.refine );
			if ( target && typeof target.focus === 'function' ) {
				target.focus();
			}
			els.refine.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}, 40 );
	}

	function closeRefine() {
		var els = getFormEls();
		if ( els.refine ) {
			els.refine.open = false;
		}
		setAdjustExpanded( false );
	}

	function syncScopeSelectionClasses() {
		var els = getFormEls();
		document.querySelectorAll( '.rwga-ux-reviewer__scope-option' ).forEach( function ( label ) {
			var input = label.querySelector( 'input[type="checkbox"]' );
			label.classList.toggle( 'is-selected', !!( input && input.checked ) );
		} );
		if ( els.fullScope && els.fullScope.checked ) {
			document.querySelectorAll( '.rwga-ux-reviewer__scope-item' ).forEach( function ( item ) {
				var label = item.closest( '.rwga-ux-reviewer__scope-option' );
				if ( label ) {
					label.classList.remove( 'is-selected' );
				}
			} );
		}
	}

	function syncScopeFull() {
		var els = getFormEls();
		if ( ! els.fullScope ) {
			return;
		}
		var allChecked = true;
		var anyChecked = false;
		els.scopeItems.forEach( function ( item ) {
			if ( item.checked ) {
				anyChecked = true;
			} else {
				allChecked = false;
			}
		} );
		if ( allChecked && anyChecked ) {
			els.fullScope.checked = true;
			els.scopeItems.forEach( function ( item ) {
				item.checked = false;
			} );
		} else if ( anyChecked ) {
			els.fullScope.checked = false;
		}
		syncScopeSelectionClasses();
		updateSetupSummary();
	}

	function applyFullScope( checked ) {
		var els = getFormEls();
		if ( ! els.fullScope ) {
			return;
		}
		els.fullScope.checked = !!checked;
		if ( checked ) {
			els.scopeItems.forEach( function ( item ) {
				item.checked = false;
			} );
		}
		syncScopeSelectionClasses();
		updateSetupSummary();
	}

	function syncTargetFields() {
		var els = getFormEls();
		var type = els.targetType ? els.targetType.value : 'page';
		var fields = document.querySelectorAll( '.rwga-ux-target-field' );
		fields.forEach( function ( field ) {
			var allowed = ( field.getAttribute( 'data-target' ) || '' ).split( /\s+/ );
			field.hidden = allowed.indexOf( type ) === -1;
		} );
		if ( els.hiddenPage ) {
			els.hiddenPage.value = '0';
		}
		if ( els.hiddenProduct ) {
			els.hiddenProduct.value = '0';
		}
		if ( els.hiddenVariant ) {
			els.hiddenVariant.value = '0';
		}
		if ( els.hiddenRule ) {
			els.hiddenRule.value = '';
		}
		if ( type === 'page' && els.pageSelect && els.hiddenPage ) {
			els.hiddenPage.value = els.pageSelect.value || '0';
		}
		if ( type === 'site' && els.hiddenPage ) {
			var frontPageId = parseInt( cfg.frontPageId, 10 ) || 0;
			els.hiddenPage.value = frontPageId > 0 ? String( frontPageId ) : ( els.pageSelect ? els.pageSelect.value : '0' );
			if ( els.pageSelect && frontPageId > 0 ) {
				els.pageSelect.value = String( frontPageId );
			}
		}
		if ( type === 'product' && els.productSelect && els.hiddenProduct ) {
			els.hiddenProduct.value = els.productSelect.value || '0';
		}
		if ( type === 'variant' && els.variantInput && els.hiddenVariant ) {
			els.hiddenVariant.value = els.variantInput.value || '0';
		}
		if ( type === 'rule' && els.ruleInput && els.hiddenRule ) {
			els.hiddenRule.value = els.ruleInput.value || '';
		}
		updateSetupSummary();
	}

	function selectedOptionLabel( select ) {
		if ( ! select || ! select.options || select.selectedIndex < 0 ) {
			return '';
		}
		return String( select.options[ select.selectedIndex ].text || '' ).trim();
	}

	function reviewTypeSummary() {
		var els = getFormEls();
		var auditLabels = cfg.auditLabels || {};
		if ( els.fullScope && els.fullScope.checked ) {
			return i18n.fullReview || 'Full review';
		}
		var labels = [];
		els.scopeItems.forEach( function ( item ) {
			if ( item.checked ) {
				labels.push( auditLabels[ item.value ] || item.value );
			}
		} );
		return labels.length ? labels.join( ', ' ) : ( i18n.fullReview || 'Full review' );
	}

	function targetSummary() {
		var els = getFormEls();
		var type = els.targetType ? els.targetType.value : 'page';
		if ( type === 'site' ) {
			return i18n.homepage || 'Homepage';
		}
		if ( type === 'page' && els.pageSelect ) {
			var pageLabel = selectedOptionLabel( els.pageSelect );
			var pageVal = parseInt( els.pageSelect.value, 10 ) || 0;
			if ( pageVal > 0 && pageLabel && pageLabel.indexOf( '—' ) !== 0 ) {
				return pageLabel;
			}
			return i18n.pageFallback || 'Page';
		}
		if ( type === 'product' && els.productSelect ) {
			var productLabel = selectedOptionLabel( els.productSelect );
			var productVal = parseInt( els.productSelect.value, 10 ) || 0;
			if ( productVal > 0 && productLabel && productLabel.indexOf( '—' ) !== 0 ) {
				return productLabel;
			}
			return i18n.productFallback || 'Product';
		}
		if ( type === 'variant' && els.variantInput ) {
			var variantId = String( els.variantInput.value || '' ).trim();
			return variantId ? ( ( i18n.variantFallback || 'Variant' ) + ' #' + variantId ) : ( i18n.variantFallback || 'Variant' );
		}
		if ( type === 'rule' && els.ruleInput ) {
			var ruleId = String( els.ruleInput.value || '' ).trim();
			return ruleId ? ( ( i18n.ruleFallback || 'Rule' ) + ' #' + ruleId ) : ( i18n.ruleFallback || 'Rule' );
		}
		return i18n.homepage || 'Homepage';
	}

	function updateSetupSummary() {
		var els = getFormEls();
		if ( ! els.summaryValue ) {
			return;
		}
		var audience = selectedOptionLabel( els.audience ) || ( i18n.allVisitors || 'All visitors' );
		var device = selectedOptionLabel( els.device ) || ( i18n.desktop || 'Desktop' );
		els.summaryValue.textContent = [
			reviewTypeSummary(),
			targetSummary(),
			audience,
			device,
		].join( ' · ' );
	}

	function detectionNeedsRefine( parsed ) {
		if ( ! parsed.auditScopes || ! parsed.auditScopes.length ) {
			return true;
		}
		var type = parsed.targetType || 'page';
		if ( type === 'page' && ! parsed.pageId ) {
			return true;
		}
		if ( type === 'product' && ! parsed.productId ) {
			return true;
		}
		if ( type === 'variant' || type === 'rule' ) {
			return true;
		}
		return false;
	}

	function applyDetection( parsed ) {
		var els = getFormEls();
		if ( els.targetType && parsed.targetType ) {
			els.targetType.value = parsed.targetType;
		}
		if ( parsed.pageId && els.pageSelect ) {
			els.pageSelect.value = String( parsed.pageId );
		}
		if ( parsed.productId && els.productSelect ) {
			els.productSelect.value = String( parsed.productId );
		}
		if ( parsed.auditScopes && parsed.auditScopes.length ) {
			var allScopes = Array.isArray( cfg.allScopes ) ? cfg.allScopes : [];
			var isFull = parsed.auditScopes.length === allScopes.length
				&& allScopes.every( function ( slug ) {
					return parsed.auditScopes.indexOf( slug ) !== -1;
				} );
			if ( isFull ) {
				applyFullScope( true );
			} else {
				els.fullScope && ( els.fullScope.checked = false );
				els.scopeItems.forEach( function ( item ) {
					item.checked = parsed.auditScopes.indexOf( item.value ) !== -1;
				} );
				syncScopeSelectionClasses();
			}
		}
		syncTargetFields();
		if ( detectionNeedsRefine( parsed ) ) {
			openRefine( els.targetType );
		} else {
			closeRefine();
		}
	}

	function appendAssistant( html ) {
		var thread = document.getElementById( 'rwga-ux-assistant-thread' );
		if ( ! thread ) {
			return;
		}
		thread.appendChild( assistantBubble( html ) );
		thread.scrollTop = thread.scrollHeight;
	}

	function appendUser( text, chips ) {
		var thread = document.getElementById( 'rwga-ux-assistant-thread' );
		if ( ! thread ) {
			return;
		}
		thread.appendChild( userBubble( text, chips ) );
		thread.scrollTop = thread.scrollHeight;
	}

	function resetThread() {
		var thread = document.getElementById( 'rwga-ux-assistant-thread' );
		if ( ! thread ) {
			return;
		}
		thread.innerHTML = '';
		appendAssistant( '<p>' + esc( i18n.welcome || '' ) + '</p>' );
		hasInteracted = false;
		var els = getFormEls();
		if ( els.resetBtn ) {
			els.resetBtn.hidden = true;
			els.resetBtn.classList.add( 'rwga-ux-assistant-reset--hidden' );
		}
	}

	function sendPhrase( text ) {
		var phrase = String( text || '' ).trim();
		if ( ! phrase ) {
			return;
		}
		markInteracted();
		var detecting = document.getElementById( 'rwga-ux-assistant-detecting' );
		if ( detecting ) {
			detecting.classList.remove( 'rwgc-is-hidden' );
		}
		window.setTimeout( function () {
			var parsed = parsePhrase( phrase );
			appendUser( phrase, parsed.chips );
			if ( parsed.blocked ) {
				appendAssistant( '<p>' + esc( parsed.blockReason ) + '</p>' );
			} else if ( ! parsed.auditScopes.length ) {
				appendAssistant( '<p>' + esc( i18n.noScopes || '' ) + '</p>' );
				openRefine();
			} else {
				applyDetection( parsed );
				appendAssistant( '<p>' + esc( i18n.applied || '' ) + '</p>' );
			}
			if ( detecting ) {
				detecting.classList.add( 'rwgc-is-hidden' );
			}
		}, 180 );
	}

	function buildHintCloud( hintsEl, phraseInput ) {
		if ( ! hintsEl ) {
			return;
		}
		hintsEl.innerHTML = '';
		var examples = [];
		( cfg.keywordHints || [] ).forEach( function ( group ) {
			( group.items || [] ).forEach( function ( item ) {
				examples.push( item );
			} );
		} );
		examples = examples.slice( 0, 3 );
		if ( ! examples.length ) {
			return;
		}

		var details = el( 'details', 'rwga-ux-reviewer__examples' );
		var summary = el( 'summary', '', i18n.tryExample || 'Try an example' );
		details.appendChild( summary );
		var list = el( 'div', 'rwga-ux-reviewer__examples-list' );
		examples.forEach( function ( item ) {
			var chip = el( 'button', 'rwgc-targeting-assistant__hint-chip', item.label || item.text || '' );
			chip.type = 'button';
			chip.addEventListener( 'click', function () {
				var insert = item.insert || item.label || item.text || '';
				phraseInput.value = insert;
				phraseInput.focus();
				markInteracted();
			} );
			list.appendChild( chip );
		} );
		details.appendChild( list );
		hintsEl.appendChild( details );
	}

	function validateBeforeSubmit( ev ) {
		var els = getFormEls();
		var anyScope = !!( els.fullScope && els.fullScope.checked );
		els.scopeItems.forEach( function ( item ) {
			if ( item.checked ) {
				anyScope = true;
			}
		} );
		if ( ! anyScope ) {
			ev.preventDefault();
			window.alert( i18n.selectAudit || 'Select at least one review type.' );
			openRefine();
			return false;
		}

		var type = els.targetType ? els.targetType.value : 'page';
		var unresolved = false;
		if ( type === 'page' && ( ! els.pageSelect || ! parseInt( els.pageSelect.value, 10 ) ) ) {
			unresolved = true;
		}
		if ( type === 'product' && ( ! els.productSelect || ! parseInt( els.productSelect.value, 10 ) ) ) {
			unresolved = true;
		}
		if ( type === 'variant' && ( ! els.variantInput || ! String( els.variantInput.value || '' ).trim() ) ) {
			unresolved = true;
		}
		if ( type === 'rule' && ( ! els.ruleInput || ! String( els.ruleInput.value || '' ).trim() ) ) {
			unresolved = true;
		}
		if ( unresolved ) {
			ev.preventDefault();
			openRefine( type === 'product' ? els.productSelect : ( type === 'variant' ? els.variantInput : ( type === 'rule' ? els.ruleInput : els.pageSelect ) ) );
			window.alert( i18n.selectTarget || 'Choose a review target in Refine setup.' );
			return false;
		}
		return true;
	}

	function initResultsControls() {
		var runAnother = document.getElementById( 'rwga-ux-run-another' );
		var adjustSetup = document.getElementById( 'rwga-ux-adjust-setup' );
		var openRefineBtn = document.getElementById( 'rwga-ux-open-refine' );
		var form = document.getElementById( 'rwga-ux-review-setup' );

		function showSetup( openManual ) {
			if ( form ) {
				form.hidden = false;
				form.classList.remove( 'rwga-ux-reviewer__setup--has-results' );
				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
			if ( openManual ) {
				openRefine();
			} else {
				closeRefine();
			}
		}

		runAnother && runAnother.addEventListener( 'click', function () {
			showSetup( false );
		} );
		adjustSetup && adjustSetup.addEventListener( 'click', function () {
			showSetup( true );
		} );
		openRefineBtn && openRefineBtn.addEventListener( 'click', function () {
			var refine = document.getElementById( 'rwga-ux-refine-setup' );
			if ( refine && refine.open ) {
				closeRefine();
			} else {
				openRefine();
			}
		} );
	}

	function init() {
		var composer = document.getElementById( 'rwga-ux-assistant-composer' );
		var phraseInput = document.getElementById( 'rwga-ux-assistant-phrase' );
		var sendBtn = document.getElementById( 'rwga-ux-assistant-send' );
		var resetBtn = document.getElementById( 'rwga-ux-assistant-reset' );
		var hints = document.getElementById( 'rwga-ux-assistant-hints' );
		var form = document.getElementById( 'rwga-ux-review-setup' );

		initResultsControls();

		if ( ! composer || ! phraseInput ) {
			return;
		}

		resetThread();
		buildHintCloud( hints, phraseInput );

		function submitPhrase() {
			var text = phraseInput.value;
			phraseInput.value = '';
			sendPhrase( text );
		}

		sendBtn && sendBtn.addEventListener( 'click', submitPhrase );
		phraseInput.addEventListener( 'keydown', function ( ev ) {
			if ( ev.key === 'Enter' && ! ev.shiftKey ) {
				ev.preventDefault();
				submitPhrase();
			}
		} );
		phraseInput.addEventListener( 'input', function () {
			if ( phraseInput.value ) {
				markInteracted();
			}
		} );
		resetBtn && resetBtn.addEventListener( 'click', function () {
			phraseInput.value = '';
			resetThread();
		} );

		if ( form ) {
			var els = getFormEls();
			if ( els.targetType ) {
				els.targetType.addEventListener( 'change', syncTargetFields );
			}
			[ els.pageSelect, els.productSelect, els.variantInput, els.ruleInput, els.audience, els.device ].forEach( function ( node ) {
				if ( ! node ) {
					return;
				}
				node.addEventListener( 'change', syncTargetFields );
				node.addEventListener( 'input', syncTargetFields );
			} );
			form.addEventListener( 'submit', function ( ev ) {
				syncTargetFields();
				validateBeforeSubmit( ev );
			} );
			if ( els.fullScope ) {
				els.fullScope.addEventListener( 'change', function () {
					applyFullScope( els.fullScope.checked );
					markInteracted();
				} );
			}
			els.scopeItems.forEach( function ( item ) {
				item.addEventListener( 'change', function () {
					if ( item.checked && els.fullScope ) {
						els.fullScope.checked = false;
					}
					syncScopeFull();
					markInteracted();
				} );
			} );
			if ( els.refine ) {
				els.refine.addEventListener( 'toggle', function () {
					setAdjustExpanded( !!els.refine.open );
				} );
			}
			syncScopeSelectionClasses();
			syncTargetFields();
			updateSetupSummary();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
