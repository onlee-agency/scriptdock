<?php
/**
 * Conservative CSS minifier.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Removes comments and redundant whitespace from CSS without touching strings
 * or anything whose meaning depends on spacing (calc(), selectors with + or ~).
 */
final class Minifier {

	/**
	 * Minifies CSS.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function css( $css ) {
		$css    = (string) $css;
		$length = strlen( $css );
		$chunks = array();
		$buffer = '';
		$i      = 0;

		while ( $i < $length ) {
			$char = $css[ $i ];

			// Strings are copied verbatim.
			if ( '"' === $char || "'" === $char ) {
				$chunks[] = array( false, $buffer );
				$buffer   = '';
				$end      = $i + 1;
				while ( $end < $length && $css[ $end ] !== $char ) {
					$end += ( '\\' === $css[ $end ] ) ? 2 : 1;
				}
				$chunks[] = array( true, substr( $css, $i, $end - $i + 1 ) );
				$i        = $end + 1;
				continue;
			}

			// Comments are dropped, except /*! license */ comments.
			if ( '/' === $char && $i + 1 < $length && '*' === $css[ $i + 1 ] ) {
				$end = strpos( $css, '*/', $i + 2 );
				$end = false === $end ? $length : $end + 2;
				if ( $i + 2 < $length && '!' === $css[ $i + 2 ] ) {
					$chunks[] = array( false, $buffer );
					$buffer   = '';
					$chunks[] = array( true, substr( $css, $i, $end - $i ) );
				} else {
					$buffer .= ' ';
				}
				$i = $end;
				continue;
			}

			$buffer .= $char;
			++$i;
		}
		$chunks[] = array( false, $buffer );

		$out = '';
		foreach ( $chunks as $chunk ) {
			$out .= $chunk[0] ? $chunk[1] : self::squeeze( $chunk[1] );
		}
		return trim( $out );
	}

	/**
	 * Collapses whitespace in CSS that contains no strings or comments.
	 *
	 * @param string $css CSS fragment.
	 * @return string
	 */
	private static function squeeze( $css ) {
		$css = preg_replace( '/\s+/', ' ', $css );
		$css = preg_replace( '/\s*([{};,>])\s*/', '$1', $css );
		$css = str_replace( ';}', '}', $css );
		return $css;
	}
}
