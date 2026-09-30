/**
 * Build configuration, extending WordPress's default (@wordpress/scripts).
 *
 * - `plugin`: the plugin's screens, built into scriptdock/build/ and shipped.
 * - `gallery`: the component gallery (dev only), built into the Docker
 *   site's mu-plugins folder; it never ships with the plugin.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const plugin = {
	...defaultConfig,
	name: 'plugin',
	entry: {
		'block-editor': path.resolve(
			__dirname,
			'scriptdock/src/block-editor/index.js'
		),
		files: path.resolve( __dirname, 'scriptdock/src/files/index.js' ),
		library: path.resolve( __dirname, 'scriptdock/src/library/index.js' ),
		global: path.resolve( __dirname, 'scriptdock/src/global/index.js' ),
		onboarding: path.resolve(
			__dirname,
			'scriptdock/src/onboarding/index.js'
		),
		overview: path.resolve( __dirname, 'scriptdock/src/overview/index.js' ),
		palette: path.resolve( __dirname, 'scriptdock/src/palette/index.js' ),
		snippets: path.resolve( __dirname, 'scriptdock/src/snippets/index.js' ),
		settings: path.resolve( __dirname, 'scriptdock/src/settings/index.js' ),
		tools: path.resolve( __dirname, 'scriptdock/src/tools/index.js' ),
		editor: path.resolve( __dirname, 'scriptdock/src/editor/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'scriptdock/build' ),
	},
};

const gallery = {
	...defaultConfig,
	name: 'gallery',
	entry: {
		gallery: path.resolve( __dirname, 'dev/gallery/src/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'dev/mu-plugins/sd-gallery' ),
	},
};

module.exports = [ plugin, gallery ];
