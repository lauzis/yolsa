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
}
