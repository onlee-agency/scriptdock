/**
 * ScriptDock admin: the one helper the classic editor's meta box needs.
 *
 * window.scriptdock.codeEditor() wraps wp.codeEditor with the plugin's
 * theme and comment shortcut. Everything else on the admin side is React
 * now; this is loaded only where the block editor is not in use.
 */
( function ( $, wp ) {
	'use strict';

	var config = window.scriptdockAdmin || {};

	var TABBABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [contenteditable="true"], [tabindex]:not([tabindex="-1"])';

	/**
	 * Moves focus out of an editor to the next or previous control on the
	 * page. WordPress calls this after Escape then Tab, but its own default
	 * does nothing, which leaves keyboard users stuck in the editor.
	 *
	 * @param {Object}  codemirror CodeMirror instance.
	 * @param {boolean} forward    Whether to go forward.
	 */
	function leave( codemirror, forward ) {
		var wrapper = codemirror.getWrapperElement();
		var candidates = $( TABBABLE ).filter( ':visible' ).get().filter( function ( element ) {
			return element.tabIndex >= 0 && ! wrapper.contains( element );
		} );
		var target = null;
		candidates.forEach( function ( element ) {
			var after = wrapper.compareDocumentPosition( element ) & window.Node.DOCUMENT_POSITION_FOLLOWING;
			if ( forward && after && ! target ) {
				target = element;
			} else if ( ! forward && ! after ) {
				target = element;
			}
		} );
		// Nothing further on: go round, as Tab does at the end of a page.
		if ( ! target && candidates.length ) {
			target = forward ? candidates[ 0 ] : candidates[ candidates.length - 1 ];
		}
		if ( target ) {
			target.focus();
		}
	}

	/**
	 * Turns a textarea into a CodeMirror editor.
	 *
	 * @param {HTMLTextAreaElement} textarea Textarea.
	 * @param {Object|undefined}    settings wp.codeEditor settings for the language.
	 * @return {Object|null} CodeMirror instance, or null when highlighting is off.
	 */
	function codeEditor( textarea, settings ) {
		if ( ! settings || ! wp.codeEditor ) {
			return null;
		}
		var copy = $.extend( true, {}, settings );
		copy.codemirror = copy.codemirror || {};
		if ( 'dark' === config.editorTheme ) {
			copy.codemirror.theme = 'scriptdock-dark';
		}
		copy.codemirror.extraKeys = $.extend( {}, copy.codemirror.extraKeys, {
			'Ctrl-/': 'toggleComment',
			'Cmd-/': 'toggleComment',
		} );
		copy.onTabNext = function ( codemirror ) {
			leave( codemirror, true );
		};
		copy.onTabPrevious = function ( codemirror ) {
			leave( codemirror, false );
		};
		var instance = wp.codeEditor.initialize( textarea, copy );
		return instance && instance.codemirror ? instance.codemirror : null;
	}

	window.scriptdock = window.scriptdock || {};
	window.scriptdock.codeEditor = codeEditor;
}( jQuery, window.wp ) );
