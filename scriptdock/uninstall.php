<?php
/**
 * Uninstall routine.
 *
 * Data is only removed when "Delete all snippets ... when the plugin is
 * deleted" is ticked in ScriptDock > Settings.
 *
 * @package ScriptDock
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes ScriptDock data from the current site.
 */
function scriptdock_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'scriptdock_settings' );
	if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	// Snippets, their revisions and meta.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup; the post type is not registered during uninstall.
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'scriptdock_snippet' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}

	// Tags, and the links between them and the snippets. wp_delete_post()
	// above cannot remove those links: it only knows registered taxonomies.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup; the taxonomy is not registered during uninstall.
	$tags = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'scriptdock_tag' ) );
	foreach ( $tags as $tag ) {
		$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => (int) $tag->term_taxonomy_id ) );
		$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => (int) $tag->term_taxonomy_id ) );
		// A term another taxonomy still uses stays.
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $tag->term_id ) ) ) {
			$wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $tag->term_id ) );
			$wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $tag->term_id ) );
		}
	}
	// phpcs:enable

	// Page scripts on posts.
	delete_post_meta_by_key( '_scriptdock_page' );

	foreach ( array( 'scriptdock_settings', 'scriptdock_runtime', 'scriptdock_global', 'scriptdock_files', 'scriptdock_error_notices', 'scriptdock_version', 'scriptdock_onboarded', 'scriptdock_imported', 'scriptdock_page_types_chosen' ) as $option ) {
		delete_option( $option );
	}
	delete_site_transient( 'scriptdock_update_info' );

	// Everyone's preferences for ScriptDock's screens. Most are stored per
	// site; rows per page is stored without the site prefix, the way the
	// Screen Options form keeps it.
	foreach ( array( 'scriptdock_list_density', 'scriptdock_editor_theme', 'scriptdock_safe_link_saved' ) as $key ) {
		delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . $key, '', true );
	}
	delete_metadata( 'user', 0, 'scriptdock_snippets_per_page', '', true );

	// Cached CSS and JS files.
	$uploads = wp_get_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'scriptdock';
	if ( is_dir( $dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			$wp_filesystem->delete( $dir, true );
		}
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $scriptdock_site_id ) {
		switch_to_blog( $scriptdock_site_id );
		scriptdock_uninstall_site();
		restore_current_blog();
	}
} else {
	scriptdock_uninstall_site();
}
