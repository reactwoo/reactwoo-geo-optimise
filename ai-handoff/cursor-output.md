# Cursor output

## Status
done

## Task
Staging QA follow-up on draft PR #2 (`241a1dc`). The variant body is correct. The document head still used the source page title and SEO description from Rank Math. The variant must supply those, while the canonical URL, `og:url`, variant URL redirect, and bot control page stay as they are. No merge and no `v*` tag.

## Files changed and why
- `includes/class-rwgo-sitewide.php` — when the variant arm is rendered, the document title, meta description, Open Graph title/description, Twitter title/description, and JSON-LD description are resolved from the variant page. Rank Math meta is used first, then Yoast, then the variant title and excerpt. Canonical and `og:url` filters still return the original permalink. Head filters do nothing unless the variant swap succeeded, so bots stay on the control page.
- `tests/Sitewide/RWGOSitewideAssignmentTest.php`, `tests/sitewide-assert.php` — variant head resolution, JSON-LD description replacement that keeps URLs, idle filters, and original canonical URL.
- `CHANGELOG.md`, `readme.txt` — 0.4.98 note for the head fix. Version stays 0.4.98.

## What was not changed
- Variant URL redirect (`maybe_redirect_variant_url`).
- Bot assignment: bots still receive the control page and do not swap.
- Body more-marker stripping and Elementor cache skipping.
- Single `Vary: Cookie` header and bot `DONOTCACHEPAGE` behaviour.
- No Geo Core edits. No `v*` tag and no merge.
- 390px admin layout CSS was not edited.

## Commands run and results
- `php -l includes/class-rwgo-sitewide.php` — no syntax errors.
- `php tests/sitewide-assert.php` — all checks passed, including variant head, JSON-LD, and original canonical URL.
- `phpunit --configuration phpunit.xml.dist` — OK (35 tests, 200 assertions).

## Remaining errors
None. Staging still needs to confirm Rank Math's rendered `<head>` for a variant arm.
