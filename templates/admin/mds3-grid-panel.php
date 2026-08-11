<?php
/**
 * ImageGrid controls embedded in the MDS3 grid editor.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}

$cdn_known = isset($cdn_known) ? (bool) $cdn_known : !empty($last_account ?? []);
$cdn_label = !empty($cdn_access) ? __('Enabled', 'mds-imagegrid') : ($cdn_known ? __('Local fallback', 'mds-imagegrid') : __('Unknown until connection test', 'mds-imagegrid'));
$notice_message = $notice ? (string) ($notice['message'] ?? '') : '';
$show_render_disabled_reason = !empty($render_disabled_reason) && $render_disabled_reason !== $notice_message;
?>
<section class="<?php echo esc_attr($classes); ?>" data-settings-panel="grid-imagegrid">
    <h2><?php esc_html_e('ImageGrid', 'mds-imagegrid'); ?></h2>
    <?php if ($notice) : ?>
        <div class="notice notice-<?php echo esc_attr(self::notice_class($notice['type'])); ?> inline">
            <p>
                <?php echo esc_html($notice['message']); ?>
                <?php if (self::message_requires_authorization($notice['message'] ?? '') && !empty($settings_url)) : ?>
                    <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open ImageGrid settings', 'mds-imagegrid'); ?></a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
    <p><?php esc_html_e('Submit this grid for hosted ImageGrid rendering. CDN delivery is shown below when the connected ImageGrid API key includes that entitlement.', 'mds-imagegrid'); ?></p>

    <?php if ($show_render_disabled_reason) : ?>
        <p class="description">
            <?php echo esc_html($render_disabled_reason); ?>
            <?php if (!empty($settings_url)) : ?>
                <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open ImageGrid settings', 'mds-imagegrid'); ?></a>
            <?php endif; ?>
        </p>
    <?php elseif (!$cdn_known) : ?>
        <p class="description"><?php esc_html_e('Test the ImageGrid connection to fetch account entitlements before relying on CDN delivery status.', 'mds-imagegrid'); ?></p>
    <?php elseif (empty($cdn_access)) : ?>
        <p class="description"><?php esc_html_e('Remote render jobs can still be submitted, but public tile delivery stays local until CDN Delivery is active for the connected ImageGrid account.', 'mds-imagegrid'); ?></p>
    <?php endif; ?>

    <div class="mds-imagegrid-grid-actions">
        <?php
        self::grid_action_form('mds_imagegrid_submit_grid_render', 'mds_imagegrid_submit_grid_render', $grid_id, __('Render with ImageGrid', 'mds-imagegrid'), 'button button-primary', !empty($render_disabled), $render_disabled_reason);
        self::grid_action_form('mds_imagegrid_refresh_job', 'mds_imagegrid_refresh_job', $grid_id, __('Refresh job', 'mds-imagegrid'), 'button', !$grid_id || empty($state['job_id']));
        ?>
    </div>

    <?php include MDS_IMAGEGRID_PATH . 'templates/admin/partials/render-estimate.php'; ?>

    <dl class="mds-imagegrid-facts mds-imagegrid-grid-state">
        <?php
        self::fact(__('State', 'mds-imagegrid'), $state['status'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Job ID', 'mds-imagegrid'), $state['job_id'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Tileset ID', 'mds-imagegrid'), $state['tileset_id'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Tiles', 'mds-imagegrid'), !empty($state['total_tiles']) ? number_format_i18n((int) $state['total_tiles']) : __('Unknown', 'mds-imagegrid'));
        self::fact(__('Connection', 'mds-imagegrid'), 'connected' === ($connection_status ?? '') ? __('Connected', 'mds-imagegrid') : (!empty($has_api_key) ? __('API key saved', 'mds-imagegrid') : __('Not connected', 'mds-imagegrid')));
        self::fact(__('CDN Delivery', 'mds-imagegrid'), $cdn_label);
        self::fact(__('Updated', 'mds-imagegrid'), $state['updated_at'] ?? __('Never', 'mds-imagegrid'));
        ?>
    </dl>

    <?php if (!empty($last_error) && empty($authorization_required) && (!$notice || $last_error !== ($notice['message'] ?? ''))) : ?>
        <p class="mds-imagegrid-error">
            <?php echo esc_html($last_error); ?>
            <?php if (self::message_requires_authorization($last_error) && !empty($settings_url)) : ?>
                <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open ImageGrid settings', 'mds-imagegrid'); ?></a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>
