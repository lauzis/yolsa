<?php

namespace Lauzis\WpPackages\Notices;

/**
 * Transient floating toast messages.
 *
 * A different mechanism from Notices: toasts are raised by JavaScript at
 * runtime, float above the page, auto-dismiss, and are never persisted. They
 * carry the outcome of an action the user just took; notices carry a standing
 * condition the user needs to know about.
 *
 * The script exposes window.wpNoticesToast.show( message, type ).
 */
class Toasts {

	const HANDLE = 'wp-notices-toasts';

	/** @var Assets */
	private $assets;

	/** @var int */
	private $timeout;

	/**
	 * @param string $slug   Plugin slug. Unused for now, but keeps the registry
	 *                       API symmetrical with notices() and leaves room for
	 *                       per-plugin styling later.
	 * @param array  $config {
	 *     @type int    $timeout      Auto-dismiss delay in ms. Default 5000.
	 *     @type string $assets_url   Explicit URL of the package assets/ directory.
	 *     @type string $package_root Set by the registry.
	 * }
	 */
	public function __construct( $slug, array $config = array() ) {
		$this->timeout = isset( $config['timeout'] ) ? (int) $config['timeout'] : 5000;
		$this->assets  = new Assets(
			isset( $config['package_root'] ) ? $config['package_root'] : dirname( __DIR__ ),
			isset( $config['assets_url'] ) ? $config['assets_url'] : null
		);
	}

	/**
	 * Registers and enqueues the toast assets.
	 *
	 * Other scripts can declare a dependency on the Toasts::HANDLE handle to be
	 * sure window.wpNoticesToast exists before they run.
	 */
	public function enqueue() {
		wp_enqueue_style( self::HANDLE, $this->assets->url( 'toasts.css' ), array(), Assets::VERSION );
		wp_enqueue_script( self::HANDLE, $this->assets->url( 'toasts.js' ), array(), Assets::VERSION, true );

		wp_localize_script(
			self::HANDLE,
			'wpNoticesToastConfig',
			array( 'timeout' => $this->timeout )
		);

		return $this;
	}
}
