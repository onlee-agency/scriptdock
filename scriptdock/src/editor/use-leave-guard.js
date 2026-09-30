/**
 * Keeps unsaved changes from being lost. Links on the page (the breadcrumb,
 * WordPress's menu, the top bar) ask first in the app's own dialog; closing
 * the tab or going back falls to the browser's prompt.
 */
import { useEffect, useRef } from '@wordpress/element';

/**
 * Whether a click on a link would leave the page in this tab.
 *
 * @param {MouseEvent} event Click.
 * @return {HTMLAnchorElement|null} The link, when it leaves.
 */
function leavingLink( event ) {
	if (
		event.defaultPrevented ||
		event.button !== 0 ||
		event.metaKey ||
		event.ctrlKey ||
		event.shiftKey ||
		event.altKey
	) {
		return null;
	}
	const link = event.target.closest && event.target.closest( 'a[href]' );
	if ( ! link || link.hasAttribute( 'download' ) ) {
		return null;
	}
	if ( link.target && link.target !== '_self' ) {
		return null;
	}
	const href = link.getAttribute( 'href' ) || '';
	if (
		! href ||
		href.startsWith( '#' ) ||
		/^(javascript|mailto|tel):/i.test( href )
	) {
		return null;
	}
	return link;
}

/**
 * @param {boolean}  dirty     Whether there are unsaved changes.
 * @param {Function} onAttempt Called with a URL someone tried to open.
 * @return {Object} leave( url ) goes without asking; allow() stops asking
 *                  (after a save that navigates).
 */
export default function useLeaveGuard( dirty, onAttempt ) {
	const state = useRef( { dirty, leaving: false, onAttempt } );
	state.current.dirty = dirty;
	state.current.onAttempt = onAttempt;

	useEffect( () => {
		const beforeUnload = ( event ) => {
			if ( state.current.dirty && ! state.current.leaving ) {
				event.preventDefault();
				// Older browsers need a return value to ask.
				event.returnValue = '';
			}
		};
		const click = ( event ) => {
			if ( ! state.current.dirty || state.current.leaving ) {
				return;
			}
			const link = leavingLink( event );
			if ( link ) {
				event.preventDefault();
				state.current.onAttempt( link.href );
			}
		};
		window.addEventListener( 'beforeunload', beforeUnload );
		// Capture, so the question comes before anything the link does.
		document.addEventListener( 'click', click, true );
		return () => {
			window.removeEventListener( 'beforeunload', beforeUnload );
			document.removeEventListener( 'click', click, true );
		};
	}, [] );

	return {
		leave( url ) {
			state.current.leaving = true;
			window.location.assign( url );
		},
		allow() {
			state.current.leaving = true;
		},
	};
}
