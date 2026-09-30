<?php
/**
 * Plugin Name:       ScriptDock
 * Plugin URI:        https://github.com/onlee-agency/scriptdock
 * Description:       Add PHP, HTML, CSS and JavaScript snippets anywhere on your site — site-wide, per page, or by smart conditions — with error protection, revisions and no upsells.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            ScriptDock
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       scriptdock
 * Update URI:        https://github.com/onlee-agency/scriptdock
 *
 * @package ScriptDock
 */

defined( 'ABSPATH' ) || exit;

/*
 * GitHub repository ("owner/repo") that publishes releases of this plugin.
 * Define it in wp-config.php to use another one, or as '' to switch update
 * checks off.
 */
if ( ! defined( 'SCRIPTDOCK_UPDATE_REPO' ) ) {
	define( 'SCRIPTDOCK_UPDATE_REPO', 'onlee-agency/scriptdock' );
}

define( 'SCRIPTDOCK_VERSION', '1.0.0' );
define( 'SCRIPTDOCK_FILE', __FILE__ );
define( 'SCRIPTDOCK_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCRIPTDOCK_URL', plugin_dir_url( __FILE__ ) );

/*
 * Autoloader for the ScriptDock namespace.
 * ScriptDock\Admin\Editor_Page => includes/admin/class-editor-page.php
 */
spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'ScriptDock\\' ) ) {
			return;
		}
		$parts = explode( '\\', substr( $class_name, strlen( 'ScriptDock\\' ) ) );
		$name  = array_pop( $parts );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = SCRIPTDOCK_DIR . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'ScriptDock\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ScriptDock\\Plugin', 'deactivate' ) );

ScriptDock\Plugin::instance();
