<?php
/**
 * REST: snippet tags.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/tags: list with counts, create, rename, delete and merge,
 * for the Manage tags modal and the editor's tag input.
 *
 * Tags are only labels: they are not part of a snippet's signature and do
 * not change what runs, so none of this re-signs or rebuilds anything.
 */
final class Tags_Controller extends \WP_REST_Controller {

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'tags';
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		$manage = array( Rest::class, 'can_manage' );
		$name   = array(
			'description' => __( 'Tag name.', 'scriptdock' ),
			'type'        => 'string',
			'minLength'   => 1,
			'maxLength'   => 190,
			'required'    => true,
		);
		$id     = array(
			'id' => array(
				'description' => __( 'Tag ID.', 'scriptdock' ),
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
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $manage,
					'args'                => array( 'name' => $name ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				'args' => $id,
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $manage,
					'args'                => array( 'name' => $name ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/merge',
			array(
				'args' => $id,
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'merge' ),
					'permission_callback' => $manage,
					'args'                => array(
						'into' => array(
							'description' => __( 'The tag that takes over this tag’s snippets.', 'scriptdock' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Every tag, by name, with how many snippets outside the trash use it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by the parent signature.
		return rest_ensure_response( $this->all() );
	}

	/**
	 * Creates a tag.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$name = $this->clean_name( $request['name'] );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		$existing = term_exists( $name, Post_Type::TAXONOMY );
		if ( $existing ) {
			return new \WP_Error(
				'scriptdock_tag_exists',
				__( 'A tag with that name already exists.', 'scriptdock' ),
				array(
					'status' => 409,
					'id'     => (int) ( is_array( $existing ) ? $existing['term_id'] : $existing ),
				)
			);
		}
		$term = wp_insert_term( $name, Post_Type::TAXONOMY );
		if ( is_wp_error( $term ) ) {
			return $this->failed( $term );
		}
		$response = rest_ensure_response( $this->one( (int) $term['term_id'] ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Renames a tag. Its slug stays, so saved filters keep working.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$term = $this->term( (int) $request['id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$name = $this->clean_name( $request['name'] );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		$other = get_term_by( 'name', $name, Post_Type::TAXONOMY );
		if ( $other && (int) $other->term_id !== (int) $term->term_id ) {
			return new \WP_Error(
				'scriptdock_tag_exists',
				__( 'Another tag already has that name. Merge the two instead.', 'scriptdock' ),
				array(
					'status' => 409,
					'id'     => (int) $other->term_id,
				)
			);
		}
		$result = wp_update_term( $term->term_id, Post_Type::TAXONOMY, array( 'name' => $name ) );
		if ( is_wp_error( $result ) ) {
			return $this->failed( $result );
		}
		return rest_ensure_response( $this->one( (int) $term->term_id ) );
	}

	/**
	 * Deletes a tag. Its snippets stay, untagged.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$term = $this->term( (int) $request['id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$result = wp_delete_term( $term->term_id, Post_Type::TAXONOMY );
		if ( is_wp_error( $result ) ) {
			return $this->failed( $result );
		}
		if ( ! $result ) {
			return new \WP_Error( 'scriptdock_tag_delete_failed', __( 'The tag could not be deleted.', 'scriptdock' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => (int) $term->term_id,
			)
		);
	}

	/**
	 * Moves a tag's snippets to another tag, then deletes it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function merge( $request ) {
		$from = $this->term( (int) $request['id'] );
		$into = $this->term( (int) $request['into'] );
		if ( is_wp_error( $from ) ) {
			return $from;
		}
		if ( is_wp_error( $into ) ) {
			return $into;
		}
		if ( (int) $from->term_id === (int) $into->term_id ) {
			return new \WP_Error( 'scriptdock_tag_same', __( 'Choose a different tag to merge into.', 'scriptdock' ), array( 'status' => 400 ) );
		}

		$objects = get_objects_in_term( (int) $from->term_id, Post_Type::TAXONOMY );
		if ( is_wp_error( $objects ) ) {
			return $this->failed( $objects );
		}
		foreach ( $objects as $object_id ) {
			wp_add_object_terms( (int) $object_id, (int) $into->term_id, Post_Type::TAXONOMY );
		}
		wp_delete_term( $from->term_id, Post_Type::TAXONOMY );

		return rest_ensure_response(
			array(
				'merged' => (int) $from->term_id,
				'into'   => $this->one( (int) $into->term_id ),
			)
		);
	}

	/**
	 * Every tag with counts.
	 *
	 * Counted here rather than read from the term, because WordPress only
	 * counts published posts and switched-off snippets are drafts.
	 *
	 * @return array
	 */
	private function all() {
		$terms = get_terms(
			array(
				'taxonomy'   => Post_Type::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$counts = array();
		$ids    = get_posts(
			array(
				'post_type'   => Post_Type::NAME,
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		if ( $ids ) {
			$pairs = wp_get_object_terms( $ids, Post_Type::TAXONOMY, array( 'fields' => 'all_with_object_id' ) );
			if ( is_array( $pairs ) ) {
				foreach ( $pairs as $pair ) {
					$counts[ $pair->term_id ] = isset( $counts[ $pair->term_id ] ) ? $counts[ $pair->term_id ] + 1 : 1;
				}
			}
		}

		$list = array();
		foreach ( $terms as $term ) {
			$list[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => isset( $counts[ $term->term_id ] ) ? $counts[ $term->term_id ] : 0,
			);
		}
		return $list;
	}

	/**
	 * One tag with its count.
	 *
	 * @param int $id Term ID.
	 * @return array|null
	 */
	private function one( $id ) {
		foreach ( $this->all() as $tag ) {
			if ( $tag['id'] === $id ) {
				return $tag;
			}
		}
		return null;
	}

	/**
	 * Loads a tag.
	 *
	 * @param int $id Term ID.
	 * @return \WP_Term|\WP_Error
	 */
	private function term( $id ) {
		$term = get_term( $id, Post_Type::TAXONOMY );
		if ( ! $term instanceof \WP_Term ) {
			return new \WP_Error( 'scriptdock_tag_not_found', __( 'Tag not found.', 'scriptdock' ), array( 'status' => 404 ) );
		}
		return $term;
	}

	/**
	 * A tag name, trimmed and checked.
	 *
	 * @param string $name Raw name.
	 * @return string|\WP_Error
	 */
	private function clean_name( $name ) {
		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name ) {
			return new \WP_Error( 'scriptdock_tag_missing', __( 'Enter a tag name.', 'scriptdock' ), array( 'status' => 400 ) );
		}
		return $name;
	}

	/**
	 * A term API error with an HTTP status.
	 *
	 * @param \WP_Error $error Error.
	 * @return \WP_Error
	 */
	private function failed( \WP_Error $error ) {
		$code = $error->get_error_code();
		$error->add_data( array( 'status' => in_array( $code, array( 'term_exists', 'duplicate_term_slug' ), true ) ? 409 : 400 ), $code );
		return $error;
	}
}
