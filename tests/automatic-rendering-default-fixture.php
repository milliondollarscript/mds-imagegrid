<?php
/**
 * ImageGrid automatic-rendering default and upgrade fixture.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

use MillionDollarScript\Extensions\ImageGrid\Main;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(Main::class)) {
    throw new RuntimeException('The ImageGrid extension must be active.');
}

$keys = [
    'mds_imagegrid_enabled',
    'mds_imagegrid_auto_render',
    'mds_imagegrid_auto_render_preference',
];
$backup = [];
foreach ($keys as $key) {
    $backup[$key] = get_option($key, '__missing__');
}

$restore = static function() use ($backup) {
    foreach ($backup as $key => $value) {
        if ('__missing__' === $value) {
            delete_option($key);
        } else {
            update_option($key, $value, false);
        }
    }
    delete_transient('mds_imagegrid_activated');
};

try {
    update_option('mds_imagegrid_enabled', false, false);
    update_option('mds_imagegrid_auto_render', false, false);
    delete_option('mds_imagegrid_auto_render_preference');

    $migration = new ReflectionMethod(Main::class, 'migrate_auto_render_preference');
    $migration->setAccessible(true);
    $migration->invoke(Main::instance());
    if ('legacy_preserved' !== get_option('mds_imagegrid_auto_render_preference') || get_option('mds_imagegrid_auto_render')) {
        throw new RuntimeException('Upgrade migration did not preserve the existing automatic-rendering choice.');
    }
    if (Main::apply_connected_defaults()) {
        throw new RuntimeException('A legacy installation must not be opted into paid automatic rendering.');
    }

    delete_option('mds_imagegrid_auto_render_preference');
    \MillionDollarScript\Extensions\ImageGrid\mds_imagegrid_activate();
    if ('legacy_preserved' !== get_option('mds_imagegrid_auto_render_preference') || get_option('mds_imagegrid_auto_render')) {
        throw new RuntimeException('Reactivating an older installation did not preserve its automatic-rendering choice.');
    }

    delete_option('mds_imagegrid_auto_render');
    delete_option('mds_imagegrid_auto_render_preference');
    \MillionDollarScript\Extensions\ImageGrid\mds_imagegrid_activate();
    if (!get_option('mds_imagegrid_auto_render') || 'recommended_default' !== get_option('mds_imagegrid_auto_render_preference')) {
        throw new RuntimeException('Fresh activation did not recommend automatic rendering.');
    }
    update_option('mds_imagegrid_enabled', false, false);
    update_option('mds_imagegrid_auto_render', false, false);
    if (!Main::apply_connected_defaults() || !get_option('mds_imagegrid_enabled') || !get_option('mds_imagegrid_auto_render')) {
        throw new RuntimeException('A fresh verified connection did not enable recommended rendering defaults.');
    }

    update_option('mds_imagegrid_auto_render_preference', 'explicit', false);
    update_option('mds_imagegrid_auto_render', false, false);
    if (Main::apply_connected_defaults() || get_option('mds_imagegrid_auto_render')) {
        throw new RuntimeException('An explicit off preference was overwritten.');
    }
} finally {
    $restore();
}

echo wp_json_encode([
    'ok' => true,
    'fresh_default' => true,
    'legacy_preserved' => true,
    'explicit_off_preserved' => true,
]) . PHP_EOL;
