<?php
/**
 * REST: the current user's list and editor preferences.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Admin\Editor_App;
use ScriptDock\Admin\Snippets_Page;
use ScriptDock\Safe_Mode;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/preferences: snippets per page, row density and the code
 * editor's theme, saved per user. Snippets per page is the same setting as
 * Screen Options.
 */
final class Preferences_Controller extends \WP_REST_Controller {

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'preferences';
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( Rest::class, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( Rest::class, 'can_manage' ),
					'args'                => array(
						'per_page'        => array(
							'description' => __( 'Snippets per page.', 'scriptdock' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'density'         => array(
							'description' => __( 'Row density.', 'scriptdock' ),
							'type'        => 'string',
							'enum'        => array( 'comfortable', 'compact' ),
						),
						'editor_theme'    => array(
							'description' => __( 'Code editor theme.', 'scriptdock' ),
							'type'        => 'string',
							'enum'        => array( 'light', 'dark' ),
						),
						'safe_link_saved' => array(
							'description' => __( 'Whether you have copied or emailed yourself the safe mode link.', 'scriptdock' ),
							'type'        => 'boolean',
						),
					),
				),
			)
		);
	}

	/**
	 * The current preferences.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by the parent signature.
		return rest_ensure_response( $this->current() );
	}

	/**
	 * Saves the preferences that were sent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_item( $request ) {
		$user_id = get_current_user_id();
		if ( null !== $request['per_page'] ) {
			// Stored where WordPress's Screen Options form stores it (user
			// meta without the site prefix), so both controls change the
			// same setting. A prefixed copy would win over it, so none stays.
			update_user_meta( $user_id, Snippets_Page::PER_PAGE_OPTION, (int) $request['per_page'] );
			delete_user_option( $user_id, Snippets_Page::PER_PAGE_OPTION );
		}
		if ( null !== $request['density'] ) {
			update_user_option( $user_id, Snippets_Page::DENSITY_OPTION, $request['density'] );
		}
		if ( null !== $request['editor_theme'] ) {
			update_user_option( $user_id, Editor_App::THEME_OPTION, $request['editor_theme'] );
		}
		if ( true === $request['safe_link_saved'] ) {
			Safe_Mode::mark_link_saved();
		}
		return rest_ensure_response( $this->current() );
	}

	/**
	 * The preferences as stored.
	 *
	 * @return array
	 */
	private function current() {
		return array(
			'per_page'     => Snippets_Page::per_page(),
			'density'      => Snippets_Page::density(),
			'editor_theme' => Editor_App::theme(),
		);
	}
}
