<?php
/**
 * Import & Export screen.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The screen itself; everything it does goes through the tools REST
 * routes. This class only prints the root and hands the app its first
 * answer.
 */
final class Tools_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'scriptdock-tools';

	/**
	 * Hooks handlers.
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
			<div id="sd-tools" class="sd-app sd-tools-screen">
				<noscript>
					<p><?php esc_html_e( 'This screen needs JavaScript. Turn it on in your browser to import or export snippets.', 'scriptdock' ); ?></p>
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
		$asset_file = SCRIPTDOCK_DIR . 'build/tools.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( 'scriptdock-tools', SCRIPTDOCK_URL . 'assets/css/app/tools.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/tools.css' ) );
		wp_enqueue_script( 'scriptdock-tools', SCRIPTDOCK_URL . 'build/tools.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'scriptdock-tools', 'scriptdock' );
		wp_add_inline_script( 'scriptdock-tools', 'window.sdTools = ' . wp_json_encode( self::bootstrap(), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @return array
	 */
	private static function bootstrap() {
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/' . \ScriptDock\Rest\Rest::ROUTE_NAMESPACE . '/tools' ) );
		return array( 'item' => $response->is_error() ? null : $response->get_data() );
	}
}
