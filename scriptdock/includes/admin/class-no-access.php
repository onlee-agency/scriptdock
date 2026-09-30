<?php
/**
 * S21: the page shown to someone who may not use ScriptDock.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;


defined( 'ABSPATH' ) || exit;

/**
 * WordPress's own wall for a screen you cannot reach is a bare white page
 * reading "Sorry, you are not allowed to access this page." Someone who
 * followed a link from a colleague deserves better than that: who to ask,
 * and a way back.
 *
 * WordPress decides this inside wp-admin/includes/menu.php, before the admin
 * page has a hook suffix or a header, so there is no way to draw inside the
 * admin frame. What we can do is take over the wp_die() that follows, which
 * is a whole page of its own — the same thing WordPress is about to draw,
 * only ours. It is put back if anything else ends up calling wp_die() first,
 * and it only ever applies on ScriptDock's own pages.
 */
final class No_Access {

	/**
	 * The padlock drawing.
	 *
	 * Kept in step with `no-access` in src/components/illustrations.js, which
	 * is the same drawing for the React screens. Change both together.
	 */
	const PADLOCK = '<g class="sd-illustration__line"><rect x="60" y="66" width="80" height="54" rx="8" /><path d="M77 66V51a23 23 0 0 1 46 0v15" /><g class="sd-illustration__faint"><path d="M44 96h-10" /><path d="M166 96h10" /></g></g><circle class="sd-illustration__glow" cx="100" cy="93" r="18" /><circle class="sd-illustration__locked" cx="100" cy="93" r="8.5" />';

	/**
	 * Stylesheets the page needs, in order.
	 */
	const STYLES = array( 'tokens', 'base', 'button', 'display' );

	/**
	 * Hooks the page.
	 */
	public static function init() {
		add_action( 'admin_page_access_denied', array( __CLASS__, 'take_over' ) );
	}

	/**
	 * Offers to draw the refusal, on our pages only.
	 */
	public static function take_over() {
		if ( ! self::is_our_page() ) {
			return;
		}
		add_filter( 'wp_die_handler', array( __CLASS__, 'handler' ) );
	}

	/**
	 * Hands WordPress our page in place of its own.
	 *
	 * @return callable
	 */
	public static function handler() {
		remove_filter( 'wp_die_handler', array( __CLASS__, 'handler' ) );
		return array( __CLASS__, 'render' );
	}

	/**
	 * Draws the page and stops.
	 *
	 * @param string|\WP_Error $message What WordPress was going to say.
	 * @param string           $title   Page title.
	 * @param array|int        $args    wp_die() arguments.
	 */
	public static function render( $message, $title = '', $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- wp_die() handler signature; our page says it in our own words.
		$args   = is_array( $args ) ? $args : array();
		$status = isset( $args['response'] ) ? (int) $args['response'] : 403;

		if ( ! headers_sent() ) {
			status_header( $status );
			nocache_headers();
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		}

		$heading = __( 'You do not have access to this', 'scriptdock' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $heading ); ?></title>
<?php foreach ( self::STYLES as $style ) : ?>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- A wp_die() handler prints the whole page itself; wp_head() never runs here, so an enqueued style would go nowhere. WordPress's own handler inlines its CSS for the same reason. ?>
<link rel="stylesheet" href="<?php echo esc_url( SCRIPTDOCK_URL . 'assets/css/app/' . $style . '.css?ver=' . SCRIPTDOCK_VERSION ); ?>" media="all">
<?php endforeach; ?>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--sd-gray-50);padding:24px;}</style>
</head>
<body class="sd-app">
	<main class="sd-empty">
		<div class="sd-empty__glow" aria-hidden="true"></div>
		<div class="sd-empty__art" aria-hidden="true">
			<svg class="sd-illustration" viewBox="0 0 200 150" width="160" height="120" fill="none" aria-hidden="true" focusable="false"><?php echo self::PADLOCK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?></svg>
		</div>
		<div class="sd-empty__body">
			<h1 class="sd-empty__title"><?php echo esc_html( $heading ); ?></h1>
			<p class="sd-empty__text"><?php echo esc_html( self::explanation() ); ?></p>
		</div>
		<div class="sd-empty__actions">
			<a class="sd-button sd-button--secondary" href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Back to Dashboard', 'scriptdock' ); ?></a>
		</div>
	</main>
</body>
</html>
		<?php
		die();
	}

	/**
	 * Whether the page being asked for is one of ours.
	 *
	 * @return bool
	 */
	private static function is_our_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which page was asked for.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return '' !== $page && 0 === strpos( $page, 'scriptdock' );
	}

	/**
	 * Who to ask. Nobody is named: anyone logged in can reach this page, and
	 * a display name is often the administrator's login.
	 *
	 * @return string
	 */
	private static function explanation() {
		// On a network a site's own administrators cannot be given access:
		// code runs for every site, so WordPress keeps it to super admins.
		if ( is_multisite() ) {
			return __( 'On this network, only super admins can manage code. Ask one to make the change for you.', 'scriptdock' );
		}
		return __( 'ScriptDock is limited to administrators on this site. Ask an administrator to give you access, or to make the change for you.', 'scriptdock' );
	}
}
