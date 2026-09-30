<?php
/**
 * Snippet error handling and automatic deactivation.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Catches errors thrown by snippets, records them and switches off the
 * snippet responsible so a single bad snippet cannot keep a site down.
 */
final class Error_Handler {

	/**
	 * ID of the snippet currently executing.
	 *
	 * @var int
	 */
	private static $current = 0;

	/**
	 * Test run context: snippet ID, and either the redirect URL or the extra
	 * data for a JSON answer.
	 *
	 * @var array|null
	 */
	private static $test = null;

	/**
	 * Fatal error types.
	 *
	 * @var int[]
	 */
	private static $fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

	/**
	 * Registers the shutdown handler.
	 */
	public static function init() {
		register_shutdown_function( array( __CLASS__, 'shutdown' ) );
	}

	/**
	 * Marks a snippet as executing.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return int The previously executing snippet ID.
	 */
	public static function begin( $snippet_id ) {
		$previous      = self::$current;
		self::$current = (int) $snippet_id;
		return $previous;
	}

	/**
	 * Restores the previously executing snippet.
	 *
	 * @param int $previous Previous snippet ID.
	 */
	public static function end( $previous ) {
		self::$current = (int) $previous;
	}

	/**
	 * Starts a test run of a snippet inside the save request.
	 *
	 * Defining WP_SANDBOX_SCRAPING stops core's fatal error handler from
	 * printing its error page, so the shutdown handler below can redirect back
	 * to the editor with a readable message instead.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $redirect   URL to send the user to if the test run dies.
	 */
	public static function start_test( $snippet_id, $redirect ) {
		self::sandbox();
		self::$test = array(
			'id'       => (int) $snippet_id,
			'redirect' => $redirect,
		);
	}

	/**
	 * Starts a test run inside a REST request. If the snippet dies with a
	 * fatal error, the request answers with a JSON error (HTTP 422) instead of
	 * a redirect, so the screen that asked can show what went wrong.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $data       Extra data for the error, such as the snippets
	 *                          a bulk action had already switched on. A
	 *                          'message' entry replaces the default message.
	 */
	public static function start_json_test( $snippet_id, array $data = array() ) {
		self::sandbox();
		self::$test = array(
			'id'   => (int) $snippet_id,
			'json' => $data,
		);
	}

	/**
	 * Keeps core's fatal error handler out of the way during a test run.
	 */
	private static function sandbox() {
		if ( ! defined( 'WP_SANDBOX_SCRAPING' ) ) {
			define( 'WP_SANDBOX_SCRAPING', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core constant, set the same way core does when it sandboxes plugin activation.
		}
	}

	/**
	 * Ends a test run.
	 */
	public static function end_test() {
		self::$test = null;
	}

	/**
	 * Records a caught Throwable.
	 *
	 * Caught errors are logged but do not switch the snippet off: the page
	 * still loads, and the error may only happen in a rare context. Fatal
	 * errors, which take the page down, do switch it off (see shutdown()).
	 *
	 * @param int        $snippet_id Snippet ID.
	 * @param \Throwable $error      Error.
	 * @return array The recorded error.
	 */
	public static function handle_throwable( $snippet_id, $error ) {
		$line = Stream::snippet_id_from( $error->getFile() ) === (int) $snippet_id ? $error->getLine() : 0;
		return self::record( $snippet_id, get_class( $error ) . ': ' . $error->getMessage(), $line );
	}

	/**
	 * Shutdown handler: detects fatal errors caused by snippets.
	 */
	public static function shutdown() {
		$error = error_get_last();
		if ( ! $error || ! in_array( $error['type'], self::$fatal_types, true ) ) {
			return;
		}

		$snippet_id = Stream::snippet_id_from( $error['file'] );
		$line       = $snippet_id ? (int) $error['line'] : 0;

		if ( ! $snippet_id ) {
			// Errors raised in other files but reached through a snippet mention it in the stack trace.
			$snippet_id = Stream::snippet_id_from_trace( $error['message'] );
		}
		if ( ! $snippet_id ) {
			$snippet_id = self::$current;
		}
		if ( ! $snippet_id ) {
			return;
		}

		$recorded = self::record( $snippet_id, $error['message'], $line, true );

		if ( self::$test && self::$test['id'] === $snippet_id && ! headers_sent() ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			if ( isset( self::$test['json'] ) ) {
				self::send_json_fatal( $snippet_id, $recorded, self::$test['json'] );
			}
			wp_safe_redirect( self::$test['redirect'] );
			exit;
		}
	}

	/**
	 * Answers a REST request whose test run died, in the shape of a REST
	 * error: code, message and data.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $error      The recorded error.
	 * @param array $data       Extra data from start_json_test().
	 */
	private static function send_json_fatal( $snippet_id, array $error, array $data ) {
		$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'The snippet caused a fatal error when ScriptDock tested it, so it was left switched off.', 'scriptdock' );
		unset( $data['message'] );

		status_header( 422 );
		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode(
			array(
				'code'    => 'scriptdock_test_fatal',
				'message' => $message,
				'data'    => array_merge(
					$data,
					array(
						'status'     => 422,
						'snippet_id' => (int) $snippet_id,
						'error'      => array(
							'message' => $error['message'],
							'line'    => $error['line'],
						),
					)
				),
			)
		);
		exit;
	}

	/**
	 * Stores an error on the snippet and deactivates it when configured to.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $message    Error message.
	 * @param int    $line       Line in the snippet, 0 if unknown.
	 * @param bool   $fatal      Whether the error was fatal.
	 * @return array The recorded error.
	 */
	public static function record( $snippet_id, $message, $line = 0, $fatal = false ) {
		$snippet_id = (int) $snippet_id;
		// A trial run is not a page visit, so it records no address.
		$in_test    = self::$test && self::$test['id'] === $snippet_id;
		$error      = array(
			'message' => self::clean_message( $message ),
			'line'    => (int) $line,
			'time'    => time(),
			'fatal'   => (bool) $fatal,
			'url'     => $in_test ? '' : self::request_path(),
		);

		$post = get_post( $snippet_id );
		if ( ! $post || Post_Type::NAME !== $post->post_type ) {
			return $error;
		}

		update_post_meta( $snippet_id, Snippet::META_ERROR, wp_slash( $error ) );

		// Test runs happen on inactive snippets; nothing to switch off.
		if ( $in_test || ! $fatal || 'publish' !== $post->post_status || ! Settings::get( 'auto_disable' ) ) {
			return $error;
		}

		Snippets::deactivate_after_error( $snippet_id );
		Notices::add_error( $snippet_id, $post->post_title, $error );
		// The editor's error banner says who was told.
		$emailed = self::maybe_email( $post, $error );
		if ( $emailed ) {
			$error['emailed'] = $emailed;
			update_post_meta( $snippet_id, Snippet::META_ERROR, wp_slash( $error ) );
		}
		return $error;
	}

	/**
	 * Emails the site admin that a snippet was switched off.
	 *
	 * @param \WP_Post $post  Snippet post.
	 * @param array    $error Error data.
	 * @return string The address it went to, or '' when none was sent.
	 */
	private static function maybe_email( $post, $error ) {
		if ( ! Settings::get( 'error_email' ) ) {
			return '';
		}
		return Crash_Email::send( $post, $error );
	}

	/**
	 * Shortens an error message for display.
	 *
	 * @param string $message Raw message.
	 * @return string
	 */
	private static function clean_message( $message ) {
		$message = (string) $message;
		$cut     = strpos( $message, 'Stack trace:' );
		if ( false !== $cut ) {
			$message = substr( $message, 0, $cut );
		}
		$message = preg_replace( '#\s+in\s+' . Stream::PROTOCOL . '://snippet/\d+(?::\d+| on line \d+)?#', '', $message );
		$message = str_replace( array( wp_normalize_path( ABSPATH ), ABSPATH ), '', $message );
		return trim( wp_strip_all_tags( $message ) );
	}

	/**
	 * Path of the current request, for error context.
	 *
	 * @return string
	 */
	private static function request_path() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp-cli';
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return substr( (string) strtok( $uri, '?' ), 0, 200 );
	}
}
