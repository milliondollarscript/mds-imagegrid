<?php
/**
 * Plugin Name: MDS ImageGrid
 * Plugin URI: https://milliondollarscript.com/extensions/imagegrid
 * Description: Connects Million Dollar Script grids to the hosted ImageGrid service for remote grid rendering and tile delivery.
 * Version: 1.0.0
 * Author: Million Dollar Script
 * Author URI: https://milliondollarscript.com
 * Text Domain: mds-imagegrid
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: Commercial
 * License URI: https://milliondollarscript.com/license
 * Requires Plugins: million-dollar-script
 *
 * Requires MDS: 3.0.0
 * Requires License: Yes
 *
 * @package MDS\Extensions\ImageGrid
 */

namespace MDS\Extensions\ImageGrid;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MDS_IMAGEGRID_VERSION')) {
    define('MDS_IMAGEGRID_VERSION', '1.0.0');
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
 * Resolve the default service URL.
 *
 * Local/development WordPress environments use the local ImageGrid API noted for
 * this workspace. Production can override the URL with MDS_IMAGEGRID_API_URL or
 * the mds_imagegrid_default_api_url filter.
 *
 * @return string
 */
function mds_imagegrid_default_api_url() {
    if (defined('MDS_IMAGEGRID_API_URL') && MDS_IMAGEGRID_API_URL) {
        return untrailingslashit((string) MDS_IMAGEGRID_API_URL);
    }

    $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
    $default = in_array($environment, ['local', 'development'], true)
        ? 'http://localhost:18082'
        : 'https://imagegrid.milliondollarscript.com';

    return untrailingslashit((string) apply_filters('mds_imagegrid_default_api_url', $default));
}

/**
 * Check whether MDS core is available.
 *
 * @return bool
 */
function mds_imagegrid_check_mds_active() {
    return class_exists('\\MDS\\Core\\Plugin') && function_exists('mds_initialize');
}

/**
 * Show a dependency notice when MDS core is unavailable.
 *
 * @return void
 */
function mds_imagegrid_missing_mds_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__('MDS ImageGrid', 'mds-imagegrid') . '</strong>: ';
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
    require_once MDS_IMAGEGRID_PATH . 'includes/APIClient.php';
    require_once MDS_IMAGEGRID_PATH . 'includes/Admin.php';
    require_once MDS_IMAGEGRID_PATH . 'includes/Main.php';
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

    if (class_exists('\\MDS\\Core\\Extensions\\Manager')) {
        \MDS\Core\Extensions\Manager::register([
            'id'               => 'mds-imagegrid',
            'name'             => __('ImageGrid', 'mds-imagegrid'),
            'description'      => __('Hosted ImageGrid rendering and tile delivery for MDS zoomable grids.', 'mds-imagegrid'),
            'version'          => MDS_IMAGEGRID_VERSION,
            'author'           => 'Million Dollar Script',
            'url'              => 'https://milliondollarscript.com/extensions/imagegrid',
            'main_file'        => __FILE__,
            'premium'          => true,
            'license_required' => true,
            'capabilities'     => [
                'payment_gateway' => false,
                'admin_pages'     => true,
                'frontend_assets' => false,
                'remote_tiles'    => true,
                'imagegrid'        => true,
            ],
            'init_callback'    => ['\\MDS\\Extensions\\ImageGrid\\Main', 'init'],
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
            esc_html__('MDS ImageGrid requires the Million Dollar Script plugin to be installed and activated.', 'mds-imagegrid'),
            esc_html__('Plugin Activation Error', 'mds-imagegrid'),
            ['back_link' => true]
        );
    }

    add_option('mds_imagegrid_enabled', false);
    add_option('mds_imagegrid_auto_render', false);
    add_option('mds_imagegrid_api_url', mds_imagegrid_default_api_url());
    add_option('mds_imagegrid_api_key', '', '', 'no');
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
    echo '<strong>' . esc_html__('MDS ImageGrid', 'mds-imagegrid') . '</strong>: ';
    echo esc_html__('Extension activated. Add your ImageGrid API key to enable remote grid rendering.', 'mds-imagegrid');
    echo ' <a href="' . esc_url(admin_url('admin.php?page=mds-imagegrid')) . '">' . esc_html__('Settings', 'mds-imagegrid') . '</a>';
    echo '</p></div>';

    delete_transient('mds_imagegrid_activated');
}

register_activation_hook(MDS_IMAGEGRID_FILE, __NAMESPACE__ . '\\mds_imagegrid_activate');
register_deactivation_hook(MDS_IMAGEGRID_FILE, __NAMESPACE__ . '\\mds_imagegrid_deactivate');

add_action('plugins_loaded', __NAMESPACE__ . '\\mds_imagegrid_init', 20);
add_action('mds_register_extensions', __NAMESPACE__ . '\\mds_imagegrid_register_extension');
add_action('admin_notices', __NAMESPACE__ . '\\mds_imagegrid_activation_notice');
add_action('init', function() {
    load_plugin_textdomain(
        'mds-imagegrid',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});
