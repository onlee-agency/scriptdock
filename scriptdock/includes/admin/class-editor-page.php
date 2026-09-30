<?php
/**
 * Snippet editor screen.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The add/edit screen. Everything on it is drawn by Editor_App, the React
 * editor built from S04 — this class only owns the menu slug and the
 * browser title.
 *
 * There was a second, form-based editor here while the targeting wizard
 * was still being built, and it was the default. The wizard landed, so the
 * form went: it could not do targeting, history or smart tags, and two
 * editors saving the same snippet two different ways was a standing risk.
 */
final class Editor_Page {

	/**
	 * Page slug.
	 */
	const SLUG = 'scriptdock-edit';

	/**
	 * Hooks handlers.
	 */
	public static function init() {
		add_action( 'scriptdock_admin_assets', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_title', array( __CLASS__, 'admin_title' ) );
	}

	/**
	 * Uses "Edit Snippet" in the browser title when editing.
	 *
	 * @param string $title Admin title.
	 * @return string
	 */
	public static function admin_title( $title ) {
		if ( Admin::is_screen( self::SLUG ) && ! empty( $_GET['snippet'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return str_replace( __( 'Add Snippet', 'scriptdock' ), __( 'Edit Snippet', 'scriptdock' ), $title );
		}
		return $title;
	}

	/**
	 * Enqueues the editor.
	 */
	public static function assets() {
		if ( Admin::is_screen( self::SLUG ) ) {
			Editor_App::assets();
		}
	}

	/**
	 * Prints the screen.
	 */
	public static function render() {
		Editor_App::render();
	}
}
