<?php
/**
 * ImageGrid admin UI.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid;

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
        add_action('million-dollar-script/admin/menu', [__CLASS__, 'register_mds3_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('million-dollar-script/dashboard/extension/cards', [__CLASS__, 'register_mds3_dashboard_card']);
        add_action('million-dollar-script/admin/settings/group/rendering', [__CLASS__, 'render_mds3_settings_panel']);
        add_filter('million-dollar-script/admin/grid/tabs', [__CLASS__, 'register_mds3_grid_tab'], 10, 2);
        add_action('million-dollar-script/admin/grid/tab/panels', [__CLASS__, 'render_mds3_grid_panel'], 10, 2);
        add_filter('million-dollar-script/admin/validate/settings', [__CLASS__, 'validate_mds3_settings'], 10, 3);
        add_filter('million-dollar-script/admin/sanitize/settings', [__CLASS__, 'sanitize_mds3_settings'], 10, 3);
        add_action('million-dollar-script/admin/settings/saved', [__CLASS__, 'after_mds3_settings_saved'], 10, 3);
        add_action('admin_post_mds_imagegrid_save_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_mds_imagegrid_test_connection', [__CLASS__, 'handle_test_connection']);
        add_action('admin_post_mds_imagegrid_connect_return', [__CLASS__, 'handle_connect_return']);
        add_action('admin_post_mds_imagegrid_disconnect', [__CLASS__, 'handle_disconnect']);
        add_action('admin_post_mds_imagegrid_reset_plan_limits', [__CLASS__, 'handle_reset_plan_limits']);
        add_action('admin_post_mds_imagegrid_submit_grid_render', [__CLASS__, 'handle_submit_grid_render']);
        add_action('admin_post_mds_imagegrid_refresh_job', [__CLASS__, 'handle_refresh_job']);
    }

    /**
     * Register submenu.
     *
     * @return void
     */
    public static function register_menu() {
        if (class_exists('\MillionDollarScript\Core\Runtime')) {
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
        if (!class_exists('\MillionDollarScript\Core\Runtime')) {
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
     * Add ImageGrid quick actions to the dashboard Extensions section.
     *
     * @param array $cards Dashboard extension cards keyed by slug.
     * @return array
     */
    public static function register_mds3_dashboard_card(array $cards) {
        if (!class_exists('\MillionDollarScript\Core\Runtime')) {
            return $cards;
        }

        $slug = 'mds-imagegrid';
        $card = isset($cards[$slug]) && is_array($cards[$slug]) ? $cards[$slug] : [
            'slug' => $slug,
            'name' => __('ImageGrid Rendering', 'mds-imagegrid'),
            'description' => __('Render large pixel grids through the hosted ImageGrid service when local rendering is not practical.', 'mds-imagegrid'),
            'active' => true,
            'installed' => true,
            'actions' => [],
        ];

        $card['description'] = __('Render large pixel grids through the hosted ImageGrid service when local rendering is not practical.', 'mds-imagegrid');
        $card['actions'] = [
            [
                'label' => __('Open ImageGrid', 'mds-imagegrid'),
                'url' => admin_url('admin.php?page=mds3-imagegrid'),
                'primary' => true,
                'icon' => 'dashicons-format-image',
            ],
            [
                'label' => __('Docs', 'mds-imagegrid'),
                'url' => add_query_arg([
                    'page' => 'mds3-docs',
                    'package' => 'mds-imagegrid',
                    'doc' => 'usage',
                ], admin_url('admin.php')),
                'icon' => 'dashicons-book',
            ],
            [
                'label' => __('Rendering settings', 'mds-imagegrid'),
                'url' => admin_url('admin.php?page=mds3-settings&tab=rendering'),
                'icon' => 'dashicons-admin-generic',
            ],
        ];
        $cards[$slug] = $card;

        return $cards;
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
