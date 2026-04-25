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
 * @var array $grids
 * @var int   $selected_grid_id
 * @var array $selected_state
 * @var string $app_url
 * @var array|null $notice
 */

if (!defined('ABSPATH')) {
    exit;
}

$account = isset($last_account['account']) && is_array($last_account['account']) ? $last_account['account'] : [];
$api_key = isset($last_account['api_key']) && is_array($last_account['api_key']) ? $last_account['api_key'] : [];
$connection_status = $last_connection['status'] ?? 'not_connected';
?>

<div class="wrap mds-imagegrid-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('ImageGrid', 'mds-imagegrid'); ?></h1>
    <p class="mds-imagegrid-lede">
        <?php esc_html_e('Connect MDS zoomable grids to ImageGrid for remote rendering and authenticated tile delivery.', 'mds-imagegrid'); ?>
    </p>

    <?php if ($notice) : ?>
        <div class="notice notice-<?php echo esc_attr('error' === $notice['type'] ? 'error' : 'success'); ?> is-dismissible">
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
                                <?php esc_html_e('Serve ready remote tiles for grids that have an ImageGrid tileset.', 'mds-imagegrid'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Automatic rendering', 'mds-imagegrid'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="mds_imagegrid_auto_render" value="1" <?php checked($auto_render); ?>>
                                <?php esc_html_e('Submit a remote render job when MDS requests tile generation.', 'mds-imagegrid'); ?>
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
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'mds-imagegrid'); ?></button>
                    <?php if (!empty($app_url)) : ?>
                        <a class="button" href="<?php echo esc_url($app_url . '/app/signup'); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Create ImageGrid Account', 'mds-imagegrid'); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url($app_url . '/app/login'); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Manage API Keys', 'mds-imagegrid'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>

            <form class="mds-imagegrid-actions" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mds_imagegrid_test_connection'); ?>
                <input type="hidden" name="action" value="mds_imagegrid_test_connection">
                <button type="submit" class="button" <?php disabled(!$has_api_key); ?>>
                    <?php esc_html_e('Test Connection', 'mds-imagegrid'); ?>
                </button>
            </form>
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
            <p><?php esc_html_e('No MDS grids were found yet.', 'mds-imagegrid'); ?></p>
        <?php else : ?>
            <form method="get" class="mds-imagegrid-grid-select">
                <input type="hidden" name="page" value="mds-imagegrid">
                <label for="mds_imagegrid_grid_id"><?php esc_html_e('Grid', 'mds-imagegrid'); ?></label>
                <select id="mds_imagegrid_grid_id" name="grid_id" onchange="this.form.submit()">
                    <?php foreach ($grids as $grid) : ?>
                        <?php
                        if (!is_object($grid) || !method_exists($grid, 'get_id')) {
                            continue;
                        }
                        $grid_id = absint($grid->get_id());
                        ?>
                        <option value="<?php echo esc_attr($grid_id); ?>" <?php selected($selected_grid_id, $grid_id); ?>>
                            <?php echo esc_html(sprintf('%s (#%d)', $grid->get_title(), $grid_id)); ?>
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
