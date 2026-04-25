# Changelog

## 1.0.1 - Unreleased

- Reclassify MDS ImageGrid as a free connector for the paid ImageGrid service.
- Split browser app links from the configurable server API URL.
- Add MDS3 Settings, admin menu, and Edit Grid tab hooks.
- Keep ImageGrid credentials in extension-owned options while exposing them to MDS3 core through filters.
- Add hosted ImageGrid connect-token return handling, site-scoped credential storage, and disconnect controls.
- Add render preflight before job submission.
- Submit current ImageGrid `grid_render` jobs and tolerate local API deployments without the optional preflight endpoint.

## 1.0.0

- Initial ImageGrid extension for Million Dollar Script 3.
- Adds ImageGrid account/API-key connection settings.
- Adds manual and optional automatic remote grid render job submission.
- Proxies authenticated ImageGrid tiles through WordPress for ready tilesets.
- Integrates with MDS3 grid generation and tile delivery filters.
