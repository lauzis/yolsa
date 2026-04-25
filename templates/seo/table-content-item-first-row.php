<?php
if (empty($id)) {
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
$penalty = $report ? 0 : "not analysed yet";


if ($report && !$report['meta_description']) {
    $penalty++;
    $penalty++;
    $penalty++;
}
if ($report && $report['h1_count'] == 0) {
    $penalty++;
    $penalty++;
    $penalty++;
}
if ($report && $report['h1_found_keyword'] == 0) {
    $penalty++;
}
if ($report && !$report['meta_description_keyword_found']) {
    $penalty++;
}
if ($report && !$report['first_paragraph_found_keywords']) {
    $penalty++;
}

if ($report && !$report['meta_title_keyword_found']) {
    $penalty++;
}

if ($report && $report['meta_description_duplicate']) {
    $penalty++;
}

if ($report && $report['meta_title_duplicate']) {
    $penalty++;
}

$time = date("Y-m-d H:i:s");
if ($report) {
    $time = date("Y-m-d H:i:s", $report['timestamp']);
}

$local_keywords = false;
if ($report) {
    $local_keywords = $report['local_keywords'] ?? false;
}
?>

<td>

    <?php if (!$report): ?>
        <script>
          chatGptSoeIdsToAudit.push(<?= $id; ?>);
        </script>
    <?php else: ?>
        <script>
          chatGptSoeIdsChecked.push(<?= $id; ?>);
        </script>
    <?php endif; ?>
    <?= $id; ?>
    <?php include(YOLSA_PLUGIN_DIR . "/templates/seo/table-content-item-edit-form.php"); ?>
</td>
<td>
    <?= get_the_title($id); ?>
</td>
<td><span id="penalty-<?= $id ?>"><?= $penalty ?></span></td>
<td>
    <a onclick="expandReport(<?= $id; ?>)">More info</a>
</td>
<td>
    <a onclick="reAudit(<?= $id; ?>)">Re-audit</a>
</td>
<td>
    <a href="<?= get_the_permalink($id); ?>" target="_blank">View page</a>
</td>
<td>
    <?php edit_post_link(__('Edit page'), "", "", $id); ?>
</td>

<td>
    <?php \SeoAudit\Helpers::get_audit_status($local_keywords); ?>
</td>

<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['meta_title_keyword_found']); ?></td>
<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['meta_title_duplicate'], true); ?></td>
<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['meta_description']); ?></td>
<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['meta_description_keyword_found']); ?></td>
<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['meta_description_duplicate'], true); ?></td>
<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['h1_count'] > 0); ?></td>
<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['h1_found_keyword'] > 0); ?></td>
<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['first_paragraph_found_keywords']); ?></td>
<td><?php \SeoAudit\Helpers::get_audit_status($report && $report['content_has_keywords']); ?></td>

<td><?php \SeoAudit\Helpers::get_audit_status($report && isset($report['img_alt_missing']) ? floor(count($report['img_alt_missing']) / $report['img_count'] * 100) : false); ?></td>


<td><span><?= $time ?></span></td>





