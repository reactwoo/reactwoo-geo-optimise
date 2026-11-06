# Plugin updates

## WordPress.org Geo Core does not offer Optimise updates

The current WordPress.org package, `reactwoo-geocore` 1.9.26, sets `RWGC_DISTRIBUTION` to `wporg`. That package does not include the ReactWoo update client. It loads `class-rwgc-satellite-updater-stub.php`, and that stub's `register()` ignores Optimise. The Geo Core settings screen does not render a plugin-update control in that package. There is no hidden cache button to use.

Optimise 0.5.3 and 0.5.4 only register with that stub. They never call `https://api.reactwoo.com/api/v5/updates/check` for slug `reactwoo-geo-optimise`. A newer Optimise release therefore does not appear in `update_plugins`, and the connector's update tools report `update_available: false`.

Support Connector is different. It checks slug `reactwoo-support-connector` itself and does not send a license token. That slug is allowed to answer without one. `reactwoo-geo-optimise` is not. A check with no token returns HTTP 401 and `Missing license token`, and it does not return a zip.

Optimise 0.5.5 performs that check itself only when Geo Core's real updater filter is absent. It sends the saved Optimise license token and does not put the token in the download URL stored for WordPress. When the ReactWoo-channel Geo Core updater is already registered, 0.5.5 does not add a second check.

## Bootstrap

0.5.3 and 0.5.4 cannot download 0.5.5 through the connector. The connector also cannot upload a local zip. Install 0.5.5 from **Plugins → Add New → Upload Plugin** using the zip published with tag `v0.5.5`. Confirm the file's SHA-256 against the publish workflow before uploading. Do not activate a site-wide experiment or publish draft pages as part of that install.

After 0.5.5 is installed on a WordPress.org Geo Core site, later Optimise releases can appear through the normal plugin update check, because 0.5.5 sends the license token from wp-admin. The connector's refresh still only calls `wp_update_plugins()` and does not upload a zip.
