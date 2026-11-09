# Cursor output

## Status
done

## Task
Site-wide variant arm printed the B page and enqueued header/footer CSS, but Elementor never printed header 4040 or footer 4041. Hello’s fallback header showed. Control and cart/checkout were already fine.

## Root cause
Elementor Pro’s condition walker calls `elementor/theme/get_location_templates/template_id`, then records that template’s include and exclude rules against the ID the filter returns. The variant arm rewrote the control template ID to the variant ID. The control header’s `exclude/singular/page/{B page}` therefore removed the variant ID after the variant template’s own include had added it. `do_location()` had nothing to print. CSS was queued separately from the delivery plan.

## Fix
On the variant arm the control template is skipped (filter returns 0) so its excludes are not copied onto the variant. The variant header and footer are queued with `add_doc_to_location` before the location is printed, including when their own display conditions do not match the current URL. Control still substitutes its own template. Locked pages (cart, checkout, account) do not queue variant chrome.

## Files changed
- `includes/class-rwgo-theme-context.php` — skip control candidates, force-queue the arm templates, model Elementor’s condition walk and location markup, enqueue Elementor frontend and document runtime assets.
- `includes/class-rwgo-sitewide.php` — prime locations on `elementor/theme/register_locations`, `get_header`, and `get_footer`.
- `tests/Sitewide/RWGOThemeContextTest.php` — variant markup on the swapped B page and on a URL the variant conditions miss, control markup, excluded checkout, location-queue prime.
- Version 0.5.7 in the plugin header, `RWGO_VERSION`, readme stable tag, changelog, README, test bootstrap, and the local WordPress integration version check.

## Not changed
- Canonical, noindex, sitemap exclusion, and variant-URL redirects.
- Product/single template map and the 0.5.6 body swap.
- No tag and no merge.

## Commands
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (108 tests, 717 assertions).
- `php tests/integration/theme-ab-routing.php` — all checks passed.
- `php tests/sitewide-assert.php` — all checks passed.
- No WordPress, Elementor, or Elementor Pro install is present in this environment. `tests/integration/local-wp-theme-ab.php` and `tests/integration/elementor-free-template-css.php` need `wp-load.php` and were not run.

## Remaining errors
None from the unit and routing checks.
