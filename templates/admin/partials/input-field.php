<?php
/**
 * MDS3-compatible ImageGrid input field.
 *
 * @package MDS\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<p class="mds3-field">
    <label for="<?php echo esc_attr($name); ?>">
        <span class="mds3-field-label-text"><?php echo esc_html($label); ?></span>
    </label>
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
