<?php

namespace Lauzis\WpPackages\Settings;

/**
 * Loads a settings schema fragment and normalises it.
 *
 * A fragment is a JSON file declaring sections and fields. Fragments come from
 * two places — the plugin's own config, and components inside this package —
 * and are merged into one settings page. Because several plugins may be active
 * on one site, every option key is namespaced with the consuming plugin's
 * prefix at load time; the JSON itself declares bare ids.
 */
class Schema {

	/** Escape hatch marker for values that cannot be JSON literals. */
	const CALLBACK_PREFIX = '@callback:';

	/**
	 * Reads a fragment and returns its normalised sections.
	 *
	 * @param string $file Absolute path to the JSON file.
	 * @param array  $args {
	 *     @type string $prefix Prepended to every field and section id.
	 *     @type string $domain Text domain for this fragment's strings.
	 *     @type array  $map    Bare id => replacement id, applied before the
	 *                          prefix, for fields that must keep a legacy key.
	 *     @type array  $conditions Bare id => conditional_logic rules, applied to
	 *                          a fragment's field. Lets a plugin hide a component
	 *                          field in contexts the component knows nothing about.
	 *     @type array  $defaults Bare id => default value, overriding the
	 *                          schema's own default. Lets a component ship a
	 *                          neutral default while a plugin keeps the one its
	 *                          users already have.
	 * }
	 * @return array[] Normalised sections.
	 * @throws \RuntimeException When the file is missing or not valid JSON.
	 */
	public static function load( $file, array $args = array() ) {
		if ( ! is_readable( $file ) ) {
			throw new \RuntimeException( 'Settings schema not readable: ' . $file );
		}

		$raw = json_decode( file_get_contents( $file ), true );

		if ( null === $raw ) {
			throw new \RuntimeException( 'Settings schema is not valid JSON: ' . $file );
		}

		return self::normalize( $raw, $args );
	}

	/**
	 * Normalises a decoded fragment.
	 *
	 * Accepts "sections", "tabs" (an alias — which of the two renders as tabs is
	 * a container-level choice, not a fragment-level one) or a bare "fields"
	 * list, and always returns a list of sections.
	 *
	 * @param array $raw  Decoded fragment.
	 * @param array $args See load().
	 * @return array[]
	 */
	public static function normalize( array $raw, array $args = array() ) {
		$prefix = isset( $args['prefix'] ) ? $args['prefix'] : '';
		$domain = isset( $args['domain'] ) ? $args['domain'] : 'default';
		$map      = isset( $args['map'] ) ? $args['map'] : array();
		$defaults   = isset( $args['defaults'] ) ? $args['defaults'] : array();
		$conditions = isset( $args['conditions'] ) ? $args['conditions'] : array();

		if ( isset( $raw['sections'] ) ) {
			$sections = $raw['sections'];
		} elseif ( isset( $raw['tabs'] ) ) {
			$sections = $raw['tabs'];
		} elseif ( isset( $raw['fields'] ) ) {
			$sections = array( array( 'id' => 'default', 'title' => '', 'fields' => $raw['fields'] ) );
		} else {
			$sections = array();
		}

		$result = array();

		foreach ( $sections as $section ) {
			$fields = array();

			foreach ( isset( $section['fields'] ) ? $section['fields'] : array() as $field ) {
				$fields[] = self::normalize_field( $field, $prefix, $map, $domain, $defaults, $conditions );
			}

			$result[] = array(
				'id'          => $prefix . ( isset( $section['id'] ) ? $section['id'] : 'default' ),
				'title'       => isset( $section['title'] ) ? $section['title'] : '',
				'description' => isset( $section['description'] ) ? $section['description'] : '',
				'domain'      => $domain,
				'fields'      => $fields,
			);
		}

		return $result;
	}

	/**
	 * Normalises one field, recursing into complex sub-fields.
	 *
	 * Sub-fields of a complex field are NOT prefixed: Carbon Fields stores them
	 * inside the parent field's value rather than as options of their own, so
	 * they cannot collide across plugins and prefixing them would corrupt the
	 * stored shape.
	 *
	 * @param array  $field
	 * @param string $prefix
	 * @param array  $map
	 * @param string $domain
	 * @return array
	 */
	private static function normalize_field( array $field, $prefix, array $map, $domain, array $defaults = array(), array $conditions = array() ) {
		$bare = isset( $field['id'] ) ? $field['id'] : '';

		$normalized = array(
			'id'     => $prefix . self::resolve_id( $bare, $map ),
			'bare'   => $bare,
			'type'   => isset( $field['type'] ) ? $field['type'] : 'text',
			'title'  => isset( $field['title'] ) ? $field['title'] : '',
			'domain' => $domain,
		);

		foreach ( array( 'help_text', 'help_text_args', 'html', 'default_value', 'options', 'attributes', 'width', 'required' ) as $key ) {
			if ( isset( $field[ $key ] ) ) {
				$normalized[ $key ] = $field[ $key ];
			}
		}

		if ( array_key_exists( $bare, $defaults ) ) {
			$normalized['default_value'] = $defaults[ $bare ];
		}

		// A condition names another field by its bare id, so it has to go
		// through the same mapping and prefixing or it will point at an id that
		// no longer exists.
		$logic = isset( $conditions[ $bare ] ) ? $conditions[ $bare ] : ( isset( $field['conditional_logic'] ) ? $field['conditional_logic'] : array() );

		if ( ! empty( $logic ) ) {
			$rules = array();

			foreach ( $logic as $rule ) {
				if ( isset( $rule['field'] ) ) {
					$rule['field'] = $prefix . self::resolve_id( $rule['field'], $map );
				}

				if ( ! isset( $rule['compare'] ) ) {
					$rule['compare'] = '=';
				}

				$rules[] = $rule;
			}

			$normalized['conditional_logic'] = $rules;
		}

		if ( ! empty( $field['fields'] ) ) {
			$children = array();

			foreach ( $field['fields'] as $child ) {
				$children[] = self::normalize_field( $child, '', array(), $domain, array(), array() );
			}

			$normalized['fields'] = $children;
		}

		return $normalized;
	}

	/**
	 * Applies the legacy-key map to a bare id.
	 *
	 * @param string $id
	 * @param array  $map
	 * @return string
	 */
	private static function resolve_id( $id, array $map ) {
		return isset( $map[ $id ] ) ? $map[ $id ] : $id;
	}

	/**
	 * True when a value is a callback reference rather than a literal.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function is_callback( $value ) {
		return is_string( $value ) && 0 === strpos( $value, self::CALLBACK_PREFIX );
	}

	/**
	 * Returns the callback name from a reference.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function callback_name( $value ) {
		return substr( $value, strlen( self::CALLBACK_PREFIX ) );
	}

	/**
	 * Walks every translatable string in a set of sections.
	 *
	 * Used both by the renderer and by bin/schema-i18n, so the manifest that
	 * feeds `wp i18n make-pot` can never drift from what is actually rendered.
	 *
	 * @param array[]  $sections
	 * @param callable $visitor function ( string $text, string $domain ): void
	 */
	public static function walk_strings( array $sections, callable $visitor ) {
		foreach ( $sections as $section ) {
			foreach ( array( 'title', 'description' ) as $key ) {
				if ( ! empty( $section[ $key ] ) ) {
					$visitor( $section[ $key ], $section['domain'] );
				}
			}

			self::walk_field_strings( $section['fields'], $visitor );
		}
	}

	/**
	 * @param array[]  $fields
	 * @param callable $visitor
	 */
	private static function walk_field_strings( array $fields, callable $visitor ) {
		foreach ( $fields as $field ) {
			foreach ( array( 'title', 'help_text', 'html' ) as $key ) {
				if ( ! empty( $field[ $key ] ) ) {
					$visitor( $field[ $key ], $field['domain'] );
				}
			}

			// default_value is deliberately not visited: it is a stored value,
			// not a label, and translating it would change what gets saved.

			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $label ) {
					if ( is_string( $label ) && '' !== $label ) {
						$visitor( $label, $field['domain'] );
					}
				}
			}

			if ( ! empty( $field['fields'] ) ) {
				self::walk_field_strings( $field['fields'], $visitor );
			}
		}
	}
}
