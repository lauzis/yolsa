<?php

namespace Lauzis\WpPackages\Logs;

/**
 * File-based logger shared by the plugins.
 *
 * One instance per plugin, obtained through WpPackages_Registry::logger(). Log
 * lines keep the format the plugins already use, so existing log files stay
 * readable after migrating:
 *
 *     [2026-07-31 09:14:02] [cron] Batch finished. | {"processed":12}
 *
 * Files are named "{channel}-YYYY-MM-DD.log" and the default channel is the
 * plugin slug, which reproduces the previous naming exactly.
 */
class Logger {

	/** @var string */
	private $slug;

	/** @var string Absolute path to the log directory, with trailing slash. */
	private $dir;

	/** @var callable|bool|null */
	private $enabled;

	/** @var bool Used when the schema has not been registered yet. */
	private $enabled_default;

	/** @var string */
	private $default_channel;

	/**
	 * @param string $slug   Plugin slug.
	 * @param array  $config {
	 *     @type string        $dir      Absolute path to the log directory. Defaults
	 *                                   to uploads/{slug}-logs/.
	 *     @type callable|bool $enabled  Whether logging is on. A callable is
	 *                                   resolved per call, so a settings change
	 *                                   takes effect immediately. Omit it and the
	 *                                   component reads its own 'logs_enabled'
	 *                                   setting from the plugin's settings page.
	 *     @type bool          $enabled_default Value to assume before the schema
	 *                                   has been registered — logging can happen
	 *                                   during bootstrap or cron, earlier than
	 *                                   carbon_fields_register_fields. Default false.
	 *     @type string        $channel  Default channel name. Defaults to $slug.
	 * }
	 */
	public function __construct( $slug, array $config = array() ) {
		$this->slug            = $this->sanitize_channel( $slug );
		$this->enabled         = isset( $config['enabled'] ) ? $config['enabled'] : null;
		$this->enabled_default = ! empty( $config['enabled_default'] );
		$this->default_channel = isset( $config['channel'] ) ? $this->sanitize_channel( $config['channel'] ) : $this->slug;

		if ( isset( $config['dir'] ) ) {
			$this->dir = rtrim( str_replace( '\\', '/', $config['dir'] ), '/' ) . '/';
		} else {
			$uploads   = wp_upload_dir();
			$this->dir = str_replace( '\\', '/', $uploads['basedir'] ) . '/' . $this->slug . '-logs/';
		}
	}

	/**
	 * Returns true when logging is currently switched on.
	 *
	 * With no explicit 'enabled' config the component reads the 'logs_enabled'
	 * field from its own schema, so a plugin that registers settings/logs.json
	 * does not have to wire the setting through by hand. The bare id is used,
	 * so this still works for a plugin that mapped the field onto a legacy
	 * option key.
	 */
	public function isEnabled() {
		if ( null === $this->enabled ) {
			if ( ! class_exists( 'WpPackages_Registry' ) ) {
				return false;
			}

			return (bool) \WpPackages_Registry::settings( $this->slug )->get( 'logs_enabled', $this->enabled_default );
		}

		return is_callable( $this->enabled ) ? (bool) call_user_func( $this->enabled ) : (bool) $this->enabled;
	}

	/** Absolute path to the log directory, with trailing slash. */
	public function dir() {
		return $this->dir;
	}

	/**
	 * Appends an entry to today's log file, if logging is enabled.
	 *
	 * @param string      $action  Short label.
	 * @param string      $message Human-readable message.
	 * @param array       $context Key-value context, appended as JSON.
	 * @param string|null $channel Alternate log stream. Defaults to the plugin's own.
	 * @return bool True on success; false if disabled or the write failed.
	 */
	public function add( $action, $message = '', array $context = array(), $channel = null ) {
		if ( ! $this->isEnabled() ) {
			return false;
		}

		return $this->write( $this->format( $action, $message, $context ), $channel );
	}

	/**
	 * Logs a failure unconditionally: always to PHP's error_log, and to the
	 * plugin's own file as well when logging is enabled.
	 *
	 * Use for failures that should never be silent.
	 *
	 * @param string $action  Short label.
	 * @param string $message Human-readable message.
	 * @param array  $context Key-value context, appended as JSON.
	 */
	public function error( $action, $message = '', array $context = array() ) {
		$line = $this->format( $action, $message, $context );

		error_log( $this->slug . ': ' . $line );

		if ( $this->isEnabled() ) {
			$this->write( $line, null );
		}
	}

	/**
	 * Deletes the daily log files for a channel.
	 *
	 * @param string|null $channel Defaults to the plugin's own channel. Pass '*'
	 *                             to clear every channel in the log directory.
	 * @return bool True if the directory was readable and deletion was attempted.
	 */
	public function clear( $channel = null ) {
		if ( ! is_dir( $this->dir ) ) {
			return false;
		}

		foreach ( $this->paths( $channel ) as $file ) {
			@unlink( $file );
		}

		return true;
	}

	/**
	 * Total number of log entries (lines) across a channel's daily files.
	 *
	 * @param string|null $channel Defaults to the plugin's own channel.
	 * @return int
	 */
	public function count( $channel = null ) {
		$total = 0;

		foreach ( $this->files( $channel ) as $file ) {
			$total += $file['count'];
		}

		return $total;
	}

	/**
	 * Lists a channel's daily log files, newest first.
	 *
	 * @param string|null $channel Defaults to the plugin's own channel.
	 * @return array[] Each entry: ['file' => string, 'name' => string, 'date' => string, 'count' => int]
	 */
	public function files( $channel = null ) {
		$result = array();

		foreach ( $this->paths( $channel ) as $file ) {
			$name  = basename( $file );
			$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

			$result[] = array(
				'file'  => $file,
				'name'  => $name,
				'date'  => preg_replace( '/^.*?-(\d{4}-\d{2}-\d{2})\.log$/', '$1', $name ),
				'count' => count( $lines ? $lines : array() ),
			);
		}

		return $result;
	}

	/**
	 * Reads back a channel's entries, newest file first.
	 *
	 * @param string|null $channel Defaults to the plugin's own channel.
	 * @param int         $limit   Maximum lines to return; 0 for all.
	 * @return string[]
	 */
	public function read( $channel = null, $limit = 0 ) {
		$lines = array();

		foreach ( $this->paths( $channel ) as $file ) {
			$contents = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

			if ( $contents ) {
				$lines = array_merge( $lines, array_reverse( $contents ) );
			}

			if ( $limit > 0 && count( $lines ) >= $limit ) {
				break;
			}
		}

		return $limit > 0 ? array_slice( $lines, 0, $limit ) : $lines;
	}

	/**
	 * Absolute paths of a channel's log files, newest first.
	 *
	 * @param string|null $channel Channel name, or '*' for every channel.
	 * @return string[]
	 */
	private function paths( $channel = null ) {
		if ( ! is_dir( $this->dir ) ) {
			return array();
		}

		$prefix = ( '*' === $channel ) ? '*' : $this->sanitize_channel( null === $channel ? $this->default_channel : $channel );
		$files  = glob( $this->dir . $prefix . '-*.log' );

		if ( ! $files ) {
			return array();
		}

		rsort( $files );

		return $files;
	}

	/**
	 * Builds a single log line.
	 *
	 * An empty $action omits the action segment entirely, which keeps the
	 * format of audit-style streams that only carry a message.
	 */
	private function format( $action, $message, array $context ) {
		$prefix = '[' . gmdate( 'Y-m-d H:i:s' ) . '] ';
		$line   = '' === $action ? $prefix . $message : $prefix . '[' . $action . '] ' . $message;

		if ( ! empty( $context ) ) {
			$line .= ' | ' . wp_json_encode( $context );
		}

		return $line;
	}

	/** Appends a line to a channel's file for today. */
	private function write( $line, $channel ) {
		if ( ! $this->ensure_dir() ) {
			return false;
		}

		$channel = $this->sanitize_channel( null === $channel ? $this->default_channel : $channel );
		$file    = $this->dir . $channel . '-' . gmdate( 'Y-m-d' ) . '.log';

		return (bool) file_put_contents( $file, $line . PHP_EOL, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Creates the log directory if needed and keeps it non-browsable.
	 *
	 * @return bool True when the directory exists and is writable.
	 */
	private function ensure_dir() {
		if ( ! is_dir( $this->dir ) ) {
			wp_mkdir_p( $this->dir );
		}

		if ( ! is_dir( $this->dir ) || ! is_writable( $this->dir ) ) {
			return false;
		}

		$index = $this->dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<?php // Silence is golden.' );
		}

		// Defence in depth on Apache: the log directory usually lives under
		// uploads/, which is web-served. index.php stops directory listing but
		// not direct hits on a known filename.
		$htaccess = $this->dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}

		return true;
	}

	/**
	 * Restricts a channel name to characters that are safe in a filename,
	 * so a caller-supplied channel cannot escape the log directory.
	 *
	 * @param string $channel
	 * @return string
	 */
	private function sanitize_channel( $channel ) {
		$channel = strtolower( (string) $channel );
		$channel = preg_replace( '/[^a-z0-9_-]/', '', $channel );

		return '' !== $channel ? $channel : 'log';
	}
}
