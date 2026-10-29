# Cursor output

## Status
done

## Task
Bump Geo Optimise to 0.5.0. Add bulk mapping for the site-wide experiment, register the swapped-content filter once, and open a PR. No merge and no `v*` tag.

## Files changed and why
- `includes/class-rwgo-sitewide.php` — coverage mode `all_published` with checkout, cart, my-account, and chosen ID exclusions; bulk duplicate via `RWGO_Page_Duplicator` that fills `page_map` in batches; resolver for pages published later (`include` or `skip`); map index so a large map is not scanned on every lookup; `filter_swapped_variant_content` is added to `the_content` once at priority 0.
- `tests/Sitewide/RWGOSitewideAssignmentTest.php`, `tests/sitewide-assert.php` — coverage, bulk selection, map append, single content-filter registration, and active config still requiring a page map.
- `reactwoo-geo-optimise.php`, `readme.txt`, `README.md`, `CHANGELOG.md`, `tests/bootstrap.php` — version 0.5.0.

## What was not changed
- One arm per visitor, original URLs, variant URL 302, bot control page, and variant SEO title/description with the original canonical URL.
- No Geo Core edits. No `v*` tag and no merge.
- Generated zip is not committed.

## Commands run and results
- `php -l includes/class-rwgo-sitewide.php` — no syntax errors.
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (40 tests, 234 assertions).
- `php tests/sitewide-assert.php` — all checks passed.
- `python3 scripts/package_zip.py` — wrote `reactwoo-geo-optimise-0.5.0.zip`.
- `tests/integration/sitewide-split-check.sh` was not run. It needs a staging URL.

## Remaining errors
None in the unit harness. Bulk duplication, the publish hook, and the admin screen still need a WordPress request to exercise.
