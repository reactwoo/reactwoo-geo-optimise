# ReactWoo Geo Optimise

**Version:** 0.4.74  
**Plugin slug:** `reactwoo-geo-optimise`

## Overview

Geo Optimise is the **merged** conversion-optimisation satellite on **ReactWoo Geo Core**: AI review, recommendations, and drafts (embedded `merged-geo-ai/`) plus experiments (`rwgo_experiment` CPT), sticky assignment, goal tracking, and reports. Standalone Geo AI is deprecated.

Licensing and updates use **react-license** and **reactwoo-api**. WordPress AI (BYOK) is the preferred on-site generation path when available; ReactWoo managed AI remains optional. Both require an Optimise licence; only managed calls consume the ReactWoo managed AI allowance.

## Position in family

```
Geo Core (events, routing, evaluator context)
    ↓
Geo Optimise — AI review + experiments + goals + reports
    (merged-geo-ai/ owns AI until a later prefix cleanup)
```

Geo Optimise consumes Core hooks (`rwgc_emit_geo_event`, REST `/capabilities`) and does not duplicate visitor detection.

## Key Features

### Available

- **AI Review / Recommendations / Drafts** via embedded Geo AI (`merged-geo-ai/`)
- **Generation modes:** Automatic, WordPress AI, ReactWoo managed, Local (`docs/GENERATION-TRANSPORTS.md`)
- **Experiments CPT** with Create Test wizard, Tests list, Edit Test, Reports
- Sticky **variant assignment** (`rwgo_get_variant`, cookie-based, weighted splits)
- **REST goal tracking**: `POST /wp-json/rwgo/v1/goal`, tracking nonce endpoint
- Front-end **`rwgo-tracking.js`** with defined-goal mapping across Control and Variant B
- Elementor and Gutenberg **builder-defined goals** (CTA, form submit, destination goals)
- Elementor Atomic V4 normalized context **plus** measurement-key write stamps (full Atomic page construction is a later pass)
- **Canonical page bindings** with resync (front page, shop, relative paths)
- **Promote winner** wizard (Mode A: replace primary content)
- CSV export and stats snapshot (`rwgo_stats_snapshot`)
- Geo Core event bridge (`assignment` events via `RWGC_Event`)
- Experiments and Reports under platform **Experiences** / **Targeting** sections
- Independent license and `RWGC_Satellite_Updater`

### In Progress

- **Slug-swap promotion (Mode B):** `RWGO_Promotion_Slug_Scaffold` exists but is not exposed in UI until ordering and redirects are fully validated
- Winner promotion UX polish and redirect rule cleanup

### Planned

- Public slug/URL takeover for winning variants (Mode B)
- Deeper integration with Geo Commerce experiment segments

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.2+ |
| PHP | 7.4+ |
| ReactWoo Geo Core | 1.6+ (1.8.x for platform shell) |
| react-license | Valid Geo Optimise product key |
| reactwoo-api | JWT and commercial updates |

Elementor optional for widget-level goals; Gutenberg for block goals.

## Installation

1. Install and activate **ReactWoo Geo Core**.
2. Activate **reactwoo-geo-optimise** (creates `wp_rwgo_events` table).
3. Configure license under **Geo → Geo Optimise → License**.
4. Create a test via **Experiences → Create test**.

```bash
npm run package:zip
```

## Configuration

| Area | Notes |
|------|-------|
| License | Geo Optimise → License / Settings |
| Tracking debug | `WP_DEBUG`, `RWGO_TRACKING_DEBUG`, `RWGO_REST_GOAL_DEBUG` |
| Referer hosts | Filter `rwgo_goal_referer_allowed_hosts` for staging domains |
| Cross-origin assets | `rwgo_fix_cross_origin_wp_content_urls` on cloned sites |

REST base: `/wp-json/rwgo/v1/` (goals, tracking-nonce, experiments as documented in Developer docs).

## Feature Entitlements

| Feature | Requires |
|---------|----------|
| Experiments & assignment | Geo Optimise license |
| Goal REST tracking | Geo Optimise license (nonce + referer checks) |
| Geo events in Core | Geo Core active |
| Builder goals | Elementor and/or Gutenberg |
| Plugin updates | License JWT via reactwoo-api |

## Integrations

| Integration | Purpose |
|-------------|---------|
| **Geo Core** | Visitor context, geo events, suite shell, REST discovery |
| **Elementor / Gutenberg** | Goal controls and destination goals |
| **react-license** | Product key |
| **reactwoo-api** | Auth and updates |
| **Geo AI** | Optional handoff via `rwgc_variant_page_id` suite context |

## Developer Notes

- Constant: `RWGO_VERSION`; fires `rwgo_loaded`.
- Assignment: `RWGO_Assignment`, action `rwgo_variant_assigned`.
- Stats: `RWGO_Stats::get_snapshot()`, filter `rwgo_stats_snapshot`.
- Promotion: `RWGO_Promotion_Service`; slug scaffold intentionally returns `WP_Error` until Mode B ships.
- Phase doc: Geo Core `docs/phases/phase-6.md`.

## Known Limitations

- **Slug-swap promotion** is not available in this release — use “Replace primary content” (Mode A).
- Full-page cache can stale nonces; tracking script fetches fresh nonce via REST.
- Variant B must be publicly viewable for valid split diagnostics.
- Defined goals require matching physical `(goal_id, handler_id)` pairs or explicit `defined_goal_mapping`.

## Release Readiness

| Area | Status |
|------|--------|
| Experiments, assignment, reports | **Shipped** |
| REST goal tracking | **Shipped** |
| Promote winner (content replace) | **Shipped** |
| Slug-swap promotion | **In Progress** (scaffold only) |

## Compatibility

| Component | Version |
|-----------|---------|
| WordPress | 6.2+ |
| PHP | 7.4+ |
| Geo Core | 1.6.x – 1.8.x |
| Elementor | Optional |
| react-license | 1.0.x |
| reactwoo-api | 0.1.x |

## Roadmap

- Ship Mode B slug/URL promotion after validation
- Experiment ↔ commerce segment hooks
- Enhanced multivariate support beyond Control / Variant B

## Support

- In-plugin Help and Developer diagnostics (`rwgoInspectTracking()` in browser console)
- [ReactWoo support](https://reactwoo.com/support)

## License

GPLv2 or later.
