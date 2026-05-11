<?php
/**
 * ImageGrid settings pages and MDS3 embedded grid/settings panels.
 *
 * @package MDS\Extensions\ImageGrid
 */

namespace MDS\Extensions\ImageGrid\Concerns;

use MDS\Extensions\ImageGrid\Main;
use function MDS\Extensions\ImageGrid\mds_imagegrid_default_api_url;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersImageGridAdminPanels {

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

        include MDS_IMAGEGRID_PATH . 'templates/admin/mds3-settings-panel.php';
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

        include MDS_IMAGEGRID_PATH . 'templates/admin/mds3-grid-panel.php';
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
}
