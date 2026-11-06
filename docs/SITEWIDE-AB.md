# Site-wide A/B

Geo Optimise already assigns one arm per visitor and can render a published variant page at the original URL. This note is the setup for that existing test, including Elementor header and footer templates.

No page IDs are built into the plugin. The IDs below are the fields you fill in for a site.

## What was already in place

- Site-wide option `rwgo_sitewide_experiment`, cookie `rwgo_site`, and a page map of original ID to variant page ID.
- Stable assignment from the visitor id and the control weight. The same visitor keeps the same arm for the cookie lifetime.
- A published variant page is rendered at the original URL. A direct visit to the variant URL redirects once to the original.
- Bots and logged-in editors stay on control.
- Per-page tests still redirect to a published variant URL. Those tests are separate from the site-wide header and footer fields.

## What was missing

- Elementor header and footer are their own templates. Display conditions on those templates are not the visitor assignment, so a published variant header could show for everyone, or for nobody, while the page body stayed on the original.
- The old `?rwgo_arm=variant` link rewrote the assignment cookie and was counted in exposure.
- An unpublished variant page still left the visitor in the variant arm for reporting, even though the original body was what they saw.
- A paused test left a published variant URL in place. A map where a page was both an original and a variant could redirect in a circle.
- URLs outside the page map could be stored by a full-page cache. A header that changes per visitor would then leak from one cache entry.

## Configure the test

1. In wp-admin open **Site-wide test** (`admin.php?page=rwgo-sitewide`). The screen title is “Site-wide design test”.
2. Set status to **Paused** while you prepare the pages.
3. Page map, one pair per line: `original page ID, variant page ID`.
4. Control weight is the percent of visitors who stay on control. `50` is an even split.
5. Elementor template IDs, from the Elementor library (post type `elementor_library`):
   - Control header ID and control footer ID
   - Variant B header ID and variant B footer ID
   - `0` means “do not override this location”, except a variant template listed here is hidden from the control arm.
6. Save. Saving does **not** publish drafts.

Publish each variant page and each header or footer when the content is ready. Until a variant page is published, visitors keep the original page body. A published variant header or footer can still change with the arm.

Remove “entire site” display conditions from the variant header and footer after the IDs are saved. The test selects them. A condition that matches every URL fights the control arm.

Cart, checkout, and account are never given variant chrome. Shop and other system pages stay on the normal templates while “Exclude the shop, cart, checkout…” is checked.

## Trigger variant B

Visitors do not need a query argument. With the test **Active**:

- A new visitor is bucketed from their id and the control weight.
- The arm is stored in `rwgo_site` (HTTP-only, Lax, 90 days unless you change the lifetime).
- Mapped pages with a **published** variant page render that page at the original URL.
- The header and footer IDs for that arm are used when Elementor Pro 3.9 or newer prints the header and footer locations.

To force one arm without contaminating the numbers, use the **Preview control** or **Preview variant B** button on the site-wide screen. The link is valid for your user only. It does not write `rwgo_site` and it does not record exposure or conversions. A copied link without the nonce does nothing. The old `?rwgo_arm=` parameter does nothing unless it is sent with that same nonce.

## Measurement

- Exposure is one row per experiment, delivered arm, and session per UTC day.
- Conversions use that delivered arm. If no variant page is published and no variant header or footer is published, a visitor who was bucketed into variant B is counted as control, because that is what they were shown.
- With the WordPress Consent API active, statistics consent is required before exposure, goals, or the tracking script run. The assignment cookie is still set so the arm stays stable. A refused consent is not replaced with an IP and user-agent hash.
- Without the Consent API, measurement stays on, which matches the previous plugin. A site can turn it off with the `rwgo_measurement_allowed` filter.

## Cache and CDN

Mapped URLs, and every public URL when a header or footer ID is set, send `Cache-Control: private, no-store` and a single `Vary: Cookie`, and define `DONOTCACHEPAGE`. Bots get the same bypass so a control response is not stored and replayed.

When LiteSpeed Cache is the only page cache, a returning visitor with a stable cookie can be stored separately per cookie. A new cookie, a preview, a bot, or any other page-cache plugin still bypasses the cache.

Saving the test purges the original pages, the variant pages, and the header and footer templates through LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, or the Cloudflare plugin, when that plugin is present.

A CDN that ignores `Cache-Control` and cookies can still serve one visitor’s HTML to the next. That has to be fixed in the CDN, not by a second assignment engine.

## Canonical and stopped tests

While variant B is rendered at the original URL, the canonical URL and `og:url` stay on the original. Variant URLs are omitted from the core sitemap and marked noindex when requested directly.

Paused and ended tests do not assign an arm and do not swap the body. A direct hit on a variant URL redirects once to the original. If a page is both an original and a variant, that pair is ignored and the screen warns, so the redirect cannot loop.

## Staging rollout

1. Deploy this build to staging. Do not tag a release and do not publish customer drafts as part of the deploy.
2. Confirm Geo Core is active and Geo Optimise is the version that contains this change. Confirm Elementor Pro is 3.9 or newer if header and footer IDs are used.
3. Keep the site-wide test **Paused**.
4. Enter the page map and the four template IDs. Save.
5. Read the warnings. Publish only the variant pages and templates you intend visitors to see.
6. Open **Preview variant B** while logged in as a manager. Confirm the variant body only if that page is published, and confirm the variant header and footer. Confirm the admin bar says the preview is not tracked.
7. Open **Preview control**. Confirm the control header and footer, and that the variant templates do not appear.
8. In a private window, with the test still paused, load the original URL and a variant URL. You should see the original page. The variant URL should redirect once to the original.
9. Set the test **Active** on staging only.
10. In a private window, reload the original URL. Response headers should include `X-RWGO-Arm`, `Cache-Control: private, no-store`, and `Vary: Cookie`. A second reload should keep the same arm.
11. Load cart, checkout, and account. They should keep the normal header and footer.
12. Stop the test (Paused). Confirm a new private window no longer receives a variant body, and that the variant URL still redirects once.

## Rollback

1. Set the site-wide test to **Paused** or **Ended** and save. Visitors stay on the original body. Variant URLs redirect home. No pages are deleted.
2. If a cached page still shows the wrong arm, use **Clear Optimise cache** and purge the CDN for the original URLs and the template URLs.
3. To remove the header and footer binding without ending the page test, set all four template IDs to `0` and save.
4. Code rollback is a revert of this change. Stored `rwgo_sitewide_experiment` keeps `theme_context`. Older plugin code ignores that key. The page map is unchanged.

## Admin fields

The site-wide screen stays on the WordPress core form table and the core warning notice. No new color, type, or spacing tokens were added. Figma was not updated.

| Field | Control | Empty value |
| --- | --- | --- |
| Control header ID | number | `0`, do not override |
| Control footer ID | number | `0`, do not override |
| Variant B header ID | number | `0`, do not override |
| Variant B footer ID | number | `0`, do not override |
| Preview control / Preview variant B | links on the same screen | nonce query, current user only |

Warnings use the core `notice notice-warning` pattern. They list unpublished variant pages, unpublished templates, a missing control template, and page-map pairs that would loop.

## Limits

- Header and footer selection needs Elementor Pro 3.9 or newer. Older Elementor does not pass the location into the template filter, so display conditions still apply. The page-body swap does not need that filter.
- Per-page redirect tests do not use these header and footer IDs. They send the visitor to the variant URL. Use the site-wide test when the header and footer must follow one arm across pages.
- This repository’s tests decide routing, cache headers, preview, consent, and the Elementor template id. They do not boot WordPress, Elementor, or a CDN.
- A full-page CDN that ignores `Cache-Control` and `Vary` is outside the plugin.
- Without the WordPress Consent API, exposure stays enabled, as it was before this change.
