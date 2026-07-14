# Generation transports (WordPress AI / managed / local)

**Status:** shipped in Geo Optimise 0.4.68+  
**Canonical code:** `merged-geo-ai/includes/services/generation/`

## Modes

| Stored value | Behaviour |
|--------------|-----------|
| `automatic` (default for new installs) | WordPress AI → ReactWoo managed → local deterministic. Preflight skips unavailable transports; **Pro-tier / entitlement gates after a managed attempt also fall through to local**. |
| `wordpress_ai` | WordPress 7 AI Client / site BYOK provider only |
| `managed` | ReactWoo `/api/v5/geo-optimise/workflow` only |
| `local` | Deterministic workflow callback only |
| `remote` (legacy) | Treated as **managed** |
| `remote_fallback` (legacy) | Managed then local — **does not** prefer WordPress AI after upgrade |

## Boundaries

- Product licence + WP capability: `RWGA_Workflow_Base`
- Transport selection: `RWGA_Generation_Router`
- WordPress AI: public `wp_supports_ai()` / `wp_ai_client_prompt()` only — no provider keys in Optimise
- Managed: platform JWT + **ReactWoo managed AI allowance** (`RWGA_AI_Usage_Guard::can_run_managed_generation`)
- Cloud snapshot / intelligence graph: managed-only (`can_sync_snapshot`)

## Migrated workflows

Bounded generation (may use WordPress AI when supported + available):

- `ux_analysis`
- `ux_recommend`

Routed via the same router (managed / local; WordPress AI only when a prompt spec exists):

- `ux_opportunity_review`
- `copy_implement`
- `competitor_research`
- `weather_facet_suggest`

Cloud-only (managed required; Local / explicit WordPress AI modes rejected):

- intelligence workflows (`RWGA_Workflow_Intelligence`)

WordPress AI prompt specs remain limited to `ux_analysis` and `ux_recommend` in this pass.
