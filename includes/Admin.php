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
    use Concerns\RendersImageGridAdminPanels;
    use Concerns\ValidatesImageGridSettings;
    use Concerns\HandlesImageGridAdminActions;
    use Concerns\SupportsImageGridAdmin;

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
     * Add ImageGrid to the internal MDS3 manage menu without crowding the WordPress sidebar.
     *
     * @param array $groups Existing dashboard menu groups.
     * @return array
     */
    public static function register_mds3_dashboard_menu(array $groups) {
        if (!defined('MDS3_VERSION')) {
            return $groups;
        }

        if (!isset($groups['manage']) || !is_array($groups['manage'])) {
            $groups['manage'] = [
                'label' => __('Manage', 'mds-imagegrid'),
                'items' => [],
            ];
        }

        if (empty($groups['manage']['items']) || !is_array($groups['manage']['items'])) {
            $groups['manage']['items'] = [];
        }

        $groups['manage']['items'][] = [
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
     * Add ImageGrid to the MDS3 grid edit tabs.
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public static function register_mds3_grid_tab(array $tabs) {
        $tabs['grid-imagegrid'] = __('ImageGrid', 'mds-imagegrid');

        return $tabs;
    }
}
