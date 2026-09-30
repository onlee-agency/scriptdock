<?php
/**
 * REST: what the targeting wizard needs to browse a site.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Conditions;
use ScriptDock\Registry;
use ScriptDock\Targeting_Options;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/targeting: the condition catalogue and its value lists, the
 * content, term and user pickers, the match estimate and the URL tester.
 *
 * Everything here reads; nothing it answers changes a snippet.
 */
final class Targeting_Controller extends \WP_REST_Controller {

	/**
	 * How many items one page of content holds by default.
	 */
	const PER_PAGE = 24;

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'targeting';
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes() {
		$manage = array( Rest::class, 'can_manage' );
		$base   = '/' . $this->rest_base;

		register_rest_route(
			$this->namespace,
			$base . '/options',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/content',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_content' ),
				'permission_callback' => $manage,
				'args'                => array(
					'type'     => array(
						'description' => __( 'Post type.', 'scriptdock' ),
						'type'        => 'string',
						'default'     => 'page',
					),
					'search'   => array(
						'description' => __( 'Search words.', 'scriptdock' ),
						'type'        => 'string',
						'default'     => '',
					),
					'status'   => array(
						'description' => __( 'Post status, or any.', 'scriptdock' ),
						'type'        => 'string',
						'default'     => '',
					),
					'author'   => array(
						'description' => __( 'Author ID.', 'scriptdock' ),
						'type'        => 'integer',
						'default'     => 0,
					),
					'parent'   => array(
						'description' => __( 'Parent page ID.', 'scriptdock' ),
						'type'        => 'integer',
						'default'     => 0,
					),
					'term'     => array(
						'description' => __( 'Term ID the posts must have.', 'scriptdock' ),
						'type'        => 'integer',
						'default'     => 0,
					),
					'year'     => array(
						'description' => __( 'Year, as YYYYMM or YYYY.', 'scriptdock' ),
						'type'        => 'string',
						'default'     => '',
					),
					'orderby'  => array(
						'description' => __( 'title, date or menu_order.', 'scriptdock' ),
						'type'        => 'string',
						'enum'        => array( 'title', 'date', 'menu_order' ),
						'default'     => 'title',
					),
					'include'  => array(
						'description' => __( 'Specific IDs, for showing what is already selected.', 'scriptdock' ),
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'default'     => array(),
					),
					'page'     => array(
						'description' => __( 'Page number.', 'scriptdock' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'default'     => 1,
					),
					'per_page' => array(
						'description' => __( 'Items per page.', 'scriptdock' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'default'     => self::PER_PAGE,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/terms',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_terms' ),
				'permission_callback' => $manage,
				'args'                => array(
					'taxonomy' => array(
						'description' => __( 'Taxonomy name; empty means every public taxonomy.', 'scriptdock' ),
						'type'        => 'string',
						'default'     => '',
					),
					'search'   => array(
						'description' => __( 'Search words.', 'scriptdock' ),
						'type'        => 'string',
						'default'     => '',
					),
					'include'  => array(
						'description' => __( 'Specific term IDs.', 'scriptdock' ),
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'default'     => array(),
					),
					'per_page' => array(
						'description' => __( 'Terms per answer.', 'scriptdock' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 200,
						'default'     => 100,
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/users',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_users' ),
				'permission_callback' => $manage,
				'args'                => array(
					'search'  => array(
						'description' => __( 'Search words.', 'scriptdock' ),
						'type'        => 'string',
						'default'     => '',
					),
					'include' => array(
						'description' => __( 'Specific user IDs.', 'scriptdock' ),
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'default'     => array(),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/estimate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'estimate' ),
				'permission_callback' => $manage,
				'args'                => array(
					'conditions' => array(
						'description' => __( 'Rule set to estimate.', 'scriptdock' ),
						'type'        => 'object',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/test-url',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_url' ),
				'permission_callback' => $manage,
				'args'                => array(
					'url'   => array(
						'description' => __( 'URL or path to test.', 'scriptdock' ),
						'type'        => 'string',
						'required'    => true,
					),
					'rules' => array(
						'description' => __( 'URL rules: operator and value.', 'scriptdock' ),
						'type'        => 'array',
						'default'     => array(),
					),
				),
			)
		);
	}

	/**
	 * The catalogue, its operators and every fixed value list.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_options() {
		return rest_ensure_response(
			array(
				'catalogue'   => Conditions::catalogue(),
				'operators'   => Conditions::operator_labels(),
				'lists'       => Targeting_Options::all(),
				'woocommerce' => Registry::woocommerce_active(),
				'home'        => home_url( '/' ),
				'timezone'    => wp_timezone_string(),
			)
		);
	}

	/**
	 * A page of content to choose from.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_content( $request ) {
		$type = (string) $request['type'];
		if ( ! post_type_exists( $type ) || ! is_post_type_viewable( get_post_type_object( $type ) ) ) {
			return new \WP_Error( 'scriptdock_unknown_type', __( 'That content type does not exist.', 'scriptdock' ), array( 'status' => 400 ) );
		}

		$statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );
		$status   = (string) $request['status'];
		$args     = array(
			'post_type'      => $type,
			'post_status'    => $status && in_array( $status, $statuses, true ) ? $status : $statuses,
			'posts_per_page' => (int) $request['per_page'],
			'paged'          => (int) $request['page'],
			'orderby'        => 'title' === $request['orderby'] ? 'title' : (string) $request['orderby'],
			'order'          => 'date' === $request['orderby'] ? 'DESC' : 'ASC',
			'no_found_rows'  => false,
		);
		if ( 'menu_order' === $request['orderby'] ) {
			$args['orderby'] = 'menu_order title';
		}
		if ( $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}
		if ( $request['author'] ) {
			$args['author'] = (int) $request['author'];
		}
		if ( $request['parent'] ) {
			$args['post_parent'] = (int) $request['parent'];
		}
		if ( $request['term'] ) {
			$term = get_term( (int) $request['term'] );
			if ( $term && ! is_wp_error( $term ) ) {
				$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => $term->taxonomy,
						'terms'    => $term->term_id,
					),
				);
			}
		}
		if ( $request['year'] ) {
			$raw          = preg_replace( '/\D/', '', (string) $request['year'] );
			$args['year'] = (int) substr( $raw, 0, 4 );
			$month        = strlen( $raw ) > 4 ? (int) substr( $raw, 4, 2 ) : 0;
			if ( $month ) {
				$args['monthnum'] = $month;
			}
		}
		$include = array_filter( array_map( 'intval', (array) $request['include'] ) );
		if ( $include ) {
			// Looking up what is already picked: the IDs say what they are,
			// and one tray can hold pages, posts and products together.
			$args['post_type']      = array_values( get_post_types( array( 'public' => true ) ) );
			$args['post__in']       = $include;
			$args['posts_per_page'] = count( $include );
			$args['paged']          = 1;
			unset( $args['s'] );
		}

		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->content_item( $post );
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
				'page'  => (int) $request['page'],
			)
		);
	}

	/**
	 * One piece of content, with what the cards draw.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private function content_item( $post ) {
		$thumbnail = get_the_post_thumbnail_url( $post, 'medium' );
		$parent    = $post->post_parent ? get_post( $post->post_parent ) : null;
		$children  = is_post_type_hierarchical( $post->post_type )
			? count(
				get_children(
					array(
						'post_parent' => $post->ID,
						'post_type'   => $post->post_type,
						'post_status' => array( 'publish', 'future', 'draft', 'pending', 'private' ),
						'fields'      => 'ids',
					)
				)
			)
			: 0;
		$time      = (int) get_post_timestamp( $post );

		return array(
			'id'        => (int) $post->ID,
			'title'     => get_the_title( $post ) ? get_the_title( $post ) : __( '(no title)', 'scriptdock' ),
			'type'      => $post->post_type,
			'path'      => $this->path( $post ),
			'status'    => $post->post_status,
			'date'      => $time ? gmdate( 'c', $time ) : '',
			'date_label' => $time ? wp_date( get_option( 'date_format' ), $time ) : '',
			'thumbnail' => $thumbnail ? $thumbnail : '',
			'parent'    => $parent ? array( 'id' => (int) $parent->ID, 'title' => get_the_title( $parent ) ) : null,
			'children'  => $children,
		);
	}

	/**
	 * The path a visitor sees, without the site address. A draft has no
	 * permalink yet, so it shows the address it will have.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private function path( $post ) {
		if ( in_array( $post->post_status, array( 'publish', 'private', 'future' ), true ) ) {
			$link = get_permalink( $post );
		} else {
			if ( ! function_exists( 'get_sample_permalink' ) ) {
				require_once ABSPATH . 'wp-admin/includes/post.php';
			}
			list( $template, $name ) = get_sample_permalink( $post->ID );
			$link                    = str_replace( array( '%pagename%', '%postname%' ), $name, (string) $template );
		}
		$path = $link ? wp_parse_url( $link, PHP_URL_PATH ) : '';
		return $path ? $path : '/?p=' . (int) $post->ID;
	}

	/**
	 * Terms, as a flat list carrying each one's parent so the picker can draw
	 * the tree.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_terms( $request ) {
		$taxonomy = (string) $request['taxonomy'];
		$args     = array(
			'taxonomy'   => $taxonomy && taxonomy_exists( $taxonomy ) ? $taxonomy : get_taxonomies( array( 'public' => true ) ),
			'hide_empty' => false,
			'number'     => (int) $request['per_page'],
			'orderby'    => 'name',
		);
		if ( $request['search'] ) {
			$args['search'] = (string) $request['search'];
		}
		$include = array_filter( array_map( 'intval', (array) $request['include'] ) );
		if ( $include ) {
			$args['include'] = $include;
			$args['number']  = count( $include );
			unset( $args['search'] );
		}

		$items = array();
		foreach ( get_terms( $args ) as $term ) {
			if ( is_wp_error( $term ) ) {
				continue;
			}
			$taxonomy_object = get_taxonomy( $term->taxonomy );
			$items[]         = array(
				'id'       => (int) $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'parent'   => (int) $term->parent,
				'count'    => (int) $term->count,
				'taxonomy' => $term->taxonomy,
				'taxonomy_label' => $taxonomy_object ? $taxonomy_object->labels->singular_name : $term->taxonomy,
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Users who can be an author or a specific visitor.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_users( $request ) {
		$args = array(
			'number'  => 30,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => array( 'ID', 'display_name', 'user_login' ),
		);
		if ( $request['search'] ) {
			$args['search']         = '*' . (string) $request['search'] . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}
		$include = array_filter( array_map( 'intval', (array) $request['include'] ) );
		if ( $include ) {
			$args['include'] = $include;
			$args['number']  = count( $include );
			unset( $args['search'] );
		}

		$items = array();
		foreach ( get_users( $args ) as $user ) {
			$items[] = array(
				'id'    => (int) $user->ID,
				'name'  => $user->display_name ? $user->display_name : $user->user_login,
				'login' => $user->user_login,
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Roughly how many pages a rule set matches.
	 *
	 * Counts published content: the rules that name posts, post types, terms,
	 * authors or templates decide it, and rules about the visitor, the device
	 * or the time cannot be counted in advance. Always shown with "≈".
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function estimate( $request ) {
		$conditions = Conditions::sanitize( $request['conditions'] );
		$hide       = 'hide' === $conditions['action'];
		$total      = $this->published_total();

		if ( ! Conditions::is_active( $conditions ) ) {
			return rest_ensure_response(
				array(
					'count'   => $total,
					'total'   => $total,
					'visitor' => false,
					'exact'   => true,
				)
			);
		}

		$visitor   = false;
		$anywhere  = false;
		$ids       = array();
		foreach ( $conditions['groups'] as $group ) {
			$group_ids = null;
			foreach ( $group as $rule ) {
				$matches = $this->rule_matches( $rule );
				if ( null === $matches ) {
					$visitor = true;
					continue;
				}
				$group_ids = null === $group_ids ? $matches : array_intersect( $group_ids, $matches );
			}
			if ( null === $group_ids ) {
				// The group only asks about the visitor, so any page can match.
				$anywhere = true;
			} else {
				$ids = array_merge( $ids, $group_ids );
			}
		}
		$count = $anywhere ? $total : count( array_unique( $ids ) );
		if ( $hide ) {
			$count = max( 0, $total - $count );
		}

		return rest_ensure_response(
			array(
				'count'   => $count,
				'total'   => $total,
				'visitor' => $visitor,
				'exact'   => false,
			)
		);
	}

	/**
	 * The IDs a content rule matches, or null when the rule is about the
	 * visitor, the device or the time and cannot be counted.
	 *
	 * @param array $rule Rule.
	 * @return array|null
	 */
	private function rule_matches( array $rule ) {
		$values  = array_filter( array_map( 'intval', (array) $rule['value'] ) );
		$strings = array_map( 'strval', (array) $rule['value'] );
		$negate  = 'is_not' === $rule['operator'];
		$all     = null;

		switch ( $rule['rule'] ) {
			case 'post':
				$all = $values;
				break;
			case 'post_type':
				$all = $this->ids( array( 'post_type' => $strings ) );
				break;
			case 'post_parent':
				$all = $this->ids( array( 'post_parent__in' => $values ) );
				break;
			case 'taxonomy_term':
				$terms = array();
				foreach ( $values as $term_id ) {
					$term = get_term( $term_id );
					if ( $term && ! is_wp_error( $term ) ) {
						$terms[ $term->taxonomy ][] = $term->term_id;
					}
				}
				$query = array( 'relation' => 'OR' );
				foreach ( $terms as $taxonomy => $term_ids ) {
					$query[] = array( 'taxonomy' => $taxonomy, 'terms' => $term_ids );
				}
				$all = count( $query ) > 1 ? $this->ids( array( 'tax_query' => $query ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				break;
			case 'post_author':
				$all = $this->ids( array( 'author__in' => $values ) );
				break;
			case 'page_template':
				$all = $this->ids(
					array(
						'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array( 'key' => '_wp_page_template', 'value' => $strings, 'compare' => 'IN' ),
						),
					)
				);
				break;
			default:
				return null;
		}

		if ( ! $negate ) {
			return $all;
		}
		return array_values( array_diff( $this->ids( array() ), $all ) );
	}

	/**
	 * Published IDs for a query, capped so a huge site cannot stall the answer.
	 *
	 * @param array $args Extra WP_Query arguments.
	 * @return array
	 */
	private function ids( array $args ) {
		$query = new \WP_Query(
			array_merge(
				array(
					'post_type'      => array_values( get_post_types( array( 'public' => true ) ) ),
					'post_status'    => 'publish',
					'posts_per_page' => 2000,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				),
				$args
			)
		);
		return array_map( 'intval', $query->posts );
	}

	/**
	 * How much published content the site has, plus the two pages that are
	 * not posts at all: search results and the 404 page. A front page or blog
	 * page that is a real page is already counted.
	 *
	 * @return int
	 */
	private function published_total() {
		return count( $this->ids( array() ) ) + 2;
	}

	/**
	 * Whether a URL matches the URL rules as they stand.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function test_url( $request ) {
		$url  = trim( (string) $request['url'] );
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			$path = '/' === substr( $url, 0, 1 ) ? $url : '/' . $url;
		}
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		$path  = $query ? $path . '?' . $query : $path;

		$rules = array();
		foreach ( (array) $request['rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$rules[] = array(
				'rule'     => 'url',
				'operator' => isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : 'contains',
				'value'    => isset( $rule['value'] ) ? $rule['value'] : '',
			);
		}

		$matched = array();
		foreach ( $rules as $index => $rule ) {
			if ( Conditions::matches_url( $rule['operator'], $path, (array) $rule['value'] ) ) {
				$matched[] = $index;
			}
		}

		return rest_ensure_response(
			array(
				'path'    => $path,
				'matches' => (bool) $matched,
				'matched' => $matched,
			)
		);
	}
}
