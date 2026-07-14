# Cursor output — AI Review local fallback + engine control

## Status

done

## Root cause

1. Automatic mode called managed remote successfully past preflight (JWT OK), then API returned `This workflow requires pro tier or higher`. Router treated that as a hard generation failure and never tried local.
2. Execution mode lived only under Settings → AI Connector; AI Review itself had no local/remote control, so users stayed on broken automatic/remote path.

## Files changed

- `merged-geo-ai/.../class-rwga-generation-router.php` — Pro-tier / entitlement errors fall through in `automatic` / `remote_fallback`
- `merged-geo-ai/admin/views/partials/ux-reviewer-workspace.php` — “How should this review run?” select + AI Connector link
- `merged-geo-ai/includes/class-rwga-ux-reviewer-ui.php` — pass engine + connector URL
- `merged-geo-ai/includes/class-rwga-admin.php` — persist `workflow_engine` from Run review
- `admin/views/optimise/tab-settings.php` — AI Connector panel first
- `tests/Generation/RWGAGenerationRouterTest.php` — tier fallthrough test
- Version **0.4.89**

## What was not changed

- API Pro-tier policy
- Event names / GTM

## Commands

- `composer test -- --filter RWGAGenerationRouterTest`
