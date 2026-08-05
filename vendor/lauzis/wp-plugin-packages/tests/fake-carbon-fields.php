<?php
/**
 * Minimal Carbon Fields test double.
 *
 * Records what the renderer builds so the adapter can be asserted on without a
 * WordPress install or the real library. Only the surface CarbonFields.php
 * actually calls is implemented — if the renderer starts using something new,
 * these tests will fail loudly rather than silently passing.
 */

namespace Carbon_Fields {

	class Container {

		public $type;
		public $title;
		public $page_parent = null;
		public $page_file   = null;
		public $page_menu_title = null;
		/** @var array[] list of ['title' => string, 'fields' => Field[]] */
		public $tabs = array();
		/** @var Field[] */
		public $fields = array();

		public static $last;

		public static function make( $type, $title ) {
			$c        = new self();
			$c->type  = $type;
			$c->title = $title;
			self::$last = $c;

			return $c;
		}

		public function set_page_parent( $p ) { $this->page_parent = $p; return $this; }
		public function set_page_file( $p )   { $this->page_file = $p;   return $this; }
		public function set_page_menu_title( $t ) { $this->page_menu_title = $t; return $this; }

		public function add_tab( $title, $fields ) {
			$this->tabs[] = array( 'title' => $title, 'fields' => $fields );
			return $this;
		}

		public function add_fields( $fields ) {
			$this->fields = array_merge( $this->fields, $fields );
			return $this;
		}

		/** Flattens tabs and top-level fields into one list, for assertions. */
		public function all_fields() {
			$all = $this->fields;

			foreach ( $this->tabs as $tab ) {
				$all = array_merge( $all, $tab['fields'] );
			}

			return $all;
		}

		/** Finds a field by id anywhere in the container, including nested. */
		public function find( $id ) {
			return self::search( $this->all_fields(), $id );
		}

		private static function search( array $fields, $id ) {
			foreach ( $fields as $f ) {
				if ( $f->id === $id ) {
					return $f;
				}

				$hit = self::search( $f->children, $id );

				if ( $hit ) {
					return $hit;
				}
			}

			return null;
		}
	}

	class Field {

		public $type;
		public $id;
		public $label;
		public $html              = null;
		public $help_text         = null;
		public $options           = null;
		public $default_value     = null;
		public $attributes        = array();
		public $conditional_logic = null;
		public $required          = false;
		/** @var Field[] */
		public $children = array();

		/** @var array|null Field-type-specific config, e.g. wp_editor settings. */
		public $settings = null;

		public static function make( $type, $id, $label = null ) {
			$f        = new self();
			$f->type  = $type;
			$f->id    = $id;
			$f->label = $label;

			return $f;
		}

		public function set_html( $h )              { $this->html = $h;              return $this; }
		public function set_help_text( $t )         { $this->help_text = $t;         return $this; }
		public function set_options( $o )           { $this->options = $o;           return $this; }
		public function set_default_value( $v )     { $this->default_value = $v;     return $this; }
		public function set_conditional_logic( $c ) { $this->conditional_logic = $c; return $this; }
		public function set_required( $r )          { $this->required = $r;          return $this; }

		public function set_settings( $s ) {
			$this->settings = $s;
			return $this;
		}

		public function set_attribute( $n, $v ) {
			$this->attributes[ $n ] = $v;
			return $this;
		}

		public function add_fields( $fields ) {
			$this->children = array_merge( $this->children, $fields );
			return $this;
		}
	}
}

namespace Carbon_Fields\Field {
	// The plugins reference both \Carbon_Fields\Field and \Carbon_Fields\Field\Field.
	class Field extends \Carbon_Fields\Field {}
}
