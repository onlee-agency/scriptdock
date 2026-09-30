<?php
/**
 * The Header & Footer screen (S09).
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Registry;
use ScriptDock\Rest\Rest;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the root the app mounts on, with the saved code inlined so the
 * screen draws without waiting for a request.
 */
final class Global_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'scriptdock-global';

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'scriptdock-global';

	/**
	 * Hooks the screen.
	 */
	public static function init() {
		add_action( 'scriptdock_admin_assets', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Prints the mount point.
	 */
	public static function render() {
		?>
		<div class="wrap sd-wrap">
			<hr class="wp-header-end">
			<div id="sd-global" class="sd-app sd-global-screen">
				<noscript>
					<p><?php esc_html_e( 'This screen needs JavaScript. Turn it on in your browser to edit the site-wide code.', 'scriptdock' ); ?></p>
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
		$asset_file = SCRIPTDOCK_DIR . 'build/global.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		// Also enqueues CodeMirror and wp.codeEditor.
		$editors = Admin::code_editor_settings( array( 'html' ) );

		wp_enqueue_style( self::HANDLE, SCRIPTDOCK_URL . 'assets/css/app/global.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/global.css' ) );
		wp_enqueue_script( self::HANDLE, SCRIPTDOCK_URL . 'build/global.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'scriptdock' );
		wp_add_inline_script( self::HANDLE, 'window.sdGlobal = ' . wp_json_encode( self::bootstrap( $editors ), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @param array|false $editors Code editor settings.
	 * @return array
	 */
	private static function bootstrap( $editors ) {
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/global' ) );

		return array(
			'item'        => $response->is_error() ? null : $response->get_data(),
			'codeEditors' => $editors ? $editors : null,
			'theme'       => Editor_App::theme(),
			'urls'        => array(
				'new'  => Snippets::edit_url(),
				'list' => Snippets::list_url(),
			),
			'types'       => Registry::TYPES,
		);
	}
}
