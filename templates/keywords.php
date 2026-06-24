<h1>
    Keyword audit
</h1>
<?php
$files = scandir(CHAT_GPT_SEO_REPORT_DIR);

$summary_data = [];
$total_items = 0;
foreach ($files as $file) {
    $full_path = CHAT_GPT_SEO_REPORT_DIR . "/" . $file;
    $ext = \SeoAudit\Helpers::getFileExtension($full_path);
    if ($ext === "json" && substr_count(".prev.", $full_path) == 0) {
        $total_items ++;
        $json_data = json_decode(file_get_contents($full_path), TRUE);
        $keywords = $json_data['keywords'];
        $id = $json_data['id'];
        $url = $json_data['url'];
        foreach ($keywords as $k) {

            $keyword = $k['keyword'];
            $count = $k['count'];
            $exact = $k['count_exact'];
            $phrase = $k['count_phrase'];
            $place_where_found = $k['place_where_found'];

            if (!isset($summary_data[$keyword])) {
                $summary_data[$keyword] = $k;
                $summary_data[$keyword]['ids'] = [];
                $summary_data[$keyword]['urls'] = [];
                $summary_data[$keyword]['place_where_found'] = [];
                $summary_data[$keyword]['place_where_found'][0] = [];
                if (count($place_where_found)){
                    $summary_data[$keyword]['place_where_found'][0] = $place_where_found;
                    $summary_data[$keyword]['place_where_found'][$id] = $place_where_found;
                    $summary_data[$keyword]['urls'][] = $url;
                }
            } else {
                $summary_data[$keyword]['count'] += $count;
                $summary_data[$keyword]['count_exact'] += $exact;
                $summary_data[$keyword]['count_phrase'] += $phrase;
                if (count($place_where_found)){
                    $summary_data[$keyword]['ids'][] = $id;
                    $summary_data[$keyword]['urls'][] = $url;
                    $summary_data[$keyword]['ids'] = array_unique($summary_data[$keyword]['ids']);
                    $summary_data[$keyword]['urls'] = array_unique($summary_data[$keyword]['urls']);
                }

                $summary_data[$keyword]['place_where_found'][0] = array_unique(array_merge($summary_data[$keyword]['place_where_found'][0], $place_where_found));
                if (!isset($summary_data[$keyword]['place_where_found'][$id])) {
                    $summary_data[$keyword]['place_where_found'][$id] = $place_where_found;
                } else {
                    $summary_data[$keyword]['place_where_found'][$id] = array_unique(array_merge($summary_data[$keyword]['place_where_found'][$id], $place_where_found));
                }
            }
        }
    } ?>
<?php } ?>



<section class="chat-gpt-keywords-audit">

    <?php if(count($summary_data)===0): ?>
    <h2>Looks like there is no data. Please run Seo audit first!</h2>
    <?php else: ?>
    <table class="yolsa-keywords-table">
        <thead>
        <tr>
            <th>
                Keyword
            </th>
            <th>
                Exact match
            </th>
            <th>
                As a phrase
            </th>
            <th>
                In content items
            </th>
            <th>
                Content type
            </th>
        </tr>
        </thead>
        <tbody>


        <?php
        $listItemCount = 0;
        foreach ($summary_data as $keyword => $summary) {
            $listItemCount++;
            $exact = $summary['count_exact'];
            $phrase = $summary['count_phrase'];
            $urls = $summary['urls'];
            $ids = $summary['ids'];
            $place_where_found = $summary['place_where_found'][0];
            ?>
            <tr>
                <td>
                    <?= $keyword ?>
                </td>
                <td>
                    <?= $exact ?>
                </td>
                <td>
                    <?= $phrase ?>
                </td>
                <td>
                    <?php if (count($urls) > 0):?>
                        <?= count($urls) ?> / <?= $total_items ?> (<?= ceil(count($urls)/$total_items*100); ?>%)<br/>
                        <a id="cgs-keywords-links-show--<?= $listItemCount ?>" href="javascript:showKeywordPages(<?= $listItemCount ?>);">Show pages</a>
                        <a id="cgs-keywords-links-hide--<?= $listItemCount ?>"  class="cgs--hide" href="javascript:hideKeywordPages(<?= $listItemCount ?>);">Hide pages</a>
                        <br/>
                        <div id="cgs-keywords-links--<?= $listItemCount ?>" class="cgs-keywords-links cgs--hide">
                            <?php foreach ($ids as $id): ?>
                                <a target="_blank" href="<?= get_the_permalink($id) ?>"><?= get_the_title($id); ?></a><br/>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?= 0 ?>
                    <?php endif; ?>

                </td>
                <td>
                    <div>
                        <?php foreach ($place_where_found as $pwf): ?>
                            <?= ucfirst(str_replace("_"," ",$pwf)) ?><br/>
                        <?php endforeach; ?>
                    </div>

                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
