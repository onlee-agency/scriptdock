<?php
/**
 * Static CSS and JavaScript files.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Writes CSS and JavaScript snippets to uploads/scriptdock/ so browsers and
 * CDNs can cache them. File names contain a content hash, so a changed
 * snippet always gets a fresh URL. Only CSS and JS are written; PHP never
 * touches the file system.
 */
final class Assets {

	/**
	 * Folder name inside uploads.
	 */
	const FOLDER = 'scriptdock';

	/**
	 * Absolute path of the assets folder.
	 *
	 * @return string
	 */
	public static function dir() {
		$uploads = wp_get_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . self::FOLDER;
	}

	/**
	 * Public URL of an asset file.
	 *
	 * @param string $file File name.
	 * @return string
	 */
	public static function url( $file ) {
		$uploads = wp_get_upload_dir();
		return set_url_scheme( trailingslashit( $uploads['baseurl'] ) . self::FOLDER . '/' . rawurlencode( $file ) );
	}

	/**
	 * Whether an asset file exists.
	 *
	 * @param string $file File name.
	 * @return bool
	 */
	public static function exists( $file ) {
		return '' !== $file && file_exists( self::dir() . '/' . $file );
	}

	/**
	 * Writes a snippet file.
	 *
	 * @param int    $id   Snippet ID.
	 * @param string $type css or js.
	 * @param string $code Contents.
	 * @return string|false File name, or false when the file could not be written.
	 */
	public static function write( $id, $type, $code ) {
		$extension = 'css' === $type ? 'css' : 'js';
		$file      = sprintf( 'snippet-%d-%s.%s', (int) $id, substr( md5( $code ), 0, 12 ), $extension );
		$dir       = self::dir();

		if ( file_exists( $dir . '/' . $file ) ) {
			return $file;
		}

		$filesystem = self::filesystem();
		if ( ! $filesystem ) {
			return false;
		}
		if ( ! $filesystem->is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( ! $filesystem->exists( $dir . '/index.html' ) ) {
			$filesystem->put_contents( $dir . '/index.html', '', FS_CHMOD_FILE );
		}

		$header = '/* ScriptDock snippet ' . (int) $id . " */\n";
		if ( ! $filesystem->put_contents( $dir . '/' . $file, $header . $code, FS_CHMOD_FILE ) ) {
			return false;
		}
		return $file;
	}

	/**
	 * Deletes snippet files that are no longer used.
	 *
	 * @param string[] $keep File names to keep.
	 */
	public static function cleanup( array $keep ) {
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$filesystem = self::filesystem();
		if ( ! $filesystem ) {
			return;
		}
		$list = $filesystem->dirlist( $dir );
		if ( ! is_array( $list ) ) {
			return;
		}
		foreach ( array_keys( $list ) as $name ) {
			if ( preg_match( '/^snippet-\d+-[a-f0-9]{12}\.(css|js)$/', $name ) && ! in_array( $name, $keep, true ) ) {
				$filesystem->delete( $dir . '/' . $name );
			}
		}
	}

	/**
	 * Removes the whole assets folder (used on uninstall).
	 */
	public static function remove_all() {
		$filesystem = self::filesystem();
		if ( $filesystem && $filesystem->is_dir( self::dir() ) ) {
			$filesystem->delete( self::dir(), true );
		}
	}

	/**
	 * Direct file system access, when available without credentials.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem() {
		global $wp_filesystem;
		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$uploads = wp_upload_dir( null, false );
		if ( 'direct' !== get_filesystem_method( array(), $uploads['basedir'] ) ) {
			return null;
		}
		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base && ! WP_Filesystem() ) {
			return null;
		}
		return $wp_filesystem;
	}
}
