<?php
/**
 * Inline SVG icons and the WordPress menu icon.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The icon set: thin-line icons on a 24px grid with round caps and joins,
 * drawn in currentColor so CSS sets their colour.
 *
 * The shapes live in assets/icons/icons.json, taken from the design files.
 * The React components import the same file, so both sides draw identical
 * icons.
 */
final class Icons {

	/**
	 * Icon bodies keyed by name, loaded once per request.
	 *
	 * @var array<string, string>|null
	 */
	private static $paths = null;

	/**
	 * Returns the icon bodies.
	 *
	 * @return array<string, string>
	 */
	private static function paths() {
		if ( null === self::$paths ) {
			$json        = file_get_contents( SCRIPTDOCK_DIR . 'assets/icons/icons.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a file bundled with the plugin.
			$paths       = is_string( $json ) ? json_decode( $json, true ) : null;
			self::$paths = is_array( $paths ) ? $paths : array();
		}
		return self::$paths;
	}

	/**
	 * WordPress sidebar icon, 20 x 20. WordPress recolours menu icons by
	 * rewriting fill attributes, so the design's 1.5px bracket strokes are
	 * drawn here as filled outlines, with the dot as a solid disc.
	 */
	const MENU_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M5.997 4.954L2.597 9.554A.75 .75 0 0 0 3.803 10.446L7.203 5.846A.75 .75 0 0 0 5.997 4.954ZM2.597 10.446L5.997 15.046A.75 .75 0 0 0 7.203 14.154L3.803 9.554A.75 .75 0 0 0 2.597 10.446ZM12.797 5.846L16.197 10.446A.75 .75 0 0 0 17.403 9.554L14.003 4.954A.75 .75 0 0 0 12.797 5.846ZM16.197 9.554L12.797 14.154A.75 .75 0 0 0 14.003 15.046L17.403 10.446A.75 .75 0 0 0 16.197 9.554ZM7.8 10a2.2 2.2 0 1 0 4.4 0a2.2 2.2 0 1 0-4.4 0Z"/></svg>';

	/**
	 * Returns an icon as inline SVG, hidden from assistive technology.
	 *
	 * @param string $name   Icon name.
	 * @param int    $size   Width and height in pixels.
	 * @param float  $stroke Stroke width on the 24px grid.
	 * @return string
	 */
	public static function get( $name, $size = 16, $stroke = 1.8 ) {
		$paths = self::paths();
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="sd-icon sd-icon--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%3$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%4$s</svg>',
			esc_attr( $name ),
			(int) $size,
			esc_attr( (string) (float) $stroke ),
			$paths[ $name ]
		);
	}

	/**
	 * Prints an icon.
	 *
	 * @param string $name   Icon name.
	 * @param int    $size   Width and height in pixels.
	 * @param float  $stroke Stroke width on the 24px grid.
	 */
	public static function render( $name, $size = 16, $stroke = 1.8 ) {
		echo self::get( $name, $size, $stroke ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup; attributes are escaped in get().
	}

	/**
	 * The sidebar icon as a data URI for add_menu_page().
	 *
	 * @return string
	 */
	public static function menu_icon() {
		return 'data:image/svg+xml;base64,' . base64_encode( self::MENU_ICON ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress expects menu icons as base64 data URIs.
	}
}
