# Geo Optimise measurement contract

**Status:** Phase 2 implemented (element keys + exposure + tracking manifest)  
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

On page load (sessionStorage-deduped), `rwgo-tracking.js` pushes:

```js
{
  event: "rwgo_experiment_exposure",
  rwgo_test_name: "...",
  rwgo_experiment_key: "...",
  rwgo_variant_id: "var_b",
  rwgo_variant_label: "Variant B",
  rwgo_page_context_id: 123,
  rwgo_builder: "elementor"
}
```

Server owns DB insert; the client push is for GTM/GA4 only.

### Tracking manifest

`RWGO_Tracking_Manifest::build()` (schema `1.0`) is attached to each experiment in the front-end config as `trackingManifest`:

```json
{
  "schema_version": "1.0",
  "experiment_key": "...",
  "hypothesis": "",
  "primary_goal": { "id": "...", "type": "click", "semantic_key": "hero.primary_cta" },
  "secondary_goals": [],
  "tracked_elements": [
    {
      "semantic_key": "hero.primary_cta",
      "goal_id": "hero_cta_click",
      "handler_id": "..."
    }
  ],
  "guardrails": ["bounce_rate", "form_error_rate", "page_performance"]
}
```

Use the same `semantic_key` on Control and Variant B widgets.

## Not in this phase

- Tracking preflight / GTM API provisioning
- Winner policy statistical gates
- Replacing physical goal/handler mapping with element-key-only matching
- Atomic V4 write that stamps keys from the blueprint

## Next

Atomic write path that stamps keys from the blueprint, then GTM provisioning + winner policy.
