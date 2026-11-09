# Product template map

The site-wide arm is the `rwgo_site` cookie. Page bodies stay on the page map. Header and footer stay in the four template fields. A Woo product is not copied. Elementor Theme Builder selects one single template for that product from display conditions. This plugin then prints the mapped variant template only while the variant arm is being served. The queried post remains the product.

## How Elementor chooses a template

Elementor Pro does this before Optimise runs:

1. Rules are stored on the template as `_elementor_conditions` and copied into the option `elementor_pro_theme_builder_conditions`, keyed by location (`single`, `header`, `footer`) and template ID.
2. `Conditions_Manager::get_documents_for_location()` returns the templates whose rules match the current request. A published template with no rules is not in that list.
3. `Locations_Manager` prints the first matching document. Two templates with the same rule can both match. The first one wins. `enqueue_styles()` also queues `elementor-post-{id}` for every match, not only the one that is printed.
4. Pro 3.9 and newer then passes that ID through `elementor/theme/get_location_templates/template_id`. That is the filter Optimise uses.

Copying the source conditions onto a published B template puts B in that list. Elementor can select B for every matching product when the experiment is paused, when the arm is control, on cart, checkout, and account, and when this plugin is inactive. The filter cannot run if the plugin is off.

## Supported B template

Create a new Theme Builder single-product template. Do not edit the source template and do not duplicate the Woo product.

1. Design B.
2. Publish the template in WordPress so its document CSS exists.
3. Display conditions: none. Do not add an Include row. The template stays unassigned. It must not appear under Theme Builder as a template that displays on products.
4. Leave the source template’s conditions exactly as they are. That is what makes Elementor select the source.
5. On Geo Optimise → Site-wide test, add one line per pair:

```text
single,SOURCE_TEMPLATE_ID,VARIANT_TEMPLATE_ID
```

`product` is stored as `single`. Saving a variant template that has any `_elementor_conditions` rule fails with `rwgo_variant_template_conditioned`. A source template with no rules is saved and warned as `rwgo_source_template_unassigned`, because Elementor will not select it and the swap will not run.

These source templates already have the conditions to keep. The B column is a new unassigned template.

| Products | Source, keep its conditions | Map line |
| --- | --- | --- |
| Core, tag 51, product 2388 | 2999 | `single,2999,B` |
| Pro, tag 52, product 2294 | 3003 | `single,3003,B` |
| Commerce, tag 53, product 2893 | 3044 | `single,3044,B` |
| Maintenance 3654 | 3697 | `single,3697,B` |
| Maintenance 3657 | 3698 | `single,3698,B` |
| Maintenance 3660 | 3699 | `single,3699,B` |
| Fallback include/product for Atomic 3527, Atomic Pro 3494, Optimise 2891, Decision Cloud 3166, and support SKUs | 3593 | `single,3593,B` |

While this plugin is active, a variant template that does have conditions is still sent back to its source on the control arm, on a paused or ended test, when no delivery plan is built, and on cart, checkout, account, and other excluded pages. That does not protect the site when the plugin is inactive. Unassigned B templates are the configuration that cannot leak.

Header and footer are a separate field. Do not give a variant header an Entire Site condition either. Cart, checkout, account, and wp-admin keep the source templates.

## Styles

The canonical page, or the product post, keeps `elementor-post-{that id}`. A header-only swap does not dequeue it. A product-body swap dequeues `elementor-post-{source template}` only when that template was the one replaced, and it does not dequeue the product post or handles such as `woocommerce-general` and `elementor-frontend`. The variant document is queued as `elementor-post-{variant id}`.

A desktop menu that stacks, or a mobile menu button that shows above the desktop menu, was seen in a browser on the B header. This repository does not include Elementor Pro, so that layout was not reproduced here. It can be the header widget’s own CSS. Fix that in the B header on the site. This plugin does not change those widget styles.

## What was not executed

Elementor Pro is not installed in this workspace. `get_documents_for_location()` was not called. The candidate order above is the contract read from Elementor Pro’s locations manager: only templates with rules are candidates, and the first candidate is the one printed before the filter. The regression tests model that contract. They do not boot Pro’s renderer.
