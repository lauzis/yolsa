<section>
<h2>Default keywords</h2>

<?php $list_of_keywords = [] ?>

<?php 
$keyword_list = carbon_get_theme_option('keyword_list');
if (!empty($keyword_list)): ?>
    <p>
    <ul class="soe-chat-gpt-keyword-list">
        <?php foreach ($keyword_list as $item): ?>
            <?php $keyword = $item['keyword'] ?? ''; ?>
            <?php $keyword_variations_acf = $item['kyeword_variations'] ?? []; ?>
            <?php $keyword_variations = [] ?>
            <?php $keyword_variations_str = [] ?>
            <?php if ($keyword_variations_acf): ?>
                <?php foreach ($keyword_variations_acf as $keyword_variation): ?>
                    <?php $keyword_variations_str[] = $keyword_variation['keyword_variation'] ?? ''; ?>
                    <?php $keyword_variations[] = ['keyword' => $keyword_variation['keyword_variation'] ?? '', 'count' => 0]; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php $full_list_of_keywords[] = ['keyword' => $keyword, 'variations' => $keyword_variations, 'count' => 0]; ?>
            <li><?= $keyword; ?>
                <?php if (is_array($keyword_variations) && count($keyword_variations) > 0): ?>
                    <ul>
                        <li><?= implode(",", $keyword_variations_str); ?></li>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <!--<pre>-->
    <!--    --><?php //print_r($full_list_of_keywords); ?>
    <!--</pre>-->
    </p>
<?php else: ?>
    <p>
        Please set the keywords
    </p>
<?php endif; ?>
</section>
