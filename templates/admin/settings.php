<?php
/**
 * ImageGrid settings shell.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}

$connection_status = sanitize_key((string) ($last_connection['status'] ?? 'not_connected'));
$has_connection = $has_api_key || !empty($connection);
$connection_badge_status = 'connected' === $connection_status ? 'connected' : ($has_api_key ? 'warning' : 'not_connected');
$connection_badge_label = 'connected' === $connection_status ? __('Connected', 'mds-imagegrid') : ($has_api_key ? __('API key saved', 'mds-imagegrid') : __('Not connected', 'mds-imagegrid'));
$cdn_badge_label = $cdn_access ? __('CDN Delivery enabled', 'mds-imagegrid') : ($cdn_known ? __('CDN Delivery not active', 'mds-imagegrid') : __('CDN status unknown', 'mds-imagegrid'));
$notice_message = $notice ? (string) ($notice['message'] ?? '') : '';
$tabs = [
    'connection' => __('Connection', 'mds-imagegrid'),
    'account' => __('Account', 'mds-imagegrid'),
    'rendering' => __('Grid rendering', 'mds-imagegrid'),
    'limits' => __('Local safeguards', 'mds-imagegrid'),
];
?>

<div class="wrap mds3-admin mds-imagegrid-wrap">
    <div class="mds-imagegrid-page-heading">
        <div>
            <h1 class="wp-heading-inline"><?php esc_html_e('ImageGrid', 'mds-imagegrid'); ?></h1>
            <p class="mds-imagegrid-lede"><?php esc_html_e('Connect Million Dollar Script zoomable grids to ImageGrid for hosted render jobs and optional CDN delivery.', 'mds-imagegrid'); ?></p>
        </div>
        <div class="mds-extension-header-actions">
            <?php echo wp_kses_post(\MillionDollarScript\Extensions\Admin::docs_button('mds-imagegrid')); ?>
            <div class="mds-imagegrid-status-stack" aria-label="<?php esc_attr_e('ImageGrid status', 'mds-imagegrid'); ?>">
                <span class="mds-imagegrid-status mds-imagegrid-status-<?php echo esc_attr($connection_badge_status); ?>"><?php echo esc_html($connection_badge_label); ?></span>
                <?php if ($has_api_key) : ?>
                    <span class="mds-imagegrid-status <?php echo esc_attr($cdn_access ? 'mds-imagegrid-status-connected' : 'mds-imagegrid-status-warning'); ?>"><?php echo esc_html($cdn_badge_label); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <hr class="wp-header-end">

    <?php if ($notice) : ?>
        <div class="notice notice-<?php echo esc_attr(\MillionDollarScript\Extensions\ImageGrid\Admin::notice_class($notice['type'])); ?> is-dismissible">
            <p>
                <?php echo esc_html($notice_message); ?>
                <?php if (self::message_requires_authorization($notice_message)) : ?>
                    <a href="<?php echo esc_url($section_urls['connection']); ?>"><?php esc_html_e('Review connection', 'mds-imagegrid'); ?></a>
                <?php elseif (self::message_requires_account_action($notice_message) && !empty($app_url)) : ?>
                    <a href="<?php echo esc_url($app_url . '/app'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Review ImageGrid account', 'mds-imagegrid'); ?></a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper mds-imagegrid-tabs" aria-label="<?php esc_attr_e('ImageGrid settings', 'mds-imagegrid'); ?>">
        <?php foreach ($tabs as $section => $label) : ?>
            <a class="nav-tab <?php echo $active_section === $section ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($section_urls[$section]); ?>" <?php echo $active_section === $section ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="mds-imagegrid-tab-content">
        <?php include MDS_IMAGEGRID_PATH . 'templates/admin/tabs/' . $active_section . '.php'; ?>
    </div>
</div>
