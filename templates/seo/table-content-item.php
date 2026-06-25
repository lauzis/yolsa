<?php
if (empty($id)){
    ?>
    <tr>
        <td colspan="18">
            Missing ID
        </td>
    </tr>
    <?php
}
$url = get_the_permalink($id);
$report = \SeoAudit\Helpers::get_report($url);
$report_url = \SeoAudit\Helpers::get_report_url($url);
$report_html_url = \SeoAudit\Helpers::get_report_html_url($url);
?>

<tr data-id="<?= $id ?>" id="seo-summary-<?= $id ?>" class="<?= $report ? 'yolsa-report-done' : 'yolsa-check-post' ?>">
    <?php include(YOLSA_PLUGIN_DIR."/templates/seo/table-content-item-first-row.php"); ?>
</tr>

