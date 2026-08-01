<?php

namespace Lauzis\WpPackages\Settings;

/**
 * Renders a normalised schema as a Carbon Fields theme-options container.
 *
 * This is the only part of the package that knows about Carbon Fields. The
 * schema itself stays renderer-agnostic, so a second renderer could be added
 * without touching the fragments or the loader.
 */
class CarbonFields {

	/** @var Settings */
	private $settings;

	/** @param Settings $settings Owner, used to resolve @callback: references. */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @param array[] $sections  Normalised sections.
	 * @param string  $title     Page title.
	 * @param string  $mode      'tabs' or 'flat'.
	 * @param array   $container page_parent / page_file.
	 * @return mixed The container, or null when Carbon Fields is unavailable.
	 */
	public function render( array $sections, $title, $mode, array $container = array() ) {
		if ( ! class_exists( '\Carbon_Fields\Container' ) ) {
			return null;
		}

		$page = \Carbon_Fields\Container::make( 'theme_options', $title );

		if ( ! empty( $container['page_parent'] ) ) {
			$page->set_page_parent( $container['page_parent'] );
		}

		if ( ! empty( $container['page_file'] ) ) {
			$page->set_page_file( $container['page_file'] );
		}

		if ( ! empty( $container['page_menu_title'] ) ) {
			$page->set_page_menu_title( $container['page_menu_title'] );
		}

		if ( 'tabs' === $mode ) {
			foreach ( $sections as $section ) {
				$page->add_tab( $this->text( $section['title'], $section['domain'] ), $this->fields( $section ) );
			}

			return $page;
		}

		$flat = array();

		foreach ( $sections as $section ) {
			// Without tabs a section header is a separator, which is how the
			// Carbon Fields plugins already group fields today.
			if ( '' !== $section['title'] ) {
				$flat[] = \Carbon_Fields\Field::make(
					'separator',
					$section['id'] . '_separator',
					$this->text( $section['title'], $section['domain'] )
				);
			}

			$flat = array_merge( $flat, $this->fields( $section ) );
		}

		$page->add_fields( $flat );

		return $page;
	}

	/**
	 * Builds the fields for one section, prepending its description as an
	 * html field when it has one.
	 *
	 * @param array $section
	 * @return array
	 */
	private function fields( array $section ) {
		$fields = array();

		if ( '' !== $section['description'] ) {
			$fields[] = \Carbon_Fields\Field::make( 'html', $section['id'] . '_description' )
				->set_html( '<p>' . wp_kses_post( $this->text( $section['description'], $section['domain'] ) ) . '</p>' );
		}

		foreach ( $section['fields'] as $field ) {
			$built = $this->field( $field );

			if ( $built ) {
				$fields[] = $built;
			}
		}

		return $fields;
	}

	/**
	 * Builds a single Carbon Fields field from a normalised schema field.
	 *
	 * @param array $field
	 * @return mixed|null
	 */
	private function field( array $field ) {
		$domain = $field['domain'];
		$label  = $this->text( $field['title'], $domain );

		$made = 'html' === $field['type']
			? \Carbon_Fields\Field::make( 'html', $field['id'] )
			: \Carbon_Fields\Field::make( $field['type'], $field['id'], $label );

		if ( 'html' === $field['type'] && isset( $field['html'] ) ) {
			if ( Schema::is_callback( $field['html'] ) ) {
				// Pass the callable itself rather than its result: Carbon Fields
				// renders html fields at display time, so anything time-sensitive
				// in the markup (a nonce, current state) stays correct.
				$renderer = $this->settings->callable_for( Schema::callback_name( $field['html'] ) );

				if ( $renderer ) {
					$made->set_html( $renderer );
				}
			} else {
				$made->set_html( wp_kses_post( $this->text( $field['html'], $domain ) ) );
			}
		}

		if ( ! empty( $field['help_text'] ) ) {
			$made->set_help_text(
				$this->format(
					$this->text( $field['help_text'], $domain ),
					isset( $field['help_text_args'] ) ? $field['help_text_args'] : array()
				)
			);
		}

		if ( isset( $field['options'] ) ) {
			$options = $this->settings->resolve( $field['options'] );

			if ( is_array( $options ) ) {
				$made->set_options( $this->translate_options( $options, $domain ) );
			} elseif ( is_callable( $options ) ) {
				$made->set_options( $options );
			}
		}

		if ( isset( $field['default_value'] ) ) {
			$default = $this->settings->resolve( $field['default_value'] );

			if ( null !== $default ) {
				$made->set_default_value( $default );
			}
		}

		if ( ! empty( $field['attributes'] ) ) {
			foreach ( $field['attributes'] as $name => $value ) {
				$made->set_attribute( $name, $value );
			}
		}

		if ( ! empty( $field['required'] ) ) {
			$made->set_required( true );
		}

		if ( ! empty( $field['conditional_logic'] ) ) {
			$made->set_conditional_logic( $field['conditional_logic'] );
		}

		if ( ! empty( $field['fields'] ) ) {
			$children = array();

			foreach ( $field['fields'] as $child ) {
				$built = $this->field( $child );

				if ( $built ) {
					$children[] = $built;
				}
			}

			$made->add_fields( $children );
		}

		return $made;
	}

	/**
	 * Translates select/set option labels, leaving the stored values alone.
	 *
	 * @param array  $options
	 * @param string $domain
	 * @return array
	 */
	private function translate_options( array $options, $domain ) {
		$translated = array();

		foreach ( $options as $value => $label ) {
			$translated[ $value ] = is_string( $label ) ? $this->text( $label, $domain ) : $label;
		}

		return $translated;
	}

	/**
	 * Applies sprintf arguments to an already-translated string.
	 *
	 * Keeps a string with a runtime value in it translatable: the sentence stays
	 * a literal in the schema (so the i18n manifest picks it up) while the value
	 * arrives through a callback.
	 *
	 * @param string $text
	 * @param array  $args Literal values or "@callback:name" references.
	 * @return string
	 */
	private function format( $text, array $args ) {
		if ( empty( $args ) ) {
			return $text;
		}

		$resolved = array();

		foreach ( $args as $arg ) {
			$resolved[] = $this->settings->resolve( $arg );
		}

		return vsprintf( $text, $resolved );
	}

	/**
	 * Translates a schema string against the domain of the fragment it came
	 * from, so package strings resolve against the package's own translations
	 * and plugin strings against the plugin's.
	 *
	 * The variable arguments are intentional: these strings live in JSON, and
	 * `wp i18n make-pot` sees them through the manifest that bin/schema-i18n
	 * generates.
	 *
	 * @param string $text
	 * @param string $domain
	 * @return string
	 */
	private function text( $text, $domain ) {
		if ( '' === $text ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		return __( $text, $domain );
	}
}
