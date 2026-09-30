/**
 * Mounts the command palette on every ScriptDock screen.
 *
 * It listens for the shortcut and for the top bar's Search button, and draws
 * nothing at all until one of them fires — so the cost on a page nobody opens
 * it on is a listener.
 */
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Layer, isApple } from '../components';
import App, { shortcutLabel } from './app';

/**
 * Where the palette is opened from: the shortcut, and the buttons in the top
 * bar. Both go through the same state.
 *
 * @param {Object} props      Props.
 * @param {Object} props.boot Bootstrap data.
 * @return {Element|null} The palette, when it is open.
 */
function Palette( { boot } ) {
	const [ open, setOpen ] = useState( false );

	useEffect( () => {
		const onKeyDown = ( event ) => {
			const combo = isApple() ? event.metaKey : event.ctrlKey;
			if ( ! combo || event.altKey || event.key.toLowerCase() !== 'k' ) {
				return;
			}
			// WordPress 6.9 has a palette of its own on the same shortcut.
			// On ScriptDock's screens ours answers, and this stops both
			// opening at once.
			event.preventDefault();
			event.stopPropagation();
			setOpen( ( was ) => ! was );
		};
		// Capture, so the shortcut still works from inside the code editor.
		document.addEventListener( 'keydown', onKeyDown, true );
		return () => document.removeEventListener( 'keydown', onKeyDown, true );
	}, [] );

	useEffect( () => {
		const buttons = document.querySelectorAll(
			'[data-sd-command-palette]'
		);
		const show = () => setOpen( true );
		buttons.forEach( ( button ) => {
			button.addEventListener( 'click', show );
			// The markup ships the Mac label; swap it everywhere else.
			const key = button.querySelector( '.sd-kbd' );
			if ( key ) {
				key.textContent = shortcutLabel();
			}
			button.setAttribute(
				'aria-keyshortcuts',
				isApple() ? 'Meta+K' : 'Control+K'
			);
		} );
		return () =>
			buttons.forEach( ( button ) =>
				button.removeEventListener( 'click', show )
			);
	}, [] );

	if ( ! open ) {
		return null;
	}

	return (
		<Layer
			className="sd-palette-layer"
			focus="firstInputElement"
			onClose={ () => setOpen( false ) }
			labelledBy="sd-palette-title"
		>
			<h2 id="sd-palette-title" className="sd-visually-hidden">
				{ __( 'Search snippets and commands', 'scriptdock' ) }
			</h2>
			<App boot={ boot } onClose={ () => setOpen( false ) } />
		</Layer>
	);
}

if ( window.sdPalette ) {
	const root = document.createElement( 'div' );
	root.className = 'sd-palette-root';
	document.body.appendChild( root );
	// No error boundary here: this root is appended to the body, so a
	// failed state would be a stray card at the foot of the page. If the
	// palette cannot draw, the screen simply carries on without it.
	createRoot( root ).render( <Palette boot={ window.sdPalette } /> );
}
