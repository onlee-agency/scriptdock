<?php
/**
 * S18: the safe mode banner.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Capabilities;
use ScriptDock\Safe_Mode;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Safe mode has two moments worth a banner of our own:
 *
 * - Someone has just opened the secret recovery link and needs to know what
 *   has happened and what to do next.
 * - Safe mode is forced in wp-config.php, which cannot be undone from here.
 *
 * Everywhere else — safe mode carried on from earlier, from the toolbar link
 * — a plain WordPress notice is the right weight, and Admin::notices() keeps
 * printing that one.
 */
final class Safe_Mode_Banner {

	/**
	 * Hooks the banner.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	/**
	 * Whether the banner replaces the plain notice on this screen.
	 *
	 * @return bool
	 */
	public static function shows() {
		if ( ! Capabilities::can_manage() || ! Safe_Mode::is_active() ) {
			return false;
		}
		return Safe_Mode::arrived_via_link() || Safe_Mode::is_forced();
	}

	/**
	 * Loads the banner's styles.
	 */
	public static function assets() {
		if ( ! self::shows() ) {
			return;
		}
		Shell::register();
		wp_enqueue_style(
			'scriptdock-safe-mode',
			SCRIPTDOCK_URL . 'assets/css/app/safe-mode.css',
			array( 'scriptdock-base', 'scriptdock-button' ),
			Admin::asset_version( 'css/app/safe-mode.css' )
		);
	}

	/**
	 * Draws the banner.
	 */
	public static function render() {
		if ( ! self::shows() ) {
			return;
		}
		if ( Safe_Mode::is_forced() ) {
			self::forced();
			return;
		}
		self::landing();
	}

	/**
	 * After the secret link: what has happened, and the two ways on.
	 */
	private static function landing() {
		?>
		<div class="sd-app sd-safe">
			<span class="sd-safe__mark" aria-hidden="true"><?php Icons::render( 'shield-check', 26, 1.6 ); ?></span>
			<div class="sd-safe__body">
				<h2 class="sd-safe__title"><?php esc_html_e( 'Safe mode is on in this browser', 'scriptdock' ); ?></h2>
				<p class="sd-safe__text">
					<?php esc_html_e( 'No snippets are running for you, so you can fix whatever broke. Everyone else still sees your site exactly as it was. Safe mode ends when you exit, or on its own after two hours.', 'scriptdock' ); ?>
				</p>
				<div class="sd-safe__actions">
					<a class="sd-button sd-button--primary" href="<?php echo esc_url( Snippets::list_url() ); ?>">
						<?php esc_html_e( 'Go to snippets', 'scriptdock' ); ?>
						<span class="sd-button__dot" aria-hidden="true"><?php Icons::render( 'chevron-right', 14, 2.6 ); ?></span>
					</a>
					<a class="sd-button sd-button--secondary" href="<?php echo esc_url( Safe_Mode::toggle_url( false ) ); ?>">
						<?php esc_html_e( 'Exit safe mode', 'scriptdock' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Forced for everyone: nothing to press, so the banner only explains.
	 */
	private static function forced() {
		?>
		<div class="sd-app sd-safe sd-safe--forced">
			<span class="sd-safe__mark" aria-hidden="true"><?php Icons::render( 'lock', 20, 1.8 ); ?></span>
			<p class="sd-safe__text">
				<strong class="sd-safe__lead"><?php esc_html_e( 'Safe mode is forced for everyone.', 'scriptdock' ); ?></strong>
				<?php
				printf(
					/* translators: %s: the SCRIPTDOCK_SAFE_MODE constant, in code. */
					esc_html__( 'No snippets run anywhere on the site. Remove %s from wp-config.php to switch it off — it cannot be turned off from here.', 'scriptdock' ),
					'<code>SCRIPTDOCK_SAFE_MODE</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed markup.
				);
				?>
			</p>
		</div>
		<?php
	}
}
