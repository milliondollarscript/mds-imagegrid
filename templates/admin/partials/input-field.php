<?php
/**
 * MDS3-compatible ImageGrid input field.
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
    <label for="<?php echo esc_attr($name); ?>">
        <span class="mds3-field-label-text"><?php echo esc_html($label); ?></span>
        <?php echo $help_markup; ?>
    </label>
    <?php if ('password' === $type) : ?>
        <input
            type="text"
            name="<?php echo esc_attr($name); ?>_autocomplete_context"
            value="ImageGrid API key"
            autocomplete="username"
            hidden
            readonly
        />
    <?php endif; ?>
    <input
        id="<?php echo esc_attr($name); ?>"
        type="<?php echo esc_attr($type); ?>"
        name="<?php echo esc_attr($name); ?>"
        value="<?php echo esc_attr($value); ?>"
        placeholder="<?php echo esc_attr($placeholder); ?>"
        autocomplete="<?php echo esc_attr('password' === $type ? 'new-password' : 'off'); ?>"
    />
    <?php if ($description) : ?>
        <span class="description"><?php echo esc_html($description); ?></span>
    <?php endif; ?>
</p>
