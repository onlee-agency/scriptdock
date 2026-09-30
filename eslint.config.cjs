/**
 * ESLint for ScriptDock's React sources: the wp-scripts defaults, plus the
 * WordPress packages the build leaves external.
 *
 * DependencyExtractionWebpackPlugin turns every `@wordpress/*` import into a
 * `wp.*` global that WordPress loads, so none of them are bundled and none
 * belong in package.json. Declaring them as core modules tells the import
 * rules they are provided by the platform, the way Node built-ins are.
 */
const defaults = require( '@wordpress/scripts/config/eslint.config.cjs' );

const WORDPRESS_EXTERNALS = [
	'@wordpress/a11y',
	'@wordpress/api-fetch',
	'@wordpress/block-editor',
	'@wordpress/blocks',
	'@wordpress/components',
	'@wordpress/compose',
	'@wordpress/data',
	'@wordpress/dom-ready',
	'@wordpress/edit-post',
	'@wordpress/editor',
	'@wordpress/element',
	'@wordpress/hooks',
	'@wordpress/html-entities',
	'@wordpress/i18n',
	'@wordpress/plugins',
	'@wordpress/url',
];

module.exports = [
	...defaults,
	{
		settings: {
			'import/core-modules': WORDPRESS_EXTERNALS,
		},
	},
];
