# Geo AI merge — standalone deprecation (Phase 6)

**Status:** Implemented  
**Date:** 2026-07-08

Standalone **ReactWoo Geo AI** (`reactwoo-geo-ai`) is deprecated. **ReactWoo Geo Optimise** is the surviving product.

## Customer-facing

- New installs: **Geo Core + Geo Optimise** only.
- Existing Geo AI sites: update Optimise, verify hub tabs, deactivate standalone Geo AI.

## Technical

| Area | Change |
|------|--------|
| `reactwoo-geo-ai` | `RWGA_Deprecation` compatibility shim; boots at `plugins_loaded` 25; defers when Optimise embedded AI is active |
| `reactwoo-geo-ai` CI | Tag push publish blocked; manual dispatch requires `PUBLISH_DEPRECATED` override |
| `reactwoo-geo-optimise` | Conflict notice includes one-click deactivate link |
| `reactwoo-api` | `Deprecation: true` + `Link` successor header on `/geo-ai/*` responses |

## Rollback

Install tagged standalone **v0.4.140** from git if needed. Merged Optimise builds remain independent.

## References

- `reactwoo-geo-ai/docs/DEPRECATED.md`
- `docs/MERGE-GEO-AI-INTO-OPTIMISE.md` § Phase 6
