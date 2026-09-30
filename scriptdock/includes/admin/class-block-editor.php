<?php
/**
 * ScriptDock inside the block editor.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Capabilities;
use ScriptDock\Compiler;
use ScriptDock\Page_Scripts;
use ScriptDock\Registry;
use ScriptDock\Snippet;
use ScriptDock\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Loads two things into the block editor:
 *
 * - S08a/S08b, the page scripts panel and its full "Page code" window. The
 *   panel edits the post's own meta, so page code saves when the post does.
 * - S16, the "ScriptDock Snippet" block, which places a snippet in the canvas.
 *
 * The classic editor keeps its meta box (S08c); Page_Meta_Box steps aside on
 * posts the panel handles.
 */
final class Block_Editor {

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'scriptdock-block-editor';

	/**
	 * Hooks the editor assets.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Whether the page scripts panel handles this post, rather than the
	 * classic meta box.
	 *
	 * @param \WP_Post|null $post Post being edited.
	 * @return bool
	 */
	public static function applies( $post ) {
		if ( ! $post instanceof \WP_Post || ! Capabilities::can_manage() ) {
			return false;
		}
		if ( ! in_array( $post->post_type, Page_Scripts::rest_post_types(), true ) ) {
			return false;
		}
		return function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post );
	}

	/**
	 * Enqueues the editor bundle and the data it opens with.
	 */
	public static function assets() {
		$asset_file = SCRIPTDOCK_DIR . 'build/block-editor.asset.php';
		if ( ! file_exists( $asset_file ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$asset = include $asset_file;
		$post  = get_post();
		$page  = self::applies( $post ) ? self::page_data( $post ) : null;

		Shell::register();
		wp_enqueue_style( self::HANDLE, SCRIPTDOCK_URL . 'assets/css/app/block-editor.css', array( 'scriptdock-ui' ), Admin::asset_version( 'css/app/block-editor.css' ) );
		wp_enqueue_script( self::HANDLE, SCRIPTDOCK_URL . 'build/block-editor.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'scriptdock' );
		wp_add_inline_script(
			self::HANDLE,
			'window.sdBlockEditor = ' . wp_json_encode(
				array(
					'page'  => $page,
					'block' => self::block_data(),
				),
				Admin::JSON_IN_SCRIPT
			) . ';',
			'before'
		);
	}

	/**
	 * What the page scripts panel opens with.
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return array
	 */
	private static function page_data( $post ) {
		// Also enqueues CodeMirror and wp.codeEditor. False when the viewer
		// turned syntax highlighting off in their profile.
		$editors = Admin::code_editor_settings( array( 'html', 'css', 'js' ) );

		return array(
			'metaKey'     => Page_Scripts::META,
			'slots'       => self::slots(),
			'snippets'    => self::page_snippets(),
			'trusted'     => Page_Scripts::is_trusted( $post->ID, Page_Scripts::get( $post->ID ) ),
			'codeEditors' => $editors ? $editors : null,
			'smartTags'   => Editor_App::smart_tags(),
			'theme'       => Editor_App::theme(),
			'phpNote'     => __( 'PHP snippets that run everywhere start before WordPress knows which page is loading, so they cannot be switched off per page. Use conditional logic for those.', 'scriptdock' ),
			'manageUrl'   => Snippets::list_url(),
		);
	}

	/**
	 * The seven places page code can go, in the order the panel lists them.
	 *
	 * @return array key, label, language, title, description.
	 */
	private static function slots() {
		return array(
			array(
				'key'         => 'head',
				'label'       => __( 'Header', 'scriptdock' ),
				'language'    => 'html',
				'title'       => __( 'Header code for this page only', 'scriptdock' ),
				'description' => __( 'Printed in the <head> of this page. It runs nowhere else.', 'scriptdock' ),
			),
			array(
				'key'         => 'body',
				'label'       => __( 'Body', 'scriptdock' ),
				'language'    => 'html',
				'title'       => __( 'Body code for this page only', 'scriptdock' ),
				'description' => __( 'Printed right after the opening <body> tag, before anything your theme draws.', 'scriptdock' ),
			),
			array(
				'key'         => 'footer',
				'label'       => __( 'Footer', 'scriptdock' ),
				'language'    => 'html',
				'title'       => __( 'Footer code for this page only', 'scriptdock' ),
				'description' => __( 'Printed before the closing </body> tag, after the rest of the page.', 'scriptdock' ),
			),
			array(
				'key'         => 'before',
				'label'       => __( 'Before content', 'scriptdock' ),
				'language'    => 'html',
				'title'       => __( 'Before this page’s content', 'scriptdock' ),
				'description' => __( 'HTML added just above the content, inside your theme’s layout.', 'scriptdock' ),
			),
			array(
				'key'         => 'after',
				'label'       => __( 'After content', 'scriptdock' ),
				'language'    => 'html',
				'title'       => __( 'After this page’s content', 'scriptdock' ),
				'description' => __( 'HTML added just below the content, inside your theme’s layout.', 'scriptdock' ),
			),
			array(
				'key'         => 'css',
				'label'       => __( 'CSS', 'scriptdock' ),
				'language'    => 'css',
				'title'       => __( 'CSS for this page only', 'scriptdock' ),
				'description' => __( 'Loaded in the head, after your theme’s styles. No <style> tag needed.', 'scriptdock' ),
			),
			array(
				'key'         => 'js',
				'label'       => __( 'JavaScript', 'scriptdock' ),
				'language'    => 'js',
				'title'       => __( 'JavaScript for this page only', 'scriptdock' ),
				'description' => __( 'Runs in the footer, after other scripts. No <script> tag needed.', 'scriptdock' ),
			),
		);
	}

	/**
	 * Site-wide snippets that can be switched off on a single page: the ones
	 * that run on the front end and are placed by ScriptDock rather than by
	 * the writer.
	 *
	 * @return array id, title, type, typeLabel, location.
	 */
	private static function page_snippets() {
		$labels = Registry::type_labels();
		$list   = array();

		foreach ( Compiler::get()['snippets'] as $id => $snippet ) {
			if ( 'frontend' !== Compiler::context_for( $snippet['location'] ) || 'shortcode' === $snippet['location'] ) {
				continue;
			}
			$list[] = array(
				'id'        => (int) $id,
				'title'     => $snippet['title'],
				'type'      => $snippet['type'],
				'typeLabel' => $labels[ $snippet['type'] ],
				'location'  => Registry::location_label( $snippet['location'] ),
				'editUrl'   => Snippets::edit_url( (int) $id ),
			);
		}
		return $list;
	}

	/**
	 * What the Snippet block can place: snippets set to "Shortcode or block
	 * only", which are the only ones a writer positions by hand.
	 *
	 * @return array
	 */
	private static function block_data() {
		$labels = Registry::type_labels();
		$list   = array();

		foreach ( Snippets::query(
			array(
				'post_status' => 'publish',
				'meta_key'    => Snippet::META_LOCATION, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'shortcode', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'     => 'title',
			)
		) as $snippet ) {
			$list[] = array(
				'id'        => $snippet->id,
				'title'     => $snippet->title,
				'type'      => $snippet->type,
				'typeLabel' => isset( $labels[ $snippet->type ] ) ? $labels[ $snippet->type ] : $snippet->type,
				'editUrl'   => Capabilities::can_manage() ? Snippets::edit_url( $snippet->id ) : '',
			);
		}

		return array(
			'snippets' => $list,
			'canEdit'  => Capabilities::can_manage(),
			'newUrl'   => Capabilities::can_manage() ? Snippets::edit_url() : '',
		);
	}
}
