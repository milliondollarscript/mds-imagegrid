<?php
/**
 * ImageGrid admin information-architecture and account-data fixture.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

use MillionDollarScript\Extensions\ImageGrid\Admin;
use MillionDollarScript\Extensions\ImageGrid\Main;
use function MillionDollarScript\Extensions\ImageGrid\mds_imagegrid_app_url;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(Admin::class) || !class_exists(Main::class)) {
    throw new RuntimeException('The ImageGrid extension must be active.');
}

$invoke_private = static function($method, array $arguments = []) {
    $reflection = new ReflectionMethod(Admin::class, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs(null, $arguments);
};

$fixture_response = [
    'account' => ['id' => 'acct-fixture', 'slug' => 'fixture', 'display_name' => 'Fixture Account'],
    'api_key' => ['id' => 'key-fixture', 'name' => 'Fixture key', 'role_code' => 'api_runtime'],
    'plan' => ['code' => 'growth', 'display_name' => 'Growth', 'status' => 'active'],
    'effective_limits' => [
        'grid_megapixels' => 500,
        'tile_estimate' => 50000,
        'storage_estimate_bytes' => 5368709120,
        'processing_credits' => 10000,
    ],
    'quotas' => [
        'processing_credits_monthly' => ['limit' => 10000, 'interval_seconds' => 2592000],
        'max_input_megapixels' => ['limit' => 48],
        'max_input_edge_pixels' => ['limit' => 8000],
        'max_upload_bytes' => ['limit' => 52428800],
        'max_tile_patch_megapixels_per_job' => ['limit' => 4],
        'max_tile_patch_patches_per_job' => ['limit' => 1],
        'max_in_flight_jobs' => ['limit' => 25],
        'jobs_per_day' => ['limit' => 10000, 'interval_seconds' => 86400],
        'retention_ttl_seconds' => ['limit' => 86400],
        'cdn_delivery_bytes_monthly' => ['limit' => 100000000000, 'interval_seconds' => 2592000],
    ],
    'usage' => [
        'processing_credits' => [
            'used' => 2500,
            'remaining' => 7500,
            'limit' => 10000,
            'period_start' => '2026-07-01T00:00:00Z',
            'period_end' => '2026-08-01T00:00:00Z',
        ],
        'cdn_delivery_bytes' => ['used' => 2048],
    ],
    'permissions' => ['account.read', 'usage.read'],
    'features' => ['cdn_delivery' => false],
    'unknown_service_field' => '<script>not stored</script>',
];
$fixture_account = $invoke_private('account_payload_from_response', [$fixture_response]);
$connection = ['status' => 'connected', 'checked_at' => current_time('mysql')];

if (
    'Growth' !== ($fixture_account['plan']['display_name'] ?? '')
    || 7500.0 !== ($fixture_account['usage']['processing_credits']['remaining'] ?? null)
    || isset($fixture_account['unknown_service_field'])
) {
    throw new RuntimeException('The current ImageGrid account response was not normalized safely.');
}

$limits = $invoke_private('plan_limit_options', [$fixture_account]);
if (10000.0 !== ($limits['mds_imagegrid_quota_processing_credits'] ?? null)) {
    throw new RuntimeException('Plan processing-credit limits were not mapped to local safeguards.');
}

$summary = $invoke_private('account_summary', [$fixture_account, $connection]);
$metrics_by_key = [];
foreach ($summary['metrics'] as $metric) {
    $metrics_by_key[$metric['key']] = $metric;
}
if (
    'Growth' !== ($summary['plan_name'] ?? '')
    || '2026-08-01T00:00:00Z' !== ($summary['period_end'] ?? '')
    || 48.0 !== ($metrics_by_key['input_megapixels']['limit'] ?? null)
    || 'capacity' !== ($metrics_by_key['in_flight_jobs']['category'] ?? '')
    || 'periodic' !== ($metrics_by_key['jobs_per_day']['category'] ?? '')
    || 'delivery' !== ($metrics_by_key['cdn_delivery_bytes']['category'] ?? '')
    || empty($summary['limits_available'])
) {
    throw new RuntimeException('Plan and usage summary did not preserve authoritative fixture data.');
}

if ('http://localhost:18082' !== mds_imagegrid_app_url()) {
    throw new RuntimeException('The local ImageGrid account app URL is not using the local service.');
}
$connect_url = $invoke_private('connect_url');
parse_str((string) wp_parse_url($connect_url, PHP_URL_QUERY), $connect_query);
if ('http://host.docker.internal:18082' !== ($connect_query['api_url'] ?? '')) {
    throw new RuntimeException('The ImageGrid connect request did not include the configured API URL.');
}

$paused_payload = $invoke_private('account_payload_from_response', [[
    'account' => ['id' => 'acct-paused', 'slug' => 'paused'],
    'entitlements' => [
        'cdn_delivery' => ['feature_code' => 'cdn_delivery', 'enabled' => true, 'status' => 'past_due'],
    ],
]]);
if (Main::has_cdn_delivery_access($paused_payload)) {
    throw new RuntimeException('A past-due CDN entitlement must not enable delivery.');
}
$active_payload = $invoke_private('account_payload_from_response', [[
    'account' => ['id' => 'acct-active', 'slug' => 'active'],
    'features' => ['cdn_delivery' => true, 'mds3_cdn' => true],
    'entitlements' => [
        'cdn_delivery' => ['feature_code' => 'cdn_delivery', 'enabled' => true, 'status' => 'active'],
    ],
]]);
if (!Main::has_cdn_delivery_access($active_payload)) {
    throw new RuntimeException('An active CDN entitlement did not enable delivery.');
}

$administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (empty($administrators)) {
    throw new RuntimeException('An administrator is required for the admin rendering fixture.');
}
wp_set_current_user((int) reset($administrators));

$original_get = $_GET;
$rendered = [];
foreach (['connection', 'account', 'rendering', 'limits'] as $section) {
    $_GET = ['page' => 'mds3-imagegrid', 'section' => $section];
    ob_start();
    Admin::render_settings_page();
    $html = (string) ob_get_clean();
    if (false === strpos($html, 'aria-label="ImageGrid settings"') || false === strpos($html, 'section=' . $section)) {
        throw new RuntimeException(sprintf('The %s tab did not render stable navigation.', $section));
    }
    if (false !== stripos($html, 'workspace')) {
        throw new RuntimeException(sprintf('The %s tab contains obsolete workspace terminology.', $section));
    }
    $rendered[$section] = strlen($html);
}
$_GET = $original_get;

echo wp_json_encode([
    'ok' => true,
    'tabs' => $rendered,
    'plan' => $summary['plan_name'],
    'metrics' => count($summary['metrics']),
    'paused_cdn' => false,
]) . PHP_EOL;
