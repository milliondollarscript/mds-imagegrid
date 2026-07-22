<?php
/**
 * ImageGrid settings embedded in the MDS3 Rendering settings panel.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}

$connection_badge_status = 'connected' === $connection_status ? 'connected' : ($has_api_key ? 'warning' : 'not_connected');
$connection_badge_label = 'connected' === $connection_status ? __('Connected', 'mds-imagegrid') : ($has_api_key ? __('API key saved', 'mds-imagegrid') : __('Not connected', 'mds-imagegrid'));
$cdn_known = isset($cdn_known) ? (bool) $cdn_known : false;
$cdn_badge_label = !empty($cdn_access) ? __('CDN Delivery enabled', 'mds-imagegrid') : ($cdn_known ? __('CDN Delivery not active', 'mds-imagegrid') : __('CDN status unknown', 'mds-imagegrid'));
?>
<div class="mds-imagegrid-core-settings">
    <input type="hidden" name="mds_imagegrid_settings_present" value="1" />
    <div class="mds-imagegrid-core-heading">
        <div>
            <h3><?php esc_html_e('ImageGrid remote rendering', 'mds-imagegrid'); ?></h3>
            <p class="description"><?php esc_html_e('Connect Million Dollar Script zoomable grids to ImageGrid for hosted render jobs. Public delivery remains local unless the connected ImageGrid API key includes CDN access.', 'mds-imagegrid'); ?></p>
        </div>
        <div class="mds-imagegrid-status-stack">
            <span class="mds-imagegrid-status mds-imagegrid-status-<?php echo esc_attr($connection_badge_status); ?>">
                <?php echo esc_html($connection_badge_label); ?>
            </span>
            <?php if ($has_api_key) : ?>
                <span class="mds-imagegrid-status <?php echo esc_attr(!empty($cdn_access) ? 'mds-imagegrid-status-connected' : 'mds-imagegrid-status-warning'); ?>">
                    <?php echo esc_html($cdn_badge_label); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($has_api_key && !$cdn_known) : ?>
        <p class="description"><?php esc_html_e('Test the connection to fetch account entitlements before relying on CDN delivery status.', 'mds-imagegrid'); ?></p>
    <?php elseif ($has_api_key && empty($cdn_access)) : ?>
        <p class="description"><?php esc_html_e('ImageGrid can process large render jobs, but public grid tile delivery stays local until CDN Delivery is active on the connected account.', 'mds-imagegrid'); ?></p>
    <?php endif; ?>

    <div class="mds-imagegrid-core-sections">
        <section class="mds-imagegrid-core-section">
            <h4><?php esc_html_e('Connection', 'mds-imagegrid'); ?></h4>
            <?php
            self::input_field('mds_imagegrid_api_url', __('API URL', 'mds-imagegrid'), 'url', $api_url, __('Server API URL. This can point at local Docker during development and is never used for browser account links.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_api_key', __('API key', 'mds-imagegrid'), 'password', '', $has_api_key ? __('Saved key is hidden. Leave blank to keep it.', 'mds-imagegrid') : __('Paste an ImageGrid API key.', 'mds-imagegrid'), $has_api_key ? __('Saved key is hidden', 'mds-imagegrid') : __('Paste an ImageGrid API key', 'mds-imagegrid'));
            if ($has_api_key) {
                self::checkbox_field('mds_imagegrid_clear_api_key', __('Clear saved key', 'mds-imagegrid'), false, __('Remove the saved API key during this settings save.', 'mds-imagegrid'));
            }
            ?>
        </section>

        <section class="mds-imagegrid-core-section">
            <h4><?php esc_html_e('Rendering', 'mds-imagegrid'); ?></h4>
            <?php
            self::checkbox_field('mds_imagegrid_enabled', __('Enable ImageGrid', 'mds-imagegrid'), $enabled, __('Allow Million Dollar Script to submit eligible large-grid render jobs to ImageGrid.', 'mds-imagegrid'));
            self::checkbox_field('mds_imagegrid_auto_render', __('Automatic rendering', 'mds-imagegrid'), $auto_render, __('Submit eligible jobs automatically. Each remote job can use processing credits and count toward the connected ImageGrid plan limits.', 'mds-imagegrid'));
            self::checkbox_field('mds_imagegrid_failure_email_enabled', __('Render failure email', 'mds-imagegrid'), $failure_email_enabled, __('Email the site administrator once when a grid cannot complete ImageGrid rendering. WordPress admin notices remain enabled.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_local_render_threshold_megapixels', __('Local render threshold MP', 'mds-imagegrid'), 'number', (string) $threshold, __('Use local rendering at or below this grid size. Larger grids can use ImageGrid when configured.', 'mds-imagegrid'));
            ?>
        </section>
    </div>

    <details class="mds-imagegrid-advanced-settings">
        <summary><?php esc_html_e('Local rendering safeguards', 'mds-imagegrid'); ?></summary>
        <p class="description"><?php esc_html_e('These WordPress-side caps stop unexpectedly large remote submissions before they reach ImageGrid. ImageGrid separately enforces the connected account plan and usage limits.', 'mds-imagegrid'); ?></p>
        <div class="mds-imagegrid-quota-grid">
            <?php
            self::input_field('mds_imagegrid_quota_grid_megapixels', __('Maximum grid megapixels', 'mds-imagegrid'), 'number', (string) $quota_grid, __('Local cap for the grid size submitted in one remote job.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_quota_source_megapixels', __('Maximum source megapixels', 'mds-imagegrid'), 'number', (string) $quota_source, __('Local cap for the combined source-image size submitted in one remote job.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_quota_tile_estimate', __('Maximum estimated tiles', 'mds-imagegrid'), 'number', (string) $quota_tiles, __('Local cap for the estimated tile count of one remote job.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_quota_storage_bytes', __('Maximum estimated storage bytes', 'mds-imagegrid'), 'number', (string) $quota_storage, __('Local cap for the estimated storage footprint of one remote job.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_quota_processing_credits', __('Maximum estimated processing credits', 'mds-imagegrid'), 'number', (string) $quota_credits, __('Local cap for the estimated processing credits used by one remote job.', 'mds-imagegrid'));
            ?>
        </div>
        <p class="description"><?php esc_html_e('Open the connected ImageGrid account to review current plan usage, remaining credits, and service-enforced limits.', 'mds-imagegrid'); ?></p>
    </details>

    <p class="mds3-button-row">
        <?php if (!empty($connect_url)) : ?>
            <a class="button button-primary" href="<?php echo esc_url($connect_url); ?>"><?php esc_html_e('Connect ImageGrid', 'mds-imagegrid'); ?></a>
        <?php endif; ?>
        <?php if (!empty($app_url)) : ?>
            <a class="button" href="<?php echo esc_url($app_url . '/app/signup'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Create ImageGrid Account', 'mds-imagegrid'); ?></a>
            <a class="button" href="<?php echo esc_url($app_url . '/app/api-keys'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Manage API keys', 'mds-imagegrid'); ?></a>
        <?php endif; ?>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::settings_page_slug())); ?>">
            <?php echo esc_html($has_connection ? __('Manage connection', 'mds-imagegrid') : __('Open ImageGrid settings', 'mds-imagegrid')); ?>
        </a>
    </p>
</div>
