<?php
/** ImageGrid connection tab. @package MillionDollarScript\Extensions\ImageGrid */
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="mds-imagegrid-panel" id="mds-imagegrid-connection">
    <div class="mds-imagegrid-panel-header">
        <div>
            <h2><?php esc_html_e('Connection', 'mds-imagegrid'); ?></h2>
            <p class="description"><?php esc_html_e('Use the hosted connection flow for normal setup, or enter a manual API key for local development and self-hosted testing.', 'mds-imagegrid'); ?></p>
        </div>
    </div>

    <?php if ($has_api_key && !$cdn_known) : ?>
        <div class="mds-imagegrid-callout mds-imagegrid-callout-warning" role="status">
            <?php esc_html_e('Account and CDN status have not been verified with the saved key. Test the connection before relying on remote rendering.', 'mds-imagegrid'); ?>
        </div>
    <?php endif; ?>

    <form class="mds-imagegrid-settings-form mds-imagegrid-narrow-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mds_imagegrid_save_settings'); ?>
        <input type="hidden" name="action" value="mds_imagegrid_save_settings">
        <input type="hidden" name="section" value="connection">
        <?php
        self::input_field('mds_imagegrid_api_url', __('API URL', 'mds-imagegrid'), 'url', $api_url, __('Use the hosted ImageGrid API URL unless this site connects to a local or self-hosted service.', 'mds-imagegrid'), '', __('This server-to-server URL handles render jobs. Browser account links always use the ImageGrid app URL.', 'mds-imagegrid'));
        self::input_field('mds_imagegrid_api_key', __('API key', 'mds-imagegrid'), 'password', '', $has_api_key ? __('A key is saved. Leave this blank to keep it.', 'mds-imagegrid') : __('Paste a site-scoped ImageGrid API key.', 'mds-imagegrid'), $has_api_key ? __('Saved key is hidden', 'mds-imagegrid') : __('Paste an ImageGrid API key', 'mds-imagegrid'), __('The key is stored server-side and must never be added to a public page or browser script.', 'mds-imagegrid'));
        if ($has_api_key) {
            self::checkbox_field('mds_imagegrid_clear_api_key', __('Clear saved key', 'mds-imagegrid'), false, __('Remove the local key when these settings are saved.', 'mds-imagegrid'));
        }
        ?>
        <p class="submit mds-imagegrid-settings-actions"><button type="submit" class="button button-primary"><?php esc_html_e('Save connection', 'mds-imagegrid'); ?></button></p>
    </form>

    <div class="mds-imagegrid-connect-tools">
        <?php if (!empty($connect_url)) : ?><a class="button button-primary" href="<?php echo esc_url($connect_url); ?>"><?php esc_html_e('Connect ImageGrid', 'mds-imagegrid'); ?></a><?php endif; ?>
        <form class="mds-imagegrid-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds_imagegrid_test_connection'); ?>
            <input type="hidden" name="action" value="mds_imagegrid_test_connection"><input type="hidden" name="section" value="connection">
            <button type="submit" class="button" <?php disabled(!$has_api_key); ?>><?php esc_html_e('Test connection', 'mds-imagegrid'); ?></button>
        </form>
        <?php if ($has_connection) : ?>
            <form class="mds-imagegrid-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mds_imagegrid_disconnect'); ?>
                <input type="hidden" name="action" value="mds_imagegrid_disconnect"><input type="hidden" name="section" value="connection">
                <button type="submit" class="button button-link-delete"><?php esc_html_e('Disconnect', 'mds-imagegrid'); ?></button>
            </form>
        <?php endif; ?>
        <?php if (!empty($app_url)) : ?>
            <span class="mds-imagegrid-action-spacer" aria-hidden="true"></span>
            <a class="button" href="<?php echo esc_url($app_url . '/app/signup'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Create account', 'mds-imagegrid'); ?></a>
            <a class="button" href="<?php echo esc_url($app_url . '/app/api-keys'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Manage API keys', 'mds-imagegrid'); ?></a>
        <?php endif; ?>
    </div>

    <?php if (!empty($connection)) : ?>
        <dl class="mds-imagegrid-facts mds-imagegrid-connection-facts">
            <?php
            if (!empty($connection['connection_id'])) {
                self::fact(__('Connection ID', 'mds-imagegrid'), $connection['connection_id']);
            }
            if (!empty($connection['account_slug'])) {
                self::fact(__('Account', 'mds-imagegrid'), $connection['account_slug']);
            }
            if (!empty($connection['connected_at'])) {
                self::fact(__('Connected', 'mds-imagegrid'), $connection['connected_at']);
            }
            ?>
        </dl>
    <?php endif; ?>
</section>
