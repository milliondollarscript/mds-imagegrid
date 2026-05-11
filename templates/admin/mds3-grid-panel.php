<?php
/**
 * ImageGrid controls embedded in the MDS3 grid editor.
 *
 * @package MDS\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="<?php echo esc_attr($classes); ?>" data-settings-panel="grid-imagegrid">
    <h2><?php esc_html_e('ImageGrid', 'mds-imagegrid'); ?></h2>
    <?php if ($notice) : ?>
        <div class="notice notice-<?php echo esc_attr(self::notice_class($notice['type'])); ?> inline">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endif; ?>
    <p><?php esc_html_e('Submit this grid for hosted ImageGrid rendering. Public delivery remains local unless the connected ImageGrid API key includes CDN access.', 'mds-imagegrid'); ?></p>

    <?php if (!$enabled || !$has_api_key) : ?>
        <p class="description"><?php esc_html_e('Enable ImageGrid and save an API key in Settings before submitting render jobs.', 'mds-imagegrid'); ?></p>
    <?php endif; ?>

    <div class="mds-imagegrid-grid-actions">
        <?php
        self::grid_action_form('mds_imagegrid_submit_grid_render', 'mds_imagegrid_submit_grid_render', $grid_id, __('Render With ImageGrid', 'mds-imagegrid'), 'button button-primary', !$grid_id || !$enabled || !$has_api_key);
        self::grid_action_form('mds_imagegrid_refresh_job', 'mds_imagegrid_refresh_job', $grid_id, __('Refresh Job', 'mds-imagegrid'), 'button', !$grid_id || empty($state['job_id']));
        ?>
    </div>

    <dl class="mds-imagegrid-facts mds-imagegrid-grid-state">
        <?php
        self::fact(__('State', 'mds-imagegrid'), $state['status'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Job ID', 'mds-imagegrid'), $state['job_id'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Tileset ID', 'mds-imagegrid'), $state['tileset_id'] ?? __('None', 'mds-imagegrid'));
        self::fact(__('Tiles', 'mds-imagegrid'), !empty($state['total_tiles']) ? number_format_i18n((int) $state['total_tiles']) : __('Unknown', 'mds-imagegrid'));
        self::fact(__('Updated', 'mds-imagegrid'), $state['updated_at'] ?? __('Never', 'mds-imagegrid'));
        ?>
    </dl>

    <?php if (!empty($state['last_error'])) : ?>
        <p class="mds-imagegrid-error"><?php echo esc_html($state['last_error']); ?></p>
    <?php endif; ?>
</section>
