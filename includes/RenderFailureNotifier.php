<?php
/**
 * Persistent, privacy-safe ImageGrid render failure notifications.
 *
 * @package MillionDollarScript\Extensions\ImageGrid
 */

namespace MillionDollarScript\Extensions\ImageGrid;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Records actionable render failures without exposing service credentials.
 */
final class Render_Failure_Notifier {

    const OPTION_NAME = 'mds_imagegrid_render_failures';
    const EMAIL_OPTION = 'mds_imagegrid_failure_email_enabled';
    const MAX_RECORDS = 50;

    /**
     * Register notification hooks.
     *
     * @return void
     */
    public static function init() {
        add_action('admin_notices', [__CLASS__, 'render_admin_notices']);
        add_action('admin_post_mds_imagegrid_dismiss_render_failure', [__CLASS__, 'handle_dismiss']);
        add_action('million-dollar-script/extensions/imagegrid/render/failure', [__CLASS__, 'handle_reported_failure'], 10, 3);
        add_action('million-dollar-script/extensions/imagegrid/render/recovered', [__CLASS__, 'resolve_grid']);
        add_action('mds_imagegrid_render_failure', [__CLASS__, 'handle_reported_failure'], 10, 3);
        add_action('mds_imagegrid_render_recovered', [__CLASS__, 'resolve_grid']);
    }

    /**
     * Receive a controlled failure reported by core or another integration.
     *
     * @param int    $grid_id Grid ID.
     * @param string $source  Failure code or class.
     * @param string $message Safe human-readable message.
     * @return void
     */
    public static function handle_reported_failure($grid_id, $source = '', $message = '') {
        self::record($grid_id, $source, $message);
    }

    /**
     * Record or update one unresolved failure class for a grid.
     *
     * @param int    $grid_id Grid ID.
     * @param string $source  Failure code or class.
     * @param string $message Human-readable failure message.
     * @return void
     */
    public static function record($grid_id, $source, $message) {
        $grid_id = absint($grid_id);
        $message = self::safe_message($message);
        if (!$grid_id || '' === $message) {
            return;
        }

        $class = self::classify($source, $message);
        $id = substr(hash('sha256', $grid_id . '|' . $class), 0, 20);
        $records = self::records();
        $existing = isset($records[$id]) && is_array($records[$id]) ? $records[$id] : [];
        $is_recurrence = empty($existing['active']);
        $now = time();

        $record = array_merge($existing, [
            'id' => $id,
            'grid_id' => $grid_id,
            'class' => $class,
            'message' => $message,
            'active' => true,
            'dismissed' => $is_recurrence ? false : !empty($existing['dismissed']),
            'first_seen' => $is_recurrence ? $now : absint($existing['first_seen'] ?? $now),
            'last_seen' => $now,
            'count' => $is_recurrence ? 1 : max(1, absint($existing['count'] ?? 0) + 1),
            'resolved_at' => 0,
            'email_attempted_at' => $is_recurrence ? 0 : absint($existing['email_attempted_at'] ?? 0),
            'email_sent_at' => $is_recurrence ? 0 : absint($existing['email_sent_at'] ?? 0),
        ]);

        unset($records[$id]);
        $records = [$id => $record] + $records;
        $records = self::bounded_records($records);
        self::save_records($records);
        if (isset($records[$id])) {
            self::maybe_send_email($records[$id]);
        }
    }

    /**
     * Resolve all active failures for a grid after a successful submission or job.
     *
     * @param int $grid_id Grid ID.
     * @return void
     */
    public static function resolve_grid($grid_id) {
        $grid_id = absint($grid_id);
        if (!$grid_id) {
            return;
        }

        $records = self::records();
        $changed = false;
        foreach ($records as &$record) {
            if ($grid_id !== absint($record['grid_id'] ?? 0) || empty($record['active'])) {
                continue;
            }

            $record['active'] = false;
            $record['resolved_at'] = time();
            $changed = true;
        }
        unset($record);

        if ($changed) {
            self::save_records(self::bounded_records($records));
        }
    }

    /**
     * Display active failures to administrators.
     *
     * @return void
     */
    public static function render_admin_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active = array_filter(self::records(), static function($record) {
            return is_array($record) && !empty($record['active']) && empty($record['dismissed']);
        });
        uasort($active, static function($left, $right) {
            return absint($right['last_seen'] ?? 0) <=> absint($left['last_seen'] ?? 0);
        });

        foreach (array_slice($active, 0, 3, true) as $record) {
            self::render_notice($record);
        }
    }

    /**
     * Dismiss one persistent failure notice.
     *
     * @return void
     */
    public static function handle_dismiss() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to dismiss ImageGrid notices.', 'mds-imagegrid'));
        }

        check_admin_referer('mds_imagegrid_dismiss_render_failure');
        $id = sanitize_key((string) ($_POST['failure_id'] ?? ''));
        $records = self::records();
        if (isset($records[$id]) && is_array($records[$id])) {
            $records[$id]['dismissed'] = true;
            self::save_records($records);
        }

        $redirect = wp_get_referer();
        wp_safe_redirect($redirect ?: admin_url('admin.php?page=mds3-imagegrid&section=rendering'));
        exit;
    }

    /**
     * Return stored records for tests and diagnostics.
     *
     * @return array
     */
    public static function records() {
        $records = get_option(self::OPTION_NAME, []);

        return is_array($records) ? $records : [];
    }

    /**
     * Classify a controlled failure without persisting raw service payloads.
     *
     * @param string $source  Failure code or class.
     * @param string $message Safe message.
     * @return string
     */
    public static function classify($source, $message) {
        $haystack = strtolower(sanitize_key((string) $source) . ' ' . (string) $message);

        if (preg_match('/memory|execution time|resource|too large|local render|shared host/', $haystack)) {
            return 'local_resource';
        }
        if (preg_match('/missing.api.key|not configured|remote disabled|automatic rendering is off|extension unavailable/', $haystack)) {
            return 'remote_disabled';
        }
        if (preg_match('/past.due|billing|payment|plan required|subscription|suspended|paused/', $haystack)) {
            return 'account_billing';
        }
        if (preg_match('/quota|credit|rate.limit|too many requests|\b429\b|limit exceeded/', $haystack)) {
            return 'quota';
        }
        if (preg_match('/unauthor|forbidden|invalid.api.key|expired.key|\b401\b|\b403\b/', $haystack)) {
            return 'authorization';
        }
        if (preg_match('/timeout|timed out|unavailable|connection|could not resolve|\b5\d\d\b/', $haystack)) {
            return 'remote_service';
        }

        return 'terminal_job';
    }

    /**
     * Render one standard WordPress notice.
     *
     * @param array $record Failure record.
     * @return void
     */
    private static function render_notice(array $record) {
        $grid_id = absint($record['grid_id'] ?? 0);
        $label = self::class_label((string) ($record['class'] ?? 'terminal_job'));
        $grid_url = class_exists('\MillionDollarScript\Core\Runtime')
            ? add_query_arg(['page' => 'mds3-grids', 'grid_id' => $grid_id, 'tab' => 'grid-imagegrid'], admin_url('admin.php'))
            : '';
        $settings_url = admin_url('admin.php?page=' . (class_exists('\MillionDollarScript\Core\Runtime') ? 'mds3-imagegrid' : 'mds-imagegrid') . '&section=rendering&grid_id=' . $grid_id);
        ?>
        <div class="notice notice-warning mds-imagegrid-render-failure-notice">
            <p><strong><?php esc_html_e('ImageGrid rendering needs attention.', 'mds-imagegrid'); ?></strong></p>
            <p>
                <?php echo esc_html(sprintf(__('Grid #%1$d: %2$s', 'mds-imagegrid'), $grid_id, $label)); ?>
                <?php echo esc_html((string) ($record['message'] ?? '')); ?>
            </p>
            <p>
                <?php if ($grid_url) : ?><a class="button button-primary" href="<?php echo esc_url($grid_url); ?>"><?php esc_html_e('Review grid', 'mds-imagegrid'); ?></a><?php endif; ?>
                <a class="button" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Review ImageGrid', 'mds-imagegrid'); ?></a>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mds_imagegrid_dismiss_render_failure'); ?>
                <input type="hidden" name="action" value="mds_imagegrid_dismiss_render_failure">
                <input type="hidden" name="failure_id" value="<?php echo esc_attr((string) ($record['id'] ?? '')); ?>">
                <button type="submit" class="button-link"><?php esc_html_e('Dismiss this notice', 'mds-imagegrid'); ?></button>
            </form>
        </div>
        <?php
    }

    /**
     * Send one opt-in email per unresolved failure occurrence.
     *
     * @param array $record Failure record.
     * @return void
     */
    private static function maybe_send_email(array $record) {
        if (!get_option(self::EMAIL_OPTION, false) || !empty($record['email_attempted_at'])) {
            return;
        }

        $email = sanitize_email((string) get_option('admin_email', ''));
        if (!$email) {
            return;
        }

        $grid_id = absint($record['grid_id'] ?? 0);
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] ImageGrid rendering needs attention', 'mds-imagegrid'),
            wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)
        );
        /* translators: 1: grid ID, 2: failure category, 3: failure message, 4: settings URL */
        $body_format = __('ImageGrid could not complete rendering for grid #%1$d.

Category: %2$s
Reason: %3$s

Review ImageGrid: %4$s', 'mds-imagegrid');
        $body = sprintf(
            $body_format,
            $grid_id,
            self::class_label((string) ($record['class'] ?? 'terminal_job')),
            (string) ($record['message'] ?? ''),
            admin_url('admin.php?page=' . (class_exists('\MillionDollarScript\Core\Runtime') ? 'mds3-imagegrid' : 'mds-imagegrid') . '&section=rendering&grid_id=' . $grid_id)
        );

        $sent = wp_mail($email, $subject, $body);
        $records = self::records();
        $id = (string) ($record['id'] ?? '');
        if (isset($records[$id])) {
            $records[$id]['email_attempted_at'] = time();
            $records[$id]['email_sent_at'] = $sent ? time() : 0;
            self::save_records($records);
        }
    }

    /**
     * Convert a failure class into administrator-facing text.
     *
     * @param string $class Failure class.
     * @return string
     */
    private static function class_label($class) {
        $labels = [
            'local_resource' => __('Local hosting limit.', 'mds-imagegrid'),
            'remote_disabled' => __('Remote rendering is unavailable.', 'mds-imagegrid'),
            'authorization' => __('ImageGrid authorization is required.', 'mds-imagegrid'),
            'account_billing' => __('ImageGrid account action is required.', 'mds-imagegrid'),
            'quota' => __('ImageGrid usage limit reached.', 'mds-imagegrid'),
            'remote_service' => __('ImageGrid service request failed.', 'mds-imagegrid'),
            'terminal_job' => __('ImageGrid job failed.', 'mds-imagegrid'),
        ];

        return $labels[$class] ?? $labels['terminal_job'];
    }

    /**
     * Remove credentials, URLs, paths, markup, and excessive detail.
     *
     * @param string $message Raw message.
     * @return string
     */
    private static function safe_message($message) {
        $message = wp_strip_all_tags((string) $message);
        $message = preg_replace('/\bauthorization\s*:\s*bearer\s+[^\s,;]+/i', 'Authorization: Bearer [redacted]', $message);
        $message = preg_replace('/\bbearer\s+[^\s,;]+/i', 'Bearer [redacted]', (string) $message);
        $message = preg_replace('/\b(api[_ -]?key|token)\s*[:=]\s*[^\s,;]+/i', '$1: [redacted]', (string) $message);
        $message = preg_replace('#https?://\S+#i', '[redacted URL]', (string) $message);
        $message = preg_replace('#(?:/[A-Za-z0-9._-]+){3,}#', '[redacted path]', (string) $message);
        $message = sanitize_text_field((string) $message);

        return strlen($message) > 280 ? substr($message, 0, 277) . '...' : $message;
    }

    /**
     * Retain a bounded recent history and discard old resolved records.
     *
     * @param array $records Records keyed by notification ID.
     * @return array
     */
    private static function bounded_records(array $records) {
        $resolved_cutoff = time() - (7 * DAY_IN_SECONDS);
        $records = array_filter($records, static function($record) use ($resolved_cutoff) {
            return !is_array($record)
                || !empty($record['active'])
                || absint($record['resolved_at'] ?? 0) >= $resolved_cutoff;
        });
        uasort($records, static function($left, $right) {
            return absint($right['last_seen'] ?? 0) <=> absint($left['last_seen'] ?? 0);
        });

        return array_slice($records, 0, self::MAX_RECORDS, true);
    }

    /**
     * Persist non-autoloaded notification state.
     *
     * @param array $records Notification records.
     * @return void
     */
    private static function save_records(array $records) {
        update_option(self::OPTION_NAME, $records, false);
    }
}
