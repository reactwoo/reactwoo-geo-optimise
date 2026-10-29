# Cursor output

## Status
done

## Task
Geo Optimise 0.5.1 against main (`5b6add8`). Make variant assignment cache-safe. One PR. Do not merge or tag.

## Files changed and why
- `includes/class-rwgo-cache-compat.php` — LiteSpeed vary cookies and `X-LiteSpeed-Vary`; guarded purges for LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, Cloudflare, and Elementor CSS; settings clear action.
- `includes/class-rwgo-sitewide.php` — stable assignments can be cached when LiteSpeed is the only page cache. New cookies, bots, QA, and any other page cache still bypass. Purge delegates to the cache adapters.
- `includes/class-rwgo-experiment-repository.php` — a saved experiment config purges the previous and new original and variant URLs.
- `includes/class-rwgo-assignment.php` — a newly written assignment cookie is uncacheable only when a page-cache plugin is active.
- `includes/class-rwgo-runtime.php`, `includes/class-rwgo-rest-tracking.php`, `assets/js/rwgo-tracking.js` — cached HTML no longer carries the goal nonce. The script fetches `GET /rwgo/v1/tracking-nonce` and that response is uncacheable.
- `includes/class-rwgo-plugin.php` — boots the cache adapter.
- `admin/views/settings-optimisation.php` — Clear Optimise cache button (capability is checked on submit, nonce on the form).
- `admin/views/partials/tools-section-developer.php` — developer note matches the fresh-nonce request.
- `tests/Plugin/RWGOCacheCompatTest.php`, `tests/Sitewide/RWGOSitewideAssignmentTest.php` — vary list, empty purge plan, lifecycle URL selection, stable-arm cache rule.
- `reactwoo-geo-optimise.php`, `readme.txt`, `CHANGELOG.md`, `README.md`, `tests/bootstrap.php` — version 0.5.1.

## What was not changed
- No merge and no `v*` tag.
- Site-wide private no-store behaviour when LiteSpeed is not the only page cache, including when no cache plugin is active.
- Assignment, geo detection, and the one-arm / original-URL / variant-redirect rules.

## Commands run and results
- `php -l` on the changed PHP files — no syntax errors.
- `node --check assets/js/rwgo-tracking.js` — ok.
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (51 tests, 327 assertions).
- `php tests/sitewide-assert.php` — all checks passed.

## Remaining errors
None in the unit harness. Staging still needs the QA checklist in the PR (LiteSpeed vary hit/miss, purge on save/start/stop/promote, fresh nonce, and the settings button).
