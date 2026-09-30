<?php
/**
 * The snippet editor (S04) with its smart tags palette (S07), drawn by the
 * editor app.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Capabilities;
use ScriptDock\Registry;
use ScriptDock\Rest\Rest;
use ScriptDock\Rest\Snippets_Controller;
use ScriptDock\Safe_Mode;
use ScriptDock\Settings;
use ScriptDock\Smart_Tags;
use ScriptDock\Snippet;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the root the editor app mounts on, with everything it needs to
 * draw a snippet without waiting for a request: the snippet, the code
 * editor's settings, placements, types, smart tags, tags, safe mode and the
 * viewer's editor theme.
 *
 * This is the editor: Editor_Page owns the menu slug and hands over to it.
 */
final class Editor_App {

	/**
	 * User option: the code editor's theme, light or dark.
	 */
	const THEME_OPTION = 'scriptdock_editor_theme';

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'scriptdock-editor-app';

	/**
	 * The viewer's code editor theme: their own choice, else the site's
	 * default from Settings.
	 *
	 * @return string light or dark.
	 */
	public static function theme() {
		$theme = get_user_option( self::THEME_OPTION );
		if ( in_array( $theme, array( 'light', 'dark' ), true ) ) {
			return $theme;
		}
		return 'dark' === Settings::get( 'editor_theme' ) ? 'dark' : 'light';
	}

	/**
	 * Prints the page. The app draws everything inside the root; notices
	 * land above it, after the header marker.
	 */
	public static function render() {
		?>
		<div class="wrap sd-wrap">
			<hr class="wp-header-end">
			<div id="sd-editor" class="sd-app sd-editor-screen">
				<noscript>
					<p><?php esc_html_e( 'The snippet editor needs JavaScript. Turn it on in your browser to edit snippets.', 'scriptdock' ); ?></p>
				</noscript>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueues the app, WordPress's code editor and the editor's styles.
	 */
	public static function assets() {
		$asset_file = SCRIPTDOCK_DIR . 'build/editor.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		// Also enqueues CodeMirror, its linters and wp.codeEditor. False when
		// the viewer turned syntax highlighting off in their profile.
		$editors = Admin::code_editor_settings( Registry::TYPES );

		wp_enqueue_style( self::HANDLE, SCRIPTDOCK_URL . 'assets/css/app/editor.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/editor.css' ) );
		wp_enqueue_style( self::HANDLE . '-targeting', SCRIPTDOCK_URL . 'assets/css/app/targeting.css', array( self::HANDLE ), Admin::asset_version( 'css/app/targeting.css' ) );
		wp_enqueue_script( self::HANDLE, SCRIPTDOCK_URL . 'build/editor.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'scriptdock' );
		wp_add_inline_script( self::HANDLE, 'window.sdEditor = ' . wp_json_encode( self::bootstrap( $editors ), Admin::JSON_IN_SCRIPT ) . ';', 'before' );
	}

	/**
	 * What the app needs before its first request.
	 *
	 * @param array|false $editors Code editor settings per type.
	 * @return array
	 */
	private static function bootstrap( $editors ) {
		$tags   = rest_do_request( new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/tags' ) );
		$groups = Registry::location_groups();
		$places = array();
		foreach ( Registry::locations() as $key => $location ) {
			$places[] = array(
				'value'       => $key,
				'label'       => $location['label'],
				'short'       => Registry::short_label( $key ),
				'group'       => isset( $groups[ $location['group'] ] ) ? $groups[ $location['group'] ] : '',
				'groupKey'    => $location['group'],
				'types'       => $location['types'],
				'files'       => ! empty( $location['files'] ),
				'description' => $location['description'],
			);
		}
		$types = array();
		foreach ( Registry::type_labels() as $type => $label ) {
			$types[] = array(
				'value'           => $type,
				'label'           => $label,
				'allowed'         => Capabilities::can_edit_type( $type ),
				'defaultLocation' => Registry::default_location( $type ),
			);
		}

		return array(
			'snippet'     => self::snippet(),
			'codeEditors' => $editors ? $editors : null,
			'types'       => $types,
			'locations'   => $places,
			'smartTags'   => self::smart_tags(),
			'tags'        => $tags->is_error() ? array() : array_values( wp_list_pluck( $tags->get_data(), 'name' ) ),
			'canPhp'      => Capabilities::can_manage_php(),
			'phpReason'   => Capabilities::php_unavailable_reason(),
			'theme'       => self::theme(),
			'assetFiles'  => (bool) Settings::get( 'asset_files' ),
			'consent'     => self::consent(),
			'safeMode'    => array(
				'active'  => Safe_Mode::is_active(),
				'forced'  => Safe_Mode::is_forced(),
				'exitUrl' => Safe_Mode::toggle_url( false ),
			),
			// A link from WordPress's own revisions screen opens the history
			// drawer on that version.
			'history'     => isset( $_GET['history'] ) ? absint( $_GET['history'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which version to show.
			'urls'        => array(
				'list'     => Snippets::list_url(),
				'new'      => Snippets::edit_url(),
				'revision' => admin_url( 'revision.php' ),
			),
		);
	}

	/**
	 * The snippet the URL asks for, as the REST API describes it, or a new
	 * one with its type's defaults. Null when it does not exist.
	 *
	 * @return array|null
	 */
	private static function snippet() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads which snippet to open.
		$id = isset( $_GET['snippet'] ) ? absint( $_GET['snippet'] ) : 0;
		if ( $id ) {
			$response = rest_do_request( new \WP_REST_Request( 'GET', '/' . Rest::ROUTE_NAMESPACE . '/snippets/' . $id ) );
			return $response->is_error() ? null : $response->get_data();
		}
		$type = isset( $_GET['type'] ) ? Registry::sanitize_type( sanitize_key( wp_unslash( $_GET['type'] ) ) ) : 'html';
		// phpcs:enable
		if ( ! Capabilities::can_edit_type( $type ) ) {
			$type = 'html';
		}
		$snippet           = new Snippet();
		$snippet->type     = $type;
		$snippet->location = Registry::default_location( $type );
		return ( new Snippets_Controller() )->blank( $snippet );
	}

	/**
	 * Smart tag groups for the palette. WooCommerce's stay listed, marked
	 * unavailable, when WooCommerce is not active.
	 *
	 * @return array label, available, note, tags ( tag, description ).
	 */
	public static function smart_tags() {
		$list = static function ( array $tags ) {
			$items = array();
			foreach ( $tags as $tag => $description ) {
				$items[] = array(
					'tag'         => $tag,
					'description' => $description,
				);
			}
			return $items;
		};

		$groups = array();
		foreach ( Smart_Tags::reference() as $label => $tags ) {
			$groups[] = array(
				'label'     => $label,
				'available' => true,
				'tags'      => $list( $tags ),
			);
		}
		if ( ! Registry::woocommerce_active() ) {
			$groups[] = array(
				'label'     => __( 'WooCommerce', 'scriptdock' ),
				'available' => false,
				'note'      => __( 'WooCommerce is not active on this site, so these tags come out empty. They stay listed so you know they exist.', 'scriptdock' ),
				'tags'      => $list( Smart_Tags::woocommerce_reference() ),
			);
		}
		return $groups;
	}

	/**
	 * Whether a consent plugin speaks the WP Consent API, and which one when
	 * it can be named.
	 *
	 * @return array api, plugin.
	 */
	private static function consent() {
		$api     = function_exists( 'wp_has_consent' );
		$plugins = array(
			'Complianz'      => defined( 'cmplz_version' ),
			'Cookiebot'      => defined( 'CYBOT_COOKIEBOT_VERSION' ),
			'Borlabs Cookie' => defined( 'BORLABS_COOKIE_VERSION' ),
		);
		$name    = '';
		foreach ( $plugins as $label => $found ) {
			if ( $found ) {
				$name = $label;
				break;
			}
		}
		return array(
			'api'    => $api,
			'plugin' => $api ? $name : '',
		);
	}
}
