# Geo Optimise + Geo AI — license consolidation (Phase 7)

**Status:** Done in react-license + API default slug  
**Date:** 2026-07-08

## react-license

- `utils/productSlugAliases.js` — `reactwoo-geo-ai` → `reactwoo-geo-optimise` canonical mapping
- Activation, access-token, JWT, refresh revoke — alias-aware product type checks
- `migrations/migrate_geo_ai_to_optimise_licenses.sql` — deprecate Geo AI package row, repoint licenses
- `docs/GEO-AI-OPTIMISE-LICENSE-MERGE.md` — operator guide

## reactwoo-api

- Site register default `product_slug`: `reactwoo-geo-optimise`
- Entitlement dual-slug already in `licenseAssistantShared.ts` (Phase 4)

## Deploy checklist

1. Deploy react-license code to license server.
2. Run `migrate_geo_ai_to_optimise_licenses.sql` on license DB (backup first).
3. Deploy reactwoo-api (optional: env `GEO_AI_ALLOWED_PRODUCT_SLUGS` includes both slugs during transition).

## Verify

- Activate Optimise with a license that was issued on legacy Geo AI package → success.
- JWT `product_slug` claim is `reactwoo-geo-optimise` when plugin requests Optimise slug.
- `POST /api/v5/geo-optimise/workflow` accepts legacy Geo AI JWT.

## References

- `react-license/docs/GEO-AI-OPTIMISE-LICENSE-MERGE.md`
- `docs/MERGE-GEO-AI-INTO-OPTIMISE.md` § Licence model
