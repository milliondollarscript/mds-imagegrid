<?php
/**
 * ImageGrid settings embedded in the MDS3 Rendering settings panel.
 *
 * @package MDS\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mds-imagegrid-core-settings">
    <input type="hidden" name="mds_imagegrid_settings_present" value="1" />
    <h3><?php esc_html_e('ImageGrid remote rendering', 'mds-imagegrid'); ?></h3>
    <p class="description"><?php esc_html_e('Connect Million Dollar Script zoomable grids to ImageGrid for hosted render jobs. Public delivery remains local unless the connected ImageGrid API key includes CDN access.', 'mds-imagegrid'); ?></p>
    <p>
        <span class="mds-imagegrid-status mds-imagegrid-status-<?php echo esc_attr($connection_status); ?>">
            <?php echo esc_html('connected' === $connection_status ? __('Connected', 'mds-imagegrid') : __('Not Connected', 'mds-imagegrid')); ?>
        </span>
    </p>
    <?php
    self::checkbox_field('mds_imagegrid_enabled', __('Enable ImageGrid', 'mds-imagegrid'), $enabled, __('Allow Million Dollar Script to submit eligible large-grid render jobs to ImageGrid.', 'mds-imagegrid'));
    self::checkbox_field('mds_imagegrid_auto_render', __('Automatic rendering', 'mds-imagegrid'), $auto_render, __('Submit a remote render job when Million Dollar Script requests tile generation.', 'mds-imagegrid'));
    self::input_field('mds_imagegrid_api_url', __('API URL', 'mds-imagegrid'), 'url', $api_url, __('Server API URL. This can point at local Docker during development and is never used for browser account links.', 'mds-imagegrid'));
    self::input_field('mds_imagegrid_api_key', __('API key', 'mds-imagegrid'), 'password', '', $has_api_key ? __('Saved key is hidden. Leave blank to keep it.', 'mds-imagegrid') : __('Paste an ImageGrid API key.', 'mds-imagegrid'), $has_api_key ? __('Saved key is hidden', 'mds-imagegrid') : __('Paste an ImageGrid API key', 'mds-imagegrid'));
    if ($has_api_key) {
        self::checkbox_field('mds_imagegrid_clear_api_key', __('Clear saved key', 'mds-imagegrid'), false, __('Remove the saved API key during this settings save.', 'mds-imagegrid'));
    }
    self::input_field('mds_imagegrid_local_render_threshold_megapixels', __('Local Render Threshold MP', 'mds-imagegrid'), 'number', (string) $threshold, __('Use local rendering at or below this grid size. Larger grids can use ImageGrid when configured.', 'mds-imagegrid'));
    self::input_field('mds_imagegrid_quota_grid_megapixels', __('Grid Megapixel Quota', 'mds-imagegrid'), 'number', (string) $quota_grid, __('Maximum grid megapixels allowed for a remote ImageGrid job.', 'mds-imagegrid'));
    self::input_field('mds_imagegrid_quota_source_megapixels', __('Source Megapixel Quota', 'mds-imagegrid'), 'number', (string) $quota_source, __('Maximum combined source-image megapixels allowed for a remote ImageGrid job.', 'mds-imagegrid'));
    self::input_field('mds_imagegrid_quota_tile_estimate', __('Tile Estimate Quota', 'mds-imagegrid'), 'number', (string) $quota_tiles, __('Maximum estimated tile count allowed for a remote ImageGrid job.', 'mds-imagegrid'));
    self::input_field('mds_imagegrid_quota_storage_bytes', __('Storage Byte Quota', 'mds-imagegrid'), 'number', (string) $quota_storage, __('Maximum estimated storage footprint allowed for a remote ImageGrid job.', 'mds-imagegrid'));
    self::input_field('mds_imagegrid_quota_processing_credits', __('Processing Credit Quota', 'mds-imagegrid'), 'number', (string) $quota_credits, __('Maximum estimated processing credits allowed for a remote ImageGrid job.', 'mds-imagegrid'));
    ?>
    <p class="mds3-button-row">
        <?php if (!empty($connect_url)) : ?>
            <a class="button button-primary" href="<?php echo esc_url($connect_url); ?>"><?php esc_html_e('Connect ImageGrid', 'mds-imagegrid'); ?></a>
        <?php endif; ?>
        <?php if (!empty($app_url)) : ?>
            <a class="button" href="<?php echo esc_url($app_url . '/app/signup'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Create ImageGrid Account', 'mds-imagegrid'); ?></a>
            <a class="button" href="<?php echo esc_url($app_url . '/app/login'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Manage API Keys', 'mds-imagegrid'); ?></a>
        <?php endif; ?>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::settings_page_slug())); ?>">
            <?php echo esc_html($has_connection ? __('Manage Connection', 'mds-imagegrid') : __('Open ImageGrid Settings', 'mds-imagegrid')); ?>
        </a>
    </p>
</div>
