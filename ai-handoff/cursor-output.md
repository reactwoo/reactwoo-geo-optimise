# Cursor output

## Status
done

## Task
Stop site-wide experiments showing Incomplete / Missing variant in the experiments list, Edit Test, and Reports when delivery is configured through the page map, a variant header or footer, or product template pairs.

## Root cause
Site-wide save writes `variants[control].page_id` and `variants[var_b].page_id` as 0. Delivery uses `page_map`, `theme_context.variant`, and `template_map`. The list badge, Edit Test health pill, and Reports warning treated a missing `var_b` page id as an incomplete single-page variant.

## Fix
`RWGO_Experiment_Service::admin_variant_health()` is admin-only. A site-wide test is complete when it has a page-map pair, a variant header or footer id, or a product template pair. Each mapped B page or template that is missing or not published is named in a warning. Single-page health still requires a publicly viewable Variant B page. `variant_b_is_routable()` and the stored config are unchanged.

## Files changed
- `includes/class-rwgo-experiment-service.php` — admin health helper.
- `includes/class-rwgo-experiment-repository.php` — a site-wide test with no source page id is not warned as a missing single-page source.
- `admin/views/tests-list.php`, `admin/views/edit-test.php`, `admin/views/reports.php` — consume the helper.
- `admin/views/partials/test-variant-management-edit.php` — skip the single-page “no Variant B” block for site-wide tests.
- `tests/Sitewide/RWGOAdminVariantHealthTest.php` — complete, draft warning, unmapped, and single-page cases.
- Version files to 0.5.10.

## Not changed
- Runtime assignment and site-wide delivery.
- Stored page map, theme context, template map, and zero variant page ids.
- Site-wide test settings screen form markup.
- No tag and no publish.

## Commands
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (128 tests, 855 assertions).

## Remaining errors
None from the PHPUnit suite.
