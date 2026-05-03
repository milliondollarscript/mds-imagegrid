<?php
/**
 * ImageGrid admin UI.
 *
 * @package MDS\Extensions\ImageGrid
 */

namespace MDS\Extensions\ImageGrid;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings and actions.
 */
class Admin {

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 36);
        add_action('mds3_admin_menu', [__CLASS__, 'register_mds3_menu']);
        add_action('admin_head', [__CLASS__, 'hide_mds3_sidebar_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('mds3_dashboard_menu_items', [__CLASS__, 'register_mds3_dashboard_menu']);
        add_action('mds3_admin_settings_group_rendering', [__CLASS__, 'render_mds3_settings_panel']);
        add_filter('mds3_admin_grid_tabs', [__CLASS__, 'register_mds3_grid_tab'], 10, 2);
        add_action('mds3_admin_grid_tab_panels', [__CLASS__, 'render_mds3_grid_panel'], 10, 2);
        add_filter('mds3_admin_validate_settings', [__CLASS__, 'validate_mds3_settings'], 10, 3);
        add_filter('mds3_admin_sanitize_settings', [__CLASS__, 'sanitize_mds3_settings'], 10, 3);
        add_action('mds3_admin_settings_saved', [__CLASS__, 'after_mds3_settings_saved'], 10, 3);
        add_action('admin_post_mds_imagegrid_save_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_mds_imagegrid_test_connection', [__CLASS__, 'handle_test_connection']);
        add_action('admin_post_mds_imagegrid_connect_return', [__CLASS__, 'handle_connect_return']);
        add_action('admin_post_mds_imagegrid_disconnect', [__CLASS__, 'handle_disconnect']);
        add_action('admin_post_mds_imagegrid_submit_grid_render', [__CLASS__, 'handle_submit_grid_render']);
        add_action('admin_post_mds_imagegrid_refresh_job', [__CLASS__, 'handle_refresh_job']);
    }

    /**
     * Register submenu.
     *
     * @return void
     */
    public static function register_menu() {
        if (defined('MDS3_VERSION')) {
            return;
        }

        add_submenu_page(
            'mds-dashboard',
            __('ImageGrid', 'mds-imagegrid'),
            __('ImageGrid', 'mds-imagegrid'),
            'manage_options',
            'mds-imagegrid',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register an MDS3 submenu from the core admin menu hook.
     *
     * @return void
     */
    public static function register_mds3_menu() {
        if (!defined('MDS3_VERSION')) {
            return;
        }

        add_submenu_page(
            'mds3',
            __('ImageGrid', 'mds-imagegrid'),
            __('ImageGrid', 'mds-imagegrid'),
            'manage_options',
            'mds3-imagegrid',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Keep ImageGrid in the Million Dollar Script dashboard menu instead of the WordPress sidebar.
     *
     * @return void
     */
    public static function hide_mds3_sidebar_menu() {
        if (defined('MDS3_VERSION')) {
            remove_submenu_page('mds3', 'mds3-imagegrid');
        }
    }

    /**
     * Add ImageGrid to the internal MDS3 dashboard menu without crowding the WordPress sidebar.
     *
     * @param array $groups Existing dashboard menu groups.
     * @return array
     */
    public static function register_mds3_dashboard_menu(array $groups) {
        if (!defined('MDS3_VERSION')) {
            return $groups;
        }

        if (!isset($groups['extend']) || !is_array($groups['extend'])) {
            $groups['extend'] = [
                'label' => __('Extend', 'mds-imagegrid'),
                'items' => [],
            ];
        }

        if (empty($groups['extend']['items']) || !is_array($groups['extend']['items'])) {
            $groups['extend']['items'] = [];
        }

        $groups['extend']['items'][] = [
            'label' => __('ImageGrid', 'mds-imagegrid'),
            'url' => admin_url('admin.php?page=mds3-imagegrid'),
            'icon' => 'dashicons-format-image',
        ];

        return $groups;
    }

    /**
     * Enqueue admin CSS.
     *
     * @param string $hook Current admin hook.
     * @return void
     */
    public static function enqueue_assets($hook) {
        if (!in_array($hook, ['mds-dashboard_page_mds-imagegrid', 'mds_page_mds-imagegrid', 'million-dollar-script_page_mds3-settings', 'million-dollar-script_page_mds3-grids', 'million-dollar-script_page_mds3-imagegrid', 'admin_page_mds3-imagegrid'], true)) {
            return;
        }

        wp_enqueue_style(
            'mds-imagegrid-admin',
            MDS_IMAGEGRID_URL . 'assets/css/admin.css',
            [],
            self::asset_version('assets/css/admin.css')
        );
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage ImageGrid settings.', 'mds-imagegrid'));
        }

        $enabled = (bool) get_option('mds_imagegrid_enabled', false);
        $auto_render = (bool) get_option('mds_imagegrid_auto_render', false);
        $api_url = (string) get_option('mds_imagegrid_api_url', mds_imagegrid_default_api_url());
        $has_api_key = '' !== (string) get_option('mds_imagegrid_api_key', '');
        $threshold = self::number_option('mds_imagegrid_local_render_threshold_megapixels', 25);
        $quota_grid = self::number_option('mds_imagegrid_quota_grid_megapixels', 100);
        $quota_source = self::number_option('mds_imagegrid_quota_source_megapixels', 250);
        $quota_tiles = self::number_option('mds_imagegrid_quota_tile_estimate', 12000);
        $quota_storage = self::number_option('mds_imagegrid_quota_storage_bytes', 2147483648);
        $quota_credits = self::number_option('mds_imagegrid_quota_processing_credits', 5000);
        $last_account = get_option('mds_imagegrid_last_account', []);
        $last_connection = get_option('mds_imagegrid_last_connection', []);
        $connection = self::connection();
        $connect_url = self::connect_url();
        $grids = self::get_grids();
        $selected_grid_id = isset($_GET['grid_id']) ? absint(wp_unslash($_GET['grid_id'])) : 0;
        $app_url = self::get_app_url();
        $page_slug = self::settings_page_slug();

        if (!$selected_grid_id && !empty($grids)) {
            $first_grid = reset($grids);
            $selected_grid_id = self::grid_id($first_grid);
        }

        $selected_state = $selected_grid_id ? Main::get_grid_state($selected_grid_id) : [];
        $notice = self::get_notice();

        include MDS_IMAGEGRID_PATH . 'templates/admin/settings.php';
    }

    /**
     * Render ImageGrid settings inside the MDS3 core Rendering settings panel.
     *
     * @return void
     */
    public static function render_mds3_settings_panel() {
        $enabled = (bool) get_option('mds_imagegrid_enabled', false);
        $auto_render = (bool) get_option('mds_imagegrid_auto_render', false);
        $api_url = (string) get_option('mds_imagegrid_api_url', mds_imagegrid_default_api_url());
        $has_api_key = '' !== (string) get_option('mds_imagegrid_api_key', '');
        $threshold = self::number_option('mds_imagegrid_local_render_threshold_megapixels', 25);
        $quota_grid = self::number_option('mds_imagegrid_quota_grid_megapixels', 100);
        $quota_source = self::number_option('mds_imagegrid_quota_source_megapixels', 250);
        $quota_tiles = self::number_option('mds_imagegrid_quota_tile_estimate', 12000);
        $quota_storage = self::number_option('mds_imagegrid_quota_storage_bytes', 2147483648);
        $quota_credits = self::number_option('mds_imagegrid_quota_processing_credits', 5000);
        $last_connection = get_option('mds_imagegrid_last_connection', []);
        $app_url = self::get_app_url();
        $connect_url = self::connect_url();
        $connection = self::connection();
        $has_connection = $has_api_key || !empty($connection);
        $connection_status = is_array($last_connection) ? ($last_connection['status'] ?? 'not_connected') : 'not_connected';

        echo '<div class="mds-imagegrid-core-settings">';
        echo '<input type="hidden" name="mds_imagegrid_settings_present" value="1" />';
        echo '<h3>' . esc_html__('ImageGrid remote rendering', 'mds-imagegrid') . '</h3>';
        echo '<p class="description">' . esc_html__('Connect Million Dollar Script zoomable grids to ImageGrid for remote rendering and authenticated tile delivery.', 'mds-imagegrid') . '</p>';
        echo '<p><span class="mds-imagegrid-status mds-imagegrid-status-' . esc_attr($connection_status) . '">' . esc_html('connected' === $connection_status ? __('Connected', 'mds-imagegrid') : __('Not Connected', 'mds-imagegrid')) . '</span></p>';
        self::checkbox_field('mds_imagegrid_enabled', __('Enable ImageGrid', 'mds-imagegrid'), $enabled, __('Serve ready remote tiles for grids that have an ImageGrid tileset.', 'mds-imagegrid'));
        self::checkbox_field('mds_imagegrid_auto_render', __('Automatic rendering', 'mds-imagegrid'), $auto_render, __('Submit a remote render job when Million Dollar Script requests tile generation.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_api_url', __('API URL', 'mds-imagegrid'), 'url', $api_url, __('Server API URL. This can point at local Docker during development and is never used for browser account links.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_api_key', __('API key', 'mds-imagegrid'), 'password', '', $has_api_key ? __('Saved key is hidden. Leave blank to keep it.', 'mds-imagegrid') : __('Paste an ImageGrid API key.', 'mds-imagegrid'), $has_api_key ? __('Saved key is hidden', 'mds-imagegrid') : __('Paste an ImageGrid API key', 'mds-imagegrid'));
        if ($has_api_key) {
            self::checkbox_field('mds_imagegrid_clear_api_key', __('Clear saved key', 'mds-imagegrid'), false, __('Remove the saved API key during this settings save.', 'mds-imagegrid'));
        }
        self::input_field('mds_imagegrid_local_render_threshold_megapixels', __('Local Render Threshold MP', 'mds-imagegrid'), 'number', (string) $threshold, __('Use local rendering at or below this grid size. Larger grids can use ImageGrid when configured.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_quota_grid_megapixels', __('Grid Megapixel Quota', 'mds-imagegrid'), 'number', (string) $quota_grid, __('Maximum grid megapixels allowed for a remote ImageGrid job.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_quota_source_megapixels', __('Source Megapixel Quota', 'mds-imagegrid'), 'number', (string) $quota_source, __('Maximum combined source-image megapixels allowed for a remote ImageGrid job.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_quota_tile_estimate', __('Tile Estimate Quota', 'mds-imagegrid'), 'number', (string) $quota_tiles, __('Maximum estimated tile count allowed for a remote ImageGrid job.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_quota_storage_bytes', __('Storage Byte Quota', 'mds-imagegrid'), 'number', (string) $quota_storage, __('Maximum estimated storage footprint allowed for a remote ImageGrid job.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_quota_processing_credits', __('Processing Credit Quota', 'mds-imagegrid'), 'number', (string) $quota_credits, __('Maximum estimated processing credits allowed for a remote ImageGrid job.', 'mds-imagegrid'));
        echo '<p class="mds3-button-row">';
        if (!empty($connect_url)) {
            echo '<a class="button button-primary" href="' . esc_url($connect_url) . '">' . esc_html__('Connect ImageGrid', 'mds-imagegrid') . '</a>';
        }
        if (!empty($app_url)) {
            echo '<a class="button" href="' . esc_url($app_url . '/app/signup') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Create ImageGrid Account', 'mds-imagegrid') . '</a>';
            echo '<a class="button" href="' . esc_url($app_url . '/app/login') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Manage API Keys', 'mds-imagegrid') . '</a>';
        }
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::settings_page_slug())) . '">' . esc_html($has_connection ? __('Manage Connection', 'mds-imagegrid') : __('Open ImageGrid Settings', 'mds-imagegrid')) . '</a>';
        echo '</p></div>';
    }

    /**
     * Add ImageGrid to the MDS3 grid edit tabs.
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public static function register_mds3_grid_tab(array $tabs) {
        $tabs['grid-imagegrid'] = __('ImageGrid', 'mds-imagegrid');

        return $tabs;
    }

    /**
     * Render per-grid ImageGrid controls in the MDS3 grid editor.
     *
     * @param object $grid MDS3 grid entity.
     * @return void
     */
    public static function render_mds3_grid_panel($grid, $active_tab = '') {
        $grid_id = self::grid_id($grid);
        $enabled = (bool) get_option('mds_imagegrid_enabled', false);
        $has_api_key = '' !== (string) get_option('mds_imagegrid_api_key', '');
        $state = $grid_id ? Main::get_grid_state($grid_id) : [];
        $notice = self::get_notice();
        $classes = 'mds3-settings-panel';
        if ('grid-imagegrid' === (string) $active_tab) {
            $classes .= ' is-active';
        }

        echo '<section class="' . esc_attr($classes) . '" data-settings-panel="grid-imagegrid">';
        echo '<h2>' . esc_html__('ImageGrid', 'mds-imagegrid') . '</h2>';
        if ($notice) {
            echo '<div class="notice notice-' . esc_attr(self::notice_class($notice['type'])) . ' inline"><p>' . esc_html($notice['message']) . '</p></div>';
        }
        echo '<p>' . esc_html__('Submit this grid for hosted ImageGrid rendering and serve ready remote tiles through Million Dollar Script.', 'mds-imagegrid') . '</p>';

        if (!$enabled || !$has_api_key) {
            echo '<p class="description">' . esc_html__('Enable ImageGrid and save an API key in Settings before submitting render jobs.', 'mds-imagegrid') . '</p>';
        }

        echo '<div class="mds-imagegrid-grid-actions">';
        self::grid_action_form('mds_imagegrid_submit_grid_render', 'mds_imagegrid_submit_grid_render', $grid_id, __('Render With ImageGrid', 'mds-imagegrid'), 'button button-primary', !$grid_id || !$enabled || !$has_api_key);
        self::grid_action_form('mds_imagegrid_refresh_job', 'mds_imagegrid_refresh_job', $grid_id, __('Refresh Job', 'mds-imagegrid'), 'button', !$grid_id || empty($state['job_id']));
        echo '</div>';

        echo '<dl class="mds-imagegrid-facts mds-imagegrid-grid-state">';
        self::fact(__('State', 'mds-imagegrid'), $state['status'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Job ID', 'mds-imagegrid'), $state['job_id'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Tileset ID', 'mds-imagegrid'), $state['tileset_id'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Tiles', 'mds-imagegrid'), !empty($state['total_tiles']) ? number_format_i18n((int) $state['total_tiles']) : __('Unknown', 'mds-imagegrid'));
        self::fact(__('Updated', 'mds-imagegrid'), $state['updated_at'] ?? __('Never', 'mds-imagegrid'));
        echo '</dl>';

        if (!empty($state['last_error'])) {
            echo '<p class="mds-imagegrid-error">' . esc_html($state['last_error']) . '</p>';
        }

        echo '</section>';
    }

    /**
     * Validate ImageGrid settings posted through MDS3 core settings.
     *
     * @param \WP_Error $errors Existing errors.
     * @param array     $post   Raw unslashed POST values.
     * @return \WP_Error
     */
    public static function validate_mds3_settings($errors, array $post) {
        if (!is_wp_error($errors)) {
            $errors = new \WP_Error();
        }
        if (!self::mds3_settings_present($post)) {
            return $errors;
        }

        $api_url = isset($post['mds_imagegrid_api_url'])
            ? esc_url_raw(trim((string) $post['mds_imagegrid_api_url']))
            : mds_imagegrid_default_api_url();

        if ('' !== $api_url && !API_Client::is_allowed_url($api_url)) {
            $errors->add('mds_imagegrid_invalid_api_url', __('Enter a valid ImageGrid service URL.', 'mds-imagegrid'));
        }

        return $errors;
    }

    /**
     * Store ImageGrid settings posted through MDS3 core settings.
     *
     * Values are stored in ImageGrid-owned options. Returning $settings
     * unchanged keeps core MDS settings free of extension-private secrets.
     *
     * @param array $settings Current MDS3 settings to save.
     * @param array $post     Raw unslashed POST values.
     * @return array
     */
    public static function sanitize_mds3_settings(array $settings, array $post) {
        if (!self::mds3_settings_present($post)) {
            return $settings;
        }

        update_option('mds_imagegrid_enabled', !empty($post['mds_imagegrid_enabled']));
        update_option('mds_imagegrid_auto_render', !empty($post['mds_imagegrid_auto_render']));
        update_option('mds_imagegrid_api_url', untrailingslashit(esc_url_raw(trim((string) ($post['mds_imagegrid_api_url'] ?? mds_imagegrid_default_api_url())))));
        self::save_rendering_limits($post);

        $submitted_key = trim((string) ($post['mds_imagegrid_api_key'] ?? ''));
        if (!empty($post['mds_imagegrid_clear_api_key'])) {
            self::clear_connection();
        } elseif ('' !== $submitted_key) {
            update_option('mds_imagegrid_api_key', sanitize_text_field($submitted_key), false);
            update_option('mds_imagegrid_last_connection', [
                'status' => 'manual_key_saved',
                'message' => __('API key saved. Test the connection to verify the workspace.', 'mds-imagegrid'),
                'checked_at' => current_time('mysql'),
            ], false);
        }

        return $settings;
    }

    /**
     * Check whether ImageGrid fields were rendered in the MDS3 settings form.
     *
     * @param array $post Raw unslashed POST values.
     * @return bool
     */
    private static function mds3_settings_present(array $post) {
        return !empty($post['mds_imagegrid_settings_present']);
    }

    /**
     * Hook point for future ImageGrid post-save work.
     *
     * @return void
     */
    public static function after_mds3_settings_saved() {
        do_action('mds_imagegrid_settings_saved');
    }

    /**
     * Save settings.
     *
     * @return void
     */
    public static function handle_save_settings() {
        self::verify_admin_action('mds_imagegrid_save_settings');

        $api_url = isset($_POST['mds_imagegrid_api_url'])
            ? esc_url_raw(trim((string) wp_unslash($_POST['mds_imagegrid_api_url'])))
            : mds_imagegrid_default_api_url();

        if ('' !== $api_url && !API_Client::is_allowed_url($api_url)) {
            self::redirect_with_notice('error', __('Enter a valid ImageGrid service URL.', 'mds-imagegrid'));
        }

        update_option('mds_imagegrid_enabled', isset($_POST['mds_imagegrid_enabled']));
        update_option('mds_imagegrid_auto_render', isset($_POST['mds_imagegrid_auto_render']));
        update_option('mds_imagegrid_api_url', untrailingslashit($api_url));
        self::save_rendering_limits(wp_unslash($_POST));

        $submitted_key = isset($_POST['mds_imagegrid_api_key'])
            ? trim((string) wp_unslash($_POST['mds_imagegrid_api_key']))
            : '';

        if (isset($_POST['mds_imagegrid_clear_api_key'])) {
            self::clear_connection();
        } elseif ('' !== $submitted_key) {
            update_option('mds_imagegrid_api_key', sanitize_text_field($submitted_key), false);
            update_option('mds_imagegrid_last_connection', [
                'status' => 'manual_key_saved',
                'message' => __('API key saved. Test the connection to verify the workspace.', 'mds-imagegrid'),
                'checked_at' => current_time('mysql'),
            ], false);
        }

        self::redirect_with_notice('success', __('ImageGrid settings saved.', 'mds-imagegrid'));
    }

    /**
     * Handle the hosted ImageGrid connect return.
     *
     * @return void
     */
    public static function handle_connect_return() {
        self::verify_admin_action('mds_imagegrid_connect_return');

        $token = self::connect_token_from_request();
        if (!$token) {
            self::redirect_with_notice('error', __('ImageGrid did not return a usable connect token.', 'mds-imagegrid'));
        }

        $client = new API_Client(null, '');
        $response = $client->exchange_connect_token($token, self::site_payload());
        if (is_wp_error($response)) {
            self::redirect_with_notice('error', $response->get_error_message());
        }

        $api_key = self::api_key_from_response($response);
        if (!$api_key) {
            self::redirect_with_notice('error', __('ImageGrid did not return a site API key.', 'mds-imagegrid'));
        }

        $api_url = self::api_url_from_response($response);
        if ($api_url) {
            update_option('mds_imagegrid_api_url', $api_url, false);
        }

        $connection = self::connection_from_response($response);
        $account_payload = self::account_payload_from_response($response);

        update_option('mds_imagegrid_enabled', true, false);
        update_option('mds_imagegrid_api_key', $api_key, false);
        update_option('mds_imagegrid_connection', $connection, false);
        update_option('mds_imagegrid_last_account', $account_payload, false);
        update_option('mds_imagegrid_last_connection', [
            'status' => 'connected',
            'message' => __('ImageGrid account connected.', 'mds-imagegrid'),
            'checked_at' => current_time('mysql'),
        ], false);

        self::redirect_with_notice('success', __('ImageGrid account connected.', 'mds-imagegrid'));
    }

    /**
     * Disconnect the local ImageGrid credential and best-effort revoke remotely.
     *
     * @return void
     */
    public static function handle_disconnect() {
        self::verify_admin_action('mds_imagegrid_disconnect');

        $connection = self::connection();
        $has_api_key = '' !== (string) get_option('mds_imagegrid_api_key', '');
        $has_hosted_connection = !empty($connection['connection_id']) && !empty($connection['api_key_id']);
        $remote = true;

        if ($has_api_key && $has_hosted_connection) {
            $remote = (new API_Client())->disconnect_site($connection);
        }

        self::clear_connection();
        update_option('mds_imagegrid_last_connection', [
            'status' => 'disconnected',
            'message' => is_wp_error($remote)
                ? __('Local ImageGrid access was removed. Remote revocation could not be confirmed.', 'mds-imagegrid')
                : __('ImageGrid disconnected.', 'mds-imagegrid'),
            'checked_at' => current_time('mysql'),
        ], false);

        if (is_wp_error($remote)) {
            self::redirect_with_notice('warning', __('Local ImageGrid access was removed. Remote revocation could not be confirmed.', 'mds-imagegrid'));
        }

        self::redirect_with_notice('success', __('ImageGrid disconnected.', 'mds-imagegrid'));
    }

    /**
     * Test the connection.
     *
     * @return void
     */
    public static function handle_test_connection() {
        self::verify_admin_action('mds_imagegrid_test_connection');

        $client = new API_Client();
        $ready = $client->ready();

        if (is_wp_error($ready)) {
            update_option('mds_imagegrid_last_connection', [
                'status' => 'failed',
                'message' => $ready->get_error_message(),
                'checked_at' => current_time('mysql'),
            ], false);

            self::redirect_with_notice('error', $ready->get_error_message());
        }

        $account = $client->account();

        if (is_wp_error($account)) {
            update_option('mds_imagegrid_last_connection', [
                'status' => 'failed',
                'message' => $account->get_error_message(),
                'checked_at' => current_time('mysql'),
            ], false);

            self::redirect_with_notice('error', $account->get_error_message());
        }

        update_option('mds_imagegrid_last_account', $account, false);
        update_option('mds_imagegrid_last_connection', [
            'status' => 'connected',
            'message' => __('Connection verified.', 'mds-imagegrid'),
            'checked_at' => current_time('mysql'),
        ], false);

        self::redirect_with_notice('success', __('ImageGrid connection verified.', 'mds-imagegrid'));
    }

    /**
     * Submit a grid render job.
     *
     * @return void
     */
    public static function handle_submit_grid_render() {
        self::verify_admin_action('mds_imagegrid_submit_grid_render');

        $grid_id = absint($_POST['grid_id'] ?? 0);

        if (!$grid_id) {
            self::redirect_with_notice('error', __('Choose a grid to render.', 'mds-imagegrid'));
        }

        $response = Main::submit_grid_render($grid_id);

        if (is_wp_error($response)) {
            self::redirect_with_notice('error', $response->get_error_message(), $grid_id);
        }

        self::redirect_with_notice('success', __('ImageGrid render job submitted.', 'mds-imagegrid'), $grid_id);
    }

    /**
     * Refresh a grid job.
     *
     * @return void
     */
    public static function handle_refresh_job() {
        self::verify_admin_action('mds_imagegrid_refresh_job');

        $grid_id = absint($_POST['grid_id'] ?? 0);

        if (!$grid_id) {
            self::redirect_with_notice('error', __('Choose a grid to refresh.', 'mds-imagegrid'));
        }

        $response = Main::refresh_grid_job($grid_id);

        if (is_wp_error($response)) {
            self::redirect_with_notice('error', $response->get_error_message(), $grid_id);
        }

        $status = sanitize_text_field((string) ($response['status'] ?? 'updated'));
        self::redirect_with_notice(
            'success',
            sprintf(__('ImageGrid job refreshed. Current status: %s.', 'mds-imagegrid'), $status),
            $grid_id
        );
    }

    /**
     * Resolve grids for the admin selector.
     *
     * @return array
     */
    private static function get_grids() {
        if (class_exists('\\MDS3\\Grid\\GridRepository')) {
            $repo = new \MDS3\Grid\GridRepository();

            return $repo->all();
        }

        if (!class_exists('\\MDS\\Core\\Grid')) {
            return [];
        }

        $grid_model = new \MDS\Core\Grid();
        $grids = $grid_model->get_all();

        return is_array($grids) ? $grids : [];
    }

    /**
     * Resolve a grid ID across supported MDS versions.
     *
     * @param mixed $grid Grid object.
     * @return int
     */
    public static function grid_id($grid) {
        if (!is_object($grid)) {
            return 0;
        }

        if (method_exists($grid, 'id')) {
            return absint($grid->id());
        }

        if (method_exists($grid, 'get_id')) {
            return absint($grid->get_id());
        }

        return 0;
    }

    /**
     * Resolve a grid title across supported MDS versions.
     *
     * @param mixed $grid Grid object.
     * @return string
     */
    public static function grid_title($grid) {
        if (!is_object($grid)) {
            return '';
        }

        if (method_exists($grid, 'get')) {
            return sanitize_text_field((string) $grid->get('title', ''));
        }

        if (method_exists($grid, 'get_title')) {
            return sanitize_text_field((string) $grid->get_title());
        }

        return '';
    }

    /**
     * Render a grid action form.
     *
     * @param string $action   Admin post action.
     * @param string $nonce    Nonce action.
     * @param int    $grid_id  Grid ID.
     * @param string $label    Button label.
     * @param string $class    Button CSS classes.
     * @param bool   $disabled Whether the button is disabled.
     * @return void
     */
    private static function grid_action_form($action, $nonce, $grid_id, $label, $class, $disabled) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field($nonce);
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
        echo '<input type="hidden" name="grid_id" value="' . esc_attr(absint($grid_id)) . '">';
        echo '<input type="hidden" name="redirect_to" value="grid">';
        echo '<button type="submit" class="' . esc_attr($class) . '" ' . disabled($disabled, true, false) . '>' . esc_html($label) . '</button>';
        echo '</form>';
    }

    /**
     * Render a facts-list item.
     *
     * @param string $label Label.
     * @param string $value Value.
     * @return void
     */
    private static function fact($label, $value) {
        echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html((string) $value) . '</dd></div>';
    }

    /**
     * Render an MDS3-compatible checkbox field.
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param bool   $checked     Checked state.
     * @param string $description Help text.
     * @return void
     */
    private static function checkbox_field($name, $label, $checked, $description = '') {
        $field_id = sanitize_key((string) $name);
        echo '<p class="mds3-field"><label for="' . esc_attr($field_id) . '"><span class="mds3-field-label-text">' . esc_html($label) . '</span></label>';
        echo '<label class="mds3-checkbox-label"><input id="' . esc_attr($field_id) . '" type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . ' /> ' . esc_html($description) . '</label></p>';
    }

    /**
     * Render an MDS3-compatible input field.
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $type        Input type.
     * @param string $value       Input value.
     * @param string $description Help text.
     * @param string $placeholder Placeholder text.
     * @return void
     */
    private static function input_field($name, $label, $type, $value, $description = '', $placeholder = '') {
        echo '<p class="mds3-field"><label for="' . esc_attr($name) . '"><span class="mds3-field-label-text">' . esc_html($label) . '</span></label>';
        echo '<input id="' . esc_attr($name) . '" type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" autocomplete="' . esc_attr('password' === $type ? 'new-password' : 'off') . '" />';
        if ($description) {
            echo '<span class="description">' . esc_html($description) . '</span>';
        }
        echo '</p>';
    }

    /**
     * Persist numeric rendering limits from a settings form.
     *
     * @param array $post Raw unslashed POST values.
     * @return void
     */
    private static function save_rendering_limits(array $post) {
        $limits = [
            'mds_imagegrid_local_render_threshold_megapixels' => 25,
            'mds_imagegrid_quota_grid_megapixels' => 100,
            'mds_imagegrid_quota_source_megapixels' => 250,
            'mds_imagegrid_quota_tile_estimate' => 12000,
            'mds_imagegrid_quota_storage_bytes' => 2147483648,
            'mds_imagegrid_quota_processing_credits' => 5000,
        ];

        foreach ($limits as $key => $default) {
            update_option($key, self::number_from_post($post, $key, $default), false);
        }
    }

    /**
     * Read a numeric option with a safe fallback.
     *
     * @param string $key     Option key.
     * @param float  $default Default value.
     * @return float
     */
    private static function number_option($key, $default) {
        return self::positive_number(get_option($key, $default), $default);
    }

    /**
     * Read a numeric POST value with a safe fallback.
     *
     * @param array  $post    Raw unslashed POST values.
     * @param string $key     Field key.
     * @param float  $default Default value.
     * @return float
     */
    private static function number_from_post(array $post, $key, $default) {
        return self::positive_number($post[$key] ?? $default, $default);
    }

    /**
     * Sanitize a positive numeric value.
     *
     * @param mixed $value   Raw value.
     * @param float $default Default value.
     * @return float
     */
    private static function positive_number($value, $default) {
        if (!is_numeric($value)) {
            return (float) $default;
        }

        return max(0, (float) $value);
    }

    /**
     * Get the customer-facing ImageGrid app URL.
     *
     * @return string
     */
    private static function get_app_url() {
        return function_exists(__NAMESPACE__ . '\\mds_imagegrid_app_url')
            ? mds_imagegrid_app_url()
            : 'https://imagegrid.dev';
    }

    /**
     * Build the hosted ImageGrid connect URL.
     *
     * @return string
     */
    private static function connect_url() {
        $app_url = self::get_app_url();
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

        return add_query_arg(
            [
                'return_url' => rawurlencode($return_url),
                'site_url' => home_url('/'),
                'site_name' => get_bloginfo('name'),
                'plugin' => 'mds-imagegrid',
            ],
            $app_url . '/app/integrations/mds/connect'
        );
    }

    /**
     * Return stored connection metadata.
     *
     * @return array
     */
    private static function connection() {
        $connection = get_option('mds_imagegrid_connection', []);

        return is_array($connection) ? $connection : [];
    }

    /**
     * Site metadata for hosted connection exchange.
     *
     * @return array
     */
    private static function site_payload() {
        return [
            'site_url' => home_url('/'),
            'admin_url' => admin_url(),
            'site_name' => get_bloginfo('name'),
            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : '',
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => MDS_IMAGEGRID_VERSION,
            'mds_version' => defined('MDS3_VERSION') ? MDS3_VERSION : '',
        ];
    }

    /**
     * Pull the connect token from known return parameter names.
     *
     * @return string
     */
    private static function connect_token_from_request() {
        foreach (['connect_token', 'token', 'imagegrid_connect_token'] as $key) {
            if (empty($_REQUEST[$key]) || !is_scalar($_REQUEST[$key])) {
                continue;
            }

            $token = trim((string) wp_unslash($_REQUEST[$key]));
            $token = preg_replace('/[^A-Za-z0-9._~:-]/', '', $token);
            if (strlen((string) $token) >= 12 && strlen((string) $token) <= 512) {
                return (string) $token;
            }
        }

        return '';
    }

    /**
     * Extract an API key from an exchange response.
     *
     * @param array $response Exchange response.
     * @return string
     */
    private static function api_key_from_response(array $response) {
        $candidates = [
            $response['api_key'] ?? '',
            $response['apiKey'] ?? '',
            $response['key'] ?? '',
            $response['credential']['api_key'] ?? '',
            $response['credential']['apiKey'] ?? '',
            $response['data']['api_key'] ?? '',
            $response['data']['apiKey'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $key = trim((string) $candidate);
            $key = preg_replace('/[\x00-\x1F\x7F\s]+/', '', $key);
            if (strlen((string) $key) >= 12 && strlen((string) $key) <= 512) {
                return sanitize_text_field((string) $key);
            }
        }

        return '';
    }

    /**
     * Extract an optional API URL from an exchange response.
     *
     * @param array $response Exchange response.
     * @return string
     */
    private static function api_url_from_response(array $response) {
        $url = (string) ($response['api_url'] ?? $response['apiUrl'] ?? $response['data']['api_url'] ?? '');
        $url = untrailingslashit(esc_url_raw($url));

        return $url && API_Client::is_allowed_url($url) ? $url : '';
    }

    /**
     * Build sanitized connection metadata from an exchange response.
     *
     * @param array $response Exchange response.
     * @return array
     */
    private static function connection_from_response(array $response) {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $connection = is_array($response['connection'] ?? null) ? $response['connection'] : (is_array($data['connection'] ?? null) ? $data['connection'] : []);
        $account = is_array($response['account'] ?? null) ? $response['account'] : (is_array($response['data']['account'] ?? null) ? $response['data']['account'] : []);
        $api_key = is_array($response['api_key_record'] ?? null) ? $response['api_key_record'] : (is_array($data['api_key_record'] ?? null) ? $data['api_key_record'] : (is_array($response['api_key'] ?? null) ? $response['api_key'] : []));

        return array_filter([
            'connection_id' => sanitize_text_field((string) ($connection['id'] ?? $response['connection_id'] ?? '')),
            'account_id' => sanitize_text_field((string) ($account['id'] ?? $connection['account_id'] ?? '')),
            'account_slug' => sanitize_key((string) ($account['slug'] ?? $connection['account_slug'] ?? '')),
            'api_key_id' => sanitize_text_field((string) ($api_key['id'] ?? $connection['api_key_id'] ?? '')),
            'site_url' => esc_url_raw(home_url('/')),
            'connected_at' => current_time('mysql'),
        ]);
    }

    /**
     * Build sanitized account status payload from an exchange response.
     *
     * @param array $response Exchange response.
     * @return array
     */
    private static function account_payload_from_response(array $response) {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $account = is_array($response['account'] ?? null) ? $response['account'] : (is_array($data['account'] ?? null) ? $data['account'] : []);
        $api_key = is_array($response['api_key_record'] ?? null) ? $response['api_key_record'] : (is_array($data['api_key_record'] ?? null) ? $data['api_key_record'] : (is_array($response['credential'] ?? null) ? $response['credential'] : []));

        return [
            'account' => [
                'id' => sanitize_text_field((string) ($account['id'] ?? '')),
                'slug' => sanitize_key((string) ($account['slug'] ?? '')),
                'display_name' => sanitize_text_field((string) ($account['display_name'] ?? $account['name'] ?? '')),
                'status' => sanitize_key((string) ($account['status'] ?? 'active')),
            ],
            'api_key' => [
                'id' => sanitize_text_field((string) ($api_key['id'] ?? '')),
                'name' => sanitize_text_field((string) ($api_key['name'] ?? $api_key['display_name'] ?? __('Connected key', 'mds-imagegrid'))),
                'role_code' => sanitize_key((string) ($api_key['role_code'] ?? $api_key['role'] ?? 'api_runtime')),
            ],
        ];
    }

    /**
     * Clear local credentials and account metadata.
     *
     * @return void
     */
    private static function clear_connection() {
        update_option('mds_imagegrid_api_key', '', false);
        update_option('mds_imagegrid_connection', [], false);
        update_option('mds_imagegrid_last_account', [], false);
    }

    /**
     * Verify an admin POST action.
     *
     * @param string $action Nonce action.
     * @return void
     */
    private static function verify_admin_action($action) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage ImageGrid settings.', 'mds-imagegrid'));
        }

        check_admin_referer($action);
    }

    /**
     * Redirect back to the settings screen with a notice.
     *
     * @param string $type    Notice type.
     * @param string $message Notice message.
     * @param int    $grid_id Optional selected grid ID.
     * @return void
     */
    private static function redirect_with_notice($type, $message, $grid_id = 0) {
        $url = add_query_arg(
            [
                'page' => self::settings_page_slug(),
                'mds_imagegrid_notice_type' => sanitize_key($type),
                'mds_imagegrid_notice' => (string) $message,
            ],
            admin_url('admin.php')
        );

        if ($grid_id) {
            $url = add_query_arg('grid_id', absint($grid_id), $url);
        }

        $redirect_to = isset($_POST['redirect_to']) ? sanitize_key((string) wp_unslash($_POST['redirect_to'])) : '';
        if ('grid' === $redirect_to && defined('MDS3_VERSION') && $grid_id) {
            $url = add_query_arg(
                [
                    'page' => 'mds3-grids',
                    'grid_id' => absint($grid_id),
                    'tab' => 'grid-imagegrid',
                    'mds_imagegrid_notice_type' => sanitize_key($type),
                    'mds_imagegrid_notice' => (string) $message,
                ],
                admin_url('admin.php')
            );
        }

        wp_safe_redirect($url);
        exit;
    }

    /**
     * Get notice data from the current request.
     *
     * @return array|null
     */
    private static function get_notice() {
        if (empty($_GET['mds_imagegrid_notice'])) {
            return null;
        }

        return [
            'type' => sanitize_key((string) ($_GET['mds_imagegrid_notice_type'] ?? 'info')),
            'message' => sanitize_text_field((string) wp_unslash($_GET['mds_imagegrid_notice'])),
        ];
    }

    /**
     * Map stored notice types to WordPress notice classes.
     *
     * @param string $type Notice type.
     * @return string
     */
    public static function notice_class($type) {
        $type = sanitize_key((string) $type);

        return in_array($type, ['error', 'warning', 'success', 'info'], true) ? $type : 'info';
    }

    /**
     * Resolve asset version.
     *
     * @param string $relative_path Relative path.
     * @return string
     */
    private static function asset_version($relative_path) {
        $path = MDS_IMAGEGRID_PATH . ltrim($relative_path, '/\\');

        return file_exists($path) ? (string) filemtime($path) : MDS_IMAGEGRID_VERSION;
    }

    /**
     * Resolve the extension settings page slug for the active MDS version.
     *
     * @return string
     */
    private static function settings_page_slug() {
        return defined('MDS3_VERSION') ? 'mds3-imagegrid' : 'mds-imagegrid';
    }
}
