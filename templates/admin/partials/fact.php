<?php
/**
 * ImageGrid facts-list item.
 *
 * @package MDS\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div>
    <dt><?php echo esc_html($label); ?></dt>
    <dd><?php echo esc_html((string) $value); ?></dd>
</div>
