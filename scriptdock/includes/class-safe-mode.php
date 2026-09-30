<?php
/**
 * Safe mode.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Safe mode stops every snippet from running so a locked-out admin can get back
 * in and fix things. It can be turned on in three ways:
 *
 * 1. define( 'SCRIPTDOCK_SAFE_MODE', true ); in wp-config.php (whole site).
 * 2. Visiting any URL with ?scriptdock_safe_mode={secret key} (sets a cookie
 *    for this browser only, valid for two hours).
 * 3. The "Enter safe mode" link in the admin bar (same cookie, nonce protected).
 */
final class Safe_Mode {

	/**
	 * Cookie name.
	 */
	const COOKIE = 'scriptdock_safe_mode';

	/**
	 * Query parameter.
	 */
	const PARAM = 'scriptdock_safe_mode';

	/**
	 * Cached state.
	 *
	 * @var bool|null
	 */
	private static $active = null;

	/**
	 * Query parameter that marks the screen the recovery link lands on.
	 */
	const LANDED = 'scriptdock-safe';

	/**
	 * User option recording that someone has kept the recovery link, by
	 * copying it or emailing it to themselves. Ticks the Overview checklist.
	 */
	const LINK_SAVED = 'scriptdock_safe_link_saved';

	/**
	 * Hooks the exit handler.
	 */
	public static function init() {
		add_action( 'admin_post_scriptdock_safe_mode', array( __CLASS__, 'handle_toggle' ) );
		// On init, before wp-admin sends a logged-out visitor to the login
		// screen: that redirect would carry the key along in redirect_to.
		add_action( 'init', array( __CLASS__, 'clean_link' ), 1 );
		add_action( 'send_headers', array( __CLASS__, 'no_cache' ) );
	}

	/**
	 * Keeps pages drawn in safe mode out of page caches. Snippets are off
	 * for this browser only; a cached copy would switch them off for everyone.
	 */
	public static function no_cache() {
		if ( ! self::is_active() || self::is_forced() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The signal page-cache plugins share; it has to have this name.
		}
		nocache_headers();
	}

	/**
	 * Takes the secret out of the address bar once it has done its job.
	 *
	 * The cookie is already set by then, so the key is only sitting in the
	 * URL — where it would be bookmarked, kept in history and sent on in
	 * Referer headers. The screen it lands on is marked instead, which is
	 * what S18's banner looks for.
	 */
	public static function clean_link() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A secret key, checked in is_active().
		if ( ! isset( $_GET[ self::PARAM ] ) || ! self::is_active() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		// A redirect would drop a form's fields.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ), array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$url = add_query_arg( self::LANDED, '1', remove_query_arg( self::PARAM ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Whether this screen is the one the recovery link led to.
	 *
	 * @return bool
	 */
	public static function arrived_via_link() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides which banner to draw.
		return isset( $_GET[ self::LANDED ] ) && self::is_active() && ! self::is_forced();
	}

	/**
	 * Whether safe mode is on for this request.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( null !== self::$active ) {
			return self::$active;
		}

		if ( defined( 'SCRIPTDOCK_SAFE_MODE' ) && SCRIPTDOCK_SAFE_MODE ) {
			self::$active = true;
			return true;
		}

		self::$active = false;
		$key          = (string) Settings::get( 'safe_mode_key' );
		if ( '' === $key ) {
			return false;
		}

		// A secret key, not a nonce, protects this link: it has to work while logged out.
		if ( isset( $_GET[ self::PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$given = sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( hash_equals( $key, $given ) ) {
				self::set_cookie( true );
				self::$active = true;
				return true;
			}
		}

		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			self::$active = self::cookie_is_valid( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
		}

		return self::$active;
	}

	/**
	 * Records that the current user has kept the recovery link.
	 */
	public static function mark_link_saved() {
		update_user_option( get_current_user_id(), self::LINK_SAVED, 1 );
	}

	/**
	 * Whether the current user has kept the recovery link.
	 *
	 * @return bool
	 */
	public static function link_saved() {
		return (bool) get_user_option( self::LINK_SAVED );
	}

	/**
	 * Whether safe mode was forced by the wp-config.php constant.
	 *
	 * @return bool
	 */
	public static function is_forced() {
		return defined( 'SCRIPTDOCK_SAFE_MODE' ) && SCRIPTDOCK_SAFE_MODE;
	}

	/**
	 * The secret recovery URL.
	 *
	 * @return string
	 */
	public static function recovery_url() {
		return add_query_arg( self::PARAM, rawurlencode( (string) Settings::get( 'safe_mode_key' ) ), admin_url( 'index.php' ) );
	}

	/**
	 * URL that turns safe mode on or off for the current browser.
	 *
	 * @param bool $enable Whether to enable.
	 * @return string
	 */
	public static function toggle_url( $enable ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'scriptdock_safe_mode',
					'enable' => $enable ? '1' : '0',
				),
				admin_url( 'admin-post.php' )
			),
			'scriptdock_safe_mode'
		);
	}

	/**
	 * Handles the enter/exit link.
	 */
	public static function handle_toggle() {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'scriptdock' ), 403 );
		}
		check_admin_referer( 'scriptdock_safe_mode' );
		$enable = isset( $_GET['enable'] ) && '1' === $_GET['enable'];
		self::set_cookie( $enable );
		$redirect = wp_get_referer();
		wp_safe_redirect( $redirect ? remove_query_arg( self::PARAM, $redirect ) : admin_url() );
		exit;
	}

	/**
	 * Sets or clears the safe mode cookie.
	 *
	 * @param bool $enable Whether to enable.
	 */
	private static function set_cookie( $enable ) {
		if ( headers_sent() ) {
			return;
		}
		$expires = $enable ? time() + 2 * HOUR_IN_SECONDS : time() - YEAR_IN_SECONDS;
		$value   = $enable ? self::cookie_value( $expires ) : '';
		setcookie( self::COOKIE, $value, $expires, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		if ( COOKIEPATH !== SITECOOKIEPATH ) {
			setcookie( self::COOKIE, $value, $expires, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}
		if ( ! $enable ) {
			unset( $_COOKIE[ self::COOKIE ] );
		}
		self::$active = $enable || self::is_forced();
	}

	/**
	 * Cookie value: when it runs out, signed with the secret key. The expiry
	 * is checked here too, not just by the browser, so a copied cookie stops
	 * working on time.
	 *
	 * @param int $expires Unix time the cookie runs out.
	 * @return string
	 */
	private static function cookie_value( $expires ) {
		return (int) $expires . '.' . hash_hmac( 'sha256', 'safe-mode|' . (int) $expires, (string) Settings::get( 'safe_mode_key' ) );
	}

	/**
	 * Whether a cookie was made with the current key and has not run out.
	 *
	 * @param string $cookie Cookie value.
	 * @return bool
	 */
	private static function cookie_is_valid( $cookie ) {
		$parts = explode( '.', (string) $cookie, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || (int) $parts[0] < time() ) {
			return false;
		}
		return hash_equals( self::cookie_value( (int) $parts[0] ), $cookie );
	}
}
