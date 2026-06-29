<?php

namespace SeoAudit;

class SeoMeta
{
    const META_DESCRIPTION = '_yolsa_meta_description';
    const META_TITLE       = '_yolsa_meta_title';
    const OG_TITLE         = '_yolsa_og_title';
    const OG_DESCRIPTION   = '_yolsa_og_description';

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

    public static function getMetaDescription(int $postId): string
    {
        return (string) get_post_meta($postId, self::getMetaDescriptionKey(), true);
    }

    public static function getMetaTitle(int $postId): string
    {
        return (string) get_post_meta($postId, self::getMetaTitleKey(), true);
    }

    public static function getOgTitle(int $postId): string
    {
        return (string) get_post_meta($postId, self::getOgTitleKey(), true);
    }

    public static function getOgDescription(int $postId): string
    {
        return (string) get_post_meta($postId, self::getOgDescriptionKey(), true);
    }

    public static function setMetaDescription(int $postId, string $value): void
    {
        update_post_meta($postId, self::getMetaDescriptionKey(), $value);
    }

    public static function setMetaTitle(int $postId, string $value): void
    {
        update_post_meta($postId, self::getMetaTitleKey(), $value);
    }

    public static function registerFallbackMeta(): void
    {
        $post_types = get_post_types(['public' => true]);
        foreach ($post_types as $post_type) {
            register_post_meta($post_type, self::META_DESCRIPTION, [
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
            ]);
            register_post_meta($post_type, self::META_TITLE, [
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
            ]);
            register_post_meta($post_type, self::OG_TITLE, [
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
            ]);
            register_post_meta($post_type, self::OG_DESCRIPTION, [
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
            ]);
        }
    }
}
