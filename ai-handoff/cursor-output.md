# Cursor output — Elementor Atomic V4 read support

**Status:** done

**Date:** 2026-07-11

## Root cause

`RWGA_Elementor_Adapter` treated every node as legacy V3 and read flat `settings.title` / `settings.text` / `settings.header_size`. Atomic V4 widgets (`e-heading`, etc.) store typed `{ $$type, value }` props, plus `styles`, `classes`, and interactions outside flat settings — so content and design-system signals were lost.

## Data shapes inspected

- Legacy V3 fixture: `tests/fixtures/elementor-sample.json` (section/column/heading/button/form/image)
- Atomic V4 fixture: `tests/fixtures/elementor-atomic-v4.json` (`e-flexbox`, `e-heading` html-v3 title, `e-paragraph`, `e-button` link, `e-image`, `e-grid`, unknown `e-custom-widget`)
- Mixed fixture: `tests/fixtures/elementor-mixed-v3-v4.json` (V3 section + Atomic flexbox siblings)

## Files changed

### reactwoo-geo-ai (canonical)

| File | Why |
|------|-----|
| `includes/builders/elementor/class-rwga-elementor-node-version.php` | Per-node V3/V4 detection |
| `includes/builders/elementor/class-rwga-elementor-atomic-prop-resolver.php` | Recursive `$$type`/`value` unwrap |
| `includes/builders/elementor/class-rwga-elementor-style-summary.php` | Bounded breakpoint/state style summary + class scope |
| `includes/builders/elementor/class-rwga-elementor-v3-node-reader.php` | Legacy reader boundary |
| `includes/builders/elementor/class-rwga-elementor-v4-node-reader.php` | Atomic widget reader |
| `includes/builders/class-rwga-elementor-adapter.php` | Version-aware walk; Atomic section roots; media/CTA |
| `includes/builders/class-rwga-builder-loader.php` | Load new elementor helpers |
| `includes/builders/class-rwga-builder-normalize.php` | `e-button` in CTA types |
| `tests/fixtures/elementor-atomic-v4.json` | Atomic fixture |
| `tests/fixtures/elementor-mixed-v3-v4.json` | Mixed fixture |
| `tests/Builders/RWGAElementorAtomicV4Test.php` | Focused Atomic/mixed tests |

### reactwoo-geo-optimise

| File | Why |
|------|-----|
| `merged-geo-ai/includes/builders/**` (synced) | Shipping embed of Geo AI builders |
| `ai-handoff/current-task.md` | Task brief |
| `ai-handoff/cursor-output.md` | This file |

## What was NOT changed

- Elementor action planner / mutation / executor
- Gutenberg adapter
- Geo Core / Optimise goals / GTM
- WordPress AI transport
- `_elementor_data` writes / V3→V4 conversion
- Plugin minimum WordPress version

## Commands run

- `php vendor/phpunit/phpunit/phpunit -c phpunit.xml.dist --filter RWGAElementor` → **OK (14 tests, 55 assertions)**
- `php -l` on new/changed Elementor PHP → OK
- `npm run package:zip` (Optimise) → pending below

## Unsupported Atomic element types (visible as `semantic_type: unknown`)

Any `e-*` widget not in the initial map (heading, paragraph, button, image, svg, divider, video, div-block, flexbox, grid, form, tabs, accordion). Example in fixture: `e-custom-widget` — still emitted with content/unknown_meta, not dropped.

## Recommended next file (write-path / measurement)

**Geo Optimise:** define canonical exposure/goal measurement contract + stable semantic element keys (`data-rwgo-element-key`) before Atomic write executor or GTM provisioning.

## Test checklist

- [x] Existing V3 Elementor extraction tests pass
- [x] V3 heading content + level
- [x] Atomic `e-heading` typed title → plain text
- [x] Atomic heading tag h1–h6
- [x] Atomic button text + link
- [x] Atomic image url/alt
- [x] Class references + local/global scope
- [x] Style summary by breakpoint/state
- [x] Unknown typed values preserved
- [x] Mixed V3/V4 coherent context
- [x] Unsupported Atomic widgets visible
- [x] No document mutation
- [x] `npm run package:zip` (Optimise) → `reactwoo-geo-optimise-0.4.66.zip`
