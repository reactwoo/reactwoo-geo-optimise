# Agent workflow — ReactWoo Geo Optimise

Geo Optimise is a **Geo Core satellite** (experiments, CRO, assignment, reporting). It is not a standalone geo engine.

## Defaults

- Prefer **one coherent thread** (read → change → verify). Match the sequential style in Geo Core `docs/AGENTS.md` for suite work.
- **Do not** duplicate visitor detection or Core routing — consume Geo Core hooks, REST `/capabilities`, and assignment events documented for phase 6.

## Build and release (parity with Geo AI / Geo Commerce)

- **`package.json`** defines `reactwooBuild.pluginFolder`, `reactwooBuild.zipFile`, and `reactwooBuild.geoCoreDependencySlug` (`reactwoo-geocore`).
- **Distribution zip:** `npm run package:zip` → `python scripts/package_zip.py` (includes `admin/`, `assets/`, `includes/`, main PHP, `readme.txt`).
- **CI:** `.github/workflows/publish-update.yml` runs the same packager on tag / dispatch.
- **Git:** do not commit `*.zip` (see `.gitignore`).
- **Cursor:** shared rules live under **`.cursor/rules/`** (committed).

## Product notes

- Fires **`rwgo_loaded`** when ready. Experiment models, stats export, and admin flows live in this plugin; Core owns engine contracts and discovery.

## References

- Geo Core: `docs/phases/phase-6.md`, `docs/geo-core-cursor-master-plan.md`, `docs/releases-and-git-tags.md`.
- **`RWGO_VERSION`** in `reactwoo-geo-optimise.php` must match the shipped release and readme **Stable tag**.
