=== ReactWoo Geo Optimise ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.1.13.0

Experiments and CRO on ReactWoo Geo Core.

== Description ==

Consumes Geo Core hooks and REST `/capabilities` for A/B and optimisation workflows. Requires **ReactWoo Geo Core**. See Geo Core `docs/phases/phase-6.md` for the author checklist.

== Installation ==

1. Install and activate **ReactWoo Geo Core**.
2. Activate this plugin.

== Changelog ==

= 0.1.12.0 =
* **`assignment_per_route_resolved`** in **`rwgo_stats_snapshot`** (assignments divided by route events when routes exist); dashboard row.

= 0.1.11.0 =
* **CSV export telemetry:** **`csv_export_count`** (lifetime) and **`last_csv_export_gmt`** in **`rwgo_stats_snapshot`**; dashboard rows; incremented before each export download.

= 0.1.10.0 =
* CSV export filename includes **sanitized site host** (multi-site friendly). Filter **`rwgo_export_csv_filename`** (REST discovery in Geo Core **`integration.satellite_filters`**).

= 0.1.9.0 =
* CSV export: **`experiment_variant_counts`** flattened to **`experiment_variant.{slug}.{variant}`** rows (Excel-friendly). **`RWGO_Stats::flatten_for_csv()`**.

= 0.1.8.0 =
* **Reporting:** server-side per-experiment / per-variant assignment counts; dashboard table; included in **`rwgo_stats_snapshot`** as **`experiment_variant_counts`**. Cleared with **Reset counters**.

= 0.1.7.0 =
* **`rwgo_get_variant( $slug, $variants, $weights )`** — optional **weighted** first-time assignment (same length as variants).

= 0.1.6.0 =
* **`RWGO_Core_Event_Bridge`:** emits **`RWGC_Event`** (`assignment`) via **`rwgc_emit_geo_event`** when **`rwgo_variant_assigned`** runs. Filter **`rwgo_emit_assignment_geo_event`** (default true) to disable.

= 0.1.5.0 =
* **`rwgo_get_assignment_map()`** — read current cookie map (no admin).

= 0.1.4.0 =
* **`assignment_count`** in stats snapshot / CSV; incremented on each new `rwgo_get_variant` assignment; cleared with **Reset counters**.

= 0.1.3.0 =
* **`rwgo_get_variant()`** — sticky experiment assignment (cookie, 30 days); **`RWGO_Assignment`**; action **`rwgo_variant_assigned`**.

= 0.1.2.0 =
* `RWGO_Stats::get_snapshot()` and filter **`rwgo_stats_snapshot`** for dashboards and integrations. **Export CSV snapshot** (UTF-8 BOM) from the Optimise admin screen.

= 0.1.1.0 =
* **Geo Core → Geo Optimise** dashboard: counters for `rwgc_geo_event` / `rwgc_route_variant_resolved`; reset; re-emits `rwgo_*` hooks.
