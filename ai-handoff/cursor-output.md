# Cursor output

## Status
done

## Task
Site-wide A/B: bind Elementor header and footer to the existing arm, keep unpublished pages unpublished, and stop preview from contaminating assignment or reporting.

## Files changed
- `includes/class-rwgo-theme-context.php` — delivery plan, Elementor location filter, signed preview, consent gate, redirect loop guard, cache bypass for a theme split.
- `includes/class-rwgo-sitewide.php` — uses that plan on the existing site-wide runtime. Admin warnings and preview links. Does not publish drafts.
- `includes/class-rwgo-plugin.php` — loads the theme context class.
- `includes/class-rwgo-cache-compat.php` — purge includes header and footer template IDs.
- `includes/class-rwgo-exposure.php`, `includes/class-rwgo-runtime.php`, `includes/class-rwgo-rest-tracking.php`, `assets/js/rwgo-tracking.js` — skip exposure and goals for preview and for refused statistics consent.
- `tests/Sitewide/RWGOThemeContextTest.php`, `tests/integration/theme-ab-routing.php` — routing, cache, preview, consent.
- `docs/SITEWIDE-AB.md`, `docs/MEASUREMENT-CONTRACT.md`, `CHANGELOG.md` — setup, preview, staging, rollback.

## Not changed
- No version bump, tag, package, or merge.
- No customer page is published.
- Per-page redirect tests are unchanged.
- Geo Core and Commerce pricing are untouched. Cart, checkout, and account do not take variant chrome.

## Commands
- `php -l` on the changed PHP files — no syntax errors.
- `php tests/integration/theme-ab-routing.php` — all checks passed.
- `php tests/sitewide-assert.php` — all checks passed.
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (72 tests, 444 assertions).
- Local WordPress at `http://127.0.0.1:8093` only. Not staging.reactwoo.com. WordPress 7.1.4, Elementor 4.3.6, Geo Core 1.9.26, Optimise 0.5.3 activated. Draft variant stayed draft. Variant URL returned 302 once. With Elementor Pro absent, a variant assignment kept the original body and `X-RWGO-Arm: control`.

## Not published
- No tag, no update-catalog publish, no merge, no production deploy.

## Remaining errors
None from the unit and routing checks. WordPress, Elementor, and a CDN were not booted in this run.
