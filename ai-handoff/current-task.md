# Current task

> Canonical repository: `reactwoo-geo-optimise`

## Problem

Agents need stable semantic targets across Control and Variant B. Physical `goal_id` / `handler_id` pairs differ per Elementor element ID, which complicates measurement contracts and GTM.

## Expected (this pass)

- Stamp `data-rwgo-element-key` on Elementor + Gutenberg goals
- Pipe `element_key` through tracking JS, REST payload, and GTM dataLayer docs
- Document contract in `docs/MEASUREMENT-CONTRACT.md`

## Do not touch

- Atomic Elementor write / mutation
- GTM API provisioning
- Winner auto-promotion
- Replacing existing goal/handler storage matching
