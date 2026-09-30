<?php
/**
 * Stored admin notices about snippet errors.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a short list of "snippet was deactivated" notices until an admin
 * dismisses them or fixes the snippet.
 */
final class Notices {

	/**
	 * Option name.
	 */
	const OPTION = 'scriptdock_error_notices';

	/**
	 * Adds a notice for a deactivated snippet.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $title      Snippet title.
	 * @param array  $error      Error data.
	 */
	public static function add_error( $snippet_id, $title, array $error ) {
		$notices                        = self::all();
		$notices[ (int) $snippet_id ] = array(
			'title'   => (string) $title,
			'message' => (string) $error['message'],
			'line'    => (int) $error['line'],
			'time'    => (int) $error['time'],
		);
		// Keep the list short.
		$notices = array_slice( $notices, -10, null, true );
		update_option( self::OPTION, $notices, false );
	}

	/**
	 * All stored notices.
	 *
	 * @return array
	 */
	public static function all() {
		$notices = get_option( self::OPTION, array() );
		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Removes the notice for a snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 */
	public static function remove( $snippet_id ) {
		$notices = self::all();
		if ( isset( $notices[ (int) $snippet_id ] ) ) {
			unset( $notices[ (int) $snippet_id ] );
			update_option( self::OPTION, $notices, false );
		}
	}

	/**
	 * Removes all notices.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}
