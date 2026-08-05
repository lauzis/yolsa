/**
 * Dismissal handling for wp-notices admin notices.
 *
 * Each notice carries its plugin slug in a data attribute; the matching
 * wpNotices<slug> object localised by Notices::enqueue() supplies the AJAX
 * endpoint and nonce. Session-mode notices are removed without a request.
 */
( function () {
	'use strict';

	function config( slug ) {
		return window[ 'wpNotices' + slug ] || null;
	}

	function persistDismissal( notice ) {
		var slug = notice.getAttribute( 'data-wp-notices-slug' );
		var mode = notice.getAttribute( 'data-wp-notices-mode' );
		var id = notice.getAttribute( 'data-wp-notices-id' );
		var settings = config( slug );

		if ( mode === 'session' || ! settings ) {
			return;
		}

		var body = new FormData();
		body.append( 'action', settings.action );
		body.append( 'nonce', settings.nonce );
		body.append( 'notification_id', id );

		window.fetch( settings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.wp-notices-notice .notice-dismiss' );

		if ( ! button ) {
			return;
		}

		var notice = button.closest( '.wp-notices-notice' );

		persistDismissal( notice );
		notice.parentNode.removeChild( notice );
	} );
} )();
