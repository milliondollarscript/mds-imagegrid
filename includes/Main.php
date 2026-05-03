<?php
/**
 * Main ImageGrid extension class.
 *
 * @package MDS\Extensions\ImageGrid
 */

namespace MDS\Extensions\ImageGrid;

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
        $this->migrate_mds3_core_settings();

        if (is_admin()) {
            Admin::init();
        }

        add_filter('mds_grid_remote_tile_request', [$this, 'get_remote_tile_request'], 10, 5);
        add_filter('mds_grid_has_remote_tiles', [$this, 'has_remote_tiles'], 10, 2);
        add_filter('mds3_imagegrid_api_url', [$this, 'filter_mds3_api_url']);
        add_filter('mds3_imagegrid_api_key', [$this, 'filter_mds3_api_key']);
        add_filter('mds3_imagegrid_quota', [$this, 'filter_mds3_quota']);
        add_filter('mds3_imagegrid_remote_enabled', [$this, 'filter_mds3_remote_enabled'], 10, 2);
        add_filter('mds3_imagegrid_account_status', [$this, 'filter_mds3_account_status'], 10, 2);
        add_filter('mds3_imagegrid_test_connection', [$this, 'test_mds3_connection'], 10, 2);
        add_filter('mds3_imagegrid_local_render_threshold_megapixels', [$this, 'filter_mds3_local_render_threshold']);
        add_filter('mds3_imagegrid_submit_render', [$this, 'submit_mds3_render'], 10, 3);
        add_filter('mds3_imagegrid_poll_render', [$this, 'poll_mds3_render'], 10, 2);
        add_filter('mds_image_processor_generate_tiles_for_grid', [$this, 'maybe_start_remote_generation'], 10, 3);
        add_filter('mds_imagegrid_client', [$this, 'filter_client']);
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
            'account_url' => $app_url ? $app_url . '/app/account' : ($account['account_url'] ?? ''),
            'billing_portal_url' => $app_url ? $app_url . '/app/billing' : ($account['billing_portal_url'] ?? ''),
            'connect_url' => $this->connect_url(),
            'reconnect_url' => $this->connect_url('reconnect'),
            'fallback_mode' => $fallback_mode,
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
     * @return API_Client
     */
    public function filter_client() {
        return $this->client();
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

        return [
            'ok' => true,
            'status' => 200,
            'account_url' => $this->app_url(),
            'api_url' => $client->get_api_url(),
            'account' => $account,
        ];
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
     * Report whether ImageGrid has a completed remote tileset for a grid.
     *
     * @param bool $has_tiles Existing result.
     * @param int  $grid_id   Grid ID.
     * @return bool
     */
    public function has_remote_tiles($has_tiles, $grid_id) {
        if ($has_tiles || !self::is_enabled()) {
            return (bool) $has_tiles;
        }

        $state = self::get_grid_state($grid_id);

        return !empty($state['tileset_id']) && 'ready' === ($state['status'] ?? '');
    }

    /**
     * Return a server-side remote tile request for a ready grid.
     *
     * @param mixed $request Existing request value.
     * @param int   $grid_id Grid ID.
     * @param int   $z       Zoom level.
     * @param int   $x       Tile x coordinate.
     * @param int   $y       Tile y coordinate.
     * @return mixed
     */
    public function get_remote_tile_request($request, $grid_id, $z, $x, $y) {
        if ($request || !self::is_enabled()) {
            return $request;
        }

        $state = self::get_grid_state($grid_id);
        $tileset_id = $state['tileset_id'] ?? '';

        if (!$tileset_id || 'ready' !== ($state['status'] ?? '')) {
            return $request;
        }

        $tile_request = $this->client()->get_tileset_tile_request(
            $tileset_id,
            absint($z),
            absint($x),
            absint($y),
            $state['format'] ?? 'png'
        );

        return is_wp_error($tile_request) ? $request : $tile_request;
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
            if (!class_exists('\\MDS\\Core\\Grid')) {
                return $result;
            }

            $grid_model = new \MDS\Core\Grid();
            $grid = $grid_model->get($grid_id);
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
            self::update_grid_state($grid_id, [
                'status'     => 'failed',
                'last_error' => $response->get_error_message(),
                'updated_at' => current_time('mysql'),
            ]);

            return null;
        }

        return [
            'status' => 'remote_queued',
            'job_id' => $response['id'] ?? '',
        ];
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
            return $ready;
        }

        $response = $client->create_manifest_render_job($manifest, $preflight);
        if (is_wp_error($response)) {
            return $response;
        }

        $grid_id = absint($manifest['grid']['id'] ?? 0);
        $job_id = sanitize_text_field((string) ($response['id'] ?? $response['job_id'] ?? ''));
        $result_payload = is_array($response['result'] ?? null) ? $response['result'] : [];
        $tileset_id = sanitize_text_field((string) ($result_payload['tileset_id'] ?? $response['tileset_id'] ?? ''));
        $status = self::normalize_job_status($response['status'] ?? 'pending');

        if ($grid_id) {
            self::update_grid_state($grid_id, [
                'job_id' => $job_id,
                'status' => $status,
                'operation' => $response['operation'] ?? 'mds_grid_composition',
                'tileset_id' => $tileset_id,
                'format' => $result_payload['format'] ?? 'png',
                'preflight' => $preflight,
                'submitted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'last_error' => '',
            ]);
        }

        return [
            'remote_job_id' => $job_id,
            'remote_tileset_id' => $tileset_id,
            'status' => $status,
            'operation' => $response['operation'] ?? 'mds_grid_composition',
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

        $response = $this->client()->get_job($job_id);
        if (is_wp_error($response)) {
            return $response;
        }

        $result_payload = is_array($response['result'] ?? null) ? $response['result'] : [];
        $status = self::normalize_job_status($response['status'] ?? ($job['status'] ?? 'pending'));
        $tileset_id = sanitize_text_field((string) ($result_payload['tileset_id'] ?? $response['tileset_id'] ?? ($job['remote_tileset_id'] ?? '')));
        $grid_id = absint($job['grid_id'] ?? 0);

        if ($grid_id) {
            self::update_grid_state($grid_id, [
                'job_id' => $response['id'] ?? $job_id,
                'status' => $status,
                'tileset_id' => $tileset_id,
                'format' => $result_payload['format'] ?? 'png',
                'tile_size' => $result_payload['tile_size'] ?? 256,
                'min_level' => $result_payload['min_level'] ?? 0,
                'max_level' => $result_payload['max_level'] ?? 0,
                'total_tiles' => $result_payload['total_tiles'] ?? 0,
                'last_error' => sanitize_text_field((string) ($response['error_message'] ?? '')),
                'updated_at' => current_time('mysql'),
            ]);
        }

        return [
            'status' => $status,
            'remote_tileset_id' => $tileset_id,
            'result' => $response,
            'error_message' => sanitize_text_field((string) ($response['error_message'] ?? '')),
        ];
    }

    /**
     * Submit a grid render job.
     *
     * @param int                 $grid_id Grid ID.
     * @param \MDS\Core\Grid|null $grid    Optional grid object.
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
                'updated_at' => current_time('mysql'),
            ]);

            return new \WP_Error('mds_imagegrid_preflight_failed', $message, $preflight);
        }

        $response = $client->create_grid_render_job($grid);

        if (is_wp_error($response)) {
            return $response;
        }

        self::update_grid_state($grid_id, [
            'job_id'     => $response['id'] ?? '',
            'status'     => self::normalize_job_status($response['status'] ?? 'pending'),
            'operation'  => $response['operation'] ?? 'mds_grid_composition',
            'tileset_id' => '',
            'format'     => 'png',
            'preflight'  => $preflight,
            'submitted_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'last_error' => '',
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
                'updated_at' => current_time('mysql'),
            ]);

            return $response;
        }

        $result = isset($response['result']) && is_array($response['result']) ? $response['result'] : [];

        self::update_grid_state($grid_id, [
            'job_id'     => $response['id'] ?? $job_id,
            'status'     => self::normalize_job_status($response['status'] ?? ($state['status'] ?? '')),
            'tileset_id' => $result['tileset_id'] ?? ($state['tileset_id'] ?? ''),
            'format'     => $result['format'] ?? ($state['format'] ?? 'png'),
            'tile_size'  => $result['tile_size'] ?? ($state['tile_size'] ?? 256),
            'min_level'  => $result['min_level'] ?? ($state['min_level'] ?? 0),
            'max_level'  => $result['max_level'] ?? ($state['max_level'] ?? 0),
            'total_tiles' => $result['total_tiles'] ?? ($state['total_tiles'] ?? 0),
            'last_error' => $response['error_message'] ?? '',
            'updated_at' => current_time('mysql'),
        ]);

        return $response;
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
        $existing = self::get_grid_state($grid_id);
        $updated = array_merge($existing, $state);

        update_option(self::grid_state_option_name($grid_id), $updated, false);
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
     * Whether remote integration is enabled.
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option('mds_imagegrid_enabled', false);
    }

    /**
     * Create an API client.
     *
     * @return API_Client
     */
    private function client() {
        return new API_Client();
    }

    /**
     * Resolve a grid from the active MDS version.
     *
     * @param int $grid_id Grid ID.
     * @return object|null
     */
    private static function get_grid($grid_id) {
        $grid_id = absint($grid_id);

        if (class_exists('\\MDS3\\Grid\\GridRepository')) {
            return (new \MDS3\Grid\GridRepository())->find($grid_id);
        }

        if (class_exists('\\MDS\\Core\\Grid')) {
            $grid_model = new \MDS\Core\Grid();

            return $grid_model->get($grid_id);
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
