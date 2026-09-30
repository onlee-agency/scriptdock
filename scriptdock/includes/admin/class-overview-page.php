<?php
/**
 * The Overview screen (S01): ScriptDock's landing page.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Rest\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the root the Overview app mounts on, with the whole screen's data
 * inlined so it draws without waiting for a request.
 */
final class Overview_Page {

	/**
	 * Page slug. Also the slug of the top-level menu, so ScriptDock opens
	 * here.
	 */
	const SLUG = 'scriptdock';

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'scriptdock-overview';

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
			<div id="sd-overview" class="sd-app sd-overview-screen">
				<noscript>
					<p><?php esc_html_e( 'The Overview needs JavaScript. Turn it on in your browser, or go straight to the snippet list.', 'scriptdock' ); ?></p>
				</noscript>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueues the app on this screen. The page is matched by its slug: a
	 * submenu's hook suffix carries the parent's menu title, which changes
	 * with the count bubble.
	 */
	public static function assets() {
		if ( ! Admin::is_screen( self::SLUG ) ) {
			return;
		}
		$asset_file = SCRIPTDOCK_DIR . 'build/overview.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( self::HANDLE, SCRIPTDOCK_URL . 'assets/css/app/overview.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/overview.css' ) );
		wp_enqueue_script( self::HANDLE, SCRIPTDOCK_URL . 'build/overview.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'scriptdock' );
		wp_add_inline_script( self::HANDLE, 'window.sdOverview = ' . wp_json_encode( self::bootstrap(), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * The screen's data, from the same route the app would call.
	 *
	 * @return array|null
	 */
	private static function bootstrap() {
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/overview' ) );
		return $response->is_error() ? null : $response->get_data();
	}
}
