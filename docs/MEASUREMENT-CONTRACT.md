# Geo Optimise measurement contract

**Status:** Phase 5 implemented (keys + exposure + manifest + stamp write + GTM pack + blueprint construction)  
**Owner:** `reactwoo-geo-optimise`

## Purpose

When an agent (or editor) instruments a variant, measurement must use **logical** targets that stay aligned across Control and Variant B — even when Elementor physical element IDs differ. Exposure must be a first-class event so conversion rates have a real denominator.

## Phase 1 — stable element keys

### Attribute

```html
data-rwgo-element-key="hero.primary_cta"
```

Stamped on Elementor widgets and Gutenberg blocks that are Geo Optimise goals.

### Resolution

1. Explicit **Element key** control / `rwgoElementKey` block attribute when set.
2. Otherwise derive from UI goal type prefix + goal label slug, e.g. `cta.primary-hero-cta`.

Helper: `RWGO_Element_Key` (`includes/class-rwgo-element-key.php`).

### Event / GTM

Goal events and `rwgo_goal_fired` dataLayer pushes include:

| Field | Role |
|-------|------|
| `element_key` / `rwgo_element_key` | Logical target (new) |
| `goal_id` / `handler_id` | Existing physical binding (unchanged) |
| `element_fingerprint` | Strict-binding / defined stamp |

Existing goal_id + handler_id matching remains the source of truth for conversion storage. Element keys are additive for cross-variant identity, blueprints, and GTM.

## Phase 2 — exposure + tracking manifest

### Assignment vs exposure vs conversion

| Signal | Meaning | Storage |
|--------|---------|---------|
| Assignment | Cookie / bucket decision | Assignment cookie |
| Served (legacy) | Page view served a variant | WP option `rwgo_experiment_variant_served` |
| **Exposure** | Rendered experience counted once per session+day | `wp_rwgo_events` `event_type=experiment_exposure` |
| Conversion | Goal fired | `wp_rwgo_events` goal rows + `rwgo_goal_fired` |

Helper: `RWGO_Exposure::record()` from `RWGO_Runtime::maybe_record_variant_served()`. Dedupes via `event_instance_id` (`exp_*` hash of experiment+variant+session+UTC day).

### Client dataLayer

On page load (sessionStorage-deduped), `rwgo-tracking.js` pushes `rwgo_experiment_exposure`.

### Tracking manifest

`RWGO_Tracking_Manifest::build()` (schema `1.0`) is attached to each experiment in the front-end config as `trackingManifest`, including `tracked_elements[].elementor_id` when known.

## Phase 3 — Atomic / Elementor write path (stamp from blueprint)

### Writer

`RWGA_Elementor_Document_Writer` patches existing `_elementor_data` nodes:

- Loads / saves Elementor JSON
- `patch_widget_settings` / `patch_many` by element id
- Measurement keys (`rwgo_*`) stored as **plain** strings (compatible with Advanced controls)
- Other Atomic V4 content props can use `{ $$type, value }` wrappers when the node is detected as V4

Does **not** invent full Atomic style systems when only patching — use Phase 5 for construction.

### Stamper

`RWGO_Measurement_Stamper`:

- `apply_tracked_elements` / `apply_manifest` — write keys onto widgets by `elementor_id`
- `sync_keys_source_to_target` — pair Control → Variant B widgets of the same `widgetType` in document order
- Runs automatically on `rwgo_post_duplicate_variant`
- Developer Tools: **Sync Control → Variant B keys**

### Atomic goal support

Supported Elementor widget list includes Atomic types (`e-button`, `e-form`, …). Defined-goal collection and render stamping unwrap typed `{ $$type, value }` settings when present.

## Phase 4 — Tracking preflight + GTM provision pack

### Preflight

`RWGO_Tracking_Preflight::run()` checks Control/Variant pages, goal+handler, element keys, tracking JS, and status.

Shown on Tracking Tools per-test cards.

### Provision pack (offline)

`RWGO_GTM_Provisioner::build_pack()` downloads JSON (`Download GTM pack`) containing:

- Preflight summary + tracking manifest
- Recommended DLV variables
- Triggers for `rwgo_goal_fired` and `rwgo_experiment_exposure`
- GA4 event tag blueprints with parameter maps
- Example dataLayer object

This is **agency offline provisioning** — not a live Google Tag Manager API push (OAuth / container write is a later phase).

## Phase 5 — Blueprint page construction

`RWGA_Elementor_Blueprint_Builder` turns `RWGA_Page_Blueprint` (e.g. `lead_generation_landing()`) into a full Elementor tree:

| Mode | Structure |
|------|-----------|
| `v3` | section → column → classic widgets |
| `atomic` | `e-flexbox` shells → `e-heading` / `e-paragraph` / `e-button` / `e-image` / … |

Interactive CTAs (`primary_cta`, `button`) receive plain `rwgo_*` measurement settings with semantic keys like `hero.primary_cta`.

`RWGO_Blueprint_Page_Writer::create_draft_page()` / `apply_to_post()` persist via `RWGA_Elementor_Document_Writer::write_document()`. Developer Tools: **Create page from blueprint**.

## Not in this phase

- Live GTM Tag Manager API push
- Winner policy statistical gates
- Replacing physical goal/handler mapping with element-key-only matching

## Next

Winner policy (sample size / significance gates) + promotion automation.
