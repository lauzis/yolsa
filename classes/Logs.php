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

    /** Deletes every daily log file. */
    public static function clear(): bool
    {
        $logger = self::logger();

        return $logger ? $logger->clear() : false;
    }
}
