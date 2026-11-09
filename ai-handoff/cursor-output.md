# Cursor output

## Status
done

## Task
A non-Optimise key could obtain a Geo Optimise access token and update check because login sent only `product_slug` / `catalog_slug`. The license server compares the key's package when `package_type` is sent.

## Root cause
`RWGO_Settings::filter_auth_login_body` and `RWGO_Platform_Client::get_access_token` named the catalog but did not send `package_type`. A returned token was cached even when its package was another product, and the self-updater sent that bearer to `/api/v5/updates/check`.

## Fix
Login JSON now includes `package_type` `reactwoo-geo-optimise` from the Optimise client and from the login-body filter (including the embedded Geo AI filter). A token is kept only when its package is `reactwoo-geo-optimise` or the legacy `reactwoo-geo-ai` alias, or when `product_slugs` / `productSlugs` lists Geo Optimise (Decision Cloud Growth and Scale). `product_slug` alone does not count. The self-updater and the Geo Core bearer callback use that check. Saving or testing a mismatched key records `rwgo_license_wrong_product` and the License screen shows that message. Version is 0.5.8 because 0.5.7 is already tagged.

## Files changed
- `includes/class-rwgo-platform-client.php` — send `package_type`, reject non-covering tokens, record the license check.
- `includes/class-rwgo-settings.php` — same field on the shared login filter; validate when the saved key changes.
- `includes/class-rwgo-self-updater.php` — request a catalog check only with a covering bearer.
- `includes/class-rwgo-admin.php`, `admin/views/license-settings.php` — store the error code and show the mismatched-key notice.
- `includes/class-rwgo-merge-bridge.php`, `includes/class-rwgo-sitewide-ability.php` — a rejected key is not an Optimise entitlement.
- `merged-geo-ai/includes/class-rwga-settings.php`, `merged-geo-ai/includes/class-rwga-platform-client.php` — embedded login sends `package_type` and does not keep a non-covering token.
- Tests, plugin header, `RWGO_VERSION`, readme stable tag, changelog, README.

## Not changed
- react-license and reactwoo-api. Those repos are not readable with this run's GitHub credential. The server follow-up is in the pull request.
- No tag and no merge.

## Commands
- `php tests/test-update-login.php` — update login checks passed.
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (115 tests, 737 assertions).
- No WordPress install is present. The License screen was not clicked in a browser.

## Remaining errors
None from the unit and login checks.
