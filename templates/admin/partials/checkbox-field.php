<?php
/**
 * MDS3-compatible ImageGrid checkbox field.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}
$help_markup = '';
if ('' !== trim((string) ($help ?? ''))) {
    $help_markup = class_exists('\\MillionDollarScript\\Extensions\\Admin')
        ? \MillionDollarScript\Extensions\Admin::help($help, $label)
        : '<span class="mds3-help" tabindex="0" role="button" aria-label="' . esc_attr($label . ': ' . $help) . '" data-help="' . esc_attr($help) . '">?</span>';
}
?>
<p class="mds3-field">
    <label for="<?php echo esc_attr($field_id); ?>">
        <span class="mds3-field-label-text"><?php echo esc_html($label); ?></span>
        <?php echo $help_markup; ?>
    </label>
    <label class="mds3-checkbox-label">
        <input id="<?php echo esc_attr($field_id); ?>" type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($checked, true); ?> />
        <?php echo esc_html($description); ?>
    </label>
</p>
