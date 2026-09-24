<?php
/**
 * Yoast backup page: explains what is copied and runs the copy.
 *
 * @var array<string, array{copied:int, skipped:int, empty:int}>|null $totals Set after a run.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<h1><?php esc_html_e('Yoast Backup', 'yolsa'); ?></h1>

<?php if (is_array($totals)) : ?>
    <div class="notice notice-success">
        <p>
            <?php
            printf(
                /* translators: 1: values copied, 2: values skipped, 3: values Yoast had nothing for */
                esc_html__('Backup finished: %1$d copied, %2$d skipped because YoLSA already had a value, %3$d empty in Yoast.', 'yolsa'),
                (int) array_sum(array_column($totals, 'copied')),
                (int) array_sum(array_column($totals, 'skipped')),
                (int) array_sum(array_column($totals, 'empty'))
            );
            ?>
        </p>
    </div>

    <table class="widefat striped" style="max-width:720px;margin-bottom:20px;">
        <thead>
            <tr>
                <th><?php esc_html_e('YoLSA field', 'yolsa'); ?></th>
                <th><?php esc_html_e('Copied', 'yolsa'); ?></th>
                <th><?php esc_html_e('Skipped', 'yolsa'); ?></th>
                <th><?php esc_html_e('Empty in Yoast', 'yolsa'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($totals as $field => $counts) : ?>
                <tr>
                    <td><code><?php echo esc_html($field); ?></code></td>
                    <td><?php echo (int) $counts['copied']; ?></td>
                    <td><?php echo (int) $counts['skipped']; ?></td>
                    <td><?php echo (int) $counts['empty']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><?php esc_html_e('Copies the SEO values Yoast stored on every post and page into YoLSA\'s own fields, so they are kept if Yoast is switched off or removed.', 'yolsa'); ?></p>
<ul style="list-style:disc;padding-left:20px;">
    <li><?php esc_html_e('Every Yoast field: titles, descriptions, social titles, descriptions and images, canonical URL, robots settings, breadcrumb title, cornerstone flag and schema types.', 'yolsa'); ?></li>
    <li><?php esc_html_e('The focus keyphrase (and Premium\'s additional keyphrases and synonyms) becomes the page\'s own keyword list, which replaces the site-wide list when that page is audited.', 'yolsa'); ?></li>
    <li><?php esc_html_e('A YoLSA field that already has a value is skipped, never overwritten — and so is a page that already has its own keyword list. Yoast\'s values are not changed.', 'yolsa'); ?></li>
</ul>

<form method="post">
    <?php wp_nonce_field('yolsa_yoast_backup', 'yolsa_yoast_backup_nonce'); ?>
    <input type="hidden" name="action" value="yoast_backup">
    <?php submit_button(__('Backup', 'yolsa'), 'primary', 'submit', false); ?>
</form>
