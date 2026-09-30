<?php
/**
 * The app shell on every ScriptDock screen: top bar, notice zone and canvas.
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
 * Prints the top bar above the page and loads the design-system styles.
 *
 * The top bar prints on in_admin_header, before #wpbody, so it sits above
 * every notice. Pages print hr.wp-header-end first inside their .wrap, and
 * WordPress moves notices to just after it: that is the notice zone.
 */
final class Shell {

	/**
	 * Hooks the shell.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'preload_fonts' ), 1 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'render' ), 20 );
	}

	/**
	 * Whether this request shows a ScriptDock screen to someone who may use it.
	 *
	 * @return bool
	 */
	private static function applies() {
		return Admin::is_plugin_screen() && Capabilities::can_manage();
	}

	/**
	 * Component stylesheets in assets/css/app/. Each needs only the tokens
	 * and base styles.
	 */
	const COMPONENT_STYLES = array( 'button', 'form', 'display', 'feedback', 'overlay', 'navigation', 'code', 'surface' );

	/**
	 * Registers the design-system styles and the shell script.
	 *
	 * Each stylesheet is its own handle (scriptdock-tokens, scriptdock-form
	 * and so on). "scriptdock-ui" loads the whole set; the shell needs only
	 * the buttons and the menu, so screens not yet rebuilt stay light.
	 */
	public static function register() {
		if ( wp_style_is( 'scriptdock-ui', 'registered' ) ) {
			return;
		}
		$url = SCRIPTDOCK_URL . 'assets/';
		wp_register_style( 'scriptdock-tokens', $url . 'css/app/tokens.css', array(), Admin::asset_version( 'css/app/tokens.css' ) );
		wp_register_style( 'scriptdock-base', $url . 'css/app/base.css', array( 'scriptdock-tokens' ), Admin::asset_version( 'css/app/base.css' ) );

		$all = array();
		foreach ( self::COMPONENT_STYLES as $name ) {
			$path = 'css/app/' . $name . '.css';
			wp_register_style( 'scriptdock-' . $name, $url . $path, array( 'scriptdock-base' ), Admin::asset_version( $path ) );
			$all[] = 'scriptdock-' . $name;
		}
		wp_register_style( 'scriptdock-ui', false, $all, SCRIPTDOCK_VERSION );
		wp_register_style( 'scriptdock-shell', $url . 'css/app/shell.css', array( 'scriptdock-button', 'scriptdock-overlay' ), Admin::asset_version( 'css/app/shell.css' ) );
		wp_register_script( 'scriptdock-shell', $url . 'js/shell.js', array(), Admin::asset_version( 'js/shell.js' ), true );
	}

	/**
	 * Loads the shell on ScriptDock screens.
	 */
	public static function assets() {
		self::register();
		if ( ! self::applies() ) {
			return;
		}
		wp_enqueue_style( 'scriptdock-shell' );
		wp_enqueue_script( 'scriptdock-shell' );
		self::palette();
	}

	/**
	 * Loads the command palette (S20). It draws nothing until it is opened,
	 * so the cost on a screen nobody opens it on is one keyboard listener.
	 */
	private static function palette() {
		$asset_file = SCRIPTDOCK_DIR . 'build/palette.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;

		wp_enqueue_style( 'scriptdock-palette', SCRIPTDOCK_URL . 'assets/css/app/palette.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/palette.css' ) );
		wp_enqueue_script( 'scriptdock-palette', SCRIPTDOCK_URL . 'build/palette.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'scriptdock-palette', 'scriptdock' );
		wp_add_inline_script(
			'scriptdock-palette',
			'window.sdPalette = ' . wp_json_encode(
				array(
					'urls'     => array(
						'new'         => Snippets::edit_url(),
						'library'     => admin_url( 'admin.php?page=' . Library_Page::SLUG ),
						'global'      => admin_url( 'admin.php?page=' . Global_Page::SLUG ),
						'files'       => admin_url( 'admin.php?page=' . Files_Page::SLUG ),
						'tools'       => admin_url( 'admin.php?page=' . Tools_Page::SLUG ),
						'settings'    => Settings::page_url(),
						'editorTheme' => Settings::page_url( 'editor' ),
						'safeLink'    => Settings::page_url( 'safe-mode' ),
					),
					'safeMode' => array(
						'active'   => Safe_Mode::is_active(),
						'forced'   => Safe_Mode::is_forced(),
						'enterUrl' => Safe_Mode::toggle_url( true ),
						'exitUrl'  => Safe_Mode::toggle_url( false ),
					),
				),
				Admin::JSON_IN_SCRIPT
			) . ';',
			'before'
		);
	}

	/**
	 * Preloads the Latin subset of the UI font, so the top bar does not
	 * redraw when the font arrives.
	 */
	public static function preload_fonts() {
		if ( ! self::applies() ) {
			return;
		}
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( SCRIPTDOCK_URL . 'assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin-wght-normal.woff2' )
		);
	}

	/**
	 * Adds the class that paints the canvas and sets the insets.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( self::applies() ) {
			$classes .= ' sd-screen';
		}
		return $classes;
	}

	/**
	 * The top bar's sections, in display order.
	 *
	 * "primary" sections stay visible when the bar collapses; the rest move
	 * into "More".
	 *
	 * @return array<string, array{label: string, url: string, primary: bool}>
	 */
	public static function sections() {
		$page = static function ( $slug ) {
			return admin_url( 'admin.php?page=' . $slug );
		};

		return array(
			'overview' => array(
				'label'   => __( 'Overview', 'scriptdock' ),
				'url'     => $page( Overview_Page::SLUG ),
				'primary' => true,
			),
			'snippets' => array(
				'label'   => __( 'Snippets', 'scriptdock' ),
				'url'     => Snippets::list_url(),
				'primary' => true,
			),
			'global'   => array(
				'label'   => __( 'Header & Footer', 'scriptdock' ),
				'url'     => $page( Global_Page::SLUG ),
				'primary' => false,
			),
			'library'  => array(
				'label'   => __( 'Library', 'scriptdock' ),
				'url'     => $page( Library_Page::SLUG ),
				'primary' => true,
			),
			'files'    => array(
				'label'   => __( 'Site Files', 'scriptdock' ),
				'url'     => $page( Files_Page::SLUG ),
				'primary' => false,
			),
			'tools'    => array(
				'label'   => __( 'Import & Export', 'scriptdock' ),
				'url'     => $page( Tools_Page::SLUG ),
				'primary' => false,
			),
			'settings' => array(
				'label'   => __( 'Settings', 'scriptdock' ),
				'url'     => $page( Settings_Page::SLUG ),
				'primary' => false,
			),
		);
	}

	/**
	 * The section the current screen belongs to.
	 *
	 * @return string Section key, or '' when none matches.
	 */
	public static function current_section() {
		$screen = get_current_screen();
		if ( $screen && ( Post_Type::NAME === $screen->post_type || Post_Type::TAXONOMY === $screen->taxonomy ) ) {
			return 'snippets';
		}
		$pages = array(
			Overview_Page::SLUG => 'overview',
			Snippets_Page::SLUG => 'snippets',
			Editor_Page::SLUG   => 'snippets',
			Global_Page::SLUG   => 'global',
			Library_Page::SLUG  => 'library',
			Files_Page::SLUG    => 'files',
			Tools_Page::SLUG    => 'tools',
			Settings_Page::SLUG => 'settings',
		);
		foreach ( $pages as $slug => $section ) {
			if ( Admin::is_screen( $slug ) ) {
				return $section;
			}
		}
		return '';
	}

	/**
	 * How many things need the user's attention: snippets switched off after
	 * a fatal error that nobody has dismissed yet, plus active snippets
	 * paused because they changed outside ScriptDock.
	 *
	 * @return int
	 */
	public static function attention_count() {
		$runtime = Compiler::get();
		return count( Notices::all() ) + count( $runtime['untrusted'] );
	}

	/**
	 * WordPress's menu count bubble, for a menu title.
	 *
	 * @param int $count Number of items.
	 * @return string HTML, or '' when there is nothing to count.
	 */
	public static function count_bubble( $count ) {
		if ( $count < 1 ) {
			return '';
		}
		return sprintf(
			' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
			(int) $count,
			esc_html( number_format_i18n( $count ) ),
			esc_html(
				sprintf(
					/* translators: %s: number of snippets */
					_n( '%s snippet needs attention', '%s snippets need attention', $count, 'scriptdock' ),
					number_format_i18n( $count )
				)
			)
		);
	}

	/**
	 * Prints the top bar.
	 */
	public static function render() {
		if ( ! self::applies() ) {
			return;
		}

		$sections  = self::sections();
		$current   = self::current_section();
		$secondary = array_filter(
			$sections,
			static function ( $section ) {
				return ! $section['primary'];
			}
		);
		$in_more   = isset( $secondary[ $current ] );
		$safe_mode = Safe_Mode::is_active();
		$home      = reset( $sections );
		$new_url   = Snippets::edit_url();
		?>
		<header class="sd-app sd-shell">
			<div class="sd-topbar<?php echo $safe_mode ? ' is-safe-mode' : ''; ?>">
				<a class="sd-topbar__brand" href="<?php echo esc_url( $home['url'] ); ?>">
					<span class="sd-brand-dot" aria-hidden="true"></span>
					<span class="sd-wordmark">scriptdock</span>
				</a>

				<?php self::render_safe_mode_pill( $safe_mode ); ?>

				<nav class="sd-topbar__nav" aria-label="<?php esc_attr_e( 'ScriptDock', 'scriptdock' ); ?>">
					<ul class="sd-topbar__tabs">
						<?php foreach ( $sections as $key => $section ) : ?>
							<li class="sd-topbar__item<?php echo $section['primary'] ? ' is-primary' : ''; ?>">
								<a class="sd-topbar__tab" href="<?php echo esc_url( $section['url'] ); ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
					<div class="sd-topbar__more">
						<button type="button" class="sd-topbar__tab sd-topbar__more-toggle<?php echo $in_more ? ' is-current' : ''; ?>" aria-expanded="false" aria-controls="sd-topbar-more" data-sd-disclosure>
							<?php esc_html_e( 'More', 'scriptdock' ); ?>
							<?php Icons::render( 'chevron-down', 13, 2.4 ); ?>
						</button>
						<ul id="sd-topbar-more" class="sd-menu" hidden>
							<?php foreach ( $secondary as $key => $section ) : ?>
								<li><a class="sd-menu__item" href="<?php echo esc_url( $section['url'] ); ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</nav>

				<div class="sd-topbar__actions">
					<?php self::render_search_button( 'sd-topbar__search' ); ?>

					<a class="sd-button sd-button--primary sd-topbar__new" href="<?php echo esc_url( $new_url ); ?>" aria-label="<?php esc_attr_e( 'New snippet', 'scriptdock' ); ?>">
						<span class="sd-topbar__new-full"><?php esc_html_e( 'New snippet', 'scriptdock' ); ?></span>
						<span class="sd-topbar__new-short"><?php echo esc_html_x( 'New', 'short label for the New snippet button', 'scriptdock' ); ?></span>
						<span class="sd-button__dot" aria-hidden="true"><?php Icons::render( 'plus', 15, 2.2 ); ?></span>
					</a>

					<button type="button" class="sd-icon-button sd-topbar__menu-toggle" aria-expanded="false" aria-controls="sd-topbar-menu" aria-label="<?php esc_attr_e( 'Menu', 'scriptdock' ); ?>" data-sd-disclosure>
						<?php Icons::render( 'menu', 17, 2 ); ?>
					</button>
					<a class="sd-icon-button sd-icon-button--ink sd-topbar__new-icon" href="<?php echo esc_url( $new_url ); ?>" aria-label="<?php esc_attr_e( 'New snippet', 'scriptdock' ); ?>">
						<?php Icons::render( 'plus', 17, 2.4 ); ?>
					</a>

					<ul id="sd-topbar-menu" class="sd-menu sd-menu--end" hidden>
						<?php foreach ( $sections as $key => $section ) : ?>
							<li><a class="sd-menu__item" href="<?php echo esc_url( $section['url'] ); ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></a></li>
						<?php endforeach; ?>
						<li class="sd-topbar__menu-search"<?php echo wp_script_is( 'scriptdock-palette', 'enqueued' ) ? '' : ' hidden'; ?>>
							<span class="sd-menu__separator" role="presentation"></span>
							<button type="button" class="sd-menu__item" data-sd-command-palette>
								<?php Icons::render( 'search', 15, 1.8 ); ?>
								<?php esc_html_e( 'Search', 'scriptdock' ); ?>
							</button>
						</li>
					</ul>
				</div>
			</div>
		</header>
		<?php
	}

	/**
	 * Prints the search button, which opens the command palette (S20). It is
	 * only printed when the palette's script is on the page, so it is never
	 * a button that does nothing.
	 *
	 * @param string $class_name Extra class.
	 */
	private static function render_search_button( $class_name ) {
		if ( ! wp_script_is( 'scriptdock-palette', 'enqueued' ) ) {
			return;
		}
		?>
		<button type="button" class="<?php echo esc_attr( $class_name ); ?>" data-sd-command-palette>
			<?php Icons::render( 'search', 16, 1.8 ); ?>
			<span class="sd-topbar__search-label"><?php esc_html_e( 'Search', 'scriptdock' ); ?></span>
			<kbd class="sd-kbd" aria-hidden="true" data-mac="<?php echo esc_attr_x( '⌘K', 'keyboard shortcut on macOS', 'scriptdock' ); ?>" data-other="<?php echo esc_attr_x( 'Ctrl K', 'keyboard shortcut on Windows and Linux', 'scriptdock' ); ?>"><?php echo esc_html_x( '⌘K', 'keyboard shortcut on macOS', 'scriptdock' ); ?></kbd>
		</button>
		<?php
	}

	/**
	 * Prints the safe-mode pill while safe mode is on for this browser, or
	 * for everyone when wp-config.php forces it.
	 *
	 * @param bool $active Whether safe mode is on.
	 */
	private static function render_safe_mode_pill( $active ) {
		if ( ! $active ) {
			return;
		}
		$forced = Safe_Mode::is_forced();
		?>
		<div class="sd-safe-pill<?php echo $forced ? ' sd-safe-pill--forced' : ''; ?>">
			<?php Icons::render( 'alert', 16, 1.8 ); ?>
			<span class="sd-safe-pill__label"><?php esc_html_e( 'Safe mode on', 'scriptdock' ); ?></span>
			<?php if ( ! $forced ) : ?>
				<a class="sd-safe-pill__exit" href="<?php echo esc_url( Safe_Mode::toggle_url( false ) ); ?>" aria-label="<?php esc_attr_e( 'Exit safe mode', 'scriptdock' ); ?>"><?php esc_html_e( 'Exit', 'scriptdock' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}
}
