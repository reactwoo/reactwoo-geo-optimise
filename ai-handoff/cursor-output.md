# Cursor output

## Status
done

## Task
Skip Geo Optimise Elementor goal controls during heavy widgets-config (LiteSpeed 503).

## Files changed
- `includes/class-rwgo-elementor-goals.php` — do not register common-stack goal section on heavy `elementor_ajax`
- `includes/class-rwgo-elementor-page-goal.php` — skip document goal controls on the same path
- Version 0.4.93

## What was not changed
- Frontend goal stamping / tracking
