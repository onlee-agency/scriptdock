<?php
/**
 * Admin bootstrap: menus, assets, notices and redirects.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Capabilities;
use ScriptDock\Compiler;
use ScriptDock\Notices;
use ScriptDock\Post_Type;
use ScriptDock\Safe_Mode;
use ScriptDock\Settings;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
final class Admin {

	/**
	 * Top-level menu slug: the Overview, ScriptDock's landing page.
	 */
	const MENU = Overview_Page::SLUG;

	/**
	 * The Tags menu item: the Snippets screen with Manage tags open.
	 */
	const TAGS_MENU = 'admin.php?page=scriptdock-snippets&tags=manage';

	/**
	 * wp_json_encode() flags for data printed inside a <script> tag. With
	 * < > and & written as \u escapes, nothing in a title or a note can end
	 * the tag or open a comment inside it; the values themselves are the same.
	 */
	const JSON_IN_SCRIPT = JSON_HEX_TAG | JSON_HEX_AMP;

	/**
	 * Hooks admin screens.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'redirect_new' ) );
		add_action( 'load-post.php', array( __CLASS__, 'redirect_edit' ) );
		add_action( 'load-revision.php', array( __CLASS__, 'prepare_revision_restore' ) );
		add_filter( 'wp_prepare_revision_for_js', array( __CLASS__, 'revision_for_js' ), 10, 3 );
		add_filter( 'get_edit_post_link', array( __CLASS__, 'edit_link' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( SCRIPTDOCK_FILE ), array( __CLASS__, 'plugin_links' ) );
		add_action( 'admin_post_scriptdock_dismiss_notice', array( __CLASS__, 'dismiss_notice' ) );

		Shell::init();
		Onboarding_Page::init();
		Overview_Page::init();
		Snippets_Page::init();
		Editor_Page::init();
		Page_Meta_Box::init();
		Block_Editor::init();
		Safe_Mode_Banner::init();
		No_Access::init();
		Global_Page::init();
		Library_Page::init();
		Tools_Page::init();
		Files_Page::init();
		Settings_Page::init();
	}

	/**
	 * Registers the menu.
	 */
	public static function menu() {
		$cap    = Capabilities::MANAGE;
		$bubble = Capabilities::can_manage() ? Shell::count_bubble( Shell::attention_count() ) : '';

		add_menu_page(
			__( 'ScriptDock', 'scriptdock' ),
			__( 'ScriptDock', 'scriptdock' ) . $bubble,
			$cap,
			self::MENU,
			array( Overview_Page::class, 'render' ),
			Icons::menu_icon(),
			81
		);

		// The Overview has the menu's own slug, so ScriptDock opens there and
		// the submenu reads Overview, All Snippets, and the rest.
		add_submenu_page( self::MENU, __( 'ScriptDock Overview', 'scriptdock' ), __( 'Overview', 'scriptdock' ), $cap, Overview_Page::SLUG, array( Overview_Page::class, 'render' ) );
		add_submenu_page( self::MENU, __( 'Snippets', 'scriptdock' ), __( 'All Snippets', 'scriptdock' ) . $bubble, $cap, Snippets_Page::SLUG, array( Snippets_Page::class, 'render' ) );
		add_submenu_page( self::MENU, __( 'Add Snippet', 'scriptdock' ), __( 'Add Snippet', 'scriptdock' ), $cap, Editor_Page::SLUG, array( Editor_Page::class, 'render' ) );
		add_submenu_page( self::MENU, __( 'Header & Footer', 'scriptdock' ), __( 'Header & Footer', 'scriptdock' ), $cap, Global_Page::SLUG, array( Global_Page::class, 'render' ) );
		add_submenu_page( self::MENU, __( 'Snippet Library', 'scriptdock' ), __( 'Library', 'scriptdock' ), $cap, Library_Page::SLUG, array( Library_Page::class, 'render' ) );
		add_submenu_page( self::MENU, __( 'Snippet Tags', 'scriptdock' ), __( 'Tags', 'scriptdock' ), $cap, self::TAGS_MENU );
		add_submenu_page( self::MENU, __( 'Site Files', 'scriptdock' ), __( 'Site Files', 'scriptdock' ), $cap, Files_Page::SLUG, array( Files_Page::class, 'render' ) );
		add_submenu_page( self::MENU, __( 'Import & Export', 'scriptdock' ), __( 'Import & Export', 'scriptdock' ), $cap, Tools_Page::SLUG, array( Tools_Page::class, 'render' ) );
		add_submenu_page( self::MENU, __( 'ScriptDock Settings', 'scriptdock' ), __( 'Settings', 'scriptdock' ), $cap, Settings_Page::SLUG, array( Settings_Page::class, 'render' ) );
	}

	/**
	 * Keeps the menu open on the tag screen.
	 *
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public static function parent_file( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && Post_Type::TAXONOMY === $screen->taxonomy ) {
			return self::MENU;
		}
		return $parent_file;
	}

	/**
	 * Highlights "All Snippets" while editing an existing snippet.
	 *
	 * @param string|null $submenu_file Submenu file.
	 * @return string|null
	 */
	public static function submenu_file( $submenu_file ) {
		$screen = get_current_screen();
		if ( ( $screen && Post_Type::TAXONOMY === $screen->taxonomy ) || ( self::is_screen( Snippets_Page::SLUG ) && isset( $_GET['tags'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::TAGS_MENU;
		}
		if ( self::is_screen( Editor_Page::SLUG ) && ! empty( $_GET['snippet'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return Snippets_Page::SLUG;
		}
		return $submenu_file;
	}

	/**
	 * Whether the current screen is one of our pages.
	 *
	 * @param string $slug Page slug.
	 * @return bool
	 */
	public static function is_screen( $slug ) {
		return isset( $_GET['page'] ) && sanitize_key( wp_unslash( $_GET['page'] ) ) === $slug; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Whether the current screen belongs to ScriptDock.
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		$is = Post_Type::NAME === $screen->post_type || Post_Type::TAXONOMY === $screen->taxonomy;
		foreach ( array( Overview_Page::SLUG, Snippets_Page::SLUG, Editor_Page::SLUG, Global_Page::SLUG, Library_Page::SLUG, Tools_Page::SLUG, Files_Page::SLUG, Settings_Page::SLUG ) as $slug ) {
			if ( ! $is && self::is_screen( $slug ) ) {
				$is = true;
			}
		}

		/**
		 * Filters whether the current admin screen belongs to ScriptDock. A
		 * ScriptDock screen gets the app shell and ScriptDock's styles.
		 *
		 * @param bool       $is     Whether it is a ScriptDock screen.
		 * @param \WP_Screen $screen The current screen.
		 */
		return (bool) apply_filters( 'scriptdock_is_admin_screen', $is, $screen );
	}

	/**
	 * Registers and enqueues admin assets.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function assets( $hook_suffix ) {
		$url = SCRIPTDOCK_URL . 'assets/';

		wp_register_style( 'scriptdock-admin', $url . 'css/admin.css', array(), self::asset_version( 'css/admin.css' ) );
		wp_register_script( 'scriptdock-admin', $url . 'js/admin.js', array( 'jquery', 'wp-i18n', 'wp-a11y' ), self::asset_version( 'js/admin.js' ), true );
		wp_set_script_translations( 'scriptdock-admin', 'scriptdock' );
		wp_localize_script(
			'scriptdock-admin',
			'scriptdockAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'scriptdock_ajax' ),
				'editorTheme' => Settings::get( 'editor_theme' ),
			)
		);

		// Registered, not enqueued: the only screen that needs these is the
		// page scripts meta box, which asks for them itself. ScriptDock's own
		// screens are React and load the design system instead.

		/**
		 * Lets admin page classes enqueue their own assets.
		 *
		 * @param string $hook_suffix Current admin page.
		 */
		do_action( 'scriptdock_admin_assets', $hook_suffix );
	}

	/**
	 * Version string for a plugin asset: the file time while SCRIPT_DEBUG is on,
	 * so browsers never use stale files during development.
	 *
	 * @param string $path Path inside assets/.
	 * @return string
	 */
	public static function asset_version( $path ) {
		return \ScriptDock\Plugin::asset_version( $path );
	}

	/**
	 * Enqueues the code editor for the given types and returns the settings.
	 *
	 * @param string[] $types Snippet types or languages.
	 * @return array|false Settings per type, or false if the user turned syntax highlighting off.
	 */
	public static function code_editor_settings( array $types ) {
		$mimes    = array(
			'php'       => 'text/x-php',
			'universal' => 'application/x-httpd-php',
			'html'      => 'text/html',
			'css'       => 'text/css',
			'js'        => 'text/javascript',
		);
		$settings = array();
		foreach ( $types as $type ) {
			if ( ! isset( $mimes[ $type ] ) ) {
				continue;
			}
			$result = wp_enqueue_code_editor(
				array(
					'type'       => $mimes[ $type ],
					'codemirror' => array(
						'indentUnit'     => 4,
						'tabSize'        => 4,
						'indentWithTabs' => true,
						'lineWrapping'   => false,
					),
				)
			);
			if ( false === $result ) {
				return false;
			}
			if ( 'php' === $type ) {
				// Snippets are written without the opening tag.
				$result['codemirror']['mode'] = array(
					'name'      => 'php',
					'startOpen' => true,
				);
			}
			if ( 'universal' === $type ) {
				$result['codemirror']['mode'] = 'application/x-httpd-php';
			}
			$settings[ $type ] = $result;
		}
		return $settings;
	}

	/**
	 * Admin notices: safe mode, deactivated snippets, snippets waiting for review.
	 */
	public static function notices() {
		if ( ! Capabilities::can_manage() ) {
			return;
		}

		// Safe_Mode_Banner takes over on the two screens that need more than a
		// line: the one the recovery link lands on, and a site forced into
		// safe mode by wp-config.php. The editor says it in its own banner.
		if ( Safe_Mode::is_active() && ! Safe_Mode_Banner::shows() && ! self::is_screen( Editor_Page::SLUG ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'ScriptDock safe mode is on.', 'scriptdock' ) . '</strong> ';
			if ( Safe_Mode::is_forced() ) {
				esc_html_e( 'No snippets run anywhere on the site. Remove SCRIPTDOCK_SAFE_MODE from wp-config.php to turn it off.', 'scriptdock' );
			} else {
				esc_html_e( 'No snippets run for your browser, so you can fix a broken snippet. Visitors are not affected.', 'scriptdock' );
				echo ' <a class="button button-small" href="' . esc_url( Safe_Mode::toggle_url( false ) ) . '">' . esc_html__( 'Exit safe mode', 'scriptdock' ) . '</a>';
			}
			echo '</p></div>';
		}

		foreach ( Notices::all() as $id => $notice ) {
			$dismiss = wp_nonce_url( add_query_arg( array( 'action' => 'scriptdock_dismiss_notice', 'id' => (int) $id ), admin_url( 'admin-post.php' ) ), 'scriptdock_dismiss_notice' );
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: 1: snippet title, 2: error message */
				esc_html__( 'ScriptDock deactivated the snippet “%1$s” because it caused a fatal error: %2$s', 'scriptdock' ),
				'<a href="' . esc_url( Snippets::edit_url( (int) $id ) ) . '"><strong>' . esc_html( $notice['title'] ) . '</strong></a>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				'<code>' . esc_html( $notice['message'] ) . ( $notice['line'] ? ' (' . esc_html( sprintf( /* translators: %d: line number */ __( 'line %d', 'scriptdock' ), $notice['line'] ) ) . ')' : '' ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			);
			echo ' <a href="' . esc_url( Snippets::edit_url( (int) $id ) ) . '">' . esc_html__( 'Fix it', 'scriptdock' ) . '</a> | <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'scriptdock' ) . '</a>';
			echo '</p></div>';
		}

		if ( self::is_plugin_screen() ) {
			$data = Compiler::get();
			if ( ! empty( $data['untrusted'] ) ) {
				echo '<div class="notice notice-warning"><p>';
				printf(
					esc_html(
						/* translators: %d: number of snippets */
						_n(
							'%d active snippet was changed outside ScriptDock and is paused until you review it. This protects your site from code injected directly into the database.',
							'%d active snippets were changed outside ScriptDock and are paused until you review them. This protects your site from code injected directly into the database.',
							count( $data['untrusted'] ),
							'scriptdock'
						)
					),
					count( $data['untrusted'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Integer.
				);
				echo ' <a href="' . esc_url( Snippets::list_url( array( 'view' => 'review' ) ) ) . '">' . esc_html__( 'Review snippets', 'scriptdock' ) . '</a></p></div>';
			}
		}
	}

	/**
	 * Dismisses a stored error notice.
	 */
	public static function dismiss_notice() {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'scriptdock' ), 403 );
		}
		check_admin_referer( 'scriptdock_dismiss_notice' );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $id ) {
			Notices::remove( $id );
		} else {
			Notices::clear();
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Sends "Add New" to the snippet editor.
	 */
	public static function redirect_new() {
		if ( isset( $_GET['post_type'] ) && Post_Type::NAME === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( Snippets::edit_url() );
			exit;
		}
	}

	/**
	 * Sends the core edit screen to the snippet editor.
	 */
	public static function redirect_edit() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id && 'edit' === $action && Post_Type::NAME === get_post_type( $post_id ) ) {
			wp_safe_redirect( Snippets::edit_url( $post_id ) );
			exit;
		}
	}

	/**
	 * A request variable read the way the revisions screen reads it, through
	 * wp_reset_vars(): a non-empty POST value wins, otherwise GET. Reading it
	 * any other way could check a different revision from the one core restores.
	 *
	 * @param string $name Variable name.
	 * @return string
	 */
	private static function revision_screen_var( $name ) {
		// phpcs:disable WordPress.Security.NonceVerification -- Read exactly as core reads it; core checks the nonce afterwards.
		$value = ! empty( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ( ! empty( $_GET[ $name ] ) ? wp_unslash( $_GET[ $name ] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		// phpcs:enable
		return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	}

	/**
	 * On the revisions screen for a snippet: refuses a restore the user may
	 * not make, explains why at the top of the screen, and stops content
	 * filters from changing code when a revision is restored.
	 *
	 * Runs before core handles the restore. Read before nonce verification on
	 * purpose: it can only refuse, and core checks the nonce afterwards.
	 */
	public static function prepare_revision_restore() {
		$revision_id = absint( self::revision_screen_var( 'revision' ) );
		$revision    = $revision_id ? get_post( $revision_id ) : null;
		if ( ! $revision || Post_Type::NAME !== get_post_type( $revision->post_parent ) ) {
			return;
		}

		$snippet = \ScriptDock\Snippet::from_post( get_post( $revision->post_parent ) );
		$action  = self::revision_screen_var( 'action' );

		// ScriptDock has its own history, so looking at a snippet's revisions
		// goes there. A restore already under way is left to core, which our
		// own hook follows by signing the code again.
		if ( 'restore' !== $action ) {
			wp_safe_redirect(
				add_query_arg( 'history', $revision_id, Snippets::edit_url( $snippet->id ) )
			);
			exit;
		}

		$error = Snippets::revision_restore_error( $snippet );
		if ( $error ) {
			if ( 'restore' === $action ) {
				wp_die(
					esc_html( $error->get_error_message() ),
					esc_html__( 'Restoring is not available', 'scriptdock' ),
					array(
						'response'  => 403,
						'back_link' => true,
					)
				);
			}
			add_action(
				'admin_notices',
				static function () use ( $error ) {
					printf(
						'<div class="notice notice-info"><p>%1$s %2$s</p></div>',
						esc_html__( 'You can look through these revisions, but not restore them.', 'scriptdock' ),
						esc_html( $error->get_error_message() )
					);
				}
			);
			return;
		}

		\ScriptDock\Snippet::suspend_content_filters();
	}

	/**
	 * Turns off the Restore button on the revisions screen for a snippet the
	 * user may not edit. The notice from prepare_revision_restore() says why.
	 *
	 * @param array    $data     Revision data for the revisions screen.
	 * @param \WP_Post $revision Revision.
	 * @param \WP_Post $post     Parent post.
	 * @return array
	 */
	public static function revision_for_js( $data, $revision, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Filter signature.
		if ( $post instanceof \WP_Post && Post_Type::NAME === $post->post_type && Snippets::revision_restore_error( \ScriptDock\Snippet::from_post( $post ) ) ) {
			$data['restoreUrl'] = false;
		}
		return $data;
	}

	/**
	 * Points edit links for snippets at the snippet editor.
	 *
	 * @param string $link    Link.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function edit_link( $link, $post_id ) {
		if ( Post_Type::NAME === get_post_type( $post_id ) ) {
			return Snippets::edit_url( $post_id );
		}
		return $link;
	}

	/**
	 * Plugin list links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function plugin_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( Snippets::list_url() ) . '">' . esc_html__( 'Snippets', 'scriptdock' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . Settings_Page::SLUG ) ) . '">' . esc_html__( 'Settings', 'scriptdock' ) . '</a>'
		);
		return $links;
	}
}
