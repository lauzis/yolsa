<?php

namespace Lauzis\WpPackages\Migrations;

/**
 * Runs a plugin's data migrations once each, in version order.
 *
 * A migration is a piece of work that has to happen when stored data no longer
 * matches what the code expects — renaming an option, moving meta, backfilling
 * a column. The version it is registered against is the plugin version that
 * introduced the need for it.
 *
 * The applied version is recorded after each migration rather than at the end,
 * so a run that fails half way keeps the work it already did and resumes from
 * there rather than repeating it.
 */
class Runner {

	/** Prefix for the lock that keeps two concurrent requests from both running. */
	const LOCK_PREFIX = 'wp_packages_migrating_';

	/**
	 * How long the lock is held before it is assumed abandoned, in seconds.
	 *
	 * A literal rather than MINUTE_IN_SECONDS: a class constant is evaluated when
	 * the file loads, so depending on a WordPress constant here would stop this
	 * file loading anywhere WordPress is not booted — tooling and tests included.
	 */
	const LOCK_TTL = 300;

	/** @var string */
	private $slug;

	/** @var string Version of the code currently running. */
	private $version;

	/** @var string Option holding the highest version applied so far. */
	private $option;

	/** @var array<string, callable> Version => migration. */
	private $migrations = array();

	/**
	 * @param string $slug   Plugin slug.
	 * @param array  $config {
	 *     @type string $version Current plugin version. Required for run() to do
	 *                           anything useful.
	 *     @type string $option  Option name storing the applied version.
	 *                           Defaults to "{slug}_data_version".
	 * }
	 */
	public function __construct( $slug, array $config = array() ) {
		$this->slug    = $slug;
		$this->version = isset( $config['version'] ) ? (string) $config['version'] : '';
		$this->option  = isset( $config['option'] )
			? $config['option']
			: preg_replace( '/[^a-z0-9_]/', '_', strtolower( $slug ) ) . '_data_version';
	}

	/**
	 * Registers a migration.
	 *
	 * The callable may return false to say "not finished" — the version is then
	 * not recorded and the migration runs again on the next request. That is how
	 * a migration too large for one request processes a batch at a time.
	 * Anything else, including no return value, counts as done.
	 *
	 * @param string   $version   Plugin version that introduced the need for it.
	 * @param callable $migration
	 * @return $this
	 */
	public function add( $version, callable $migration ) {
		$this->migrations[ (string) $version ] = $migration;

		return $this;
	}

	/**
	 * Applies whatever has not run yet. Safe to call on every request.
	 *
	 * @return array{applied: string[], failed: string|null, skipped: bool}
	 */
	public function run() {
		$report = array( 'applied' => array(), 'failed' => null, 'skipped' => false );

		if ( empty( $this->migrations ) ) {
			return $report;
		}

		$from = $this->applied_version();

		if ( ! $this->pending( $from ) ) {
			return $report;
		}

		// Two requests arriving together would otherwise both migrate. Whoever
		// gets the lock does the work; the other returns and picks up whatever
		// is left on a later request.
		if ( ! $this->lock() ) {
			$report['skipped'] = true;

			return $report;
		}

		try {
			foreach ( $this->pending( $from ) as $version => $migration ) {
				$result = call_user_func( $migration );

				if ( false === $result ) {
					// Deliberately unfinished: leave the version unrecorded so
					// the next request continues it, and stop here so later
					// migrations never run against half-migrated data.
					break;
				}

				update_option( $this->option, $version, false );
				$report['applied'][] = $version;

				$this->log( 'migration', 'Applied migration.', array( 'version' => $version ) );
			}
		} catch ( \Throwable $e ) {
			$report['failed'] = $e->getMessage();

			$this->log_error(
				'migration',
				'A migration failed; later ones were not attempted.',
				array( 'error' => $e->getMessage() )
			);
		}

		$this->unlock();

		return $report;
	}

	/**
	 * Records the current version without running anything.
	 *
	 * For a fresh install on activation: there is no old data to migrate, and
	 * running the whole history against an empty site is at best wasted work
	 * and at worst wrong. Does nothing if a version is already recorded, so
	 * calling it on reactivation cannot erase migration state.
	 *
	 * @return bool True when a baseline was written.
	 */
	public function baseline() {
		if ( '' !== (string) $this->applied_version() ) {
			return false;
		}

		update_option( $this->option, $this->version, false );

		return true;
	}

	/** The highest version applied so far, or '' on a site that has never run. */
	public function applied_version() {
		return (string) get_option( $this->option, '' );
	}

	/** Whether anything is waiting to run. */
	public function has_pending() {
		return ! empty( $this->pending( $this->applied_version() ) );
	}

	/**
	 * Migrations newer than $from and no newer than the running code, in order.
	 *
	 * The upper bound matters on a downgrade: code rolled back to an earlier
	 * version must not run migrations it does not contain the rest of.
	 *
	 * @param string $from
	 * @return array<string, callable>
	 */
	private function pending( $from ) {
		$pending = array();

		foreach ( $this->migrations as $version => $migration ) {
			if ( '' !== $from && version_compare( $version, $from, '<=' ) ) {
				continue;
			}

			if ( '' !== $this->version && version_compare( $version, $this->version, '>' ) ) {
				continue;
			}

			$pending[ $version ] = $migration;
		}

		uksort( $pending, 'version_compare' );

		return $pending;
	}

	/** @return bool True when this request may migrate. */
	private function lock() {
		$key = self::LOCK_PREFIX . $this->slug;

		if ( get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, 1, self::LOCK_TTL );

		return true;
	}

	private function unlock() {
		delete_transient( self::LOCK_PREFIX . $this->slug );
	}

	/**
	 * @param string $action
	 * @param string $message
	 * @param array  $context
	 */
	private function log( $action, $message, array $context ) {
		if ( class_exists( 'WpPackages_Registry' ) ) {
			\WpPackages_Registry::logger( $this->slug )->add( $action, $message, $context );
		}
	}

	/**
	 * @param string $action
	 * @param string $message
	 * @param array  $context
	 */
	private function log_error( $action, $message, array $context ) {
		if ( class_exists( 'WpPackages_Registry' ) ) {
			\WpPackages_Registry::logger( $this->slug )->error( $action, $message, $context );
		}
	}
}
