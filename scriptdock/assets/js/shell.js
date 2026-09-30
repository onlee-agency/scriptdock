/**
 * ScriptDock top bar.
 *
 * - Collapses the section tabs into "More" when the full row does not fit
 *   (translated labels, the safe-mode pill, narrow screens).
 * - Opens and closes the "More" menu and the phone menu.
 */
( function () {
	'use strict';

	var bar = document.querySelector( '.sd-topbar' );
	if ( ! bar ) {
		return;
	}

	var tabs = bar.querySelector( '.sd-topbar__tabs' );
	var toggles = Array.prototype.slice.call( bar.querySelectorAll( '[data-sd-disclosure]' ) );
	var phone = window.matchMedia( '(max-width: 782px)' );

	/* Menus ------------------------------------------------------------ */

	function panelOf( toggle ) {
		return document.getElementById( toggle.getAttribute( 'aria-controls' ) );
	}

	function isOpen( toggle ) {
		return 'true' === toggle.getAttribute( 'aria-expanded' );
	}

	function setOpen( toggle, open ) {
		var panel = panelOf( toggle );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( panel ) {
			panel.hidden = ! open;
		}
	}

	function closeAll( except ) {
		toggles.forEach( function ( toggle ) {
			if ( toggle !== except && isOpen( toggle ) ) {
				setOpen( toggle, false );
			}
		} );
	}

	function firstItem( toggle ) {
		var panel = panelOf( toggle );
		return panel ? panel.querySelector( 'a, button:not([hidden])' ) : null;
	}

	toggles.forEach( function ( toggle ) {
		toggle.addEventListener( 'click', function () {
			var open = ! isOpen( toggle );
			closeAll( toggle );
			setOpen( toggle, open );
		} );

		// Down arrow opens the menu and moves into it.
		toggle.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowDown' !== event.key ) {
				return;
			}
			event.preventDefault();
			closeAll( toggle );
			setOpen( toggle, true );
			var item = firstItem( toggle );
			if ( item ) {
				item.focus();
			}
		} );

		// Close when focus leaves the toggle and its menu.
		var panel = panelOf( toggle );
		[ toggle, panel ].forEach( function ( element ) {
			if ( ! element ) {
				return;
			}
			element.addEventListener( 'focusout', function ( event ) {
				var next = event.relatedTarget;
				if ( next && ( toggle.contains( next ) || ( panel && panel.contains( next ) ) ) ) {
					return;
				}
				if ( next ) {
					setOpen( toggle, false );
				}
			} );
		} );
	} );

	document.addEventListener( 'click', function ( event ) {
		toggles.forEach( function ( toggle ) {
			var panel = panelOf( toggle );
			if ( isOpen( toggle ) && ! toggle.contains( event.target ) && ! ( panel && panel.contains( event.target ) ) ) {
				setOpen( toggle, false );
			}
		} );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}
		toggles.forEach( function ( toggle ) {
			if ( isOpen( toggle ) ) {
				setOpen( toggle, false );
				toggle.focus();
			}
		} );
	} );

	/* Collapse --------------------------------------------------------- */

	// Lay the bar out in full, and collapse it if the tabs overflow.
	function fit() {
		var was = bar.classList.contains( 'is-collapsed' );
		bar.classList.remove( 'is-collapsed' );
		if ( ! phone.matches && tabs && tabs.scrollWidth > tabs.clientWidth + 1 ) {
			bar.classList.add( 'is-collapsed' );
		}
		if ( was !== bar.classList.contains( 'is-collapsed' ) ) {
			closeAll();
		}
	}

	if ( 'ResizeObserver' in window ) {
		new window.ResizeObserver( fit ).observe( bar );
	} else {
		window.addEventListener( 'resize', fit );
	}
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( fit );
	}
	bar.classList.add( 'is-enhanced' );
	fit();

	// The Search button and the ⌘K shortcut belong to the command palette,
	// a React root of its own in src/palette. The button is printed only
	// when that script is loaded, so it is part of the row from the start
	// and fit() measures it like everything else.
}() );
