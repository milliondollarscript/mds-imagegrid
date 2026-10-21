<?php
/**
 * Plugin Name: Million Dollar Script - ImageGrid Rendering
 * Plugin URI: https://milliondollarscript.com/extensions/imagegrid
 * Description: Free connector for rendering Million Dollar Script grids with the paid ImageGrid service.
 * Version: 1.0.9
 * Author: Million Dollar Script
 * Author URI: https://milliondollarscript.com
 * Text Domain: mds-imagegrid
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.1.3
 * Requires PHP: 8.1
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: million-dollar-script
 *
 * Requires MDS: 3.0.0
 * Requires MDS API: 1
 * Requires License: No
 * MDS Provides: rendering.imagegrid
 * MDS Requires: inventory.grid
 * MDS Requires Service: yes
 * MDS Setup Category: rendering
 * MDS Minimum Security Level: api_key_write
 * MDS API Manifest: https://imagegrid.dev/docs/openapi.json
 * MDS LLM Safe Actions: read_account read_render_status submit_render
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MDS_IMAGEGRID_VERSION')) {
    define('MDS_IMAGEGRID_VERSION', '1.0.9');
}

if (!defined('MDS_IMAGEGRID_FILE')) {
    define('MDS_IMAGEGRID_FILE', __FILE__);
}

if (!defined('MDS_IMAGEGRID_PATH')) {
    define('MDS_IMAGEGRID_PATH', plugin_dir_path(__FILE__));
}

if (!defined('MDS_IMAGEGRID_URL')) {
    define('MDS_IMAGEGRID_URL', plugin_dir_url(__FILE__));
}

if (!defined('MDS_IMAGEGRID_BASENAME')) {
    define('MDS_IMAGEGRID_BASENAME', plugin_basename(__FILE__));
}

/**
 * Resolve the default server API URL.
 *
 * Browser account links are handled separately by mds_imagegrid_app_url() so
 * Docker/local API URLs never leak into account or API-key links.
 *
 * @return string
 */
function mds_imagegrid_default_api_url() {
    if (defined('MDS_IMAGEGRID_API_URL') && MDS_IMAGEGRID_API_URL) {
        return untrailingslashit((string) MDS_IMAGEGRID_API_URL);
    }

    $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
    $default = in_array($environment, ['local', 'development'], true)
        ? 'http://host.docker.internal:18082'
        : 'https://api.imagegrid.dev';

    return untrailingslashit((string) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/imagegrid/default/api/url', ['mds_imagegrid_default_api_url'], $default));
}

/**
 * Resolve the browser-facing ImageGrid app URL.
 *
 * @return string
 */
function mds_imagegrid_app_url() {
    if (defined('MDS_IMAGEGRID_APP_URL') && MDS_IMAGEGRID_APP_URL) {
        $default = (string) MDS_IMAGEGRID_APP_URL;
    } else {
        $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        $default = in_array($environment, ['local', 'development'], true)
            ? 'http://localhost:18082'
            : 'https://imagegrid.dev';
    }

    return untrailingslashit((string) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/imagegrid/app/url', ['mds_imagegrid_app_url'], $default));
}

/**
 * Check whether MDS core is available.
 *
 * @return bool
 */
function mds_imagegrid_check_mds_active() {
    return class_exists('\\MillionDollarScript\\Core\\Runtime')
        && \MillionDollarScript\Core\Runtime::is_ready();
}

/**
 * Show a dependency notice when MDS core is unavailable.
 *
 * @return void
 */
function mds_imagegrid_missing_mds_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__('Million Dollar Script - ImageGrid Rendering', 'mds-imagegrid') . '</strong>: ';
    echo esc_html__('This extension requires the Million Dollar Script plugin to be installed and activated.', 'mds-imagegrid');
    echo ' <a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Manage Plugins', 'mds-imagegrid') . '</a>';
    echo '</p></div>';
}

/**
 * Load extension files.
 *
 * @return void
 */
function mds_imagegrid_load_files() {
    \MillionDollarScript\Extensions\Support::register_autoloader(
        __NAMESPACE__,
        MDS_IMAGEGRID_PATH . 'includes'
    );
}

/**
 * Initialize the extension when MDS is active.
 *
 * @return void
 */
function mds_imagegrid_init() {
    if (!mds_imagegrid_check_mds_active()) {
        add_action('admin_notices', __NAMESPACE__ . '\\mds_imagegrid_missing_mds_notice');
        return;
    }

    mds_imagegrid_load_files();
    Main::init();
}

/**
 * Register extension metadata with MDS.
 *
 * @return void
 */
function mds_imagegrid_register_extension() {
    if (!mds_imagegrid_check_mds_active()) {
        return;
    }

    mds_imagegrid_load_files();

    if (class_exists('\\MillionDollarScript\\Extensions\\Registry')) {
        \MillionDollarScript\Extensions\Registry::register([
            'id'               => 'mds-imagegrid',
            'name'             => 'ImageGrid Rendering',
            'description'      => 'Free connector for hosted ImageGrid rendering.',
            'version'          => MDS_IMAGEGRID_VERSION,
            'author'           => 'Million Dollar Script',
            'url'              => 'https://milliondollarscript.com/extensions/imagegrid',
            'main_file'        => __FILE__,
            'premium'          => false,
            'license_required' => false,
            'cleanup'          => [
                'description' => 'ImageGrid connection, rendering state, quotas, and cached failure records.',
            ],
            'capabilities'     => [
                'payment_gateway' => false,
                'admin_pages'     => true,
                'frontend_assets' => false,
                'remote_tiles'    => true,
                'imagegrid'        => true,
            ],
            'init_callback'    => ['\\MillionDollarScript\\Extensions\\ImageGrid\\Main', 'init'],
        ]);
    }
}

/**
 * Activation hook.
 *
 * @return void
 */
function mds_imagegrid_activate() {
    if (!mds_imagegrid_check_mds_active()) {
        deactivate_plugins(MDS_IMAGEGRID_BASENAME);
        wp_die(
            esc_html__('Million Dollar Script - ImageGrid Rendering requires the Million Dollar Script plugin to be installed and activated.', 'mds-imagegrid'),
            esc_html__('Plugin Activation Error', 'mds-imagegrid'),
            ['back_link' => true]
        );
    }

    $had_auto_render_option = '__missing__' !== get_option('mds_imagegrid_auto_render', '__missing__');

    add_option('mds_imagegrid_enabled', false);
    add_option('mds_imagegrid_auto_render', true);
    add_option('mds_imagegrid_failure_email_enabled', false);
    add_option('mds_imagegrid_render_failures', [], '', 'no');
    add_option(
        'mds_imagegrid_auto_render_preference',
        $had_auto_render_option ? 'legacy_preserved' : 'recommended_default',
        '',
        'no'
    );
    add_option('mds_imagegrid_api_url', mds_imagegrid_default_api_url());
    add_option('mds_imagegrid_api_key', '', '', 'no');
    add_option('mds_imagegrid_local_render_threshold_megapixels', 25);
    add_option('mds_imagegrid_quota_grid_megapixels', 100);
    add_option('mds_imagegrid_quota_source_megapixels', 250);
    add_option('mds_imagegrid_quota_tile_estimate', 12000);
    add_option('mds_imagegrid_quota_storage_bytes', 2147483648);
    add_option('mds_imagegrid_quota_processing_credits', 5000);
    add_option('mds_imagegrid_connection', [], '', 'no');
    add_option('mds_imagegrid_last_account', [], '', 'no');
    add_option('mds_imagegrid_last_connection', [], '', 'no');
    set_transient('mds_imagegrid_activated', true, 30);
}

/**
 * Deactivation hook.
 *
 * @return void
 */
function mds_imagegrid_deactivate() {
    delete_transient('mds_imagegrid_activated');
}

/**
 * Display activation notice.
 *
 * @return void
 */
function mds_imagegrid_activation_notice() {
    if (!get_transient('mds_imagegrid_activated')) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>';
    echo '<strong>' . esc_html__('Million Dollar Script - ImageGrid Rendering', 'mds-imagegrid') . '</strong>: ';
    echo esc_html__('Extension activated. Connect an ImageGrid account to enable automatic rendering for eligible grids.', 'mds-imagegrid');
    echo ' <a href="' . esc_url(admin_url('admin.php?page=mds3-imagegrid&section=connection')) . '">' . esc_html__('Connect ImageGrid', 'mds-imagegrid') . '</a>';
    echo '</p></div>';

    delete_transient('mds_imagegrid_activated');
}

register_activation_hook(MDS_IMAGEGRID_FILE, __NAMESPACE__ . '\\mds_imagegrid_activate');
register_deactivation_hook(MDS_IMAGEGRID_FILE, __NAMESPACE__ . '\\mds_imagegrid_deactivate');

add_action('plugins_loaded', __NAMESPACE__ . '\\mds_imagegrid_init', 20);
add_action('million-dollar-script/register/extensions', __NAMESPACE__ . '\\mds_imagegrid_register_extension');
add_action('admin_notices', __NAMESPACE__ . '\\mds_imagegrid_activation_notice');
add_action('init', function() {
    load_plugin_textdomain(
        'mds-imagegrid',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});
