# Changelog

## 1.0.10 - 2026-10-21
- Turning hosted rendering on without a service key now says what is missing.
- Tested with WordPress 7.1.3 and WooCommerce 11.2.1, and the plugin states the exact versions it was tested with, so WordPress and WooCommerce no longer report it as untested on a current site.


## 1.0.9 - 2026-08-11

- Added opt-in uninstall cleanup through the core extension policy for extension-owned ImageGrid settings and transients.
- Preserve the current ImageGrid plan, effective limits, quotas, usage periods, permissions, and CDN entitlement data when refreshing a hosted connection.
- Map local safeguards only to equivalent published ImageGrid limits and leave unmatched safeguards unchanged during a plan reset.
- Use the local ImageGrid account app during local development while keeping `imagegrid.dev` and `api.imagegrid.dev` as the production targets.
- Correct the Million Dollar Script render-estimate facade call and add guidance for current worker-capacity and quota-policy errors.
- Add idempotent, per-grid render submission coordination so ambiguous transport retries recover the original job and concurrent requests cannot create duplicates.
- Show detailed pre-submission dimensions, tile geometry, source totals, working-size estimates, and service-compatible credit estimates.
- Separate per-job, concurrent-capacity, periodic-allowance, storage, and delivery limits in the account view.
- Keep grids with browser-composited background images on local rendering until the remote contract supports equivalent composition, and invalidate stale remote output when presentation changes.
- Add ImageGrid health and readiness probes to Million Dollar Script network diagnostics without sending the saved API key.

## 1.0.8 - 2026-07-16

- Match the current ImageGrid service contract by replacing the obsolete remote preflight request with a local render estimate.
- Rely on atomic plan, quota, request-validation, and worker-capacity enforcement from `POST /v1/jobs` without generating a predictable 404 before every render.
- Keep direct browser tile delivery entitlement-gated and prefer signed `public_tile_url_template` or `tile_url_template` values returned by completed jobs.

## 1.0.7 - 2026-07-14

- Added persistent, dismissible administrator notices when ImageGrid rendering fails, with links to the affected grid and rendering settings.
- Added an opt-in, deduplicated render failure email sent through WordPress `wp_mail()` to the site administration address.
- Clear unresolved render warnings automatically after a successful submission or recovered job, and redact credentials, URLs, paths, and raw service payloads from stored notices.

## 1.0.6 - 2026-07-14

- Make automatic rendering the recommended default for fresh ImageGrid installations.
- Enable ImageGrid and automatic rendering after the first valid connection only while the fresh-install recommendation remains unchanged.
- Preserve pre-1.0.6 settings as a legacy preference and preserve every explicit on/off choice across updates, reactivation, and reconnects.
- Link the activation notice directly to the Connection tab and explain the automatic-rendering outcome after connection.
- Add upgrade, fresh-activation, first-connection, and explicit-off fixtures.

## 1.0.5 - 2026-07-14

- Split the ImageGrid admin page into URL-addressable Connection, Account, Grid rendering, and Local safeguards tabs that work without JavaScript.
- Scope settings saves by tab so absent controls cannot overwrite unrelated ImageGrid options.
- Add verified, stale, and unavailable account states without fabricating plan or usage values.
- Render plan allowances, usage, remaining values, and reset dates when authoritative service data is supplied.
- Add a confirmed Reset to plan limits action only when authoritative limits are available.
- Add accessible help controls and clearer automatic-rendering/local-resource guidance.
- Add deterministic admin, plan-data, tab-navigation, and paused-entitlement fixtures.

## 1.0.4 - 2026-07-14

- Match the connector to the current ImageGrid account and paid service-access contract.
- Refresh account and CDN entitlement metadata immediately after hosted connection.
- Preserve stable service error codes and show actionable authorization, billing, plan, account, policy, and quota guidance.
- Treat `past_due` and `paused` CDN entitlements as inactive.
- Distinguish local WordPress submission safeguards from service-enforced plan limits and explain that automatic rendering consumes account usage.
- Update customer account, billing, and API-key links for the current ImageGrid app routes.
- Add a WordPress fixture covering current coded errors and legacy text-only service responses.

## 1.0.3 - 2026-07-13

- Prevent authenticated API, asset upload, and tile requests from following redirects to another origin.
- Use WordPress safe HTTP validation for non-local service hosts and permit loopback service URLs only in local or development environments.
- Reject service URLs containing embedded credentials, query strings, or fragments.
- Add explicit light and dark borders, backgrounds, and foreground colors to ImageGrid status indicators.

## 1.0.2 - 2026-06-01

- Require the ImageGrid `mds3_cdn`/`cdn_delivery` account entitlement before advertising or proxying remote tiles to public MDS 3.0 grids.
- Preserve ImageGrid account `features` and `entitlements` metadata from hosted connect responses.
- Show CDN Delivery status in the ImageGrid settings, MDS 3.0 rendering settings, and grid editor panels.

## 1.0.1 - 2026-06-01

- Reclassify Million Dollar Script - ImageGrid Rendering as a free connector for the paid ImageGrid service.
- Split browser app links from the configurable server API URL.
- Add MDS 3.0 Settings, admin menu, and Edit Grid tab hooks.
- Keep ImageGrid credentials in extension-owned options while exposing them to Million Dollar Script core through filters.
- Add hosted ImageGrid connect-token return handling, site-scoped credential storage, and disconnect controls.
- Add render preflight before job submission.
- Submit current ImageGrid `grid_render` jobs and tolerate local API deployments without the optional preflight endpoint.
- Add MDS 3.0 remote tile metadata and proxy hooks so ready ImageGrid tilesets are served through the core tile endpoint.
- Add deferred placement asset patching for MDS 3.0 manifests after the base grid render is ready.
- Add a local WP-CLI fixture for the MDS 3.0 remote render, asset patch, and tile proxy workflow.

## 1.0.0

- Initial ImageGrid extension for Million Dollar Script 3.
- Adds ImageGrid account/API-key connection settings.
- Adds manual and optional automatic remote grid render job submission.
- Proxies authenticated ImageGrid tiles through WordPress for ready tilesets.
- Integrates with MDS 3.0 grid generation and tile delivery filters.
