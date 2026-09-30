<?php
/**
 * REST: first run.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Admin\Onboarding_Page;
use ScriptDock\Safe_Mode;
use ScriptDock\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/onboarding: the two things setup does that nothing else
 * does — email the safe mode link to the person setting up, and remember
 * that setup is over.
 *
 * Importing from another plugin is the Import & Export route's job, and
 * setup calls that one.
 */
final class Onboarding_Controller extends \WP_REST_Controller {

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'onboarding';
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		$manage = array( Rest::class, 'can_manage' );

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/email-link',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'email_link' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/done',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'done' ),
				'permission_callback' => $manage,
			)
		);
	}

	/**
	 * Emails the safe mode link to whoever is setting ScriptDock up.
	 *
	 * It goes to their own account's address and nowhere else: the link is a
	 * password in all but name, and nobody should be able to send it
	 * somewhere by asking.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function email_link() {
		$user = wp_get_current_user();
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return new \WP_Error(
				'scriptdock_no_email',
				__( 'Your account has no email address, so there is nowhere to send it.', 'scriptdock' ),
				array( 'status' => 400 )
			);
		}

		$site = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$body = implode(
			"\n",
			array(
				sprintf(
					/* translators: %s: site name. */
					__( 'This is your ScriptDock safe mode link for %s.', 'scriptdock' ),
					$site
				),
				'',
				Safe_Mode::recovery_url(),
				'',
				__( 'If code ever locks you out of WordPress, open this link. It pauses every snippet in your browser so you can get back in and fix things. Visitors are not affected.', 'scriptdock' ),
				'',
				__( 'Treat it like a password: anyone with the link can pause your snippets. You can make a new one at any time, which stops the old one working:', 'scriptdock' ),
				Settings::page_url(),
			)
		);

		$sent = wp_mail(
			$user->user_email,
			sprintf(
				/* translators: %s: site name. */
				__( '[%s] Your ScriptDock safe mode link', 'scriptdock' ),
				$site
			),
			$body
		);

		if ( ! $sent ) {
			return new \WP_Error(
				'scriptdock_mail_failed',
				__( 'The email could not be sent. Copy the link instead — this site may not be set up to send mail.', 'scriptdock' ),
				array( 'status' => 500 )
			);
		}
		Safe_Mode::mark_link_saved();

		return rest_ensure_response(
			array(
				'sent'   => $user->user_email,
				'notice' => array(
					'code'    => 'emailed',
					'message' => sprintf(
						/* translators: %s: email address. */
						__( 'Sent to %s.', 'scriptdock' ),
						$user->user_email
					),
				),
			)
		);
	}

	/**
	 * Remembers that setup is over, so it does not come back.
	 *
	 * @return \WP_REST_Response
	 */
	public function done() {
		Onboarding_Page::finish();
		return rest_ensure_response( array( 'done' => true ) );
	}
}
