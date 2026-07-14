# Cursor output — AI Connector diagnostics

## Status

done

## Root cause

After the Geo AI merge, `rwga-advanced` redirects to Optimise → Settings, but the Advanced UI (execution mode + connection checks) was never embedded there. AI Review still preferred managed/remote; Pro-tier API errors looked like a broken local connector.

## Files changed

- `includes/class-rwgo-ai-connector-diagnostics.php` — status rows + four separate tests + save engine
- `admin/views/partials/ai-connector-diagnostics.php` — Settings panel UI
- `admin/views/optimise/tab-settings.php` — includes the panel
- `includes/class-rwgo-plugin.php` — boot diagnostics
- `merged-geo-ai/includes/services/class-rwga-engine.php` — `rwga_workflow_engine_mode` filter for forced test modes
- Version **0.4.88** (header, constant, readme Stable tag)

## Behaviour

1. **Run local test** — forces `local` mode; no reactwoo-api call
2. **Check remote API** — JWT + usage endpoint (not a billable workflow)
3. **Run remote workflow test** — managed only; Pro tier message does not mark local as failed
4. **Test remote fallback** — `remote_fallback` chain; clear remote-blocked / local-ok messaging
5. Execution mode select writes `rwga_settings[workflow_engine]`

## What was not changed

- Remote Pro tier policy on reactwoo-api
- AI Review UX / event names
- Standalone Geo AI packaging

## Commands

- `php -l` on new/changed PHP — clean
