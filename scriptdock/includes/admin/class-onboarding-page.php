<?php
/**
 * S02: first run.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Capabilities;
use ScriptDock\Library;
use ScriptDock\Migrator;
use ScriptDock\Safe_Mode;
use ScriptDock\Settings;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Four screens on the first visit: what ScriptDock is, the safe mode link
 * (the one thing worth doing before anything goes wrong), snippets to bring
 * over from another plugin, and where to go next.
 *
 * It is a full-screen page of its own rather than a modal over the Overview,
 * because the Overview has nothing on it yet and reading it through a scrim
 * would be a distraction.
 */
final class Onboarding_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'scriptdock-setup';

	/**
	 * Option that remembers setup is over.
	 */
	const OPTION = 'scriptdock_onboarded';

	/**
	 * Hooks the page.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'scriptdock_admin_assets', array( __CLASS__, 'assets' ) );
		add_action( 'current_screen', array( __CLASS__, 'title' ) );
	}

	/**
	 * Registers the page without putting it in the menu: it is somewhere you
	 * are sent, not somewhere you browse to.
	 */
	public static function menu() {
		add_submenu_page(
			'',
			__( 'Set up ScriptDock', 'scriptdock' ),
			__( 'Set up ScriptDock', 'scriptdock' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Whether setup has been finished or skipped.
	 *
	 * @return bool
	 */
	public static function done() {
		return (bool) get_option( self::OPTION );
	}

	/**
	 * Marks setup as over, however it ended.
	 */
	public static function finish() {
		update_option( self::OPTION, time(), true );
	}

	/**
	 * Sends a first-time administrator to setup instead of the Overview.
	 *
	 * Only from ScriptDock's own screens, so activating the plugin never
	 * takes over a screen the reader was in the middle of.
	 */
	public static function maybe_redirect() {
		if ( self::done() || wp_doing_ajax() || ! Capabilities::can_manage() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which screen was asked for.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::SLUG === $page || 0 !== strpos( $page, 'scriptdock' ) ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Names the page. A page with no parent menu gets no title from
	 * WordPress, which leaves the front of the browser tab blank and hands
	 * the admin header a null it complains about on PHP 8.1 and later. The
	 * header reads the title from this global, and keeps one already set.
	 */
	public static function title() {
		if ( Admin::is_screen( self::SLUG ) ) {
			$GLOBALS['title'] = __( 'Set up ScriptDock', 'scriptdock' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- See above.
		}
	}

	/**
	 * The setup page's URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Prints the mount point.
	 */
	public static function render() {
		?>
		<div class="wrap sd-wrap">
			<hr class="wp-header-end">
			<div id="sd-onboarding" class="sd-app sd-onboarding-screen">
				<noscript>
					<p><?php esc_html_e( 'Setup needs JavaScript. You can turn it on, or go straight to ScriptDock — nothing here is required.', 'scriptdock' ); ?></p>
					<p><a href="<?php echo esc_url( Snippets::list_url() ); ?>"><?php esc_html_e( 'Go to snippets', 'scriptdock' ); ?></a></p>
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
		$asset_file = SCRIPTDOCK_DIR . 'build/onboarding.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( 'scriptdock-onboarding', SCRIPTDOCK_URL . 'assets/css/app/onboarding.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/onboarding.css' ) );
		wp_enqueue_script( 'scriptdock-onboarding', SCRIPTDOCK_URL . 'build/onboarding.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'scriptdock-onboarding', 'scriptdock' );
		wp_add_inline_script( 'scriptdock-onboarding', 'window.sdOnboarding = ' . wp_json_encode( self::bootstrap(), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @return array
	 */
	private static function bootstrap() {
		$sources = array();
		foreach ( Migrator::sources() as $key => $source ) {
			$sources[] = array(
				'key'   => $key,
				'label' => $source[0],
				'count' => (int) $source[1],
			);
		}

		$user = wp_get_current_user();

		return array(
			'safeLink' => Safe_Mode::recovery_url(),
			'sources'  => $sources,
			'email'    => $user->user_email,
			'library'  => count( Library::templates() ),
			'urls'     => array(
				'new'      => Snippets::edit_url(),
				'library'  => admin_url( 'admin.php?page=' . Library_Page::SLUG ),
				'overview' => admin_url( 'admin.php?page=' . Overview_Page::SLUG ),
				'settings' => Settings::page_url(),
			),
		);
	}
}
