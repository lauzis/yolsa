<?php
/**
 * Log viewer. Shows one day's entries at a time, newest first.
 *
 * @var string $selected
 * @var array  $files
 * @var array  $lines
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<h1><?php esc_html_e('Logs', 'yolsa'); ?></h1>

<?php if (!\SeoAudit\Logs::enabled()) : ?>
    <div class="notice notice-warning">
        <p>
            <?php
            printf(
                /* translators: %s: link to the settings page */
                esc_html__('Logging is switched off, so nothing new is being recorded. Turn it on under %s.', 'yolsa'),
                '<a href="' . esc_url(admin_url('admin.php?page=yolsa-settings')) . '">' . esc_html__('Settings', 'yolsa') . '</a>'
            );
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (empty($files)) : ?>
    <p><?php esc_html_e('No log files yet.', 'yolsa'); ?></p>
<?php else : ?>
    <form method="get" style="display:inline-block;margin:12px 0;">
        <input type="hidden" name="page" value="yolsa-logs">
        <label for="yolsa-log-date" class="screen-reader-text"><?php esc_html_e('Log date', 'yolsa'); ?></label>
        <select name="log_date" id="yolsa-log-date" onchange="this.form.submit()">
            <?php foreach ($files as $file) : ?>
                <option value="<?php echo esc_attr($file['date']); ?>" <?php selected($selected, $file['date']); ?>>
                    <?php
                    printf(
                        /* translators: 1: date, 2: number of entries */
                        esc_html__('%1$s (%2$d entries)', 'yolsa'),
                        esc_html($file['date']),
                        (int) $file['count']
                    );
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <form method="post" style="display:inline-block;margin-left:8px;">
        <?php wp_nonce_field('yolsa_clear_logs', 'yolsa_clear_logs_nonce'); ?>
        <input type="hidden" name="action" value="clear_logs">
        <button type="submit" class="button button-secondary"
                onclick="return confirm('<?php echo esc_js(__('Delete every log file? This cannot be undone.', 'yolsa')); ?>');">
            <?php esc_html_e('Clear All', 'yolsa'); ?>
        </button>
    </form>

    <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
        <thead>
            <tr>
                <th style="width:160px;"><?php esc_html_e('Time (UTC)', 'yolsa'); ?></th>
                <th style="width:160px;"><?php esc_html_e('Action', 'yolsa'); ?></th>
                <th><?php esc_html_e('Message', 'yolsa'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lines)) : ?>
                <tr><td colspan="3"><?php esc_html_e('This log file is empty.', 'yolsa'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($lines as $line) : ?>
                <?php
                // [2026-08-02 09:14:02] [action] message | {"json":"context"}
                preg_match('/^\[([^\]]*)\]\s*(?:\[([^\]]*)\])?\s*(.*)$/', $line, $m);
                $time    = $m[1] ?? '';
                $action  = $m[2] ?? '';
                $message = $m[3] ?? $line;
                ?>
                <tr>
                    <td><code><?php echo esc_html($time); ?></code></td>
                    <td><?php echo '' === $action ? '<em style="color:#999;">&mdash;</em>' : '<code>' . esc_html($action) . '</code>'; ?></td>
                    <td style="word-break:break-word;"><?php echo esc_html($message); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
