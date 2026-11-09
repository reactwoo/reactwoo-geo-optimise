# Generation transports (WordPress AI / hosted / local)

**Status:** Geo Optimise 0.5.9  
**Canonical code:** `merged-geo-ai/includes/services/generation/` and `includes/class-rwgo-decision-cloud.php`

AI generation uses the site's WordPress AI provider (bring your own key). ReactWoo hosted AI comes with Decision Cloud.

## Modes

| Stored value | Behaviour |
|--------------|-----------|
| `automatic` (default) | WordPress AI, then local deterministic. Hosted AI is not in this chain. |
| `wordpress_ai` | WordPress AI provider only. Does not fall through to hosted AI. |
| `local` | Deterministic workflow callback only. |
| `managed` | ReactWoo hosted AI (`/api/v5/geo-optimise/workflow`) only when the cached licence token is Decision Cloud Growth or Scale and `product_slugs` includes Geo Optimise. Otherwise this saved value behaves as `automatic`. The stored value is kept. |
| `remote` (legacy) | Same as `managed`. |
| `remote_fallback` (legacy) | WordPress AI, then local. Hosted AI is not selected. Without a Decision Cloud entitlement this behaves as `automatic`. The stored value is kept. |

Nothing falls through from WordPress AI / BYOK to hosted AI.

## Decision Cloud detection

Hosted AI reads the JWT already cached by licence login (`rwgo_rw_jwt_cache` or `rwga_rw_jwt_cache`). It does not call the API to discover the plan.

The token must have:

- package `reactwoo-decision-cloud`
- plan `growth` or `scale` (`plan_code`, `plan_key`, `plan`, or `type_key`)
- `product_slugs` / `productSlugs` listing `reactwoo-geo-optimise` (or the legacy `reactwoo-geo-ai` alias)
- a still-valid `exp` when that claim is present, and a cache expiry more than two minutes ahead

A Geo Optimise-only key, Decision Cloud Starter, or a Growth/Scale token that does not list Geo Optimise does not enable hosted AI. With no cached token, hosted AI stays off. Licence login (`/api/v5/auth/login`) and the package update check are unchanged.

Without that entitlement the managed transport makes no HTTP call to ReactWoo AI routes (`/ai/`, `/geo-ai/`, `/geo-optimise/`), including `GET /api/v5/ai/assistant/usage`.

## Boundaries

- Product licence + WP capability: `RWGA_Workflow_Base`
- Transport selection: `RWGA_Generation_Router`
- Effective mode when a saved hosted choice has no entitlement: `RWGA_Engine::get_effective_mode()`
- WordPress AI: public `wp_supports_ai()` / `wp_ai_client_prompt()` only — no provider keys in Optimise
- Hosted: Decision Cloud entitlement, then platform JWT + managed allowance (`RWGA_AI_Usage_Guard::can_run_managed_generation`)
- WordPress AI / BYOK does not consume the ReactWoo managed AI allowance

## Migrated workflows

Bounded generation (WordPress AI when a prompt spec exists, otherwise local in automatic mode):

- `ux_analysis`
- `ux_recommend`

Routed through the same chain (WordPress AI only when a prompt spec exists):

- `ux_opportunity_review`
- `copy_implement`
- `competitor_research`
- `weather_facet_suggest`

Cloud-only. These run on the hosted transport when Decision Cloud allows it. Without that entitlement the admin screens show **Available with ReactWoo Decision Cloud** and the action returns that message instead of calling the API:

- intelligence workflows (`RWGA_Workflow_Intelligence`)
- cloud snapshot sync (`RWGA_AI_Usage_Guard::can_sync_snapshot`)

WordPress AI prompt specs remain limited to `ux_analysis` and `ux_recommend`.
