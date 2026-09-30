<?php
/**
 * REST: the Settings screen.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Capabilities;
use ScriptDock\Compiler;
use ScriptDock\Page_Scripts;
use ScriptDock\Safe_Mode;
use ScriptDock\Settings;
use ScriptDock\Signer;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/settings: the stored settings, plus what the screen needs to
 * explain them — the safe mode link, how snippets are signed, and whether PHP
 * snippets are available here.
 */
final class Settings_Controller extends \WP_REST_Controller {

	/**
	 * Option set once page-script content types have been saved, for the
	 * Overview checklist.
	 */
	const PAGE_TYPES_OPTION = 'scriptdock_page_types_chosen';

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'settings';
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		$manage = array( Rest::class, 'can_manage' );

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_item' ),
					'permission_callback' => $manage,
					'args'                => array(
						'values' => array(
							'description' => __( 'Settings to save.', 'scriptdock' ),
							'type'        => 'object',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/safe-link',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'new_safe_link' ),
				'permission_callback' => $manage,
			)
		);
	}

	/**
	 * The settings and everything the screen says about them.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The screen takes no arguments.
		return rest_ensure_response( $this->data() );
	}

	/**
	 * Saves the settings. Anything left out keeps its current value.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_item( $request ) {
		$values = (array) ( $request['values'] ? $request['values'] : array() );
		$merged = array_merge( Settings::all(), $values );
		update_option( Settings::OPTION, Settings::sanitize( $merged ) );
		Compiler::mark_dirty();
		if ( array_key_exists( 'page_post_types', $values ) ) {
			update_option( self::PAGE_TYPES_OPTION, 1, false );
		}

		return rest_ensure_response(
			array(
				'item'   => $this->data(),
				'notice' => array(
					'code'    => 'saved',
					'message' => __( 'Settings saved.', 'scriptdock' ),
				),
			)
		);
	}

	/**
	 * Makes a new safe mode link, which stops the old one working.
	 *
	 * @return \WP_REST_Response
	 */
	public function new_safe_link() {
		$settings                  = Settings::all();
		$settings['safe_mode_key'] = Settings::generate_key();
		update_option( Settings::OPTION, Settings::sanitize( $settings ) );

		return rest_ensure_response(
			array(
				'item'   => $this->data(),
				'notice' => array(
					'code'    => 'new_link',
					'message' => __( 'New link made. The old one no longer works.', 'scriptdock' ),
				),
			)
		);
	}

	/**
	 * Everything the screen draws.
	 *
	 * @return array
	 */
	private function data() {
		$settings = Settings::all();
		$user     = wp_get_current_user();
		$untrusted = Snippets::untrusted();

		$types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$types[] = array(
				'value' => $type->name,
				'label' => $type->labels->name,
			);
		}

		$uploads = wp_get_upload_dir();

		return array(
			'values'  => array(
				'admin_bar'           => (bool) $settings['admin_bar'],
				'auto_disable'        => (bool) $settings['auto_disable'],
				'error_email'         => (bool) $settings['error_email'],
				'page_post_types'     => array_values( (array) $settings['page_post_types'] ),
				'asset_files'         => (bool) $settings['asset_files'],
				'minify_css'          => (bool) $settings['minify_css'],
				'editor_theme'        => (string) $settings['editor_theme'],
				'revisions'           => (int) $settings['revisions'],
				'delete_on_uninstall' => (bool) $settings['delete_on_uninstall'],
			),
			'types'   => $types,
			'email'   => $user->user_email,
			'files'   => array(
				'path' => str_replace( ABSPATH, '', $uploads['basedir'] ) . '/scriptdock/',
			),
			'pages'   => array(
				'current' => Page_Scripts::post_types(),
			),
			'safe'    => array(
				'url'      => Safe_Mode::recovery_url(),
				'forced'   => Safe_Mode::is_forced(),
				'active'   => Safe_Mode::is_active(),
				'constant' => "define( 'SCRIPTDOCK_SAFE_MODE', true );",
			),
			'tamper'  => array(
				'on'        => Signer::enabled(),
				'source'    => self::key_source_text(),
				'off_note'  => __( 'Switched off by SCRIPTDOCK_DISABLE_TAMPER_PROTECTION in wp-config.php.', 'scriptdock' ),
				'review'    => count( $untrusted ),
				'review_url' => Snippets::list_url( array( 'view' => 'review' ) ),
			),
			'php'     => array(
				'available' => Capabilities::can_manage_php(),
				'reason'    => Capabilities::php_unavailable_reason(),
				'constants' => array(
					"define( 'SCRIPTDOCK_DISABLE_PHP', true );",
					"define( 'SCRIPTDOCK_ALLOW_PHP_WITHOUT_FILE_EDIT', true );",
				),
			),
			'about'   => array(
				'version' => SCRIPTDOCK_VERSION,
				'badges'  => array(
					__( 'Free & open source', 'scriptdock' ),
					__( 'No tracking', 'scriptdock' ),
					__( 'No ads or upsells', 'scriptdock' ),
				),
			),
		);
	}

	/**
	 * Where the signing key comes from, in words.
	 *
	 * @return string
	 */
	private static function key_source_text() {
		switch ( Signer::key_source() ) {
			case 'constant':
				return __( 'Using the SCRIPTDOCK_SIGNING_KEY constant from wp-config.php.', 'scriptdock' );
			case 'config':
				return __( 'Using WordPress’s own security keys from wp-config.php. If those change, snippets need approving again.', 'scriptdock' );
			default:
				return __( 'wp-config.php has no security keys, so WordPress keeps its key in the database, which is where tampered code would be written. Add security keys to wp-config.php, or define SCRIPTDOCK_SIGNING_KEY there.', 'scriptdock' );
		}
	}
}
