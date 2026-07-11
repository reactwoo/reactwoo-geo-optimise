# Cursor output

## Status

done

## Files changed

- `includes/class-rwgo-exposure.php` (new) — server exposure recording
- `includes/class-rwgo-tracking-manifest.php` (new) — schema 1.0 manifest builder
- `includes/class-rwgo-event-payload.php` — `normalize_experiment_exposure`
- `includes/class-rwgo-event-store.php` — hook + counts; persist session_hash
- `includes/class-rwgo-runtime.php` — call exposure after served
- `includes/class-rwgo-plugin.php` — require new classes
- `includes/class-rwgo-goal-registry.php` — `trackingManifest` on FE config
- `includes/class-rwgo-gtm-handoff.php` — exposure event docs/example
- `assets/js/rwgo-tracking.js` — dataLayer exposure push
- `docs/MEASUREMENT-CONTRACT.md` — phase 2
- Version → **0.4.71**

## What was not changed

- Physical goal_id/handler_id matching
- GTM API provisioning / Atomic write / winner policy
- Unrelated UX reviewer workspace JS (left unstaged)
- Standalone Geo AI repo

## Commands / validation

- PHP syntax check on new classes
- `npm run package:zip`
- Tag `v0.4.71` push + `git ls-remote` verify

## Remaining

- Next roadmap: Atomic write stamping keys from blueprint
