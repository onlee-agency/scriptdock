/**
 * ScriptDock delayed script loader.
 *
 * Runs <script type="text/plain"> tags that ScriptDock marked with
 * data-scriptdock-delay and/or data-scriptdock-consent once their condition
 * is met, in document order:
 *
 * - dom:         after the document has been parsed
 * - idle:        after the page has loaded and the browser is idle
 * - interaction: on the first scroll, click, key press, touch or mouse move
 * - consent:     when the WP Consent API reports consent for the category
 *                (when no consent plugin is installed, scripts run normally;
 *                when one is, they wait for its script however late it loads)
 */
( function () {
	'use strict';

	var SELECTOR = 'script[type="text/plain"][data-scriptdock-delay],script[type="text/plain"][data-scriptdock-consent]';
	var INTERACTION_EVENTS = [ 'keydown', 'mousedown', 'mousemove', 'touchstart', 'scroll', 'wheel' ];
	var state = { dom: false, idle: false, interaction: false };
	var queue = [];
	var running = false;
	var consentTimer = null;
	var consentChecks = 0;

	function hasConsent( category ) {
		if ( ! category ) {
			return true;
		}
		if ( typeof window.wp_has_consent !== 'function' ) {
			// No consent plugin: run. One whose script has not arrived yet
			// (an optimiser may hold it back): wait, never assume consent.
			return ! window.scriptdockConsentApi;
		}
		return !! window.wp_has_consent( category );
	}

	function isReady( element ) {
		var delay = element.getAttribute( 'data-scriptdock-delay' );
		if ( delay && ! state[ delay ] ) {
			return false;
		}
		return hasConsent( element.getAttribute( 'data-scriptdock-consent' ) );
	}

	function scan() {
		var elements = document.querySelectorAll( SELECTOR );
		var awaitingApi = false;
		for ( var i = 0; i < elements.length; i++ ) {
			var element = elements[ i ];
			if ( element.hasAttribute( 'data-scriptdock-queued' ) ) {
				continue;
			}
			if ( isReady( element ) ) {
				element.setAttribute( 'data-scriptdock-queued', '' );
				queue.push( element );
			} else if ( element.hasAttribute( 'data-scriptdock-consent' ) && typeof window.wp_has_consent !== 'function' ) {
				awaitingApi = true;
			}
		}
		if ( awaitingApi ) {
			awaitConsentApi();
		}
		runNext();
	}

	// The consent plugin's script has not arrived. Look again twice a second
	// for half a minute, for a returning visitor whose consent it will not
	// announce with an event; after that its own events take over.
	function awaitConsentApi() {
		if ( consentTimer || consentChecks >= 60 ) {
			return;
		}
		consentChecks++;
		consentTimer = window.setTimeout( function () {
			consentTimer = null;
			scan();
		}, 500 );
	}

	function runNext() {
		if ( running ) {
			return;
		}
		var placeholder = queue.shift();
		if ( ! placeholder ) {
			return;
		}
		running = true;

		var script = document.createElement( 'script' );
		for ( var i = 0; i < placeholder.attributes.length; i++ ) {
			var attribute = placeholder.attributes[ i ];
			if ( 'type' === attribute.name || 0 === attribute.name.indexOf( 'data-scriptdock-' ) ) {
				continue;
			}
			script.setAttribute( attribute.name, attribute.value );
		}

		var originalType = placeholder.getAttribute( 'data-scriptdock-type' );
		if ( originalType ) {
			script.setAttribute( 'type', originalType );
		}

		var finish = function () {
			running = false;
			runNext();
		};

		if ( placeholder.hasAttribute( 'src' ) ) {
			// Wait for external scripts so later scripts can rely on them.
			script.onload = finish;
			script.onerror = finish;
			placeholder.parentNode.replaceChild( script, placeholder );
		} else {
			script.text = placeholder.text;
			placeholder.parentNode.replaceChild( script, placeholder );
			finish();
		}
	}

	function onInteraction() {
		if ( state.interaction ) {
			return;
		}
		state.interaction = true;
		for ( var i = 0; i < INTERACTION_EVENTS.length; i++ ) {
			window.removeEventListener( INTERACTION_EVENTS[ i ], onInteraction, { passive: true } );
		}
		scan();
	}

	function onDomReady() {
		state.dom = true;
		scan();
	}

	function onLoad() {
		var markIdle = function () {
			state.idle = true;
			scan();
		};
		if ( 'requestIdleCallback' in window ) {
			window.requestIdleCallback( markIdle, { timeout: 3000 } );
		} else {
			window.setTimeout( markIdle, 200 );
		}
	}

	for ( var i = 0; i < INTERACTION_EVENTS.length; i++ ) {
		window.addEventListener( INTERACTION_EVENTS[ i ], onInteraction, { passive: true } );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', onDomReady );
	} else {
		onDomReady();
	}

	if ( 'complete' === document.readyState ) {
		onLoad();
	} else {
		window.addEventListener( 'load', onLoad );
	}

	// Fired by the WP Consent API whenever a visitor changes their choices,
	// and once its consent type is known.
	document.addEventListener( 'wp_listen_for_consent_change', scan );
	document.addEventListener( 'wp_consent_type_defined', scan );
}() );
