<?php
/** ImageGrid uninstall cleanup. */
if (!defined('WP_UNINSTALL_PLUGIN') || !class_exists('\\MillionDollarScript\\Extensions\\CleanupPolicy')) { exit; }
\MillionDollarScript\Extensions\CleanupPolicy::cleanup('mds-imagegrid', [
    'option_prefixes' => ['mds_imagegrid_'],
    'transient_prefixes' => ['mds_imagegrid_'],
]);
