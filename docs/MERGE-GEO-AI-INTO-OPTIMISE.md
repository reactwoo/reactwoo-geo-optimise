# Merge Geo AI into Geo Optimise — WordPress consolidation plan

**Status:** Phase 7 complete — license aliases + migration SQL  
**Last updated:** 2026-07-08  
**Surviving plugin:** `reactwoo-geo-optimise`  
**Deprecated plugin:** `reactwoo-geo-ai` (compatibility-only or stop packaging before public launch)

---

## 1. Root cause

Geo AI and Geo Optimise are technically clean satellites but commercially weak as separate products.

| Today | Owns |
|-------|------|
| **Geo AI** (`reactwoo-geo-ai`) | UX review, recommendations, drafts, site intelligence sync, approval-gated actions, cloud workflow client, local deterministic runner, competitor/automation surfaces |
| **Geo Optimise** (`reactwoo-geo-optimise`) | Experiments, assignment, goals, reports, winner promotion, test wizard, tracking tools |

The customer journey is one optimisation loop:

```text
Find opportunity → recommend change → draft improvement → create test → measure result → promote winner
```

Splitting advice (Geo AI) from proof (Geo Optimise) weakens positioning before either product is public.

---

## 2. Product decision

**One customer-facing satellite:**

```text
ReactWoo Geo Optimise
```

**Positioning:**

```text
AI-assisted conversion optimisation for geo-targeted pages, products, variants, and campaigns.
```

**Primary shell navigation (Geo Core platform):**

```text
ReactWoo Geo → Optimise
```

**Optimise tabs (target IA):**

| Tab | Source today |
|-----|----------------|
| AI Review | Geo AI — `rwga-ux-opportunity-review`, UX reviewer workspace |
| Recommendations | Geo AI — analyses, intelligence actions, recommendations |
| Drafts | Geo AI — implementation drafts |
| Experiments | Geo Optimise — tests, create/edit, assignment |
| Goals | Geo Optimise — goal tracking, REST events |
| Reports | Both — experiment reports + AI review exports |
| History | Merged — review runs + experiment lifecycle |
| Settings | Licence, workflow engine, tracking/debug |

AI Review must **not** remain the primary home under Insights after merge.

---

## 3. Ownership boundaries (do not violate)

### Geo Core (`reactwoo-geocore`)

- Visitor detection, MaxMind, visitor context
- `RWGC_Rule_Evaluator`, targeting rules, variants, preview context
- `rwgc_build_ai_snapshot()` / compact site intelligence builder
- Shared suite capability map host (`RWGC_Suite_Capability_Map`)
- Platform shell route registry (`RWGC_Admin_Route_Registry`)

### Merged Geo Optimise (`reactwoo-geo-optimise`)

- AI Review UI (chat assistant, scoped audits, category summaries)
- Recommendations queue, findings, approval-gated optimisation actions
- Copy / SEO / variant drafts
- Experiment creation, assignment, goals, reports, winner promotion
- Optimisation history (review + experiment)
- Orchestration of the growth journey (handoffs to Core variants, Commerce impact views)

### reactwoo-api

- Remote AI orchestration, provider keys, workflow registry
- Site register/snapshot, intelligence runs/graph
- Token metering, quotas, Redis caches, deterministic fallbacks
- **No** WordPress mutation

### Geo Commerce (`reactwoo-geo-commerce`)

- Product visibility, pricing, shipping/payment restrictions
- Commerce impact collectors — Optimise **consumes**, does not duplicate

### GeoCore Pro (`reactwoo-geocore-pro`)

- Audiences, campaigns, weather providers — snapshot enrichment only; no move into Optimise

---

## 4. Surviving plugin identity

| Field | Value |
|-------|--------|
| Product name | ReactWoo Geo Optimise |
| Plugin slug / folder | `reactwoo-geo-optimise` |
| Main file | `reactwoo-geo-optimise.php` |
| Version constant | `RWGO_VERSION` |
| Bootstrap hook | `rwgo_loaded` (extend payload; do not break existing listeners) |
| GitHub slug | `reactwoo-geo-optimise` (existing) |
| Primary licence slug | `reactwoo-geo-optimise` |

### Geo AI after merge

**Preferred (pre-public):** stop standalone packaging; fold code into Optimise.

**Transition option:** thin compatibility loader in `reactwoo-geo-ai` for 1–2 internal releases:

- Admin notice: *Geo AI is now included in Geo Optimise. Deactivate standalone Geo AI after verifying Optimise.*
- If merged Optimise AI module loaded → standalone must not register menus/workflows (guard in `RWGA_Plugin::init`).

---

## 5. Class prefix strategy

### Phase 1–3 (merge implementation)

Keep internal prefixes to reduce risk:

```text
RWGO_*  — experiments, assignment, goals, reports (existing)
RWGA_*  — imported AI/intelligence modules (copied or required from includes/ai/)
```

Load AI code from Optimise paths:

```text
includes/ai/
includes/intelligence/
includes/recommendations/
includes/drafts/
admin/views/optimise/
admin/js/
admin/css/
```

### Long-term (post-launch)

Gradual rename of **public-facing** classes only:

```text
RWGO_AI_*
RWGO_Intelligence_*
RWGO_Recommendations_*
```

**Do not** mass-rename in the first merge pass.

---

## 6. Database strategy

### First merge — keep existing tables

| Prefix | Tables / storage (representative) |
|--------|-----------------------------------|
| `rwga_*` | analyses, findings, recommendations, drafts, intelligence actions |
| `rwgo_*` | events, experiment CPT/meta |

Add schema version options:

```text
rwgo_schema_version
rwgo_ai_schema_version
```

### Later (optional, post-launch)

Rename to unified `rwgo_*` only if migration tooling is proven safe. **Not** part of first merge.

---

## 7. Admin route migration

### Target Optimise hub

New primary route (proposed):

```text
admin.php?page=rwgo-optimise&tab=ai-review
admin.php?page=rwgo-optimise&tab=recommendations
admin.php?page=rwgo-optimise&tab=drafts
admin.php?page=rwgo-optimise&tab=experiments
admin.php?page=rwgo-optimise&tab=goals
admin.php?page=rwgo-optimise&tab=reports
admin.php?page=rwgo-optimise&tab=history
admin.php?page=rwgo-optimise&tab=settings
```

### Legacy Geo AI redirects (internal compatibility)

| Legacy | Redirect target |
|--------|-----------------|
| `rwga-ux-opportunity-review` | `rwgo-optimise&tab=ai-review` |
| `rwga-intelligence-actions` | `rwgo-optimise&tab=recommendations` |
| `rwga-implementation-drafts` | `rwgo-optimise&tab=drafts` |
| `rwga-analyses` | `rwgo-optimise&tab=history` or `tab=reports` |
| `rwga-recommendations` | `rwgo-optimise&tab=recommendations` |
| `rwga-intelligence-wizard` | `rwgo-optimise&tab=settings` (or dedicated intelligence sub-tab) |
| `rwga-license` | `rwgo-optimise&tab=settings` |

Existing Optimise routes (`rwgo-tests`, `rwgo-create-test`, `rwgo-reports`, etc.) remain until hub absorbs them or redirects are added.

### Geo Core shell updates

Update `RWGC_Admin_Route_Registry`, Insights nav, and capability map:

- Single **Optimise** section when merged plugin active
- Remove primary **AI UX Reviewer** under Insights (embed may redirect to Optimise)
- `rwgc_is_geo_ai_active()` → bridge to `optimise.ai_review` capability
- Expose unified `optimise` block in REST `/capabilities`

---

## 8. Licence model

### Primary entitlement (new purchases)

```text
product_slug: reactwoo-geo-optimise
```

### Transition (accept both)

```text
reactwoo-geo-ai      → legacy; migration notice in wp-admin
reactwoo-geo-optimise → primary; full AI + experiments when licensed
```

### WordPress behaviour

```text
optimise licence active     → AI review + experiments + reports
legacy geo-ai licence only  → AI surfaces + migration notice
legacy optimise licence only→ experiments + notice if AI entitlement missing
```

Before public launch, simplest path: **one product, one licence** (`reactwoo-geo-optimise`).

### react-license

Requires product mapping update (separate repo) — see API plan § entitlement.

---

## 9. Capability map (target)

Geo Core `rwgc_get_suite_capability_map()` should report:

```json
{
  "optimise": {
    "active": true,
    "version": "x.x.x",
    "license": "active",
    "ai_review": true,
    "recommendations": true,
    "drafts": true,
    "experiments": true,
    "goals": true,
    "reports": true
  },
  "legacy_geo_ai_detected": false
}
```

Deprecate separate `geo_ai_active` / `geo_ai_licensed` as **primary** keys after transition; keep as aliases for one release.

---

## 10. Code modules to import from Geo AI

Bring into Optimise (copy or require — no behaviour change in Phase 3):

| Area | Representative classes / assets |
|------|----------------------------------|
| Workflows | `RWGA_Workflow_Registry`, `RWGA_Workflow_ux_opportunity_review`, local runner |
| Remote client | `RWGA_Platform_Client` |
| Intelligence | `RWGA_Site_Intelligence_Sync`, wizard/cloud screens |
| UX Reviewer | `RWGA_UX_Reviewer_UI`, `rwga-ux-reviewer-assistant.js`, CSS |
| Actions | `RWGA_Intelligence_Action_*`, approval applier |
| Handoff | Existing Optimise test prefill from `optimisation_recommendation` |
| Admin | Inner nav patterns, journey router (adapt to Optimise tabs) |

**Current Geo AI version at plan time:** `0.4.140` (AI UX Reviewer chat, scoped audits, glossary).

---

## 11. Migration phases

| Phase | Scope | Ship? |
|-------|--------|-------|
| **0** | Freeze feature work on both satellites; branch `merge/geo-ai-into-optimise`; tag current builds | Yes |
| **1** | This doc + API plan; handoff updates | **Now** |
| **2** | Optimise shell only — tabs + placeholders; existing experiments unchanged | Next |
| **3** | Import Geo AI modules into `includes/ai/`; wire licence bridge | After shell |
| **4** | Route redirects; Geo Core shell/capability updates | With 3 |
| **5** | API workflow aliases + unified history | Done |
| **6** | Deprecate standalone Geo AI packaging | Done |
| **7** | react-license product consolidation | Done |

---

## 12. Rollback plan

- Keep `reactwoo-geo-ai` tag `v0.4.140` (and later) installable standalone
- Merged Optimise releases gate AI module behind constant `RWGO_AI_MERGE_ENABLED` until stable
- DB: no table renames in first pass → rollback = deactivate merged build, reactivate Geo AI
- API: legacy `/geo-ai/*` routes remain aliases indefinitely until explicit deprecation notice

---

## 13. Test matrix (minimum)

```text
GeoCore + merged Optimise only
GeoCore + old Geo AI + old Optimise (transition)
GeoCore + merged Optimise + legacy Geo AI active (duplicate-hook guard)
GeoCore + Geo Commerce + merged Optimise
GeoCore Pro + merged Optimise
No licence / legacy Geo AI licence / legacy Optimise licence / new Optimise licence
Remote API down vs up; local deterministic vs remote workflow
Full journey: AI Review → recommendation → draft → variant → experiment → goal → report → winner promotion
No duplicate admin menus; no frontend AI dependency; no silent WP mutation
```

---

## 14. Do not touch (first passes)

- Geo Core evaluator, detection, MaxMind stacks
- Geo Commerce pricing/visibility engines
- Frontend visitor routing depending on API/AI
- DB table renames
- Mass `RWGA_*` → `RWGO_*` renames
- Removing `/api/v5/geo-ai/*` routes
- Breaking current experiment assignment or goal tracking
- Slug-swap winner promotion (still out of UI per Optimise readme)

---

## 15. Acceptance — planning pass

- [x] This document exists
- [x] Surviving slug `reactwoo-geo-optimise` documented
- [x] API alias strategy cross-referenced (`reactwoo-api/docs/PLAN-GEO-OPTIMISE-AI-MERGE.md`)
- [x] Licence, routes, DB, class prefix, phases, rollback, test matrix defined
- [x] No production logic moved in Phase 1

## 16. Acceptance — full merge (later)

See user journey checklist in product brief: activate Core + merged Optimise only → AI Review → draft → test → goals → reports → promotion → API history → no duplicate menus.

---

## References

- `reactwoo-api/docs/PLAN-GEO-OPTIMISE-AI-MERGE.md`
- `reactwoo-api/docs/PLAN-GEO-AI-INTELLIGENCE.md`
- `reactwoo-geocore/docs/geo-core-cursor-master-plan.md`
- `reactwoo-geocore/docs/phases/phase-5.md`, `phase-6.md`
- Geo AI `docs/GEO-AI-INTELLIGENCE.md` (if present)
- `ai-handoff/` in Geo Core, Geo AI, Geo Optimise
