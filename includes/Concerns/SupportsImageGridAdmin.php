<?php
/**
 * ImageGrid admin forms, connection payloads, parsing, and asset helpers.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid\Concerns;

use MillionDollarScript\Extensions\ImageGrid\API_Client;
use function MillionDollarScript\Extensions\ImageGrid\mds_imagegrid_app_url;
use function MillionDollarScript\Extensions\ImageGrid\mds_imagegrid_default_api_url;

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
     * @param string $disabled_reason Why the action is unavailable.
     * @param string $redirect_to Optional redirect target.
     * @return void
     */
    private static function grid_action_form($action, $nonce, $grid_id, $label, $class, $disabled, $disabled_reason = '', $redirect_to = 'grid') {
        include MDS_IMAGEGRID_PATH . 'templates/admin/partials/grid-action-form.php';
    }

    /**
     * Render a facts-list item.
     *
     * @param string $label Label.
     * @param string $value Value.
     * @return void
     */
    private static function fact($label, $value) {
        include MDS_IMAGEGRID_PATH . 'templates/admin/partials/fact.php';
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
    private static function checkbox_field($name, $label, $checked, $description = '', $help = '') {
        $field_id = sanitize_key((string) $name);
        include MDS_IMAGEGRID_PATH . 'templates/admin/partials/checkbox-field.php';
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
    private static function input_field($name, $label, $type, $value, $description = '', $placeholder = '', $help = '') {
        include MDS_IMAGEGRID_PATH . 'templates/admin/partials/input-field.php';
    }

    /**
     * Get the customer-facing ImageGrid app URL.
     *
     * @return string
     */
    private static function get_app_url() {
        return function_exists('MillionDollarScript\\Extensions\\ImageGrid\\mds_imagegrid_app_url')
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
                'api_url' => (string) get_option('mds_imagegrid_api_url', mds_imagegrid_default_api_url()),
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
     * Extract plan limits that map to local ImageGrid safeguards.
     *
     * @param array $payload Stored account payload.
     * @return array<string, float>
     */
    private static function plan_limit_options(array $payload) {
        $effective_limits = is_array($payload['effective_limits'] ?? null) ? $payload['effective_limits'] : [];
        $quotas = is_array($payload['quotas'] ?? null) ? $payload['quotas'] : [];
        $mapping = [
            'mds_imagegrid_quota_grid_megapixels' => ['grid_megapixels', 'max_grid_render_megapixels'],
            'mds_imagegrid_quota_tile_estimate' => ['tile_estimate', 'max_generated_tiles_per_job'],
            'mds_imagegrid_quota_storage_bytes' => ['storage_estimate_bytes', 'max_artifact_bytes_per_job'],
            'mds_imagegrid_quota_processing_credits' => ['processing_credits', 'processing_credits_monthly'],
        ];
        $options = [];
        foreach ($mapping as $option_name => [$effective_key, $quota_key]) {
            $value = $effective_limits[$effective_key] ?? null;
            if (!is_numeric($value) && is_array($quotas[$quota_key] ?? null)) {
                $value = $quotas[$quota_key]['limit'] ?? null;
            }
            if (is_numeric($value) && (float) $value >= 0) {
                $options[$option_name] = (float) $value;
            }
        }

        return $options;
    }

    /**
     * Build display-safe plan and usage data from supported response shapes.
     *
     * @param array $payload         Stored account payload.
     * @param array $last_connection Last connection metadata.
     * @return array
     */
    private static function account_summary(array $payload, array $last_connection) {
        $account = is_array($payload['account'] ?? null) ? $payload['account'] : [];
        $plan = is_array($payload['plan'] ?? null)
            ? $payload['plan']
            : (is_array($account['plan'] ?? null) ? $account['plan'] : []);
        $usage = is_array($payload['usage'] ?? null)
            ? $payload['usage']
            : (is_array($account['usage'] ?? null) ? $account['usage'] : []);
        $limit_options = self::plan_limit_options($payload);
        $metric_map = [
            'grid_megapixels' => [__('Grid megapixels per job', 'mds-imagegrid'), 'mds_imagegrid_quota_grid_megapixels'],
            'tile_estimate' => [__('Generated tiles per job', 'mds-imagegrid'), 'mds_imagegrid_quota_tile_estimate'],
            'storage_estimate_bytes' => [__('Artifact bytes per job', 'mds-imagegrid'), 'mds_imagegrid_quota_storage_bytes'],
            'processing_credits' => [__('Processing credits', 'mds-imagegrid'), 'mds_imagegrid_quota_processing_credits'],
            'cdn_delivery_bytes' => [__('CDN delivery bytes', 'mds-imagegrid'), ''],
        ];
        $metrics = [];
        foreach ($metric_map as $key => [$label, $option_name]) {
            $entry = is_array($usage[$key] ?? null) ? $usage[$key] : [];
            $limit = ('' !== $option_name ? ($limit_options[$option_name] ?? null) : null) ?? ($entry['limit'] ?? $entry['total'] ?? null);
            $used = $entry['used'] ?? $usage[$key . '_used'] ?? null;
            $remaining = $entry['remaining'] ?? $usage[$key . '_remaining'] ?? null;
            if (null === $remaining && is_numeric($limit) && is_numeric($used)) {
                $remaining = max(0, (float) $limit - (float) $used);
            }
            if (!is_numeric($limit) && !is_numeric($used) && !is_numeric($remaining)) {
                continue;
            }
            $metrics[] = [
                'key' => $key,
                'label' => $label,
                'limit' => is_numeric($limit) ? (float) $limit : null,
                'used' => is_numeric($used) ? (float) $used : null,
                'remaining' => is_numeric($remaining) ? (float) $remaining : null,
            ];
        }

        $checked_at = sanitize_text_field((string) ($last_connection['checked_at'] ?? ''));
        $checked_timestamp = $checked_at ? strtotime($checked_at) : false;
        $is_stale = $checked_timestamp && $checked_timestamp < (time() - DAY_IN_SECONDS);
        $credit_usage = is_array($usage['processing_credits'] ?? null) ? $usage['processing_credits'] : [];

        return [
            'plan_name' => sanitize_text_field((string) ($plan['display_name'] ?? $plan['name'] ?? $plan['code'] ?? '')),
            'plan_status' => sanitize_key((string) ($plan['status'] ?? $payload['plan_status'] ?? '')),
            'period_start' => sanitize_text_field((string) ($credit_usage['period_start'] ?? $usage['period_start'] ?? $usage['current_period_start'] ?? '')),
            'period_end' => sanitize_text_field((string) ($credit_usage['period_end'] ?? $usage['period_end'] ?? $usage['current_period_end'] ?? '')),
            'resets_at' => sanitize_text_field((string) ($credit_usage['resets_at'] ?? $usage['resets_at'] ?? $usage['reset_at'] ?? '')),
            'metrics' => $metrics,
            'limits_available' => !empty($limit_options),
            'checked_at' => $checked_at,
            'stale' => (bool) $is_stale,
        ];
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
            'mds_version' => class_exists('\MillionDollarScript\Core\Runtime') ? \MillionDollarScript\Core\Runtime::version() : '',
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
        $api_key = is_array($response['api_key_record'] ?? null)
            ? $response['api_key_record']
            : (is_array($data['api_key_record'] ?? null)
                ? $data['api_key_record']
                : (is_array($response['api_key'] ?? null)
                    ? $response['api_key']
                    : (is_array($response['credential'] ?? null) ? $response['credential'] : [])));
        $features = self::sanitize_boolean_map(is_array($response['features'] ?? null) ? $response['features'] : (is_array($data['features'] ?? null) ? $data['features'] : []));
        $entitlements = self::sanitize_entitlement_map(is_array($response['entitlements'] ?? null) ? $response['entitlements'] : (is_array($data['entitlements'] ?? null) ? $data['entitlements'] : []));
        $plan = self::sanitize_plan(is_array($response['plan'] ?? null) ? $response['plan'] : (is_array($data['plan'] ?? null) ? $data['plan'] : []));
        $effective_limits = self::sanitize_numeric_map(is_array($response['effective_limits'] ?? null) ? $response['effective_limits'] : []);
        $quotas = self::sanitize_quota_map(is_array($response['quotas'] ?? null) ? $response['quotas'] : []);
        $usage = self::sanitize_usage(is_array($response['usage'] ?? null) ? $response['usage'] : []);
        $permissions = [];
        foreach ((array) ($response['permissions'] ?? $data['permissions'] ?? []) as $permission) {
            $permission = sanitize_text_field((string) $permission);
            if ('' !== $permission) {
                $permissions[] = $permission;
            }
        }

        return [
            'account' => [
                'id' => sanitize_text_field((string) ($account['id'] ?? '')),
                'slug' => sanitize_key((string) ($account['slug'] ?? '')),
                'display_name' => sanitize_text_field((string) ($account['display_name'] ?? $account['name'] ?? '')),
                'status' => sanitize_key((string) ($account['status'] ?? '')),
            ],
            'api_key' => [
                'id' => sanitize_text_field((string) ($api_key['id'] ?? '')),
                'name' => sanitize_text_field((string) ($api_key['name'] ?? $api_key['display_name'] ?? __('Connected key', 'mds-imagegrid'))),
                'role_code' => sanitize_key((string) ($api_key['role_code'] ?? $api_key['role'] ?? 'api_runtime')),
                'expires_at' => sanitize_text_field((string) ($api_key['expires_at'] ?? '')),
            ],
            'subject_type' => sanitize_key((string) ($response['subject_type'] ?? $data['subject_type'] ?? '')),
            'permissions' => array_values(array_unique($permissions)),
            'features' => $features,
            'entitlements' => $entitlements,
            'plan' => $plan,
            'effective_limits' => $effective_limits,
            'quotas' => $quotas,
            'usage' => $usage,
        ];
    }

    /**
     * Sanitize the current ImageGrid plan summary.
     *
     * @param array $plan Raw plan payload.
     * @return array
     */
    private static function sanitize_plan(array $plan) {
        return array_filter([
            'code' => sanitize_key((string) ($plan['code'] ?? '')),
            'display_name' => sanitize_text_field((string) ($plan['display_name'] ?? $plan['name'] ?? '')),
            'status' => sanitize_key((string) ($plan['status'] ?? '')),
            'access_source' => sanitize_key((string) ($plan['access_source'] ?? '')),
            'expires_at' => sanitize_text_field((string) ($plan['expires_at'] ?? '')),
        ], static function ($value) {
            return '' !== $value;
        });
    }

    /**
     * Sanitize a map of non-negative numeric limits.
     *
     * @param array $values Raw values.
     * @return array
     */
    private static function sanitize_numeric_map(array $values) {
        $sanitized = [];
        foreach ($values as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' !== $key && is_numeric($value) && (float) $value >= 0) {
                $sanitized[$key] = (float) $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize ImageGrid quota definitions.
     *
     * @param array $quotas Raw quota payload.
     * @return array
     */
    private static function sanitize_quota_map(array $quotas) {
        $sanitized = [];
        foreach ($quotas as $key => $quota) {
            $key = sanitize_key((string) $key);
            if ('' === $key || !is_array($quota) || !is_numeric($quota['limit'] ?? null) || (float) $quota['limit'] < 0) {
                continue;
            }
            $sanitized[$key] = ['limit' => (float) $quota['limit']];
            if (is_numeric($quota['interval_seconds'] ?? null) && (int) $quota['interval_seconds'] > 0) {
                $sanitized[$key]['interval_seconds'] = (int) $quota['interval_seconds'];
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize the supported ImageGrid usage metrics.
     *
     * @param array $usage Raw usage payload.
     * @return array
     */
    private static function sanitize_usage(array $usage) {
        $sanitized = [];
        foreach (['processing_credits', 'cdn_delivery_bytes'] as $key) {
            $entry = is_array($usage[$key] ?? null) ? $usage[$key] : [];
            if (!$entry) {
                continue;
            }
            $clean = [];
            foreach (['used', 'remaining', 'limit'] as $numeric_key) {
                if (is_numeric($entry[$numeric_key] ?? null) && (float) $entry[$numeric_key] >= 0) {
                    $clean[$numeric_key] = (float) $entry[$numeric_key];
                }
            }
            foreach (['period_start', 'period_end', 'resets_at'] as $date_key) {
                if (!empty($entry[$date_key])) {
                    $clean[$date_key] = sanitize_text_field((string) $entry[$date_key]);
                }
            }
            if (array_key_exists('rolling_window', $entry)) {
                $clean['rolling_window'] = self::truthy($entry['rolling_window']);
            }
            if ($clean) {
                $sanitized[$key] = $clean;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize boolean feature flags from ImageGrid.
     *
     * @param array $features Raw feature map.
     * @return array
     */
    private static function sanitize_boolean_map(array $features) {
        $sanitized = [];

        foreach ($features as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }

            $sanitized[$key] = self::truthy($value);
        }

        return $sanitized;
    }

    /**
     * Sanitize account entitlements from ImageGrid.
     *
     * @param array $entitlements Raw entitlement map.
     * @return array
     */
    private static function sanitize_entitlement_map(array $entitlements) {
        $sanitized = [];

        foreach ($entitlements as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = sanitize_key((string) $key);
            if ('' === $key) {
                $key = sanitize_key((string) ($item['feature_code'] ?? $item['code'] ?? ''));
            }
            if ('' === $key) {
                continue;
            }

            $status = sanitize_key((string) ($item['status'] ?? ''));
            $status_allows_access = '' === $status || in_array($status, ['trialing', 'active'], true);
            $enabled = array_key_exists('enabled', $item)
                ? self::truthy($item['enabled'])
                : in_array($status, ['trialing', 'active'], true);
            $sanitized[$key] = [
                'enabled' => $enabled && $status_allows_access,
                'feature_code' => sanitize_key((string) ($item['feature_code'] ?? $key)),
                'status' => $status,
                'source' => sanitize_key((string) ($item['source'] ?? '')),
                'provider' => sanitize_key((string) ($item['provider'] ?? '')),
                'add_on_code' => sanitize_key((string) ($item['add_on_code'] ?? '')),
                'cancel_at_period_end' => self::truthy($item['cancel_at_period_end'] ?? false),
                'current_period_end' => sanitize_text_field((string) ($item['current_period_end'] ?? '')),
            ];
        }

        return $sanitized;
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
     * Whether a remote error means the saved credential needs authorization.
     *
     * @param string $message Error message.
     * @return bool
     */
    private static function message_requires_authorization($message) {
        $message = strtolower(trim((string) $message));
        if ('' === $message) {
            return false;
        }

        foreach (['authorization required', 'authorization is required', 'unauthorized', 'not authorized', 'invalid api key', 'api key is invalid', 'missing api key'] as $needle) {
            if (false !== strpos($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an error requires plan, billing, or account action.
     *
     * @param string $message Error message.
     * @return bool
     */
    private static function message_requires_account_action($message) {
        $message = strtolower(trim((string) $message));
        if ('' === $message) {
            return false;
        }

        foreach (['billing needs attention', 'active imagegrid plan is required', 'account is unavailable', 'could not verify the account plan', 'account quota'] as $needle) {
            if (false !== strpos($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert raw service auth errors into sentence-case admin copy.
     *
     * @param string $message Raw message.
     * @return string
     */
    private static function friendly_error_message($message) {
        $message = sanitize_text_field((string) $message);
        if (self::message_requires_authorization($message)) {
            return __('ImageGrid authorization is required. Connect or verify your ImageGrid API key before using hosted rendering.', 'mds-imagegrid');
        }

        switch (strtolower(trim($message))) {
            case 'billing action required':
                return __('ImageGrid billing needs attention. Review the connected ImageGrid account to restore rendering access.', 'mds-imagegrid');
            case 'active plan required':
            case 'plan required':
                return __('An active ImageGrid plan is required before this account can process render jobs.', 'mds-imagegrid');
            case 'account unavailable':
                return __('This ImageGrid account is unavailable. Review the account status or connect a different account.', 'mds-imagegrid');
            case 'plan policy unavailable':
                return __('ImageGrid could not verify the account plan. Try again shortly or review the connected account.', 'mds-imagegrid');
            case 'quota exceeded':
                return __('An ImageGrid account quota has been reached. Review usage before submitting another render job.', 'mds-imagegrid');
        }

        return $message;
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
        return class_exists('\MillionDollarScript\Core\Runtime') ? 'mds3-imagegrid' : 'mds-imagegrid';
    }
}
