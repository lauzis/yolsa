<?php

namespace Lauzis\WpPackages\Notices;

/**
 * A single admin notice.
 *
 * Plain properties rather than promoted constructor arguments, so the package
 * still runs on PHP 7.4.
 */
class Notice {

	/** Dismissal is remembered forever. */
	const ONCE = 'once';

	/** Dismissal is remembered until the plugin version changes. */
	const VERSION = 'version';

	/** Dismissible, but reappears on the next page load. */
	const SESSION = 'session';

	/** @var string Stable identifier; the dismissal is recorded against it. */
	public $id;

	/** @var string Message HTML, filtered through wp_kses_post() on output. */
	public $message;

	/** @var string One of info, success, warning, error. */
	public $type;

	/** @var string One of the mode constants above. */
	public $mode;

	/**
	 * @param string $id
	 * @param string $message
	 * @param string $type
	 * @param string $mode
	 */
	public function __construct( $id, $message, $type = 'info', $mode = self::ONCE ) {
		$this->id      = $id;
		$this->message = $message;
		$this->type    = in_array( $type, array( 'info', 'success', 'warning', 'error' ), true ) ? $type : 'info';
		$this->mode    = in_array( $mode, array( self::ONCE, self::VERSION, self::SESSION ), true ) ? $mode : self::ONCE;
	}
}
