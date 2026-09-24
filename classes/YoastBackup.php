<?php

namespace SeoAudit;

/**
 * Copies Yoast's per-post SEO values into YoLSA's own keys.
 *
 * Additive only: a YoLSA value that is already set is never overwritten, and
 * nothing of Yoast's is touched. Running it twice is harmless — the second run
 * finds everything already filled and skips it.
 */
class YoastBackup
{
    const BATCH_SIZE = 100;

    /** Pseudo field name the keyword list is counted under in the summary. */
    const KEYWORDS = 'keyword_list';

    /**
     * Runs the backup over every post of every type the SEO box appears on.
     *
     * @return array<string, array{copied:int, skipped:int, empty:int}> Counts per YoLSA key.
     */
    public static function run(): array
    {
        $totals = [];

        foreach (SeoMeta::getYoastFieldMap() as $own) {
            $totals[$own] = ['copied' => 0, 'skipped' => 0, 'empty' => 0];
        }
        $totals[self::KEYWORDS] = ['copied' => 0, 'skipped' => 0, 'empty' => 0];

        $page = 1;

        do {
            // Attachments are left out, as they are from the SEO box: the local
            // keyword list is only declared where that box is.
            $ids = get_posts([
                'post_type'              => SeoMetaBox::postTypes(),
                'post_status'            => 'any',
                'fields'                 => 'ids',
                'posts_per_page'         => self::BATCH_SIZE,
                'paged'                  => $page,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'suppress_filters'       => true,
                'update_post_term_cache' => false,
            ]);

            foreach ($ids as $postId) {
                self::backupPost((int) $postId, $totals);
                self::backupKeywords((int) $postId, $totals);
            }

            $page++;
        } while (count($ids) === self::BATCH_SIZE);

        return $totals;
    }

    /**
     * Copies each mapped Yoast value whose YoLSA counterpart is still empty.
     *
     * @param int   $postId
     * @param array $totals
     */
    public static function backupPost(int $postId, array &$totals): void
    {
        foreach (SeoMeta::getYoastFieldMap() as $yoast => $own) {
            $value = trim((string) get_post_meta($postId, $yoast, true));

            if ('' === $value) {
                $totals[$own]['empty']++;
                continue;
            }

            if ('' !== trim((string) get_post_meta($postId, $own, true))) {
                $totals[$own]['skipped']++;
                continue;
            }

            update_post_meta($postId, $own, wp_slash($value));
            $totals[$own]['copied']++;
        }
    }

    /**
     * Turns Yoast's keyphrases into the post's local keyword list.
     *
     * A local list replaces the global one for that post in the keyword audit,
     * so it is only written where the post has none yet.
     *
     * @param int   $postId
     * @param array $totals
     */
    public static function backupKeywords(int $postId, array &$totals): void
    {
        $keywords = self::yoastKeywords($postId);

        if (!$keywords) {
            $totals[self::KEYWORDS]['empty']++;
            return;
        }

        if (!function_exists('carbon_get_post_meta') || !empty(carbon_get_post_meta($postId, 'keyword_list'))) {
            $totals[self::KEYWORDS]['skipped']++;
            return;
        }

        $list = [];

        foreach ($keywords as $keyword => $variations) {
            $list[] = [
                'keyword'            => $keyword,
                'keyword_variations' => array_map(
                    static fn(string $variation): array => ['keyword_variation' => $variation],
                    $variations
                ),
            ];
        }

        try {
            carbon_set_post_meta($postId, 'keyword_list', $list);
            $totals[self::KEYWORDS]['copied']++;
        } catch (\Throwable $e) {
            $totals[self::KEYWORDS]['skipped']++;
        }
    }

    /**
     * Yoast's keyphrases for a post, each with its synonyms.
     *
     * The focus keyphrase, then Premium's additional keyphrases. Synonyms are
     * stored by Premium as a JSON array lined up with those keyphrases (the
     * first entry belongs to the focus keyphrase); a plain string is treated as
     * the focus keyphrase's synonyms.
     *
     * @param int $postId
     * @return array<string, array<int, string>> Keyword => variations.
     */
    private static function yoastKeywords(int $postId): array
    {
        $phrases = [trim((string) get_post_meta($postId, '_yoast_wpseo_focuskw', true))];

        $extra = json_decode((string) get_post_meta($postId, '_yoast_wpseo_focuskeywords', true), true);

        if (is_array($extra)) {
            foreach ($extra as $entry) {
                $phrases[] = trim((string) (is_array($entry) ? ($entry['keyword'] ?? '') : $entry));
            }
        }

        $rawSynonyms = (string) get_post_meta($postId, '_yoast_wpseo_keywordsynonyms', true);
        $synonyms    = json_decode($rawSynonyms, true);

        if (!is_array($synonyms)) {
            $synonyms = [$rawSynonyms];
        }

        $keywords = [];

        foreach ($phrases as $index => $phrase) {
            if ('' === $phrase) {
                continue;
            }

            $variations = array_filter(array_map('trim', explode(',', (string) ($synonyms[$index] ?? ''))));
            $variations = array_diff($variations, [$phrase]);

            $keywords[$phrase] = array_values(array_unique(array_merge($keywords[$phrase] ?? [], $variations)));
        }

        return $keywords;
    }
}
