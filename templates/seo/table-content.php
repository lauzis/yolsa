<?php $missingMetaDescriptions = 0; ?>
<?php $missingMetaDescriptions_noKeywords = 0; ?>
<?php $missingMetaTitle_noKeywords = 0; ?>
<?php $totalCount = count($items); ?>

<?php foreach ($items as $item): ?>
    <?php
    $meta_description_keyword_found = false;
    $meta_title_keyword_found = false;
    ?>

    <?php $id = $item->ID; ?>


<?php include(YOLSA_PLUGIN_DIR.'/templates/seo/table-content-item.php'); ?>

<?php endforeach; ?>


