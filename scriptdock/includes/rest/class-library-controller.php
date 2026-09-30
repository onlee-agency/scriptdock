<?php
/**
 * REST: the snippet library.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Capabilities;
use ScriptDock\Library;
use ScriptDock\Post_Type;
use ScriptDock\Registry;
use ScriptDock\Snippet;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/library: what ships with the plugin, and adding one of them
 * as a snippet.
 *
 * Everything here is bundled: nothing is fetched from anywhere.
 */
final class Library_Controller extends \WP_REST_Controller {

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'library';
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
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => $manage,
				'args'                => array(
					'id'       => array(
						'description' => __( 'Template ID.', 'scriptdock' ),
						'type'        => 'string',
						'required'    => true,
					),
					'values'   => array(
						'description' => __( 'Values for the template’s fields.', 'scriptdock' ),
						'type'        => 'object',
					),
					'activate' => array(
						'description' => __( 'Whether the snippets run straight away.', 'scriptdock' ),
						'type'        => 'boolean',
						'default'     => false,
					),
					'consent'  => array(
						'description' => __( 'Consent category to wait for.', 'scriptdock' ),
						'type'        => 'string',
					),
				),
			)
		);
	}

	/**
	 * Every template, with its category, what it needs and whether this site
	 * already has it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The whole library takes no arguments.
		$templates = Library::templates();
		$added     = $this->added();
		$woo       = Registry::woocommerce_active();
		$can_php   = Capabilities::can_manage_php();

		$items  = array();
		$counts = array();
		foreach ( $templates as $id => $template ) {
			$category             = isset( $template['category'] ) ? $template['category'] : 'content';
			$counts[ $category ]  = isset( $counts[ $category ] ) ? $counts[ $category ] + 1 : 1;
			$items[]              = $this->item( $id, $template, $added, $woo, $can_php );
		}

		$categories = array(
			array(
				'key'   => 'all',
				'label' => __( 'All', 'scriptdock' ),
				'count' => count( $items ),
			),
		);
		foreach ( Library::categories() as $key => $label ) {
			$categories[] = array(
				'key'   => $key,
				'label' => $label,
				'count' => isset( $counts[ $key ] ) ? $counts[ $key ] : 0,
			);
		}

		return rest_ensure_response(
			array(
				'categories' => $categories,
				'templates'  => $items,
				'total'      => count( $items ),
			)
		);
	}

	/**
	 * One template as the cards and the modal draw it.
	 *
	 * @param string $id       Template ID.
	 * @param array  $template Template.
	 * @param array  $added    Template ID => snippet IDs already on the site.
	 * @param bool   $woo      Whether WooCommerce is active.
	 * @param bool   $can_php  Whether this user may add PHP.
	 * @return array
	 */
	private function item( $id, array $template, array $added, $woo, $can_php ) {
		$parts  = Library::parts( $template );
		$types  = array();
		$places = array();
		$php    = false;
		foreach ( $parts as $part ) {
			$types[]  = $part['type'];
			$places[] = Registry::short_label( $part['location'] );
			$php      = $php || Registry::is_php_type( $part['type'] );
		}

		$category = isset( $template['category'] ) ? $template['category'] : 'content';
		$blocked  = '';
		if ( 'woocommerce' === $category && ! $woo ) {
			$blocked = __( 'Needs WooCommerce, which is not active on this site.', 'scriptdock' );
		} elseif ( $php && ! $can_php ) {
			$blocked = Capabilities::php_unavailable_reason();
		}

		$fields = array();
		foreach ( isset( $template['fields'] ) ? $template['fields'] : array() as $key => $field ) {
			$fields[] = array(
				'key'         => $key,
				'label'       => $field['label'],
				'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
				'pattern'     => isset( $field['pattern'] ) ? $field['pattern'] : '',
				'default'     => isset( $field['default'] ) ? (string) $field['default'] : '',
				'help'        => isset( $field['help'] ) ? $field['help'] : '',
			);
		}

		$snippets = array();
		foreach ( $parts as $part ) {
			$snippets[] = array(
				'title'     => isset( $part['title'] ) ? $part['title'] : $template['title'],
				'type'      => $part['type'],
				'placement' => Registry::short_label( $part['location'] ),
				'code'      => Library::code( $part ),
				'consent'   => isset( $part['options']['consent'] ) ? $part['options']['consent'] : '',
			);
		}

		$existing = isset( $added[ $id ] ) ? $added[ $id ] : array();

		return array(
			'id'          => $id,
			'title'       => $template['title'],
			'description' => $template['description'],
			'category'    => $category,
			'types'       => array_values( array_unique( $types ) ),
			'placement'   => implode( ' · ', array_unique( $places ) ),
			'consent'     => isset( $template['options']['consent'] ) ? $template['options']['consent'] : ( $snippets ? $snippets[0]['consent'] : '' ),
			'fields'      => $fields,
			'snippets'    => $snippets,
			'blocked'     => $blocked,
			'added'       => (bool) $existing,
			'edit_url'    => $existing ? Snippets::edit_url( (int) $existing[0] ) : '',
		);
	}

	/**
	 * Which templates this site already has, by the source each snippet
	 * carries.
	 *
	 * @return array Template ID => snippet IDs.
	 */
	private function added() {
		$found = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Snippet::META_SOURCE,
						'value'   => 'library:',
						'compare' => 'LIKE',
					),
				),
			)
		);

		$added = array();
		foreach ( $found as $post_id ) {
			$source = (string) get_post_meta( $post_id, Snippet::META_SOURCE, true );
			$id     = substr( $source, strlen( 'library:' ) );
			if ( '' !== $id ) {
				$added[ $id ][] = (int) $post_id;
			}
		}
		return $added;
	}

	/**
	 * Adds a template to the site.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$id       = (string) $request['id'];
		$template = Library::get( $id );
		if ( ! $template ) {
			return new \WP_Error( 'scriptdock_not_found', __( 'Template not found.', 'scriptdock' ), array( 'status' => 404 ) );
		}

		if ( isset( $template['category'] ) && 'woocommerce' === $template['category'] && ! Registry::woocommerce_active() ) {
			return new \WP_Error( 'scriptdock_needs_woocommerce', __( 'This template needs WooCommerce, which is not active on this site.', 'scriptdock' ), array( 'status' => 409 ) );
		}

		$consent = null !== $request['consent'] ? (string) $request['consent'] : null;
		if ( null !== $consent && '' !== $consent && ! in_array( $consent, Snippet::CONSENT_CATEGORIES, true ) ) {
			return new \WP_Error( 'scriptdock_consent', __( 'That consent category is not one WordPress knows.', 'scriptdock' ), array( 'status' => 400 ) );
		}

		$values = (array) ( $request['values'] ? $request['values'] : array() );
		foreach ( isset( $template['fields'] ) ? $template['fields'] : array() as $key => $field ) {
			$value = isset( $values[ $key ] ) ? trim( (string) $values[ $key ] ) : '';
			if ( '' === $value && isset( $field['default'] ) ) {
				continue;
			}
			if ( '' === $value ) {
				return new \WP_Error(
					'scriptdock_field_required',
					/* translators: %s: field name, for example "Measurement ID". */
					sprintf( __( '%s is needed.', 'scriptdock' ), $field['label'] ),
					array(
						'status' => 400,
						'field'  => $key,
					)
				);
			}
			if ( ! empty( $field['pattern'] ) && ! preg_match( '/' . str_replace( '/', '\\/', $field['pattern'] ) . '/', $value ) ) {
				return new \WP_Error(
					'scriptdock_field_format',
					sprintf(
						/* translators: 1: field name, 2: the format it needs, for example "G-XXXXXXXXXX". */
						__( 'Use the format %2$s for %1$s.', 'scriptdock' ),
						$field['label'],
						isset( $field['placeholder'] ) ? $field['placeholder'] : $field['pattern']
					),
					array(
						'status' => 400,
						'field'  => $key,
					)
				);
			}
		}

		$ids = Library::create( $id, $values, (bool) $request['activate'] );
		if ( is_wp_error( $ids ) ) {
			return $this->with_status( $ids );
		}

		// The consent category is the snippet's, not the template's, so it is
		// set after the snippets exist.
		if ( null !== $consent ) {
			foreach ( $ids as $snippet_id ) {
				$snippet = Snippet::get( $snippet_id );
				if ( $snippet ) {
					$snippet->options['consent'] = $consent;
					$snippet->save();
				}
			}
		}

		$created = array();
		foreach ( $ids as $snippet_id ) {
			$snippet   = Snippet::get( $snippet_id );
			$created[] = array(
				'id'       => (int) $snippet_id,
				'title'    => $snippet ? $snippet->title : '',
				'active'   => $snippet ? $snippet->active : false,
				'edit_url' => Snippets::edit_url( (int) $snippet_id ),
			);
		}

		$response = rest_ensure_response(
			array(
				'created' => $created,
				'notice'  => array(
					'code'    => 'added',
					'message' => $this->added_message( $created, (bool) $request['activate'] ),
				),
			)
		);
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * What the toast says after adding.
	 *
	 * @param array $created Created snippets.
	 * @param bool  $active  Whether they were switched on.
	 * @return string
	 */
	private function added_message( array $created, $active ) {
		$count = count( $created );
		if ( $active ) {
			return sprintf(
				/* translators: %d: number of snippets. */
				_n( 'Added %d snippet, and it is running.', 'Added %d snippets, and they are running.', $count, 'scriptdock' ),
				$count
			);
		}
		return sprintf(
			/* translators: %d: number of snippets. */
			_n( 'Added %d snippet, switched off.', 'Added %d snippets, switched off.', $count, 'scriptdock' ),
			$count
		);
	}

	/**
	 * Gives an error the HTTP status it carries.
	 *
	 * @param \WP_Error $error Error.
	 * @return \WP_Error
	 */
	private function with_status( \WP_Error $error ) {
		$data = $error->get_error_data();
		if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
			$error->add_data( array( 'status' => 'scriptdock_forbidden' === $error->get_error_code() ? 403 : 400 ), $error->get_error_code() );
		}
		return $error;
	}
}
