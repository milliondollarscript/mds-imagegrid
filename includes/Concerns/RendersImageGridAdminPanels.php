<?php
/**
 * ImageGrid settings pages and MDS3 embedded grid/settings panels.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid\Concerns;

use MillionDollarScript\Extensions\ImageGrid\Main;
use function MillionDollarScript\Extensions\ImageGrid\mds_imagegrid_default_api_url;

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
        $failure_email_enabled = (bool) get_option('mds_imagegrid_failure_email_enabled', false);
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
        $last_account = is_array($last_account) ? $last_account : [];
        $last_connection = is_array($last_connection) ? $last_connection : [];
        $connection_status = sanitize_key((string) ($last_connection['status'] ?? 'not_connected'));
        $cdn_known = !empty($last_account) && 'connected' === $connection_status;
        $cdn_access = $cdn_known && Main::has_cdn_delivery_access($last_account);
        $connection = self::connection();
        $connect_url = self::connect_url();
        $grids = self::get_grids();
        $selected_grid_id = isset($_GET['grid_id']) ? absint(wp_unslash($_GET['grid_id'])) : 0;
        $app_url = self::get_app_url();
        $page_slug = self::settings_page_slug();
        $active_section = sanitize_key((string) ($_GET['section'] ?? 'connection'));
        if (!in_array($active_section, ['connection', 'account', 'rendering', 'limits'], true)) {
            $active_section = 'connection';
        }
        $section_urls = [];
        foreach (['connection', 'account', 'rendering', 'limits'] as $section) {
            $section_urls[$section] = add_query_arg(
                ['page' => $page_slug, 'section' => $section],
                admin_url('admin.php')
            );
        }
        $account_summary = self::account_summary($last_account, $last_connection);
        $plan_limit_options = self::plan_limit_options($last_account);

        if (!$selected_grid_id && !empty($grids)) {
            $first_grid = reset($grids);
            $selected_grid_id = self::grid_id($first_grid);
        }

        $selected_state = $selected_grid_id ? Main::get_grid_state($selected_grid_id) : [];
        $notice = self::get_notice();
        if ($notice) {
            $notice['message'] = self::friendly_error_message((string) ($notice['message'] ?? ''));
        }
        $connection_message = self::friendly_error_message((string) ($last_connection['message'] ?? ''));
        $render_state = self::grid_render_action_state(
            $selected_grid_id,
            $enabled,
            $has_api_key,
            $connection_status,
            $connection_message,
            $selected_state,
            $notice
        );
        $render_disabled = $render_state['disabled'];
        $render_disabled_reason = $render_state['reason'];
        $authorization_required = $render_state['authorization_required'];
        $selected_last_error = self::friendly_error_message((string) ($selected_state['last_error'] ?? ''));

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
        $failure_email_enabled = (bool) get_option('mds_imagegrid_failure_email_enabled', false);
        $api_url = (string) get_option('mds_imagegrid_api_url', mds_imagegrid_default_api_url());
        $has_api_key = '' !== (string) get_option('mds_imagegrid_api_key', '');
        $threshold = self::number_option('mds_imagegrid_local_render_threshold_megapixels', 25);
        $quota_grid = self::number_option('mds_imagegrid_quota_grid_megapixels', 100);
        $quota_source = self::number_option('mds_imagegrid_quota_source_megapixels', 250);
        $quota_tiles = self::number_option('mds_imagegrid_quota_tile_estimate', 12000);
        $quota_storage = self::number_option('mds_imagegrid_quota_storage_bytes', 2147483648);
        $quota_credits = self::number_option('mds_imagegrid_quota_processing_credits', 5000);
        $last_connection = get_option('mds_imagegrid_last_connection', []);
        $last_account = get_option('mds_imagegrid_last_account', []);
        $last_account = is_array($last_account) ? $last_account : [];
        $connection_status = sanitize_key((string) (is_array($last_connection) ? ($last_connection['status'] ?? 'not_connected') : 'not_connected'));
        $cdn_known = !empty($last_account) && 'connected' === $connection_status;
        $cdn_access = $cdn_known && Main::has_cdn_delivery_access($last_account);
        $app_url = self::get_app_url();
        $connect_url = self::connect_url();
        $connection = self::connection();
        $has_connection = $has_api_key || !empty($connection);

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
        $last_account = get_option('mds_imagegrid_last_account', []);
        $last_connection = get_option('mds_imagegrid_last_connection', []);
        $last_account = is_array($last_account) ? $last_account : [];
        $last_connection = is_array($last_connection) ? $last_connection : [];
        $connection_status = sanitize_key((string) ($last_connection['status'] ?? 'not_connected'));
        $connection_message = self::friendly_error_message((string) ($last_connection['message'] ?? ''));
        $cdn_known = !empty($last_account) && 'connected' === $connection_status;
        $cdn_access = $cdn_known && Main::has_cdn_delivery_access($last_account);
        $state = $grid_id ? Main::get_grid_state($grid_id) : [];
        $last_error = self::friendly_error_message((string) ($state['last_error'] ?? ''));
        $notice = self::get_notice();
        if ($notice) {
            $notice['message'] = self::friendly_error_message((string) ($notice['message'] ?? ''));
        }
        $settings_url = admin_url('admin.php?page=' . self::settings_page_slug());
        $render_state = self::grid_render_action_state(
            $grid_id,
            $enabled,
            $has_api_key,
            $connection_status,
            $connection_message,
            $state,
            $notice
        );
        $authorization_required = $render_state['authorization_required'];
        $render_disabled = $render_state['disabled'];
        $render_disabled_reason = $render_state['reason'];
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
        if (!class_exists('\\MillionDollarScript\\Core\\Grids')) {
            return [];
        }

        return \MillionDollarScript\Core\Grids::all();
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
     * Determine whether a grid render action can be submitted.
     *
     * @param int        $grid_id            Grid ID.
     * @param bool       $enabled            ImageGrid enabled flag.
     * @param bool       $has_api_key        Whether an API key is stored.
     * @param string     $connection_status  Last connection status.
     * @param string     $connection_message Last connection message.
     * @param array      $state              Stored grid render state.
     * @param array|null $notice             Current admin notice.
     * @return array{disabled: bool, reason: string, authorization_required: bool, account_action_required: bool}
     */
    private static function grid_render_action_state($grid_id, $enabled, $has_api_key, $connection_status, $connection_message, array $state, $notice = null) {
        $notice_message = is_array($notice) ? (string) ($notice['message'] ?? '') : '';
        $authorization_required = $has_api_key
            && 'connected' !== sanitize_key((string) $connection_status)
            && (
                self::message_requires_authorization((string) $connection_message)
                || self::message_requires_authorization((string) ($state['last_error'] ?? ''))
                || self::message_requires_authorization($notice_message)
            );
        $account_action_required = $has_api_key
            && 'connected' !== sanitize_key((string) $connection_status)
            && (
                self::message_requires_account_action((string) $connection_message)
                || self::message_requires_account_action((string) ($state['last_error'] ?? ''))
                || self::message_requires_account_action($notice_message)
            );

        $reason = '';
        if (!$grid_id) {
            $reason = __('Choose a grid before submitting a render job.', 'mds-imagegrid');
        } elseif (!$enabled) {
            $reason = __('Enable ImageGrid before submitting render jobs.', 'mds-imagegrid');
        } elseif (!$has_api_key) {
            $reason = __('Connect or save an ImageGrid API key before submitting render jobs.', 'mds-imagegrid');
        } elseif ($authorization_required) {
            $reason = __('ImageGrid authorization is required. Connect or verify your ImageGrid API key before rendering this grid.', 'mds-imagegrid');
        } elseif ($account_action_required) {
            $reason = self::friendly_error_message($connection_message ?: ((string) ($state['last_error'] ?? $notice_message)));
        }

        return [
            'disabled' => '' !== $reason,
            'reason' => $reason,
            'authorization_required' => $authorization_required,
            'account_action_required' => $account_action_required,
        ];
    }
}
