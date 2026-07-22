<?php
/**
 * ImageGrid settings validation, sanitization, and numeric option helpers.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid\Concerns;

use MillionDollarScript\Extensions\ImageGrid\API_Client;
use function MillionDollarScript\Extensions\ImageGrid\mds_imagegrid_default_api_url;

if (!defined('ABSPATH')) {
    exit;
}

trait ValidatesImageGridSettings {

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

        $raw_api_url = isset($post['mds_imagegrid_api_url'])
            ? (string) $post['mds_imagegrid_api_url']
            : mds_imagegrid_default_api_url();
        $api_url = API_Client::sanitize_service_url($raw_api_url);

        if (API_Client::has_invalid_service_url_submission($raw_api_url, $api_url)) {
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
        update_option('mds_imagegrid_failure_email_enabled', !empty($post['mds_imagegrid_failure_email_enabled']), false);
        update_option('mds_imagegrid_auto_render_preference', 'explicit', false);
        update_option('mds_imagegrid_api_url', API_Client::sanitize_service_url($post['mds_imagegrid_api_url'] ?? mds_imagegrid_default_api_url()));
        self::save_rendering_limits($post);

        $submitted_key = trim((string) ($post['mds_imagegrid_api_key'] ?? ''));
        if (!empty($post['mds_imagegrid_clear_api_key'])) {
            self::clear_connection();
        } elseif ('' !== $submitted_key) {
            update_option('mds_imagegrid_api_key', sanitize_text_field($submitted_key), false);
            update_option('mds_imagegrid_last_connection', [
                'status' => 'manual_key_saved',
                'message' => __('API key saved. Test the connection to verify the account.', 'mds-imagegrid'),
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
        \MillionDollarScript\Core\Hooks::do_compat('million-dollar-script/extensions/imagegrid/settings/saved', ['mds_imagegrid_settings_saved']);
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
}
