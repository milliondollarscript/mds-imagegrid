# Million Dollar Script - ImageGrid Rendering

Free Million Dollar Script connector for the paid hosted ImageGrid service.

## Features

- Connects Million Dollar Script to a separately subscribed ImageGrid account with an API key.
- Submits ImageGrid `grid_render` jobs for zoomable grids.
- Uses the current ImageGrid standard Deep Zoom level geometry for correctly scaled edge tiles on large grids.
- Stores remote job and tileset state per grid without changing existing grid records.
- Loads signed, versioned public tile templates directly from ImageGrid when the connected account has CDN Delivery access.
- Adds ImageGrid controls to the Million Dollar Script Settings screen, dashboard menu, and grid editor only when the extension is active.
- Supports hosted ImageGrid account connection when the app returns a connect token, plus manual API keys for local development.
- Keeps local Million Dollar Script tile generation as the default fallback.

## Local Development

The server API URL defaults to `http://host.docker.internal:18082` when WordPress reports a `local` or `development` environment. Production environments default to `https://api.imagegrid.dev`.

Browser account, API-key, and connect links point to `http://localhost:18082` in local or development environments and `https://imagegrid.dev` in production. Override the browser app URL with `MDS_IMAGEGRID_APP_URL` or the `mds_imagegrid_app_url` filter.

Override the service URL with the `MDS_IMAGEGRID_API_URL` constant or the `mds_imagegrid_default_api_url` filter.

The connector records a local submission estimate and relies on the current `POST /v1/jobs` path for atomic plan, quota, request-validation, and worker-capacity enforcement. It does not call the removed `/v1/renders/preflight` route.

`tests/live-imagegrid-fixture.php` provides an opt-in WordPress-to-ImageGrid check. Pass `IMAGEGRID_API_URL` and `IMAGEGRID_API_KEY` to the WP-CLI process; the fixture does not save either value in WordPress.

## Setup

1. Install and activate Million Dollar Script 3.0+.
2. Install and activate this extension.
3. Open `Million Dollar Script -> Dashboard -> ImageGrid`.
4. Connect ImageGrid from the settings page, or enter the ImageGrid API URL and API key manually.
5. Test the connection, then render a grid from `Million Dollar Script -> Dashboard -> ImageGrid` or `Grids -> Edit Grid -> ImageGrid`.

## Production Notes

- Requires PHP 8.1+ and the PHP cURL extension for placement asset uploads.
- ImageGrid API keys are stored server-side in extension-owned WordPress options and are not exposed to browsers.
- Ready ImageGrid jobs expose signed direct tile templates only when `GET /v1/account` reports `features.mds3_cdn=true` or an equivalent `cdn_delivery` entitlement, so sites without CDN Delivery keep using local public tiles.
- Account refreshes store only the supported account, key, plan, quota, usage, feature, entitlement, and permission fields returned by `GET /v1/account`.
- If ImageGrid is disabled, unavailable, or outside the configured quota, Million Dollar Script keeps using local rendering.
- Existing MDS 3.0 core ImageGrid settings are migrated into extension-owned options the first time the extension loads.

## Release Notes

Version 1.0.8 is the current connector for Million Dollar Script 3.0. It supports hosted account connection, compatibility-safe automatic-rendering defaults, tabbed administration, manual API credentials, remote render submission, deferred placement asset patching, entitlement-gated direct CDN tile delivery, standard Deep Zoom geometry, local rendering fallback, redirect-safe authenticated transport, and actionable paid-access status handling. Render failures create persistent, dismissible WordPress notices; an administrator can also opt in to one deduplicated `wp_mail()` notification per unresolved failure.
