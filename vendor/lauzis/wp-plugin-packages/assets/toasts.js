/**
 * Transient toast messages.
 *
 * window.wpNoticesToast.show( message, type ) — type is one of success,
 * error, warning, info.
 */
( function () {
	'use strict';

	var TYPES = [ 'success', 'error', 'warning', 'info' ];
	var CONTAINER_ID = 'wp-notices-toast-container';

	function timeout() {
		var config = window.wpNoticesToastConfig;

		return config && config.timeout ? parseInt( config.timeout, 10 ) : 5000;
	}

	function getContainer() {
		var container = document.getElementById( CONTAINER_ID );

		if ( ! container ) {
			container = document.createElement( 'div' );
			container.id = CONTAINER_ID;
			container.setAttribute( 'role', 'status' );
			container.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( container );
		}

		return container;
	}

	function remove( toast ) {
		if ( ! toast.parentNode ) {
			return;
		}

		toast.classList.add( 'wp-notices-toast--dismissing' );

		window.setTimeout( function () {
			if ( toast.parentNode ) {
				toast.parentNode.removeChild( toast );
			}
		}, 200 );
	}

	function show( message, type ) {
		type = TYPES.indexOf( type ) !== -1 ? type : 'info';

		var toast = document.createElement( 'div' );
		toast.className = 'wp-notices-toast wp-notices-toast--' + type;

		var text = document.createElement( 'span' );
		text.className = 'wp-notices-toast__message';
		// textContent, not innerHTML: toast messages routinely carry server
		// responses and user-supplied post titles.
		text.textContent = message;
		toast.appendChild( text );

		var dismiss = document.createElement( 'button' );
		dismiss.type = 'button';
		dismiss.className = 'wp-notices-toast__dismiss';
		dismiss.setAttribute( 'aria-label', 'Dismiss' );
		dismiss.innerHTML = '&times;';
		dismiss.addEventListener( 'click', function () {
			remove( toast );
		} );
		toast.appendChild( dismiss );

		getContainer().appendChild( toast );

		window.setTimeout( function () {
			remove( toast );
		}, timeout() );

		return toast;
	}

	window.wpNoticesToast = { show: show };
} )();
