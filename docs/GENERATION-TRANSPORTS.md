# Generation transports (WordPress AI / managed / local)

**Status:** shipped in Geo Optimise 0.4.68+  
**Canonical code:** `merged-geo-ai/includes/services/generation/`

## Modes

| Stored value | Behaviour |
|--------------|-----------|
| `automatic` (default for new installs) | WordPress AI → ReactWoo managed → local deterministic (preflight skip only) |
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

## Migrated workflows (this pass)

- `ux_analysis`
- `ux_recommend`

Other workflows still call `RWGA_Remote_Client` / local stubs directly until a later pass.
