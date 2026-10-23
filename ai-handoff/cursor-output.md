# Cursor output

## Status
done

## Task
Draft 0.4.98 from main (merge 252439d, 0.4.97). Staging QA follow-ups only. No merge and no `v*` tag.

## Files changed and why
- `includes/class-rwgo-sitewide.php` — one `Vary: Cookie` header per response; bots on mapped split-test URLs get `DONOTCACHEPAGE` and the same private no-store headers as assigned visitors; variant swaps rebuild content globals from the variant page and strip the source page more-marker and its teaser.
- `includes/class-rwgo-page-duplicator.php` — do not copy `_elementor_element_cache` (or other generated Elementor files) onto a variant. That cache is the source page's rendered HTML.
- `readme.txt`, `CHANGELOG.md`, `README.md`, plugin header and `RWGO_VERSION` — 0.4.98.
- `tests/Sitewide/RWGOSitewideAssignmentTest.php`, `tests/sitewide-assert.php` — Vary-once, bot cache bypass, and source-marker stripping.

## What was not changed
- No Geo Core or Geo Core Pro edits.
- No `v*` tag and no merge. Publish workflow stays tag-only.
- 390px admin layout CSS was not edited.
- Existing per-page `rwgo_ab` redirects.
- Text domain on existing Geo AI strings stays `reactwoo-geo-ai`. Plugin header text domain remains `reactwoo-geo-optimise`.

## Commands run and results
- `php -l` on `includes/class-rwgo-sitewide.php` and `includes/class-rwgo-page-duplicator.php` — no syntax errors.
- `php tests/sitewide-assert.php` — all checks passed, including single Vary, bot bypass, and source-marker stripping.
- `phpunit --configuration phpunit.xml.dist` — OK (30 tests, 141 assertions).

## Remaining errors
None.
