<?php
/**
 * In-memory stream wrapper used to run PHP snippets.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Serves snippet code from memory so it can be include()d.
 *
 * This keeps PHP snippets out of the file system (nothing is written to disk),
 * while giving every snippet its own "file" name. Error messages and stack
 * traces therefore point at scriptdock://snippet/{id}, which lets the error
 * handler tell exactly which snippet failed, even inside callbacks the
 * snippet registered for later hooks.
 */
final class Stream {

	/**
	 * Wrapper protocol.
	 */
	const PROTOCOL = 'scriptdock';

	/**
	 * Registered code, keyed by snippet ID.
	 *
	 * @var string[]
	 */
	private static $sources = array();

	/**
	 * Stream context, set by PHP.
	 *
	 * @var resource|null
	 */
	public $context;

	/**
	 * Code being read.
	 *
	 * @var string
	 */
	private $data = '';

	/**
	 * Read position.
	 *
	 * @var int
	 */
	private $position = 0;

	/**
	 * Registers the wrapper once.
	 */
	public static function register() {
		if ( ! in_array( self::PROTOCOL, stream_get_wrappers(), true ) ) {
			stream_wrapper_register( self::PROTOCOL, __CLASS__ );
		}
	}

	/**
	 * Stores code for a snippet and returns the path to include.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $code       Full PHP source, including the opening tag.
	 * @return string
	 */
	public static function path_for( $snippet_id, $code ) {
		$snippet_id                   = (int) $snippet_id;
		self::$sources[ $snippet_id ] = (string) $code;
		return self::PROTOCOL . '://snippet/' . $snippet_id;
	}

	/**
	 * Extracts a snippet ID from a file path or error message.
	 *
	 * @param string $text Path or message.
	 * @return int Snippet ID, or 0.
	 */
	public static function snippet_id_from( $text ) {
		if ( preg_match( '#' . self::PROTOCOL . '://snippet/(\d+)#', (string) $text, $matches ) ) {
			return (int) $matches[1];
		}
		return 0;
	}

	/**
	 * The innermost snippet in the stack trace PHP appends to a fatal error.
	 *
	 * Only the trace itself is read, from its last "Stack trace:" heading on.
	 * The error text before it can repeat what a visitor sent, so a snippet
	 * named there proves nothing about which one failed.
	 *
	 * @param string $message Fatal error message.
	 * @return int Snippet ID, or 0.
	 */
	public static function snippet_id_from_trace( $message ) {
		$message = (string) $message;
		$at      = strrpos( $message, "\nStack trace:\n" );
		if ( false === $at ) {
			return 0;
		}
		if ( preg_match( '#^\#\d+ ' . self::PROTOCOL . '://snippet/(\d+)\(#m', substr( $message, $at ), $matches ) ) {
			return (int) $matches[1];
		}
		return 0;
	}

	/**
	 * Opens a stream.
	 *
	 * @param string      $path        Path.
	 * @param string      $mode        Mode.
	 * @param int         $options     Options.
	 * @param string|null $opened_path Opened path.
	 * @return bool
	 */
	public function stream_open( $path, $mode, $options, &$opened_path ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$snippet_id = self::snippet_id_from( $path );
		if ( ! $snippet_id || ! isset( self::$sources[ $snippet_id ] ) ) {
			return false;
		}
		$this->data     = self::$sources[ $snippet_id ];
		$this->position = 0;
		return true;
	}

	/**
	 * Reads from the stream.
	 *
	 * @param int $count Bytes to read.
	 * @return string
	 */
	public function stream_read( $count ) {
		$chunk           = (string) substr( $this->data, $this->position, $count );
		$this->position += strlen( $chunk );
		return $chunk;
	}

	/**
	 * Whether the end has been reached.
	 *
	 * @return bool
	 */
	public function stream_eof() {
		return $this->position >= strlen( $this->data );
	}

	/**
	 * Current position.
	 *
	 * @return int
	 */
	public function stream_tell() {
		return $this->position;
	}

	/**
	 * Stat for an open stream.
	 *
	 * @return array
	 */
	public function stream_stat() {
		return self::stat( strlen( $this->data ) );
	}

	/**
	 * Stat for a path.
	 *
	 * @param string $path  Path.
	 * @param int    $flags Flags.
	 * @return array|false
	 */
	public function url_stat( $path, $flags ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$snippet_id = self::snippet_id_from( $path );
		if ( ! $snippet_id || ! isset( self::$sources[ $snippet_id ] ) ) {
			return false;
		}
		return self::stat( strlen( self::$sources[ $snippet_id ] ) );
	}

	/**
	 * Stream options are not supported.
	 *
	 * @param int $option Option.
	 * @param int $arg1   Argument 1.
	 * @param int $arg2   Argument 2.
	 * @return bool
	 */
	public function stream_set_option( $option, $arg1, $arg2 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return false;
	}

	/**
	 * Closes the stream.
	 */
	public function stream_close() {
		$this->data = '';
	}

	/**
	 * Builds a stat array for a read-only regular file.
	 *
	 * @param int $size Size in bytes.
	 * @return array
	 */
	private static function stat( $size ) {
		return array(
			'dev'     => 0,
			'ino'     => 0,
			'mode'    => 0100444,
			'nlink'   => 0,
			'uid'     => 0,
			'gid'     => 0,
			'rdev'    => 0,
			'size'    => $size,
			'atime'   => 0,
			'mtime'   => 0,
			'ctime'   => 0,
			'blksize' => -1,
			'blocks'  => -1,
		);
	}
}
