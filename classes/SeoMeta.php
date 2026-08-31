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
        $own = trim((string) get_post_meta($postId, self::ROBOTS_INDEX, true));

        if ('noindex' === $own) {
            return true;
        }

        if ('index' === $own) {
            return false;
        }

        return '1' === trim((string) get_post_meta($postId, self::YOAST_NOINDEX, true));
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
        if (!is_singular() || !self::shouldOutput()) {
            return $robots;
        }

        $postId = get_queried_object_id();

        if (!$postId || !self::isNoIndex($postId)) {
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

        // Say nothing at all unless something was actually stored for this
        // post. Emitting an og:title derived from the post title would add a
        // tag the theme very likely already provides, on every page, whether or
        // not YoLSA has ever been used on it.
        if ('' === $description && '' === $ogTitle && '' === $ogDesc) {
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

        if ('' !== $ogDesc) {
            printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($ogDesc));
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
        }
    }
}
