# Cursor output

## Status

done

## Files changed

- `includes/class-rwgo-winner-policy.php` — sample/significance/exposure gates
- `includes/class-rwgo-promotion-automation.php` — optional auto-promote hook
- `includes/class-rwgo-winner-service.php` — exposure rates + attach policy + fire action
- `includes/class-rwgo-plugin.php` — require + `Promotion_Automation::init()`
- `includes/class-rwgo-admin.php` — enforce gate on promote (override flag)
- `admin/views/promote-winner.php` — policy gates UI + promote anyway
- `admin/views/reports.php` — policy summary in report headline
- `docs/MEASUREMENT-CONTRACT.md` — Phase 6
- Version bump → **0.4.77**

## Not changed

- Live GTM API OAuth push
- Standalone Geo AI

## Commands

- `php -l` on changed PHP files — OK

## Remaining

- Enable `rwgo_auto_promote_when_ready` / `winner_policy.enforce` only when product wants it
- Live GTM API later
