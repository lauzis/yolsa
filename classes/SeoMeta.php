<?php

namespace SeoAudit;

class SeoMeta
{
    const META_DESCRIPTION = '_yolsa_meta_description';
    const META_TITLE       = '_yolsa_meta_title';
    const OG_TITLE         = '_yolsa_og_title';
    const OG_DESCRIPTION   = '_yolsa_og_description';

    /** Yoast's equivalents, read as a fallback so older values keep working. */
    const ROBOTS_INDEX = '_yolsa_robots_index';
    const OG_TYPE      = '_yolsa_og_type';

    const YOAST_META_DESCRIPTION = '_yoast_wpseo_metadesc';
    const YOAST_META_TITLE       = '_yoast_wpseo_title';
    const YOAST_OG_TITLE         = '_yoast_wpseo_opengraph-title';
    const YOAST_OG_DESCRIPTION   = '_yoast_wpseo_opengraph-description';

    // Yoast's own three states, stored as strings: 1 hides the post, 2 shows it
    // explicitly, absent or 0 means "whatever the site says".
    const YOAST_NOINDEX = '_yoast_wpseo_meta-robots-noindex';

    /**
     * Plugins that emit their own title and description tags.
     *
     * YoLSA stays out of the head whenever one of these is active, because two
     * plugins each writing a meta description is worse than neither doing it.
     */
    const SEO_PLUGINS = [
        'wordpress-seo/wp-seo.php',                 // Yoast
        'wordpress-seo-premium/wp-seo-premium.php',
        'seo-by-rank-math/rank-math.php',
        'wp-seopress/seopress.php',
        'all-in-one-seo-pack/all_in_one_seo_pack.php',
        'autodescription/autodescription.php',      // The SEO Framework
        'slim-seo/slim-seo.php',
    ];

    private static function isYoastActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('wordpress-seo/wp-seo.php');
    }

    public static function getMetaDescriptionKey(): string
    {
        return self::isYoastActive() ? '_yoast_wpseo_metadesc' : self::META_DESCRIPTION;
    }

    public static function getMetaTitleKey(): string
    {
        return self::isYoastActive() ? '_yoast_wpseo_title' : self::META_TITLE;
    }

    public static function getOgTitleKey(): string
    {
        return self::isYoastActive() ? '_yoast_wpseo_opengraph-title' : self::OG_TITLE;
    }

    public static function getOgDescriptionKey(): string
    {
        return self::isYoastActive() ? '_yoast_wpseo_opengraph-description' : self::OG_DESCRIPTION;
    }

    /**
     * Reads a value, preferring YoLSA's own key and falling back to Yoast's.
     *
     * The two keys are both in play because which one gets written depends on
     * whether Yoast was active at the time. Anything generated while Yoast was
     * installed sits under its key, and would otherwise vanish the moment Yoast
     * was switched off — so the older value is used when ours is empty.
     */
    private static function read(int $postId, string $own, string $yoast): string
    {
        $value = trim((string) get_post_meta($postId, $own, true));

        if ('' !== $value) {
            return $value;
        }

        return trim((string) get_post_meta($postId, $yoast, true));
    }

    public static function getMetaDescription(int $postId): string
    {
        return self::read($postId, self::META_DESCRIPTION, self::YOAST_META_DESCRIPTION);
    }

    public static function getMetaTitle(int $postId): string
    {
        return self::read($postId, self::META_TITLE, self::YOAST_META_TITLE);
    }

    public static function getOgTitle(int $postId): string
    {
        return self::read($postId, self::OG_TITLE, self::YOAST_OG_TITLE);
    }

    public static function getOgDescription(int $postId): string
    {
        return self::read($postId, self::OG_DESCRIPTION, self::YOAST_OG_DESCRIPTION);
    }

    public static function setMetaDescription(int $postId, string $value): void
    {
        update_post_meta($postId, self::getMetaDescriptionKey(), $value);
    }

    public static function setMetaTitle(int $postId, string $value): void
    {
        update_post_meta($postId, self::getMetaTitleKey(), $value);
    }

    /**
     * Whether another plugin is already emitting title and description tags.
     *
     * Filterable, because this list can only ever be a guess — a theme or a
     * plugin not named here may be handling it perfectly well.
     */
    public static function isHandledElsewhere(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $handled = false;

        foreach (self::SEO_PLUGINS as $plugin) {
            if (is_plugin_active($plugin)) {
                $handled = true;
                break;
            }
        }

        return (bool) apply_filters('yolsa_seo_handled_elsewhere', $handled);
    }

    /** True when YoLSA should emit the tags itself. */
    public static function shouldOutput(): bool
    {
        return (bool) Settings::get('output_meta_tags', true) && !self::isHandledElsewhere();
    }

    /** Hooks the front-end output. */
    public static function init(): void
    {
        add_action('wp_head', [self::class, 'renderHead'], 1);
        add_filter('pre_get_document_title', [self::class, 'filterTitle'], 20);
        add_filter('wp_robots', [self::class, 'filterRobots'], 20);
    }

    /**
     * The Open Graph types worth offering.
     *
     * A short list on purpose. Open Graph defines a great many more, and the
     * interesting ones (product, book, music) only mean anything alongside
     * properties this plugin does not collect — declaring `product` without a
     * price says less than saying nothing.
     *
     * @return array<string, string> Value => label.
     */
    public static function ogTypes(): array
    {
        return [
            'article' => __('Article — a piece of writing with a publication date', 'yolsa'),
            'website' => __('Website — a landing or overview page', 'yolsa'),
            'profile' => __('Profile — a page about a person', 'yolsa'),
            'video.other' => __('Video', 'yolsa'),
        ];
    }

    /**
     * What kind of thing this page is, in Open Graph's vocabulary.
     *
     * A stored choice wins. With nothing stored the post type decides, which
     * keeps every page written before this field existed declaring what it
     * always declared.
     *
     * @param int $postId
     * @return string
     */
    public static function getOgType(int $postId): string
    {
        $stored = trim((string) get_post_meta($postId, self::OG_TYPE, true));

        if ('' !== $stored && isset(self::ogTypes()[$stored])) {
            return $stored;
        }

        return 'post' === get_post_type($postId) ? 'article' : 'website';
    }

    /**
     * The locale to declare, in Open Graph's underscored form.
     *
     * Taken from the request rather than from the post: with a multilingual
     * plugin the same code renders every translation, and the language being
     * read is the answer.
     *
     * @return string
     */
    public static function getOgLocale(): string
    {
        $locale = get_locale();

        // get_locale() already returns lv_LV; a bare "lv" would be a language
        // without a territory, which Open Graph does not accept.
        if (!preg_match('/^[a-z]{2,3}_[A-Z]{2}$/', $locale)) {
            $locale = str_replace('-', '_', $locale);
        }

        return $locale;
    }

    /**
     * The picture a social network should show for this post.
     *
     * The featured image, and nothing else. There is no separate "social image"
     * field on purpose: a second picture to maintain is a second picture to
     * forget, and the featured image is the one an editor has already chosen
     * and can see. A post without one gets no og:image rather than a stand-in,
     * because a wrong picture travels further than a missing one — the SEO box
     * says so where the editor is looking.
     *
     * @param int $postId
     * @return array{url:string,width:int,height:int,alt:string}|null
     */
    public static function getOgImage(int $postId): ?array
    {
        $attachmentId = (int) get_post_thumbnail_id($postId);

        if (!$attachmentId) {
            return null;
        }

        // Full size: the networks downscale, and their minimums (1200px wide
        // for a large card) are above most of the intermediate sizes.
        $image = wp_get_attachment_image_src($attachmentId, 'full');

        if (!$image || empty($image[0])) {
            return null;
        }

        return [
            'url'    => (string) $image[0],
            'width'  => (int) ($image[1] ?? 0),
            'height' => (int) ($image[2] ?? 0),
            'alt'    => trim((string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true)),
        ];
    }

    /**
     * Post types offered on the Indexing tab.
     *
     * @return array<string, string> Name => label.
     */
    public static function postTypeOptions(): array
    {
        $options = [];

        foreach (get_post_types(['public' => true], 'objects') as $type) {
            if ('attachment' === $type->name) {
                continue;
            }
            $options[$type->name] = $type->labels->name ?: $type->name;
        }

        return self::keepStoredOptions($options, 'noindex_post_types');
    }

    /**
     * Taxonomies offered on the Indexing tab.
     *
     * @return array<string, string> Name => label.
     */
    public static function taxonomyOptions(): array
    {
        $options = [];

        foreach (get_taxonomies(['public' => true], 'objects') as $taxonomy) {
            $options[$taxonomy->name] = $taxonomy->labels->name ?: $taxonomy->name;
        }

        return self::keepStoredOptions($options, 'noindex_taxonomies');
    }

    /**
     * Adds anything already saved to a list of choices.
     *
     * These lists are built when the fields are declared, on `init` at priority
     * zero, and plugins register their post types and taxonomies on `init` at
     * priority ten. A choice whose type had not registered yet is a choice the
     * field does not recognise — and a multi-value field drops what it does not
     * recognise when it saves. Somebody would hide a taxonomy, save something
     * unrelated later, and find it quietly unhidden.
     *
     * Whatever is stored therefore stays on the list, even when nothing has
     * claimed the name yet.
     *
     * @param array<string, string> $options
     * @param string                $setting
     * @return array<string, string>
     */
    private static function keepStoredOptions(array $options, string $setting): array
    {
        foreach ((array) Indexing::get($setting, []) as $stored) {
            $stored = (string) $stored;

            if ('' !== $stored && !isset($options[$stored])) {
                $options[$stored] = $stored;
            }
        }

        return $options;
    }

    /**
     * Whether a single term asks to be indexed, hidden, or left to its taxonomy.
     *
     * Same three values as a post, stored in term meta under the same name.
     *
     * @param int $termId
     * @return string '', 'index' or 'noindex'.
     */
    public static function termChoice(int $termId): string
    {
        $value = trim((string) get_term_meta($termId, self::ROBOTS_INDEX, true));

        return in_array($value, ['index', 'noindex'], true) ? $value : '';
    }

    /**
     * Whether a term archive should stay out of search results.
     *
     * The term's own choice first, then its taxonomy's. That ordering is what
     * makes a hidden taxonomy workable: hide every tag archive, then put the
     * handful worth having back by hand.
     *
     * @param int    $termId
     * @param string $taxonomy
     * @return bool
     */
    public static function isNoIndexTerm(int $termId, string $taxonomy): bool
    {
        $choice = self::termChoice($termId);

        if ('noindex' === $choice) {
            return true;
        }

        if ('index' === $choice) {
            return false;
        }

        return self::isNoIndexTaxonomy($taxonomy);
    }

    /**
     * Whether a whole post type was told to stay out of search results.
     *
     * @param string $postType
     * @return bool
     */
    public static function isNoIndexPostType(string $postType): bool
    {
        return in_array($postType, (array) Indexing::get('noindex_post_types', []), true);
    }

    /**
     * Whether a taxonomy's archives were told to stay out of search results.
     *
     * @param string $taxonomy
     * @return bool
     */
    public static function isNoIndexTaxonomy(string $taxonomy): bool
    {
        return in_array($taxonomy, (array) Indexing::get('noindex_taxonomies', []), true);
    }

    /**
     * Whether this post asks to be kept out of search results.
     *
     * Three states rather than a checkbox, because a checkbox cannot say the
     * difference between "leave it to the site" and "definitely index this".
     * That difference matters while Yoast's values are still being inherited:
     * unticking a box would otherwise be indistinguishable from never having
     * touched it, and the old Yoast value would win forever.
     *
     * @param int $postId
     * @return bool
     */
    public static function isNoIndex(int $postId): bool
    {
        // Before anything anybody chose: a search engine reaching a protected
        // post sees the password form, not the article. Indexing that means
        // ranking an empty page, and "Always allow" cannot sensibly overrule
        // it — there is nothing behind it to allow.
        $post = get_post($postId);

        if ($post && '' !== (string) $post->post_password) {
            return true;
        }

        $own = trim((string) get_post_meta($postId, self::ROBOTS_INDEX, true));

        if ('noindex' === $own) {
            return true;
        }

        if ('index' === $own) {
            return false;
        }

        if ('1' === trim((string) get_post_meta($postId, self::YOAST_NOINDEX, true))) {
            return true;
        }

        // The blanket rule comes last: a post that says nothing follows its
        // type, and a post that says "always allow" has already returned above.
        return self::isNoIndexPostType((string) get_post_type($postId));
    }

    /**
     * Adds `noindex` for a post that asked for it.
     *
     * Through `wp_robots` rather than printing a tag: WordPress assembles one
     * robots meta from every contributor, so a second printed tag would be a
     * second, contradictory instruction.
     *
     * @param array $robots
     * @return array
     */
    public static function filterRobots($robots)
    {
        if (!self::shouldOutput()) {
            return $robots;
        }

        if (is_singular()) {
            $postId = get_queried_object_id();

            if (!$postId || !self::isNoIndex($postId)) {
                return $robots;
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();

            if (!$term instanceof \WP_Term || !self::isNoIndexTerm((int) $term->term_id, $term->taxonomy)) {
                return $robots;
            }
        } else {
            return $robots;
        }

        // wp_robots_no_robots() is the core helper for exactly this, and it
        // also drops the previews a hidden page has no business advertising.
        return wp_robots_no_robots($robots);
    }

    /**
     * Emits the description and Open Graph tags for a single post or page.
     *
     * Only on singular views: these values are stored per post, and there is
     * nothing meaningful to say on an archive.
     */
    public static function renderHead(): void
    {
        if (!is_singular() || !self::shouldOutput()) {
            return;
        }

        $postId = get_queried_object_id();

        if (!$postId) {
            return;
        }

        $description = self::getMetaDescription($postId);
        $ogTitle     = self::getOgTitle($postId) ?: self::getMetaTitle($postId);
        $ogDesc      = self::getOgDescription($postId) ?: $description;
        $ogImage     = self::getOgImage($postId);

        // Say nothing at all unless something was actually stored for this
        // post. Emitting an og:title derived from the post title would add a
        // tag the theme very likely already provides, on every page, whether or
        // not YoLSA has ever been used on it.
        if ('' === $description && '' === $ogTitle && '' === $ogDesc && !$ogImage) {
            return;
        }

        // Only worth a title for social once there is something to accompany it.
        if ('' === $ogTitle) {
            $ogTitle = (string) get_the_title($postId);
        }

        echo "\n<!-- YoLSA -->\n";

        if ('' !== $description) {
            printf('<meta name="description" content="%s" />' . "\n", esc_attr($description));
        }

        if ('' !== $ogTitle) {
            printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($ogTitle));
        }

        // Nothing to store for these three: the address is the permalink, the
        // type follows the post type, and the language is whatever the request
        // is being served in — which under a multilingual plugin is the
        // language of the translation being read.
        printf('<meta property="og:url" content="%s" />' . "\n", esc_url(get_permalink($postId)));
        printf('<meta property="og:type" content="%s" />' . "\n", esc_attr(self::getOgType($postId)));
        printf('<meta property="og:locale" content="%s" />' . "\n", esc_attr(self::getOgLocale()));

        if ('' !== $ogDesc) {
            printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($ogDesc));
        }

        if ($ogImage) {
            printf('<meta property="og:image" content="%s" />' . "\n", esc_url($ogImage['url']));

            // Facebook and the rest lay the card out before the image has
            // loaded when they are told the size, and reflow it when they are
            // not.
            if ($ogImage['width'] && $ogImage['height']) {
                printf('<meta property="og:image:width" content="%d" />' . "\n", $ogImage['width']);
                printf('<meta property="og:image:height" content="%d" />' . "\n", $ogImage['height']);
            }

            if ('' !== $ogImage['alt']) {
                printf('<meta property="og:image:alt" content="%s" />' . "\n", esc_attr($ogImage['alt']));
            }
        }

        echo "<!-- /YoLSA -->\n";
    }

    /**
     * Replaces the document title when one has been stored for this post.
     *
     * pre_get_document_title short-circuits WordPress's own assembly, which is
     * what we want: a stored SEO title is a complete title, not a fragment to
     * be joined with the site name.
     *
     * @param string $title
     * @return string
     */
    public static function filterTitle($title)
    {
        if (!is_singular() || !self::shouldOutput()) {
            return $title;
        }

        $postId = get_queried_object_id();

        if (!$postId) {
            return $title;
        }

        $stored = self::getMetaTitle($postId);

        return '' !== $stored ? $stored : $title;
    }

    /**
     * Who may write these fields through the REST API.
     *
     * All four keys start with an underscore, which makes them protected meta,
     * and WordPress defaults the auth callback of protected meta to
     * __return_false. Registering them for REST without saying who may write
     * them therefore blocks the block editor from saving *any* post: the editor
     * sends every registered meta field back on save, core cannot skip a field
     * that has no stored row yet, and the capability check then refuses it --
     * for administrators too. Whoever may edit the post may edit its SEO text.
     *
     * @param bool   $allowed Whether the user can add the meta. Ignored.
     * @param string $meta_key
     * @param int    $post_id
     * @return bool
     */
    public static function canEditMeta($allowed, $meta_key, $post_id): bool
    {
        return current_user_can('edit_post', $post_id);
    }

    public static function registerFallbackMeta(): void
    {
        $args = [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => [self::class, 'canEditMeta'],
        ];

        $post_types = get_post_types(['public' => true]);
        foreach ($post_types as $post_type) {
            register_post_meta($post_type, self::META_DESCRIPTION, $args);
            register_post_meta($post_type, self::META_TITLE, $args);
            register_post_meta($post_type, self::OG_TITLE, $args);
            register_post_meta($post_type, self::OG_DESCRIPTION, $args);
            register_post_meta($post_type, self::ROBOTS_INDEX, $args);
            register_post_meta($post_type, self::OG_TYPE, $args);
        }
    }
}
