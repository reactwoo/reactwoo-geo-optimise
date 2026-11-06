# Changelog

All notable changes to **reactwoo-geo-optimise** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.5] - 2026-11-06

### Fixed
- The WordPress.org Geo Core 1.9.26 package does not check for Optimise updates, and its settings screen does not include an update-cache control. Optimise 0.5.5 checks catalog slug `reactwoo-geo-optimise` on the stable channel in that case and sends the license token with the check. A ReactWoo-channel Geo Core still performs the check once. Installed 0.5.3 and 0.5.4 cannot discover this through the update list. Install the published zip from Plugins → Add New → Upload.

## [0.5.4] - 2026-11-06

Staging upload only. Not tagged and not published to the ReactWoo update catalog.

### Added
- WordPress Abilities API integration for the site-wide test: `reactwoo-geo-optimise/sitewide-read`, `sitewide-validate`, `sitewide-save`, `sitewide-set-status`, and `sitewide-preview-link`. Abilities are public to REST and MCP when WordPress provides the API. They are skipped when the API is absent.
- Save writes a typed page map and header and footer IDs, stays paused for a new test, and does not change status. Activation is only `sitewide-set-status` with `confirm` true, and it refuses to go active while every variant page and template is unpublished or empty.
- A revision from the last read is required. A stale revision does not overwrite a newer save.
- The admin screen uses the same checks for malformed IDs, duplicate originals, and redirect loops.

### Fixed
- Header and footer IDs are validated as Elementor library posts. Empty draft templates are reported and are not published or copied.

## [0.5.3] - 2026-11-06

Staging upload only. Not tagged and not published to the ReactWoo update catalog.

### Added
- Site-wide tests can name an Elementor header and footer for control and for variant B. The visitor’s arm selects them. Elementor display conditions do not. Cart, checkout, and account keep the normal templates. Saving the test does not publish pages or templates.
- The site-wide screen warns when a variant page or template is unpublished, when the control template is missing, or when a page is both an original and a variant.
- Managers get a signed preview link for control and variant B. The link does not write the assignment cookie and is not counted as exposure or a conversion.

### Fixed
- An unpublished variant page no longer counts as a variant view. The original body stays in place. A published variant header or footer can still be the delivered experience.
- A paused or ended site-wide test sends a direct variant URL back to the original page once. Pairs that would redirect in a circle are ignored.
- Exposure and conversions wait for statistics consent when the WordPress Consent API is active. A refused consent is not turned into an IP and user-agent hash. Preview and bot traffic are not measured.
- A configured header or footer split is not stored as one shared cache entry, including on URLs that are not in the page map. LiteSpeed can still vary a stable assignment when it is the only page cache. Header and footer IDs are printed only when Elementor Pro theme locations are active. Without that, a published library template is not counted as variant B.

## [0.5.2] - 2026-11-03

### Fixed
- Release packages place `reactwoo-geo-optimise.php` and `readme.txt` first, then the other non-JavaScript files, then JavaScript, in a stable order. Entries 1, 2, 4, and 11–24 are not JavaScript files, so ClamAV Sanesecurity Foxhole `JS_Zip_1`, `JS_Zip_2`, `JS_Zip_4`, and `JS_Zip_11` through `JS_Zip_24` do not flag the zip.
- The Plugins screen does not log in when WordPress saves `update_plugins` and only `last_checked` changed. A failed login is remembered for 15 minutes (900 seconds), and that window is also stored as an option so a short object-cache TTL cannot drop it. Logins outside an admin action time out after 10 seconds. An admin-post or admin-ajax action can still wait 30 seconds.
- An invalid licence key does not log in on an ordinary front-end view or on WP-CLI. The Geo AI intelligence sync that runs on `init` uses the same saved token and the same 900-second failure window as every other login caller.

## [0.5.1] - 2026-10-29

### Added
- The experiment assignment cookies (`rwgo_ab` and `rwgo_site`) are added to the LiteSpeed vary list (`litespeed_vary_cookies` and `X-LiteSpeed-Vary`), so the control page and variant B are stored as separate cache entries. When LiteSpeed Cache is the only page cache, a returning visitor with a stable assignment is cacheable. A new or repaired cookie, a bot, a QA override, and any other page-cache plugin still bypass the cache. With no cache plugin active, the existing private no-store behaviour is unchanged.
- Saving, starting, stopping, or promoting an experiment, its page map, or the site-wide experiment (`rwgo_sitewide_experiment`) purges the original and variant URLs. Adapters run only when LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache, or the Cloudflare plugin is present. Elementor CSS for those pages is regenerated when Elementor is active.
- Settings includes a Clear Optimise cache button. It checks the Geo Optimise capability and a nonce.

### Fixed
- Front-end goal and conversion requests no longer use a nonce baked into cached HTML. `rwgo-tracking.js` fetches a fresh nonce from `GET /rwgo/v1/tracking-nonce` before it posts, and that response is marked uncacheable. If the fresh nonce cannot be loaded, the goal is not sent with a stale token.
- All-published site-wide coverage skips every variant page (including another test's Variant B) and every page that is already the control of an active test, so a variant is not copied again and visitors are not given both assignment cookies.
- Elementor duplicates keep `_elementor_data` as JSON, including Atomic V4 nodes (`e-*` widgets, `$$type`, styles). Those documents are not run back through Elementor's document save, which was dropping the atomic tree and failing the duplicate check.
- A promotion or page-map change purges each original and variant URL once per request. Saving the site-wide experiment purges the mapped originals and variants even when the stored option did not change.
- `X-LiteSpeed-Cache-Control` is sent only when LiteSpeed Cache is active.
- Admin actions that refuse the current user return HTTP 403.
- A new test leaves Variant B as a draft so it can be reviewed before it is public. The create form, the Tests list, and Edit Test say that every visitor stays on Control until Variant B is published.

## [0.5.0] - 2026-10-29

### Added
- Site-wide tests can cover all published pages. Checkout, cart, my-account, and chosen page IDs stay out. A bulk action copies the selected pages, or every eligible published page, with the page duplicator and writes those pairs into the page map. Pages published after that choice can be included or skipped. The public request still reads the stored map: one arm per visitor, original URLs unchanged, variant URLs redirect back, and bots receive the control page.

### Fixed
- The swapped variant content filter is registered once, at priority 0, so it does not run again after formatting filters.
- All-published coverage also leaves out the WooCommerce shop page, terms and refund pages, and the posts page. A shop page already in the map is not served as a variant.
- A duplicate that fails, including `invalid_page_template`, is not added to the page map. A published partial is trashed. Running the bulk action again reuses one existing variant for that source instead of creating another, and the screen lists the pages that failed.
- While a variant arm is shown at the original URL, oEmbed discovery links and `og:url` use that original URL, and a second `og:url` is removed.

## [0.4.98] - 2026-10-23

### Fixed
- Split-test responses send a single `Vary: Cookie` header. The early cache hook and the later template hook no longer each append `Cookie`.
- Bot visitors excluded from split tests still receive `DONOTCACHEPAGE` and `Cache-Control: private, no-store` on mapped pages, matching assigned visitors. A page cache cannot store the bot response and replay it.
- Variant HTML no longer includes the source page's `<!--more-->` marker, its `more-{sourceId}` anchor, or the teaser that belongs only to the source. Elementor's copied `_elementor_element_cache` is not reused as the variant body.
- A variant arm uses that variant page's title and SEO description in the document title, meta description, Open Graph description, Twitter description, and JSON-LD description. Rank Math and Yoast meta are used when present, then the variant title and excerpt. The canonical URL and `og:url` stay on the original page.

## [0.4.97] - 2026-10-22

### Fixed
- Site-wide split tests send `Cache-Control: private, no-store` and `Vary: Cookie`, and define `DONOTCACHEPAGE`, when an arm is assigned or served. Returning visitors with an assignment cookie get the same bypass, so a page cache cannot replay one arm to another visitor.
- The product-editor weather suggest control now ships `commerce-weather-suggest.js`. It calls `POST /geo-ai/v1/products/{id}/suggest-weather-facets` and checks the Geo Commerce facet boxes for the merchant to review and save. The enqueue is skipped when the file is missing.
- Deactivation clears `rwga_automation_cron_tick` and `rwga_weather_catalog_audit_tick` with `wp_clear_scheduled_hook`.
- Admin layout wraps license actions, preflight rows, and review summaries on a 390px-wide viewport.

## [0.4.96] - 2026-10-21

### Fixed
- Geo AI admin navigation and screen registration prefer the `rwgc_*` Geo Core APIs. The product-editor weather suggest button hooks `rwgc_product_tab_after_weather` on current Geo Core, and the deprecated `geocore_product_tab_after_weather` hook only on older Core.

## [0.4.95] - 2026-10-20

### Added
- Site-wide design test: one assignment per visitor across a page map, served at the original URL, with a 90-day `rwgo_site` cookie, LiteSpeed vary, bot and editor exclusions, and a `manage_options` QA override.

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
