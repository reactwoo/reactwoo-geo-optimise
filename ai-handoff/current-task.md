# Current task — Elementor Atomic V4 read support

**Phase:** Geo AI builder context — read-only Atomic Editor V4 support

## Problem

`RWGA_Elementor_Adapter` assumes legacy V3 widget types and flat settings. Elementor 4 Atomic documents use `e-heading`, typed `$$type`/`value` props, styles/classes outside flat settings. Mixed V3/V4 documents must be supported per element.

## Expected

Read-only normalized context from V3, V4, and mixed documents. No mutation/conversion.

## Acceptance

See planner brief: V3 regression, Atomic heading/button/image extraction, class/style summaries, mixed docs, fixtures/tests, package zip, cursor-output update.

## Do not touch

Action planner/executor, Gutenberg, Geo Core, Optimise goals, WP AI transport, MCP internals, `_elementor_data` writes.
