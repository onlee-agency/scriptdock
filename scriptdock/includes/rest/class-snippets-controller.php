<?php
/**
 * REST: snippets.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Capabilities;
use ScriptDock\Changes;
use ScriptDock\Compiler;
use ScriptDock\Conditions;
use ScriptDock\Error_Handler;
use ScriptDock\Import_Export;
use ScriptDock\Post_Type;
use ScriptDock\Registry;
use ScriptDock\Runtime;
use ScriptDock\Settings;
use ScriptDock\Signer;
use ScriptDock\Snippet;
use ScriptDock\Snippets;
use ScriptDock\Targeting_Summary;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/snippets: the list with its filters and counts, one
 * snippet, saving from the editor, checking PHP syntax, switching on and
 * off, running on demand, duplicating, the trash, bulk actions and export.
 *
 * Anything that adds or runs code needs the rights for the snippet's type:
 * switching on, running, duplicating. Taking code away never does: switching
 * off, trash, untrash (which brings a snippet back switched off) and
 * permanent delete are open to every snippet manager, so an admin who cannot
 * edit PHP can still stop a PHP snippet that misbehaves.
 *
 * Snippet collections are small, so the list loads every snippet once and
 * filters, counts and sorts in PHP: several filters (needs review, running,
 * targeting) depend on signatures and schedules that a database query
 * cannot see.
 */
final class Snippets_Controller extends \WP_REST_Controller {

	/**
	 * Status views, as the list's pills.
	 */
	const VIEWS = array( 'all', 'active', 'inactive', 'review', 'error', 'trash' );

	/**
	 * Targeting filter values.
	 */
	const TARGETING = array( 'everywhere', 'conditional', 'scheduled', 'shortcode' );

	/**
	 * Sort keys.
	 */
	const ORDERBY = array( 'modified', 'created', 'title', 'priority', 'type' );

	/**
	 * Bulk actions.
	 */
	const BULK_ACTIONS = array( 'activate', 'deactivate', 'approve', 'trash', 'untrash', 'delete', 'add_tag' );

	/**
	 * Placement definitions, loaded once per request.
	 *
	 * @var array|null
	 */
	private $locations = null;

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'snippets';
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		$manage = array( Rest::class, 'can_manage' );
		$id     = array(
			'id' => array(
				'description' => __( 'Snippet ID.', 'scriptdock' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'required'    => true,
			),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $manage,
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $manage,
					'args'                => $this->save_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/describe',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'describe_item' ),
					'permission_callback' => $manage,
					'args'                => array_intersect_key( $this->save_params(), array_flip( array( 'type', 'location', 'location_args', 'conditions', 'schedule' ) ) ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lint',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'lint_item' ),
					'permission_callback' => array( Capabilities::class, 'can_manage_php' ),
					'args'                => array(
						'code' => array(
							'description' => __( 'PHP code to check.', 'scriptdock' ),
							'type'        => 'string',
							'required'    => true,
						),
						'type' => array(
							'description' => __( 'Snippet type.', 'scriptdock' ),
							'type'        => 'string',
							'enum'        => array( 'php', 'universal' ),
							'default'     => 'php',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				'args'   => $id,
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $manage,
					'args'                => $this->save_params(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $manage,
					'args'                => array(
						'force' => array(
							'description' => __( 'Delete a snippet in the trash permanently instead of trashing it.', 'scriptdock' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/changes',
			array(
				'args' => $id,
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'changes_item' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/revisions',
			array(
				'args' => $id,
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_revisions' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/revisions/(?P<revision>\d+)',
			array(
				'args' => $id + array(
					'revision' => array(
						'description' => __( 'Revision ID.', 'scriptdock' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_revision' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_revision' ),
					'permission_callback' => $manage,
				),
			)
		);

		foreach ( array( 'activate', 'deactivate', 'approve', 'duplicate', 'untrash', 'run' ) as $action ) {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/(?P<id>\d+)/' . $action,
				array(
					'args' => $id,
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $action . '_item' ),
						'permission_callback' => $manage,
					),
				)
			);
		}

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk' ),
					'permission_callback' => $manage,
					'args'                => array(
						'action' => array(
							'description' => __( 'What to do with the snippets.', 'scriptdock' ),
							'type'        => 'string',
							'enum'        => self::BULK_ACTIONS,
							'required'    => true,
						),
						'ids'    => array(
							'description' => __( 'Snippet IDs.', 'scriptdock' ),
							'type'        => 'array',
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'minItems'    => 1,
							'maxItems'    => 500,
							'required'    => true,
						),
						'tag'    => array(
							'description' => __( 'Tag name, for add_tag. Created when it does not exist.', 'scriptdock' ),
							'type'        => 'string',
							'maxLength'   => 190,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export' ),
					'permission_callback' => $manage,
					'args'                => array(
						'ids' => array(
							'description' => __( 'Snippet IDs; all snippets when empty.', 'scriptdock' ),
							'type'        => 'array',
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'default'     => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/trash',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'empty_trash' ),
					'permission_callback' => $manage,
				),
			)
		);
	}

	/**
	 * Query parameters for the list.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'view'      => array(
				'description' => __( 'Status view.', 'scriptdock' ),
				'type'        => 'string',
				'enum'        => self::VIEWS,
				'default'     => 'all',
			),
			'search'    => array(
				'description' => __( 'Text to find in titles, notes, code and tags.', 'scriptdock' ),
				'type'        => 'string',
				'default'     => '',
			),
			'type'      => array(
				'description' => __( 'Code types.', 'scriptdock' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => Registry::TYPES,
				),
				'default'     => array(),
			),
			'location'  => array(
				'description' => __( 'Placements.', 'scriptdock' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'default'     => array(),
			),
			'tag'       => array(
				'description' => __( 'Tag IDs; a snippet matches when it has any of them.', 'scriptdock' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'integer' ),
				'default'     => array(),
			),
			'targeting' => array(
				'description' => __( 'Targeting kinds; a snippet matches when it has any of them.', 'scriptdock' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => self::TARGETING,
				),
				'default'     => array(),
			),
			'month'     => array(
				'description' => __( 'Month created, as YYYYMM.', 'scriptdock' ),
				'type'        => 'string',
				'pattern'     => '^(\d{6})?$',
				'default'     => '',
			),
			'orderby'   => array(
				'description' => __( 'Sort key.', 'scriptdock' ),
				'type'        => 'string',
				'enum'        => self::ORDERBY,
				'default'     => 'modified',
			),
			'order'     => array(
				'description' => __( 'Sort direction. Dates default to newest first, the rest to ascending.', 'scriptdock' ),
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
			),
			'page'      => array(
				'description' => __( 'Page of results.', 'scriptdock' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
			),
			'per_page'  => array(
				'description' => __( 'Snippets per page.', 'scriptdock' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => 100,
				'default'     => 20,
			),
		);
	}

	/**
	 * The list: one page of snippets, the counts for every view and the
	 * months that have snippets.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$view  = $request['view'];
		$trash = 'trash' === $view;
		$rows  = $this->rows( $trash );

		$counts = $this->counts( $trash ? $this->rows( false ) : $rows );
		$months = $this->months( $rows );

		$rows = array_values(
			array_filter(
				$rows,
				function ( array $row ) use ( $request ) {
					return $this->matches( $row, $request );
				}
			)
		);
		$this->sort( $rows, $request['orderby'], $request['order'] );

		$total    = count( $rows );
		$per_page = (int) $request['per_page'];
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = min( (int) $request['page'], $pages );
		$items    = array();
		foreach ( array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ) as $row ) {
			$items[] = $this->summary( $row );
		}

		$response = rest_ensure_response(
			array(
				'items'       => $items,
				'total'       => $total,
				'total_pages' => $pages,
				'page'        => $page,
				'per_page'    => $per_page,
				'counts'      => $counts,
				'months'      => $months,
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * One snippet with its code, revisions and signature status.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$row = $this->row( (int) $request['id'], true );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return rest_ensure_response( $this->full( $row ) );
	}

	/**
	 * Fields the editor saves. All optional: an update changes only what it
	 * sends.
	 *
	 * @return array
	 */
	private function save_params() {
		$object = array( 'type' => 'object' );
		return array(
			'title'         => array(
				'description' => __( 'Title.', 'scriptdock' ),
				'type'        => 'string',
			),
			'code'          => array(
				'description' => __( 'Code, stored exactly as sent.', 'scriptdock' ),
				'type'        => 'string',
			),
			'notes'         => array(
				'description' => __( 'Notes.', 'scriptdock' ),
				'type'        => 'string',
			),
			'type'          => array(
				'description' => __( 'Code type.', 'scriptdock' ),
				'type'        => 'string',
				'enum'        => Registry::TYPES,
			),
			'location'      => array(
				'description' => __( 'Placement key.', 'scriptdock' ),
				'type'        => 'string',
			),
			'location_args' => array( 'description' => __( 'Placement extras: paragraph, every, hook.', 'scriptdock' ) ) + $object,
			'priority'      => array(
				'description' => __( 'Priority; lower runs first.', 'scriptdock' ),
				'type'        => 'integer',
				'minimum'     => -9999,
				'maximum'     => 9999,
			),
			'conditions'    => array( 'description' => __( 'Targeting rules.', 'scriptdock' ) ) + $object,
			'schedule'      => array( 'description' => __( 'Start and end, in the site timezone.', 'scriptdock' ) ) + $object,
			'options'       => array( 'description' => __( 'Output options; those sent are merged into the saved ones.', 'scriptdock' ) ) + $object,
			'tags'          => array(
				'description' => __( 'Tag names. New names become tags.', 'scriptdock' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
			),
			'active'        => array(
				'description' => __( 'Whether it should run after saving.', 'scriptdock' ),
				'type'        => 'boolean',
			),
		);
	}

	/**
	 * Creates a snippet from the editor.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$snippet           = new Snippet();
		$snippet->type     = Registry::sanitize_type( $request['type'] ? $request['type'] : 'html' );
		$snippet->location = Registry::default_location( $snippet->type );
		return $this->save( $snippet, $request, true );
	}

	/**
	 * Saves the editor's changes to a snippet.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$snippet = Snippet::get( (int) $request['id'] );
		if ( ! $snippet ) {
			return $this->not_found();
		}
		return $this->save( $snippet, $request, false );
	}

	/**
	 * The plain-language targeting for settings that are not saved yet: the
	 * editor after a type change, and the targeting wizard as it changes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function describe_item( $request ) {
		$snippet = new Snippet();
		$data    = array();
		foreach ( array( 'type', 'location', 'location_args', 'conditions', 'schedule' ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$data[ $key ] = $request[ $key ];
			}
		}
		$snippet->fill( $data );
		return rest_ensure_response( array( 'targeting' => Targeting_Summary::describe( $snippet ) ) );
	}

	/**
	 * An unsaved snippet in the shape the editor loads, so a new snippet
	 * draws the same way as a saved one. Nothing is signed yet, so it counts
	 * as trusted.
	 *
	 * @param Snippet $snippet Unsaved snippet.
	 * @return array
	 */
	public function blank( Snippet $snippet ) {
		$post             = new \WP_Post(
			(object) array(
				'ID'          => 0,
				'post_type'   => Post_Type::NAME,
				'post_status' => 'draft',
				'post_author' => get_current_user_id(),
			)
		);
		$state            = $this->state( $snippet );
		$state['trusted'] = true;
		$state['running'] = false;
		return $this->full(
			array(
				'snippet'  => $snippet,
				'post'     => $post,
				'terms'    => array(),
				'state'    => $state,
				'modified' => 0,
				'created'  => 0,
			)
		);
	}

	/**
	 * Checks PHP syntax without running anything. Line numbers match the
	 * editor whether or not the code starts with an opening tag.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function lint_item( $request ) {
		$snippet       = new Snippet();
		$snippet->type = Registry::sanitize_type( $request['type'] );
		$snippet->code = str_replace( "\r\n", "\n", (string) $request['code'] );
		return rest_ensure_response( array( 'error' => $snippet->lint() ) );
	}

	/**
	 * Saves a snippet the way the editor always has: switched off first,
	 * then switched on only after its checks and, for PHP that runs on every
	 * request, a trial run. Anything that stops it running is saved anyway
	 * and explained in the answer's notice. Saving a snippet that changed
	 * outside ScriptDock signs its code again, which approves it.
	 *
	 * A fatal error in the trial ends the request: the error handler answers
	 * with scriptdock_test_fatal, and the snippet stays saved and switched
	 * off with the error recorded.
	 *
	 * @param Snippet           $snippet Snippet: loaded, or new with defaults.
	 * @param \WP_REST_Request $request Request.
	 * @param bool              $is_new  Whether it is being created.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function save( Snippet $snippet, $request, $is_new ) {
		// PHP is a separate permission: check the saved type and the new one.
		$type = null !== $request['type'] ? Registry::sanitize_type( $request['type'] ) : $snippet->type;
		if ( ! $is_new ) {
			$error = Snippets::edit_error( $snippet );
			if ( $error ) {
				return $error;
			}
		}
		if ( Registry::is_php_type( $type ) && ! Capabilities::can_manage_php() ) {
			return new \WP_Error( 'scriptdock_forbidden', Capabilities::php_unavailable_reason(), array( 'status' => 403 ) );
		}

		$data = array();
		foreach ( array( 'title', 'code', 'type', 'location', 'location_args', 'priority', 'conditions', 'schedule', 'tags' ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$data[ $key ] = $request[ $key ];
			}
		}
		if ( null !== $request['notes'] ) {
			$data['description'] = $request['notes'];
		}
		if ( null !== $request['options'] ) {
			$data['options'] = array_merge( $snippet->options, (array) $request['options'] );
		}
		$wants_active = null !== $request['active'] ? (bool) $request['active'] : $snippet->active;
		// Saving approves the code, but only code that was sent: a save that
		// only renames or retargets a snippet changed outside ScriptDock
		// leaves that change waiting for review.
		$approve = $is_new || null !== $request['code'] || $snippet->is_trusted();

		$snippet->fill( $data );

		if ( Conditions::uses_php( $snippet->conditions ) && ! Capabilities::can_manage_php() ) {
			return new \WP_Error( 'scriptdock_forbidden', __( 'Only administrators who can edit PHP may save snippets with a PHP function condition.', 'scriptdock' ), array( 'status' => 403 ) );
		}

		$notice = null;
		if ( 'custom_hook' === $snippet->location && empty( $snippet->location_args['hook'] ) ) {
			$notice       = array(
				'code'    => 'hook_missing',
				'message' => $wants_active
					? __( 'Saved, but left switched off. Add a hook name, for example woocommerce_after_cart.', 'scriptdock' )
					: __( 'Saved. Add a hook name, for example woocommerce_after_cart, before you switch it on.', 'scriptdock' ),
			);
			$wants_active = false;
		}
		$lint = $snippet->lint();
		if ( $lint ) {
			$notice       = array(
				'code'    => 'syntax',
				'message' => $wants_active
					? __( 'Saved, but left inactive — fix the syntax error first.', 'scriptdock' )
					: __( 'Saved. Fix the syntax error before you switch it on.', 'scriptdock' ),
				'error'   => $lint,
			);
			$wants_active = false;
		}

		$snippet->active = false;
		$saved           = $snippet->save( $approve );
		if ( is_wp_error( $saved ) ) {
			return $this->with_status( $saved );
		}

		if ( $wants_active ) {
			$result = $this->activate( $snippet );
			if ( is_wp_error( $result ) ) {
				$details = $result->get_error_data();
				$notice  = array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
					'error'   => is_array( $details ) && isset( $details['error'] ) ? $details['error'] : null,
				);
			} else {
				$snippet->clear_error();
			}
		}

		Compiler::rebuild();
		$row      = $this->row( $snippet->id, true );
		$response = rest_ensure_response(
			array(
				'item'   => is_wp_error( $row ) ? null : $this->full( $row ),
				'notice' => $notice,
			)
		);
		if ( $is_new ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Switches a snippet on. PHP that runs on every request is tried first;
	 * if it dies, the error handler answers with a JSON error.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function activate_item( $request ) {
		$snippet = Snippet::get( (int) $request['id'] );
		if ( ! $snippet ) {
			return $this->not_found();
		}
		$result = $this->activate( $snippet );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Compiler::rebuild();
		return $this->item_response( $snippet->id );
	}

	/**
	 * Switches a snippet off.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function deactivate_item( $request ) {
		$snippet = Snippet::get( (int) $request['id'] );
		if ( ! $snippet ) {
			return $this->not_found();
		}
		$result = Snippets::set_active( $snippet->id, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Compiler::rebuild();
		return $this->item_response( $snippet->id );
	}

	/**
	 * Approves a snippet that changed outside ScriptDock. A paused snippet
	 * that runs on every request is tried first, as when switching it on.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function approve_item( $request ) {
		$snippet = Snippet::get( (int) $request['id'] );
		if ( ! $snippet ) {
			return $this->not_found();
		}
		$result = $this->approve( $snippet );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Compiler::rebuild();
		return $this->item_response( $snippet->id );
	}

	/**
	 * What changed since ScriptDock last trusted a snippet: a summary and the
	 * changed lines with some context.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function changes_item( $request ) {
		$row = $this->row( (int) $request['id'], true );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return rest_ensure_response( Changes::describe( $row['snippet'], true ) );
	}

	/**
	 * Every saved version of a snippet, newest first.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_revisions( $request ) {
		$snippet = Snippet::get( (int) $request['id'] );
		if ( ! $snippet ) {
			return $this->not_found();
		}

		$revisions = wp_get_post_revisions( $snippet->id, array( 'posts_per_page' => Changes::MAX_REVISIONS ) );
		$items     = array();
		foreach ( $revisions as $revision ) {
			$items[] = $this->revision_entry( $revision, $snippet );
		}

		return rest_ensure_response(
			array(
				'items'   => $items,
				'current' => $this->current_entry( $snippet ),
				'trusted' => $snippet->is_trusted(),
				'can_restore' => null === Snippets::revision_restore_error( $snippet ),
				'reason'  => null === Snippets::revision_restore_error( $snippet ) ? '' : Snippets::revision_restore_error( $snippet )->get_error_message(),
			)
		);
	}

	/**
	 * One saved version: its code, and what changed between it and the code
	 * the snippet has now.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_revision( $request ) {
		$snippet  = Snippet::get( (int) $request['id'] );
		$revision = $snippet ? $this->revision_of( $snippet, (int) $request['revision'] ) : null;
		if ( ! $snippet || ! $revision ) {
			return $this->not_found();
		}

		$diff = Changes::diff( $revision->post_content, $snippet->code );
		return rest_ensure_response(
			array(
				'revision' => $this->revision_entry( $revision, $snippet ),
				'code'     => $revision->post_content,
				'current'  => $snippet->code,
				'diff'     => $diff,
			)
		);
	}

	/**
	 * Puts an older version back. The snippet is switched off first and only
	 * switched on again once the restored code passes its checks, the same way
	 * saving works.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function restore_revision( $request ) {
		$snippet  = Snippet::get( (int) $request['id'] );
		$revision = $snippet ? $this->revision_of( $snippet, (int) $request['revision'] ) : null;
		if ( ! $snippet || ! $revision ) {
			return $this->not_found();
		}
		$error = Snippets::revision_restore_error( $snippet );
		if ( $error ) {
			return $this->with_status( $error );
		}

		$was_active = $snippet->active;
		if ( $was_active ) {
			Snippets::set_active( $snippet->id, false );
		}

		require_once ABSPATH . 'wp-admin/includes/revision.php';
		wp_restore_post_revision( $revision->ID );

		$snippet = Snippet::get( $snippet->id );
		if ( ! $snippet ) {
			return $this->not_found();
		}

		$notice = array(
			'code'    => 'restored',
			'message' => __( 'Restored. This version is now the snippet’s code.', 'scriptdock' ),
		);
		if ( $was_active ) {
			$result = $this->activate( $snippet );
			if ( is_wp_error( $result ) ) {
				$details = $result->get_error_data();
				$notice  = array(
					'code'    => $result->get_error_code(),
					'message' => sprintf(
						/* translators: %s: why it could not be switched on. */
						__( 'Restored, but left switched off. %s', 'scriptdock' ),
						$result->get_error_message()
					),
					'error'   => is_array( $details ) && isset( $details['error'] ) ? $details['error'] : null,
				);
			} else {
				$snippet->clear_error();
			}
		}

		Compiler::rebuild();
		$row = $this->row( $snippet->id, true );
		return rest_ensure_response(
			array(
				'item'   => is_wp_error( $row ) ? null : $this->full( $row ),
				'notice' => $notice,
			)
		);
	}

	/**
	 * A revision of this snippet, or null when the ID is something else.
	 *
	 * @param Snippet $snippet Snippet.
	 * @param int     $id      Revision ID.
	 * @return \WP_Post|null
	 */
	private function revision_of( Snippet $snippet, $id ) {
		$revision = $id ? wp_get_post_revision( $id ) : null;
		if ( ! $revision || (int) $revision->post_parent !== (int) $snippet->id ) {
			return null;
		}
		return $revision;
	}

	/**
	 * One entry in the history: who saved it, when, how big it is, and whether
	 * someone approved it after it changed outside ScriptDock.
	 *
	 * @param \WP_Post $revision Revision.
	 * @param Snippet   $snippet  Snippet.
	 * @return array
	 */
	private function revision_entry( $revision, Snippet $snippet ) {
		$user = get_userdata( (int) $revision->post_author );
		$time = (int) get_post_timestamp( $revision, 'modified' );
		$code = (string) $revision->post_content;

		return array(
			'id'       => (int) $revision->ID,
			'date_gmt' => $time ? gmdate( 'c', $time ) : '',
			'label'    => $this->date_label( $time ),
			'stamp'    => $time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) : '',
			'author'   => $user ? $user->display_name : __( 'Someone', 'scriptdock' ),
			'avatar'   => $user ? get_avatar_url( $user->ID, array( 'size' => 48 ) ) : '',
			'lines'    => '' === $code ? 0 : substr_count( $code, "\n" ) + 1,
			'approved' => (bool) get_metadata( 'post', $revision->ID, Snippet::META_APPROVED, true ),
			'same'     => $code === $snippet->code,
			'url'      => admin_url( 'revision.php?revision=' . (int) $revision->ID ),
		);
	}

	/**
	 * The code as it stands now, as the first entry in the timeline.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return array
	 */
	private function current_entry( Snippet $snippet ) {
		$post = get_post( $snippet->id );
		$user = $post ? get_userdata( (int) $post->post_author ) : null;
		$time = $post ? (int) get_post_timestamp( $post, 'modified' ) : 0;

		return array(
			'id'       => 0,
			'date_gmt' => $time ? gmdate( 'c', $time ) : '',
			'label'    => $this->date_label( $time ),
			'stamp'    => $time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) : '',
			'author'   => $user ? $user->display_name : __( 'Someone', 'scriptdock' ),
			'avatar'   => $user ? get_avatar_url( $user->ID, array( 'size' => 48 ) ) : '',
			'lines'    => '' === $snippet->code ? 0 : substr_count( $snippet->code, "\n" ) + 1,
			'approved' => $snippet->is_trusted(),
			'same'     => true,
			'url'      => '',
		);
	}

	/**
	 * Runs an on-demand PHP snippet once and returns what it printed.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_item( $request ) {
		if ( ! Capabilities::can_manage_php() ) {
			return new \WP_Error( 'scriptdock_forbidden', Capabilities::php_unavailable_reason(), array( 'status' => 403 ) );
		}
		$snippet = Snippet::get( (int) $request['id'] );
		if ( ! $snippet || 'php' !== $snippet->type ) {
			return new \WP_Error( 'scriptdock_not_found', __( 'PHP snippet not found.', 'scriptdock' ), array( 'status' => 404 ) );
		}
		if ( ! $snippet->is_trusted() ) {
			return new \WP_Error( 'scriptdock_untrusted', __( 'This snippet was changed outside ScriptDock. Review and save it before running it.', 'scriptdock' ), array( 'status' => 409 ) );
		}
		$lint = $snippet->lint();
		if ( $lint ) {
			return new \WP_Error(
				'scriptdock_syntax',
				/* translators: 1: error message, 2: line number */
				sprintf( __( 'PHP syntax error: %1$s on line %2$d.', 'scriptdock' ), $lint['message'], $lint['line'] ),
				array(
					'status' => 422,
					'error'  => $lint,
				)
			);
		}

		$started = microtime( true );
		$output  = $this->trial( $snippet, array(), 'run' );
		if ( is_wp_error( $output ) ) {
			return $output;
		}
		$snippet->clear_error();
		return rest_ensure_response(
			array(
				'ok'     => true,
				'output' => $output,
				'ms'     => (int) round( ( microtime( true ) - $started ) * 1000 ),
			)
		);
	}

	/**
	 * Duplicates a snippet as a switched-off copy.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate_item( $request ) {
		$copy = Snippets::duplicate( (int) $request['id'] );
		if ( is_wp_error( $copy ) ) {
			return $this->with_status( $copy );
		}
		$response = $this->item_response( (int) $copy );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Moves a snippet to the trash, or deletes a trashed snippet for good.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$row = $this->row( (int) $request['id'], true );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$result = $this->remove( $row['post'], (bool) $request['force'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Compiler::rebuild();
		if ( $request['force'] ) {
			return rest_ensure_response(
				array(
					'deleted' => true,
					'id'      => (int) $request['id'],
					'counts'  => $this->counts( $this->rows( false ) ),
				)
			);
		}
		return $this->item_response( (int) $request['id'] );
	}

	/**
	 * Takes a snippet out of the trash. It comes back switched off.
	 *
	 * Not to be confused with restoring a revision, which replaces the code
	 * and so needs the rights to edit it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function untrash_item( $request ) {
		$row = $this->row( (int) $request['id'], true );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$result = $this->untrash( $row['post'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->item_response( (int) $request['id'] );
	}

	/**
	 * Runs one action on several snippets. Each snippet succeeds or fails on
	 * its own; the answer lists both.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk( $request ) {
		$action = $request['action'];
		$ids    = array_values( array_unique( array_map( 'absint', (array) $request['ids'] ) ) );
		$term   = 0;

		if ( 'add_tag' === $action ) {
			$term = $this->tag_id( (string) $request['tag'] );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
		}

		$done   = array();
		$failed = array();
		foreach ( $ids as $id ) {
			$result = $this->bulk_one( $action, $id, $term, $done );
			if ( is_wp_error( $result ) ) {
				$failed[] = array(
					'id'      => $id,
					'title'   => get_the_title( $id ),
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				);
			} else {
				$done[] = $id;
			}
		}

		if ( 'add_tag' !== $action ) {
			Compiler::rebuild();
		}
		return rest_ensure_response(
			array(
				'action' => $action,
				'done'   => $done,
				'failed' => $failed,
				'counts' => $this->counts( $this->rows( false ) ),
			)
		);
	}

	/**
	 * Export file contents for some or all snippets.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function export( $request ) {
		$payload = Import_Export::export( array_map( 'absint', (array) $request['ids'] ) );
		if ( ! $payload['snippets'] ) {
			return $this->not_found();
		}
		return rest_ensure_response(
			array(
				'filename' => Import_Export::filename( $payload ),
				'data'     => $payload,
			)
		);
	}

	/**
	 * Deletes every snippet in the trash.
	 *
	 * @return \WP_REST_Response
	 */
	public function empty_trash() {
		$deleted = array();
		$failed  = array();
		foreach ( $this->rows( true ) as $row ) {
			$result = $this->remove( $row['post'], true );
			if ( is_wp_error( $result ) ) {
				$failed[] = array(
					'id'      => $row['snippet']->id,
					'title'   => $row['snippet']->title,
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				);
			} else {
				$deleted[] = $row['snippet']->id;
			}
		}
		Compiler::rebuild();
		return rest_ensure_response(
			array(
				'deleted' => $deleted,
				'failed'  => $failed,
				'counts'  => $this->counts( $this->rows( false ) ),
			)
		);
	}

	/**
	 * One snippet of a bulk action.
	 *
	 * @param string $action Action.
	 * @param int    $id     Snippet ID.
	 * @param int    $term   Tag ID for add_tag.
	 * @param int[]  $done   Snippets already done, for the crash answer.
	 * @return true|\WP_Error
	 */
	private function bulk_one( $action, $id, $term, array $done ) {
		if ( in_array( $action, array( 'untrash', 'delete', 'trash' ), true ) ) {
			$row = $this->row( $id, true );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
			if ( 'untrash' === $action ) {
				return $this->untrash( $row['post'] );
			}
			return $this->remove( $row['post'], 'delete' === $action );
		}

		$snippet = Snippet::get( $id );
		if ( ! $snippet ) {
			return $this->not_found();
		}
		switch ( $action ) {
			case 'activate':
				return $snippet->active ? true : $this->activate( $snippet, $done );
			case 'approve':
				return $this->approve( $snippet, $done );
			case 'deactivate':
				return $snippet->active ? Snippets::set_active( $id, false ) : true;
			case 'add_tag':
				$result = wp_add_object_terms( $id, $term, Post_Type::TAXONOMY );
				return is_wp_error( $result ) ? $result : true;
		}
		return new \WP_Error( 'scriptdock_bad_action', __( 'Unknown action.', 'scriptdock' ), array( 'status' => 400 ) );
	}

	/**
	 * Switches a snippet on after its checks and, when needed, a trial run.
	 *
	 * @param Snippet $snippet Snippet.
	 * @param int[]   $done    Snippets a bulk action already switched on.
	 * @return true|\WP_Error
	 */
	private function activate( Snippet $snippet, array $done = array() ) {
		$error = Snippets::activation_error( $snippet );
		if ( $error ) {
			return $error;
		}
		if ( Snippets::needs_test_run( $snippet ) ) {
			$trial = $this->trial( $snippet, $done );
			if ( is_wp_error( $trial ) ) {
				return $trial;
			}
		}
		// Hooks the trial registered may print while the post is saved.
		ob_start();
		$result = Snippets::set_active( $snippet->id, true );
		ob_end_clean();
		return $result;
	}

	/**
	 * Approves a changed snippet after its checks and, when it would start
	 * running again, a trial run.
	 *
	 * @param Snippet $snippet Snippet.
	 * @param int[]   $done    Snippets a bulk action already approved.
	 * @return true|\WP_Error
	 */
	private function approve( Snippet $snippet, array $done = array() ) {
		if ( $snippet->is_trusted() ) {
			return true;
		}
		$error = Snippets::approval_error( $snippet );
		if ( $error ) {
			return $error;
		}
		if ( $snippet->active && Snippets::needs_test_run( $snippet ) ) {
			$trial = $this->trial( $snippet, $done );
			if ( is_wp_error( $trial ) ) {
				return $trial;
			}
		}
		Snippets::approve( $snippet );
		return true;
	}

	/**
	 * Runs PHP once in this request, the way the editor tries a snippet on
	 * save: as the admin would run it. A fatal error ends the request with a
	 * JSON error from Error_Handler; an error the snippet throws comes back
	 * as a WP_Error.
	 *
	 * @param Snippet $snippet Snippet.
	 * @param int[]   $done    Snippets a bulk action already switched on.
	 * @param string  $why     activate (a trial before switching on) or run.
	 * @return string|\WP_Error What the snippet printed, or the error.
	 */
	private function trial( Snippet $snippet, array $done = array(), $why = 'activate' ) {
		$data = array( 'done' => $done );
		if ( 'run' === $why ) {
			$data['message'] = __( 'The snippet caused a fatal error while running.', 'scriptdock' );
		}

		$screen = $this->enter_admin_context();
		Error_Handler::start_json_test( $snippet->id, $data );
		$output = Runtime::run_source( $snippet->id, '<?php ' . $snippet->code, array(), true );
		Error_Handler::end_test();
		$this->leave_admin_context( $screen );

		if ( Runtime::$last_error ) {
			$message = 'run' === $why
				/* translators: %s: error message */
				? sprintf( __( 'The snippet threw an error: %s', 'scriptdock' ), Runtime::$last_error['message'] )
				/* translators: %s: error message */
				: sprintf( __( 'The snippet threw an error when tested and was left switched off: %s', 'scriptdock' ), Runtime::$last_error['message'] );
			return new \WP_Error(
				'scriptdock_test_error',
				$message,
				array(
					'status'     => 422,
					'snippet_id' => $snippet->id,
					'error'      => array(
						'message' => Runtime::$last_error['message'],
						'line'    => Runtime::$last_error['line'],
					),
					'done'       => $done,
				)
			);
		}
		return $output;
	}

	/**
	 * Makes is_admin() true for a trial run, as it is when the editor tries
	 * a snippet on save and when admin snippets really run.
	 *
	 * @return mixed The screen that was set before, to put back.
	 */
	private function enter_admin_context() {
		$previous = isset( $GLOBALS['current_screen'] ) ? $GLOBALS['current_screen'] : null;
		if ( ! class_exists( 'WP_Screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}
		$GLOBALS['current_screen'] = \WP_Screen::get( 'scriptdock-trial' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored right after the trial run.
		return $previous;
	}

	/**
	 * Puts back the screen from before the trial run.
	 *
	 * @param mixed $previous Previous screen.
	 */
	private function leave_admin_context( $previous ) {
		if ( null === $previous ) {
			unset( $GLOBALS['current_screen'] );
		} else {
			$GLOBALS['current_screen'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the value from before the trial run.
		}
	}

	/**
	 * Trashes a snippet, or deletes a trashed one permanently. Open to every
	 * snippet manager: it takes code away.
	 *
	 * @param \WP_Post $post  Snippet post.
	 * @param bool     $force Delete permanently.
	 * @return true|\WP_Error
	 */
	private function remove( \WP_Post $post, $force ) {
		if ( $force ) {
			if ( 'trash' !== $post->post_status ) {
				return new \WP_Error( 'scriptdock_not_trashed', __( 'Move the snippet to the trash before deleting it permanently.', 'scriptdock' ), array( 'status' => 409 ) );
			}
			return wp_delete_post( $post->ID, true ) ? true : new \WP_Error( 'scriptdock_delete_failed', __( 'The snippet could not be deleted.', 'scriptdock' ), array( 'status' => 500 ) );
		}
		if ( 'trash' === $post->post_status ) {
			return true;
		}
		return wp_trash_post( $post->ID ) ? true : new \WP_Error( 'scriptdock_trash_failed', __( 'The snippet could not be moved to the trash.', 'scriptdock' ), array( 'status' => 500 ) );
	}

	/**
	 * Takes a snippet out of the trash, switched off. Open to every snippet
	 * manager: nothing runs until someone allowed to switches it on.
	 *
	 * @param \WP_Post $post Snippet post.
	 * @return true|\WP_Error
	 */
	private function untrash( \WP_Post $post ) {
		if ( 'trash' !== $post->post_status ) {
			return true;
		}
		return wp_untrash_post( $post->ID ) ? true : new \WP_Error( 'scriptdock_untrash_failed', __( 'The snippet could not be taken out of the trash.', 'scriptdock' ), array( 'status' => 500 ) );
	}

	/**
	 * Finds or creates a tag by name.
	 *
	 * @param string $name Tag name.
	 * @return int|\WP_Error Term ID.
	 */
	private function tag_id( $name ) {
		$name = trim( sanitize_text_field( $name ) );
		if ( '' === $name ) {
			return new \WP_Error( 'scriptdock_tag_missing', __( 'Enter a tag name.', 'scriptdock' ), array( 'status' => 400 ) );
		}
		$term = term_exists( $name, Post_Type::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( $name, Post_Type::TAXONOMY );
		}
		if ( is_wp_error( $term ) ) {
			return $this->with_status( $term );
		}
		return (int) ( is_array( $term ) ? $term['term_id'] : $term );
	}

	/**
	 * Response for one snippet after a change: the snippet and fresh counts.
	 *
	 * @param int $id Snippet ID.
	 * @return \WP_REST_Response
	 */
	private function item_response( $id ) {
		$row = $this->row( $id, true );
		return rest_ensure_response(
			array(
				'item'   => is_wp_error( $row ) ? null : $this->summary( $row ),
				'counts' => $this->counts( $this->rows( false ) ),
			)
		);
	}

	/**
	 * Loads every snippet in the trash, or every snippet outside it.
	 *
	 * @param bool $trash Whether to load the trash.
	 * @return array Rows: snippet, post, terms, state, modified, created.
	 */
	private function rows( $trash ) {
		$posts = get_posts(
			array(
				'post_type'   => Post_Type::NAME,
				'post_status' => $trash ? 'trash' : array( 'publish', 'draft' ),
				'numberposts' => -1,
				'orderby'     => 'modified',
				'order'       => 'DESC',
			)
		);
		return array_map( array( $this, 'make_row' ), $posts );
	}

	/**
	 * Loads one snippet.
	 *
	 * @param int  $id          Snippet ID.
	 * @param bool $allow_trash Whether a trashed snippet counts.
	 * @return array|\WP_Error Row.
	 */
	private function row( $id, $allow_trash = false ) {
		$post = get_post( (int) $id );
		if ( ! $post || Post_Type::NAME !== $post->post_type || ( 'trash' === $post->post_status && ! $allow_trash ) ) {
			return $this->not_found();
		}
		return $this->make_row( $post );
	}

	/**
	 * A snippet with its post, tags and computed state.
	 *
	 * @param \WP_Post $post Snippet post.
	 * @return array
	 */
	private function make_row( \WP_Post $post ) {
		$snippet = Snippet::from_post( $post );
		$terms   = get_the_terms( $post->ID, Post_Type::TAXONOMY );
		return array(
			'snippet'  => $snippet,
			'post'     => $post,
			'terms'    => is_array( $terms ) ? $terms : array(),
			'state'    => $this->state( $snippet ),
			// Timestamps from the local dates: new drafts store no GMT dates.
			'modified' => (int) get_post_timestamp( $post, 'modified' ),
			'created'  => (int) get_post_timestamp( $post ),
		);
	}

	/**
	 * What a snippet is doing: trusted, running, schedule, error.
	 *
	 * Running mirrors what the compiler lets through: switched on, trusted,
	 * inside its schedule, at a placement that runs by itself, and not PHP
	 * while PHP is disabled.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return array
	 */
	private function state( Snippet $snippet ) {
		$trusted = $snippet->is_trusted();
		$now     = time();
		$start   = Compiler::to_timestamp( $snippet->schedule['start'] );
		$end     = Compiler::to_timestamp( $snippet->schedule['end'] );

		$schedule = 'none';
		if ( $end && $now >= $end ) {
			$schedule = 'expired';
		} elseif ( $start && $now < $start ) {
			$schedule = 'scheduled';
		} elseif ( $start || $end ) {
			$schedule = 'live';
		}

		$runnable = 'on_demand' !== $snippet->location
			&& Registry::type_allowed_at( $snippet->type, $snippet->location )
			&& ! ( 'custom_hook' === $snippet->location && empty( $snippet->location_args['hook'] ) )
			&& ! ( $snippet->is_php() && ! Capabilities::php_enabled() );

		return array(
			'trusted'  => $trusted,
			'running'  => $snippet->active && $trusted && $runnable && in_array( $schedule, array( 'none', 'live' ), true ),
			'schedule' => $schedule,
			'start'    => $start,
			'end'      => $end,
			'error'    => ! empty( $snippet->last_error['message'] ),
		);
	}

	/**
	 * Counts for the status views, plus running for the overview.
	 *
	 * @param array $rows Rows outside the trash.
	 * @return array
	 */
	private function counts( array $rows ) {
		$counts = array(
			'all'      => count( $rows ),
			'active'   => 0,
			'inactive' => 0,
			'review'   => 0,
			'error'    => 0,
			'running'  => 0,
			'trash'    => (int) wp_count_posts( Post_Type::NAME )->trash,
		);
		foreach ( $rows as $row ) {
			foreach ( array( 'active', 'inactive', 'review', 'error' ) as $view ) {
				if ( $this->in_view( $row, $view ) ) {
					++$counts[ $view ];
				}
			}
			if ( $row['state']['running'] ) {
				++$counts['running'];
			}
		}
		return $counts;
	}

	/**
	 * Whether a row belongs to a status view. Active and inactive split the
	 * whole list; needs review and errors cut across them.
	 *
	 * @param array  $row  Row.
	 * @param string $view View.
	 * @return bool
	 */
	private function in_view( array $row, $view ) {
		$state = $row['state'];
		switch ( $view ) {
			case 'active':
				return $row['snippet']->active;
			case 'inactive':
				return ! $row['snippet']->active;
			case 'review':
				return ! $state['trusted'];
			case 'error':
				return $state['error'];
		}
		return true;
	}

	/**
	 * Months that have snippets, newest first, for the month filter.
	 *
	 * @param array $rows Rows.
	 * @return array value (YYYYMM) and label.
	 */
	private function months( array $rows ) {
		global $wp_locale;
		$months = array();
		foreach ( $rows as $row ) {
			$months[ mysql2date( 'Ym', $row['post']->post_date ) ] = true;
		}
		krsort( $months );
		$list = array();
		foreach ( array_keys( $months ) as $value ) {
			$value  = (string) $value;
			$list[] = array(
				'value' => $value,
				/* translators: 1: month name, 2: four-digit year. */
				'label' => sprintf( __( '%1$s %2$d', 'scriptdock' ), $wp_locale->get_month( substr( $value, 4, 2 ) ), (int) substr( $value, 0, 4 ) ),
			);
		}
		return $list;
	}

	/**
	 * Whether a row passes the list's filters.
	 *
	 * @param array            $row     Row.
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	private function matches( array $row, $request ) {
		$snippet = $row['snippet'];

		if ( 'trash' !== $request['view'] && ! $this->in_view( $row, $request['view'] ) ) {
			return false;
		}
		if ( $request['type'] && ! in_array( $snippet->type, (array) $request['type'], true ) ) {
			return false;
		}
		if ( $request['location'] && ! in_array( $snippet->location, (array) $request['location'], true ) ) {
			return false;
		}
		if ( $request['tag'] && ! array_intersect( array_map( 'intval', (array) $request['tag'] ), wp_list_pluck( $row['terms'], 'term_id' ) ) ) {
			return false;
		}
		if ( $request['targeting'] && ! array_intersect( (array) $request['targeting'], $this->targeting_kinds( $row ) ) ) {
			return false;
		}
		if ( '' !== $request['month'] && mysql2date( 'Ym', $row['post']->post_date ) !== $request['month'] ) {
			return false;
		}

		$search = trim( (string) $request['search'] );
		if ( '' !== $search ) {
			$haystack = implode( "\n", array_merge( array( $snippet->title, $snippet->description, $snippet->code ), wp_list_pluck( $row['terms'], 'name' ) ) );
			$found    = function_exists( 'mb_stripos' ) ? mb_stripos( $haystack, $search ) : stripos( $haystack, $search );
			if ( false === $found ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Targeting kinds of a snippet, for the targeting filter.
	 *
	 * @param array $row Row.
	 * @return string[]
	 */
	private function targeting_kinds( array $row ) {
		$snippet = $row['snippet'];
		$kinds   = array();
		if ( Conditions::is_active( $snippet->conditions ) ) {
			$kinds[] = 'conditional';
		}
		if ( $row['state']['start'] || $row['state']['end'] ) {
			$kinds[] = 'scheduled';
		}
		if ( 'shortcode' === $snippet->location ) {
			$kinds[] = 'shortcode';
		}
		if ( ! $kinds ) {
			$kinds[] = 'everywhere';
		}
		return $kinds;
	}

	/**
	 * Sorts rows in place. Ties fall back to title, then ID.
	 *
	 * @param array       $rows    Rows.
	 * @param string      $orderby Sort key.
	 * @param string|null $order   asc or desc; dates default to desc.
	 */
	private function sort( array &$rows, $orderby, $order ) {
		if ( ! $order ) {
			$order = in_array( $orderby, array( 'modified', 'created' ), true ) ? 'desc' : 'asc';
		}
		$labels = Registry::type_labels();
		$sign   = 'desc' === $order ? -1 : 1;
		usort(
			$rows,
			static function ( $a, $b ) use ( $orderby, $sign, $labels ) {
				switch ( $orderby ) {
					case 'created':
						$compare = $a['created'] - $b['created'];
						break;
					case 'title':
						$compare = strnatcasecmp( $a['snippet']->title, $b['snippet']->title );
						break;
					case 'priority':
						$compare = $a['snippet']->priority - $b['snippet']->priority;
						break;
					case 'type':
						$compare = strnatcasecmp( $labels[ $a['snippet']->type ], $labels[ $b['snippet']->type ] );
						break;
					default:
						$compare = $a['modified'] - $b['modified'];
				}
				if ( 0 !== $compare ) {
					return $sign * ( $compare < 0 ? -1 : 1 );
				}
				$title = strnatcasecmp( $a['snippet']->title, $b['snippet']->title );
				return 0 !== $title ? $title : $a['snippet']->id - $b['snippet']->id;
			}
		);
	}

	/**
	 * A snippet as the list shows it.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function summary( array $row ) {
		$snippet  = $row['snippet'];
		$post     = $row['post'];
		$state    = $row['state'];
		$can_edit = Capabilities::can_edit_type( $snippet->type );
		$args     = $snippet->location_args;
		$location = isset( $this->locations()[ $snippet->location ] ) ? $this->locations()[ $snippet->location ] : null;

		return array(
			'id'            => $snippet->id,
			'title'         => $snippet->title,
			'notes'         => wp_html_excerpt( $snippet->description, 200, '…' ),
			'type'          => $snippet->type,
			'status'        => $post->post_status,
			'active'        => $snippet->active,
			'trusted'       => $state['trusted'],
			'paused'        => $snippet->active && ! $state['trusted'],
			'running'       => $state['running'],
			'can_edit'       => $can_edit,
			'can_activate'   => $can_edit,
			'can_deactivate' => true,
			'locked_reason'  => $can_edit ? '' : Capabilities::php_unavailable_reason(),
			'placement'     => array(
				'key'       => $snippet->location,
				'label'     => Registry::location_label( $snippet->location ),
				'short'     => Registry::short_label( $snippet->location, $args ),
				'group'     => $location ? $location['group'] : '',
				'hook'      => isset( $args['hook'] ) ? $args['hook'] : '',
				'action'    => isset( Registry::ACTION_LOCATIONS[ $snippet->location ] ) ? Registry::ACTION_LOCATIONS[ $snippet->location ][0] : '',
				'paragraph' => isset( $args['paragraph'] ) ? (int) $args['paragraph'] : null,
				'every'     => isset( $args['every'] ) ? (int) $args['every'] : null,
			),
			'shortcode'     => 'shortcode' === $snippet->location ? sprintf( '[scriptdock id="%d"]', $snippet->id ) : '',
			'targeting'     => Targeting_Summary::describe( $snippet ),
			'schedule'      => array(
				'start' => $snippet->schedule['start'],
				'end'   => $snippet->schedule['end'],
				'state' => $state['schedule'],
			),
			'badges'        => $this->badges( $snippet, $state ),
			'error'         => $state['error'] ? $this->error( $snippet->last_error ) : null,
			'priority'      => (int) $snippet->priority,
			'tags'          => array_map(
				static function ( \WP_Term $term ) {
					return array(
						'id'   => (int) $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					);
				},
				$row['terms']
			),
			'modified'      => $this->modified( $post, $row['modified'] ),
			'created_gmt'   => $row['created'] ? gmdate( 'c', $row['created'] ) : '',
			'trashed'       => 'trash' === $post->post_status ? $this->trashed( $post ) : null,
			'review'        => $state['trusted'] ? null : Changes::describe( $snippet ),
			'edit_url'      => Snippets::edit_url( $snippet->id ),
		);
	}

	/**
	 * A snippet as the quick view and editor need it: the list fields plus
	 * code, raw settings, signature status and recent revisions.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function full( array $row ) {
		$snippet   = $row['snippet'];
		$revisions = wp_get_post_revisions( $snippet->id, array( 'posts_per_page' => -1 ) );
		$latest    = array();
		foreach ( array_slice( $revisions, 0, 5 ) as $revision ) {
			$user     = get_userdata( (int) $revision->post_author );
			$time     = (int) get_post_timestamp( $revision, 'modified' );
			$latest[] = array(
				'id'       => (int) $revision->ID,
				'date_gmt' => $time ? gmdate( 'c', $time ) : '',
				'label'    => $this->date_label( $time ),
				'author'   => $user ? $user->display_name : '',
				'url'      => admin_url( 'revision.php?revision=' . (int) $revision->ID ),
			);
		}

		if ( ! $snippet->id ) {
			$signature = 'new';
		} elseif ( ! Signer::enabled() ) {
			$signature = 'off';
		} else {
			$signature = $row['state']['trusted'] ? 'valid' : 'changed';
		}

		return array_merge(
			$this->summary( $row ),
			array(
				'notes'         => $snippet->description,
				'code'          => $snippet->code,
				'location_args' => $snippet->location_args,
				'conditions'    => $snippet->conditions,
				'options'       => $snippet->options,
				'source'        => $snippet->source,
				'signature'     => $signature,
				'consent'       => array(
					'category' => $snippet->options['consent'],
					// The WP Consent API plugin; without it consent-gated code loads right away.
					'api'      => function_exists( 'wp_has_consent' ),
				),
				'revisions'     => array(
					'count'  => count( $revisions ),
					'latest' => $latest,
				),
				'file_output'   => $this->file_output( $snippet ),
				'usage'         => 'shortcode' === $snippet->location ? $this->shortcode_usage( $snippet->id ) : null,
			)
		);
	}

	/**
	 * Whether a snippet can load as a cached file, and why not when it
	 * cannot: only CSS and JavaScript, only in the head, footer, admin, login
	 * and block editor, and only while files are on in Settings.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return array possible, reason.
	 */
	private function file_output( Snippet $snippet ) {
		$reason = '';
		if ( ! in_array( $snippet->type, array( 'css', 'js' ), true ) ) {
			$reason = __( 'Only CSS and JavaScript can load as a file.', 'scriptdock' );
		} elseif ( ! in_array( $snippet->location, Registry::FILE_LOCATIONS, true ) ) {
			$reason = __( 'This placement prints into the page, so it cannot use a file. Files work in the site header and footer, the admin, the login page and the block editor.', 'scriptdock' );
		} elseif ( ! Settings::get( 'asset_files' ) ) {
			$reason = __( 'Loading as files is turned off in Settings.', 'scriptdock' );
		}
		return array(
			'possible' => '' === $reason,
			'reason'   => $reason,
		);
	}

	/**
	 * How many posts, pages and other content use a snippet's shortcode or
	 * block. A quick count of the saved content, not of what renders.
	 *
	 * @param int $id Snippet ID.
	 * @return array count.
	 */
	private function shortcode_usage( $id ) {
		global $wpdb;
		$id       = (int) $id;
		$patterns = array(
			'[scriptdock id="' . $id . '"',
			"[scriptdock id='" . $id . "'",
			'[scriptdock id=' . $id . ']',
			'[scriptdock id=' . $id . ' ',
			'"snippetId":' . $id . '}',
			'"snippetId":' . $id . ',',
		);
		$likes    = implode( ' OR ', array_fill( 0, count( $patterns ), 'post_content LIKE %s' ) );
		$values   = array_map(
			static function ( $pattern ) use ( $wpdb ) {
				return '%' . $wpdb->esc_like( $pattern ) . '%';
			},
			$patterns
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A count over post content that no cache holds; $likes is only placeholders.
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ( 'publish', 'future', 'private' ) AND post_type NOT IN ( 'revision', %s ) AND ( {$likes} )", array_merge( array( Post_Type::NAME ), $values ) ) );
		return array( 'count' => $count );
	}

	/**
	 * Status badges, most important first: needs review, error, test mode,
	 * scheduled or expired, consent, delayed loading, cached file and
	 * conditional. The list shows the first and counts the rest.
	 *
	 * @param Snippet $snippet Snippet.
	 * @param array   $state   State.
	 * @return array key, label, tone, detail.
	 */
	private function badges( Snippet $snippet, array $state ) {
		$badges = array();
		if ( ! $state['trusted'] ) {
			$badges[] = $this->badge( 'review', __( 'Needs review', 'scriptdock' ), 'danger', __( 'Changed outside ScriptDock. Review the code before approving it.', 'scriptdock' ) );
		}
		if ( $state['error'] ) {
			$badges[] = $this->badge( 'error', __( 'Error', 'scriptdock' ), 'danger', $snippet->last_error['message'] );
		}
		if ( ! empty( $snippet->options['test_mode'] ) ) {
			$badges[] = $this->badge( 'test_mode', __( 'Test mode', 'scriptdock' ), 'info', __( 'Only administrators see it.', 'scriptdock' ) );
		}
		if ( 'scheduled' === $state['schedule'] ) {
			/* translators: %s: start date. */
			$badges[] = $this->badge( 'scheduled', __( 'Scheduled', 'scriptdock' ), 'info', sprintf( __( 'Starts %s', 'scriptdock' ), Targeting_Summary::date_label( $state['start'] ) ) );
		} elseif ( 'expired' === $state['schedule'] ) {
			/* translators: %s: end date. */
			$badges[] = $this->badge( 'expired', __( 'Expired', 'scriptdock' ), 'warning', sprintf( __( 'Ended %s', 'scriptdock' ), Targeting_Summary::date_label( $state['end'] ) ) );
		}
		$delays = array( 'js', 'html' );
		if ( ! empty( $snippet->options['consent'] ) && in_array( $snippet->type, $delays, true ) ) {
			$labels   = Snippet::consent_labels();
			$category = isset( $labels[ $snippet->options['consent'] ] ) ? $labels[ $snippet->options['consent'] ] : $snippet->options['consent'];
			/* translators: %s: consent category, for example "Statistics". */
			$badges[] = $this->badge( 'consent', $category, 'info', sprintf( __( 'Waits for %s consent.', 'scriptdock' ), $category ) );
		}
		$strategies = array(
			'interaction' => array( __( 'On interaction', 'scriptdock' ), __( 'Loads after the first scroll, tap, click or key press.', 'scriptdock' ) ),
			'idle'        => array( __( 'When idle', 'scriptdock' ), __( 'Loads once the page has finished loading and the browser is idle.', 'scriptdock' ) ),
		);
		if ( isset( $strategies[ $snippet->options['strategy'] ] ) && in_array( $snippet->type, $delays, true ) ) {
			list( $label, $detail ) = $strategies[ $snippet->options['strategy'] ];
			$badges[]               = $this->badge( 'strategy', $label, 'neutral', $detail );
		}
		$compiled = Compiler::get();
		if ( ! empty( $compiled['snippets'][ $snippet->id ]['file'] ) ) {
			$badges[] = $this->badge( 'file', __( 'Cached file', 'scriptdock' ), 'neutral', __( 'Loads from a cached file.', 'scriptdock' ) );
		}
		if ( Conditions::is_active( $snippet->conditions ) ) {
			$badges[] = $this->badge( 'conditional', __( 'Conditional', 'scriptdock' ), 'info', __( 'Runs only where its rules match.', 'scriptdock' ) );
		}
		return $badges;
	}

	/**
	 * A badge.
	 *
	 * @param string $key    Key.
	 * @param string $label  Label.
	 * @param string $tone   danger, warning, info or neutral.
	 * @param string $detail Tooltip text.
	 * @return array
	 */
	private function badge( $key, $label, $tone, $detail ) {
		return array(
			'key'    => $key,
			'label'  => $label,
			'tone'   => $tone,
			'detail' => (string) $detail,
		);
	}

	/**
	 * A stored error for the response.
	 *
	 * @param array $error Stored error.
	 * @return array
	 */
	private function error( array $error ) {
		$time = isset( $error['time'] ) ? (int) $error['time'] : 0;
		return array(
			'message' => (string) $error['message'],
			'line'    => isset( $error['line'] ) ? (int) $error['line'] : 0,
			'fatal'   => ! empty( $error['fatal'] ),
			'url'     => isset( $error['url'] ) ? (string) $error['url'] : '',
			'time'    => $time ? gmdate( 'c', $time ) : '',
			'label'   => $this->date_label( $time ),
			/* translators: %s: time difference, for example "25 mins". */
			'human'   => $time ? sprintf( __( '%s ago', 'scriptdock' ), human_time_diff( $time ) ) : '',
			'emailed' => isset( $error['emailed'] ) ? (string) $error['emailed'] : '',
		);
	}

	/**
	 * When a snippet went to the trash.
	 *
	 * @param \WP_Post $post Snippet post.
	 * @return array gmt, human.
	 */
	private function trashed( \WP_Post $post ) {
		$time = (int) get_post_meta( $post->ID, '_wp_trash_meta_time', true );
		return array(
			'gmt'   => $time ? gmdate( 'c', $time ) : '',
			/* translators: %s: time difference, for example "4 days". */
			'human' => $time ? sprintf( __( '%s ago', 'scriptdock' ), human_time_diff( $time ) ) : '',
		);
	}

	/**
	 * When and by whom a snippet last changed.
	 *
	 * @param \WP_Post $post Snippet post.
	 * @param int      $time Modified timestamp.
	 * @return array gmt, label, human, author.
	 */
	private function modified( \WP_Post $post, $time ) {
		$user_id = (int) get_post_meta( $post->ID, '_edit_last', true );
		$user    = get_userdata( $user_id ? $user_id : (int) $post->post_author );
		return array(
			'gmt'    => $time ? gmdate( 'c', $time ) : '',
			'label'  => $this->date_label( $time ),
			/* translators: %s: time difference, for example "5 mins". */
			'human'  => $time ? sprintf( __( '%s ago', 'scriptdock' ), human_time_diff( $time ) ) : '',
			'author' => $user ? $user->display_name : '',
		);
	}

	/**
	 * A time in the site's date and time format.
	 *
	 * @param int $time Timestamp.
	 * @return string
	 */
	private function date_label( $time ) {
		return $time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $time ) : '';
	}

	/**
	 * Placement definitions.
	 *
	 * @return array
	 */
	private function locations() {
		if ( null === $this->locations ) {
			$this->locations = Registry::locations();
		}
		return $this->locations;
	}

	/**
	 * Not found error.
	 *
	 * @return \WP_Error
	 */
	private function not_found() {
		return new \WP_Error( 'scriptdock_not_found', __( 'Snippet not found.', 'scriptdock' ), array( 'status' => 404 ) );
	}

	/**
	 * Gives an error from a helper an HTTP status the client can act on.
	 *
	 * @param \WP_Error $error Error.
	 * @return \WP_Error
	 */
	private function with_status( \WP_Error $error ) {
		$data = $error->get_error_data();
		if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
			$statuses = array(
				'scriptdock_not_found' => 404,
				'scriptdock_forbidden' => 403,
				'term_exists'          => 409,
			);
			$code     = $error->get_error_code();
			$error->add_data( array_merge( is_array( $data ) ? $data : array(), array( 'status' => isset( $statuses[ $code ] ) ? $statuses[ $code ] : 400 ) ), $code );
		}
		return $error;
	}

	/**
	 * Schema of a snippet in the list.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$string  = array( 'type' => 'string' );
		$boolean = array( 'type' => 'boolean' );
		$object  = array( 'type' => 'object' );

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'scriptdock-snippet',
			'type'       => 'object',
			'properties' => array(
				'id'            => array( 'type' => 'integer' ),
				'title'         => $string,
				'notes'         => $string,
				'type'          => array(
					'type' => 'string',
					'enum' => Registry::TYPES,
				),
				'status'        => array(
					'type' => 'string',
					'enum' => array( 'publish', 'draft', 'trash' ),
				),
				'active'        => $boolean,
				'trusted'       => $boolean,
				'paused'        => $boolean,
				'running'       => $boolean,
				'can_edit'       => $boolean,
				'can_activate'   => $boolean,
				'can_deactivate' => $boolean,
				'locked_reason'  => $string,
				'placement'     => $object,
				'shortcode'     => $string,
				'targeting'     => $object,
				'schedule'      => $object,
				'badges'        => array( 'type' => 'array' ),
				'error'         => array( 'type' => array( 'object', 'null' ) ),
				'priority'      => array( 'type' => 'integer' ),
				'tags'          => array( 'type' => 'array' ),
				'modified'      => $object,
				'created_gmt'   => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'edit_url'      => array(
					'type'   => 'string',
					'format' => 'uri',
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
