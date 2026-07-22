<?php
/**
 * ImageGrid API error compatibility fixture.
 *
 * Run with WP-CLI while the extension is active. Requests are intercepted and
 * never leave WordPress.
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

$cases = [
    'billing_action_required' => [403, ['error' => 'billing action required', 'code' => 'billing_action_required'], 'billing needs attention'],
    'plan_required' => [403, ['error' => 'active plan required', 'code' => 'plan_required'], 'active imagegrid plan'],
    'account_unavailable' => [403, ['error' => 'account unavailable', 'code' => 'account_unavailable'], 'account is unavailable'],
    'plan_policy_unavailable' => [503, ['error' => 'plan policy unavailable', 'code' => 'plan_policy_unavailable'], 'could not verify the account plan'],
    'quota_exceeded' => [429, ['error' => 'quota exceeded', 'quota_key' => 'processing_credits'], 'processing credits'],
    'worker_capacity_exceeded' => [429, ['error' => 'job exceeds current processing limits', 'code' => 'worker_capacity_exceeded'], 'current processing capacity'],
    'quota_policy_failed' => [503, ['error' => 'failed to load quota policy', 'code' => 'quota_policy_failed'], 'could not verify the account limits'],
    'legacy_text' => [403, ['error' => 'legacy service denial'], 'legacy service denial'],
    'unauthorized' => [401, ['error' => 'unauthorized'], 'authorization is required'],
];

$results = [];
foreach ($cases as $name => $case) {
    [$status, $body, $expected_message] = $case;
    $filter = static function() use ($status, $body) {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => wp_json_encode($body),
            'response' => ['code' => $status, 'message' => 'Fixture'],
            'cookies' => [],
            'filename' => null,
        ];
    };

    add_filter('pre_http_request', $filter, 10, 3);
    $error = (new API_Client('http://host.docker.internal:18082', 'fixture-key-123456'))->account();
    remove_filter('pre_http_request', $filter, 10);

    if (!is_wp_error($error)) {
        throw new RuntimeException(sprintf('Expected a WordPress error for %s.', $name));
    }
    if (false === stripos($error->get_error_message(), $expected_message)) {
        throw new RuntimeException(sprintf('Unexpected %s message: %s', $name, $error->get_error_message()));
    }

    $data = $error->get_error_data();
    if ((int) ($data['status'] ?? 0) !== $status) {
        throw new RuntimeException(sprintf('Unexpected %s status metadata.', $name));
    }

    $results[$name] = [
        'error_code' => $error->get_error_code(),
        'status' => $status,
        'message' => $error->get_error_message(),
    ];
}

$preflight_requests = 0;
$preflight_filter = static function() use (&$preflight_requests) {
    $preflight_requests++;

    return new WP_Error('mds_imagegrid_unexpected_preflight_request', 'The submission check must not call a removed service route.');
};
$grid = new class {
    public function to_array() {
        return [
            'width' => 640,
            'height' => 320,
            'block_width' => 32,
            'block_height' => 32,
            'settings' => [],
        ];
    }
};

add_filter('pre_http_request', $preflight_filter, 10, 3);
$preflight = (new API_Client('http://host.docker.internal:18082', 'fixture-key-123456'))->preflight_grid_render($grid);
remove_filter('pre_http_request', $preflight_filter, 10);

if (0 !== $preflight_requests) {
    throw new RuntimeException('The ImageGrid submission check called an obsolete preflight endpoint.');
}
if (
    empty($preflight['ok'])
    || 'job_endpoint_validates_limits' !== ($preflight['reason'] ?? '')
    || empty($preflight['estimate']['tile_estimate'])
) {
    throw new RuntimeException('The local ImageGrid submission estimate is incompatible with the current service contract.');
}
$results['submission_check'] = [
    'requests' => $preflight_requests,
    'reason' => $preflight['reason'],
];

echo wp_json_encode(['ok' => true, 'cases' => $results]) . PHP_EOL;
