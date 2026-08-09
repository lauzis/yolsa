<?php

namespace Lauzis\WpPackages\Admin;

/**
 * Puts a plugin's version in the admin footer, on its own pages.
 *
 * WordPress already prints its own version there, which is where anyone looks
 * for "what is running". A plugin's version is otherwise only on the Plugins
 * screen, two clicks from the page where the question usually comes up — and
 * the first thing worth knowing about a page misbehaving is which version drew
 * it.
 *
 * Appended rather than substituted: replacing the text would take WordPress's
 * own version away, and knowing both is the point.
 */
class Footer {

	/** @var array<string, array> slug => config, so one plugin registers once. */
	private static $registered = array();

	/**
	 * @param string $slug   Plugin slug; also the prefix its admin pages use.
	 * @param array  $config {
	 *     @type string   $name    Shown before the version. Required to read well.
	 *     @type string   $version Shown as-is.
	 *     @type callable $screen  Overrides "is this one of my pages?".
	 *     @type string[] $types   Post types whose edit screens also count.
	 * }
	 */
	public static function show( $slug, array $config = array() ) {
		if ( isset( self::$registered[ $slug ] ) ) {
			return;
		}

		self::$registered[ $slug ] = $config;

		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}

		// Priority 11: core attaches core_update_footer at 10, so this runs
		// after it and has the version string to append to.
		add_filter(
			'update_footer',
			function ( $text ) use ( $slug ) {
				return self::append( $text, $slug );
			},
			11
		);
	}

	/**
	 * @param string $text Whatever the footer says already.
	 * @param string $slug
	 * @return string
	 */
	private static function append( $text, $slug ) {
		$config = isset( self::$registered[ $slug ] ) ? self::$registered[ $slug ] : array();

		if ( ! self::on_screen( $slug, $config ) ) {
			return $text;
		}

		$name    = isset( $config['name'] ) ? $config['name'] : $slug;
		$version = isset( $config['version'] ) ? $config['version'] : '';

		if ( '' === (string) $version ) {
			return $text;
		}

		$mine = sprintf(
			'<span class="wp-packages-version">%s %s</span>',
			esc_html( $name ),
			esc_html( $version )
		);

		return '' === trim( (string) $text ) ? $mine : $text . ' &nbsp;|&nbsp; ' . $mine;
	}

	/**
	 * Whether the screen belongs to this plugin.
	 *
	 * The page parameter covers menu pages; the post type covers the edit
	 * screens of anything the plugin registers, which have no page parameter
	 * at all and are just as much its own.
	 *
	 * @param string $slug
	 * @param array  $config
	 * @return bool
	 */
	private static function on_screen( $slug, array $config ) {
		if ( isset( $config['screen'] ) && is_callable( $config['screen'] ) ) {
			return (bool) call_user_func( $config['screen'] );
		}

		// Read-only check of a request parameter to decide what to display, so
		// no nonce is warranted.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' !== $page && 0 === strpos( $page, $slug ) ) {
			return true;
		}

		$types = isset( $config['types'] ) ? (array) $config['types'] : array();

		if ( ! $types || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && in_array( $screen->post_type, $types, true );
	}
}
