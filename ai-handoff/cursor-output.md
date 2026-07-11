# Cursor output

## Status

done

## Task

AI Review workspace UX hierarchy pass (Optimise 0.4.72). WordPress AI transport left untouched.

## Before → after hierarchy

### Before
1. Assistant + glossary chips (audit types / targets / examples)
2. Refine setup **open** (target, audience, device first)
3. Audit scope checkboxes (Full + 4 categories all checked)
4. Start review inside `<details>`
5. Category cards + score sidebar + Key recommendations + findings

### After
1. Assistant (short welcome) + **Try an example** disclosure
2. **Review types** (Full review as select-all; individuals exclusive of Full)
3. Detected setup summary + **Run review** (outside Refine)
4. **Refine setup** collapsed (content type + relevant selector; Audience & device nested optional)
5. After review: compact current-review bar → overall/category summary → findings (no Key recommendations card)

## Files changed

- `merged-geo-ai/admin/views/partials/ux-reviewer-workspace.php` — reorder, collapse, results compact, finding action hierarchy
- `merged-geo-ai/admin/js/rwga-ux-reviewer-assistant.js` — no auto-open refine; summary; Full sync; Adjust setup; validation opens refine
- `merged-geo-ai/admin/css/rwga-ux-reviewer.css` — `[hidden]` override for `.rwga-ux-target-field`; denser layout
- `merged-geo-ai/includes/class-rwga-ux-reviewer-ui.php` — labels, welcome, examples-only hints, i18n
- `includes/class-rwgo-admin.php` — Experiments/Reports `is_section_nav => false`
- `admin/css/rwgo-admin.css` — hub AI review padding 18–20px
- Version refs → **0.4.72**

## Not changed

- AI generation transports, quota, experiments, goals, promotion, Geo Core evaluator
- Form action / nonce / field names (`audit_scopes[]`, `page_id`, etc.)
- Geo Core navigation CSS

## Commands run

- `php -l` on workspace PHP, UX UI class, RWGO_Admin — OK
- `node --check` on assistant JS — OK
- `npm run package:zip` — OK → `reactwoo-geo-optimise-0.4.72.zip`

## Acceptance notes

- Target field visibility: confirmed `.rwgc-wrap.rwgc-suite .rwgc-field { display: flex }` overrides native `[hidden]`; fixed with scoped `display: none !important`.
- Outer Experiences strip: legacy Experiments/Reports routes remain registered but excluded from section nav; Optimise hub tabs are primary.
- Core strip may still show other Experiences items (Dynamic content, Geo content, Optimise) — no Core change required for this pass.
