<h1>
    Seo audit
</h1>

<?php include(YOLSA_PLUGIN_DIR . '/templates/sections/keywords.php'); ?>


<?php
$postSettings = [
    "Pages" => [
        'post_type' => 'page',
        'post_status' => ['publish', 'draft'],
        'numberposts' => -1,
        'suppress_filters' => false
    ],
    "Posts" => [
        'post_type' => 'post',
        'post_status' => ['publish', 'draft'],
        'numberposts' => -1,
        'suppress_filters' => false
    ]
];
?>

<script>
  var chatGptSoeIdsToAudit = [];
  var chatGptSoeIdsChecked = [];
</script>

<?php include(YOLSA_PLUGIN_DIR . '/templates/sections/status.php'); ?>

<?php foreach ($postSettings as $type => $settings): ?>
    <section class="yolsa-audit">
        <h2><?= $type ?></h2>
        <?php $items = get_posts($settings); ?>


        <?php if ($items): ?>
            <?php include(YOLSA_PLUGIN_DIR . "/templates/seo/table-header.php"); ?>
            <?php include(YOLSA_PLUGIN_DIR . "/templates/seo/table-content.php"); ?>
            <?php include(YOLSA_PLUGIN_DIR . "/templates/seo/table-footer.php"); ?>
        <?php else : ?>
            <pre>
            <?php print_r($items); ?>
        </pre>
        <?php endif; ?>
    </section>
<?php endforeach; ?>




<?php include(YOLSA_PLUGIN_DIR . '/templates/sections/modal.php'); ?>
