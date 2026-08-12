<?php

namespace SeoAudit;

/**
 * YoLSA's logging entry point.
 *
 * A thin facade over the shared logger in lauzis/wp-plugin-packages, so call
 * sites stay short and logging degrades to silence — rather than fataling — if
 * the package is ever missing.
 */
class Logs
{
    private const SLUG = 'yolsa';

    /**
     * Returns the shared logger, or null when the package is unavailable.
     *
     * No 'enabled' is passed: the component reads the logs_enabled field from
     * the schema registered in Settings. enabled_default is deliberately false,
     * so a site that has never opened the settings page writes nothing.
     *
     * @return \Lauzis\WpPackages\Logs\Logger|null
     */
    private static function logger()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::logger(self::SLUG, ['dir' => YOLSA_LOG_PATH]);
    }

    /**
     * Records an event when logging is switched on.
     *
     * @param string $action  Short label, e.g. 'meta-description'.
     * @param string $message Human-readable message.
     * @param array  $context Key-value context, appended as JSON.
     */
    public static function add(string $action, string $message = '', array $context = []): bool
    {
        $logger = self::logger();

        return $logger ? $logger->add($action, $message, $context) : false;
    }

    /**
     * Records a failure. Always reaches PHP's error log, whatever the setting
     * says, so problems are never entirely silent.
     */
    public static function error(string $action, string $message = '', array $context = []): void
    {
        $logger = self::logger();

        if ($logger) {
            $logger->error($action, $message, $context);
        }
    }

    /** True when file logging is switched on in Settings. */
    public static function enabled(): bool
    {
        $logger = self::logger();

        return $logger ? $logger->isEnabled() : false;
    }

    /**
     * Daily log files, newest first.
     *
     * @return array[] Each: ['file', 'name', 'date', 'count'].
     */
    public static function files(): array
    {
        $logger = self::logger();

        return $logger ? $logger->files() : [];
    }

    /** Entries in a single day's file, newest first. */
    public static function read(?string $date = null, int $limit = 0): array
    {
        $logger = self::logger();

        if (!$logger) {
            return [];
        }

        if (null === $date) {
            return $logger->read(null, $limit);
        }

        $file = $logger->dir() . self::SLUG . '-' . $date . '.log';

        if (!is_readable($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_reverse($lines);
    }

    /**
     * The log, as a panel for the settings page.
     *
     * The listing is the shared package's, because every plugin here writes the
     * same log and would otherwise grow its own reader for it. What stays here
     * is whether to show it and what happens when somebody clears it.
     */
    public static function panel(): string
    {
        $logger = self::logger();

        if (!$logger || !class_exists('\\Lauzis\\WpPackages\\Logs\\Viewer')) {
            // An older copy of the shared package won the version race — see
            // WpPackages_Registry. The rest of the page still works, so this
            // says what is missing rather than fataling.
            return '<p class="description">'
                . esc_html__('The log reader needs a newer copy of the shared package than the one running.', 'yolsa')
                . '</p>';
        }

        $viewer = new \Lauzis\WpPackages\Logs\Viewer($logger, ['clear' => 'yolsa_clear_logs']);

        return $viewer->render();
    }

    /** Empties the log, from the button on that panel. */
    public static function handleClear(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do that.', 'yolsa'));
        }

        check_admin_referer('yolsa_clear_logs');

        self::add('logs', 'Log cleared from the settings page.', ['user' => get_current_user_id()]);
        self::clear();

        // Back where the button was, whichever screen carried the panel.
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /** Deletes every daily log file. */
    public static function clear(): bool
    {
        $logger = self::logger();

        return $logger ? $logger->clear() : false;
    }
}
