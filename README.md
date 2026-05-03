# Million Dollar Script ImageGrid

Free Million Dollar Script connector for the paid hosted ImageGrid service.

## Features

- Connects Million Dollar Script to a separately subscribed ImageGrid workspace with an API key.
- Submits ImageGrid `grid_render` jobs for zoomable grids.
- Stores remote job and tileset state per grid without changing existing grid records.
- Proxies authenticated remote tiles through WordPress when a grid has a ready ImageGrid tileset.
- Adds ImageGrid controls to the Million Dollar Script Settings screen, dashboard menu, and grid editor only when the extension is active.
- Supports hosted ImageGrid account connection when the app returns a connect token, plus manual API keys for local development.
- Keeps local Million Dollar Script tile generation as the default fallback.

## Local Development

The server API URL defaults to `http://host.docker.internal:18082` when WordPress reports a `local` or `development` environment. Production environments default to `https://api.imagegrid.dev`.

Browser account, API-key, and connect links always point to `https://imagegrid.dev`.

Override the service URL with the `MDS_IMAGEGRID_API_URL` constant or the `mds_imagegrid_default_api_url` filter.

The connector is compatible with the local ImageGrid API even when `/v1/renders/preflight` is unavailable; in that case it records preflight as skipped and relies on the `POST /v1/jobs` quota and validation path.

## Setup

1. Install and activate Million Dollar Script 3.0+.
2. Install and activate this extension.
3. Open `Million Dollar Script -> Dashboard -> ImageGrid`.
4. Connect ImageGrid from the settings page, or enter the ImageGrid API URL and API key manually.
5. Test the connection, then render a grid from `Million Dollar Script -> Dashboard -> ImageGrid` or `Grids -> Edit Grid -> ImageGrid`.

Package with:

```bash
./scripts/package-extension.sh free/mds-imagegrid
```

## Release Notes

This extension is prepared for manual testing and review. Do not publish it until the Million Dollar Script release candidate has been smoke tested against the current ImageGrid API and extension-server-go catalog flow.
