<?php
/**
 * ImageGrid per-grid action form.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field($nonce); ?>
    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
    <input type="hidden" name="grid_id" value="<?php echo esc_attr(absint($grid_id)); ?>">
    <?php if (!empty($redirect_to)) : ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr(sanitize_key($redirect_to)); ?>">
    <?php else : ?>
        <input type="hidden" name="section" value="rendering">
    <?php endif; ?>
    <button type="submit" class="<?php echo esc_attr($class); ?>" <?php disabled($disabled, true); ?><?php echo $disabled && !empty($disabled_reason) ? ' title="' . esc_attr($disabled_reason) . '" aria-label="' . esc_attr($label . ': ' . $disabled_reason) . '"' : ''; ?>>
        <?php echo esc_html($label); ?>
    </button>
</form>
