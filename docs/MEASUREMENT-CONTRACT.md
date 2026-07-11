# Geo Optimise measurement contract

**Status:** Phase 1 implemented (semantic element keys)  
**Owner:** `reactwoo-geo-optimise`

## Purpose

When an agent (or editor) instruments a variant, measurement must use **logical** targets that stay aligned across Control and Variant B — even when Elementor physical element IDs differ.

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

### Example measurement fragment (blueprint)

```json
{
  "tracked_elements": [
    {
      "semantic_key": "hero.primary_cta",
      "goal_id": "hero_cta_click"
    }
  ]
}
```

Use the same `semantic_key` on Control and Variant B widgets.

## Not in this phase

- First-class `experiment_exposure` event rows (served counts remain option increments)
- Tracking preflight / GTM API provisioning
- Winner policy statistical gates
- Replacing physical goal/handler mapping with element-key-only matching

## Next

Exposure events + experiment tracking manifest, then Atomic write path that stamps keys from the blueprint.
