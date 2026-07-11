# Cursor output — generation router migration (remaining workflows)

**Status:** done  
**Date:** 2026-07-11

## Summary

Migrated remaining workflows onto `RWGA_Generation_Router`. Only `RWGA_Managed_AI_Transport` still calls `RWGA_Remote_Client::dispatch` directly.

## Files changed

| File | Why |
|------|-----|
| `class-rwga-workflow-competitor-research.php` | Router + local stub |
| `class-rwga-workflow-copy-implement.php` | Router + local stub |
| `class-rwga-workflow-ux-opportunity-review.php` | Router + telemetry |
| `class-rwga-workflow-intelligence.php` | Managed-only via router |
| `class-rwga-weather-facet-suggester.php` | Router + keyword local |
| `docs/GENERATION-TRANSPORTS.md` | Document migrated set |
| Version / readme / README | **0.4.70** |

## Not changed

- WP AI prompt registry (still ux_analysis / ux_recommend only)
- Standalone Geo AI
- License / Core / API

## Commands

- `php -l` on migrated files — OK
- PHPUnit generation suite via Geo Core vendor phpunit — (see session)
- `npm run package:zip`

## Remaining

None for this routing pass.
