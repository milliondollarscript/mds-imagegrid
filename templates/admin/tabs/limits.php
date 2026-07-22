<?php
/** ImageGrid local safeguards tab. @package MillionDollarScript\Extensions\ImageGrid */
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="mds-imagegrid-panel">
    <div class="mds-imagegrid-panel-header"><div><h2><?php esc_html_e('Local rendering safeguards', 'mds-imagegrid'); ?></h2><p class="description"><?php esc_html_e('Stop unexpectedly large remote submissions before they leave WordPress. These caps cannot increase the connected ImageGrid plan limits.', 'mds-imagegrid'); ?></p></div></div>
    <div class="mds-imagegrid-callout" role="note"><?php esc_html_e('ImageGrid separately enforces account plan, billing, and usage limits. Set local safeguards at or below those limits when you want predictable submissions. A plan reset changes only safeguards that have a matching service limit.', 'mds-imagegrid'); ?></div>
    <form class="mds-imagegrid-settings-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mds_imagegrid_save_settings'); ?><input type="hidden" name="action" value="mds_imagegrid_save_settings"><input type="hidden" name="section" value="limits">
        <div class="mds-imagegrid-quota-grid">
            <?php
            self::input_field('mds_imagegrid_quota_grid_megapixels', __('Maximum grid megapixels', 'mds-imagegrid'), 'number', (string) $quota_grid, __('Maximum grid size accepted for one remote submission.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_quota_source_megapixels', __('Maximum source megapixels', 'mds-imagegrid'), 'number', (string) $quota_source, __('Maximum combined source-image size accepted for one remote submission.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_quota_tile_estimate', __('Maximum estimated tiles', 'mds-imagegrid'), 'number', (string) $quota_tiles, __('Maximum estimated tile count accepted for one remote submission.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_quota_storage_bytes', __('Maximum estimated storage bytes', 'mds-imagegrid'), 'number', (string) $quota_storage, __('Maximum estimated storage accepted for one remote submission.', 'mds-imagegrid'), '', __('This is a pre-submission estimate, not the account storage balance.', 'mds-imagegrid'));
            self::input_field('mds_imagegrid_quota_processing_credits', __('Maximum estimated processing credits', 'mds-imagegrid'), 'number', (string) $quota_credits, __('Maximum estimated processing credits accepted for one remote submission.', 'mds-imagegrid'), '', __('This cap applies to one estimated job and is not the account remaining-credit balance.', 'mds-imagegrid'));
            ?>
        </div>
        <p class="submit mds-imagegrid-settings-actions"><button type="submit" class="button button-primary"><?php esc_html_e('Save local safeguards', 'mds-imagegrid'); ?></button></p>
    </form>
    <div class="mds-imagegrid-secondary-actions">
        <?php if (!empty($plan_limit_options)) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('mds_imagegrid_reset_plan_limits'); ?><input type="hidden" name="action" value="mds_imagegrid_reset_plan_limits"><input type="hidden" name="section" value="limits"><button type="submit" class="button" onclick="return window.confirm('<?php echo esc_js(__('Replace matching local safeguards with the current ImageGrid plan limits?', 'mds-imagegrid')); ?>');"><?php esc_html_e('Reset to plan limits', 'mds-imagegrid'); ?></button></form>
        <?php else : ?>
            <p class="description"><?php esc_html_e('Reset to plan limits is unavailable because ImageGrid has not supplied authoritative limits to this site.', 'mds-imagegrid'); ?> <?php if (!empty($app_url)) : ?><a href="<?php echo esc_url($app_url . '/app'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Review plan usage in ImageGrid', 'mds-imagegrid'); ?></a><?php endif; ?></p>
        <?php endif; ?>
    </div>
</section>
