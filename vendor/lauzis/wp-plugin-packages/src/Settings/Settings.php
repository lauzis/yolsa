<?php

namespace Lauzis\WpPackages\Settings;

/**
 * A plugin's settings page, composed from one or more schema fragments.
 *
 * The plugin registers its own schema plus whichever component schemas it
 * wants; they merge into a single page. Obtained through
 * WpPackages_Registry::settings().
 */
class Settings {

	/** @var string */
	private $slug;

	/** @var string */
	private $title;

	/** @var string 'tabs' or 'flat'. */
	private $mode;

	/** @var array Container options passed through to the renderer. */
	private $container;

	/** @var array[] Merged, normalised sections in registration order. */
	private $sections = array();

	/** @var array<string, callable> Named callbacks for @callback: references. */
	private $callbacks = array();

	/** @var array<string, string> Bare id => final option key, for get(). */
	private $keys = array();

	/** @var array<string, mixed> Bare id => schema default, for get(). */
	private $defaults = array();

	/** @var bool */
	private $rendered = false;

	/**
	 * @param string $slug   Plugin slug.
	 * @param array  $config {
	 *     @type string $title       Settings page title.
	 *     @type string $mode        'tabs' (default) or 'flat'.
	 *     @type string $page_parent Parent menu slug, if the page is a submenu.
	 *     @type string $page_file   Menu slug for the page itself.
	 * }
	 */
	public function __construct( $slug, array $config = array() ) {
		$this->slug  = $slug;
		$this->title = isset( $config['title'] ) ? $config['title'] : $slug;
		$this->mode  = ( isset( $config['mode'] ) && 'flat' === $config['mode'] ) ? 'flat' : 'tabs';

		$this->container = array_intersect_key(
			$config,
			array_flip( array( 'page_parent', 'page_file', 'page_menu_title' ) )
		);
	}

	/**
	 * Adds a schema fragment to the page.
	 *
	 * @param string $file Absolute path to a JSON schema.
	 * @param array  $args {
	 *     @type string $prefix Prepended to every id in the fragment.
	 *     @type string $domain Text domain for this fragment's strings.
	 *     @type array  $map    Bare id => replacement id, for legacy keys.
	 * }
	 * @return $this
	 */
	public function register( $file, array $args = array() ) {
		$sections = Schema::load( $file, $args );

		foreach ( $sections as $section ) {
			foreach ( $section['fields'] as $field ) {
				if ( '' === $field['bare'] ) {
					continue;
				}

				$this->keys[ $field['bare'] ] = $field['id'];

				if ( isset( $field['default_value'] ) ) {
					$this->defaults[ $field['bare'] ] = $field['default_value'];
				}
			}
		}

		$this->sections = array_merge( $this->sections, $sections );

		return $this;
	}

	/**
	 * Registers a named callback for "@callback:name" references in a schema.
	 *
	 * Callbacks are looked up by name and never derived from the JSON itself,
	 * so a schema file can never cause arbitrary code to run.
	 *
	 * @param string   $name
	 * @param callable $callback
	 * @return $this
	 */
	public function callback( $name, callable $callback ) {
		$this->callbacks[ $name ] = $callback;

		return $this;
	}

	/**
	 * Resolves a value that may be a "@callback:name" reference.
	 *
	 * @param mixed $value
	 * @return mixed The literal value, or the callback's return value. An
	 *               unregistered callback resolves to null rather than fataling,
	 *               so one missing callback cannot take down the settings page.
	 */
	public function resolve( $value ) {
		if ( ! Schema::is_callback( $value ) ) {
			return $value;
		}

		$name = Schema::callback_name( $value );

		return isset( $this->callbacks[ $name ] ) ? call_user_func( $this->callbacks[ $name ] ) : null;
	}

	/**
	 * Returns a registered callback itself, rather than its result.
	 *
	 * Used where the consumer wants to invoke it later — Carbon Fields renders
	 * html fields lazily, so passing the callable keeps output (and any nonce
	 * in it) generated at display time rather than at registration time.
	 *
	 * @param string $name
	 * @return callable|null
	 */
	public function callable_for( $name ) {
		return isset( $this->callbacks[ $name ] ) ? $this->callbacks[ $name ] : null;
	}

	/**
	 * Builds the settings page. Call on carbon_fields_register_fields.
	 *
	 * @return $this
	 */
	public function render() {
		if ( $this->rendered || empty( $this->sections ) ) {
			return $this;
		}

		$this->rendered = true;

		( new CarbonFields( $this ) )->render( $this->sections, $this->title, $this->mode, $this->container );

		return $this;
	}

	/**
	 * Reads a stored setting by its bare id, so callers never need to know the
	 * prefix or whether a legacy key was mapped.
	 *
	 * Falls back to the schema's own default before the caller's, so a setting
	 * the user has never saved still reads as the value the settings page shows.
	 *
	 * @param string $bare_id Id as written in the schema.
	 * @param mixed  $default Returned when nothing is stored and the schema
	 *                        declares no default.
	 * @return mixed
	 */
	public function get( $bare_id, $default = null ) {
		if ( ! isset( $this->keys[ $bare_id ] ) ) {
			return $default;
		}

		$key = $this->keys[ $bare_id ];

		if ( function_exists( 'carbon_get_theme_option' ) ) {
			// Carbon Fields applies the field's own default, so whatever it
			// returns is authoritative — including an empty string, which is how
			// an unchecked checkbox is stored and must not be mistaken for unset.
			return carbon_get_theme_option( $key );
		}

		// Without Carbon Fields, read the option it would have written. Only a
		// genuinely absent option falls back; a stored empty value is a real
		// answer the user chose.
		$value = get_option( '_' . $key, null );

		if ( null !== $value ) {
			return $value;
		}

		if ( isset( $this->defaults[ $bare_id ] ) ) {
			$fallback = $this->resolve( $this->defaults[ $bare_id ] );

			if ( null !== $fallback ) {
				return $fallback;
			}
		}

		return $default;
	}

	/**
	 * The schema's declared default for a field, resolved.
	 *
	 * Lets a caller apply its own emptiness rule — mawiblah historically treated
	 * any falsy stored value as "use the default", which differs from this
	 * class's absent-only rule.
	 *
	 * @param string $bare_id
	 * @return mixed|null
	 */
	public function default_for( $bare_id ) {
		return isset( $this->defaults[ $bare_id ] ) ? $this->resolve( $this->defaults[ $bare_id ] ) : null;
	}

	/**
	 * The final option key for a bare id, or null if the id is unknown.
	 *
	 * @param string $bare_id
	 * @return string|null
	 */
	public function key( $bare_id ) {
		return isset( $this->keys[ $bare_id ] ) ? $this->keys[ $bare_id ] : null;
	}

	/** @return array[] The merged sections, mainly for tests and tooling. */
	public function sections() {
		return $this->sections;
	}

	/** @return string */
	public function slug() {
		return $this->slug;
	}
}
