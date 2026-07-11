# Current task

> Canonical repository: `reactwoo-geo-optimise`

## Problem

Measurement contract phase 2: exposure events + experiment tracking manifest (after element keys in 0.4.69).

## Expected (this pass)

- `RWGO_Exposure` rows in `wp_rwgo_events` (`experiment_exposure`), session/day-deduped
- `RWGO_Tracking_Manifest` on front-end experiment config
- Client `rwgo_experiment_exposure` dataLayer (sessionStorage-deduped)
- GTM handoff documents exposure event
- Ship **0.4.71**

## Do not touch

- Standalone `reactwoo-geo-ai`
- GTM API provisioning / Atomic write / winner policy (next phases)
- License / Core / API repos
- Unrelated UX reviewer workspace WIP
