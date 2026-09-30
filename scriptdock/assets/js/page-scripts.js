/**
 * ScriptDock page scripts box: tabs and code editors.
 *
 * Editors are created when their tab is first opened, and every change is
 * copied back to the textarea right away, because the block editor reads
 * meta box fields directly instead of submitting the form.
 */
( function ( $ ) {
	'use strict';

	var settings = ( window.scriptdockPageScripts && window.scriptdockPageScripts.codeEditors ) || {};
	var editors = {};

	function initEditor( $panel ) {
		var key = $panel.data( 'panel' );
		var $textarea = $panel.find( 'textarea.scriptdock-page-code' );
		if ( ! $textarea.length ) {
			return;
		}
		if ( editors[ key ] ) {
			editors[ key ].refresh();
			return;
		}
		var cm = window.scriptdock.codeEditor( $textarea[ 0 ], settings[ $textarea.data( 'language' ) ] );
		if ( ! cm ) {
			return;
		}
		cm.setSize( null, 240 );
		cm.on( 'change', function () {
			cm.save();
		} );
		// Named after its tab, with how to leave it: Tab types a tab in here.
		$( cm.getInputField() ).attr( {
			'aria-labelledby': 'scriptdock-tab-' + key,
			'aria-describedby': 'scriptdock-page-editor-help',
		} );
		editors[ key ] = cm;
	}

	$( function () {
		var $box = $( '.scriptdock-page-box' );
		if ( ! $box.length ) {
			return;
		}

		function activate( $tab, focus ) {
			var key = $tab.data( 'tab' );
			$tab.addClass( 'is-active' ).attr( { 'aria-selected': 'true', tabindex: '0' } )
				.siblings().removeClass( 'is-active' ).attr( { 'aria-selected': 'false', tabindex: '-1' } );
			$box.find( '.scriptdock-tab-panel' ).each( function () {
				var $panel = $( this );
				var active = $panel.data( 'panel' ) === key;
				$panel.toggleClass( 'is-active', active ).prop( 'hidden', ! active );
				if ( active ) {
					initEditor( $panel );
				}
			} );
			if ( focus ) {
				$tab.trigger( 'focus' );
			}
		}

		$box.on( 'click', '.scriptdock-tab', function () {
			activate( $( this ), false );
		} );

		// One tab stop for the whole row; the arrow keys, Home and End move
		// along it, the way tabs work everywhere else.
		$box.on( 'keydown', '.scriptdock-tab', function ( event ) {
			var $tabs = $box.find( '.scriptdock-tab' );
			var index = $tabs.index( this );
			var step = $( document.body ).hasClass( 'rtl' ) ? -1 : 1;
			var target = null;
			if ( 'ArrowRight' === event.key ) {
				target = index + step;
			} else if ( 'ArrowLeft' === event.key ) {
				target = index - step;
			} else if ( 'Home' === event.key ) {
				target = 0;
			} else if ( 'End' === event.key ) {
				target = $tabs.length - 1;
			}
			if ( null === target ) {
				return;
			}
			event.preventDefault();
			activate( $tabs.eq( ( target + $tabs.length ) % $tabs.length ), true );
		} );

		var syncChecklist = function () {
			var mode = $box.find( 'input[name="scriptdock_page[disable_mode]"]:checked' ).val();
			$box.find( '.scriptdock-snippet-checklist input' ).prop( 'disabled', 'some' !== mode );
		};
		$box.on( 'change', 'input[name="scriptdock_page[disable_mode]"]', syncChecklist );
		syncChecklist();

		// The meta box can start collapsed or move around; initialise lazily.
		initEditor( $box.find( '.scriptdock-tab-panel.is-active' ) );
		$( document ).on( 'postbox-toggled', function () {
			initEditor( $box.find( '.scriptdock-tab-panel.is-active' ) );
		} );
	} );
}( jQuery ) );
