# Cursor output

## Status
done

Invalid licence keys no longer start a 30-second login on every request. Version stays **0.5.2**. Not tagged and not merged.

## Files changed
- `merged-geo-ai/includes/class-rwga-platform-client.php` — every login caller shares the saved token and a 900-second failure window (transient plus an option). Front-end and WP-CLI requests do not start a login. Other logins time out at 10 seconds. Admin-post and admin-ajax may still use 30 seconds.
- `includes/class-rwgo-platform-client.php` — the Optimise bearer and cloud client use the same rules.
- `merged-geo-ai/includes/services/class-rwga-intelligence-sync-service.php` — the `init` sync does not call the API on ordinary front-end or CLI requests.
- `tests/test-update-login.php` — two runs with a bad key make one login, and a dropped transient still blocks the next one.
- `CHANGELOG.md`, `readme.txt` — 0.5.2 notes. Version was not bumped.

## Not changed
- Plugin version, `RWGO_VERSION`, and the readme stable tag stay 0.5.2.
- No tag and no merge.

## Commands
- `php tests/test-update-login.php` — passed.
- `python3 tests/test-zip-js-order.py` — passed.
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (55 tests, 370 assertions).

## Remaining errors
- None.

## Status
done

QA bump to **0.5.2** on the Foxhole zip-order and update-login branch. The branch was already even with `main`. Version was not tagged and the PR was not merged.

## Files changed
- `reactwoo-geo-optimise.php` — plugin header and `RWGO_VERSION` are 0.5.2.
- `readme.txt` — stable tag 0.5.2 and changelog entry.
- `CHANGELOG.md` — 0.5.2 notes for the zip order and the plugins-screen login path.
- `README.md` — version line.
- `tests/bootstrap.php` — `RWGO_VERSION` for the PHPUnit bootstrap.

## Not changed
- No tag and no merge.
- Historical 0.5.1 changelog entries.

## Commands
- `python3 tests/test-zip-js-order.py` — passed.
- `php tests/test-update-login.php` — passed.
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (55 tests, 370 assertions).

## Remaining errors
- None.

## Status
done

Release zips write `reactwoo-geo-optimise.php` and `readme.txt` first, then the other non-JS files, then `.js`, in a stable order. Entries 1, 2, 4, and 11–24 are never `.js`. On the plugins-screen update path, a save that only changes `last_checked` does not log in, a failed login is remembered for 15 minutes, and that login times out at 10 seconds. Other API calls still use 30 seconds. Version was not bumped. Not tagged and not merged.

## Files changed
- `scripts/package_zip.py` — Foxhole-safe, stable zip order and a guard on the built zip.
- `tests/test-zip-js-order.py` — rule windows, stable order, and the packaged tree.
- `merged-geo-ai/includes/class-rwga-platform-client.php` — `prime_jwt_before_plugins_transient` skips a `last_checked`-only save. Update-path login (prime, warm-up, and `get_bearer_for_updates`) uses 10 seconds and a 15-minute failure cache.
- `includes/class-rwgo-platform-client.php` — the bearer callback Geo Core calls for this plugin accepts that timeout and uses the same failure cache.
- `tests/test-update-login.php` — covers both clients.
- `package.json` — `test:zip-js-order` and `test:update-login` scripts.
- `ai-handoff/cursor-output.md` — this note.

## Not changed
- Plugin version, readme stable tag, and changelog.
- Geo Core's satellite updater. Optimise still registers with it.
- Usage and other API calls still use the 30 second login timeout.

## Commands
- `python3 tests/test-zip-js-order.py` — passed.
- `php tests/test-update-login.php` — passed.
- `php -l` on the changed PHP files — no syntax errors.
- `python3 scripts/package_zip.py` — zip created. Entry 1 is the bootstrap, entry 2 is `readme.txt`, first `.js` is entry 311. No `.js` at entries 1, 2, 4, or 11–24. Zip deleted afterwards and not committed.

## Remaining errors
- None from these tests.

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
