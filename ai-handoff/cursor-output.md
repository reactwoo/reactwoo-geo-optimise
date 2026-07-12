# Cursor output

## Status

done

## Files changed

### react-cloud (1.1.10)
- `utils/gtmProvision.js` — pack → GTM API create bodies + provision
- `routes/googleAds.js` — GTM OAuth integration + `/gtm/*` routes
- `package.json` / `README.md` / `CHANGELOG.md`

### reactwoo-geo-optimise (0.4.78)
- `includes/class-rwgo-cloud-client.php`
- `includes/class-rwgo-gtm-live.php`
- Tracking Tools live GTM UI
- `docs/MEASUREMENT-CONTRACT.md` Phase 7

## Not changed

- GTM container auto-publish
- Account/container picker dropdowns (manual IDs + cloud list APIs)

## Ops note

Deploy **react-cloud** to production and enable **Tag Manager API** on the GCP OAuth project before live push works end-to-end.
