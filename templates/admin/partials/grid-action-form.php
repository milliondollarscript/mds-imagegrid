<?php
/**
 * ImageGrid per-grid action form.
 *
 * @package MDS\Extensions\ImageGrid
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field($nonce); ?>
    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
    <input type="hidden" name="grid_id" value="<?php echo esc_attr(absint($grid_id)); ?>">
    <input type="hidden" name="redirect_to" value="grid">
    <button type="submit" class="<?php echo esc_attr($class); ?>" <?php disabled($disabled, true); ?>>
        <?php echo esc_html($label); ?>
    </button>
</form>
