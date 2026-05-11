<?php
/**
 * ImageGrid settings template.
 *
 * @package MDS\Extensions\ImageGrid
 *
 * @var bool  $enabled
 * @var bool  $auto_render
 * @var string $api_url
 * @var bool  $has_api_key
 * @var array $last_account
 * @var array $last_connection
 * @var array $connection
 * @var array $grids
 * @var int   $selected_grid_id
 * @var array $selected_state
 * @var string $app_url
 * @var string $connect_url
 * @var array|null $notice
 * @var string $page_slug
 */

if (!defined('ABSPATH')) {
    exit;
}

$account = isset($last_account['account']) && is_array($last_account['account']) ? $last_account['account'] : [];
$api_key = isset($last_account['api_key']) && is_array($last_account['api_key']) ? $last_account['api_key'] : [];
$connection_status = $last_connection['status'] ?? 'not_connected';
$has_connection = $has_api_key || !empty($connection);
?>

<div class="wrap mds-imagegrid-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('ImageGrid', 'mds-imagegrid'); ?></h1>
    <p class="mds-imagegrid-lede">
        <?php esc_html_e('Connect Million Dollar Script zoomable grids to ImageGrid for hosted render jobs. Public delivery remains local unless the connected ImageGrid API key includes CDN access.', 'mds-imagegrid'); ?>
    </p>

    <?php if ($notice) : ?>
        <div class="notice notice-<?php echo esc_attr(\MDS\Extensions\ImageGrid\Admin::notice_class($notice['type'])); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <div class="mds-imagegrid-layout">
        <section class="mds-imagegrid-panel">
            <div class="mds-imagegrid-panel-header">
                <h2><?php esc_html_e('Connection', 'mds-imagegrid'); ?></h2>
                <span class="mds-imagegrid-status mds-imagegrid-status-<?php echo esc_attr($connection_status); ?>">
                    <?php echo esc_html('connected' === $connection_status ? __('Connected', 'mds-imagegrid') : __('Not Connected', 'mds-imagegrid')); ?>
                </span>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mds_imagegrid_save_settings'); ?>
                <input type="hidden" name="action" value="mds_imagegrid_save_settings">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable ImageGrid', 'mds-imagegrid'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mds_imagegrid_enabled" value="1" <?php checked($enabled); ?>>
                                <?php esc_html_e('Allow Million Dollar Script to submit eligible large-grid render jobs to ImageGrid.', 'mds-imagegrid'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Automatic rendering', 'mds-imagegrid'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mds_imagegrid_auto_render" value="1" <?php checked($auto_render); ?>>
                                <?php esc_html_e('Submit a remote render job when Million Dollar Script requests tile generation.', 'mds-imagegrid'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mds_imagegrid_api_url"><?php esc_html_e('API URL', 'mds-imagegrid'); ?></label></th>
                        <td>
                            <input type="url" class="regular-text" id="mds_imagegrid_api_url" name="mds_imagegrid_api_url" value="<?php echo esc_attr($api_url); ?>" placeholder="https://api.imagegrid.dev">
                            <p class="description"><?php esc_html_e('Server API URL. This can point at local Docker during development and is never used for browser account links.', 'mds-imagegrid'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mds_imagegrid_api_key"><?php esc_html_e('API key', 'mds-imagegrid'); ?></label></th>
                        <td>
                            <input type="password" class="regular-text" id="mds_imagegrid_api_key" name="mds_imagegrid_api_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr($has_api_key ? __('Saved key is hidden', 'mds-imagegrid') : __('Paste an ImageGrid API key', 'mds-imagegrid')); ?>">
                            <?php if ($has_api_key) : ?>
                                <label class="mds-imagegrid-inline-control">
                                    <input type="checkbox" name="mds_imagegrid_clear_api_key" value="1">
                                    <?php esc_html_e('Clear saved key', 'mds-imagegrid'); ?>
                                </label>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mds_imagegrid_local_render_threshold_megapixels"><?php esc_html_e('Local Render Threshold MP', 'mds-imagegrid'); ?></label></th>
                        <td>
                            <input type="number" class="small-text" id="mds_imagegrid_local_render_threshold_megapixels" name="mds_imagegrid_local_render_threshold_megapixels" value="<?php echo esc_attr($threshold); ?>" min="0" step="0.01">
                            <p class="description"><?php esc_html_e('Use local rendering at or below this grid size. Larger grids can use ImageGrid when configured.', 'mds-imagegrid'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mds_imagegrid_quota_grid_megapixels"><?php esc_html_e('Grid Megapixel Quota', 'mds-imagegrid'); ?></label></th>
                        <td><input type="number" class="small-text" id="mds_imagegrid_quota_grid_megapixels" name="mds_imagegrid_quota_grid_megapixels" value="<?php echo esc_attr($quota_grid); ?>" min="0" step="0.01"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mds_imagegrid_quota_source_megapixels"><?php esc_html_e('Source Megapixel Quota', 'mds-imagegrid'); ?></label></th>
                        <td><input type="number" class="small-text" id="mds_imagegrid_quota_source_megapixels" name="mds_imagegrid_quota_source_megapixels" value="<?php echo esc_attr($quota_source); ?>" min="0" step="0.01"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mds_imagegrid_quota_tile_estimate"><?php esc_html_e('Tile Estimate Quota', 'mds-imagegrid'); ?></label></th>
                        <td><input type="number" class="small-text" id="mds_imagegrid_quota_tile_estimate" name="mds_imagegrid_quota_tile_estimate" value="<?php echo esc_attr($quota_tiles); ?>" min="0" step="1"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mds_imagegrid_quota_storage_bytes"><?php esc_html_e('Storage Byte Quota', 'mds-imagegrid'); ?></label></th>
                        <td><input type="number" class="regular-text" id="mds_imagegrid_quota_storage_bytes" name="mds_imagegrid_quota_storage_bytes" value="<?php echo esc_attr($quota_storage); ?>" min="0" step="1"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mds_imagegrid_quota_processing_credits"><?php esc_html_e('Processing Credit Quota', 'mds-imagegrid'); ?></label></th>
                        <td><input type="number" class="small-text" id="mds_imagegrid_quota_processing_credits" name="mds_imagegrid_quota_processing_credits" value="<?php echo esc_attr($quota_credits); ?>" min="0" step="0.01"></td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'mds-imagegrid'); ?></button>
                </p>
            </form>

            <div class="mds-imagegrid-connect-tools">
                <?php if (!empty($connect_url)) : ?>
                    <a class="button button-primary" href="<?php echo esc_url($connect_url); ?>">
                        <?php esc_html_e('Connect ImageGrid', 'mds-imagegrid'); ?>
                    </a>
                <?php endif; ?>

                <form class="mds-imagegrid-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds_imagegrid_test_connection'); ?>
                    <input type="hidden" name="action" value="mds_imagegrid_test_connection">
                    <button type="submit" class="button" <?php disabled(!$has_api_key); ?>>
                        <?php esc_html_e('Test Connection', 'mds-imagegrid'); ?>
                    </button>
                </form>

                <?php if ($has_connection) : ?>
                    <form class="mds-imagegrid-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mds_imagegrid_disconnect'); ?>
                        <input type="hidden" name="action" value="mds_imagegrid_disconnect">
                        <button type="submit" class="button button-link-delete">
                            <?php esc_html_e('Disconnect', 'mds-imagegrid'); ?>
                        </button>
                    </form>
                <?php endif; ?>

                <span class="mds-imagegrid-action-spacer" aria-hidden="true"></span>

                <?php if (!empty($app_url)) : ?>
                    <a class="button" href="<?php echo esc_url($app_url . '/app/signup'); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Create ImageGrid Account', 'mds-imagegrid'); ?>
                    </a>
                    <a class="button" href="<?php echo esc_url($app_url . '/app/login'); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Manage API Keys', 'mds-imagegrid'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <p class="description mds-imagegrid-connect-note">
                <?php esc_html_e('Use Connect ImageGrid when the hosted ImageGrid app supports browser-based site connection. Manual API keys remain available for local development and self-hosted testing.', 'mds-imagegrid'); ?>
            </p>

            <?php if (!empty($connection)) : ?>
                <dl class="mds-imagegrid-facts mds-imagegrid-connection-facts">
                    <?php if (!empty($connection['connection_id'])) : ?>
                        <div>
                            <dt><?php esc_html_e('Connection ID', 'mds-imagegrid'); ?></dt>
                            <dd><?php echo esc_html($connection['connection_id']); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($connection['account_slug'])) : ?>
                        <div>
                            <dt><?php esc_html_e('Account', 'mds-imagegrid'); ?></dt>
                            <dd><?php echo esc_html($connection['account_slug']); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($connection['connected_at'])) : ?>
                        <div>
                            <dt><?php esc_html_e('Connected', 'mds-imagegrid'); ?></dt>
                            <dd><?php echo esc_html($connection['connected_at']); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>

        </section>

        <aside class="mds-imagegrid-panel">
            <h2><?php esc_html_e('Account', 'mds-imagegrid'); ?></h2>
            <?php if (!empty($account)) : ?>
                <dl class="mds-imagegrid-facts">
                    <div>
                        <dt><?php esc_html_e('Workspace', 'mds-imagegrid'); ?></dt>
                        <dd><?php echo esc_html($account['display_name'] ?? $account['slug'] ?? __('Unknown', 'mds-imagegrid')); ?></dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('API key', 'mds-imagegrid'); ?></dt>
                        <dd><?php echo esc_html($api_key['name'] ?? __('Connected key', 'mds-imagegrid')); ?></dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('Role', 'mds-imagegrid'); ?></dt>
                        <dd><?php echo esc_html($api_key['role_code'] ?? __('Unknown', 'mds-imagegrid')); ?></dd>
                    </div>
                </dl>
            <?php else : ?>
                <p><?php esc_html_e('Test the connection after saving an API key to show the linked ImageGrid workspace.', 'mds-imagegrid'); ?></p>
            <?php endif; ?>
        </aside>
    </div>

    <section class="mds-imagegrid-panel mds-imagegrid-grid-panel">
        <div class="mds-imagegrid-panel-header">
            <h2><?php esc_html_e('Grid Rendering', 'mds-imagegrid'); ?></h2>
            <?php if ($selected_grid_id && !empty($selected_state['status'])) : ?>
                <span class="mds-imagegrid-status"><?php echo esc_html($selected_state['status']); ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($grids)) : ?>
            <p><?php esc_html_e('No Million Dollar Script grids were found yet.', 'mds-imagegrid'); ?></p>
        <?php else : ?>
            <form method="get" class="mds-imagegrid-grid-select">
                <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>">
                <label for="mds_imagegrid_grid_id"><?php esc_html_e('Grid', 'mds-imagegrid'); ?></label>
                <select id="mds_imagegrid_grid_id" name="grid_id" onchange="this.form.submit()">
                    <?php foreach ($grids as $grid) : ?>
                        <?php
                        $grid_id = \MDS\Extensions\ImageGrid\Admin::grid_id($grid);
                        if (!$grid_id) {
                            continue;
                        }
                        $grid_title = \MDS\Extensions\ImageGrid\Admin::grid_title($grid);
                        ?>
                        <option value="<?php echo esc_attr($grid_id); ?>" <?php selected($selected_grid_id, $grid_id); ?>>
                            <?php echo esc_html(sprintf('%s (#%d)', $grid_title ?: __('Untitled grid', 'mds-imagegrid'), $grid_id)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="mds-imagegrid-grid-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds_imagegrid_submit_grid_render'); ?>
                    <input type="hidden" name="action" value="mds_imagegrid_submit_grid_render">
                    <input type="hidden" name="grid_id" value="<?php echo esc_attr($selected_grid_id); ?>">
                    <button type="submit" class="button button-primary" <?php disabled(!$selected_grid_id || !$enabled || !$has_api_key); ?>>
                        <?php esc_html_e('Render With ImageGrid', 'mds-imagegrid'); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds_imagegrid_refresh_job'); ?>
                    <input type="hidden" name="action" value="mds_imagegrid_refresh_job">
                    <input type="hidden" name="grid_id" value="<?php echo esc_attr($selected_grid_id); ?>">
                    <button type="submit" class="button" <?php disabled(!$selected_grid_id || empty($selected_state['job_id'])); ?>>
                        <?php esc_html_e('Refresh Job', 'mds-imagegrid'); ?>
                    </button>
                </form>
            </div>

            <dl class="mds-imagegrid-facts mds-imagegrid-grid-state">
                <div>
                    <dt><?php esc_html_e('State', 'mds-imagegrid'); ?></dt>
                    <dd><?php echo esc_html($selected_state['status'] ?? __('None', 'mds-imagegrid')); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Job ID', 'mds-imagegrid'); ?></dt>
                    <dd><?php echo esc_html($selected_state['job_id'] ?? __('None', 'mds-imagegrid')); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Tileset ID', 'mds-imagegrid'); ?></dt>
                    <dd><?php echo esc_html($selected_state['tileset_id'] ?? __('None', 'mds-imagegrid')); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Tiles', 'mds-imagegrid'); ?></dt>
                    <dd><?php echo esc_html(!empty($selected_state['total_tiles']) ? number_format_i18n((int) $selected_state['total_tiles']) : __('Unknown', 'mds-imagegrid')); ?></dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Preflight', 'mds-imagegrid'); ?></dt>
                    <dd>
                        <?php
                        $preflight = isset($selected_state['preflight']) && is_array($selected_state['preflight']) ? $selected_state['preflight'] : [];
                        echo esc_html(isset($preflight['ok']) ? ($preflight['ok'] ? __('Passed', 'mds-imagegrid') : __('Failed', 'mds-imagegrid')) : __('Not run', 'mds-imagegrid'));
                        if (!empty($preflight['failing_key'])) {
                            echo esc_html(' (' . $preflight['failing_key'] . ')');
                        }
                        ?>
                    </dd>
                </div>
                <div>
                    <dt><?php esc_html_e('Updated', 'mds-imagegrid'); ?></dt>
                    <dd><?php echo esc_html($selected_state['updated_at'] ?? __('Never', 'mds-imagegrid')); ?></dd>
                </div>
            </dl>

            <?php if (!empty($selected_state['last_error'])) : ?>
                <p class="mds-imagegrid-error"><?php echo esc_html($selected_state['last_error']); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
