# Cursor output

## Status
done

## Task
Redesign the Site-wide test admin screen to the approved frames, keep the stored experiment format, and add a New test menu on the experiments list.

## Files changed
- `includes/class-rwgo-sitewide-screen.php` — payload mapping, search and duplicate endpoints, screen model.
- `includes/class-rwgo-sitewide.php` — screen delegate, UI save path, bulk checkbox ids, public variant copy.
- `includes/class-rwgo-plugin.php` — load the screen class.
- `admin/views/sitewide-screen.php`, `admin/css/rwgo-sitewide-screen.css`, `admin/js/rwgo-sitewide-screen.js` — grouped screen. Menus are fixed on `document.body`. Section cards use a 24px gap and `overflow: visible`.
- `admin/views/partials/new-test-menu.php`, `admin/views/tests-list.php`, `admin/css/rwgo-admin.css` — New test menu for a single page test or a site-wide test, including an empty list.
- `tests/Sitewide/RWGOSitewideScreenTest.php` — stored-format mapping, search permission, and the experiments menu.
- Version files stay at 0.5.10 (`RWGO_VERSION`, plugin header, readme Stable tag, README, changelog).

## Not changed
- Stored option name and shape (`rwgo_sitewide_experiment`).
- Create Test wizard.
- Runtime assignment and public request path.
- No tag and no publish.

## Commands
- `php /tmp/phpunit-9.6.phar --filter RWGOSitewideScreenTest` — 9 tests, 65 assertions, OK.
- `php /tmp/phpunit-9.6.phar --filter 'RWGOSitewideScreenTest|RWGOSitewideAbilityTest'` — 18 tests, 152 assertions, OK.
- Headless Chrome with WordPress `common.min.css` and `forms.min.css` rendered the populated screen at 1440×2200. Card gap measured 24px, card overlap 0, row did not overflow the content box.

## Remaining errors
None from the tests above.
