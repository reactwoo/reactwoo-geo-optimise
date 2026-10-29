# Cursor output

## Status
done

## Task
Staging QA fixes on PR #4 (`8d2fcec`). Do not merge or tag.

## Files changed and why
- `includes/class-rwgo-sitewide.php` — all-pages coverage skips every experiment variant and every active-test source. Saving the site-wide option purges the mapped originals and variants once. `X-LiteSpeed-Cache-Control` is sent only when LiteSpeed is active. Forbidden saves return 403.
- `includes/class-rwgo-page-duplicator.php` — `_elementor_data` is stored as JSON, other `_elementor_*` document meta is copied, and Atomic V4 trees are not passed through Elementor's document save.
- `includes/class-rwgo-cache-compat.php` — one purge per page per request, so promotion and page-map changes do not fire twice. Nonce responses omit the LiteSpeed header unless LiteSpeed is active. Clear-cache denial returns 403.
- `includes/class-rwgo-experiment-service.php` — draft, pending, and private variants block traffic.
- `includes/class-rwgo-admin.php`, `includes/class-rwgo-admin-wizard.php`, `includes/class-rwgo-gtm-live.php`, `includes/class-rwgo-ai-connector-diagnostics.php`, merged Geo AI admin handlers — forbidden actions return HTTP 403.
- `admin/views/partials/test-form.php`, `admin/views/tests-list.php`, `admin/views/edit-test.php`, `admin/views/help.php` — Variant B stays a draft; the UI says it gets no traffic until it is published.
- `tests/Sitewide/RWGOSitewideAssignmentTest.php`, `tests/Plugin/RWGOCacheCompatTest.php` — coverage, Elementor V4 copy, purge dedupe, and the LiteSpeed header.
- `CHANGELOG.md`, `readme.txt` — 0.5.1 notes for these fixes. Version stays 0.5.1.

## What was not changed
- No merge and no `v*` tag.
- Variant B is not auto-published. That is the safer choice: the duplicate can be reviewed before visitors see it.
- Site-wide private no-store behaviour when LiteSpeed is not the only page cache.

## Commands run and results
- `php -l` on the changed PHP files — no syntax errors.
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (55 tests, 370 assertions).
- `php tests/sitewide-assert.php` — all checks passed.

## Remaining errors
None in the unit harness.
