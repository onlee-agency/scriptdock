<?php
/**
 * The Library screen (S10).
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Rest\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the root the app mounts on, with the whole library inlined: it ships
 * with the plugin, so there is nothing to wait for.
 */
final class Library_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'scriptdock-library';

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'scriptdock-library';

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
			<div id="sd-library" class="sd-app sd-library-screen">
				<noscript>
					<p><?php esc_html_e( 'The library needs JavaScript. Turn it on in your browser to browse the ready-made snippets.', 'scriptdock' ); ?></p>
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
		$asset_file = SCRIPTDOCK_DIR . 'build/library.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( self::HANDLE, SCRIPTDOCK_URL . 'assets/css/app/library.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/library.css' ) );
		wp_enqueue_script( self::HANDLE, SCRIPTDOCK_URL . 'build/library.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'scriptdock' );
		wp_add_inline_script( self::HANDLE, 'window.sdLibrary = ' . wp_json_encode( self::bootstrap(), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @return array
	 */
	private static function bootstrap() {
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/' . self::REST_BASE ) );

		return array(
			'item'    => $response->is_error() ? null : $response->get_data(),
			'consent' => array( 'api' => function_exists( 'wp_has_consent' ) ),
		);
	}

	/**
	 * REST base for the library.
	 */
	const REST_BASE = 'library';
}
