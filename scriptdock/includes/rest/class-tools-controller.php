<?php
/**
 * REST: moving snippets in and out.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Compiler;
use ScriptDock\Import_Export;
use ScriptDock\Migrator;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/tools: what can be imported from another plugin or a file,
 * and doing it.
 *
 * Exporting stays on the snippets route, which already builds the file.
 */
final class Tools_Controller extends \WP_REST_Controller {

	/**
	 * Largest import file accepted, in bytes.
	 */
	const MAX_BYTES = 5242880;

	/**
	 * Option set once snippets have been imported, for the Overview checklist.
	 */
	const IMPORTED_OPTION = 'scriptdock_imported';

	/**
	 * Plugins ScriptDock can read, and the file each one runs from.
	 */
	const PLUGIN_FILES = array(
		'wpcode'        => array( 'insert-headers-and-footers/ihaf.php', 'wpcode-premium/wpcode.php' ),
		'wpcode_global' => array( 'insert-headers-and-footers/ihaf.php', 'wpcode-premium/wpcode.php' ),
		'code_snippets' => array( 'code-snippets/code-snippets.php', 'code-snippets-pro/code-snippets.php' ),
		'hfcm'          => array( 'header-footer-code-manager/99robots-header-footer-code-manager.php' ),
		'sccj'          => array( 'custom-css-js/custom-css-js.php' ),
	);

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'tools';
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
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/migrate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'migrate' ),
				'permission_callback' => $manage,
				'args'                => array(
					'source'   => array(
						'description' => __( 'Which plugin to read.', 'scriptdock' ),
						'type'        => 'string',
						'required'    => true,
					),
					'activate' => array(
						'description' => __( 'Whether imported snippets run straight away.', 'scriptdock' ),
						'type'        => 'boolean',
						'default'     => false,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $manage,
				'args'                => array(
					'json'     => array(
						'description' => __( 'The contents of an export file.', 'scriptdock' ),
						'type'        => 'string',
						'required'    => true,
					),
					'activate' => array(
						'description' => __( 'Whether imported snippets run straight away.', 'scriptdock' ),
						'type'        => 'boolean',
						'default'     => false,
					),
				),
			)
		);
	}

	/**
	 * What this site can import, and from where.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The screen takes no arguments.
		$sources = array();
		foreach ( Migrator::sources() as $key => $source ) {
			$sources[] = array(
				'key'    => $key,
				'label'  => $source[0],
				'count'  => (int) $source[1],
				'active' => $this->plugin_active( $key ),
			);
		}

		return rest_ensure_response(
			array(
				'sources' => $sources,
				'export'  => array(
					'cli'  => 'wp scriptdock export --file=snippets.json',
					'list' => Snippets::list_url(),
				),
				'max'     => self::MAX_BYTES,
			)
		);
	}

	/**
	 * Whether the plugin a source belongs to is still switched on.
	 *
	 * @param string $key Source key.
	 * @return bool
	 */
	private function plugin_active( $key ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( isset( self::PLUGIN_FILES[ $key ] ) ? self::PLUGIN_FILES[ $key ] : array() as $file ) {
			if ( is_plugin_active( $file ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Imports from another plugin.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function migrate( $request ) {
		$source  = sanitize_key( (string) $request['source'] );
		$sources = Migrator::sources();
		if ( ! isset( $sources[ $source ] ) ) {
			return new \WP_Error( 'scriptdock_not_found', __( 'Nothing to import from that plugin.', 'scriptdock' ), array( 'status' => 404 ) );
		}

		$results = Migrator::run( $source, (bool) $request['activate'] );
		Compiler::rebuild();
		self::remember( $results );
		return rest_ensure_response( $this->results( $results, $sources[ $source ][0] ) );
	}

	/**
	 * Imports an export file.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( $request ) {
		$json = (string) $request['json'];
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return new \WP_Error( 'scriptdock_too_big', __( 'That file is too large (5 MB is the limit).', 'scriptdock' ), array( 'status' => 413 ) );
		}

		$items = Import_Export::parse( $json );
		if ( is_wp_error( $items ) ) {
			$items->add_data( array( 'status' => 400 ), $items->get_error_code() );
			return $items;
		}

		$results = Import_Export::import( $items, array( 'activate' => (bool) $request['activate'] ) );
		Compiler::rebuild();
		self::remember( $results );
		return rest_ensure_response( $this->results( $results, '' ) );
	}

	/**
	 * Ticks the Overview checklist's import step once anything came in.
	 *
	 * @param array $results Results from the importer.
	 */
	private static function remember( array $results ) {
		if ( ! empty( $results['imported'] ) ) {
			update_option( self::IMPORTED_OPTION, 1, false );
		}
	}

	/**
	 * What came of an import, in the shape the results modal draws.
	 *
	 * @param array  $results Results from the importer.
	 * @param string $from    Where they came from.
	 * @return array
	 */
	private function results( array $results, $from ) {
		$imported = (int) $results['imported'];
		$active   = isset( $results['activated'] ) ? (int) $results['activated'] : 0;

		return array(
			'imported' => $imported,
			'active'   => $active,
			'warnings' => array_values( (array) $results['skipped'] ),
			'from'     => $from,
			'list'     => Snippets::list_url(),
			'notice'   => array(
				'code'    => $imported ? 'imported' : 'nothing',
				'message' => $imported
					? sprintf(
						/* translators: 1: how many snippets, 2: how many of them run. */
						_n( 'Imported %1$d snippet, %2$d running.', 'Imported %1$d snippets, %2$d running.', $imported, 'scriptdock' ),
						$imported,
						$active
					)
					: __( 'Nothing was imported.', 'scriptdock' ),
			),
		);
	}
}
