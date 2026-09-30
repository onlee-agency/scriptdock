<?php
/**
 * Admin bar inspector.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * S15: what ScriptDock is doing on the page you are looking at.
 *
 * The toolbar item reads "ScriptDock · 5" and opens a panel listing the
 * snippets that ran, whether the page has code of its own, and the two
 * things you are most likely to want next. In safe mode it turns red and
 * says so instead.
 *
 * The toolbar is WordPress's, so the styles here stay inside our own nodes
 * and never touch the rest of it.
 */
final class Admin_Bar {

	/**
	 * Hooks the menu.
	 */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'menu' ), 100 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	/**
	 * Whether the inspector shows on this request.
	 *
	 * @return bool
	 */
	private static function applies() {
		return ! is_admin() && is_admin_bar_showing() && Settings::get( 'admin_bar' ) && Capabilities::can_manage();
	}

	/**
	 * Loads the panel's styles.
	 */
	public static function styles() {
		if ( ! self::applies() ) {
			return;
		}
		wp_enqueue_style(
			'scriptdock-admin-bar',
			SCRIPTDOCK_URL . 'assets/css/admin-bar.css',
			array( 'admin-bar' ),
			Plugin::asset_version( 'css/admin-bar.css' )
		);
	}

	/**
	 * Adds the menu.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public static function menu( $bar ) {
		if ( ! self::applies() ) {
			return;
		}

		if ( Safe_Mode::is_active() ) {
			self::safe_mode_menu( $bar );
			return;
		}

		$items   = self::snippets_for_page();
		$page_id = Page_Scripts::current_post_id();
		$page    = $page_id ? Page_Scripts::get( $page_id ) : null;

		$bar->add_node(
			array(
				'id'    => 'scriptdock',
				'title' => '<span class="sd-bar__dot" aria-hidden="true"></span><span class="ab-label">'
					. esc_html(
						sprintf(
							/* translators: %d: how many snippets ran on this page. */
							__( 'ScriptDock · %d', 'scriptdock' ),
							count( $items )
						)
					) . '</span>',
				'href'  => Snippets::list_url(),
				'meta'  => array( 'class' => 'sd-bar' ),
			)
		);

		$bar->add_group(
			array(
				'parent' => 'scriptdock',
				'id'     => 'scriptdock-ran',
				'meta'   => array( 'class' => 'sd-bar__group sd-bar__group--list' ),
			)
		);

		$bar->add_node(
			array(
				'parent' => 'scriptdock-ran',
				'id'     => 'scriptdock-heading',
				'title'  => '<span class="sd-bar__heading">' . esc_html(
					$items
						? sprintf(
							/* translators: %d: how many snippets ran. */
							_n( '%d snippet ran on this page', '%d snippets ran on this page', count( $items ), 'scriptdock' ),
							count( $items )
						)
						: __( 'No snippets ran on this page', 'scriptdock' )
				) . '</span>',
				'meta'   => array( 'class' => 'sd-bar__row sd-bar__row--heading' ),
			)
		);

		foreach ( $items as $id => $item ) {
			$bar->add_node(
				array(
					'parent' => 'scriptdock-ran',
					'id'     => 'scriptdock-snippet-' . $id,
					'title'  => '<span class="sd-bar__mark sd-bar__mark--' . esc_attr( $item['type'] ) . '" aria-hidden="true"></span>'
						. '<span class="sd-bar__name" dir="auto">' . esc_html( $item['title'] ) . '</span>'
						. '<span class="sd-bar__where">' . esc_html( $item['where'] ) . '</span>',
					'href'   => Snippets::edit_url( $id ),
					'meta'   => array( 'class' => 'sd-bar__row' ),
				)
			);
		}

		self::page_nodes( $bar, $page );

		$bar->add_group(
			array(
				'parent' => 'scriptdock',
				'id'     => 'scriptdock-do',
				'meta'   => array( 'class' => 'sd-bar__group' ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'scriptdock-do',
				'id'     => 'scriptdock-add',
				'title'  => '<span class="sd-bar__name">' . esc_html__( 'Add a snippet', 'scriptdock' ) . '</span>',
				'href'   => Snippets::edit_url(),
				'meta'   => array( 'class' => 'sd-bar__row sd-bar__row--do' ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'scriptdock-do',
				'id'     => 'scriptdock-safe',
				'title'  => '<span class="sd-bar__name">' . esc_html__( 'Enter safe mode (this browser)', 'scriptdock' ) . '</span>',
				'href'   => Safe_Mode::toggle_url( true ),
				'meta'   => array( 'class' => 'sd-bar__row sd-bar__row--do' ),
			)
		);
	}

	/**
	 * Rows about the page's own code and the snippets it switches off.
	 *
	 * @param \WP_Admin_Bar $bar  Admin bar.
	 * @param array|null    $page Page data, or null when the page cannot have any.
	 */
	private static function page_nodes( $bar, $page ) {
		if ( ! $page ) {
			return;
		}

		if ( Page_Scripts::has_code( $page ) ) {
			$bar->add_node(
				array(
					'parent' => 'scriptdock-ran',
					'id'     => 'scriptdock-page',
					'title'  => '<span class="sd-bar__mark sd-bar__mark--page" aria-hidden="true"></span>'
						. '<span class="sd-bar__name">' . esc_html__( 'This page has its own code', 'scriptdock' ) . '</span>'
						. '<span class="sd-bar__where">' . esc_html( self::page_summary( $page ) ) . '</span>',
					'href'   => get_edit_post_link( Page_Scripts::current_post_id(), 'url' ),
					'meta'   => array( 'class' => 'sd-bar__row' ),
				)
			);
		}

		$off = $page['disable_all'] ? __( 'All site-wide code is off here', 'scriptdock' ) : (
			$page['disable']
				? sprintf(
					/* translators: %d: how many snippets are switched off on this page. */
					_n( '%d snippet is switched off here', '%d snippets are switched off here', count( $page['disable'] ), 'scriptdock' ),
					count( $page['disable'] )
				)
				: ''
		);
		if ( '' === $off ) {
			return;
		}

		$bar->add_node(
			array(
				'parent' => 'scriptdock-ran',
				'id'     => 'scriptdock-page-off',
				'title'  => '<span class="sd-bar__mark sd-bar__mark--off" aria-hidden="true"></span>'
					. '<span class="sd-bar__name sd-bar__name--muted">' . esc_html( $off ) . '</span>',
				'href'   => get_edit_post_link( Page_Scripts::current_post_id(), 'url' ),
				'meta'   => array( 'class' => 'sd-bar__row' ),
			)
		);
	}

	/**
	 * What the page's own code amounts to, for example "CSS · 14 lines".
	 *
	 * @param array $page Page data.
	 * @return string
	 */
	private static function page_summary( array $page ) {
		$labels = array(
			'head'   => __( 'Header', 'scriptdock' ),
			'body'   => __( 'Body', 'scriptdock' ),
			'footer' => __( 'Footer', 'scriptdock' ),
			'before' => __( 'Before content', 'scriptdock' ),
			'after'  => __( 'After content', 'scriptdock' ),
			'css'    => __( 'CSS', 'scriptdock' ),
			'js'     => __( 'JavaScript', 'scriptdock' ),
		);

		$used  = array();
		$lines = 0;
		foreach ( Page_Scripts::FIELDS as $field ) {
			$code = trim( (string) $page[ $field ] );
			if ( '' === $code ) {
				continue;
			}
			$used[] = $labels[ $field ];
			$lines += substr_count( $code, "\n" ) + 1;
		}

		$count = sprintf(
			/* translators: %d: number of lines of code. */
			_n( '%d line', '%d lines', $lines, 'scriptdock' ),
			$lines
		);

		// One place is named; more than two are counted, so the row stays short.
		if ( count( $used ) > 2 ) {
			$places = sprintf(
				/* translators: %d: how many places on the page carry code. */
				_n( '%d place', '%d places', count( $used ), 'scriptdock' ),
				count( $used )
			);
		} else {
			$places = implode( ' · ', $used );
		}

		return $places . ' · ' . $count;
	}

	/**
	 * The red safe mode item, which replaces the list: nothing ran.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	private static function safe_mode_menu( $bar ) {
		$bar->add_node(
			array(
				'id'    => 'scriptdock',
				'title' => '<span class="sd-bar__warn">' . Admin\Icons::get( 'alert', 14, 2 ) . '</span><span class="ab-label">'
					. esc_html__( 'ScriptDock: safe mode', 'scriptdock' ) . '</span>',
				'href'  => Snippets::list_url(),
				'meta'  => array( 'class' => 'sd-bar sd-bar--safe' ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'scriptdock',
				'id'     => 'scriptdock-safe-note',
				'title'  => '<span class="sd-bar__note">' . esc_html__(
					'No snippets are running for you. Visitors still see the site as normal.',
					'scriptdock'
				) . '</span>',
				'meta'   => array( 'class' => 'sd-bar__row sd-bar__row--note' ),
			)
		);

		if ( Safe_Mode::is_forced() ) {
			$bar->add_node(
				array(
					'parent' => 'scriptdock',
					'id'     => 'scriptdock-safe-forced',
					'title'  => '<span class="sd-bar__note sd-bar__note--muted">' . esc_html__(
						'Safe mode is set in wp-config.php, so it cannot be turned off from here.',
						'scriptdock'
					) . '</span>',
					'meta'   => array( 'class' => 'sd-bar__row sd-bar__row--note' ),
				)
			);
			return;
		}

		$bar->add_node(
			array(
				'parent' => 'scriptdock',
				'id'     => 'scriptdock-exit-safe',
				'title'  => '<span class="sd-bar__name">' . esc_html__( 'Exit safe mode', 'scriptdock' ) . '</span>',
				'href'   => Safe_Mode::toggle_url( false ),
				'meta'   => array( 'class' => 'sd-bar__row sd-bar__row--do' ),
			)
		);
	}

	/**
	 * Snippets that run on or print to the current page.
	 *
	 * The admin bar is drawn before footer snippets run, so the list is worked
	 * out from each snippet's rules instead of from what has printed so far.
	 *
	 * @return array ID => array( title, type, where ).
	 */
	private static function snippets_for_page() {
		$data     = Runtime::data();
		$rendered = Runtime::rendered_ids();
		$items    = array();

		foreach ( $data['snippets'] as $id => $snippet ) {
			$location = $snippet['location'];
			$context  = Compiler::context_for( $location );
			$runs     = in_array( (int) $id, $rendered, true );

			if ( ! $runs ) {
				if ( 'frontend' !== $context || in_array( $location, array( 'shortcode', 'custom_hook', 'php_admin' ), true ) ) {
					continue;
				}
				if ( 0 === strpos( $location, 'wc_' ) || in_array( $location, array( 'between_posts', 'before_excerpt', 'after_excerpt' ), true ) ) {
					continue;
				}
				if ( in_array( $location, Registry::CONTENT_LOCATIONS, true ) && ! is_singular() ) {
					continue;
				}
				if ( ! Runtime::should_render( $snippet ) ) {
					continue;
				}
			}

			$items[ $id ] = array(
				'title' => $snippet['title'],
				'type'  => $snippet['type'],
				'where' => Registry::location_label( $location ),
			);
		}
		return $items;
	}
}
