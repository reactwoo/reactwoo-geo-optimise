# Cursor output — concrete local CTA recommendations

## Status

done

## Root cause

`produce_local_cards()` used a hardcoded generic CTA finding with no page extraction and no proposed button labels.

## Files changed

- `merged-geo-ai/includes/workflows/class-rwga-workflow-ux-opportunity-review.php` — context-aware CTA card + paste-ready `suggested_copy`
- `merged-geo-ai/admin/views/partials/ux-reviewer-workspace.php` — render paste-ready copy block
- Version **0.4.90**

## Behaviour

Local review now:
1. Reads page builder context (headline + CTAs)
2. Names current primary CTA when present
3. Proposes specific primary/secondary labels from page type / title / geo cues
4. Shows alternatives + proof line in the finding UI

## What was not changed

- Remote Pro tier policy
- Non-CTA local cards (targeting / experiment / commerce stubs)
