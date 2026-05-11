<?php
/**
 * ImageGrid admin forms, connection payloads, parsing, and asset helpers.
 *
 * @package MDS\Extensions\ImageGrid
 */

namespace MDS\Extensions\ImageGrid\Concerns;

use MDS\Extensions\ImageGrid\API_Client;
use function MDS\Extensions\ImageGrid\mds_imagegrid_app_url;

if (!defined('ABSPATH')) {
    exit;
}

trait SupportsImageGridAdmin {

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
     * Get the customer-facing ImageGrid app URL.
     *
     * @return string
     */
    private static function get_app_url() {
        return function_exists('MDS\\Extensions\\ImageGrid\\mds_imagegrid_app_url')
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
