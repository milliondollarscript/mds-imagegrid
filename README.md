# MDS ImageGrid

Free Million Dollar Script connector for the paid hosted ImageGrid service.

## Features

- Connects MDS to a separately subscribed ImageGrid workspace with an API key.
- Submits MDS grid-composition render jobs for zoomable grids.
- Stores remote job and tileset state per grid without changing existing grid records.
- Proxies authenticated remote tiles through WordPress when a grid has a ready ImageGrid tileset.
- Keeps local MDS tile generation as the default fallback.

## Local Development

The server API URL defaults to `http://host.docker.internal:18082` when WordPress reports a `local` or `development` environment. Production environments default to `https://api.imagegrid.dev`.

Browser account and API-key links always point to `https://imagegrid.dev`.

Override the service URL with the `MDS_IMAGEGRID_API_URL` constant or the `mds_imagegrid_default_api_url` filter.

## Setup

1. Install and activate Million Dollar Script 3.0+.
2. Install and activate this extension.
3. Open `Million Dollar Script -> ImageGrid`.
4. Enter the ImageGrid API URL and API key.
5. Test the connection, then render a grid.

Package with:

```bash
./scripts/package-extension.sh free/mds-imagegrid
```

## Release Notes

This extension is prepared for manual testing and review. Do not publish it until the MDS3 release candidate has been smoke tested against the current ImageGrid API and extension-server-go catalog flow.
