# Cursor output — GA4 measurement picker from Targeting

## Status

done

## Root cause

Tracking setup asked for a manual G-XXXX even when GeoCore Pro Targeting already had GA4 OAuth + a default property. GTM tags need the web-stream measurement ID from that property, not the numeric property id alone.

## Files changed

### react-cloud (1.1.11)
- `utils/googleAnalyticsAdmin.js` — list web-stream measurement IDs
- `routes/googleAds.js` — `GET /analytics/measurement-ids`
- `CHANGELOG.md`, `AGENTS.md`, `package.json`

### reactwoo-geo-optimise (0.4.87)
- `includes/class-rwgo-cloud-client.php` — `ga_measurement_ids()`
- `includes/class-rwgo-tracking-setup.php` — GA status, prefer Targeting default stream, next-step copy
- `admin/views/partials/tracking-setup-guide.php` — dropdown / connect CTA / manual fallback

## Behaviour

1. If GA not connected → CTA to GeoCore Pro Google Analytics + optional manual G-XXXX
2. If connected → dropdown of G-XXXX from web streams (Targeting default property first)
3. Empty saved measurement ID auto-prefers the Targeting default stream when listing

## What was not changed

- GTM OAuth / provision semantics
- Event names
- GeoCore Pro audience sync

## Note

Production React Cloud must deploy **1.1.11** before the Optimise dropdown can load streams.
