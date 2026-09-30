<?php
/**
 * REST: everything the Overview screen puts on one page.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Rest;

use ScriptDock\Capabilities;
use ScriptDock\Library;
use ScriptDock\Migrator;
use ScriptDock\Page_Scripts;
use ScriptDock\Post_Type;
use ScriptDock\Safe_Mode;
use ScriptDock\Settings;
use ScriptDock\Snippet;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * /scriptdock/v1/overview: the greeting, what needs attention, the counts,
 * what was edited lately, the getting-started list and a speed tip.
 *
 * It reads the snippet list through the list route, so the Overview and the
 * Snippets screen always agree on what is running.
 */
final class Overview_Controller extends \WP_REST_Controller {

	/**
	 * How many snippets the "Recently edited" card shows.
	 */
	const RECENT = 5;

	/**
	 * Sets the route base.
	 */
	public function __construct() {
		$this->namespace = Rest::ROUTE_NAMESPACE;
		$this->rest_base = 'overview';
	}

	/**
	 * Registers the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( Rest::class, 'can_manage' ),
			)
		);
	}

	/**
	 * The whole screen in one answer.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The whole screen takes no arguments.
		$list = rest_do_request(
			self::list_request( array( 'orderby' => 'modified', 'order' => 'desc', 'per_page' => 100 ) )
		);
		if ( $list->is_error() ) {
			return $list->as_error();
		}
		$data   = $list->get_data();
		$items  = $data['items'];
		$counts = $data['counts'];

		$problems = array_values(
			array_filter(
				$items,
				static function ( $item ) {
					return $item['error'] || ! $item['trusted'];
				}
			)
		);

		return rest_ensure_response(
			array(
				'greeting'  => $this->greeting(),
				'health'    => $this->health( $problems, $counts ),
				'stats'     => $this->stats( $counts ),
				'recent'    => array_slice( $items, 0, self::RECENT ),
				'problems'  => array_slice( $problems, 0, 4 ),
				'checklist' => $this->checklist( $counts ),
				'tip'       => $this->tip(),
				'library'   => $this->library(),
				'safeMode'  => array(
					'active' => Safe_Mode::is_active(),
					'forced' => Safe_Mode::is_forced(),
					'exit'   => self::plain( Safe_Mode::toggle_url( false ) ),
				),
				'urls'      => $this->urls(),
			)
		);
	}

	/**
	 * A URL without the HTML escaping WordPress adds for markup: JSON hands
	 * it to the browser as it is.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function plain( $url ) {
		return html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * A list request with the arguments the route expects.
	 *
	 * @param array $args Query arguments.
	 * @return \WP_REST_Request
	 */
	private static function list_request( array $args ) {
		$request = new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/snippets' );
		foreach ( $args as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	/**
	 * "Good morning, Maya", by the site's clock.
	 *
	 * @return array
	 */
	private function greeting() {
		$user = wp_get_current_user();
		$name = $user->first_name ? $user->first_name : $user->display_name;
		$hour = (int) wp_date( 'G' );

		if ( $hour < 12 ) {
			/* translators: %s: the person's first name. */
			$text = __( 'Good morning, %s', 'scriptdock' );
		} elseif ( $hour < 18 ) {
			/* translators: %s: the person's first name. */
			$text = __( 'Good afternoon, %s', 'scriptdock' );
		} else {
			/* translators: %s: the person's first name. */
			$text = __( 'Good evening, %s', 'scriptdock' );
		}

		return array(
			'text' => sprintf( $text, $name ),
			'name' => $name,
		);
	}

	/**
	 * The one-line status, and a card for each thing that needs attention.
	 *
	 * @param array $problems Snippets with an error or a change to review.
	 * @param array $counts   Counts per view.
	 * @return array
	 */
	private function health( array $problems, array $counts ) {
		$cards = array();
		foreach ( $problems as $item ) {
			if ( $item['error'] ) {
				$cards[] = array(
					'id'     => 'error-' . $item['id'],
					'tone'   => 'danger',
					'title'  => sprintf(
						/* translators: %s: snippet title. */
						__( '“%s” was switched off', 'scriptdock' ),
						$item['title']
					),
					'body'   => self::error_line( $item['error'] ),
					'action' => array(
						'label' => __( 'Fix it', 'scriptdock' ),
						'url'   => Snippets::edit_url( $item['id'] ),
					),
					// The same link the admin notice uses, so dismissing here
					// dismisses it everywhere.
					'dismiss' => self::plain(
						wp_nonce_url(
							add_query_arg(
								array(
									'action' => 'scriptdock_dismiss_notice',
									'id'     => (int) $item['id'],
								),
								admin_url( 'admin-post.php' )
							),
							'scriptdock_dismiss_notice'
						)
					),
				);
				continue;
			}
			$cards[] = array(
				'id'     => 'review-' . $item['id'],
				'tone'   => 'danger',
				'title'  => __( '1 snippet needs review', 'scriptdock' ),
				'body'   => sprintf(
					/* translators: 1: snippet title, 2: how long ago, for example "1 hour". */
					__( '“%1$s” changed outside ScriptDock %2$s ago, possibly by malware or a migration. It is paused until you approve it.', 'scriptdock' ),
					$item['title'],
					$item['modified']['human'] ? str_replace( __( ' ago', 'scriptdock' ), '', $item['modified']['human'] ) : ''
				),
				'action' => array(
					'label' => __( 'Review changes', 'scriptdock' ),
					'url'   => Snippets::edit_url( $item['id'] ),
				),
			);
		}

		if ( Safe_Mode::is_active() ) {
			$cards[] = array(
				'id'     => 'safe-mode',
				'tone'   => 'warning',
				'title'  => __( 'Safe mode is on', 'scriptdock' ),
				'body'   => __( 'No snippets are running for you, so you can fix things. Visitors still see your site as normal.', 'scriptdock' ),
				'action' => array(
					'label' => __( 'Exit safe mode', 'scriptdock' ),
					'url'   => self::plain( Safe_Mode::toggle_url( false ) ),
				),
			);
		}

		$attention = count( $cards );
		$text      = $attention
			? sprintf(
				/* translators: %d: number of things that need attention. */
				_n( '%d thing needs your attention', '%d things need your attention', $attention, 'scriptdock' ),
				$attention
			)
			: __( 'Everything is running smoothly', 'scriptdock' );

		return array(
			'text'    => $text,
			'ok'      => 0 === $attention,
			'cards'   => $cards,
			'running' => $counts['running'],
		);
	}

	/**
	 * What went wrong, where, and when: "Call to undefined function wc_foo()
	 * on line 12, while loading /cart/. 25 mins ago."
	 *
	 * @param array $error Error from the list route.
	 * @return string
	 */
	private static function error_line( array $error ) {
		$where = '';
		if ( $error['line'] ) {
			$where = sprintf(
				/* translators: %d: line number. */
				__( ' on line %d', 'scriptdock' ),
				$error['line']
			);
		}
		if ( $error['url'] ) {
			$where .= sprintf(
				/* translators: %s: the page being loaded, for example "/cart/". */
				__( ', while loading %s', 'scriptdock' ),
				$error['url']
			);
		}
		return trim(
			sprintf(
				/* translators: 1: error message with where it happened, 2: how long ago. */
				__( '%1$s. %2$s', 'scriptdock' ),
				$error['message'] . $where,
				$error['human']
			)
		);
	}

	/**
	 * The four tiles, each linking to the list it counts.
	 *
	 * @param array $counts Counts per view.
	 * @return array
	 */
	private function stats( array $counts ) {
		return array(
			array(
				'key'   => 'running',
				'label' => __( 'Running', 'scriptdock' ),
				'count' => $counts['running'],
				'note'  => __( 'snippets on the front end', 'scriptdock' ),
				'url'   => Snippets::list_url( array( 'view' => 'active' ) ),
			),
			array(
				'key'   => 'inactive',
				'label' => __( 'Inactive', 'scriptdock' ),
				'count' => $counts['inactive'],
				'note'  => __( 'saved but switched off', 'scriptdock' ),
				'url'   => Snippets::list_url( array( 'view' => 'inactive' ) ),
			),
			array(
				'key'   => 'errors',
				'label' => __( 'Errors', 'scriptdock' ),
				'count' => $counts['error'],
				'note'  => $counts['error']
					? __( 'switched off after a crash', 'scriptdock' )
					: __( 'nothing has crashed', 'scriptdock' ),
				'url'   => Snippets::list_url( array( 'view' => 'errors' ) ),
			),
			array(
				'key'   => 'pages',
				'label' => __( 'Page scripts', 'scriptdock' ),
				'count' => $this->page_scripts(),
				'note'  => __( 'pages with their own code', 'scriptdock' ),
				'url'   => admin_url( 'edit.php?post_type=page&scriptdock_page_code=1' ),
			),
		);
	}

	/**
	 * How many posts and pages carry their own code.
	 *
	 * @return int
	 */
	private function page_scripts() {
		$found = get_posts(
			array(
				'post_type'      => Page_Scripts::post_types(),
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Page_Scripts::META,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$count = 0;
		foreach ( $found as $id ) {
			if ( Page_Scripts::has_code( Page_Scripts::get( $id ) ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * The getting-started list: four steps, each either done or not.
	 *
	 * @param array $counts Counts per view.
	 * @return array
	 */
	private function checklist( array $counts ) {
		$sources = Migrator::sources();
		$source  = $sources ? reset( $sources ) : null;
		$items   = array(
			array(
				'key'   => 'safe_link',
				'label' => __( 'Save your safe mode link', 'scriptdock' ),
				'done'  => Safe_Mode::link_saved(),
				'url'   => admin_url( 'admin.php?page=scriptdock-settings#safety' ),
			),
			array(
				'key'   => 'first_snippet',
				'label' => __( 'Add your first snippet', 'scriptdock' ),
				'done'  => $counts['all'] > 0,
				'url'   => Snippets::edit_url(),
			),
		);

		$items[] = $source
			? array(
				'key'   => 'import',
				'label' => sprintf(
					/* translators: %s: the plugin the snippets would come from, for example "WPCode". */
					__( 'Import from %s', 'scriptdock' ),
					$source[0]
				),
				'done'  => (bool) get_option( Tools_Controller::IMPORTED_OPTION, false ),
				'url'   => admin_url( 'admin.php?page=scriptdock-tools' ),
			)
			: array(
				'key'   => 'library',
				'label' => __( 'Try the library', 'scriptdock' ),
				'done'  => $this->library_used(),
				'url'   => admin_url( 'admin.php?page=scriptdock-library' ),
			);

		$items[] = array(
			'key'   => 'page_types',
			'label' => __( 'Choose page-script content types', 'scriptdock' ),
			'done'  => (bool) get_option( Settings_Controller::PAGE_TYPES_OPTION, false ),
			'url'   => admin_url( 'admin.php?page=scriptdock-settings#page-scripts' ),
		);

		$done = count(
			array_filter(
				$items,
				static function ( $item ) {
					return $item['done'];
				}
			)
		);

		return array(
			'items' => $items,
			'done'  => $done,
			'total' => count( $items ),
		);
	}

	/**
	 * Whether anything came from the library.
	 *
	 * @return bool
	 */
	private function library_used() {
		$found = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'any',
				'posts_per_page' => 1,
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
		return ! empty( $found );
	}

	/**
	 * One speed tip, picked from what this site actually has.
	 *
	 * @return array
	 */
	private function tip() {
		$delayed = null;
		$filed   = null;
		$posts   = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
			)
		);
		foreach ( $posts as $post ) {
			$snippet = Snippet::from_post( $post );
			if ( ! $delayed && in_array( $snippet->options['strategy'], array( 'interaction', 'idle' ), true ) ) {
				$delayed = $snippet;
			}
			if ( ! $filed && 'file' === $snippet->options['output'] ) {
				$filed = $snippet;
			}
		}

		if ( $delayed ) {
			return array(
				'text'   => sprintf(
					/* translators: %s: snippet title. */
					__( 'Delay chat widgets until the visitor interacts and pages load faster. Your “%s” snippet already does this.', 'scriptdock' ),
					$delayed->title
				),
				'action' => array(
					'label' => __( 'See load strategies', 'scriptdock' ),
					'url'   => admin_url( 'admin.php?page=scriptdock-settings#performance' ),
				),
			);
		}
		if ( $filed ) {
			return array(
				'text'   => sprintf(
					/* translators: %s: snippet title. */
					__( 'Serve big CSS snippets as cached files instead of inline. “%s” already does.', 'scriptdock' ),
					$filed->title
				),
				'action' => array(
					'label' => __( 'Open performance settings', 'scriptdock' ),
					'url'   => admin_url( 'admin.php?page=scriptdock-settings#performance' ),
				),
			);
		}
		return array(
			'text'   => __( 'Scripts that are not needed straight away can wait for the first scroll or tap. Pages then load faster without losing anything.', 'scriptdock' ),
			'action' => array(
				'label' => __( 'See load strategies', 'scriptdock' ),
				'url'   => admin_url( 'admin.php?page=scriptdock-settings#performance' ),
			),
		);
	}

	/**
	 * Three templates to start from, and how many there are in all.
	 *
	 * @return array
	 */
	private function library() {
		$templates = Library::templates();
		$added     = $this->library_titles();
		$cards     = array();
		foreach ( array_slice( $templates, 0, 3, true ) as $id => $template ) {
			$cards[] = array(
				'id'          => $id,
				'title'       => $template['title'],
				'description' => $template['description'],
				'category'    => isset( $template['category'] ) ? $template['category'] : '',
				'added'       => in_array( $template['title'], $added, true ),
				'url'         => admin_url( 'admin.php?page=scriptdock-library#' . $id ),
			);
		}
		return array(
			'total' => count( $templates ),
			'cards' => $cards,
			'url'   => admin_url( 'admin.php?page=scriptdock-library' ),
		);
	}

	/**
	 * Titles of the snippets that came from the library.
	 *
	 * @return array
	 */
	private function library_titles() {
		$found = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Snippet::META_SOURCE,
						'value'   => 'library:',
						'compare' => 'LIKE',
					),
				),
			)
		);
		return wp_list_pluck( $found, 'post_title' );
	}

	/**
	 * Where the quick actions go.
	 *
	 * @return array
	 */
	private function urls() {
		return array(
			'new'      => Snippets::edit_url(),
			'list'     => Snippets::list_url(),
			'library'  => admin_url( 'admin.php?page=scriptdock-library' ),
			'global'   => admin_url( 'admin.php?page=scriptdock-global' ),
			'import'   => admin_url( 'admin.php?page=scriptdock-tools' ),
			'settings' => admin_url( 'admin.php?page=scriptdock-settings' ),
			'canPhp'   => Capabilities::can_manage_php(),
			'files'    => (bool) Settings::get( 'asset_files' ),
		);
	}
}
