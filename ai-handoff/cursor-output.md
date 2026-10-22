# Cursor output

## Status
done

## Task
Draft 0.4.97 from main (v0.4.96). Staging QA fixes only. No merge and no `v*` tag.

## Files changed and why
- `includes/class-rwgo-sitewide.php` — mapped responses that assign an arm or serve a stored assignment cookie send `Cache-Control: private, no-store`, `Vary: Cookie`, and `DONOTCACHEPAGE`.
- `merged-geo-ai/assets/js/commerce-weather-suggest.js` — product-editor script for `POST /geo-ai/v1/products/{id}/suggest-weather-facets`. Checks `rwgcm_weather_facets[]` / `#rwgcm_weather_facet_{slug}`.
- `merged-geo-ai/includes/integrations/class-rwga-commerce-weather.php` — enqueue only when that file is readable.
- `includes/class-rwgo-deactivation.php` and `reactwoo-geo-optimise.php` — `register_deactivation_hook` calls `wp_clear_scheduled_hook` for `rwga_automation_cron_tick` and `rwga_weather_catalog_audit_tick`.
- `admin/css/rwgo-admin.css`, `merged-geo-ai/admin/css/rwga-admin.css`, `merged-geo-ai/admin/css/rwga-ux-reviewer.css` — 390px-wide admin screens wrap instead of overflowing.
- `readme.txt`, `CHANGELOG.md`, `README.md`, plugin header and `RWGO_VERSION` — 0.4.97.
- `tests/Sitewide/RWGOSitewideAssignmentTest.php`, `tests/sitewide-assert.php`, `tests/Plugin/RWGODeactivationTest.php`, `phpunit.xml.dist` — coverage for the cache bypass, cron clear, and weather script.

## What was not changed
- No Geo Core or Geo Core Pro edits.
- No `v*` tag and no merge. Publish workflow stays tag-only.
- Existing per-page `rwgo_ab` redirects.
- Text domain on existing Geo AI strings stays `reactwoo-geo-ai`. New copy uses no new gettext calls. Plugin header text domain remains `reactwoo-geo-optimise`.

## Commands run and results
- `php -l` on edited PHP files — no syntax errors.
- `php tests/sitewide-assert.php` — all checks passed, including returning-visitor cache bypass.
- `phpunit --configuration phpunit.xml.dist` — OK (24 tests, 112 assertions).

## Remaining errors
None.
