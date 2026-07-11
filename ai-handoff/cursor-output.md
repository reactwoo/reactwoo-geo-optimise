# Cursor output

## Status

done

## Task

AI Review workspace state contract: fresh vs result (Optimise 0.4.76).

## State contract

| Mode | Trigger | Main area | Sidebar |
|------|---------|-----------|---------|
| **fresh** | Clean tab URL (`page=rwgo-optimise&tab=ai-review`) or error redirect | New assistant + review types + Run review; Refine collapsed; no findings | Recent activity from persisted `RWGA_DB_Analysis_Runs` (max 3) |
| **result** | Only `rwga_ux=ran` after successful POST redirect | Transient cards + score/categories/findings | Same Recent activity rail |

Do **not** infer result mode from an existing `rwga_ux_review_{uid}` transient alone.

## Files changed

- `includes/class-rwgo-ai-hub-views.php` — explicit fresh/result from `rwga_ux=ran`
- `includes/class-rwgo-optimise-history.php` — `recent_ai_runs( $limit = 3 )`
- `merged-geo-ai/includes/class-rwga-ux-reviewer-ui.php` — display_mode; no implicit session load in fresh
- `merged-geo-ai/includes/class-rwga-admin.php` — same gate for standalone reviewer route
- `merged-geo-ai/admin/views/ux-opportunity-review-page.php` — pass display_mode / session_meta
- `merged-geo-ai/admin/views/partials/ux-reviewer-workspace.php` — Recent activity sidebar; findings only in result mode
- `merged-geo-ai/admin/js/rwga-ux-reviewer-assistant.js` — Run another review full reset + `history.replaceState` URL cleanup
- `merged-geo-ai/admin/css/rwga-ux-reviewer.css` — workspace grid + recent rail
- Version → **0.4.76**

## Not changed

- Generation transports, experiment assignment, goals, promotion, analysis DB schema, transient write path on POST

## Commands / validation

- PHP syntax on changed files — OK
- `node --check` assistant JS — OK
- `npm run package:zip` — OK → `reactwoo-geo-optimise-0.4.76.zip`

## Manual acceptance

- [ ] Complete review → see results on `rwga_ux=ran`
- [ ] Leave tab and return → fresh assistant, no sticky findings
- [ ] Previous run in Recent activity + History
- [ ] Run another review resets chat/choices and cleans result query params
