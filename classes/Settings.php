<?php

namespace SeoAudit;

/**
 * YoLSA's settings page.
 *
 * Fields are declared in config/settings.json and rendered by the shared
 * lauzis/wp-plugin-packages settings component. The AI provider configuration
 * comes from the package's own schema, so it matches the other plugins.
 */
class Settings
{
    /**
     * Returns the shared settings page, or null when the package is absent.
     *
     * @return \Lauzis\WpPackages\Settings\Settings|null
     */
    public static function page()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::settings('yolsa', [
            // Just "Settings": the menu it sits under is already called YoLSA,
            // and the other plugins label theirs the same way.
            'title'       => __('Settings', 'yolsa'),
            'mode'        => 'tabs',
            'page_parent' => 'yolsa-audit',
            'page_file'   => 'yolsa-settings',
        ]);
    }

    /** Declares the settings fields. Hooked on carbon_fields_register_fields. */
    public static function register(): void
    {
        $page = self::page();

        if (!$page) {
            return;
        }

        // No prefix: YoLSA's option keys predate the shared loader and are kept
        // exactly as they are, so nothing stored has to move.
        $page->register(YOLSA_PLUGIN_DIR . 'config/settings.json', [
            'prefix' => '',
            'domain' => 'yolsa',
        ]);

        // The provider and logging fields are prefixed, since they are new here
        // and bare names like "llm_provider" would be far too generic to sit
        // unnamespaced alongside other plugins' options.
        $page->register(\WpPackages_Registry::schema('llm'), [
            'prefix' => 'yolsa_',
            'domain' => 'wp-plugin-packages',
        ]);

        $page->register(\WpPackages_Registry::schema('logs'), [
            'prefix' => 'yolsa_',
            'domain' => 'wp-plugin-packages',
        ]);

        // The log itself, added to the section the package just declared so the
        // switch and what it writes share a tab. Whether it appears at all is
        // this plugin's decision, which is why it is registered here.
        $page->callback('yolsa_logs_view', [Logs::class, 'panel']);

        $page->register(YOLSA_PLUGIN_DIR . 'config/logs.json', [
            'prefix' => 'yolsa_',
            'domain' => 'yolsa',
        ]);

        $page->render();
    }

    /**
     * Reads a setting by its id.
     *
     * @param string $id
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $id, $default = null)
    {
        $page = self::page();

        return $page ? $page->get($id, $default) : $default;
    }
}
