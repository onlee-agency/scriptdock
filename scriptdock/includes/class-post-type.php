<?php
/**
 * Snippet post type and tag taxonomy.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Snippets are stored as a private post type. That gives us revisions, trash
 * and the familiar list screen for free. Code lives in post_content, the
 * description in post_excerpt, and "publish"/"draft" mean active/inactive.
 */
final class Post_Type {

	/**
	 * Post type name.
	 */
	const NAME = 'scriptdock_snippet';

	/**
	 * Tag taxonomy name.
	 */
	const TAXONOMY = 'scriptdock_tag';

	/**
	 * Registers the post type and taxonomy.
	 */
	public static function register() {
		if ( post_type_exists( self::NAME ) ) {
			return;
		}

		$cap = Capabilities::MANAGE;

		register_post_type(
			self::NAME,
			array(
				'labels'              => array(
					'name'               => __( 'Snippets', 'scriptdock' ),
					'singular_name'      => __( 'Snippet', 'scriptdock' ),
					'add_new'            => __( 'Add Snippet', 'scriptdock' ),
					'add_new_item'       => __( 'Add Snippet', 'scriptdock' ),
					'edit_item'          => __( 'Edit Snippet', 'scriptdock' ),
					'new_item'           => __( 'New Snippet', 'scriptdock' ),
					'search_items'       => __( 'Search Snippets', 'scriptdock' ),
					'not_found'          => __( 'No snippets found.', 'scriptdock' ),
					'not_found_in_trash' => __( 'No snippets found in Trash.', 'scriptdock' ),
					'all_items'          => __( 'All Snippets', 'scriptdock' ),
					'menu_name'          => __( 'Snippets', 'scriptdock' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'revisions' ),
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'              => $cap,
					'read_post'              => $cap,
					'delete_post'            => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'delete_posts'           => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'read'                   => $cap,
					'delete_private_posts'   => $cap,
					'delete_published_posts' => $cap,
					'delete_others_posts'    => $cap,
					'edit_private_posts'     => $cap,
					'edit_published_posts'   => $cap,
					'create_posts'           => $cap,
				),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::NAME,
			array(
				'labels'            => array(
					'name'          => __( 'Snippet Tags', 'scriptdock' ),
					'singular_name' => __( 'Snippet Tag', 'scriptdock' ),
					'search_items'  => __( 'Search Tags', 'scriptdock' ),
					'all_items'     => __( 'All Tags', 'scriptdock' ),
					'edit_item'     => __( 'Edit Tag', 'scriptdock' ),
					'update_item'   => __( 'Update Tag', 'scriptdock' ),
					'add_new_item'  => __( 'Add New Tag', 'scriptdock' ),
					'new_item_name' => __( 'New Tag Name', 'scriptdock' ),
					'menu_name'     => __( 'Tags', 'scriptdock' ),
					'not_found'     => __( 'No tags found.', 'scriptdock' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_tagcloud'     => false,
				'show_in_rest'      => false,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'rewrite'           => false,
				'query_var'         => false,
				'capabilities'      => array(
					'manage_terms' => $cap,
					'edit_terms'   => $cap,
					'delete_terms' => $cap,
					'assign_terms' => $cap,
				),
			)
		);

		add_filter( 'wp_' . self::NAME . '_revisions_to_keep', array( __CLASS__, 'revisions_to_keep' ) );
	}

	/**
	 * Number of revisions kept per snippet.
	 *
	 * @return int
	 */
	public static function revisions_to_keep() {
		return (int) Settings::get( 'revisions' );
	}
}
