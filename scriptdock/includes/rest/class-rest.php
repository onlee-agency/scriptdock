<?php
/**
 * REST API bootstrap.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the scriptdock/v1 routes the admin screens use.
 *
 * Every route needs the snippet management capability. Changing a PHP
 * snippet also needs the PHP capability; the controllers check that per
 * snippet and answer 403 with the reason.
 */
final class Rest {

	/**
	 * Route namespace.
	 */
	const ROUTE_NAMESPACE = 'scriptdock/v1';

	/**
	 * Hooks route registration.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		( new Snippets_Controller() )->register_routes();
		( new Tags_Controller() )->register_routes();
		( new Targeting_Controller() )->register_routes();
		( new Overview_Controller() )->register_routes();
		( new Content_Controller() )->register_routes();
		( new Library_Controller() )->register_routes();
		( new Tools_Controller() )->register_routes();
		( new Settings_Controller() )->register_routes();
		( new Preferences_Controller() )->register_routes();
		( new Onboarding_Controller() )->register_routes();
	}

	/**
	 * Permission callback: may the current user manage snippets?
	 *
	 * @return true|\WP_Error
	 */
	public static function can_manage() {
		if ( Capabilities::can_manage() ) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			is_multisite()
				? __( 'Sorry, only super admins can manage snippets on this network.', 'scriptdock' )
				: __( 'Sorry, you are not allowed to manage snippets. Ask an administrator.', 'scriptdock' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
