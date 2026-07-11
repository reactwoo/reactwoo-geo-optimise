# Cursor output — measurement contract phase 1

**Status:** done  
**Date:** 2026-07-11

## Summary

Added stable semantic `data-rwgo-element-key` end-to-end without changing physical goal/handler conversion matching.

## Files changed

| File | Why |
|------|-----|
| `includes/class-rwgo-element-key.php` | Sanitize / derive keys |
| `includes/class-rwgo-plugin.php` | Load helper |
| `includes/class-rwgo-elementor-goals.php` | Control + stamp |
| `includes/class-rwgo-gutenberg-goals.php` | Attr + stamp |
| `admin/js/rwgo-block-goals.js` | Editor field |
| `includes/class-rwgo-defined-goal-service.php` | Collect `element_key` |
| `assets/js/rwgo-tracking.js` | Fire + dataLayer |
| `includes/class-rwgo-event-payload.php` | Payload field |
| `includes/class-rwgo-rest-tracking.php` | REST accept |
| `includes/class-rwgo-gtm-handoff.php` | DLV + examples |
| `docs/MEASUREMENT-CONTRACT.md` | Contract doc |
| `admin/views/partials/tools-section-tracking-advanced.php` | Hint |

## Not changed

- Exposure event store schema
- Goal mapping / winner logic
- Elementor write path

## Next

Exposure events + tracking manifest; then Atomic page executor stamping keys from blueprints.
