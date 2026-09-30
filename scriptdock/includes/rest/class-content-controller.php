<?php
/**
 * REST: the screens that edit one stored thing — the site-wide header and
 * footer code, and the files ScriptDock serves at the site root.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Global_Scripts;
use ScriptDock\Virtual_Files;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/global and /scriptdock/v1/files.
 */
final class Content_Controller extends \WP_REST_Controller {

	/**
	 * Sets the namespace.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		$manage = array( Rest::class, 'can_manage' );

		register_rest_route(
			$this->namespace,
			'/global',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_global' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_global' ),
					'permission_callback' => $manage,
					'args'                => $this->global_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/files',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_files' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_files' ),
					'permission_callback' => $manage,
					'args'                => $this->file_params(),
				),
			)
		);
	}

	/**
	 * What the Header & Footer screen saves.
	 *
	 * @return array
	 */
	private function global_params() {
		$args = array();
		foreach ( Global_Scripts::AREAS as $area ) {
			$args[ $area ]               = array(
				'description' => __( 'Code for this area.', 'scriptdock' ),
				'type'        => 'string',
			);
			$args[ $area . '_priority' ] = array(
				'description' => __( 'Priority; lower runs first.', 'scriptdock' ),
				'type'        => 'integer',
				'minimum'     => -9999,
				'maximum'     => 9999,
			);
		}
		return $args;
	}

	/**
	 * What the Site Files screen saves.
	 *
	 * @return array
	 */
	private function file_params() {
		$args = array(
			'robots_txt'  => array(
				'description' => __( 'Extra robots.txt rules.', 'scriptdock' ),
				'type'        => 'string',
			),
			'robots_mode' => array(
				'description' => __( 'append or replace.', 'scriptdock' ),
				'type'        => 'string',
				'enum'        => array( 'append', 'replace' ),
			),
		);
		foreach ( array_keys( Virtual_Files::files() ) as $key ) {
			$args[ $key ] = array(
				'description' => __( 'File contents.', 'scriptdock' ),
				'type'        => 'string',
			);
		}
		return $args;
	}

	/**
	 * The site-wide code, with what each area prints on and whether the code
	 * still matches its signature.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_global() {
		return rest_ensure_response( $this->global_data() );
	}

	/**
	 * Saves the site-wide code. Saving signs it again, which approves code
	 * that was changed outside ScriptDock.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_global( $request ) {
		$data = Global_Scripts::get();
		// Saving approves all three areas, so code changed outside ScriptDock
		// is only approved by a save that sent all of it back.
		if ( ! Global_Scripts::is_trusted( $data ) ) {
			foreach ( Global_Scripts::AREAS as $area ) {
				if ( null === $request[ $area ] ) {
					return new \WP_Error( 'scriptdock_untrusted', __( 'The site-wide code changed outside ScriptDock. Review all three areas and save them together to approve the change.', 'scriptdock' ), array( 'status' => 409 ) );
				}
			}
		}
		$input = array();
		foreach ( Global_Scripts::AREAS as $area ) {
			$input[ $area ]               = null !== $request[ $area ]
				? (string) $request[ $area ]
				: $data[ $area ];
			$input[ $area . '_priority' ] = null !== $request[ $area . '_priority' ]
				? (int) $request[ $area . '_priority' ]
				: (int) $data[ $area . '_priority' ];
		}
		Global_Scripts::save( $input );

		return rest_ensure_response(
			array(
				'item'   => $this->global_data(),
				'notice' => array(
					'code'    => 'saved',
					'message' => __( 'Saved. The code runs on every page.', 'scriptdock' ),
				),
			)
		);
	}

	/**
	 * The three areas as the screen draws them.
	 *
	 * @return array
	 */
	private function global_data() {
		$data  = Global_Scripts::get();
		$hooks = array(
			'head'   => 'wp_head',
			'body'   => 'wp_body_open',
			'footer' => 'wp_footer',
		);
		$labels = array(
			'head'   => array(
				__( 'Header', 'scriptdock' ),
				__( 'Printed inside <head> on every page. Use it for analytics, verification meta tags and fonts.', 'scriptdock' ),
			),
			'body'   => array(
				__( 'Body', 'scriptdock' ),
				__( 'Printed right after the opening <body> tag, for example Google Tag Manager’s noscript code.', 'scriptdock' ),
			),
			'footer' => array(
				__( 'Footer', 'scriptdock' ),
				__( 'Printed before </body>. Scripts here do not hold the page back from rendering.', 'scriptdock' ),
			),
		);

		$areas = array();
		foreach ( Global_Scripts::AREAS as $area ) {
			$areas[] = array(
				'key'         => $area,
				'label'       => $labels[ $area ][0],
				'description' => $labels[ $area ][1],
				'hook'        => $hooks[ $area ],
				'code'        => (string) $data[ $area ],
				'priority'    => (int) $data[ $area . '_priority' ],
			);
		}

		return array(
			'areas'   => $areas,
			'trusted' => Global_Scripts::is_trusted( $data ),
		);
	}

	/**
	 * The files ScriptDock can serve, with their state.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_files() {
		return rest_ensure_response( $this->files_data() );
	}

	/**
	 * Saves the files.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_files( $request ) {
		$data  = Virtual_Files::get();
		$input = array();
		foreach ( array_merge( array_keys( Virtual_Files::files() ), array( 'robots_txt' ) ) as $key ) {
			$input[ $key ] = null !== $request[ $key ] ? (string) $request[ $key ] : (string) $data[ $key ];
		}
		$input['robots_mode'] = null !== $request['robots_mode'] ? (string) $request['robots_mode'] : $data['robots_mode'];
		Virtual_Files::save( $input );

		return rest_ensure_response(
			array(
				'item'   => $this->files_data(),
				'notice' => array(
					'code'    => 'saved',
					'message' => __( 'Saved.', 'scriptdock' ),
				),
			)
		);
	}

	/**
	 * Each file: what it is for, where it is served, and whether a real file
	 * on the server is answering instead.
	 *
	 * @return array
	 */
	private function files_data() {
		$data  = Virtual_Files::get();
		$about = array(
			'ads_txt'      => array(
				'ads.txt',
				__( 'Tells ad networks who may sell your inventory.', 'scriptdock' ),
				"google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0",
			),
			'app_ads_txt'  => array(
				'app-ads.txt',
				__( 'The same, for apps that point at this domain.', 'scriptdock' ),
				"google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0",
			),
			'llms_txt'     => array(
				'llms.txt',
				__( 'Tells AI crawlers what this site is and what they may use.', 'scriptdock' ),
				"# " . get_bloginfo( 'name' ) . "\n\n> " . get_bloginfo( 'description' ) . "\n\n## Pages\n- " . home_url( '/' ),
			),
			'security_txt' => array(
				'security.txt',
				__( 'Tells security researchers how to report a problem.', 'scriptdock' ),
				"Contact: mailto:security@" . wp_parse_url( home_url(), PHP_URL_HOST ) . "\nExpires: " . gmdate( 'Y-m-d\TH:i:s\Z', time() + YEAR_IN_SECONDS ),
			),
		);

		$files = array();
		foreach ( Virtual_Files::files() as $key => $path ) {
			$content  = (string) $data[ $key ];
			$physical = Virtual_Files::physical_exists( $path );
			$files[]  = array(
				'key'         => $key,
				'name'        => $about[ $key ][0],
				'description' => $about[ $key ][1],
				'path'        => $path,
				'url'         => Virtual_Files::url( $path ),
				'content'     => $content,
				'example'     => $about[ $key ][2],
				'state'       => $this->file_state( $content, $physical ),
			);
		}

		$robots_physical = Virtual_Files::physical_exists( '/robots.txt' );
		return array(
			'files'     => $files,
			'robots'    => array(
				'key'         => 'robots_txt',
				'name'        => 'robots.txt',
				'description' => __( 'Extra rules for crawlers, on top of the ones WordPress writes.', 'scriptdock' ),
				'path'        => '/robots.txt',
				'url'         => Virtual_Files::url( '/robots.txt' ),
				'content'     => (string) $data['robots_txt'],
				'mode'        => $data['robots_mode'],
				'example'     => "User-agent: GPTBot\nDisallow: /",
				'state'       => $this->file_state( (string) $data['robots_txt'], $robots_physical ),
			),
			// A site at the root of its domain has no path at all.
			'subfolder' => untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) ),
			'blog_public' => (bool) get_option( 'blog_public' ),
		);
	}

	/**
	 * live, off, or overridden by a file on the server.
	 *
	 * @param string $content  What ScriptDock would serve.
	 * @param bool   $physical Whether a real file is there.
	 * @return string
	 */
	private function file_state( $content, $physical ) {
		if ( $physical ) {
			return 'overridden';
		}
		return '' === trim( $content ) ? 'off' : 'live';
	}
}
