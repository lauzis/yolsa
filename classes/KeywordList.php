<?php

namespace SeoAudit;

/**
 * The keyword list screen.
 *
 * The list every audit is measured against, on its own page rather than buried
 * behind a settings tab. It is edited far more often than anything on the
 * Settings page — it is the working material of the plugin, not its
 * configuration.
 *
 * Stored exactly as before: an unprefixed `keyword_list` theme option, which
 * `Helpers` and the keyword audit already read. Moving the screen must not move
 * anybody's data.
 */
class KeywordList
{
    /**
     * @return \Lauzis\WpPackages\Settings\Settings|null
     */
    public static function page()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::settings('yolsa-keyword-list', [
            'title'       => __('Keyword list', 'yolsa'),
            'mode'        => 'flat',
            'page_parent' => 'yolsa-audit',
            'page_file'   => 'yolsa-keyword-list',
        ]);
    }

    /** Declares the fields. Hooked on carbon_fields_register_fields. */
    public static function register(): void
    {
        $page = self::page();

        if (!$page) {
            return;
        }

        $page->register(YOLSA_PLUGIN_DIR . 'config/keywords.json', [
            'prefix' => '',
            'domain' => 'yolsa',
        ]);

        $page->render();
    }

    /**
     * Declares the per-post keyword list. Hooked on carbon_fields_register_fields.
     *
     * `Helpers::get_keywords_arr()` has always read a post's own `keyword_list`
     * ahead of the global one, but no box declared it, so nothing could write
     * it — Carbon Fields only saves fields it knows about. Same shape as the
     * global list, so the audit reads both the same way.
     */
    public static function registerPostMeta(): void
    {
        $types = SeoMetaBox::postTypes();

        if (!$types) {
            return;
        }

        \Carbon_Fields\Container::make('post_meta', __('Keywords', 'yolsa'))
            ->where('post_type', 'IN', $types)
            ->set_context('normal')
            ->set_priority('low')
            ->add_fields([
                \Carbon_Fields\Field::make('complex', 'keyword_list', __('Keyword list for this page', 'yolsa'))
                    ->set_help_text(__('Replaces the site-wide keyword list when this page is audited. Empty uses the site-wide list.', 'yolsa'))
                    ->add_fields([
                        \Carbon_Fields\Field::make('text', 'keyword', __('Keyword', 'yolsa')),
                        \Carbon_Fields\Field::make('complex', 'keyword_variations', __('Variations', 'yolsa'))
                            ->add_fields([
                                \Carbon_Fields\Field::make('text', 'keyword_variation', __('Variation', 'yolsa')),
                            ]),
                    ]),
            ]);
    }
}
