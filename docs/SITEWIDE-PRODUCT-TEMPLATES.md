# Product template map

The site-wide arm is still the `rwgo_site` cookie. Page bodies stay on the page map. Header and footer stay in the four template fields. A Woo product is not a page, and it is not copied. Elementor Theme Builder already chooses a single template for that product. This map replaces that chosen template with the variant template when the visitor’s arm is variant.

The queried post remains the product, so dynamic price, stock, and add to cart stay on it. Cart, checkout, my-account, and wp-admin are unchanged.

## What the webmaster creates

For each source template below, create a **new** Elementor Theme Builder template. Do not edit the source template and do not duplicate the Woo product.

1. Templates → Theme Builder → Add New → Single Product (or duplicate the source template into a new template).
2. Design variant B.
3. Display conditions: copy the source template’s conditions. The engine swaps the template Elementor’s conditions already selected. It does not publish the new template.
4. Publish the B template in Elementor when the design is ready. Until then the source template keeps printing and the site-wide screen warns. Saving the map does not publish it.
5. On Geo Optimise → Site-wide test, add one line per pair:

```text
single,SOURCE_TEMPLATE_ID,VARIANT_TEMPLATE_ID
```

`product` is accepted and stored as `single`. Leave header and footer in their own fields.

These are the source templates to pair. The B column is empty until the new templates exist. The IDs are the templates to duplicate, not values the plugin hardcodes.

| Products | Source template | Condition to copy onto B | B template |
| --- | --- | --- | --- |
| Core, tag 51, product 2388 | 2999 | Same conditions as 2999 | Create, then map `single,2999,B` |
| Pro, tag 52, product 2294 | 3003 | Same conditions as 3003 | Create, then map `single,3003,B` |
| Commerce, tag 53, product 2893 | 3044 | Same conditions as 3044 | Create, then map `single,3044,B` |
| Maintenance 3654 | 3697 | Same conditions as 3697 | Create, then map `single,3697,B` |
| Maintenance 3657 | 3698 | Same conditions as 3698 | Create, then map `single,3698,B` |
| Maintenance 3660 | 3699 | Same conditions as 3699 | Create, then map `single,3699,B` |
| Fallback for Atomic 3527, Atomic Pro 3494, Optimise 2891, Decision Cloud 3166, and support SKUs | 3593 | Same include/product conditions as 3593 | Create, then map `single,3593,B` |

A published non-empty B template of type product or single is what makes that row ready. A header template in this map is kept and warned, and it does not count as ready. Elementor Pro theme locations have to be active before any of these rows print. Without Pro, the IDs are saved and the product body is unchanged.

The same arm follows the visitor from a page to a product. The page map does not need the product IDs.

## Preview

Use **Preview variant B** on the Site-wide screen while logged in to that browser. The nonce belongs to that login session.

A preview URL created through the connector uses the connector’s session. In the browser the nonce does not match. The page is not changed. A manager sees a notice: the link is from another login session. That notice does not show draft content. There is no shared nonce.

`preview=true` that WordPress accepts with `is_preview()` still opens. A `mosmcp_preview` link still opens when its token matches the stored hash for that post and has not expired. Any other token does not reveal the draft, and a paused test still sends a mapped variant URL back to the original.

## Styles

The printed template’s `elementor-post-{id}` stylesheet is queued before the head and, if the location is printed after the head, printed with that template. The stylesheet of the template that was replaced is dequeued. Button colors are not edited here. If a B header still looks wrong after this build, the remaining difference is in the Elementor document, which is edited on staging.
