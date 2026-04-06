=== ReactWoo Geo Optimise ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.4.6

Experiments and CRO on ReactWoo Geo Core.

== Description ==

Consumes Geo Core hooks and REST `/capabilities` for A/B and optimisation workflows. Requires **ReactWoo Geo Core**. See Geo Core `docs/phases/phase-6.md` for the author checklist.

== Installation ==

1. Install and activate **ReactWoo Geo Core**.
2. Activate this plugin.

== Changelog ==

= 0.4.6 =
* **Split validation:** Traffic weights in assignment are read by `variant_id` (not array order). **Served** impressions per variant are counted on the front end (actual page rendered) and appear in Reports **Split diagnostics** alongside **Assigned** (first-time assignments) and **Conversion** (share of recorded conversions). Health notices when Variant B is not publicly viewable or configured weights do not sum to ~1. Optional **`RWGO_TRACKING_DEBUG`** (define in `wp-config.php`) logs REST goal reject reasons and a compact front-end tracking config summary.

= 0.4.5 =
* **Fix (tracking):** Full-page cache (and similar) often serves HTML with an **expired WP nonce**, so `POST /rwgo/v1/goal` returned 403 and no conversions were stored. On experiment pages the script now calls **`GET /rwgo/v1/tracking-nonce`** (uncached) before binding clicks, then uses the fresh nonce for persistence.
* **Front-end context:** Tracking script enqueue no longer requires `is_singular()` only — the context post ID also resolves for **WooCommerce shop** (`is_shop` + `wc_get_page_id('shop')`), with `rwgo_tracking_context_post_id` for themes that need a custom ID.

= 0.4.4 =
* **Fix (tracking):** REST validation for mapped tests now accepts physical `(goal_id, handler_id)` pairs from `defined_goal_mapping.targets`, so clicks still persist if the `goals` meta array is out of sync with the mapping. Referer checks allow both `home_url` and `site_url` hosts (common on Local / multisite). Front-end stamping treats missing `handler_type` as `click` / `form_submit` from `goal_type` so `data-rwgo-experiment-key` is applied. Clicks send optional `goal_label` from `data-rwgo-goal-label` (stored on the event payload as `client_goal_label`).
* **Reports:** Success-target labels prefer handler + goal labels and append **Control** / **Variant B** when `mapping_variant` is set so side-by-side mappings are distinguishable.

= 0.4.3 =
* **Fix (tracking):** Goal persistence used `sendBeacon` first; the browser reports success when the request is queued, not when WordPress returns 201, so REST errors (nonce, validation) were invisible and `fetch` never ran. Client goals now POST with `fetch` first so failures can succeed on retry paths and behaviour matches typical REST expectations.
* **Reports:** “By success target” lists configured `(goal_id, handler_id)` targets with zero counts until events exist, so you always see which CTAs are measured.

= 0.4.2 =
* **Reports:** Leading variant is chosen by **total conversions** summed across all configured measurement targets per variant (`goal_id` + `handler_id` mapping), not a single “primary” goal. Added **By success target** breakdown table (label × variant × count). Optional insight line names the top contributing goal for the leader. Admin copy favors “total conversions”, “winning metric”, and “selected success goals” over “primary goal” where it was user-facing.

= 0.4.1 =
* **UX:** When a mapped defined goal no longer exists in Control or Variant B content (e.g. CTA removed), Edit Test shows a prominent warning with the saved goal label, page name, and actions: open Control/Variant in the builder, jump to Goal & tracking to remap. Tests list shows **Incomplete** + **Goal not on page** for defined tests that fail validation. Legacy single-goal defined tests get a clearer message including the goal label.

= 0.4.0 =
* **Defined goals:** Builder-defined conversion tracking no longer relies on matching goal labels across Control and Variant B. New tests store an explicit per-variant mapping (`defined_goal_mapping`): logical primary goal id + physical `goal_id`/`handler_id` targets for Control and Variant B. Create/Edit Test shows two pickers (“Which marked goal counts as success on Control?” / “…on Variant B?”). REST persistence rewrites fired events to the logical `primary_goal_id` so winner reporting aggregates correctly; label-based cross-page expansion remains only for older tests without explicit mapping. `rwgo-tracking.js` pushes the logical `rwgo_goal_id` to the dataLayer when mapping is active.

= 0.3.9 =
* **Tracking:** Defined Elementor/Gutenberg goals on Control and Variant B used different `goal_id`/`handler_id` hashes per page, so `rwgo-tracking.js` only stamped the experiment on the page that matched the saved test config — usually Control. Clicks on Variant B did not record. The front-end config now expands matching defined goals across all experiment page IDs (same goal label + UI goal type), so both pages stamp `data-rwgo-experiment-key` and REST validation accepts either pair.

= 0.3.8 =
* **Fix:** `add_meta_boxes` callback `RWGO_Page_Goal_Meta::add_meta_box` now accepts an optional second parameter. WordPress (block editor / `register_and_do_post_meta_boxes`) sometimes passes only the post type name, which caused a fatal `ArgumentCountError` when exiting Elementor to the post edit screen.

= 0.3.7 =
* **Elementor:** Register goal controls on `elementor/init` so hooks run before control stacks initialize; keep `common` / `common-optimized` `_section_style` pattern; narrower default widget list; merged goal-type dropdown; section title **Geo Optimise**; optional `RWGO_ELEMENTOR_GOALS_DEBUG` logging.
* **Elementor:** Wrap destination-goal `before_get_config` meta sync in try/catch to avoid hard failures if document APIs throw.
* **Tests / Help:** Copy when no builder-defined goals exist yet; support doc line for Advanced → Geo Optimise.

= 0.3.6 =
* **Elementor:** Register widget goal controls on the merged `common` / `common-optimized` Layout section (`_section_style`), matching current Elementor and GeoElementor; fixes missing Advanced → Geo Optimise — goal panel.
* **Elementor:** Sync destination-goal document settings from RWGO post meta when opening the editor (`before_get_config`), so the page Settings toggle matches meta (e.g. after duplication or REST-only meta).

= 0.3.5 =
* **Builder goals UX:** Wider Elementor widget and Gutenberg block coverage; clearer Advanced/Settings copy vs GeoElementor routing; Help screen section `#rwgo-help-builder-goals`; Developer Support/Developer docs for defined goals; `RWGO_Admin::help_url()`.
* **Gutenberg:** Inspector and document (destination goal) panels link to Help and Support; localized `helpUrl` / `supportUrl`.
* **Tests:** Create Test redirects to Tests list with success + defined-goal next steps; Incomplete pill with reason tags (Missing goal / Missing variant / Invalid builder data).
* **Variants:** Detach offers keep page, Trash, or permanent delete; naming service and slug fixes from 0.3.4 retained.

= 0.3.4 =
* **Variant B naming:** Central `RWGO_Page_Naming_Service` for predictable titles and slugs; explicit uniqueness against published, draft, private, and trashed posts; optional trash slug reuse filter; post-insert slug verification after duplicate and Elementor normalize.
* **UX:** Success notices show created variant title and URL; hint that trashed variant pages keep URL slugs reserved.

= 0.3.3 =
* **Builder-defined goals:** Expanded Elementor and Gutenberg coverage (CTAs, forms, links, Woo blocks); goal types and filters; form-submit + Elementor AJAX success handling in front-end tracking.
* **Destination goals:** Elementor document settings + Gutenberg document sidebar panel (same meta as classic box); REST meta sync for stable goal IDs.
* **Tests:** Defined-goal pending state, create/edit notices, Tests list health when a defined goal is still expected.

= 0.3.2 =
* **Variant duplication:** Builder-aware Elementor duplicate path with validation, regeneration hooks, and extensibility filters; Variant B only attaches when validation passes; recovery UX and fidelity indicators on Tests / Edit Test.
* **Goals & tracking:** Defined goals REST, Gutenberg/Elementor goal wiring, GTM handoff and tracking tool refinements.

= 0.3.1 =
* **Tests / Edit Test UX:** Card-based Variants on the Tests screen; action toolbar (View Report, Edit, Pause/Resume, Delete); status strip and health; horizontal button groups; spacing aligned with Geo Core tokens.
* **Delete test:** Admin action removes the experiment; clears redirect rules and promotion log rows; optional permanent deletion of Variant B page; `rwgo_test_deleted` hook.
* **Edit Test:** Variant management moved above goal/targeting; form section order Goal → Audience → identity → Variant B advanced → Save.

= 0.2.0.2 =
* **Admin:** License and Settings forms post to `options.php` with capability aligned to Geo Optimise menu (`option_page_capability_rwgo_license_group` + `register_setting` capability) so WooCommerce shop managers can save without `manage_options`.

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
