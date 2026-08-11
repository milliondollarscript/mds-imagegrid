<?php
/**
 * Coordinate idempotent ImageGrid submissions.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid;

if (!defined('ABSPATH')) {
    exit;
}

final class SubmissionCoordinator {

    /**
     * Reuse an ambiguous submission key or start a new render generation.
     *
     * @param int   $grid_id Grid ID.
     * @param array $state Stored grid state.
     * @return array{key: string, generation: string}
     */
    public static function context($grid_id, array $state) {
        $key = sanitize_text_field((string) ($state['submission_key'] ?? ''));
        $generation = sanitize_text_field((string) ($state['submission_generation'] ?? ''));
        $can_reuse = '' !== $key
            && '' !== $generation
            && ('submitting' === sanitize_key((string) ($state['status'] ?? '')) || !empty($state['submission_retryable']));

        if (!$can_reuse) {
            $generation = wp_generate_uuid4();
            $key = 'mds-grid-' . absint($grid_id) . '-' . $generation;
        }

        return ['key' => $key, 'generation' => $generation];
    }

    /**
     * Decide whether a request error may have occurred after remote acceptance.
     *
     * @param \WP_Error $error Request error.
     * @return bool
     */
    public static function transport_error_is_ambiguous($error) {
        $data = is_wp_error($error) ? $error->get_error_data() : null;
        if (!is_array($data) || !isset($data['status'])) {
            return true;
        }

        $status = absint($data['status']);

        return 0 === $status || $status >= 500 || in_array($status, [408, 425, 429], true);
    }

    /**
     * Build a stable key for one patch within a render generation.
     *
     * @param string $generation Render generation UUID.
     * @param string $tileset_id Tileset UUID.
     * @param array  $patch Patch payload.
     * @return string
     */
    public static function patch_key($generation, $tileset_id, array $patch) {
        $payload = wp_json_encode([
            'generation' => sanitize_text_field((string) $generation),
            'tileset_id' => sanitize_text_field((string) $tileset_id),
            'patch' => $patch,
        ]);

        return 'mds-patch-' . hash('sha256', (string) $payload);
    }

    /**
     * Acquire an atomic, short-lived per-grid submission lock.
     *
     * @param int $grid_id Grid ID.
     * @return string|\WP_Error
     */
    public static function acquire($grid_id) {
        $grid_id = absint($grid_id);
        if (!$grid_id) {
            return new \WP_Error('mds_imagegrid_grid_not_found', __('Grid not found.', 'mds-imagegrid'));
        }

        $option = self::option_name($grid_id);
        $token = wp_generate_uuid4();
        $value = ['token' => $token, 'created_at' => time()];
        if (add_option($option, $value, '', false)) {
            return $token;
        }

        $existing = get_option($option, []);
        if (is_array($existing) && (int) ($existing['created_at'] ?? 0) < time() - 300) {
            delete_option($option);
            if (add_option($option, $value, '', false)) {
                return $token;
            }
        }

        return new \WP_Error('mds_imagegrid_submission_in_progress', __('This grid already has a render submission in progress.', 'mds-imagegrid'));
    }

    /**
     * Release a per-grid submission lock owned by this request.
     *
     * @param int    $grid_id Grid ID.
     * @param string $token Lock token.
     * @return void
     */
    public static function release($grid_id, $token) {
        $option = self::option_name($grid_id);
        $existing = get_option($option, []);
        if (is_array($existing) && hash_equals((string) ($existing['token'] ?? ''), (string) $token)) {
            delete_option($option);
        }
    }

    /**
     * @param int $grid_id Grid ID.
     * @return string
     */
    private static function option_name($grid_id) {
        return 'mds_imagegrid_submit_lock_' . absint($grid_id);
    }
}
