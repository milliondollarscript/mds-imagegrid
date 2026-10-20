<?php
/**
 * ImageGrid admin POST handlers, notices, and redirect helpers.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid\Concerns;

use MillionDollarScript\Extensions\ImageGrid\API_Client;
use MillionDollarScript\Extensions\ImageGrid\Main;
use function MillionDollarScript\Extensions\ImageGrid\mds_imagegrid_default_api_url;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesImageGridAdminActions {

    /**
     * Save settings.
     *
     * @return void
     */
    public static function handle_save_settings() {
        self::verify_admin_action('mds_imagegrid_save_settings');

        $section = sanitize_key((string) ($_POST['section'] ?? 'all'));
        if (!in_array($section, ['all', 'connection', 'rendering', 'limits'], true)) {
            $section = 'all';
        }

        if (in_array($section, ['all', 'connection'], true)) {
            $raw_api_url = isset($_POST['mds_imagegrid_api_url'])
                ? (string) wp_unslash($_POST['mds_imagegrid_api_url'])
                : mds_imagegrid_default_api_url();
            $api_url = API_Client::sanitize_service_url($raw_api_url);

            if (API_Client::has_invalid_service_url_submission($raw_api_url, $api_url)) {
                self::redirect_with_notice('error', __('Enter a valid ImageGrid service URL.', 'mds-imagegrid'));
            }

            update_option('mds_imagegrid_api_url', untrailingslashit($api_url));

            $submitted_key = isset($_POST['mds_imagegrid_api_key'])
                ? trim((string) wp_unslash($_POST['mds_imagegrid_api_key']))
                : '';

            if (isset($_POST['mds_imagegrid_clear_api_key'])) {
                self::clear_connection();
            } elseif ('' !== $submitted_key) {
                update_option('mds_imagegrid_api_key', sanitize_text_field($submitted_key), false);
                update_option('mds_imagegrid_last_connection', [
                    'status' => 'manual_key_saved',
                    'message' => __('API key saved. Test the connection to verify the account.', 'mds-imagegrid'),
                    'checked_at' => current_time('mysql'),
                ], false);
            }
        }

        if (in_array($section, ['all', 'rendering'], true)) {
            $wants_enabled = isset($_POST['mds_imagegrid_enabled']);
            // Switching ImageGrid on without a key used to report "settings saved" while the panel
            // still read "Not connected", so the tick looked like it worked and did nothing. A key
            // typed in this same submission counts: the connection section saves first.
            $has_key = '' !== (string) get_option('mds_imagegrid_api_key', '')
                || '' !== trim((string) ($_POST['mds_imagegrid_api_key'] ?? ''));
            if ($wants_enabled && !$has_key) {
                self::redirect_with_notice('error', __('ImageGrid needs an API key before it can be switched on.', 'mds-imagegrid'));
            }

            update_option('mds_imagegrid_enabled', $wants_enabled);
            update_option('mds_imagegrid_auto_render', isset($_POST['mds_imagegrid_auto_render']));
            update_option('mds_imagegrid_failure_email_enabled', isset($_POST['mds_imagegrid_failure_email_enabled']), false);
            update_option('mds_imagegrid_auto_render_preference', 'explicit', false);
            update_option(
                'mds_imagegrid_local_render_threshold_megapixels',
                self::number_from_post(wp_unslash($_POST), 'mds_imagegrid_local_render_threshold_megapixels', 25),
                false
            );
        }

        if (in_array($section, ['all', 'limits'], true)) {
            self::save_rendering_limits(wp_unslash($_POST));
        }

        self::redirect_with_notice('success', __('ImageGrid settings saved.', 'mds-imagegrid'));
    }

    /**
     * Reset local submission safeguards to authoritative account plan limits.
     *
     * @return void
     */
    public static function handle_reset_plan_limits() {
        self::verify_admin_action('mds_imagegrid_reset_plan_limits');

        $last_account = get_option('mds_imagegrid_last_account', []);
        $limits = self::plan_limit_options(is_array($last_account) ? $last_account : []);
        if (empty($limits)) {
            self::redirect_with_notice('error', __('ImageGrid has not supplied plan limits for this account. Refresh the connection or review the plan in ImageGrid.', 'mds-imagegrid'));
        }

        foreach ($limits as $option_name => $value) {
            update_option($option_name, $value, false);
        }

        self::redirect_with_notice('success', __('Matching local safeguards reset to the current ImageGrid plan limits.', 'mds-imagegrid'));
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

        // The exchange response intentionally stays small. Refresh the current
        // account endpoint with the new key so entitlement status is available
        // immediately without requiring a second manual connection test.
        $account_client = new API_Client($api_url ?: null, $api_key);
        $current_account = $account_client->account();
        if (!is_wp_error($current_account)) {
            $account_payload = self::account_payload_from_response($current_account);
        }

        update_option('mds_imagegrid_enabled', true, false);
        update_option('mds_imagegrid_api_key', $api_key, false);
        update_option('mds_imagegrid_connection', $connection, false);
        update_option('mds_imagegrid_last_account', $account_payload, false);
        update_option('mds_imagegrid_last_connection', [
            'status' => 'connected',
            'message' => __('ImageGrid account connected.', 'mds-imagegrid'),
            'checked_at' => current_time('mysql'),
        ], false);

        $automatic_enabled = Main::apply_connected_defaults();

        self::redirect_with_notice(
            'success',
            $automatic_enabled
                ? __('ImageGrid account connected. Automatic rendering is enabled for eligible grids.', 'mds-imagegrid')
                : __('ImageGrid account connected.', 'mds-imagegrid')
        );
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
            $message = self::friendly_error_message($ready->get_error_message());
            update_option('mds_imagegrid_last_connection', [
                'status' => 'failed',
                'message' => $message,
                'checked_at' => current_time('mysql'),
            ], false);

            self::redirect_with_notice('error', $message);
        }

        $account = $client->account();

        if (is_wp_error($account)) {
            $message = self::friendly_error_message($account->get_error_message());
            update_option('mds_imagegrid_last_connection', [
                'status' => self::message_requires_account_action($message) ? 'action_required' : 'failed',
                'message' => $message,
                'checked_at' => current_time('mysql'),
            ], false);

            self::redirect_with_notice('error', $message);
        }

        update_option('mds_imagegrid_last_account', self::account_payload_from_response($account), false);
        update_option('mds_imagegrid_last_connection', [
            'status' => 'connected',
            'message' => __('Connection verified.', 'mds-imagegrid'),
            'checked_at' => current_time('mysql'),
        ], false);

        $automatic_enabled = Main::apply_connected_defaults();

        self::redirect_with_notice(
            'success',
            $automatic_enabled
                ? __('ImageGrid connection verified. Automatic rendering is enabled for eligible grids.', 'mds-imagegrid')
                : __('ImageGrid connection verified.', 'mds-imagegrid')
        );
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
            $message = self::friendly_error_message($response->get_error_message());
            if (self::message_requires_authorization($message) || self::message_requires_account_action($message)) {
                update_option('mds_imagegrid_last_connection', [
                    'status' => self::message_requires_account_action($message) ? 'action_required' : 'failed',
                    'message' => $message,
                    'checked_at' => current_time('mysql'),
                ], false);
            }
            self::redirect_with_notice('error', $message, $grid_id);
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

        $section = sanitize_key((string) ($_POST['section'] ?? ''));
        if (in_array($section, ['connection', 'account', 'rendering', 'limits'], true)) {
            $url = add_query_arg('section', $section, $url);
        }

        $redirect_to = isset($_POST['redirect_to']) ? sanitize_key((string) wp_unslash($_POST['redirect_to'])) : '';
        if ('grid' === $redirect_to && class_exists('\MillionDollarScript\Core\Runtime') && $grid_id) {
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
}
