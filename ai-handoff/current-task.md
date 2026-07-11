# Current task

> Canonical repository: `reactwoo-geo-optimise`
> Canonical AI implementation location: `reactwoo-geo-optimise/merged-geo-ai/`
> Standalone `reactwoo-geo-ai` is deprecated and must not receive new feature development.

## Problem

Geo AI has now been merged into Geo Optimise, but the embedded AI execution layer still supports only the legacy `local`, `remote`, and `remote_fallback` modes.

Current workflows call `RWGA_Remote_Client` directly, while `RWGA_AI_Usage_Guard` combines product licensing, ReactWoo API authentication, cloud snapshot requirements and managed AI quota into one gate.

This prevents correctly adopting the WordPress 7 AI Client API:

* WordPress-configured AI providers cannot currently execute Optimise workflows.
* BYOK generation would incorrectly depend on ReactWoo platform authentication and managed quota.
* Workflow code owns transport selection instead of relying on one stable routing contract.

## Expected

One transport-neutral generation layer with modes: `automatic`, `wordpress_ai`, `managed`, `local`.

Prove with `ux_analysis` and `ux_recommend` only. Keep managed API as fallback/cloud. Do not change Core, API, or licensing. Do not modify standalone Geo AI.

## Do not touch

* `reactwoo-geo-ai` production code
* Geo Core / reactwoo-api / react-license
* experiment assignment, goals, stats, promotion
* mass `RWGA_*` renames
* Elementor write path / Atomic page creation
* provider API-key storage
