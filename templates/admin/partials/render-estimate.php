<?php
/**
 * ImageGrid local render estimate.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}

$estimate = is_array($estimate ?? null) ? $estimate : [];
if (!$estimate) {
    return;
}

$width = max(1, absint($estimate['width'] ?? 1));
$height = max(1, absint($estimate['height'] ?? 1));
$source_count = absint($estimate['source_image_count'] ?? 0);
$base_credits = absint($estimate['processing_credits'] ?? 0);
$workflow_credits = absint($estimate['estimated_workflow_processing_credits'] ?? $base_credits);
?>
<section class="mds-imagegrid-estimate" aria-labelledby="mds-imagegrid-estimate-heading">
    <div class="mds-imagegrid-panel-header">
        <div>
            <h3 id="mds-imagegrid-estimate-heading"><?php esc_html_e('Submission estimate', 'mds-imagegrid'); ?></h3>
            <p class="description"><?php esc_html_e('Review the expected work before sending this grid. The connected account still makes the final quota and capacity decision.', 'mds-imagegrid'); ?></p>
        </div>
    </div>
    <dl class="mds-imagegrid-facts">
        <?php
        self::fact(__('Dimensions', 'mds-imagegrid'), sprintf('%s × %s px', number_format_i18n($width), number_format_i18n($height)));
        self::fact(__('Grid size', 'mds-imagegrid'), sprintf(__('%s MP', 'mds-imagegrid'), number_format_i18n((float) ($estimate['grid_megapixels'] ?? 0), 2)));
        self::fact(
            __('Tile pyramid', 'mds-imagegrid'),
            sprintf(
                /* translators: 1: level count, 2: estimated tile count */
                __('%1$s levels · %2$s estimated tiles', 'mds-imagegrid'),
                number_format_i18n(absint($estimate['tile_level_count'] ?? 0)),
                number_format_i18n(absint($estimate['tile_estimate'] ?? 0))
            )
        );
        self::fact(
            __('Source artwork', 'mds-imagegrid'),
            $source_count
                ? sprintf(
                    /* translators: 1: source image count, 2: combined source megapixels */
                    _n('%1$s image · %2$s MP combined', '%1$s images · %2$s MP combined', $source_count, 'mds-imagegrid'),
                    number_format_i18n($source_count),
                    number_format_i18n((float) ($estimate['source_megapixels'] ?? 0), 2)
                )
                : __('No source artwork in this submission', 'mds-imagegrid')
        );
        self::fact(__('Base render credits', 'mds-imagegrid'), number_format_i18n($base_credits));
        if ($workflow_credits > $base_credits) {
            self::fact(__('Estimated workflow credits', 'mds-imagegrid'), number_format_i18n($workflow_credits));
        }
        self::fact(__('Uncompressed canvas', 'mds-imagegrid'), size_format((int) ($estimate['uncompressed_canvas_bytes'] ?? 0), 1));
        self::fact(__('Uncompressed pyramid', 'mds-imagegrid'), size_format((int) ($estimate['uncompressed_pyramid_bytes'] ?? 0), 1));
        self::fact(__('Conservative storage ceiling', 'mds-imagegrid'), size_format((int) ($estimate['storage_estimate_bytes'] ?? 0), 1));
        ?>
    </dl>
    <p class="description"><?php esc_html_e('Stored tiles and CDN transfers are compressed, so actual bytes depend on artwork, format, cache hits, zoom depth, and visitor activity. Review measured usage and pricing in the connected ImageGrid account.', 'mds-imagegrid'); ?></p>
</section>
