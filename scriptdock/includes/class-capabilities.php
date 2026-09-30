<?php
/**
 * Capability handling.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the plugin's meta capabilities onto core capabilities.
 *
 * - scriptdock_manage: HTML, CSS and JavaScript snippets, page scripts and the
 *   global header/footer. Needs manage_options and unfiltered_html. Core
 *   removes unfiltered_html from everyone but super admins on multisite and
 *   from everyone when DISALLOW_UNFILTERED_HTML is set; that carries over.
 * - scriptdock_manage_php: PHP and Universal snippets. Also needs
 *   edit_plugins, so DISALLOW_FILE_EDIT switches PHP editing off just like it
 *   switches off the plugin and theme file editors.
 */
final class Capabilities {

	/**
	 * Meta capability for snippet management.
	 */
	const MANAGE = 'scriptdock_manage';

	/**
	 * Meta capability for PHP code.
	 */
	const MANAGE_PHP = 'scriptdock_manage_php';

	/**
	 * Hooks the capability mapper.
	 */
	public static function init() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 3 );
	}

	/**
	 * Maps plugin capabilities to primitive capabilities.
	 *
	 * The snippet post type points its capabilities at self::MANAGE with
	 * map_meta_cap disabled, so core may hand us self::MANAGE inside $caps.
	 *
	 * @param string[] $caps    Primitive capabilities required.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id ) {
		$caps     = (array) $caps;
		$wants    = array_intersect( array( self::MANAGE, self::MANAGE_PHP ), array_merge( array( $cap ), $caps ) );
		if ( ! $wants ) {
			return $caps;
		}

		$caps = array_values( array_diff( $caps, array( self::MANAGE, self::MANAGE_PHP ) ) );

		if ( in_array( self::MANAGE_PHP, $wants, true ) && ! self::php_enabled() ) {
			return array( 'do_not_allow' );
		}

		// WP-CLI without --user: whoever runs it already has shell access to the site.
		if ( 0 === (int) $user_id && defined( 'WP_CLI' ) && WP_CLI ) {
			return $caps ? $caps : array( 'exist' );
		}

		/**
		 * Filters the capabilities a user needs to manage snippets.
		 *
		 * @param string[] $required Capabilities. Default manage_options and unfiltered_html.
		 */
		$required = (array) apply_filters( 'scriptdock_required_capabilities', array( 'manage_options', 'unfiltered_html' ) );

		if ( in_array( self::MANAGE_PHP, $wants, true ) && ! ( defined( 'SCRIPTDOCK_ALLOW_PHP_WITHOUT_FILE_EDIT' ) && SCRIPTDOCK_ALLOW_PHP_WITHOUT_FILE_EDIT ) ) {
			$required[] = 'edit_plugins';
		}

		foreach ( array_unique( $required ) as $required_cap ) {
			$caps = array_merge( $caps, map_meta_cap( $required_cap, $user_id ) );
		}

		return array_values( array_unique( $caps ) );
	}

	/**
	 * Whether the current user may manage snippets.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::MANAGE );
	}

	/**
	 * Whether the current user may create and edit PHP code.
	 *
	 * @return bool
	 */
	public static function can_manage_php() {
		return current_user_can( self::MANAGE_PHP );
	}

	/**
	 * Whether the current user may edit a snippet of the given type.
	 *
	 * @param string $type Snippet type.
	 * @return bool
	 */
	public static function can_edit_type( $type ) {
		return Registry::is_php_type( $type ) ? self::can_manage_php() : self::can_manage();
	}

	/**
	 * Whether PHP snippets may run on this site at all.
	 *
	 * Define SCRIPTDOCK_DISABLE_PHP as true in wp-config.php to switch off all
	 * PHP execution and editing.
	 *
	 * @return bool
	 */
	public static function php_enabled() {
		return ! ( defined( 'SCRIPTDOCK_DISABLE_PHP' ) && SCRIPTDOCK_DISABLE_PHP );
	}

	/**
	 * Why PHP editing is unavailable for the current user, if it is.
	 *
	 * @return string Empty string when PHP editing is available.
	 */
	public static function php_unavailable_reason() {
		if ( ! self::php_enabled() ) {
			return __( 'PHP snippets are disabled by the SCRIPTDOCK_DISABLE_PHP constant in wp-config.php.', 'scriptdock' );
		}
		if ( self::can_manage_php() ) {
			return '';
		}
		if ( ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) || ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
			return __( 'PHP snippets cannot be edited because file editing is disabled on this site (DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS). To allow PHP snippets anyway, add define( \'SCRIPTDOCK_ALLOW_PHP_WITHOUT_FILE_EDIT\', true ); to wp-config.php.', 'scriptdock' );
		}
		return __( 'Your account is not allowed to edit PHP code on this site.', 'scriptdock' );
	}
}
