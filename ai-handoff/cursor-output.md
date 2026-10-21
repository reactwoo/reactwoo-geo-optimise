# Cursor output

## Status
done

## Task
Site-wide design split test — audit, then implement the gaps in Geo Optimise only. Staging only. Geo Core and Geo Core Pro were not changed.

## Phase 1 answers

### A. Assignment

`rwgo_get_variant()` (`includes/functions-rwgo.php` lines 18–26) calls `RWGO_Assignment::get_variant()` (`includes/class-rwgo-assignment.php` lines 32–68). `rwgo_get_assignment_map()` (functions file lines 33–37) returns `RWGO_Assignment::get_map()`.

`assignment_per_route_resolved` is not an assignment mode. `RWGO_Stats::get_snapshot()` (`includes/class-rwgo-stats.php` lines 79–81) sets it to `assignment_count / route_resolved_count` when route events exist. It is a ratio on the diagnostics snapshot.

Bucketing is `RWGO_Assignment::pick_variant()` (lines 175–198). With no weights it uses `wp_rand()`. With weights it draws `wp_rand(1, 1000000)`. It is not derived from a visitor id. The first call stores the pick. Later calls reuse it when the stored value is still in the allowed list (lines 48–50). A value outside that list is replaced.

Cookie `rwgo_ab` (line 15), written in `persist()` (lines 122–135):

| Attribute | Value |
| --- | --- |
| Lifetime | 30 days |
| Path | `COOKIEPATH`, or `/` |
| Domain | `COOKIE_DOMAIN`, or empty |
| Secure | `is_ssl()` |
| HttpOnly | true |
| SameSite | not set |

There is no stable visitor id. The cookie is a JSON map of experiment key to variant. Same-request PHP reads `$_COOKIE`. If headers were already sent, `persist()` returns without writing (lines 123–125) and the in-memory pick is still returned.

Scope is per experiment key, inside one cookie. Two experiments do not share an arm. A visitor can be control on one page test and variant on another. There was no experiment group and no site-wide map.

The redirect that triggers assignment is `RWGO_Page_Swapper::maybe_redirect()` on `template_redirect` priority 5 (`includes/class-rwgo-page-swapper.php` lines 22–24 and 29). Assignment also runs later from `RWGO_Runtime::maybe_record_variant_served()` on `template_redirect` priority 99 via `RWGO_Experiment_Service::resolve_variant_for_context()` (`includes/class-rwgo-experiment-service.php` lines 213–230). `get_variant()` itself has no hook. It runs whenever something calls it.

Bots and crawlers are not excluded. Logged-in users are assigned. `?rwgo_no_swap=1` skips the redirect only when the user passes `RWGO_Admin::required_capability()` (page swapper lines 33–35), and `resolve_variant_for_context()` then returns `control` (experiment service lines 222–224). `?preview=1` while logged in still assigns, then returns before the redirect (page swapper lines 85–87). The swapper returns immediately for admin, AJAX, and cron (lines 30–32). It does not name REST, JSON, or WP-CLI. Those requests are not singular, so the swapper does not assign them, but any other caller of `get_variant()` still would.

### B. Serving the variant

Page tests serve the variant with `wp_safe_redirect()` (page swapper line 106), which is a 302, then `exit`. It is not an internal rewrite and not a content swap. Geo Optimise does this. Geo Core Pro country routing is a separate 302.

Geo Core Pro `RWGC_Routing::maybe_route_request()` is on `template_redirect` priority 0 (`reactwoo-geocore-pro/includes/page-versions/class-rwgc-routing.php` lines 29–30 and 108). Page-version rewrites live in `RWGC_Page_Version_Routing` under Geo Core Pro. Geo Core 1.9.4 moved that redirect and the `/_gc/` rewrite into Pro (`reactwoo-geocore/CHANGELOG.md`). The WordPress.org build does not boot it (`reactwoo-geocore/tests/test-rwgc-page-variant-ownership.php`). Optimise does not call that router.

Optimise still requires Geo Core: `RWGO_Plugin::boot()` returns before experiments load when Geo Core is inactive (`includes/class-rwgo-plugin.php` lines 44–46). It does not require Geo Core Pro. With Pro inactive, existing page-test redirects still run. Country page versions and `/_gc/` do not.

A control visitor who opens a variant URL is not sent back. `resolve_variant_for_context()` (lines 232–238) treats “this URL is the variant page” as that variant even when the cookie says control. A page with no experiment is left as-is (page swapper lines 44–47). Other experiment keys already in `rwgo_ab` stay there. There is no site-wide arm to keep.

Optimise does not set canonical, robots, or sitemap rules for variant pages. They are normal WordPress pages.

Country routing runs first (priority 0) and exits on a 302, so the page test does not run on that request. If the country router does not redirect, the page test can 302 at priority 5. A `/_gc/` request makes the country router return immediately (routing class lines 39–41). Both can still apply on a later request. The order is whatever those priorities do. It was not a single site-wide decision.

### C. Caching

Optimise does not register a LiteSpeed vary for the arm. It does not send `X-LiteSpeed-Cache-Control`, `litespeed_control_set_nocache`, or `Cache-Control` on the assignment redirect. `wp_safe_redirect()` does not add `no-store`. Goal REST responses call `nocache_headers()` (`includes/class-rwgo-rest-tracking.php`). That does not cover the public page.

Geo Core varies cache by server-resolved country and page version, not by the client cookie (`reactwoo-geocore/includes/integrations/class-rwgc-cache-compat.php` lines 15–17, 21–23, 171–177). Cookies are `rwgc_cc` and `rwgc_pv`. A mismatched country cookie is overwritten (lines 111–126). Optimise does not do the equivalent for `rwgo_ab`. An arm string that is still in the allowed list is trusted.

A first request with no `rwgo_ab` cookie can be served from a full-page cache before PHP runs. The visitor then sees whatever HTML was stored (usually control) and no cookie is set. If PHP does run and the first visitor is variant, a cached 302 on the original URL can send later visitors to the variant.

QUIC.cloud honours LiteSpeed vary groups when LiteSpeed is the origin cache. Geo Core 1.8.168 and 1.8.169 cover that for `rwgc_cc` / `rwgc_pv` and QUIC edge IPs. They do not cover an Optimise arm. Cloudflare does not read `litespeed_vary_add`. A Cloudflare “cache everything” rule can serve one arm to everyone. Default Cloudflare does not cache HTML.

### D. Measurement

Goals are stored per experiment key. WooCommerce purchase (`includes/class-rwgo-woocommerce-goals.php` `on_thankyou()`, from line 161) counts only when the order’s products belong to an active experiment and `rwgo_ab` already has that experiment’s variant. A contact form that lands on `/contact/thank-you/` counts only when that page is part of the same page test or a destination goal is stamped for it. Reports are per experiment (`includes/class-rwgo-winner-service.php`). There is no whole-site arm.

QA today is `?rwgo_no_swap=1`, limited by `RWGO_Admin::required_capability()` (default `manage_options`, or `manage_woocommerce` for a shop manager). It forces control only. The public cannot use it. There is no `?rwgo_arm=`.

## Gap list

| Requirement | Before this change |
| --- | --- |
| One arm for the whole visit | Not supported. Per experiment key. |
| Deterministic split from a stored id | Not supported. `wp_rand()`. |
| Cookie Path=/, SameSite=Lax, 90 days | Partly supported. `rwgo_ab` is HttpOnly and Secure-on-SSL, 30 days, no SameSite, path follows `COOKIEPATH`. |
| Decision before output | Partly supported. `template_redirect` 5, and the write is skipped if headers were sent. |
| Original URL for both arms | Not supported. Variant arm is a 302 to another URL. |
| Uncacheable first response, then vary by arm | Not supported. |
| Forged arm overwritten | Partly supported. Only an arm outside the allowed list is replaced. |
| Variant canonical, sitemap exclusion, control 302 back | Not supported. |
| Bots always control, no cookie | Not supported. |
| Editors control unless they force an arm | Partly supported. `rwgo_no_swap` forces control for a capable user. |
| `?rwgo_arm=` for `manage_options` only | Not supported. |
| Pause returns everyone to the original and purges mapped URLs | Partly supported. Status `active` stops new redirects. No LiteSpeed purge. |
| Whole-site goals | Not supported. |
| No clash with country routing | Partly supported. Priorities differ, but it is not one documented decision. |

## Serving choice

Internal render at the original URL. A 302 keeps analytics and cache on two URLs and makes a cached redirect dangerous. Rendering the variant page in the main query keeps one URL for both arms. Canonical points at that URL. A direct variant URL 302s to the original for every arm, with `Cache-Control: no-store, private`. Existing page tests still 302. They are unchanged apart from skipping a `test_type` of `sitewide`.

Country routing still runs at priority 0 and can exit. The site test redirects variant URLs at priority -1, then renders the arm at priority 1. If country routing sends the original page somewhere else, that destination is what the visitor gets. The site test applies there only when that page is in the map.

## Changes made

Version **0.4.95**. Assignment checks passed with `php tests/sitewide-assert.php`. Staging curl was not run because no page map is saved.

- `includes/class-rwgo-sitewide.php` — assignment, cookie, internal render, variant 302, cache headers, LiteSpeed vary `rwgo_arm_control` / `rwgo_arm_variant`, bots, editors, QA override, canonical, sitemap exclusion, goals, admin screen, purge on save.
- `includes/class-rwgo-assignment.php` — `remember()` so the site arm is mirrored into `rwgo_ab` without a new random roll.
- `includes/class-rwgo-page-swapper.php` — skip a request the site test already handled, and skip site-wide configs.
- `includes/class-rwgo-runtime.php` — do not record site-wide configs through the per-page path.
- `includes/class-rwgo-plugin.php` — load and boot the site test.
- `includes/class-rwgo-admin.php` — Experiences → Site-wide test.
- `tests/Sitewide/RWGOSitewideAssignmentTest.php` — PHPUnit coverage.
- `tests/sitewide-assert.php` — same checks without a PHPUnit binary.
- `tests/integration/sitewide-split-check.sh` — staging curl script.
- `tests/integration/mint-sitewide-cookie.php` — builds a cookie whose arm matches the bucket.
- `phpunit.xml.dist`, `CHANGELOG.md`, `readme.txt`, plugin header.

Cookie `rwgo_site`: JSON `id`, `arm` (`control` or `variant`), `exp`. Path `/`, SameSite `Lax`, HttpOnly, Secure when `is_ssl()`, lifetime from the test (default 90 days). The id is 32 hex characters. The arm is `bucket_arm()` over SHA-256 of `id|experiment_key`. A forged arm or a `qa` flag from someone who cannot `manage_options` is overwritten. Bots, editors, REST, AJAX, cron, CLI, preview, and JSON requests do not get a cookie. Editors see control. `?rwgo_arm=control|variant` works only for `manage_options`, and the admin bar shows the arm.

Unmapped pages are not assigned and are not varied. An existing cookie is left in place, so the visitor keeps the arm. Thank-you page IDs and WooCommerce purchases on an active test are stored as goals for that arm (`control` / `var_b` in reports).

Pausing or ending the test, or saving the map, purges LiteSpeed for the mapped post IDs and permalinks. While the test is not `active`, originals are served and the cookie is ignored.

### Tests

`php tests/sitewide-assert.php` — all checks passed (deterministic bucket, cookie attributes, forged cookie overwritten, new id for invalid JSON, Googlebot, QA capability, editor lock, no assignment for admin, AJAX, cron, REST, CLI, preview, JSON).

PHPUnit is not installed in this environment. The same cases are in `tests/Sitewide/RWGOSitewideAssignmentTest.php`. `php -l` passed on the new and edited PHP files.

The staging curl script was not run. No page map is saved, and this pass does not call staging.

## Changes needed in Geo Core or Geo Core Pro

None made.

No Core change is required for the site test. Optimise calls `litespeed_vary_add` and `litespeed_control_set_nocache` itself.

Leave country routing at priority 0. Do not move it. The site test is written around that order.

## Known limitations

- Cloudflare, or any CDN that is not LiteSpeed / QUIC.cloud, ignores LiteSpeed vary groups. Cache HTML only when `Cache-Control` is public, or bypass when `rwgo_site` is absent. The first response is `no-store, private`.
- QUIC.cloud follows LiteSpeed vary only when LiteSpeed is the cache in front of PHP. A warm cache from before this version must be purged. Saving the test does that for mapped URLs.
- Country routing still wins when it redirects. The redesign is not applied on a request that Geo Core Pro already redirected away from.
- Elementor header and footer follow the swapped queried page. Theme-builder conditions must target the variant page.
- Logged-in editors are not entered into the test. LiteSpeed usually skips logged-in users. Their responses are also marked uncacheable.
- The curl script needs the real staging paths, and a saved active map, before it can pass.
- Existing per-page tests still use `rwgo_ab` and a 302. They are not converted into the site-wide arm.

## What was not changed

- Geo Core and Geo Core Pro.
- No commit, tag, or push.
