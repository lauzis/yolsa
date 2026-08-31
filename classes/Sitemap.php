<?php

namespace SeoAudit;

/**
 * The XML sitemap.
 *
 * Built on WordPress' own sitemap provider rather than a second implementation
 * of one. Core has paginated, cached-by-the-browser sitemaps since 5.5; what it
 * does not have is any idea which pages this site wants hidden. That is the
 * part worth writing, and it is three filters.
 *
 * Every SEO plugin switches core's sitemaps off so its own can serve
 * `/sitemap_index.xml`. YoLSA does the opposite: while another plugin owns SEO
 * it keeps out of the way entirely, and when it owns SEO it puts core's back.
 */
class Sitemap
{
    /** How long the list of hidden posts is trusted for. */
    const CACHE_TTL = DAY_IN_SECONDS;

    /** Transient holding the hidden ids for one post type. */
    const CACHE_PREFIX = 'yolsa_sitemap_hidden_';

    /** Transient holding one assembled, all-languages url list. */
    const URLS_PREFIX = 'yolsa_sitemap_urls_';

    /** The daily rebuild. */
    const CRON_HOOK = 'yolsa_sitemap_rebuild';

    /** Hooks the sitemap. */
    public static function init(): void
    {
        add_filter('wp_sitemaps_enabled', [self::class, 'enabled'], 20);
        add_filter('wp_sitemaps_post_types', [self::class, 'filterPostTypes'], 20);
        add_filter('wp_sitemaps_taxonomies', [self::class, 'filterTaxonomies'], 20);
        add_filter('wp_sitemaps_posts_query_args', [self::class, 'excludeHiddenPosts'], 20, 2);

        // Multilingual sites: core builds one sitemap in one language, and
        // WordPress has no language-prefixed sitemap route — /en/wp-sitemap.xml
        // is a 404. Left alone, three languages out of four would have no
        // sitemap at all, so the lists are assembled across every language and
        // paginated here instead.
        add_filter('wp_sitemaps_posts_url_list', [self::class, 'postUrlList'], 20, 3);
        add_filter('wp_sitemaps_posts_pre_max_num_pages', [self::class, 'postMaxPages'], 20, 2);
        add_filter('wp_sitemaps_taxonomies_url_list', [self::class, 'taxonomyUrlList'], 20, 3);
        add_filter('wp_sitemaps_taxonomies_pre_max_num_pages', [self::class, 'taxonomyMaxPages'], 20, 2);

        // A post that has just been hidden should leave the sitemap now, not
        // tomorrow — and one just published should appear.
        add_action('save_post', [self::class, 'flush']);
        add_action('deleted_post', [self::class, 'flush']);
        add_action('edited_term', [self::class, 'flush']);
        add_action('delete_term', [self::class, 'flush']);
        add_action('carbon_fields_theme_options_container_saved', [self::class, 'flushAfterSettingsSaved']);

        // The daily pass is the backstop rather than the mechanism: it catches
        // what changes a sitemap without anybody saving a post — a scheduled
        // publication going live, a bulk delete, an import.
        add_action(self::CRON_HOOK, [self::class, 'rebuild']);
        add_action('init', [self::class, 'scheduleRebuild']);
    }

    /** Books the daily rebuild if it is not booked already. */
    public static function scheduleRebuild(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /** Called on deactivation, so nothing is left booked. */
    public static function unscheduleRebuild(): void
    {
        $next = wp_next_scheduled(self::CRON_HOOK);

        if ($next) {
            wp_unschedule_event($next, self::CRON_HOOK);
        }
    }

    /** Throws the cached lists away. */
    public static function flush(): void
    {
        foreach (array_keys(get_post_types(['public' => true])) as $postType) {
            delete_transient(self::CACHE_PREFIX . $postType);
            delete_transient(self::URLS_PREFIX . 'post_' . $postType);
        }

        foreach (array_keys(get_taxonomies(['public' => true])) as $taxonomy) {
            delete_transient(self::URLS_PREFIX . 'tax_' . $taxonomy);
        }
    }

    /**
     * The same, but said out loud.
     *
     * Hooked to the settings save rather than `flush()` itself, because
     * `save_post` fires on every keystroke of an autosave and a log line per
     * keystroke is a log nobody reads. Changing what the whole site hides is
     * worth a line; editing one post is not.
     */
    public static function flushAfterSettingsSaved(): void
    {
        self::flush();

        Logs::add('sitemap', 'Cleared the hidden-post cache after a settings save.', [
            'post_types' => implode(', ', (array) Indexing::get('noindex_post_types', [])) ?: 'none hidden',
            'taxonomies' => implode(', ', (array) Indexing::get('noindex_taxonomies', [])) ?: 'none hidden',
        ]);
    }

    /** Throws the lists away and builds them again, so no visitor waits for it. */
    public static function rebuild(): void
    {
        $started = microtime(true);

        self::flush();

        $counts = [];

        foreach (array_keys(get_post_types(['public' => true])) as $postType) {
            $counts[$postType] = count(self::hiddenPostIds($postType));
            self::postUrls($postType);
        }

        foreach (array_keys(get_taxonomies(['public' => true])) as $taxonomy) {
            self::taxonomyUrls($taxonomy);
        }

        $parts = [];

        foreach ($counts as $postType => $count) {
            $parts[] = $postType . ': ' . $count;
        }

        Logs::add('sitemap', 'Rebuilt the hidden-post cache.', [
            'hidden'   => implode(', ', $parts),
            'total'    => array_sum($counts),
            'duration' => round((microtime(true) - $started) * 1000) . 'ms',
            'trigger'  => wp_doing_cron() ? 'daily schedule' : 'called directly',
        ]);
    }

    /**
     * The languages to walk, or a single unnamed one on a monolingual site.
     *
     * @return array<int, string|null>
     */
    public static function languages(): array
    {
        $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);

        if (!is_array($languages) || !$languages) {
            return [null];
        }

        return array_keys($languages);
    }

    /** Runs a callback once per language, with that language active. */
    private static function eachLanguage(callable $callback): array
    {
        $collected = [];
        $languages = self::languages();

        foreach ($languages as $language) {
            if (null !== $language) {
                do_action('wpml_switch_language', $language);
            }

            $collected[] = $callback($language);
        }

        if (count($languages) > 1) {
            do_action('wpml_switch_language', null);
        }

        return array_merge(...$collected);
    }

    /**
     * Every URL for one post type, in every language.
     *
     * The permalink has to be taken while its own language is active: asked in
     * the wrong one, WordPress returns the address without the language prefix,
     * which is a URL that belongs to a different post.
     *
     * Deduplicated on the way out. An untranslated post answers with the
     * default language's URL in every language, which is how Yoast's sitemap on
     * this site ends up listing 420 entries for 161 distinct addresses.
     *
     * @param string $postType
     * @return array<int, array{loc:string,lastmod:string}>
     */
    public static function postUrls(string $postType): array
    {
        $key = self::URLS_PREFIX . 'post_' . $postType;
        $cached = get_transient($key);

        if (is_array($cached)) {
            return $cached;
        }

        $started = microtime(true);
        $hidden = self::hiddenPostIds($postType);
        $typeHidden = SeoMeta::isNoIndexPostType($postType);

        $entries = self::eachLanguage(function () use ($postType, $hidden, $typeHidden) {
            // Core's own arguments, filter included, so anything else hooking
            // wp_sitemaps_posts_query_args still applies — this query stands in
            // for core's, it does not get to ignore what core promised.
            $args = apply_filters(
                'wp_sitemaps_posts_query_args',
                [
                    'orderby'                => 'ID',
                    'order'                  => 'ASC',
                    'post_type'              => $postType,
                    'post_status'            => ['publish'],
                    'no_found_rows'          => true,
                    'update_post_term_cache' => false,
                    'update_post_meta_cache' => false,
                    'ignore_sticky_posts'    => true,
                    // Neither core nor this plugin should advertise a page that
                    // answers with a password form.
                    'has_password'           => false,
                    'posts_per_page'         => 5000,
                    'fields'                 => 'ids',
                ],
                $postType
            );

            if ($typeHidden) {
                // The whole type is hidden, so the sitemap carries only what
                // was put back by hand — the reverse of the usual exclusion.
                $args['meta_query'] = [
                    [
                        'key'   => SeoMeta::ROBOTS_INDEX,
                        'value' => 'index',
                    ],
                ];
            } else {
                $args['post__not_in'] = array_values(array_unique(array_merge(
                    isset($args['post__not_in']) ? (array) $args['post__not_in'] : [],
                    $hidden
                )));
            }

            $query = new \WP_Query($args);

            $found = [];

            foreach ($query->posts as $id) {
                $found[] = [
                    'loc'     => (string) get_permalink($id),
                    'lastmod' => (string) get_post_modified_time('c', true, $id),
                ];
            }

            return $found;
        });

        $unique = [];

        foreach ($entries as $entry) {
            if ('' !== $entry['loc']) {
                $unique[$entry['loc']] = $entry;
            }
        }

        $list = array_values($unique);

        set_transient($key, $list, self::CACHE_TTL);

        Logs::add('sitemap', 'Assembled the url list.', [
            'post_type' => $postType,
            'urls'      => count($list),
            'languages' => count(self::languages()),
            'duplicates_dropped' => count($entries) - count($list),
            'duration'  => round((microtime(true) - $started) * 1000) . 'ms',
        ]);

        return $list;
    }

    /**
     * Every term archive for one taxonomy, in every language.
     *
     * @param string $taxonomy
     * @return array<int, array{loc:string}>
     */
    public static function taxonomyUrls(string $taxonomy): array
    {
        $key = self::URLS_PREFIX . 'tax_' . $taxonomy;
        $cached = get_transient($key);

        if (is_array($cached)) {
            return $cached;
        }

        $entries = self::eachLanguage(function () use ($taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
            ]);

            $found = [];

            if (is_array($terms)) {
                foreach ($terms as $term) {
                    // One rule for both directions: a hidden taxonomy lists the
                    // terms put back by hand, a visible one lists all but the
                    // terms taken out.
                    if (SeoMeta::isNoIndexTerm((int) $term->term_id, $taxonomy)) {
                        continue;
                    }

                    $link = get_term_link($term);

                    if (!is_wp_error($link)) {
                        $found[] = ['loc' => (string) $link];
                    }
                }
            }

            return $found;
        });

        $unique = [];

        foreach ($entries as $entry) {
            if ('' !== $entry['loc']) {
                $unique[$entry['loc']] = $entry;
            }
        }

        $list = array_values($unique);

        set_transient($key, $list, self::CACHE_TTL);

        return $list;
    }

    /**
     * Hands core the page it asked for, out of the assembled list.
     *
     * @param array  $urlList
     * @param string $postType
     * @param int    $pageNum
     * @return array
     */
    public static function postUrlList($urlList, $postType, $pageNum)
    {
        $all = self::postUrls((string) $postType);
        $perPage = wp_sitemaps_get_max_urls('post');

        return array_slice($all, ((int) $pageNum - 1) * $perPage, $perPage);
    }

    /**
     * @param int|null $pre
     * @param string   $postType
     * @return int
     */
    public static function postMaxPages($pre, $postType)
    {
        $perPage = wp_sitemaps_get_max_urls('post');

        return max(1, (int) ceil(count(self::postUrls((string) $postType)) / $perPage));
    }

    /**
     * @param array  $urlList
     * @param string $taxonomy
     * @param int    $pageNum
     * @return array
     */
    public static function taxonomyUrlList($urlList, $taxonomy, $pageNum)
    {
        $all = self::taxonomyUrls((string) $taxonomy);
        $perPage = wp_sitemaps_get_max_urls('term');

        return array_slice($all, ((int) $pageNum - 1) * $perPage, $perPage);
    }

    /**
     * @param int|null $pre
     * @param string   $taxonomy
     * @return int
     */
    public static function taxonomyMaxPages($pre, $taxonomy)
    {
        $perPage = wp_sitemaps_get_max_urls('term');

        return max(1, (int) ceil(count(self::taxonomyUrls((string) $taxonomy)) / $perPage));
    }

    /**
     * The posts of one type that asked to stay out of search results.
     *
     * One query, cached for a day, rather than a meta_query on the sitemap's
     * own query. Expressing "this meta is absent, or is anything but noindex,
     * unless that other meta says otherwise" in WP_Query means several LEFT
     * JOINs against a postmeta table with hundreds of thousands of rows — the
     * first attempt at this did not finish inside two minutes.
     *
     * @param string $postType
     * @return array<int, int>
     */
    public static function hiddenPostIds(string $postType): array
    {
        $started = microtime(true);
        $key = self::CACHE_PREFIX . $postType;
        $cached = get_transient($key);

        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID
               FROM {$wpdb->posts} p
               LEFT JOIN {$wpdb->postmeta} own
                 ON own.post_id = p.ID AND own.meta_key = %s
               LEFT JOIN {$wpdb->postmeta} yoast
                 ON yoast.post_id = p.ID AND yoast.meta_key = %s
              WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND (
                      own.meta_value = 'noindex'
                      OR (yoast.meta_value = '1' AND (own.meta_value IS NULL OR own.meta_value = ''))
                    )",
            SeoMeta::ROBOTS_INDEX,
            SeoMeta::YOAST_NOINDEX,
            $postType
        ));

        if ('' !== (string) $wpdb->last_error) {
            // An empty list would quietly put every hidden page back into the
            // sitemap, which is the one outcome worth shouting about.
            Logs::error('sitemap', 'Could not list the hidden posts; nothing will be excluded.', [
                'post_type' => $postType,
                'error'     => $wpdb->last_error,
            ]);

            return [];
        }

        $ids = array_map('intval', (array) $ids);

        set_transient($key, $ids, self::CACHE_TTL);

        Logs::add('sitemap', 'Listed the posts hidden from search.', [
            'post_type' => $postType,
            'hidden'    => count($ids),
            'duration'  => round((microtime(true) - $started) * 1000) . 'ms',
        ]);

        return $ids;
    }

    /**
     * Whether there should be a sitemap at all.
     *
     * Left exactly as it is while another SEO plugin is active — it will have
     * switched core's off to serve its own, and two sitemaps disagreeing about
     * what is on the site is worse than either.
     *
     * @param bool $enabled
     * @return bool
     */
    public static function enabled($enabled)
    {
        if (SeoMeta::isHandledElsewhere()) {
            return $enabled;
        }

        return true;
    }

    /**
     * Drops the post types hidden on the Indexing page.
     *
     * @param array $postTypes Name => object.
     * @return array
     */
    public static function filterPostTypes($postTypes)
    {
        // Media pages carry no content of their own and are the classic way
        // to fill a sitemap with nothing.
        unset($postTypes['attachment']);

        foreach (array_keys((array) $postTypes) as $name) {
            // A hidden type is not dropped outright: a post inside it may have
            // been put back by hand, and an override that does not reach the
            // sitemap is only half an override. The type goes only when it
            // would contribute nothing.
            if (SeoMeta::isNoIndexPostType((string) $name) && !self::postUrls((string) $name)) {
                unset($postTypes[$name]);
            }
        }

        return $postTypes;
    }

    /**
     * Drops the taxonomies hidden on the Indexing page.
     *
     * @param array $taxonomies Name => object.
     * @return array
     */
    public static function filterTaxonomies($taxonomies)
    {
        foreach (array_keys((array) $taxonomies) as $name) {
            // Same as post types: a hidden taxonomy stays if some term of it
            // was deliberately put back.
            if (SeoMeta::isNoIndexTaxonomy((string) $name) && !self::taxonomyUrls((string) $name)) {
                unset($taxonomies[$name]);
            }
        }

        return $taxonomies;
    }

    /**
     * Leaves out the individual posts that asked to be hidden.
     *
     * A page telling search engines not to index it, while the sitemap invites
     * them to come and look, is the site contradicting itself. Both the YoLSA
     * setting and the Yoast value it falls back to are honoured, so nothing has
     * to be re-entered before this is correct.
     *
     * @param array  $args     WP_Query arguments.
     * @param string $postType
     * @return array
     */
    public static function excludeHiddenPosts($args, $postType)
    {
        $hidden = self::hiddenPostIds((string) $postType);

        if (!$hidden) {
            return $args;
        }

        $existing = isset($args['post__not_in']) ? (array) $args['post__not_in'] : [];
        $args['post__not_in'] = array_values(array_unique(array_merge($existing, $hidden)));

        return $args;
    }
}
