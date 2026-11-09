# Cursor output

## Status
done

## Task
Geo Optimise AI is bring-your-own-key unless the site has an active Decision Cloud entitlement that covers Optimise. Hosted ReactWoo AI must not run on the default chain, and it must not call api.reactwoo.com AI routes without that entitlement.

## Root cause
`RWGA_Settings::get_defaults` and `RWGA_Generation_Router::resolve_chain` put `managed` after WordPress AI for `automatic`, and `remote_fallback` was managed then local. `RWGA_Managed_AI_Transport` and `RWGA_Platform_Client::get_usage` called the hosted API for any connected licence.

## Fix
The default chain is WordPress AI, then local. `managed` and legacy `remote` use hosted AI only when a cached licence JWT is Decision Cloud Growth or Scale and `product_slugs` includes Geo Optimise. Saved `managed`, `remote`, and `remote_fallback` values are not deleted. Without that entitlement they behave as automatic. `remote_fallback` never selects hosted AI. Intelligence workflows and cloud snapshot sync show "Available with ReactWoo Decision Cloud" and return that message instead of calling the API. Licence login and the update check are unchanged. Version is 0.5.9.

## Files changed
- `includes/class-rwgo-decision-cloud.php` — cached-token entitlement, migration stamp, AI path gate, admin notice.
- Generation router, engine, managed transport, remote client, usage guard, platform client, intelligence workflow, snapshot wizard.
- Advanced, UX reviewer, intelligence, and licence screens.
- `docs/GENERATION-TRANSPORTS.md`, version files, PHPUnit coverage.

## Not changed
- `POST /api/v5/auth/login` and `/api/v5/updates/check`.
- No tag and no merge.

## Commands
- `php /tmp/phpunit.phar --configuration phpunit.xml.dist` — OK (122 tests, 803 assertions).
- `php tests/test-update-login.php` — update login checks passed.

## Remaining errors
None from the unit and login checks. No WordPress admin browser was available, so the notice screens were not clicked.
