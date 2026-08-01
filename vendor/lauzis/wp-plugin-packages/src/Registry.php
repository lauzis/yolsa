<?php
/**
 * Version gate for lauzis/wp-plugin-packages.
 *
 * Every plugin that uses these components ships its own copy in vendor/.
 * Without arbitration PHP would use whichever copy autoloaded first, so a
 * plugin shipping a newer version could silently run an older one — and
 * calling a method that version does not have is a fatal.
 *
 * This class is deliberately global (not namespaced) and guarded by
 * class_exists(): the first copy to load defines it, and every later copy
 * registers against that same instance. Because an OLD copy may be the one
 * that wins the race, this API must stay backwards compatible essentially
 * forever. Keep it small, and put new behaviour in the component classes.
 *
 * One registry covers every component. That is the reason the components live
 * in a single package: a registry per component would mean several copies of
 * this same never-changeable arbitration logic.
 *
 * IMPORTANT: each plugin must require the package's bootstrap.php explicitly,
 * rather than leaving it to Composer's "files" autoload. Composer keys that
 * mechanism on an identifier derived from the package name, which is identical
 * in every plugin's vendor directory, so it runs exactly one copy's bootstrap
 * for the whole request and the rest never register at all. require_once keys
 * on the resolved path, which differs per plugin, so every copy is seen.
 */

if ( class_exists( 'WpPackages_Registry', false ) ) {
	return;
}

class WpPackages_Registry {

	/** @var array<string, string> version => loader path */
	private static $copies = array();

	/** @var array<string, string> version => package root directory */
	private static $roots = array();

	/** @var bool */
	private static $booted = false;

	/** @var bool Whether the deferred boot has been scheduled. */
	private static $hooked = false;

	/** @var array<string, array<string, mixed>> component => slug => instance */
	private static $instances = array();

	/**
	 * Announces a bundled copy of the library.
	 *
	 * @param string $version Semantic version of this copy.
	 * @param string $path    Absolute path to that copy's src/load.php.
	 * @param string $root    Absolute path to that copy's package root, used to
	 *                        locate the bundled CSS/JS at runtime.
	 */
	public static function register( $version, $path, $root ) {
		self::$copies[ $version ] = $path;
		self::$roots[ $version ]  = $root;

		// Arbitration is only correct once every active plugin has registered,
		// which is not until all plugin files have run. Booting on first use
		// instead would lock in whichever copy happened to be asked first --
		// exactly the outcome this class exists to prevent.
		if ( self::$hooked || self::$booted ) {
			return;
		}

		if ( function_exists( 'add_action' ) && function_exists( 'did_action' ) && ! did_action( 'plugins_loaded' ) ) {
			self::$hooked = true;

			add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), -9999 );
		}
	}

	/** Loads the highest registered version. Idempotent. */
	public static function boot() {
		if ( self::$booted || empty( self::$copies ) ) {
			return;
		}

		self::$booted = true;

		require_once self::$copies[ self::active_version() ];
	}

	/**
	 * Returns the file logger for a plugin, creating it on first use.
	 *
	 * @param string $slug   Plugin slug — namespaces the log files and the
	 *                       error_log prefix.
	 * @param array  $config See Lauzis\WpPackages\Logs\Logger::__construct().
	 * @return \Lauzis\WpPackages\Logs\Logger
	 */
	public static function logger( $slug, array $config = array() ) {
		return self::instance( 'logger', $slug, $config, '\Lauzis\WpPackages\Logs\Logger' );
	}

	/**
	 * Returns the admin-notice manager for a plugin, creating it on first use.
	 *
	 * @param string $slug   Plugin slug — namespaces the stored dismissals, the
	 *                       AJAX action and the nonce.
	 * @param array  $config See Lauzis\WpPackages\Notices\Notices::__construct().
	 * @return \Lauzis\WpPackages\Notices\Notices
	 */
	public static function notices( $slug, array $config = array() ) {
		return self::instance( 'notices', $slug, $config, '\Lauzis\WpPackages\Notices\Notices' );
	}

	/**
	 * Returns the toast component for a plugin, creating it on first use.
	 *
	 * @param string $slug   Plugin slug.
	 * @param array  $config See Lauzis\WpPackages\Notices\Toasts::__construct().
	 * @return \Lauzis\WpPackages\Notices\Toasts
	 */
	public static function toasts( $slug, array $config = array() ) {
		return self::instance( 'toasts', $slug, $config, '\Lauzis\WpPackages\Notices\Toasts' );
	}

	/**
	 * Returns the settings page for a plugin, creating it on first use.
	 *
	 * Cached per slug, so a component can reach the same instance later to read
	 * its own setting without the plugin passing anything through.
	 *
	 * @param string $slug   Plugin slug.
	 * @param array  $config See Lauzis\WpPackages\Settings\Settings::__construct().
	 * @return \Lauzis\WpPackages\Settings\Settings
	 */
	public static function settings( $slug, array $config = array() ) {
		return self::instance( 'settings', $slug, $config, '\Lauzis\WpPackages\Settings\Settings' );
	}

	/**
	 * Returns the LLM client for a plugin, creating it on first use.
	 *
	 * @param string $slug   Plugin slug.
	 * @param array  $config See Lauzis\WpPackages\Llm\Client::__construct().
	 * @return \Lauzis\WpPackages\Llm\Client
	 */
	public static function llm( $slug, array $config = array() ) {
		return self::instance( 'llm', $slug, $config, '\Lauzis\WpPackages\Llm\Client' );
	}

	/**
	 * Absolute path to a schema file shipped by this package.
	 *
	 * Resolved against the winning copy, so the schema always matches the code
	 * that renders it.
	 *
	 * @param string $name Component name, e.g. 'logs'.
	 * @return string
	 */
	public static function schema( $name ) {
		self::boot();

		return self::active_root() . '/settings/' . $name . '.json';
	}

	/**
	 * Boots the library and returns a cached per-slug component instance.
	 *
	 * @param string $component Cache bucket.
	 * @param string $slug      Plugin slug.
	 * @param array  $config    Component configuration.
	 * @param string $class     Fully-qualified class name.
	 * @return mixed
	 */
	private static function instance( $component, $slug, array $config, $class ) {
		self::boot();

		if ( ! isset( self::$instances[ $component ][ $slug ] ) ) {
			// Assets must come from the same copy as the code, or a newer
			// template could load an older stylesheet.
			$config['package_root'] = self::active_root();

			self::$instances[ $component ][ $slug ] = new $class( $slug, $config );
		}

		return self::$instances[ $component ][ $slug ];
	}

	/**
	 * The version actually in use.
	 *
	 * @return string|null
	 */
	public static function active_version() {
		if ( empty( self::$copies ) ) {
			return null;
		}

		$versions = array_keys( self::$copies );
		usort( $versions, 'version_compare' );

		return end( $versions );
	}

	/**
	 * Package root of the copy actually in use.
	 *
	 * @return string|null
	 */
	public static function active_root() {
		$version = self::active_version();

		return null === $version ? null : self::$roots[ $version ];
	}
}
