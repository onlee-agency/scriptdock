<?php
/**
 * Main plugin bootstrap.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers hooks.
	 */
	private function __construct() {
		Stream::register();
		Capabilities::init();
		Error_Handler::init();
		Safe_Mode::init();
		Snippets::init();

		add_action( 'init', array( Post_Type::class, 'register' ), 5 );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );

		add_action( 'wp_default_scripts', array( __CLASS__, 'jsx_runtime' ), 11 );
		if ( did_action( 'wp_default_scripts' ) ) {
			self::jsx_runtime( wp_scripts() );
		}

		Runtime::init();
		Shortcode::init();
		Page_Scripts::init();
		Global_Scripts::init();
		Virtual_Files::init();
		Admin_Bar::init();
		Updater::init();
		Site_Health::init();
		Rest\Rest::init();

		if ( is_admin() ) {
			Admin\Admin::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'scriptdock', CLI::class );
		}
	}

	/**
	 * Rebuilds the runtime cache when the plugin version changes.
	 */
	public function maybe_upgrade() {
		$installed = get_option( 'scriptdock_version' );
		if ( $installed === SCRIPTDOCK_VERSION ) {
			return;
		}
		Settings::ensure_defaults();
		Compiler::rebuild();

		// Setup is for a first install. Anyone upgrading has already made
		// their own way around, so it is marked done rather than sprung on
		// them.
		if ( $installed && ! get_option( Admin\Onboarding_Page::OPTION ) ) {
			Admin\Onboarding_Page::finish();
		}

		update_option( 'scriptdock_version', SCRIPTDOCK_VERSION, true );
	}

	/**
	 * Registers a stand-in for WordPress's react-jsx-runtime script, which
	 * only arrived in WordPress 6.6. Every screen is built with React's
	 * automatic JSX runtime and lists that script as a dependency, and
	 * WordPress will not load a script whose dependency is missing: without
	 * this, WordPress 6.3 to 6.5 would show every ScriptDock screen blank.
	 *
	 * @param \WP_Scripts $scripts Registered scripts.
	 */
	public static function jsx_runtime( $scripts ) {
		if ( ! $scripts->query( 'react-jsx-runtime', 'registered' ) ) {
			$scripts->add( 'react-jsx-runtime', SCRIPTDOCK_URL . 'assets/js/react-jsx-runtime.js', array( 'react' ), self::asset_version( 'js/react-jsx-runtime.js' ) );
		}
	}

	/**
	 * Version for one of the plugin's own asset files. With SCRIPT_DEBUG on,
	 * the file's modified time is added, so a browser never keeps using an
	 * old copy while the plugin is being worked on; a release changes the
	 * plugin version instead.
	 *
	 * @param string $path Path inside assets/.
	 * @return string
	 */
	public static function asset_version( $path ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$file = SCRIPTDOCK_DIR . 'assets/' . $path;
			if ( file_exists( $file ) ) {
				return SCRIPTDOCK_VERSION . '.' . filemtime( $file );
			}
		}
		return SCRIPTDOCK_VERSION;
	}

	/**
	 * Activation callback.
	 */
	public static function activate() {
		Settings::ensure_defaults();
		Post_Type::register();
		Compiler::rebuild();
		update_option( 'scriptdock_version', SCRIPTDOCK_VERSION, true );
	}

	/**
	 * Deactivation callback. Data is kept; see uninstall.php for removal.
	 */
	public static function deactivate() {
		delete_site_transient( Updater::CACHE );
	}
}
