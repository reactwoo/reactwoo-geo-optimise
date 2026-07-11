# Current task

> Canonical repository: `reactwoo-geo-optimise`

## Problem

Remaining Geo AI workflows still called `RWGA_Remote_Client::dispatch` directly after the first transport-router pass (ux_analysis / ux_recommend only).

## Expected (this pass)

- Route competitor research, copy implement, opportunity review, intelligence, and weather facet suggest through `RWGA_Generation_Router`
- Keep managed-only behaviour for intelligence
- Leave WordPress AI prompt specs limited to ux_analysis / ux_recommend
- Ship **0.4.70**

## Do not touch

- Standalone `reactwoo-geo-ai`
- Expanding WP AI prompt registry beyond UX workflows
- License / Core / API repos
