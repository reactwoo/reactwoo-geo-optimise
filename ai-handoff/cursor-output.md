# Cursor output — WordPress AI generation transports

**Status:** done

**Date:** 2026-07-11

**Version:** 0.4.68

## Root cause

Transport selection, managed quota, and workflow execution were coupled: workflows called `RWGA_Remote_Client` directly and `RWGA_AI_Usage_Guard` mixed product licence, platform auth, snapshot sync, and managed quota. That blocked WordPress AI / BYOK without ReactWoo tokens.

## Architecture implemented

```text
workflow (ux_analysis / ux_recommend)
  → RWGA_Generation_Router
      → WordPress AI transport (public WP 7 AI Client)
      → managed ReactWoo transport (JWT + managed allowance)
      → local deterministic callback
```

- Explicit modes never fall through after a started generation fails.
- `remote` → managed; `remote_fallback` → managed then local (no WordPress AI preference).
- New default: `automatic`.
- `can_run_managed_generation()` separates managed auth/quota from snapshot sync and from WordPress AI.

## Files changed

### New

- `merged-geo-ai/includes/services/generation/interface-rwga-generation-transport.php`
- `merged-geo-ai/includes/services/generation/class-rwga-generation-router.php`
- `merged-geo-ai/includes/services/generation/class-rwga-wordpress-ai-transport.php`
- `merged-geo-ai/includes/services/generation/class-rwga-managed-ai-transport.php`
- `merged-geo-ai/includes/services/generation/class-rwga-local-ai-transport.php`
- `merged-geo-ai/includes/services/generation/class-rwga-workflow-prompt-spec-registry.php`
- `merged-geo-ai/includes/services/generation/class-rwga-prompt-context-formatter.php`
- `docs/GENERATION-TRANSPORTS.md`
- `tests/bootstrap.php`, `tests/Generation/RWGAGenerationRouterTest.php`, `phpunit.xml.dist`

### Updated

- `RWGA_Engine`, settings sanitize/defaults, Advanced UI, usage guard/presenter wording
- `RWGA_Workflow_UX_Analysis`, `RWGA_Workflow_UX_Recommend`
- `class-rwga-plugin.php` requires
- `AGENTS.md`, `README.md`, `readme.txt`, `reactwoo-geo-optimise.php` (0.4.68)
- `ai-handoff/current-task.md`

## Workflows migrated

- `ux_analysis`
- `ux_recommend`

## Remaining workflows (not migrated)

Still use direct `RWGA_Remote_Client` / local stubs:

- `ux_opportunity_review`
- `copy_implement`
- `competitor_research`
- intelligence workflows
- weather facet suggester

## What was NOT changed

- Standalone `reactwoo-geo-ai`
- Geo Core / reactwoo-api / react-license
- Experiments, goals, promotion, GTM
- Elementor write path / Atomic page creation
- Provider API-key storage

## WordPress AI API

Verified public contract matches the brief:

- `wp_supports_ai()`, `wp_ai_client_prompt()`
- Fluent: `using_system_instruction`, `using_temperature`, `using_max_tokens`, `as_json_response`, `is_supported_for_text_generation`, `generate_text`
- No load-time WP 7 class type-hints; all calls behind `function_exists` / runtime checks
- No live provider called in tests (`RWGA_WordPress_AI_Transport::$test_prompt_executor`)

## Commands / tests

- `phpunit -c phpunit.xml.dist` (Optimise generation) → **OK (9 tests, 29 assertions)**
- `phpunit --filter RWGAElementor` (geo-ai Atomic suite) → **OK (14 tests, 55 assertions)**
- `php -l` on added/edited PHP → OK
- `npm run package:zip` → `reactwoo-geo-optimise-0.4.68.zip` (includes `merged-geo-ai/.../generation/*`)

## Package

`reactwoo-geo-optimise-0.4.68.zip` (not committed)

## Licence deploy note (unchanged)

Do not deploy licence-service `main` until default branch / ancestor reconciliation is resolved (`react-license` `master` vs `main`).
