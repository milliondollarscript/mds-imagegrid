<?php
/**
 * Main ImageGrid extension class.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates ImageGrid integration.
 */
class Main {

    /**
     * Singleton instance.
     *
     * @var Main|null
     */
    private static $instance = null;

    /**
     * Whether hooks have been registered.
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * Get singleton instance.
     *
     * @return Main
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the extension.
     *
     * @return void
     */
    public static function init() {
        self::instance()->initialize();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    private function initialize() {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
        $this->migrate_auto_render_preference();
        $this->migrate_mds3_core_settings();
        Render_Failure_Notifier::init();

        if (is_admin()) {
            Admin::init();
        }

        add_filter('million-dollar-script/imagegrid/api/url', [$this, 'filter_mds3_api_url']);
        add_filter('million-dollar-script/imagegrid/api/key', [$this, 'filter_mds3_api_key']);
        add_filter('million-dollar-script/imagegrid/quota', [$this, 'filter_mds3_quota']);
        add_filter('million-dollar-script/imagegrid/remote/enabled', [$this, 'filter_mds3_remote_enabled'], 10, 2);
        add_filter('million-dollar-script/imagegrid/account/status', [$this, 'filter_mds3_account_status'], 10, 2);
        add_filter('million-dollar-script/imagegrid/test/connection', [$this, 'test_mds3_connection'], 10, 2);
        add_filter('million-dollar-script/imagegrid/local/render/threshold/megapixels', [$this, 'filter_mds3_local_render_threshold']);
        add_filter('million-dollar-script/imagegrid/submit/render', [$this, 'submit_mds3_render'], 10, 3);
        add_filter('million-dollar-script/imagegrid/poll/render', [$this, 'poll_mds3_render'], 10, 2);
        add_filter('mds_image_processor_generate_tiles_for_grid', [$this, 'maybe_start_remote_generation'], 10, 3);
        add_filter('million-dollar-script/extensions/imagegrid/client', [$this, 'filter_client']);
        add_filter('million-dollar-script/grid/remote/tile/request', [$this, 'remote_tile_request'], 10, 5);
        add_filter('million-dollar-script/grid/has/remote/tiles', [$this, 'grid_has_remote_tiles'], 10, 2);
        add_filter('million-dollar-script/grid/remote/tile/metadata', [$this, 'remote_tile_metadata'], 10, 3);
        add_filter('million-dollar-script/extension/onboarding/items', [$this, 'extension_onboarding_items']);
    }

    /**
     * Add ImageGrid setup shortcuts and legal draft recommendations.
     *
     * @param array $items Existing onboarding items.
     * @return array
     */
    public function extension_onboarding_items(array $items) {
        $items['mds-imagegrid'] = [
            'name' => __('ImageGrid Rendering', 'mds-imagegrid'),
            'summary' => __('Connect the hosted rendering service, review local fallback limits, confirm CDN Delivery access, and decide when large grids should render remotely.', 'mds-imagegrid'),
            'priority' => 12,
            'actions' => [
                [
                    'label' => __('Open ImageGrid settings', 'mds-imagegrid'),
                    'url' => admin_url('admin.php?page=mds3-imagegrid'),
                    'primary' => true,
                ],
                [
                    'label' => __('Review rendering settings', 'mds-imagegrid'),
                    'url' => admin_url('admin.php?page=mds3-settings&tab=rendering'),
                ],
            ],
            'legal_documents' => [
                [
                    'slug' => 'rendering-service-terms',
                    'title' => __('ImageGrid Rendering Service Terms', 'mds-imagegrid'),
                    'description' => __('Terms for hosted rendering, quotas, local fallback, CDN Delivery, fees, failed jobs, and publication responsibility.', 'mds-imagegrid'),
                    'page_slug' => 'imagegrid-rendering-service-terms',
                    'content' => $this->imagegrid_rendering_terms_content(),
                ],
                [
                    'slug' => 'rendering-privacy-notice',
                    'title' => __('ImageGrid Rendering Privacy Notice', 'mds-imagegrid'),
                    'description' => __('Privacy language for source images, grid metadata, API credentials, hosted processing, CDN delivery, logs, and retention.', 'mds-imagegrid'),
                    'page_slug' => 'imagegrid-rendering-privacy-notice',
                    'content' => $this->imagegrid_privacy_notice_content(),
                ],
            ],
        ];

        return $items;
    }

    /**
     * Draft ImageGrid service terms content.
     *
     * @return string
     */
    private function imagegrid_rendering_terms_content() {
        return '<h2>' . esc_html__('ImageGrid Rendering Service Terms', 'mds-imagegrid') . '</h2>'
            . '<p>' . esc_html__('These terms apply when {{site_name}} uses ImageGrid to render Million Dollar Script grids, source images, placement artwork, coordinates, tiles, metadata, and rendering instructions. Questions about this service should be sent through {{contact_method}}.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Hosted Rendering and Local Fallback', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Eligible grids may be processed by the hosted ImageGrid service when the extension is enabled, authorized, and configured for remote rendering. Smaller grids, fallback jobs, or jobs that do not qualify for remote processing may render locally in WordPress. Rendering can be delayed, retried, rejected, or unavailable because of quota limits, account status, API availability, source-image problems, unsupported dimensions, security checks, maintenance, or hosting limitations.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Quotas, Fees, Credits, and Failed Jobs', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Remote rendering may depend on an ImageGrid account plan, processing credits, storage limits, CDN Delivery rights, retry limits, and usage safeguards. Fees are charged in the currency shown at checkout or in the connected service account. A submitted job does not guarantee successful rendering, uninterrupted delivery, traffic, sponsor results, search ranking, revenue, or any specific business outcome. Failed or rejected jobs may remain eligible for local rendering or admin review, but processing fees, service credits, and refunds follow the applicable account and checkout terms.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('CDN Delivery and Public Availability', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Remote tile delivery should be enabled only when the connected ImageGrid API key includes CDN Delivery access. Without CDN Delivery, {{site_name}} may keep public grid delivery local even when the hosted service creates render assets. CDN caches, browser caches, and third-party network providers may continue serving previously published files for a limited time after content is changed or removed.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Content Responsibility and Rights', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Site administrators and placement submitters are responsible for image rights, trademarks, copyright permissions, advertising claims, regulated disclosures, accessibility text, prohibited content, landing pages, and any consent required to publish or process submitted materials. {{site_name}} may reject, pause, replace, remove, or re-render content that appears unlawful, infringing, unsafe, malicious, misleading, abusive, technically harmful, or inconsistent with site rules.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Security, Availability, and Changes', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Administrators must protect ImageGrid API keys and only connect trusted accounts. The service may throttle requests, suspend access, rotate credentials, change rendering limits, update delivery paths, or require reauthorization to protect security and reliability. Scheduled or emergency maintenance may temporarily affect rendering, polling, previews, downloads, or CDN delivery.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Support and Disputes', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Support requests should include the site URL, grid ID, job ID when available, affected image or tile details, and a description of the issue. Disputes about submitted artwork, publication decisions, fees, refunds, account access, or service availability are handled under the checkout, account, and site terms that apply to {{site_name}}.', 'mds-imagegrid') . '</p>';
    }

    /**
     * Draft ImageGrid privacy notice content.
     *
     * @return string
     */
    private function imagegrid_privacy_notice_content() {
        return '<h2>' . esc_html__('ImageGrid Rendering Privacy Notice', 'mds-imagegrid') . '</h2>'
            . '<p>' . esc_html__('This notice explains how {{site_name}} may process data when ImageGrid is used for hosted or local rendering on {{site_url}}. It supplements the main site privacy policy at {{privacy_policy_url}}.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Data Sent for Rendering', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('When remote rendering is enabled, {{site_name}} may send the data needed to generate grid images and tiles. This can include uploaded sponsor artwork, placement coordinates, grid dimensions, colors, background images, URLs, alt text, job configuration, account identifiers, API authorization data, job status, technical logs, and delivery metadata.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Credentials and Account Data', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('ImageGrid API keys and account connection metadata are administrative credentials. They should be stored only by authorized administrators, never published in public pages, and never shared with sponsors or ordinary site users. If a key may have been exposed, administrators should revoke it, create a new key, review recent jobs, and rotate any related credentials.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Retention, Logs, and CDN Delivery', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Rendered files, source assets, job logs, CDN cache records, request metadata, and abuse-prevention records may be retained as needed to operate the service, troubleshoot failures, verify billing or credits, prevent misuse, and serve cached grid assets. Some cached files may remain available briefly after removal while caches expire. Requests for access, correction, deletion, export, or removal from future renders can be made through {{contact_method}} and may be limited by security, accounting, dispute, backup, or legal-retention requirements.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Third-Party Processing', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Remote rendering, CDN delivery, backups, monitoring, and security logging may involve infrastructure providers outside the direct control of {{site_name}}. Those providers act under their own service terms, data-processing commitments, security controls, and regional transfer safeguards. Administrators should publish any additional provider-specific terms required for the jurisdictions where the site operates.', 'mds-imagegrid') . '</p>'
            . '<h3>' . esc_html__('Security and User Choices', 'mds-imagegrid') . '</h3>'
            . '<p>' . esc_html__('Administrators use access controls, nonce checks, API authorization, upload validation, and service-side limits to reduce misuse. Users should submit only artwork and personal information they are allowed to provide. Where privacy law grants rights to access, correct, export, restrict, object to, or erase personal information, requests can be made through {{contact_method}}.', 'mds-imagegrid') . '</p>';
    }

    /**
     * Provide the extension-owned API URL to MDS3 core services.
     *
     * @param string $api_url Existing URL.
     * @return string
     */
    public function filter_mds3_api_url($api_url) {
        $configured = (string) get_option('mds_imagegrid_api_url', '');

        return '' !== $configured ? untrailingslashit($configured) : (string) $api_url;
    }

    /**
     * Provide the extension-owned API key to MDS3 core services.
     *
     * @param string $api_key Existing key.
     * @return string
     */
    public function filter_mds3_api_key($api_key) {
        $configured = (string) get_option('mds_imagegrid_api_key', '');

        return '' !== $configured ? $configured : (string) $api_key;
    }

    /**
     * Provide extension-owned quota settings to MDS3 core services.
     *
     * @param array $quota Existing quota values.
     * @return array
     */
    public function filter_mds3_quota(array $quota) {
        $mapping = [
            'grid_megapixels' => ['mds_imagegrid_quota_grid_megapixels', 100],
            'source_megapixels' => ['mds_imagegrid_quota_source_megapixels', 250],
            'tile_estimate' => ['mds_imagegrid_quota_tile_estimate', 12000],
            'storage_estimate_bytes' => ['mds_imagegrid_quota_storage_bytes', 2147483648],
            'processing_credits' => ['mds_imagegrid_quota_processing_credits', 5000],
        ];

        foreach ($mapping as $quota_key => [$option_key, $default]) {
            $quota[$quota_key] = (float) get_option($option_key, $default);
        }

        return $quota;
    }

    /**
     * Tell MDS3 whether remote rendering is enabled by this extension.
     *
     * @param bool  $enabled  Existing value.
     * @param array $settings Core settings.
     * @return bool
     */
    public function filter_mds3_remote_enabled($enabled, array $settings = []) {
        unset($settings);

        return (bool) $enabled || (self::is_enabled() && '' !== (string) get_option('mds_imagegrid_api_key', ''));
    }

    /**
     * Provide extension-owned account status to the MDS3 dashboard.
     *
     * @param array $account  Existing status.
     * @param array $settings Core settings.
     * @return array
     */
    public function filter_mds3_account_status(array $account, array $settings = []) {
        unset($settings);

        $enabled = self::is_enabled();
        $has_api_key = '' !== (string) get_option('mds_imagegrid_api_key', '');
        $last_account = get_option('mds_imagegrid_last_account', []);
        $last_connection = get_option('mds_imagegrid_last_connection', []);
        $connection = get_option('mds_imagegrid_connection', []);
        $last_account = is_array($last_account) ? $last_account : [];
        $last_connection = is_array($last_connection) ? $last_connection : [];
        $connection = is_array($connection) ? $connection : [];
        $connected = $has_api_key && 'connected' === sanitize_key((string) ($last_connection['status'] ?? ''));
        $app_url = $this->app_url();
        $account_name = $this->account_display_name($last_account);
        $cdn_access = self::has_cdn_delivery_access($last_account);
        $verified_cdn_access = $connected && $cdn_access;

        if ($connected && $enabled) {
            $status = 'connected';
            $label = __('Connected', 'mds-imagegrid');
            $plan = $account_name ?: __('ImageGrid account connected', 'mds-imagegrid');
            $fallback_mode = 'remote_when_needed';
        } elseif ($has_api_key && !$enabled) {
            $status = 'disabled';
            $label = __('Disabled', 'mds-imagegrid');
            $plan = __('ImageGrid configured but disabled', 'mds-imagegrid');
            $fallback_mode = 'local';
        } elseif ($has_api_key || !empty($connection)) {
            $status = 'configured';
            $label = __('Configured', 'mds-imagegrid');
            $plan = $account_name ?: __('ImageGrid account configured', 'mds-imagegrid');
            $fallback_mode = $enabled ? 'remote_when_needed' : 'local';
        } else {
            $status = 'disconnected';
            $label = __('Local fallback', 'mds-imagegrid');
            $plan = __('Local rendering only', 'mds-imagegrid');
            $fallback_mode = 'local';
        }

        return array_merge($account, [
            'status' => $status,
            'label' => $label,
            'plan' => $plan,
            'usage' => $this->account_usage($last_account),
            'account_url' => $app_url ? $app_url . '/app' : ($account['account_url'] ?? ''),
            'billing_portal_url' => $app_url ? $app_url . '/app/billing' : ($account['billing_portal_url'] ?? ''),
            'connect_url' => $this->connect_url(),
            'reconnect_url' => $this->connect_url('reconnect'),
            'fallback_mode' => $fallback_mode,
            'features' => array_merge((array) ($account['features'] ?? []), [
                'cdn_delivery' => $verified_cdn_access,
                'mds3_cdn' => $verified_cdn_access,
            ]),
            'cdn_delivery' => [
                'enabled' => $verified_cdn_access,
                'label' => $verified_cdn_access ? __('CDN Delivery enabled', 'mds-imagegrid') : __('CDN Delivery not active', 'mds-imagegrid'),
            ],
        ]);
    }

    /**
     * Provide the extension-owned local render threshold to MDS3 core services.
     *
     * @param float $threshold Existing threshold.
     * @return float
     */
    public function filter_mds3_local_render_threshold($threshold) {
        return (float) get_option('mds_imagegrid_local_render_threshold_megapixels', $threshold);
    }

    /**
     * Return a client instance to callers.
     *
     * @param mixed $client Existing client from an earlier filter.
     * @return object
     */
    public function filter_client($client = null) {
        return is_object($client) ? $client : new API_Client();
    }

    /**
     * Test the ImageGrid connection for MDS3 REST callers.
     *
     * @param mixed $result   Existing result.
     * @param array $settings Core settings.
     * @return array|\WP_Error
     */
    public function test_mds3_connection($result, array $settings = []) {
        unset($settings);

        if (null !== $result) {
            return $result;
        }

        $client = $this->client();
        $ready = $client->ready();
        if (is_wp_error($ready)) {
            $this->record_connection_failure($ready->get_error_message());

            return $ready;
        }

        $account = $client->account();
        if (is_wp_error($account)) {
            $this->record_connection_failure($account->get_error_message());

            return $account;
        }

        update_option('mds_imagegrid_last_account', $account, false);
        update_option('mds_imagegrid_last_connection', [
            'status' => 'connected',
            'message' => __('Connection verified.', 'mds-imagegrid'),
            'checked_at' => current_time('mysql'),
        ], false);
        self::apply_connected_defaults();

        return [
            'ok' => true,
            'status' => 200,
            'account_url' => $this->app_url(),
            'api_url' => $client->get_api_url(),
            'account' => $account,
        ];
    }

    /**
     * Apply recommended rendering defaults only to a fresh, unconfigured site.
     *
     * @return bool Whether defaults were applied.
     */
    public static function apply_connected_defaults() {
        if ('recommended_default' !== (string) get_option('mds_imagegrid_auto_render_preference', '')) {
            return false;
        }

        update_option('mds_imagegrid_enabled', true, false);
        update_option('mds_imagegrid_auto_render', true, false);

        return true;
    }

    /**
     * Preserve pre-1.0.6 behavior as an explicit legacy preference.
     *
     * Fresh activation runs after plugins_loaded and writes its own sentinel,
     * while an upgraded installation already has the old boolean option.
     *
     * @return void
     */
    private function migrate_auto_render_preference() {
        if ('__missing__' !== get_option('mds_imagegrid_auto_render_preference', '__missing__')) {
            return;
        }

        if ('__missing__' !== get_option('mds_imagegrid_auto_render', '__missing__')) {
            add_option('mds_imagegrid_auto_render_preference', 'legacy_preserved', '', 'no');
        }
    }

    /**
     * Move legacy MDS3 core-owned ImageGrid settings into extension-owned options.
     *
     * @return void
     */
    private function migrate_mds3_core_settings() {
        $map = [
            'imagegrid_api_url' => 'mds_imagegrid_api_url',
            'imagegrid_api_key' => 'mds_imagegrid_api_key',
            'local_render_threshold_megapixels' => 'mds_imagegrid_local_render_threshold_megapixels',
            'imagegrid_quota_grid_megapixels' => 'mds_imagegrid_quota_grid_megapixels',
            'imagegrid_quota_source_megapixels' => 'mds_imagegrid_quota_source_megapixels',
            'imagegrid_quota_tile_estimate' => 'mds_imagegrid_quota_tile_estimate',
            'imagegrid_quota_storage_bytes' => 'mds_imagegrid_quota_storage_bytes',
            'imagegrid_quota_processing_credits' => 'mds_imagegrid_quota_processing_credits',
        ];

        $settings = get_option('mds3_settings', []);
        if (!is_array($settings)) {
            update_option('mds_imagegrid_migrated_mds3_core_settings', MDS_IMAGEGRID_VERSION, false);
            return;
        }

        $has_core_keys = (bool) array_intersect(array_keys($map), array_keys($settings));
        if (get_option('mds_imagegrid_migrated_mds3_core_settings') && !$has_core_keys) {
            return;
        }

        $updated_settings = $settings;
        foreach ($map as $old_key => $new_key) {
            if (!array_key_exists($old_key, $settings) || '' === (string) $settings[$old_key]) {
                unset($updated_settings[$old_key]);
                continue;
            }

            if ('' === (string) get_option($new_key, '')) {
                update_option($new_key, $settings[$old_key], false);
            }
            unset($updated_settings[$old_key]);
        }

        if ($updated_settings !== $settings) {
            update_option('mds3_settings', $updated_settings, false);
        }

        update_option('mds_imagegrid_migrated_mds3_core_settings', MDS_IMAGEGRID_VERSION, false);
    }

    /**
     * Optionally offload tile generation to ImageGrid.
     *
     * @param mixed $result  Existing filter result.
     * @param int   $grid_id Grid ID.
     * @param array $context Generation context.
     * @return mixed
     */
    public function maybe_start_remote_generation($result, $grid_id, $context) {
        if (null !== $result || !self::is_enabled() || !get_option('mds_imagegrid_auto_render', false)) {
            return $result;
        }

        $grid = isset($context['grid']) ? $context['grid'] : null;

        if (!$grid || !is_object($grid)) {
            if (!class_exists('\\MillionDollarScript\\Core\\Grids')) {
                return $result;
            }

            $grid = \MillionDollarScript\Core\Grids::find($grid_id);
        }

        if (!$grid) {
            return $result;
        }

        $existing = self::get_grid_state($grid_id);
        if (!empty($existing['job_id']) && in_array($existing['status'] ?? '', ['queued', 'running', 'pending', 'rendering'], true)) {
            return [
                'status' => 'remote_in_progress',
                'job_id' => $existing['job_id'],
            ];
        }

        $response = self::submit_grid_render($grid_id, $grid);

        if (is_wp_error($response)) {
            return null;
        }

        return [
            'status' => 'remote_queued',
            'job_id' => $response['id'] ?? '',
        ];
    }

    /**
     * Provide a remote tile descriptor for MDS3's tile proxy.
     *
     * @param mixed $request Existing request descriptor.
     * @param int   $grid_id Grid ID.
     * @param int   $z       Zoom level.
     * @param int   $x       Tile x coordinate.
     * @param int   $y       Tile y coordinate.
     * @return mixed
     */
    public function remote_tile_request($request, $grid_id, $z, $x, $y) {
        if (null !== $request || !self::can_serve_remote_tiles()) {
            return $request;
        }

        $state = self::get_grid_state($grid_id);
        if (!self::state_is_remote_ready($state)) {
            return $request;
        }

        $tile_request = $this->client()->get_tileset_tile_request(
            $state['tileset_id'],
            absint($z),
            absint($x),
            absint($y),
            $state['format'] ?? 'png'
        );

        return is_wp_error($tile_request) ? $request : $tile_request;
    }

    /**
     * Tell MDS3 grid state responses when remote tiles are available.
     *
     * @param bool $has_remote Existing flag.
     * @param int  $grid_id    Grid ID.
     * @return bool
     */
    public function grid_has_remote_tiles($has_remote, $grid_id) {
        if ($has_remote || !self::can_serve_remote_tiles()) {
            return (bool) $has_remote;
        }

        return self::state_is_remote_ready(self::get_grid_state($grid_id));
    }

    /**
     * Provide tile metadata for MDS3's OpenLayers renderer.
     *
     * @param array  $metadata Existing metadata.
     * @param int    $grid_id  Grid ID.
     * @param object $grid     Grid object.
     * @return array
     */
    public function remote_tile_metadata(array $metadata, $grid_id, $grid = null) {
        unset($grid);

        if (!self::can_serve_remote_tiles()) {
            return $metadata;
        }

        $state = self::get_grid_state($grid_id);
        if (!self::state_is_remote_ready($state)) {
            return $metadata;
        }

        return array_merge($metadata, [
            'remote' => true,
            'job_id' => sanitize_text_field((string) ($state['job_id'] ?? '')),
            'tileset_id' => sanitize_text_field((string) ($state['tileset_id'] ?? '')),
            'url_template' => self::sanitize_tile_url_template((string) ($state['tile_url_template'] ?? '')),
            'format' => sanitize_key((string) ($state['format'] ?? 'png')),
            'tile_size' => max(64, absint($state['tile_size'] ?? 256)),
            'min_level' => absint($state['min_level'] ?? 0),
            'max_level' => absint($state['max_level'] ?? 0),
            'level_scheme' => 'deepzoom',
            'updated_at' => sanitize_text_field((string) ($state['updated_at'] ?? '')),
        ]);
    }

    /**
     * Whether a stored ImageGrid state can serve remote tiles.
     *
     * @param array $state Stored grid state.
     * @return bool
     */
    private static function state_is_remote_ready(array $state) {
        return 'ready' === sanitize_key((string) ($state['status'] ?? ''))
            && !empty($state['tileset_id'])
            && empty($state['patch_job_id']);
    }

    /**
     * Submit an MDS3 render manifest to ImageGrid.
     *
     * @param mixed $result    Existing filter result.
     * @param array $manifest  MDS3 manifest.
     * @param array $preflight Core preflight data.
     * @return mixed
     */
    public function submit_mds3_render($result, array $manifest, array $preflight) {
        if (null !== $result || !self::is_enabled()) {
            return $result;
        }

        $client = $this->client();
        $ready = $client->ready();
        if (is_wp_error($ready)) {
            $grid_id = absint($manifest['grid']['id'] ?? 0);
            self::update_grid_state($grid_id, [
                'status' => 'failed',
                'last_error' => $ready->get_error_message(),
                'failure_code' => $ready->get_error_code(),
                'updated_at' => current_time('mysql'),
            ]);
            return $ready;
        }

        $response = $client->create_manifest_render_job($manifest, $preflight);
        if (is_wp_error($response)) {
            $grid_id = absint($manifest['grid']['id'] ?? 0);
            self::update_grid_state($grid_id, [
                'status' => 'failed',
                'last_error' => $response->get_error_message(),
                'failure_code' => $response->get_error_code(),
                'updated_at' => current_time('mysql'),
            ]);
            return $response;
        }

        $grid_id = absint($manifest['grid']['id'] ?? 0);
        $job_id = sanitize_text_field((string) ($response['id'] ?? $response['job_id'] ?? ''));
        $result_payload = is_array($response['result'] ?? null) ? $response['result'] : [];
        $tileset_id = sanitize_text_field((string) ($result_payload['tileset_id'] ?? $response['tileset_id'] ?? ''));
        $status = self::normalize_job_status($response['status'] ?? 'pending');
        $last_error = 'failed' === $status
            ? sanitize_text_field((string) ($response['error_message'] ?? __('ImageGrid job failed.', 'mds-imagegrid')))
            : '';

        if ($grid_id) {
            self::update_grid_state($grid_id, array_merge([
                'job_id' => $job_id,
                'status' => $status,
                'operation' => $response['operation'] ?? 'grid_render',
                'tileset_id' => $tileset_id,
                'format' => $result_payload['format'] ?? 'png',
                'manifest' => self::compact_manifest_state($manifest),
                'patch_job_id' => '',
                'patch_queue' => [],
                'patch_queue_prepared' => false,
                'patch_complete' => false,
                'preflight' => $preflight,
                'submitted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'last_error' => $last_error,
            ], self::tile_delivery_state($result_payload)));
        }

        return [
            'remote_job_id' => $job_id,
            'remote_tileset_id' => $tileset_id,
            'status' => $status,
            'operation' => $response['operation'] ?? 'grid_render',
            'result' => $response,
        ];
    }

    /**
     * Poll an MDS3 ImageGrid render job and return a core-compatible update.
     *
     * @param mixed $result Existing filter result.
     * @param array $job    MDS3 render job row.
     * @return mixed
     */
    public function poll_mds3_render($result, array $job) {
        if (null !== $result || !self::is_enabled()) {
            return $result;
        }

        $job_id = sanitize_text_field((string) ($job['remote_job_id'] ?? ''));
        if ('' === $job_id) {
            return $result;
        }

        $grid_id = absint($job['grid_id'] ?? 0);
        if ($grid_id) {
            $state = self::get_grid_state($grid_id);
            if (!empty($state['patch_job_id'])) {
                return $this->poll_manifest_patch_job($job, $state);
            }
        }

        $response = $this->client()->get_job($job_id);
        if (is_wp_error($response)) {
            if ($grid_id) {
                self::update_grid_state($grid_id, [
                    'last_error' => $response->get_error_message(),
                    'failure_code' => $response->get_error_code(),
                    'updated_at' => current_time('mysql'),
                ]);
            }
            return $response;
        }

        $result_payload = is_array($response['result'] ?? null) ? $response['result'] : [];
        $status = self::normalize_job_status($response['status'] ?? ($job['status'] ?? 'pending'));
        $tileset_id = sanitize_text_field((string) ($result_payload['tileset_id'] ?? $response['tileset_id'] ?? ($job['remote_tileset_id'] ?? '')));

        if ($grid_id && 'ready' === $status && $tileset_id) {
            $state = self::get_grid_state($grid_id);
            if (self::state_has_manifest_assets($state) && empty($state['patch_complete'])) {
                return $this->start_manifest_patch_queue($grid_id, $tileset_id, $state);
            }
        }

        if ($grid_id) {
            self::update_grid_state($grid_id, array_merge([
                'job_id' => $response['id'] ?? $job_id,
                'status' => $status,
                'tileset_id' => $tileset_id,
                'format' => $result_payload['format'] ?? 'png',
                'tile_size' => $result_payload['tile_size'] ?? 256,
                'min_level' => $result_payload['min_level'] ?? 0,
                'max_level' => $result_payload['max_level'] ?? 0,
                'total_tiles' => $result_payload['total_tiles'] ?? 0,
                'last_error' => 'failed' === $status
                    ? sanitize_text_field((string) ($response['error_message'] ?? __('ImageGrid job failed.', 'mds-imagegrid')))
                    : sanitize_text_field((string) ($response['error_message'] ?? '')),
                'updated_at' => current_time('mysql'),
            ], self::tile_delivery_state($result_payload, self::get_grid_state($grid_id))));
        }

        return [
            'status' => $status,
            'remote_tileset_id' => $tileset_id,
            'result' => $response,
            'error_message' => sanitize_text_field((string) ($response['error_message'] ?? '')),
        ];
    }

    /**
     * Poll an active tile_patch job in the MDS3 manifest workflow.
     *
     * @param array $job   Core render job row.
     * @param array $state Extension grid state.
     * @return array|\WP_Error
     */
    private function poll_manifest_patch_job(array $job, array $state) {
        $grid_id = absint($job['grid_id'] ?? 0);
        $patch_job_id = sanitize_text_field((string) ($state['patch_job_id'] ?? ''));
        if (!$grid_id || !$patch_job_id) {
            return $job;
        }

        $response = $this->client()->get_job($patch_job_id);
        if (is_wp_error($response)) {
            return $response;
        }

        $result_payload = is_array($response['result'] ?? null) ? $response['result'] : [];
        $status = self::normalize_job_status($response['status'] ?? ($state['status'] ?? 'pending'));
        $tileset_id = sanitize_text_field((string) ($result_payload['tileset_id'] ?? ($state['tileset_id'] ?? '')));

        if ('ready' === $status && $tileset_id) {
            $queue = is_array($state['patch_queue'] ?? null) ? $state['patch_queue'] : [];
            if ($queue) {
                $state['patch_job_id'] = '';
                $state['tileset_id'] = $tileset_id;
                $state['status'] = 'pending';

                return $this->start_manifest_patch_queue($grid_id, $tileset_id, $state);
            }

            self::update_grid_state($grid_id, array_merge([
                'patch_job_id' => '',
                'patch_complete' => true,
                'status' => 'ready',
                'tileset_id' => $tileset_id,
                'format' => $result_payload['format'] ?? ($state['format'] ?? 'png'),
                'tile_size' => $result_payload['tile_size'] ?? ($state['tile_size'] ?? 256),
                'min_level' => $result_payload['min_level'] ?? ($state['min_level'] ?? 0),
                'max_level' => $result_payload['max_level'] ?? ($state['max_level'] ?? 0),
                'total_tiles' => $result_payload['total_tiles'] ?? ($state['total_tiles'] ?? 0),
                'last_error' => '',
                'updated_at' => current_time('mysql'),
            ], self::tile_delivery_state($result_payload, $state)));

            return [
                'status' => 'ready',
                'remote_tileset_id' => $tileset_id,
                'result' => $response,
                'error_message' => '',
            ];
        }

        if ('failed' === $status) {
            $message = sanitize_text_field((string) ($response['error_message'] ?? __('ImageGrid tile patch failed.', 'mds-imagegrid')));
            self::update_grid_state($grid_id, [
                'status' => 'failed',
                'last_error' => $message,
                'updated_at' => current_time('mysql'),
            ]);

            return [
                'status' => 'failed',
                'remote_tileset_id' => '',
                'result' => $response,
                'error_message' => $message,
            ];
        }

        self::update_grid_state($grid_id, [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ]);

        return [
            'status' => $status,
            'remote_tileset_id' => '',
            'result' => $response,
            'error_message' => '',
        ];
    }

    /**
     * Start or continue the manifest asset patch queue.
     *
     * @param int    $grid_id    Grid ID.
     * @param string $tileset_id Current ImageGrid tileset UUID.
     * @param array  $state      Extension grid state.
     * @return array|\WP_Error
     */
    private function start_manifest_patch_queue($grid_id, $tileset_id, array $state) {
        $grid_id = absint($grid_id);
        $tileset_id = sanitize_text_field((string) $tileset_id);
        $queue = is_array($state['patch_queue'] ?? null) ? $state['patch_queue'] : [];

        if (empty($state['patch_queue_prepared'])) {
            $manifest = is_array($state['manifest'] ?? null) ? $state['manifest'] : [];
            $queue = $this->client()->prepare_manifest_asset_patches($manifest);
            if (is_wp_error($queue)) {
                $message = $queue->get_error_message();
                self::update_grid_state($grid_id, [
                    'status' => 'failed',
                    'last_error' => $message,
                    'updated_at' => current_time('mysql'),
                ]);

                return [
                    'status' => 'failed',
                    'remote_tileset_id' => '',
                    'result' => ['error' => $message],
                    'error_message' => $message,
                ];
            }
        }

        if (!$queue) {
            self::update_grid_state($grid_id, [
                'patch_job_id' => '',
                'patch_queue' => [],
                'patch_queue_prepared' => true,
                'patch_complete' => true,
                'status' => 'ready',
                'tileset_id' => $tileset_id,
                'updated_at' => current_time('mysql'),
            ]);

            return [
                'status' => 'ready',
                'remote_tileset_id' => $tileset_id,
                'result' => ['tileset_id' => $tileset_id, 'patches' => 0],
                'error_message' => '',
            ];
        }

        $patch = array_shift($queue);
        $response = $this->client()->create_tile_patch_job($tileset_id, [$patch]);
        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            self::update_grid_state($grid_id, [
                'status' => 'failed',
                'last_error' => $message,
                'updated_at' => current_time('mysql'),
            ]);

            return [
                'status' => 'failed',
                'remote_tileset_id' => '',
                'result' => ['error' => $message],
                'error_message' => $message,
            ];
        }

        $patch_job_id = sanitize_text_field((string) ($response['id'] ?? $response['job_id'] ?? ''));
        $status = self::normalize_job_status($response['status'] ?? 'pending');
        self::update_grid_state($grid_id, [
            'base_tileset_id' => $state['base_tileset_id'] ?? $tileset_id,
            'patch_job_id' => $patch_job_id,
            'patch_queue' => $queue,
            'patch_queue_prepared' => true,
            'patch_complete' => false,
            'status' => $status,
            'tileset_id' => '',
            'updated_at' => current_time('mysql'),
            'last_error' => '',
        ]);

        return [
            'status' => $status,
            'remote_tileset_id' => '',
            'result' => [
                'patch_job' => $response,
                'remaining_patches' => count($queue),
            ],
            'error_message' => '',
        ];
    }

    /**
     * Submit a grid render job.
     *
     * @param int                 $grid_id Grid ID.
     * @param \MillionDollarScript\Core\Grid|null $grid Optional grid object.
     * @return array|\WP_Error
     */
    public static function submit_grid_render($grid_id, $grid = null) {
        $grid_id = absint($grid_id);

        if (!$grid) {
            $grid = self::get_grid($grid_id);
        }

        if (!$grid) {
            return new \WP_Error('mds_imagegrid_grid_not_found', __('Grid not found.', 'mds-imagegrid'));
        }

        $client = self::instance()->client();
        $preflight = $client->preflight_grid_render($grid);

        if (is_wp_error($preflight)) {
            self::update_grid_state($grid_id, [
                'status'     => 'failed',
                'last_error' => $preflight->get_error_message(),
                'failure_code' => $preflight->get_error_code(),
                'updated_at' => current_time('mysql'),
            ]);

            return $preflight;
        }

        if (isset($preflight['ok']) && !$preflight['ok']) {
            $failing_key = sanitize_key((string) ($preflight['failing_key'] ?? 'quota'));
            $message = sprintf(
                __('ImageGrid quota preflight failed for %s.', 'mds-imagegrid'),
                $failing_key
            );

            self::update_grid_state($grid_id, [
                'status'     => 'failed',
                'preflight'  => $preflight,
                'last_error' => $message,
                'failure_code' => 'mds_imagegrid_preflight_failed_' . $failing_key,
                'updated_at' => current_time('mysql'),
            ]);

            return new \WP_Error('mds_imagegrid_preflight_failed', $message, $preflight);
        }

        $response = $client->create_grid_render_job($grid);

        if (is_wp_error($response)) {
            self::update_grid_state($grid_id, [
                'status' => 'failed',
                'last_error' => $response->get_error_message(),
                'failure_code' => $response->get_error_code(),
                'updated_at' => current_time('mysql'),
            ]);
            return $response;
        }

        $status = self::normalize_job_status($response['status'] ?? 'pending');
        $last_error = 'failed' === $status
            ? sanitize_text_field((string) ($response['error_message'] ?? __('ImageGrid job failed.', 'mds-imagegrid')))
            : '';

        self::update_grid_state($grid_id, [
            'job_id'     => $response['id'] ?? '',
            'status'     => $status,
            'operation'  => $response['operation'] ?? 'grid_render',
            'tileset_id' => '',
            'format'     => 'png',
            'preflight'  => $preflight,
            'submitted_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'last_error' => $last_error,
        ]);

        return $response;
    }

    /**
     * Refresh a saved ImageGrid job and persist grid state.
     *
     * @param int $grid_id Grid ID.
     * @return array|\WP_Error
     */
    public static function refresh_grid_job($grid_id) {
        $grid_id = absint($grid_id);
        $state = self::get_grid_state($grid_id);
        $job_id = $state['job_id'] ?? '';

        if (!$job_id) {
            return new \WP_Error('mds_imagegrid_missing_job', __('No ImageGrid job is stored for this grid.', 'mds-imagegrid'));
        }

        $response = self::instance()->client()->get_job($job_id);

        if (is_wp_error($response)) {
            self::update_grid_state($grid_id, [
                'last_error' => $response->get_error_message(),
                'failure_code' => $response->get_error_code(),
                'updated_at' => current_time('mysql'),
            ]);

            return $response;
        }

        $result = isset($response['result']) && is_array($response['result']) ? $response['result'] : [];

        $status = self::normalize_job_status($response['status'] ?? ($state['status'] ?? ''));
        $last_error = 'failed' === $status
            ? sanitize_text_field((string) ($response['error_message'] ?? __('ImageGrid job failed.', 'mds-imagegrid')))
            : sanitize_text_field((string) ($response['error_message'] ?? ''));

        self::update_grid_state($grid_id, array_merge([
            'job_id'     => $response['id'] ?? $job_id,
            'status'     => $status,
            'tileset_id' => $result['tileset_id'] ?? ($state['tileset_id'] ?? ''),
            'format'     => $result['format'] ?? ($state['format'] ?? 'png'),
            'tile_size'  => $result['tile_size'] ?? ($state['tile_size'] ?? 256),
            'min_level'  => $result['min_level'] ?? ($state['min_level'] ?? 0),
            'max_level'  => $result['max_level'] ?? ($state['max_level'] ?? 0),
            'total_tiles' => $result['total_tiles'] ?? ($state['total_tiles'] ?? 0),
            'last_error' => $last_error,
            'updated_at' => current_time('mysql'),
        ], self::tile_delivery_state($result, $state)));

        return $response;
    }

    /**
     * Keep only the manifest fields needed for deferred tile_patch jobs.
     *
     * @param array $manifest Raw MDS3 manifest.
     * @return array
     */
    private static function compact_manifest_state(array $manifest) {
        $assets = [];
        foreach ((array) ($manifest['assets'] ?? []) as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $assets[] = [
                'placement_id' => absint($asset['placement_id'] ?? 0),
                'attachment_id' => absint($asset['attachment_id'] ?? 0),
                'x' => absint($asset['x'] ?? 0),
                'y' => absint($asset['y'] ?? 0),
                'width' => absint($asset['width'] ?? 0),
                'height' => absint($asset['height'] ?? 0),
                'fit_mode' => sanitize_key((string) ($asset['fit_mode'] ?? 'cover')),
            ];
        }

        return [
            'grid' => [
                'id' => absint($manifest['grid']['id'] ?? 0),
                'width' => absint($manifest['grid']['width'] ?? 0),
                'height' => absint($manifest['grid']['height'] ?? 0),
                'block_width' => absint($manifest['grid']['block_width'] ?? 0),
                'block_height' => absint($manifest['grid']['block_height'] ?? 0),
            ],
            'tile_size' => absint($manifest['tile_size'] ?? 256),
            'assets' => $assets,
        ];
    }

    /**
     * Check whether stored state contains deferred placement assets.
     *
     * @param array $state Extension grid state.
     * @return bool
     */
    private static function state_has_manifest_assets(array $state) {
        return !empty($state['manifest']['assets']) && is_array($state['manifest']['assets']);
    }

    /**
     * Extract direct tile delivery fields returned by ImageGrid, if present.
     *
     * @param array $result Remote job result payload.
     * @param array $state  Existing grid state.
     * @return array
     */
    private static function tile_delivery_state(array $result, array $state = []) {
        $template = self::sanitize_tile_url_template((string) (
            $result['public_tile_url_template']
            ?? $result['tile_url_template']
            ?? $result['cdn_tile_url_template']
            ?? $result['url_template']
            ?? ($state['tile_url_template'] ?? '')
        ));

        return [
            'tile_url_template' => $template,
            'level_scheme' => 'deepzoom',
        ];
    }

    /**
     * Validate a browser-safe tile URL template.
     *
     * @param string $template URL containing {z}, {x}, and {y} tokens.
     * @return string
     */
    private static function sanitize_tile_url_template($template) {
        $template = trim((string) $template);
        if ('' === $template) {
            return '';
        }

        $template = str_replace(['%7Bz%7D', '%7Bx%7D', '%7By%7D', '%7Bformat%7D'], ['{z}', '{x}', '{y}', '{format}'], $template);
        foreach (['{z}', '{x}', '{y}'] as $token) {
            if (false === strpos($template, $token)) {
                return '';
            }
        }

        $check_url = str_replace(['{z}', '{x}', '{y}', '{format}'], ['0', '0', '0', 'png'], $template);

        return esc_url_raw($check_url) ? $template : '';
    }

    /**
     * Get grid remote state.
     *
     * @param int $grid_id Grid ID.
     * @return array
     */
    public static function get_grid_state($grid_id) {
        $state = get_option(self::grid_state_option_name($grid_id), []);

        return is_array($state) ? $state : [];
    }

    /**
     * Update grid remote state.
     *
     * @param int   $grid_id Grid ID.
     * @param array $state   State patch.
     * @return void
     */
    public static function update_grid_state($grid_id, array $state) {
        $grid_id = absint($grid_id);
        if (!$grid_id) {
            return;
        }

        $existing = self::get_grid_state($grid_id);
        $updated = array_merge($existing, $state);

        update_option(self::grid_state_option_name($grid_id), $updated, false);

        $last_error = sanitize_text_field((string) ($state['last_error'] ?? ''));
        if ('' !== $last_error) {
            Render_Failure_Notifier::record($grid_id, (string) ($state['failure_code'] ?? $state['status'] ?? ''), $last_error);
            return;
        }

        if (array_key_exists('last_error', $state) && in_array(sanitize_key((string) ($updated['status'] ?? '')), ['pending', 'queued', 'running', 'rendering', 'ready'], true)) {
            Render_Failure_Notifier::resolve_grid($grid_id);
        }
    }

    /**
     * Record a failed connection check in extension-owned options.
     *
     * @param string $message Failure message.
     * @return void
     */
    private function record_connection_failure($message) {
        update_option('mds_imagegrid_last_connection', [
            'status' => 'failed',
            'message' => sanitize_text_field((string) $message),
            'checked_at' => current_time('mysql'),
        ], false);
    }

    /**
     * Return the customer-facing ImageGrid app URL.
     *
     * @return string
     */
    private function app_url() {
        return function_exists(__NAMESPACE__ . '\\mds_imagegrid_app_url')
            ? mds_imagegrid_app_url()
            : 'https://imagegrid.dev';
    }

    /**
     * Build a hosted connect URL for dashboard/account actions.
     *
     * @param string $mode Optional connect mode.
     * @return string
     */
    private function connect_url($mode = '') {
        $app_url = $this->app_url();
        if (!$app_url) {
            return '';
        }

        $return_url = add_query_arg(
            [
                'action' => 'mds_imagegrid_connect_return',
                '_wpnonce' => wp_create_nonce('mds_imagegrid_connect_return'),
            ],
            admin_url('admin-post.php')
        );

        $args = [
            'return_url' => rawurlencode($return_url),
            'site_url' => home_url('/'),
            'site_name' => get_bloginfo('name'),
            'plugin' => 'mds-imagegrid',
        ];
        if ('' !== (string) $mode) {
            $args['mode'] = sanitize_key((string) $mode);
        }

        return add_query_arg($args, $app_url . '/app/integrations/mds/connect');
    }

    /**
     * Extract a readable account name from stored ImageGrid account data.
     *
     * @param array $account Stored account payload.
     * @return string
     */
    private function account_display_name(array $account) {
        $nested = is_array($account['account'] ?? null) ? $account['account'] : $account;

        return sanitize_text_field((string) ($nested['display_name'] ?? $nested['name'] ?? $nested['slug'] ?? ''));
    }

    /**
     * Extract usage data from stored ImageGrid account data.
     *
     * @param array $account Stored account payload.
     * @return array
     */
    private function account_usage(array $account) {
        if (is_array($account['usage'] ?? null)) {
            return $account['usage'];
        }

        if (is_array($account['account']['usage'] ?? null)) {
            return $account['account']['usage'];
        }

        return [];
    }

    /**
     * Resolve CDN access from supported account payload shapes.
     *
     * @param array $account Stored account payload.
     * @return bool
     */
    private static function account_payload_has_cdn_delivery_access(array $account) {
        $keys = ['mds3_cdn', 'mds_cdn_delivery', 'cdn_delivery'];
        $feature_sets = [
            $account['features'] ?? null,
            $account['capabilities'] ?? null,
            $account['permissions'] ?? null,
            $account['account']['features'] ?? null,
            $account['account']['capabilities'] ?? null,
            $account['account']['permissions'] ?? null,
            $account['api_key']['features'] ?? null,
            $account['api_key']['capabilities'] ?? null,
            $account['api_key']['permissions'] ?? null,
        ];

        foreach ($feature_sets as $features) {
            if (!is_array($features)) {
                continue;
            }

            foreach ($keys as $key) {
                if (array_key_exists($key, $features) && self::truthy($features[$key])) {
                    return true;
                }
            }
        }

        $entitlement_sets = [
            $account['entitlements'] ?? null,
            $account['account']['entitlements'] ?? null,
            $account['api_key']['entitlements'] ?? null,
        ];

        foreach ($entitlement_sets as $entitlements) {
            if (!is_array($entitlements)) {
                continue;
            }

            foreach ($entitlements as $key => $entitlement) {
                if (is_string($key) && in_array($key, $keys, true) && self::entitlement_enabled($entitlement)) {
                    return true;
                }

                if (is_array($entitlement)) {
                    $feature_code = sanitize_key((string) ($entitlement['feature_code'] ?? $entitlement['code'] ?? ''));
                    if (in_array($feature_code, $keys, true) && self::entitlement_enabled($entitlement)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Interpret entitlement status payloads from ImageGrid.
     *
     * @param mixed $entitlement Entitlement value.
     * @return bool
     */
    private static function entitlement_enabled($entitlement) {
        if (!is_array($entitlement)) {
            return self::truthy($entitlement);
        }

        $status = sanitize_key((string) ($entitlement['status'] ?? ''));
        if (array_key_exists('enabled', $entitlement)) {
            return self::truthy($entitlement['enabled'])
                && ('' === $status || in_array($status, ['trialing', 'active'], true));
        }

        return in_array($status, ['trialing', 'active'], true);
    }

    /**
     * Normalize boolean-like API values.
     *
     * @param mixed $value Value from account metadata.
     * @return bool
     */
    private static function truthy($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'enabled', 'active'], true);
    }

    /**
     * Whether remote integration is enabled.
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option('mds_imagegrid_enabled', false);
    }

    /**
     * Whether the connected account has paid CDN delivery for public MDS3 tiles.
     *
     * @param array|null $account Optional account payload.
     * @return bool
     */
    public static function has_cdn_delivery_access($account = null) {
        if (null === $account) {
            $account = get_option('mds_imagegrid_last_account', []);
        }

        return is_array($account) && self::account_payload_has_cdn_delivery_access($account);
    }

    /**
     * Whether remote tile proxying may be exposed to public grids.
     *
     * @return bool
     */
    private static function can_serve_remote_tiles() {
        return self::is_enabled() && self::has_cdn_delivery_access();
    }

    /**
     * Create an API client.
     *
     * @return object
     */
    private function client() {
        $client = \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/imagegrid/client', ['mds_imagegrid_client'], null);

        return is_object($client) ? $client : new API_Client();
    }

    /**
     * Resolve a grid from the active MDS version.
     *
     * @param int $grid_id Grid ID.
     * @return object|null
     */
    private static function get_grid($grid_id) {
        $grid_id = absint($grid_id);

        if (class_exists('\\MillionDollarScript\\Core\\Grids')) {
            return \MillionDollarScript\Core\Grids::find($grid_id);
        }

        return null;
    }

    /**
     * Per-grid option name.
     *
     * @param int $grid_id Grid ID.
     * @return string
     */
    private static function grid_state_option_name($grid_id) {
        return 'mds_imagegrid_grid_' . absint($grid_id);
    }

    /**
     * Normalize ImageGrid statuses to MDS-facing job states.
     *
     * @param string $status Remote status.
     * @return string
     */
    private static function normalize_job_status($status) {
        $status = sanitize_key((string) $status);

        if (in_array($status, ['queued', 'pending'], true)) {
            return 'pending';
        }

        if (in_array($status, ['running', 'processing', 'rendering'], true)) {
            return 'rendering';
        }

        if (in_array($status, ['succeeded', 'complete', 'completed', 'ready'], true)) {
            return 'ready';
        }

        if (in_array($status, ['failed', 'error'], true)) {
            return 'failed';
        }

        return $status ?: 'pending';
    }
}
