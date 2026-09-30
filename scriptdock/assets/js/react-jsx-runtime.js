/**
 * ScriptDock: a stand-in for WordPress's react-jsx-runtime script, which
 * arrived in WordPress 6.6. The screens are compiled with React's automatic
 * JSX runtime, so they call jsx(), jsxs() and Fragment from it. PHP registers
 * this file only when WordPress has no script of that name (6.3 to 6.5);
 * newer versions use their own.
 *
 * Both functions hand the work to React.createElement, so they work with
 * whichever React WordPress loaded.
 */
( function ( React ) {
	'use strict';

	if ( ! React || window.ReactJSXRuntime ) {
		return;
	}

	var has = Object.prototype.hasOwnProperty;

	/**
	 * The props without children. The compiler passes the key on its own; a
	 * key in the props themselves wins, as it does in React's runtime.
	 *
	 * @param {Object} config Props from the compiled code.
	 * @param {*}      key    The element's key, when it has one.
	 * @return {Object} Props for createElement.
	 */
	function propsFor( config, key ) {
		var props = {};
		var name;
		for ( name in config ) {
			if ( 'children' !== name && has.call( config, name ) ) {
				props[ name ] = config[ name ];
			}
		}
		if ( undefined !== key && ! has.call( props, 'key' ) ) {
			props.key = key;
		}
		return props;
	}

	/**
	 * One child, or children only known when the code runs (a list from
	 * map()), which React checks for keys as usual.
	 *
	 * @param {*}      type   Element type.
	 * @param {Object} config Props, children included.
	 * @param {*}      key    Key.
	 * @return {Object} The element.
	 */
	function jsx( type, config, key ) {
		var props = propsFor( config, key );
		if ( config && has.call( config, 'children' ) ) {
			props.children = config.children;
		}
		return React.createElement( type, props );
	}

	/**
	 * Several children written out in the code. They are handed over one by
	 * one, so React knows they need no keys.
	 *
	 * @param {*}      type   Element type.
	 * @param {Object} config Props, children included.
	 * @param {*}      key    Key.
	 * @return {Object} The element.
	 */
	function jsxs( type, config, key ) {
		if ( ! config || ! Array.isArray( config.children ) ) {
			return jsx( type, config, key );
		}
		return React.createElement.apply(
			React,
			[ type, propsFor( config, key ) ].concat( config.children )
		);
	}

	window.ReactJSXRuntime = {
		Fragment: React.Fragment,
		jsx: jsx,
		jsxs: jsxs,
	};
}( window.React ) );
