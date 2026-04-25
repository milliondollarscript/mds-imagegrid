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
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('mds3_admin_settings_tabs', [__CLASS__, 'register_mds3_settings_tab']);
        add_action('mds3_admin_settings_panels', [__CLASS__, 'render_mds3_settings_panel']);
        add_filter('mds3_admin_validate_settings', [__CLASS__, 'validate_mds3_settings'], 10, 3);
        add_filter('mds3_admin_sanitize_settings', [__CLASS__, 'sanitize_mds3_settings'], 10, 3);
        add_action('mds3_admin_settings_saved', [__CLASS__, 'after_mds3_settings_saved'], 10, 3);
        add_action('admin_post_mds_imagegrid_save_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_mds_imagegrid_test_connection', [__CLASS__, 'handle_test_connection']);
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
     * Enqueue admin CSS.
     *
     * @param string $hook Current admin hook.
     * @return void
     */
    public static function enqueue_assets($hook) {
        if (!in_array($hook, ['mds-dashboard_page_mds-imagegrid', 'mds_page_mds-imagegrid', 'million-dollar-script_page_mds3-settings'], true)) {
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
        $last_account = get_option('mds_imagegrid_last_account', []);
        $last_connection = get_option('mds_imagegrid_last_connection', []);
        $grids = self::get_grids();
        $selected_grid_id = isset($_GET['grid_id']) ? absint(wp_unslash($_GET['grid_id'])) : 0;
        $app_url = self::get_app_url();

        if (!$selected_grid_id && !empty($grids)) {
            $first_grid = reset($grids);
            $selected_grid_id = is_object($first_grid) && method_exists($first_grid, 'get_id') ? absint($first_grid->get_id()) : 0;
        }

        $selected_state = $selected_grid_id ? Main::get_grid_state($selected_grid_id) : [];
        $notice = self::get_notice();

        include MDS_IMAGEGRID_PATH . 'templates/admin/settings.php';
    }

    /**
     * Add ImageGrid to the MDS3 core Settings tabs.
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public static function register_mds3_settings_tab(array $tabs) {
        $tabs['imagegrid'] = __('ImageGrid', 'mds-imagegrid');

        return $tabs;
    }

    /**
     * Render ImageGrid settings inside the MDS3 core Settings form.
     *
     * @return void
     */
    public static function render_mds3_settings_panel() {
        $enabled = (bool) get_option('mds_imagegrid_enabled', false);
        $auto_render = (bool) get_option('mds_imagegrid_auto_render', false);
        $api_url = (string) get_option('mds_imagegrid_api_url', mds_imagegrid_default_api_url());
        $has_api_key = '' !== (string) get_option('mds_imagegrid_api_key', '');
        $last_connection = get_option('mds_imagegrid_last_connection', []);
        $app_url = self::get_app_url();
        $connection_status = is_array($last_connection) ? ($last_connection['status'] ?? 'not_connected') : 'not_connected';

        echo '<section class="mds3-settings-panel" data-settings-panel="imagegrid"><h2>' . esc_html__('ImageGrid', 'mds-imagegrid') . '</h2>';
        echo '<p>' . esc_html__('Connect MDS zoomable grids to ImageGrid for remote rendering and authenticated tile delivery.', 'mds-imagegrid') . '</p>';
        echo '<p><span class="mds-imagegrid-status mds-imagegrid-status-' . esc_attr($connection_status) . '">' . esc_html('connected' === $connection_status ? __('Connected', 'mds-imagegrid') : __('Not Connected', 'mds-imagegrid')) . '</span></p>';
        echo '<div class="mds3-settings-form">';
        self::checkbox_field('mds_imagegrid_enabled', __('Enable ImageGrid', 'mds-imagegrid'), $enabled, __('Serve ready remote tiles for grids that have an ImageGrid tileset.', 'mds-imagegrid'));
        self::checkbox_field('mds_imagegrid_auto_render', __('Automatic rendering', 'mds-imagegrid'), $auto_render, __('Submit a remote render job when MDS requests tile generation.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_api_url', __('API URL', 'mds-imagegrid'), 'url', $api_url, __('Server API URL. This can point at local Docker during development and is never used for browser account links.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_api_key', __('API key', 'mds-imagegrid'), 'password', '', $has_api_key ? __('Saved key is hidden. Leave blank to keep it.', 'mds-imagegrid') : __('Paste an ImageGrid API key.', 'mds-imagegrid'), $has_api_key ? __('Saved key is hidden', 'mds-imagegrid') : __('Paste an ImageGrid API key', 'mds-imagegrid'));
        if ($has_api_key) {
            self::checkbox_field('mds_imagegrid_clear_api_key', __('Clear saved key', 'mds-imagegrid'), false, __('Remove the saved API key during this settings save.', 'mds-imagegrid'));
        }
        echo '</div><p class="mds3-button-row">';
        if (!empty($app_url)) {
            echo '<a class="button" href="' . esc_url($app_url . '/app/signup') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Create ImageGrid Account', 'mds-imagegrid') . '</a>';
            echo '<a class="button" href="' . esc_url($app_url . '/app/login') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Manage API Keys', 'mds-imagegrid') . '</a>';
        }
        echo '</p></section>';
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

        $api_url = isset($post['mds_imagegrid_api_url'])
            ? esc_url_raw(trim((string) $post['mds_imagegrid_api_url']))
            : mds_imagegrid_default_api_url();

        if (!$api_url || !API_Client::is_allowed_url($api_url)) {
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
        update_option('mds_imagegrid_enabled', !empty($post['mds_imagegrid_enabled']));
        update_option('mds_imagegrid_auto_render', !empty($post['mds_imagegrid_auto_render']));
        update_option('mds_imagegrid_api_url', untrailingslashit(esc_url_raw(trim((string) ($post['mds_imagegrid_api_url'] ?? mds_imagegrid_default_api_url())))));

        $submitted_key = trim((string) ($post['mds_imagegrid_api_key'] ?? ''));
        if (!empty($post['mds_imagegrid_clear_api_key'])) {
            update_option('mds_imagegrid_api_key', '', false);
        } elseif ('' !== $submitted_key) {
            update_option('mds_imagegrid_api_key', sanitize_text_field($submitted_key), false);
        }

        return $settings;
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

        if (!$api_url || !API_Client::is_allowed_url($api_url)) {
            self::redirect_with_notice('error', __('Enter a valid ImageGrid service URL.', 'mds-imagegrid'));
        }

        update_option('mds_imagegrid_enabled', isset($_POST['mds_imagegrid_enabled']));
        update_option('mds_imagegrid_auto_render', isset($_POST['mds_imagegrid_auto_render']));
        update_option('mds_imagegrid_api_url', untrailingslashit($api_url));

        $submitted_key = isset($_POST['mds_imagegrid_api_key'])
            ? trim((string) wp_unslash($_POST['mds_imagegrid_api_key']))
            : '';

        if (isset($_POST['mds_imagegrid_clear_api_key'])) {
            update_option('mds_imagegrid_api_key', '', false);
        } elseif ('' !== $submitted_key) {
            update_option('mds_imagegrid_api_key', sanitize_text_field($submitted_key), false);
        }

        self::redirect_with_notice('success', __('ImageGrid settings saved.', 'mds-imagegrid'));
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
        if (!class_exists('\\MDS\\Core\\Grid')) {
            return [];
        }

        $grid_model = new \MDS\Core\Grid();
        $grids = $grid_model->get_all();

        return is_array($grids) ? $grids : [];
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
        echo '<p class="mds3-field"><label><span class="mds3-field-label-text">' . esc_html($label) . '</span></label>';
        echo '<label class="mds3-checkbox-label"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . ' /> ' . esc_html($description) . '</label></p>';
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
                'page' => 'mds-imagegrid',
                'mds_imagegrid_notice_type' => sanitize_key($type),
                'mds_imagegrid_notice' => rawurlencode((string) $message),
            ],
            admin_url('admin.php')
        );

        if ($grid_id) {
            $url = add_query_arg('grid_id', absint($grid_id), $url);
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
            'message' => sanitize_text_field(rawurldecode((string) wp_unslash($_GET['mds_imagegrid_notice']))),
        ];
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
}
