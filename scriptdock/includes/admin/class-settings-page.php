<?php
/**
 * Settings screen.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Capabilities;
use ScriptDock\Compiler;
use ScriptDock\Safe_Mode;
use ScriptDock\Settings;
use ScriptDock\Signer;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, safety information and the safe mode link.
 */
final class Settings_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'scriptdock-settings';

	/**
	 * Settings group.
	 */
	const GROUP = 'scriptdock_settings_group';

	/**
	 * Hooks handlers.
	 */
	public static function init() {
		add_action( 'scriptdock_admin_assets', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( __CLASS__, 'capability' ) );
	}

	/**
	 * Capability needed to save the settings form.
	 *
	 * @return string
	 */
	public static function capability() {
		return Capabilities::MANAGE;
	}

	/**
	 * Registers the setting.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Prints the mount point.
	 */
	public static function render() {
		?>
		<div class="wrap sd-wrap">
			<hr class="wp-header-end">
			<div id="sd-settings" class="sd-app sd-settings-screen">
				<noscript>
					<p><?php esc_html_e( 'Settings need JavaScript. Turn it on in your browser to change them.', 'scriptdock' ); ?></p>
				</noscript>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueues the app on this screen.
	 */
	public static function assets() {
		if ( ! Admin::is_screen( self::SLUG ) ) {
			return;
		}
		$asset_file = SCRIPTDOCK_DIR . 'build/settings.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( 'scriptdock-settings', SCRIPTDOCK_URL . 'assets/css/app/settings.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/settings.css' ) );
		wp_enqueue_script( 'scriptdock-settings', SCRIPTDOCK_URL . 'build/settings.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'scriptdock-settings', 'scriptdock' );
		wp_add_inline_script( 'scriptdock-settings', 'window.sdSettings = ' . wp_json_encode( self::bootstrap(), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @return array
	 */
	private static function bootstrap() {
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/' . \ScriptDock\Rest\Rest::ROUTE_NAMESPACE . '/settings' ) );
		return array( 'item' => $response->is_error() ? null : $response->get_data() );
	}
}
