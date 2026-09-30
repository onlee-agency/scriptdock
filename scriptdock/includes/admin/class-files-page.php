<?php
/**
 * The Site Files screen (S11).
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Rest\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the root the app mounts on, with the files inlined so the screen
 * draws without waiting for a request.
 */
final class Files_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'scriptdock-files';

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'scriptdock-files';

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
			<div id="sd-files" class="sd-app sd-files-screen">
				<noscript>
					<p><?php esc_html_e( 'This screen needs JavaScript. Turn it on in your browser to edit these files.', 'scriptdock' ); ?></p>
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
		$asset_file = SCRIPTDOCK_DIR . 'build/files.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( self::HANDLE, SCRIPTDOCK_URL . 'assets/css/app/files.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/files.css' ) );
		wp_enqueue_script( self::HANDLE, SCRIPTDOCK_URL . 'build/files.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'scriptdock' );
		wp_add_inline_script( self::HANDLE, 'window.sdFiles = ' . wp_json_encode( self::bootstrap(), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @return array
	 */
	private static function bootstrap() {
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/files' ) );
		return array( 'item' => $response->is_error() ? null : $response->get_data() );
	}
}
