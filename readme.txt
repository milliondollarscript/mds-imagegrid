=== Million Dollar Script - ImageGrid Rendering ===
Contributors: milliondollarscript
Requires at least: 6.0
Tested up to: 7.0.3
Requires PHP: 8.1
Stable tag: 1.0.8
License: GPL-3.0+

Free connector that connects Million Dollar Script grids to ImageGrid for hosted rendering and entitlement-gated direct CDN tile delivery.

== Description ==

Million Dollar Script - ImageGrid Rendering connects Million Dollar Script zoomable grids to the separately subscribed ImageGrid service. Site owners can connect through the hosted ImageGrid app or enter an ImageGrid API URL and API key manually, then submit ImageGrid grid_render jobs. Ready jobs expose signed, versioned direct tile templates only when the connected ImageGrid account has CDN Delivery access, without exposing the API key to browsers. Standard Deep Zoom geometry keeps large-grid tiles aligned at every zoom level. ImageGrid settings and grid render controls are added only when this extension is active.

== Installation ==

1. Install and activate Million Dollar Script.
2. Install and activate Million Dollar Script - ImageGrid Rendering.
3. Open Million Dollar Script > Dashboard > ImageGrid.
4. Connect ImageGrid or save the ImageGrid API URL and API key manually.
5. Test the connection, then render a grid from Million Dollar Script > Dashboard > ImageGrid or Grids > Edit Grid > ImageGrid.

== Changelog ==

= 1.0.8 =
* Match the current ImageGrid job API by removing the obsolete remote preflight request.
* Keep a local render estimate while relying on atomic plan, quota, validation, and capacity checks from job creation.
* Continue using direct signed CDN tile templates only when the service returns them for an entitled account.
* Preserve current plan, quota, usage-period, permission, and CDN-entitlement details when refreshing an account.
* Use the local ImageGrid account app during local development and the released imagegrid.dev service in production.

= 1.0.7 =
* Added persistent, dismissible render failure notices with affected-grid and ImageGrid settings links.
* Added optional deduplicated failure emails through WordPress wp_mail(), disabled by default.
* Clear warnings after recovery and redact credentials, URLs, paths, and raw service payloads from stored notices.

= 1.0.6 =

* Enable automatic rendering by default for fresh installations after a valid account is connected.
* Preserve existing and explicitly disabled automatic-rendering choices across upgrades, reactivation, and reconnects.
* Link activation guidance directly to the ImageGrid connection workflow.

= 1.0.5 =

* Organize ImageGrid administration into Connection, Account, Grid rendering, and Local safeguards tabs.
* Preserve unrelated options when saving one tab.
* Show verified, stale, and unavailable account states without inventing plan usage.
* Support authoritative plan/usage summaries and reset local safeguards when the service supplies limits.
* Add accessible help controls and clearer automatic-rendering guidance.

= 1.0.4 =

* Match the current ImageGrid account and paid service-access contract.
* Refresh account and CDN entitlements immediately after hosted connection.
* Explain automatic-rendering usage and distinguish local submission safeguards from account plan limits.
* Provide actionable guidance for authorization, billing, plan, account, and quota errors.
* Treat paused and past-due CDN entitlements as inactive.
* Update customer account, billing, and API-key links for the current ImageGrid app routes.

= 1.0.3 =

* Prevent authenticated API and tile requests from following redirects to another origin.
* Reject unsafe public service URLs while retaining explicit loopback support in local and development environments.
* Improve ImageGrid status and error contrast in light, dark, and system admin themes.

= 1.0.2 =
Requires the ImageGrid mds3_cdn/cdn_delivery entitlement before public MDS 3.0 grids advertise direct remote tiles, preserves account feature metadata from hosted connect responses, uses signed versioned tile templates and standard Deep Zoom geometry, and shows CDN Delivery status in the admin UI.

= 1.0.1 =
Adds Million Dollar Script dashboard menu, Settings tab, grid-editor tab integration, hosted connect-token handling, disconnect controls, current ImageGrid grid_render API compatibility, deferred placement asset patching, and authenticated remote tile proxying while keeping ImageGrid credentials in extension-owned options.

= 1.0.0 =
Initial ImageGrid connector for Million Dollar Script 3.
