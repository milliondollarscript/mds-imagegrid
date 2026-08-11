# ImageGrid Rendering

Million Dollar Script includes local grid rendering. The ImageGrid extension adds hosted rendering, larger-grid processing, and optional CDN delivery when the connected ImageGrid account supports those features.

## Practical Examples

- Render a 10,000 by 10,000 pixel grid without relying on a shared host's PHP memory and request-time limits.
- Process a busy grid after many sponsors update their artwork while keeping the local renderer available as a fallback.
- Serve completed Deep Zoom tiles directly from ImageGrid CDN when the connected plan includes CDN delivery, avoiding slow WordPress AJAX tile requests.

### Example workflow: move a large grid to hosted rendering

1. Open **Million Dollar Script -> ImageGrid -> Connection**, connect the site, and test authorization.
2. Review the verified plan, remaining allowance, and CDN capability under **Account**; keep local safeguards at or below the plan limits.
3. Under **Grid rendering**, select the large grid and submit a test render, then wait for the job to complete.
4. Open the public grid and confirm fit, pan, zoom, edge tiles, and block alignment. If CDN delivery is entitled and active, the browser loads the signed tile URLs directly from ImageGrid; otherwise it uses the local delivery fallback.

## Local Rendering

Local rendering keeps files on the WordPress server. It is suitable for smaller grids and ordinary shared hosting when images are within the configured upload limits.

Very large grids, frequent render requests, or heavy image processing can exceed shared hosting time, memory, or disk limits. Those cases are good candidates for hosted ImageGrid rendering.

## Hosted Rendering

When the ImageGrid extension is active and authorized, a grid can be submitted to the ImageGrid service for rendering. The core plugin keeps a local fallback path so public grids remain available when the remote service is unavailable.

Automatic rendering submits eligible jobs without an additional confirmation. Each remote job can consume processing credits and count toward the connected account plan limits. It is the recommended default for a fresh installation and becomes active after the first valid account connection. Leave it disabled when each submission should be reviewed first.

Upgrades preserve the existing automatic-rendering value. Reconnecting an older installation does not silently enable paid usage, and an explicit off choice remains off.

The current remote render operation is `grid_render`. Million Dollar Script core builds the grid manifest and exposes hooks, while the ImageGrid extension owns API credentials, remote job submission, polling, remote tile metadata, and CDN delivery checks. ImageGrid returns a standard Deep Zoom pyramid whose full-resolution level is derived from the grid dimensions; Million Dollar Script uses the same level geometry for browser zooming and edge-tile requests.

Job submissions use a per-render idempotency key. If WordPress loses the response after ImageGrid accepted a request, the next safe retry recovers the same remote job instead of spending credits on a duplicate. A different request cannot reuse that key. Million Dollar Script also serializes submissions for the same grid so two administrators or background requests do not intentionally start competing renders.

Per-grid background images are composed by the browser beneath placement artwork. The current hosted `grid_render` operation accepts a background color but not the fit, position, repeat, and opacity contract for a WordPress background attachment. Million Dollar Script therefore disables remote tiles and automatic or manual ImageGrid submission for a grid while that image is active. This prevents stale output and avoids charging processing credits for a render that would omit the background. Clear the image to resume hosted rendering; the fallback background color remains supported remotely.

## CDN Delivery

Hosted rendering and CDN delivery are separate capabilities. A connected ImageGrid API key can authorize rendering without authorizing CDN delivery. If CDN access is not included, public delivery remains local. When CDN Delivery is active, completed jobs can return a signed, versioned public tile URL template that browsers load directly from ImageGrid instead of routing tile traffic through WordPress.

## Setup

1. Activate Million Dollar Script - ImageGrid Rendering.
2. Open **Million Dollar Script -> ImageGrid**.
3. Connect ImageGrid, or enter the ImageGrid API URL and API key manually for local development.
4. Test the connection.
5. Render a grid from the ImageGrid page or from **Grids -> Edit Grid -> ImageGrid**.

## ImageGrid Settings

The ImageGrid admin page is divided into four tabs:

- **Connection** stores the server API URL and site-scoped API key, starts hosted connection, tests authorization, and disconnects the site.
- **Account** shows verified account identity and CDN status. Plan allowance, usage, remaining amounts, and reset dates appear only when ImageGrid supplies those values to the connected site.
- **Grid rendering** controls remote rendering behavior and provides manual job submission and status refresh for a selected grid.
- **Local safeguards** limits the estimated size of one remote submission before it leaves WordPress. These values cannot expand the ImageGrid plan.

Each tab has a stable URL and saves independently. Saving Connection settings does not change rendering behavior or local safeguards.

Before a manual render, **Submission estimate** shows the grid dimensions and megapixels, Deep Zoom level and tile counts, combined source artwork, base-render and estimated patch credits, uncompressed canvas and pyramid sizes, and a conservative padded-tile storage ceiling. Actual stored and delivered bytes are measured by ImageGrid because compression, artwork, cache hits, zoom depth, and visitor activity cannot be priced accurately from dimensions alone.

## Render Failure Notifications

When a controlled local-resource, connection, authorization, billing, quota, service, or terminal-job failure prevents ImageGrid rendering, the extension stores a bounded, privacy-safe warning in WordPress. Administrators see a persistent notice with links to the affected grid and the ImageGrid rendering settings. Dismissed notices stay dismissed until that grid recovers and the same type of problem happens again.

The **Render failure email** setting is disabled by default. Enable it to send one message through WordPress `wp_mail()` to the Administration Email Address configured under **Settings -> General**. Repeated reports of the same unresolved failure are deduplicated. A successful submission or recovered job clears the warning so a later recurrence can notify the administrator again.

Notices and emails never include the ImageGrid API key, signed URLs, raw manifests, filesystem paths, stack traces, or full service payloads. Sites that need delivery logs or SMTP retries can use a WordPress mail plugin with the same `wp_mail()` pipeline.

## Local Safeguards and Account Limits

The extension's **Local rendering safeguards** stop a job before submission when its estimated grid size, source-image size, tile count, storage, or processing credits exceed the configured WordPress-side cap. These values do not change the ImageGrid plan. ImageGrid independently enforces the connected account's plan, quota, and billing status.

Use the ImageGrid account to review the active plan, current usage, remaining credits, and service-enforced limits. Keep local safeguards at or below those limits when predictable submissions are more important than accepting every possible render job.

The Account tab separates per-job limits, concurrent capacity, periodic allowances, and storage or delivery limits. Source upload limits apply to each uploaded file. The combined-source safeguard is a WordPress workflow ceiling and does not replace the service's per-file checks.

When ImageGrid supplies authoritative effective limits to the connected site, **Reset to plan limits** replaces matching local safeguards with those values after confirmation. Safeguards without an equivalent service limit remain unchanged. The action is unavailable when plan limits have not been supplied.

## Admin Checks

Before using hosted rendering:

- Confirm the ImageGrid extension is active.
- Authorize the ImageGrid API key.
- Check plan usage and account status in ImageGrid.
- Confirm the local rendering safeguards are appropriate for the site.
- Render a test grid.
- Confirm the public page loads without slow admin-ajax tile requests.

For endpoint-level troubleshooting, open **Million Dollar Script -> System Status** and run **Network diagnostics**. ImageGrid adds separate health and readiness checks without sending the saved API key. A successful result proves service reachability, not account authorization; use **Test connection** to verify the credential and account.

## Connection Security

Use an HTTPS ImageGrid service URL on public sites. Loopback URLs such as `localhost` and `host.docker.internal` are accepted only when WordPress is configured as a local or development environment. Service URLs cannot contain embedded credentials, query strings, or fragments.

Production sites use `https://imagegrid.dev` for the account app and `https://api.imagegrid.dev` for API calls.

Authenticated API, upload, and tile requests do not follow redirects. If an endpoint has moved, update the configured service URL instead of relying on an HTTP redirect. This prevents ImageGrid credentials from being forwarded to an unintended origin.

## Account Access Messages

- **Authorization is required:** reconnect ImageGrid or verify the saved API key.
- **Billing needs attention:** open ImageGrid billing and resolve the payment state before retrying.
- **An active plan is required:** activate an ImageGrid plan for the connected account.
- **Account unavailable:** review the account status or connect a different account.
- **Account quota reached:** review usage and wait for the applicable reset or adjust the plan before retrying.

Paused and past-due CDN entitlements do not enable public CDN delivery. Public grids continue using local delivery until the entitlement is active again.

## Large Grids

Large grids should fit the page when zoomed out and allow zooming in for inspection. ImageGrid and Million Dollar Script use the same full-resolution Deep Zoom level and tile bounds, so the browser does not request tiles beyond the right or bottom edge. If edge blocks cannot be selected or tiles appear blurry, check that both packages are current, then review the grid dimensions, block size, tile source, and renderer mode.

Treat the connected account response as the authoritative remote limit. A 10,000 by 10,000 grid is 100 megapixels, but that example is valid only when the active plan reports at least that allowance. Extreme aspect ratios can require more working memory than a square grid with the same pixel count.

For shared hosting, start with a local-render threshold around 4 megapixels and increase it only after a representative render completes within the host's PHP memory and execution-time limits. On a stronger WordPress host, 25 megapixels is a reasonable starting point for local testing. Larger eligible grids should use ImageGrid. When ImageGrid is absent, disconnected, or rejects a large remote-only workload, Million Dollar Script records an actionable failure instead of attempting the same unsafe workload through the local renderer.

Persistent Redis, Valkey, or Memcached can improve repeat requests on some hosts, but ImageGrid correctness does not depend on a persistent object cache. Cache loss, eviction, or restart must leave the database and remote job state authoritative.
