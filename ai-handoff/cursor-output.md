# Cursor output — Tracking setup UX

## Status

done

## Root cause

Tracking Tools was built as a single developer handoff document (connection + events + triggers + variables + GA4 + examples + per-test cards). Users could not tell what was done, what was next, or what was optional vs technical.

## Files changed

- `includes/class-rwgo-tracking-setup.php` (new) — status rows, next-step priority, last-push option, mode preference
- `includes/class-rwgo-gtm-live.php` — record successful push; save tracking mode
- `includes/class-rwgo-plugin.php` — bootstrap tracking-setup class
- `includes/class-rwgo-admin.php` — nav label “Tracking setup”
- `admin/views/tracking-tools.php` — header, status, next step, view toggle
- `admin/views/partials/tracking-setup-guide.php` (new) — 5 step cards
- `admin/views/partials/tracking-technical-reference.php` (new) — snippets / tables / per-test raw
- `admin/views/partials/gtm-quick-setup.php` — thin legacy shim
- `admin/js/rwgo-admin-gtm.js` — Setup Guide | Technical Reference toggle
- `admin/css/rwgo-admin.css` — status/badge/step styles
- Version → **0.4.85**

## Before / after UX

**Before:** One long page of GTM Quick Setup + inline code blocks.  
**After:** Status summary + next-step callout; default Setup Guide steps; Technical Reference for JSON/tables/payloads.

## Setup guide structure

1. Connect GTM (status, picker, GA4 ID, refresh/save)
2. Choose tracking mode (Simple / Advanced)
3. Publish GTM assets (counts only + preview/push)
4. Verify tracking (preview page + Reports)
5. Test-specific handoff (primary ready test)

## Technical reference structure

- Event names
- Trigger definition
- Variables table
- GA4 mapping
- Example dataLayer
- Per-test raw handoff (+ download/push)
- Debug snippets (existing advanced partial)

## Status / next-step logic

Priority: not connected → no account/container → GA4 missing → assets not pushed → no test → preflight not ready → complete.

## What was not changed

- Event names (`rwgo_goal_fired`, `rwgo_experiment_exposure`)
- GTM provision / OAuth / push API behaviour
- Goal tracking / REST endpoints
- Copy button mechanism (reorganised only)

## Commands run

- `php -l` on changed PHP files — OK
- `npm run package:zip` / `python scripts/package_zip.py` — (release step)

## Remaining limitations

- “Verified” is preflight/ready proxy — no live dataLayer receipt confirmation API yet
- “Draft pushed” vs GTM container publish still manual in GTM
- Sites that pushed before 0.4.85 may need one more push (or rely on last-result transient) to mark assets pushed
