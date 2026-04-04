=== ReactWoo Geo Optimise ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.2.0.1

Experiments and CRO on ReactWoo Geo Core.

== Description ==

Consumes Geo Core hooks and REST `/capabilities` for A/B and optimisation workflows. Requires **ReactWoo Geo Core**. See Geo Core `docs/phases/phase-6.md` for the author checklist.

== Installation ==

1. Install and activate **ReactWoo Geo Core**.
2. Activate this plugin.

== Changelog ==

= 0.2.0.1 =
* **Admin:** Menu and screen access use the same capability model as Geo Elementor (`manage_options`, or `manage_woocommerce` when the user is a WooCommerce shop manager without `manage_options`). Filter: `rwgo_required_capability`.
* **REST:** JSON body fallback when `get_json_params()` is empty (e.g. some `sendBeacon` requests).
* **Diagnostics:** Raw counters include stored goal-event total; REST discovery shows the client goal endpoint URL.

= 0.2.0.0 =
* **Product UX:** Dashboard, Create Test wizard, Tests list, Reports, Tracking Tools, Advanced (diagnostics + PHP docs); managed experiments (`rwgo_experiment` CPT); activation creates `wp_rwgo_events`; legacy admin slugs redirect to new screens.

= 0.1.18.0 =
* **Updates:** Registers **`RWGC_Satellite_Updater`** (Geo Core 1.3.4+) — update checks use the ReactWoo API + license JWT; **`download_url`** is R2-signed.

= 0.1.17.0 =
* **License:** **Geo Optimise → License** screen to save a ReactWoo product key (`rwgo_settings`); filters `rwgc_reactwoo_license_key` / `rwgc_reactwoo_api_base` (priority 15) for Geo Core’s platform client; one-time migration from Geo AI / Geo Core keys; **License** link on Geo Core dashboard card.

= 0.1.16.2 =
* **Suite handoff:** Overview and Experiments show context when opened from Geo Suite; optional **Open page in editor** when `rwgc_variant_page_id` is present (Geo Core `rwgc_get_suite_handoff_request_context()`, 1.3.3+).

= 0.1.16.1 =
* **Release:** Patch bump for remote update pipeline (version-only).

= 0.1.16.0 =
* **Admin IA:** **Experiments**, **Results**, **Events & diagnostics** screens; **Overview** is experiments-first with assignment preview and quick links.
* **Help:** Suite header when Geo Core UI is available; copy updated for the new flow.

= 0.1.15.0 =
* **Admin:** **Top-level Geo Optimise menu** (Overview, Help). No longer nested under Geo Core.
* **Geo Core dashboard:** Summary card when Geo Optimise is active.
* **UX:** Hero steps, **Technical details** for hooks/CSV filters; **rwgo-admin.css** + Geo Core styles.

= 0.1.13.0 =
* **Admin UI:** **Geo Optimise** dashboard uses Geo Core shared layout (**`rwgc-wrap`**, **`rwgc-inner-nav`**, **`rwgc-card`**) so stats, CSV actions, and docs match Geo Core / Geo Elementor-style admin rhythm.
* **Navigation:** Registers on **`rwgc_inner_nav_items`** for quick jumps alongside other Geo Core tabs.

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
