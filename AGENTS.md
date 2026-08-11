# Agent workflow — ReactWoo Geo Optimise

Geo Optimise is the **surviving merged Geo satellite**: experiments, goals, reports, promotion, **and** AI review / recommendations / drafts (embedded under `merged-geo-ai/`).

It is not a standalone geo engine. Standalone **`reactwoo-geo-ai` is deprecated** — do not add new AI features there.

## Ownership

| Layer | Owns |
|-------|------|
| **Geo Core** | Detection, MaxMind, visitor context, `RWGC_Rule_Evaluator`, shared capability map |
| **Geo Optimise** (`merged-geo-ai/` for AI) | UX analysis, recommendations, drafts, site intelligence UI bridges, experiments, goals, reports |
| **ReactWoo API** | Managed model orchestration, cloud workflow, site intelligence graph, managed AI quota |

New AI work belongs under **`merged-geo-ai/`** until a later controlled prefix cleanup. Do not mass-rename `RWGA_*` classes in drive-by passes.

## Defaults

- Prefer **one coherent thread** (read → change → verify). Match Geo Core `docs/AGENTS.md` for suite work.
- **Platform / Cloud plan:** Geo Core `docs/architecture/` — experiments/goals/events become shared capabilities; Cloud authors, Core executes locally. See `.cursor/rules/reactwoo-platform.mdc`.
- **Do not** duplicate visitor detection or Core routing — consume Geo Core hooks, REST `/capabilities`, and assignment events.
- Generation uses `RWGA_Generation_Router` (WordPress AI → managed → local). See `docs/GENERATION-TRANSPORTS.md`.
- WordPress AI / BYOK does **not** consume the ReactWoo managed AI allowance.

## Build and release

- **`package.json`** → `reactwooBuild.pluginFolder`, `reactwooBuild.zipFile`, `geoCoreDependencySlug`.
- **Distribution zip:** `npm run package:zip` → `python scripts/package_zip.py` (includes `admin/`, `assets/`, `includes/`, `merged-geo-ai/`, main PHP, `readme.txt`).
- **CI:** `.github/workflows/publish-update.yml` on tag / dispatch.
- **Git:** do not commit `*.zip`.
- **`RWGO_VERSION`** must match plugin header, README version, and readme **Stable tag**.

## AI handoff

Planner → `ai-handoff/current-task.md`; Cursor → `cursor-output.md`. Suite: `reactwoo-geocore/docs/ai-handoff-workflow.md`.
