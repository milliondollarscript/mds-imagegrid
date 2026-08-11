<?php
/** ImageGrid account tab. @package MillionDollarScript\Extensions\ImageGrid */
if (!defined('ABSPATH')) {
    exit;
}
$account = isset($last_account['account']) && is_array($last_account['account']) ? $last_account['account'] : [];
$api_key = isset($last_account['api_key']) && is_array($last_account['api_key']) ? $last_account['api_key'] : [];
$has_plan_data = '' !== $account_summary['plan_name'] || !empty($account_summary['metrics']);
$format_metric = static function($value, $key) {
    if (null === $value) {
        return __('Unavailable', 'mds-imagegrid');
    }
    if (in_array($key, ['storage_estimate_bytes', 'upload_bytes', 'cdn_delivery_bytes'], true) && function_exists('size_format')) {
        return 0.0 === (float) $value ? __('0 B', 'mds-imagegrid') : size_format((float) $value, 1);
    }
    return number_format_i18n((float) $value, (float) $value === floor((float) $value) ? 0 : 2);
};
$format_date = static function($value) {
    $timestamp = strtotime((string) $value);
    if (!$timestamp) {
        return (string) $value;
    }

    return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
};
$metric_groups = [
    'per_job' => ['label' => __('Per-job limits', 'mds-imagegrid'), 'metrics' => []],
    'capacity' => ['label' => __('Concurrent capacity', 'mds-imagegrid'), 'metrics' => []],
    'periodic' => ['label' => __('Periodic allowance', 'mds-imagegrid'), 'metrics' => []],
    'delivery' => ['label' => __('Storage and delivery', 'mds-imagegrid'), 'metrics' => []],
];
foreach ((array) ($account_summary['metrics'] ?? []) as $metric) {
    $category = sanitize_key((string) ($metric['category'] ?? 'per_job'));
    if (!isset($metric_groups[$category])) {
        $category = 'per_job';
    }
    $metric_groups[$category]['metrics'][] = $metric;
}
?>
<section class="mds-imagegrid-panel">
    <div class="mds-imagegrid-panel-header">
        <div><h2><?php esc_html_e('Account', 'mds-imagegrid'); ?></h2><p class="description"><?php esc_html_e('Review the verified account identity, CDN entitlement, and plan usage returned to this site.', 'mds-imagegrid'); ?></p></div>
    </div>

    <?php if (!$has_api_key) : ?>
        <div class="mds-imagegrid-empty-state"><h3><?php esc_html_e('Connect ImageGrid first', 'mds-imagegrid'); ?></h3><p><?php esc_html_e('A connected account is required before account and entitlement details can be displayed.', 'mds-imagegrid'); ?></p><a class="button button-primary" href="<?php echo esc_url($section_urls['connection']); ?>"><?php esc_html_e('Open connection', 'mds-imagegrid'); ?></a></div>
    <?php elseif ('connected' !== $connection_status) : ?>
        <div class="mds-imagegrid-callout mds-imagegrid-callout-warning" role="status"><strong><?php esc_html_e('Account status is not verified.', 'mds-imagegrid'); ?></strong> <?php echo esc_html(self::friendly_error_message((string) ($last_connection['message'] ?? __('Test the saved connection to refresh account details.', 'mds-imagegrid')))); ?></div>
    <?php endif; ?>

    <?php if (!empty($account) && 'connected' === $connection_status) : ?>
        <dl class="mds-imagegrid-facts mds-imagegrid-account-facts">
            <?php
            self::fact(__('Account', 'mds-imagegrid'), $account['display_name'] ?? $account['slug'] ?? __('Unknown', 'mds-imagegrid'));
            self::fact(__('API key', 'mds-imagegrid'), $api_key['name'] ?? __('Connected key', 'mds-imagegrid'));
            self::fact(__('Role', 'mds-imagegrid'), $api_key['role_code'] ?? __('Unknown', 'mds-imagegrid'));
            self::fact(__('CDN Delivery', 'mds-imagegrid'), $cdn_access ? __('Enabled', 'mds-imagegrid') : __('Not active', 'mds-imagegrid'));
            if (!empty($account_summary['checked_at'])) {
                self::fact(__('Last verified', 'mds-imagegrid'), $account_summary['checked_at']);
            }
            ?>
        </dl>
    <?php endif; ?>

    <?php if (!empty($account_summary['stale'])) : ?><div class="mds-imagegrid-callout mds-imagegrid-callout-warning" role="status"><?php esc_html_e('These account details are more than 24 hours old. Test the connection before making rendering decisions.', 'mds-imagegrid'); ?></div><?php endif; ?>

    <div class="mds-imagegrid-account-section">
        <h3><?php esc_html_e('Plan and usage', 'mds-imagegrid'); ?></h3>
        <?php if ($has_plan_data && 'connected' === $connection_status) : ?>
            <dl class="mds-imagegrid-facts mds-imagegrid-plan-facts">
                <?php
                if ($account_summary['plan_name']) {
                    self::fact(__('Plan', 'mds-imagegrid'), $account_summary['plan_name']);
                }
                if ($account_summary['plan_status']) {
                    self::fact(__('Plan status', 'mds-imagegrid'), ucfirst(str_replace('_', ' ', $account_summary['plan_status'])));
                }
                if ($account_summary['period_end']) {
                    self::fact(__('Period ends', 'mds-imagegrid'), $format_date($account_summary['period_end']));
                } elseif ($account_summary['resets_at']) {
                    self::fact(__('Usage resets', 'mds-imagegrid'), $format_date($account_summary['resets_at']));
                }
                ?>
            </dl>
            <?php foreach ($metric_groups as $metric_group) : if (empty($metric_group['metrics'])) { continue; } ?>
                <h4><?php echo esc_html($metric_group['label']); ?></h4>
                <div class="mds-imagegrid-usage-table-wrap"><table class="widefat striped mds-imagegrid-usage-table"><thead><tr><th><?php esc_html_e('Resource', 'mds-imagegrid'); ?></th><th><?php esc_html_e('Plan limit', 'mds-imagegrid'); ?></th><th><?php esc_html_e('Used', 'mds-imagegrid'); ?></th><th><?php esc_html_e('Remaining', 'mds-imagegrid'); ?></th></tr></thead><tbody>
                <?php foreach ($metric_group['metrics'] as $metric) : ?><tr><th scope="row"><?php echo esc_html($metric['label']); ?></th><td><?php echo esc_html($format_metric($metric['limit'], $metric['key'])); ?></td><td><?php echo esc_html($format_metric($metric['used'], $metric['key'])); ?></td><td><?php echo esc_html($format_metric($metric['remaining'], $metric['key'])); ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="mds-imagegrid-empty-state mds-imagegrid-empty-state-compact"><h4><?php esc_html_e('Plan usage is unavailable here', 'mds-imagegrid'); ?></h4><p><?php esc_html_e('The connected service has not supplied plan allowance or remaining-usage data to this site. Review current usage in the ImageGrid account.', 'mds-imagegrid'); ?></p></div>
        <?php endif; ?>
    </div>

    <div class="mds-imagegrid-connect-tools">
        <form class="mds-imagegrid-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('mds_imagegrid_test_connection'); ?><input type="hidden" name="action" value="mds_imagegrid_test_connection"><input type="hidden" name="section" value="account"><button type="submit" class="button button-primary" <?php disabled(!$has_api_key); ?>><?php esc_html_e('Refresh account status', 'mds-imagegrid'); ?></button></form>
        <?php if (!empty($app_url)) : ?><a class="button" href="<?php echo esc_url($app_url . '/app'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open ImageGrid account', 'mds-imagegrid'); ?></a><a class="button" href="<?php echo esc_url($app_url . '/app/billing'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Plan and billing', 'mds-imagegrid'); ?></a><?php endif; ?>
    </div>
</section>
