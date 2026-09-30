<?php
/**
 * Virtual text files: ads.txt, app-ads.txt, llms.txt, security.txt, robots.txt.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Serves small text files from the database so they can be edited without
 * FTP. A real file with the same name on the server always wins, because the
 * web server answers before WordPress runs.
 */
final class Virtual_Files {

	/**
	 * Option name.
	 */
	const OPTION = 'scriptdock_files';

	/**
	 * Hooks serving.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 0 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
	}

	/**
	 * File definitions: key => array( path, label ).
	 *
	 * @return array
	 */
	public static function files() {
		return array(
			'ads_txt'      => '/ads.txt',
			'app_ads_txt'  => '/app-ads.txt',
			'llms_txt'     => '/llms.txt',
			'security_txt' => '/.well-known/security.txt',
		);
	}

	/**
	 * Stored contents.
	 *
	 * @return array
	 */
	public static function get() {
		$stored   = get_option( self::OPTION, array() );
		$defaults = array_fill_keys( array_keys( self::files() ), '' );

		$defaults['robots_txt']  = '';
		$defaults['robots_mode'] = 'append';
		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * Saves contents.
	 *
	 * @param array $input Unslashed input.
	 */
	public static function save( array $input ) {
		$data = array();
		foreach ( array_merge( array_keys( self::files() ), array( 'robots_txt' ) ) as $key ) {
			$data[ $key ] = isset( $input[ $key ] ) ? self::clean( $input[ $key ] ) : '';
		}
		$data['robots_mode'] = isset( $input['robots_mode'] ) && 'replace' === $input['robots_mode'] ? 'replace' : 'append';
		update_option( self::OPTION, $data, true );
	}

	/**
	 * Cleans file contents: plain text with normalized line endings.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	private static function clean( $text ) {
		$text = wp_check_invalid_utf8( str_replace( "\r\n", "\n", (string) $text ) );
		return trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text ) );
	}

	/**
	 * Serves a virtual file if the request asks for one.
	 */
	public static function maybe_serve() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		$path = strtok( Conditions::request_path(), '?' );

		// Core only serves its virtual robots.txt with pretty permalinks.
		if ( '/robots.txt' === $path && ! get_option( 'permalink_structure' ) && '' !== self::get()['robots_txt'] ) {
			do_robots();
			exit;
		}

		$key = array_search( $path, self::files(), true );
		if ( false === $key ) {
			return;
		}
		$data = self::get();
		if ( '' === $data[ $key ] ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $data[ $key ] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain response, not HTML.
		exit;
	}

	/**
	 * Adds to or replaces WordPress's virtual robots.txt.
	 *
	 * @param string $output Robots.txt output.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public static function robots_txt( $output, $public ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$data = self::get();
		if ( '' === $data['robots_txt'] ) {
			return $output;
		}
		return 'replace' === $data['robots_mode'] ? $data['robots_txt'] . "\n" : rtrim( $output ) . "\n\n" . $data['robots_txt'] . "\n";
	}

	/**
	 * Whether a physical file would shadow the virtual one.
	 *
	 * @param string $path Public path.
	 * @return bool
	 */
	public static function physical_exists( $path ) {
		return file_exists( untrailingslashit( ABSPATH ) . $path );
	}

	/**
	 * Public URL of a file.
	 *
	 * @param string $path Public path.
	 * @return string
	 */
	public static function url( $path ) {
		return home_url( $path );
	}
}
