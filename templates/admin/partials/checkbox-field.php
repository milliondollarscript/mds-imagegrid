<?php
/**
 * MDS3-compatible ImageGrid checkbox field.
 *
 * @package MDS\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<p class="mds3-field">
    <label for="<?php echo esc_attr($field_id); ?>">
        <span class="mds3-field-label-text"><?php echo esc_html($label); ?></span>
    </label>
    <label class="mds3-checkbox-label">
        <input id="<?php echo esc_attr($field_id); ?>" type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($checked, true); ?> />
        <?php echo esc_html($description); ?>
    </label>
</p>
