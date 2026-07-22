<?php
/**
 * WP-CLI fixture for ImageGrid render failure notifications.
 *
 * Run with:
 * wp eval-file wp-content/plugins/mds-imagegrid/tests/render-failure-notifier-fixture.php
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

use MillionDollarScript\Extensions\ImageGrid\Render_Failure_Notifier;

if (!defined('ABSPATH')) {
    exit(1);
}

if (!class_exists(Render_Failure_Notifier::class)) {
    throw new RuntimeException('ImageGrid render failure notifier is unavailable.');
}

$records_before = get_option(Render_Failure_Notifier::OPTION_NAME, []);
$email_before = get_option(Render_Failure_Notifier::EMAIL_OPTION, false);
$mail_calls = [];
$mail_filter = static function($return, $atts) use (&$mail_calls) {
    $mail_calls[] = $atts;
    return true;
};

add_filter('pre_wp_mail', $mail_filter, 10, 2);

try {
    update_option(Render_Failure_Notifier::OPTION_NAME, [], false);
    update_option(Render_Failure_Notifier::EMAIL_OPTION, false, false);

    $class_cases = [
        ['memory_limit', 'The local render exceeded the memory limit.', 'local_resource'],
        ['mds_imagegrid_missing_api_key', 'ImageGrid API is not configured.', 'remote_disabled'],
        ['invalid_api_key', 'ImageGrid authorization is required.', 'authorization'],
        ['past_due', 'The account billing status is past due.', 'account_billing'],
        ['quota', 'The processing credit quota was exceeded.', 'quota'],
        ['http_request_failed', 'The ImageGrid connection timed out.', 'remote_service'],
        ['failed', 'ImageGrid job failed.', 'terminal_job'],
    ];
    foreach ($class_cases as [$source, $message, $expected]) {
        $actual = Render_Failure_Notifier::classify($source, $message);
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf('Expected %s classification, received %s.', $expected, $actual));
        }
    }

    Render_Failure_Notifier::record(
        901,
        'invalid_api_key',
        'Authorization: Bearer secret-value https://service.example.test/private /var/www/html/private/file.php'
    );
    $records = Render_Failure_Notifier::records();
    $record = reset($records);
    $stored = wp_json_encode($record);
    foreach (['secret-value', 'service.example.test', '/var/www/html'] as $private_value) {
        if (false !== strpos($stored, $private_value)) {
            throw new RuntimeException('A private value remained in the stored notification.');
        }
    }
    Render_Failure_Notifier::record(901, 'invalid_api_key', 'ImageGrid authorization is required.');
    $records = Render_Failure_Notifier::records();
    if (1 !== count($records)) {
        throw new RuntimeException('Repeated unresolved failures were not deduplicated.');
    }
    $record = reset($records);
    if (2 !== (int) ($record['count'] ?? 0) || empty($record['active'])) {
        throw new RuntimeException('Repeated failure count or active state is incorrect.');
    }
    if ($mail_calls) {
        throw new RuntimeException('Failure email was sent while the setting was disabled.');
    }

    Render_Failure_Notifier::resolve_grid(901);
    $resolved = reset(Render_Failure_Notifier::records());
    if (!empty($resolved['active']) || empty($resolved['resolved_at'])) {
        throw new RuntimeException('Successful recovery did not resolve the failure.');
    }

    update_option(Render_Failure_Notifier::EMAIL_OPTION, true, false);
    Render_Failure_Notifier::record(901, 'invalid_api_key', 'ImageGrid authorization is required.');
    $recurrence = reset(Render_Failure_Notifier::records());
    if (empty($recurrence['active']) || 1 !== (int) ($recurrence['count'] ?? 0) || empty($recurrence['email_sent_at'])) {
        throw new RuntimeException('A recovered failure did not create a fresh notification occurrence.');
    }
    Render_Failure_Notifier::record(901, 'invalid_api_key', 'ImageGrid authorization is required.');
    if (1 !== count($mail_calls)) {
        throw new RuntimeException('Repeated unresolved failures sent duplicate email.');
    }

    update_option(Render_Failure_Notifier::EMAIL_OPTION, false, false);
    for ($grid_id = 1000; $grid_id < 1060; $grid_id++) {
        Render_Failure_Notifier::record($grid_id, 'failed', 'Synthetic terminal job failure.');
    }
    if (Render_Failure_Notifier::MAX_RECORDS !== count(Render_Failure_Notifier::records())) {
        throw new RuntimeException('Notification history was not bounded.');
    }

    echo wp_json_encode([
        'ok' => true,
        'classifications' => count($class_cases),
        'deduplicated_mail_calls' => count($mail_calls),
        'bounded_records' => count(Render_Failure_Notifier::records()),
    ]) . PHP_EOL;
} finally {
    remove_filter('pre_wp_mail', $mail_filter, 10);
    update_option(Render_Failure_Notifier::OPTION_NAME, is_array($records_before) ? $records_before : [], false);
    update_option(Render_Failure_Notifier::EMAIL_OPTION, (bool) $email_before, false);
}
