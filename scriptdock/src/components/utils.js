/**
 * Helpers shared by the components.
 */
import { createPortal, useEffect, useRef } from '@wordpress/element';

/**
 * Joins class names, skipping falsy values. Objects add their keys whose
 * values are truthy.
 *
 * @param {...(string|Object|false|null|undefined)} names Class names.
 * @return {string} The class attribute value.
 */
export function cx( ...names ) {
	const out = [];
	for ( const name of names ) {
		if ( ! name ) {
			continue;
		}
		if ( typeof name === 'string' ) {
			out.push( name );
		} else if ( typeof name === 'object' ) {
			for ( const [ key, on ] of Object.entries( name ) ) {
				if ( on ) {
					out.push( key );
				}
			}
		}
	}
	return out.join( ' ' );
}

/**
 * Whether the admin reads right to left.
 *
 * @return {boolean} True for RTL.
 */
export function isRTL() {
	return (
		document.documentElement.dir === 'rtl' ||
		document.body.classList.contains( 'rtl' )
	);
}

/**
 * Whether shortcuts should read ⌘ rather than Ctrl.
 *
 * @return {boolean} True on Apple platforms.
 */
export function isApple() {
	const platform =
		( window.navigator.userAgentData &&
			window.navigator.userAgentData.platform ) ||
		window.navigator.platform ||
		'';
	return /mac|iphone|ipad|ipod/i.test( platform );
}

/**
 * Copies text to the clipboard. Browsers only offer the Clipboard API on
 * secure pages (https, or localhost), so on a site served over plain http,
 * as many local test sites are, the text is copied from a temporary field
 * instead. Focus goes back where it was.
 *
 * @param {string} text Text to copy.
 * @return {Promise} Resolves once copied; rejects if the browser refused.
 */
export function copyText( text ) {
	const fallback = () => {
		const field = document.createElement( 'textarea' );
		const active = field.ownerDocument.activeElement;
		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'fixed';
		field.style.insetBlockStart = '-100vh';
		document.body.appendChild( field );
		field.select();
		const copied = document.execCommand( 'copy' );
		field.remove();
		if ( active && active.focus ) {
			active.focus();
		}
		if ( ! copied ) {
			throw new Error( 'The browser refused to copy.' );
		}
	};
	if ( window.isSecureContext && window.navigator.clipboard ) {
		return window.navigator.clipboard.writeText( text ).catch( fallback );
	}
	return new Promise( ( resolve ) => {
		fallback();
		resolve();
	} );
}

/**
 * Elements a click should not treat as a click on the row or card that
 * contains them.
 */
export const INTERACTIVE =
	'a, button, input, select, textarea, label, summary, [role="switch"], [role="button"], [role="menuitem"], [tabindex]:not([tabindex="-1"])';

/**
 * Calls `handler` when a pointer goes down outside every element in `refs`.
 *
 * @param {Array}    refs    Refs to the elements that count as inside.
 * @param {Function} handler Called with the event.
 * @param {boolean}  active  Whether to listen.
 */
export function useOutsidePointer( refs, handler, active = true ) {
	const saved = useRef( handler );
	saved.current = handler;
	useEffect( () => {
		if ( ! active ) {
			return;
		}
		const onDown = ( event ) => {
			const inside = refs.some(
				( ref ) => ref.current && ref.current.contains( event.target )
			);
			if ( ! inside ) {
				saved.current( event );
			}
		};
		document.addEventListener( 'pointerdown', onDown );
		return () => document.removeEventListener( 'pointerdown', onDown );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ active ] );
}

let portalRoot = null;

/**
 * Renders children at the end of <body>, outside WordPress's layout, for
 * layers: dialogs, drawers, toasts. Each layer carries its own .sd-app.
 *
 * @param {Object}  props          Props.
 * @param {Element} props.children Content.
 * @return {Element} The portal.
 */
export function Portal( { children } ) {
	if ( ! portalRoot || ! document.body.contains( portalRoot ) ) {
		portalRoot = document.createElement( 'div' );
		portalRoot.className = 'sd-portal-root';
		document.body.appendChild( portalRoot );
	}
	return createPortal( children, portalRoot );
}
