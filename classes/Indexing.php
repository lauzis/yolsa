<?php

namespace SeoAudit;

/**
 * The Indexing screen.
 *
 * Its own page rather than a tab inside Settings, because "these pages are not
 * in Google" is not a preference — it is a decision about what the site shows
 * the world, and it should be somewhere a person can find without remembering
 * which tab it was on.
 *
 * The fields are stored exactly as they were when they lived under Settings:
 * unprefixed theme options, read through `carbon_get_theme_option()`. Moving a
 * screen must not move anybody's data.
 */
class Indexing
{
    /**
     * Returns the page, or null when the shared package is absent.
     *
     * @return \Lauzis\WpPackages\Settings\Settings|null
     */
    public static function page()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::settings('yolsa-indexing', [
            'title'       => __('Indexing', 'yolsa'),
            'mode'        => 'flat',
            'page_parent' => 'yolsa-audit',
            'page_file'   => 'yolsa-indexing',
        ]);
    }

    /** Declares the fields. Hooked on carbon_fields_register_fields. */
    public static function register(): void
    {
        $page = self::page();

        if (!$page) {
            return;
        }

        // The lists are whatever this site has, which a JSON file cannot know.
        $page->callback('yolsa_post_types', [SeoMeta::class, 'postTypeOptions']);
        $page->callback('yolsa_taxonomies', [SeoMeta::class, 'taxonomyOptions']);

        $page->register(YOLSA_PLUGIN_DIR . 'config/indexing.json', [
            'prefix' => '',
            'domain' => 'yolsa',
        ]);

        $page->render();
    }

    /**
     * Reads one of these settings.
     *
     * Straight from the theme option rather than through the page object: the
     * gate runs on front-end requests, where building an admin page to ask it a
     * question would be absurd.
     *
     * @param string $id
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $id, $default = null)
    {
        if (!function_exists('carbon_get_theme_option')) {
            return $default;
        }

        $value = carbon_get_theme_option($id);

        return (null === $value || '' === $value || [] === $value) ? $default : $value;
    }
}
