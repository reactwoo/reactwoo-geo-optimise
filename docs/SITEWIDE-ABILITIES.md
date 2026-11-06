# Site-wide abilities

Geo Optimise registers these WordPress abilities when `wp_register_ability()` exists (WordPress 6.9 or newer). WordPress.com and the miniOrange MCP server discover them because each one sets `meta.public`, `meta.show_in_rest`, and `meta.mcp.public`. If the Abilities API is not loaded, the plugin does nothing and the admin screen still works.

Category: `geo-optimise`.

Namespace: `reactwoo-geo-optimise`. There is no ability that accepts an option name, SQL, a file path, or a PHP serialized blob.

Permission for every ability: the user must pass `RWGO_Admin::can_manage()` (same gate as the site-wide screen). If `RWGO_Platform_Client` is loaded, a license key must be configured, or the Geo AI workflow license must allow workflows. The response never includes the license key. A missing license API does not block the call, matching a screen that has no license client loaded.

None of these abilities publish a page or an Elementor template. Empty draft templates must be designed in Elementor by a person. This plugin does not copy header or footer content. That remains an editor task, not an ability.

## reactwoo-geo-optimise/sitewide-read

Read only. No input.

Output includes `status`, `revision`, `experiment_key`, `control_weight_percent`, `cookie_days`, `page_map`, `theme_context`, `warnings`, `errors`, `ready`, `licensed`, `elementor_pro_locations`, `saved: false`, `published: false`.

## reactwoo-geo-optimise/sitewide-validate

Read only. Same input as save. Returns errors and warnings and does not write.

## reactwoo-geo-optimise/sitewide-save

Mutation. Does not accept `status`. A new test is stored as `paused`. An existing test keeps its current status. `expected_revision` must match the last read.

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["expected_revision", "page_map"],
  "properties": {
    "expected_revision": { "type": "integer", "minimum": 0 },
    "experiment_key": { "type": "string", "maxLength": 80 },
    "control_weight_percent": { "type": "integer", "minimum": 0, "maximum": 100 },
    "cookie_days": { "type": "integer", "minimum": 1, "maximum": 365 },
    "page_map": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["source_page_id", "variant_page_id"],
        "properties": {
          "source_page_id": { "type": "integer", "minimum": 1 },
          "variant_page_id": { "type": "integer", "minimum": 1 }
        }
      }
    },
    "theme_context": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "control": { "type": "object", "properties": { "header": { "type": "integer", "minimum": 0 }, "footer": { "type": "integer", "minimum": 0 } } },
        "variant": { "type": "object", "properties": { "header": { "type": "integer", "minimum": 0 }, "footer": { "type": "integer", "minimum": 0 } } }
      }
    },
    "goal_page_ids": { "type": "array", "items": { "type": "integer", "minimum": 1 } },
    "track_purchase": { "type": "boolean" }
  }
}
```

Blocking errors: `rwgo_malformed_id`, `rwgo_duplicate_source`, `rwgo_redirect_loop`, `rwgo_unknown_post`, `rwgo_wrong_post_type`, `rwgo_weight_range`, `rwgo_cookie_days`, `rwgo_revision_required`, `rwgo_revision_conflict`.

Warnings, saved anyway while paused: `rwgo_variant_unpublished`, `rwgo_theme_template_unpublished`, `rwgo_theme_template_empty`, `rwgo_control_template_missing`.

Page IDs must be pages on this site. Template IDs must be `elementor_library` posts. `0` means that location is not overridden.

## reactwoo-geo-optimise/sitewide-set-status

The only ability that changes status. `confirm` must be true.

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["expected_revision", "status", "confirm"],
  "properties": {
    "expected_revision": { "type": "integer", "minimum": 0 },
    "status": { "type": "string", "enum": ["active", "paused", "ended"] },
    "confirm": { "type": "boolean" }
  }
}
```

`active` returns `rwgo_not_ready` unless a mapped variant page is published, or Elementor Pro theme locations are loaded and a variant header or footer is a published non-empty Elementor template. `paused` and `ended` do not require that.

## reactwoo-geo-optimise/sitewide-preview-link

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["arm"],
  "properties": {
    "arm": { "type": "string", "enum": ["control", "variant"] },
    "page_id": { "type": "integer", "minimum": 0 }
  }
}
```

Output: `ok`, `arm`, `url`, `measures: false`, `assigns: false`. The URL contains `rwgo_preview` and `rwgo_preview_nonce` for the current user. It does not write `rwgo_site` and it does not record exposure.

## Errors

Permission failures are `rwgo_forbidden` and `rwgo_unlicensed` (403). Validation failures are 400 and include an `errors` list of `{code, post_id, detail, message}`. `detail` is a number or a short label, never a key or cookie.
