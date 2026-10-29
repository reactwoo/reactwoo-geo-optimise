# Cursor output

## Status
done

## Task
Staging QA follow-up on `f3620f1`. Keep the 0.5.0 PR. Exclude WooCommerce special pages and the posts page, roll back failed duplicates, and stop variant URLs leaking into oEmbed and og:url. No merge and no tag.

## Files changed and why
- `includes/class-rwgo-sitewide.php` — shop, terms, refund, and posts page are reserved; a reserved page already in the map is not served as a variant. Failed duplicates are not mapped, published partials are trashed, and a later run reuses one existing variant. Bulk failures are stored and shown. Head output keeps one original og:url and rewrites oEmbed discovery links.
- `tests/Sitewide/RWGOSitewideAssignmentTest.php`, `tests/sitewide-assert.php` — shop and posts-page exclusions, failed-duplicate result, reuse choice, oEmbed rewrite, and a single og:url.
- `CHANGELOG.md`, `readme.txt` — 0.5.0 notes for these fixes. Version stays 0.5.0.

## What was not changed
- One arm per visitor, original URLs, variant 302, bots on the control page, and variant SEO title/description with the original canonical.
- No Geo Core edits. No `v*` tag and no merge.

## Commands run and results
- `php -l includes/class-rwgo-sitewide.php` — no syntax errors.
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (43 tests, 259 assertions).
- `php tests/sitewide-assert.php` — all checks passed.
- `python3 scripts/package_zip.py` — wrote `reactwoo-geo-optimise-0.5.0.zip` (not committed).

## Remaining errors
None in the unit harness. Staging still needs to confirm the shop page, a failed `invalid_page_template` copy, a rerun, and the rendered head for oEmbed and og:url.
