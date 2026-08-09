<?php

namespace Lauzis\WpPackages\Notices;

/**
 * Dismissible admin notices for a single plugin.
 *
 * Covers both dismissal strategies the plugins were using independently:
 * splecheh remembered dismissals site-wide in an option, mawiblah remembered
 * them per user and only until the plugin version changed.
 */
class Notices {

	/**
	 * Handle for this package's own notice assets.
	 *
	 * Namespaced because a bare, plausible handle is exactly the kind that
	 * WordPress or another plugin has already taken.
	 */
	const HANDLE = 'wp-packages-notices';

	/** @var string */
	private $slug;

	/** @var string Sanitised slug, safe in option keys and hook names. */
	private $key;

	/** @var Notice[] */
	private $notices = array();

	/** @var string 'option' (site-wide) or 'user' (per user). */
	private $store;

	/** @var string Version recorded for Notice::VERSION dismissals. */
	private $version;

	/** @var callable|null Returns true when notices should render. */
	private $screen;

	/** @var string Capability required to dismiss. */
	private $capability;

	/** @var Assets */
	private $assets;

	/** @var bool */
	private $booted = false;

	/**
	 * @param string $slug   Plugin slug.
	 * @param array  $config {
	 *     @type string        $store        'option' or 'user'. Default 'option'.
	 *     @type string        $version      Plugin version, for VERSION-mode
	 *                                       notices. Default ''.
	 *     @type callable|null $screen       Predicate deciding whether to render
	 *                                       on the current screen. Default: render
	 *                                       on any admin page whose 'page' request
	 *                                       parameter starts with the slug.
	 *     @type string        $capability   Capability required to dismiss.
	 *                                       Default 'edit_posts'.
	 *     @type string        $assets_url   Explicit URL of the package assets/
	 *                                       directory. Usually unnecessary.
	 *     @type string        $package_root Set by the registry.
	 * }
	 */
	public function __construct( $slug, array $config = array() ) {
		$this->slug       = $slug;
		$this->key        = preg_replace( '/[^a-z0-9_]/', '', strtolower( str_replace( '-', '_', $slug ) ) );
		$this->store      = ( isset( $config['store'] ) && 'user' === $config['store'] ) ? 'user' : 'option';
		$this->version    = isset( $config['version'] ) ? (string) $config['version'] : '';
		$this->screen     = isset( $config['screen'] ) && is_callable( $config['screen'] ) ? $config['screen'] : null;
		$this->capability = isset( $config['capability'] ) ? $config['capability'] : 'edit_posts';
		$this->assets     = new Assets(
			isset( $config['package_root'] ) ? $config['package_root'] : dirname( __DIR__ ),
			isset( $config['assets_url'] ) ? $config['assets_url'] : null
		);
	}

	/**
	 * Registers the WordPress hooks. Idempotent; safe to call from a facade
	 * that may be constructed more than once.
	 */
	public function boot() {
		if ( $this->booted ) {
			return $this;
		}

		$this->booted = true;

		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . $this->action(), array( $this, 'handle_dismiss' ) );

		return $this;
	}

	/**
	 * Queues a notice for this request.
	 *
	 * @param Notice $notice
	 */
	public function add( Notice $notice ) {
		$this->notices[] = $notice;

		return $this;
	}

	/** The AJAX action name used to persist a dismissal. */
	public function action() {
		return $this->key . '_dismiss_notice';
	}

	/** Renders every notice that has not been dismissed. */
	public function render() {
		if ( ! $this->on_screen() ) {
			return;
		}

		$dismissed = $this->dismissed();

		foreach ( $this->notices as $notice ) {
			if ( $this->is_dismissed( $notice, $dismissed ) ) {
				continue;
			}

			printf(
				'<div class="notice notice-%1$s wp-notices-notice" data-wp-notices-id="%2$s" data-wp-notices-mode="%3$s" data-wp-notices-slug="%4$s">',
				esc_attr( $notice->type ),
				esc_attr( $notice->id ),
				esc_attr( $notice->mode ),
				esc_attr( $this->key )
			);

			echo '<p>' . wp_kses_post( $notice->message ) . '</p>';

			printf(
				'<button type="button" class="notice-dismiss"><span class="screen-reader-text">%s</span></button>',
				esc_html__( 'Dismiss this notice.' ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress itself.
			);

			echo '</div>';
		}
	}

	/** Enqueues the shared CSS/JS, only on screens that show notices. */
	public function enqueue() {
		if ( ! $this->on_screen() ) {
			return;
		}

		// Not "wp-notices": WordPress registers that handle itself, for
		// @wordpress/notices. wp_enqueue_script() will not re-register an
		// existing handle, so core's script loaded instead of this one and
		// nothing was listening when a notice's dismiss button was pressed.
		wp_enqueue_style( self::HANDLE, $this->assets->url( 'notices.css' ), array(), Assets::VERSION );
		wp_enqueue_script( self::HANDLE, $this->assets->url( 'notices.js' ), array(), Assets::VERSION, true );

		wp_localize_script(
			self::HANDLE,
			'wpNotices' . $this->key,
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => $this->action(),
				'nonce'   => wp_create_nonce( $this->action() ),
			)
		);
	}

	/** Persists a dismissal. */
	public function handle_dismiss() {
		check_ajax_referer( $this->action(), 'nonce' );

		if ( ! current_user_can( $this->capability ) ) {
			wp_send_json_error( 'Insufficient permissions', 403 );
		}

		$id = isset( $_POST['notification_id'] ) ? sanitize_key( wp_unslash( $_POST['notification_id'] ) ) : '';

		if ( '' === $id ) {
			wp_send_json_error( 'Invalid notification ID', 400 );
		}

		$dismissed        = $this->dismissed();
		$dismissed[ $id ] = '' !== $this->version ? $this->version : true;

		$this->save( $dismissed );

		wp_send_json_success();
	}

	/** Forgets every dismissal, so the notices show again. */
	public function reset() {
		$this->save( array() );

		return $this;
	}

	/**
	 * @param Notice $notice
	 * @param array  $dismissed
	 * @return bool
	 */
	private function is_dismissed( Notice $notice, array $dismissed ) {
		if ( Notice::SESSION === $notice->mode || ! isset( $dismissed[ $notice->id ] ) ) {
			return false;
		}

		if ( Notice::VERSION === $notice->mode ) {
			// Dismissed for an older version — show it again.
			return (string) $dismissed[ $notice->id ] === $this->version;
		}

		return true;
	}

	/** @return array<string, mixed> id => true, or id => version string. */
	private function dismissed() {
		$stored = 'user' === $this->store
			? get_user_meta( get_current_user_id(), $this->option_key(), true )
			: get_option( $this->option_key(), array() );

		return is_array( $stored ) ? $stored : array();
	}

	/** @param array<string, mixed> $dismissed */
	private function save( array $dismissed ) {
		if ( 'user' === $this->store ) {
			update_user_meta( get_current_user_id(), $this->option_key(), $dismissed );

			return;
		}

		update_option( $this->option_key(), $dismissed, false );
	}

	private function option_key() {
		return $this->key . '_dismissed_notices';
	}

	/** Whether notices should render on the current screen. */
	private function on_screen() {
		if ( null !== $this->screen ) {
			return (bool) call_user_func( $this->screen );
		}

		// Default: any admin page belonging to this plugin. Read-only check of
		// a request parameter, so no nonce is warranted.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return '' !== $page && 0 === strpos( $page, $this->slug );
	}
}
