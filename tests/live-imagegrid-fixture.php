<?php
/**
 * Opt-in live ImageGrid integration fixture.
 *
 * Pass IMAGEGRID_API_URL and IMAGEGRID_API_KEY to the WP-CLI process. The
 * fixture does not save either value in WordPress.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

use MillionDollarScript\Extensions\ImageGrid\API_Client;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(API_Client::class)) {
    throw new RuntimeException('The ImageGrid extension must be active.');
}

$api_url = untrailingslashit(trim((string) getenv('IMAGEGRID_API_URL')));
$api_key = trim((string) getenv('IMAGEGRID_API_KEY'));
if ('' === $api_url || '' === $api_key) {
    throw new RuntimeException('Set IMAGEGRID_API_URL and IMAGEGRID_API_KEY before running the live ImageGrid fixture.');
}

$grid = new class {
    public function to_array() {
        return [
            'id' => 0,
            'width' => 640,
            'height' => 320,
            'block_width' => 32,
            'block_height' => 32,
            'settings' => [],
        ];
    }
};

$client = new API_Client($api_url, $api_key);
$ready = $client->ready();
if (is_wp_error($ready)) {
    throw new RuntimeException('ImageGrid readiness failed: ' . $ready->get_error_message());
}

$account = $client->account();
if (is_wp_error($account)) {
    throw new RuntimeException('ImageGrid account lookup failed: ' . $account->get_error_message());
}

$job = $client->create_grid_render_job($grid);
if (is_wp_error($job)) {
    throw new RuntimeException('ImageGrid job creation failed: ' . $job->get_error_message());
}

$job_id = sanitize_text_field((string) ($job['id'] ?? ''));
if (!preg_match('/^[0-9a-f-]{36}$/i', $job_id)) {
    throw new RuntimeException('ImageGrid did not return a valid job ID.');
}

$terminal = [];
for ($attempt = 0; $attempt < 40; $attempt++) {
    usleep(250000);
    $terminal = $client->get_job($job_id);
    if (is_wp_error($terminal)) {
        throw new RuntimeException('ImageGrid job polling failed: ' . $terminal->get_error_message());
    }

    $status = sanitize_key((string) ($terminal['status'] ?? ''));
    if (in_array($status, ['succeeded', 'complete', 'completed', 'ready', 'failed', 'error'], true)) {
        break;
    }
}

$status = sanitize_key((string) ($terminal['status'] ?? ''));
if (!in_array($status, ['succeeded', 'complete', 'completed', 'ready'], true)) {
    throw new RuntimeException('ImageGrid job did not complete successfully: ' . ($status ?: 'timeout'));
}

$result = is_array($terminal['result'] ?? null) ? $terminal['result'] : [];
$tileset_id = sanitize_text_field((string) ($result['tileset_id'] ?? ''));
if (
    640 !== absint($result['width'] ?? 0) ||
    320 !== absint($result['height'] ?? 0) ||
    256 !== absint($result['tile_size'] ?? 0) ||
    10 !== absint($result['max_level'] ?? 0) ||
    !preg_match('/^[0-9a-f-]{36}$/i', $tileset_id)
) {
    throw new RuntimeException('ImageGrid returned incompatible Deep Zoom metadata.');
}

$tile_request = $client->get_tileset_tile_request($tileset_id, 10, 2, 1, (string) ($result['format'] ?? 'png'));
if (is_wp_error($tile_request)) {
    throw new RuntimeException('ImageGrid tile request could not be built: ' . $tile_request->get_error_message());
}

$tile_response = wp_remote_get((string) $tile_request['url'], [
    'timeout' => 20,
    'redirection' => 0,
    'headers' => (array) ($tile_request['headers'] ?? []),
]);
if (is_wp_error($tile_response)) {
    throw new RuntimeException('ImageGrid tile download failed: ' . $tile_response->get_error_message());
}

$tile_code = (int) wp_remote_retrieve_response_code($tile_response);
$tile_type = strtolower((string) wp_remote_retrieve_header($tile_response, 'content-type'));
$tile_body = (string) wp_remote_retrieve_body($tile_response);
if (200 !== $tile_code || 0 !== strpos($tile_type, 'image/') || '' === $tile_body) {
    throw new RuntimeException(sprintf('ImageGrid tile download returned HTTP %d with content type %s.', $tile_code, $tile_type ?: 'unknown'));
}

$features = is_array($account['features'] ?? null) ? $account['features'] : [];
$cdn_enabled = !empty($features['cdn_delivery']) || !empty($features['mds3_cdn']);
$template = (string) ($result['public_tile_url_template'] ?? $result['tile_url_template'] ?? '');
if ($cdn_enabled && ('' === $template || false === strpos($template, '{z}') || false === strpos($template, '{x}') || false === strpos($template, '{y}'))) {
    throw new RuntimeException('An entitled ImageGrid account did not receive a public tile URL template.');
}
if (!$cdn_enabled && '' !== $template) {
    throw new RuntimeException('ImageGrid exposed a public tile URL template without CDN Delivery access.');
}

echo wp_json_encode([
    'ready' => true,
    'job_id' => $job_id,
    'status' => $status,
    'tileset_id' => $tileset_id,
    'max_level' => absint($result['max_level'] ?? 0),
    'tile_bytes' => strlen($tile_body),
    'cdn_delivery' => $cdn_enabled,
]) . PHP_EOL;
