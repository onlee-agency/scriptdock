<?php
/**
 * The Snippets screen (S03): ScriptDock's list of snippets, and its home.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Capabilities;
use ScriptDock\Post_Type;
use ScriptDock\Registry;
use ScriptDock\Rest\Rest;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the root the list app mounts on, with everything it needs to draw
 * its first view without waiting for a request: the first page of snippets,
 * the counts, the tags and the viewer's preferences.
 *
 * Replaces WordPress's own list (edit.php) and tag screen (edit-tags.php)
 * for snippets; both redirect here. Screen Options keep working: the number
 * per page and the column toggles are WordPress's own.
 */
final class Snippets_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'scriptdock-snippets';

	/**
	 * User option for snippets per page, shared with Screen Options.
	 */
	const PER_PAGE_OPTION = 'scriptdock_snippets_per_page';

	/**
	 * User option for row density.
	 */
	const DENSITY_OPTION = 'scriptdock_list_density';

	/**
	 * Hooks the screen.
	 */
	public static function init() {
		add_action( 'current_screen', array( __CLASS__, 'load' ) );
		add_filter( 'set_screen_option_' . self::PER_PAGE_OPTION, array( __CLASS__, 'save_per_page' ), 10, 3 );
		add_action( 'load-edit.php', array( __CLASS__, 'redirect_core_list' ) );
		add_action( 'load-edit-tags.php', array( __CLASS__, 'redirect_core_tags' ) );
		add_action( 'load-term.php', array( __CLASS__, 'redirect_core_tags' ) );
		add_action( 'scriptdock_admin_assets', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Screen Options: snippets per page and the columns that can be hidden.
	 */
	public static function load() {
		if ( ! Admin::is_screen( self::SLUG ) ) {
			return;
		}
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Snippets per page', 'scriptdock' ),
				'default' => 20,
				'option'  => self::PER_PAGE_OPTION,
			)
		);
		add_filter( 'manage_' . get_current_screen()->id . '_columns', array( __CLASS__, 'columns' ) );
	}

	/**
	 * The columns Screen Options can hide. Status, the snippet itself and
	 * its menu always show.
	 *
	 * @return array Column key => label.
	 */
	public static function columns() {
		return array(
			'type'     => __( 'Type', 'scriptdock' ),
			'where'    => __( 'Where it runs', 'scriptdock' ),
			'badges'   => __( 'Badges', 'scriptdock' ),
			'priority' => __( 'Priority', 'scriptdock' ),
			'updated'  => __( 'Updated', 'scriptdock' ),
		);
	}

	/**
	 * Saves the number per page from Screen Options.
	 *
	 * @param mixed  $screen_option Value to save; false to skip.
	 * @param string $option        Option name.
	 * @param int    $value         Submitted value.
	 * @return int
	 */
	public static function save_per_page( $screen_option, $option, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Filter signature.
		return max( 1, min( 100, (int) $value ) );
	}

	/**
	 * The viewer's snippets per page.
	 *
	 * @return int
	 */
	public static function per_page() {
		$value = (int) get_user_option( self::PER_PAGE_OPTION );
		return $value ? max( 1, min( 100, $value ) ) : 20;
	}

	/**
	 * The viewer's row density.
	 *
	 * @return string comfortable or compact.
	 */
	public static function density() {
		return 'compact' === get_user_option( self::DENSITY_OPTION ) ? 'compact' : 'comfortable';
	}

	/**
	 * Sends WordPress's own snippet list here, keeping what its links asked
	 * for: the status view, the search and the filters.
	 */
	public static function redirect_core_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads which view to show.
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( Post_Type::NAME !== $type ) {
			return;
		}
		$args     = array();
		$statuses = array(
			'publish' => 'active',
			'draft'   => 'inactive',
			'trash'   => 'trash',
		);
		$status   = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
		if ( isset( $statuses[ $status ] ) ) {
			$args['view'] = $statuses[ $status ];
		}
		if ( isset( $_GET['scriptdock_view'] ) && 'review' === $_GET['scriptdock_view'] ) {
			$args['view'] = 'review';
		}
		if ( ! empty( $_GET['s'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		if ( ! empty( $_GET['scriptdock_type'] ) ) {
			$args['type'] = sanitize_key( wp_unslash( $_GET['scriptdock_type'] ) );
		}
		if ( ! empty( $_GET['scriptdock_location'] ) ) {
			$args['location'] = sanitize_key( wp_unslash( $_GET['scriptdock_location'] ) );
		}
		if ( ! empty( $_GET[ Post_Type::TAXONOMY ] ) ) {
			$term = get_term_by( 'slug', sanitize_title( wp_unslash( $_GET[ Post_Type::TAXONOMY ] ) ), Post_Type::TAXONOMY );
			if ( $term ) {
				$args['tag'] = (int) $term->term_id;
			}
		}
		// phpcs:enable
		wp_safe_redirect( Snippets::list_url( $args ) );
		exit;
	}

	/**
	 * Sends WordPress's tag screens for snippet tags to the list, with the
	 * Manage tags dialog open.
	 */
	public static function redirect_core_tags() {
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( Post_Type::TAXONOMY === $taxonomy ) {
			wp_safe_redirect( Snippets::list_url( array( 'tags' => 'manage' ) ) );
			exit;
		}
	}

	/**
	 * Prints the page. The app draws everything inside the root, including
	 * the heading; notices land above it, after the header marker.
	 */
	public static function render() {
		?>
		<div class="wrap sd-wrap">
			<hr class="wp-header-end">
			<div id="sd-snippets" class="sd-app sd-snippets">
				<noscript>
					<p><?php esc_html_e( 'The snippet list needs JavaScript. Turn it on in your browser to manage snippets.', 'scriptdock' ); ?></p>
				</noscript>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueues the app on this screen.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function assets() {
		if ( ! Admin::is_screen( self::SLUG ) ) {
			return;
		}
		$asset_file = SCRIPTDOCK_DIR . 'build/snippets.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( 'scriptdock-snippets', SCRIPTDOCK_URL . 'assets/css/app/snippets.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/snippets.css' ) );
		wp_enqueue_script( 'scriptdock-snippets', SCRIPTDOCK_URL . 'build/snippets.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'scriptdock-snippets', 'scriptdock' );
		wp_add_inline_script( 'scriptdock-snippets', 'window.sdSnippets = ' . wp_json_encode( self::bootstrap(), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @return array
	 */
	private static function bootstrap() {
		$query = self::query_from_url();
		$list  = rest_do_request( self::list_request( $query ) );
		if ( $list->is_error() ) {
			$query = array();
			$list  = rest_do_request( self::list_request( $query ) );
		}
		$tags = rest_do_request( new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/tags' ) );

		$screen = get_current_screen();
		$groups = Registry::location_groups();
		$places = array();
		foreach ( Registry::locations() as $key => $location ) {
			$places[] = array(
				'value' => $key,
				'label' => $location['label'],
				'group' => isset( $groups[ $location['group'] ] ) ? $groups[ $location['group'] ] : '',
			);
		}

		return array(
			'query'         => $query,
			'initial'       => $list->is_error() ? null : $list->get_data(),
			'tags'          => $tags->is_error() ? array() : $tags->get_data(),
			'perPage'       => self::per_page(),
			'density'       => self::density(),
			'hiddenColumns' => $screen ? array_values( get_hidden_columns( $screen ) ) : array(),
			'openTags'      => isset( $_GET['tags'] ) && 'manage' === $_GET['tags'], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'canManagePhp'  => Capabilities::can_manage_php(),
			'phpReason'     => Capabilities::php_unavailable_reason(),
			'trashDays'     => defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : 30,
			'types'         => Registry::type_labels(),
			'locations'     => $places,
			'urls'          => array(
				'list'      => Snippets::list_url(),
				'new'       => Snippets::edit_url(),
				'edit'      => Snippets::edit_url( 1 ),
				'library'   => admin_url( 'admin.php?page=' . Library_Page::SLUG ),
				'import'    => admin_url( 'admin.php?page=' . Tools_Page::SLUG ),
				'revisions' => admin_url( 'revision.php' ),
			),
		);
	}

	/**
	 * The list query the URL asks for, in the REST API's terms.
	 *
	 * @return array
	 */
	private static function query_from_url() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads which snippets to show.
		$query = array();
		$text  = array(
			'view'    => 'view',
			's'       => 'search',
			'month'   => 'month',
			'orderby' => 'orderby',
			'order'   => 'order',
		);
		foreach ( $text as $param => $key ) {
			if ( isset( $_GET[ $param ] ) && '' !== $_GET[ $param ] ) {
				$query[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
			}
		}
		foreach ( array( 'type', 'location', 'tag', 'targeting' ) as $param ) {
			if ( isset( $_GET[ $param ] ) && '' !== $_GET[ $param ] ) {
				$query[ $param ] = array_values( array_filter( array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) ) ) ) );
			}
		}
		if ( ! empty( $_GET['paged'] ) ) {
			$query['page'] = max( 1, absint( $_GET['paged'] ) );
		}
		// phpcs:enable
		return $query;
	}

	/**
	 * A list request for a query.
	 *
	 * @param array $query Query.
	 * @return \WP_REST_Request
	 */
	private static function list_request( array $query ) {
		$request = new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/snippets' );
		$request->set_query_params( array_merge( $query, array( 'per_page' => self::per_page() ) ) );
		return $request;
	}
}
