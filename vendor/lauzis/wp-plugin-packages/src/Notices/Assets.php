<?php

namespace Lauzis\WpPackages\Notices;

/**
 * Resolves the URL of the package's bundled CSS/JS.
 *
 * The package lives inside the consuming plugin's vendor/ directory, so its
 * assets are web-reachable but their URL cannot be derived with
 * plugins_url( $path, __FILE__ ) alone in every layout. Mapping the plugin
 * directory path onto the plugin directory URL works wherever the plugin is
 * installed, including non-standard WP_PLUGIN_DIR locations.
 */
class Assets {

	const VERSION = '1.11.0';

	/** @var string */
	private $root;

	/** @var string|null */
	private $base_url;

	/**
	 * @param string      $root     Absolute path to the package root.
	 * @param string|null $base_url Explicit URL of the package's assets/
	 *                              directory, for layouts where the automatic
	 *                              mapping does not apply (a symlinked vendor/,
	 *                              or the package installed outside a plugin).
	 */
	public function __construct( $root, $base_url = null ) {
		$this->root     = rtrim( str_replace( '\\', '/', (string) $root ), '/' );
		$this->base_url = $base_url ? rtrim( $base_url, '/' ) : null;
	}

	/**
	 * @param string $file Filename within assets/.
	 * @return string Absolute URL.
	 */
	public function url( $file ) {
		if ( null !== $this->base_url ) {
			return $this->base_url . '/' . ltrim( $file, '/' );
		}

		$path       = $this->root . '/assets/' . ltrim( $file, '/' );
		$plugin_dir = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' );

		if ( 0 === strpos( $path, $plugin_dir ) ) {
			return rtrim( plugins_url(), '/' ) . substr( $path, strlen( $plugin_dir ) );
		}

		// Fall back to content-dir mapping (mu-plugins, or a custom layout).
		$content_dir = rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' );

		if ( 0 === strpos( $path, $content_dir ) ) {
			return rtrim( content_url(), '/' ) . substr( $path, strlen( $content_dir ) );
		}

		return '';
	}
}
