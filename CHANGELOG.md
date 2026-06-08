# Changelog

All notable changes to **reactwoo-geo-optimise** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Slug-swap promotion (Mode B):** Scaffold present (`RWGO_Promotion_Slug_Scaffold`); UI not exposed until ordering and redirects are fully validated.

## [0.4.63] - 2026-06-08

### Added
- **Geo AI snapshot:** `RWGO_AI_Snapshot` appends `geo_optimise` metadata and experiment→page edges for cloud intelligence graphs.

## [0.4.62] - 2026-06-08

### Added
- **Geo AI handoff:** Create Test wizard accepts `rwgo_prefill_*` query args from intelligence actions.

## [0.4.61] - 2026-06-06

### Fixed
- **i18n:** Queue textdomain via Geo Core `RWGC_I18n` on `plugins_loaded` priority 6 (WP 6.7 JIT fix with Geo Core 1.8.29).

## [0.4.60] - 2026-06-06

### Changed
- **Suite release:** Aligned with Geo Core 1.7.9 contextual admin shell.

## [0.4.59] - 2026-06-06

### Changed
- **Admin IA:** Experiments and Reports under Experiences; Create test and legacy Tests hidden from section nav.

## [0.4.57] - 2026-06-06

### Changed
- **Targeting:** Experiment reports route under Geo platform Targeting section.

## [0.4.54] - 2026-06-06

### Changed
- **Admin:** Register Geo Optimise under Geo Core (`rwgc-dashboard`) instead of separate top-level menu.

## [0.4.50] - 2026-06-06

### Fixed
- **License login:** `RWGO_Platform_Client` applies `rwgc_auth_login_body` filter for consistent product metadata.

## [0.4.49] - 2026-06-06

### Changed
- **Independent licensing:** Own platform client, JWT cache, update-auth callback; explicit import only.

## [0.4.20] - 2026-06-06

### Added
- **Canonical page bindings:** Snapshots with `is_front_page`, `relative_path`; `normalize_page_bindings()`; Resync all tests.
- **Defined goals:** Per-variant `defined_goal_mapping` with logical primary goal id.

## [0.4.5] - 2026-06-06

### Fixed
- **Tracking:** Fresh nonce via `GET /rwgo/v1/tracking-nonce` before goal POST (fixes cached HTML expired nonce).

## [0.4.0] - 2026-06-06

### Added
- **Defined goals:** Explicit Control/Variant B goal mapping; REST rewrites to logical `primary_goal_id`.

## [0.3.0] - 2026-06-06

### Added
- **Builder-defined goals:** Expanded Elementor/Gutenberg coverage; destination goals; form-submit handling.

## [0.2.0.0] - 2026-06-06

### Added
- **Product UX:** Dashboard, Create Test wizard, Tests list, Reports, Tracking Tools; `rwgo_experiment` CPT; `wp_rwgo_events` table.

## [0.1.3.0] - 2026-06-06

### Added
- **`rwgo_get_variant()`** — sticky experiment assignment (cookie, 30 days).

## [0.1.2.0] - 2026-06-06

### Added
- **`rwgo_stats_snapshot`** filter and CSV export.

---

Full history: `readme.txt`.
