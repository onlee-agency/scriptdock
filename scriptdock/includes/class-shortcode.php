<?php
/**
 * Shortcode and block.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * [scriptdock id="123"] and the "ScriptDock Snippet" block output snippets
 * whose location is "Shortcode or block only". Extra shortcode attributes are
 * passed to PHP snippets as $atts and to HTML/JS snippets as {{attr:name}}.
 */
final class Shortcode {

	/**
	 * Shortcode tag.
	 */
	const TAG = 'scriptdock';

	/**
	 * Style handle for the block in the editor canvas.
	 */
	const BLOCK_STYLE = 'scriptdock-snippet-block';

	/**
	 * Nesting depth, to stop snippets that include themselves.
	 *
	 * @var int
	 */
	private static $depth = 0;

	/**
	 * Hooks registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers the shortcode and the block.
	 */
	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render_shortcode' ) );

		// The block's script lives in Admin\Block_Editor, with the rest of
		// what ScriptDock adds to the block editor. Its stylesheet is named
		// in block.json, so it has to be registered before the block is, and
		// WordPress then loads it into the editor canvas as well.
		wp_register_style(
			self::BLOCK_STYLE,
			SCRIPTDOCK_URL . 'assets/css/block/snippet.css',
			array(),
			Plugin::asset_version( 'css/block/snippet.css' )
		);

		register_block_type(
			SCRIPTDOCK_DIR . 'blocks/snippet',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts    Attributes.
	 * @param string|null  $content Enclosed content.
	 * @return string
	 */
	public static function render_shortcode( $atts, $content = null ) {
		$atts = is_array( $atts ) ? $atts : array();
		$id   = isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
		return self::output( $id, $atts, (string) $content );
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$id = isset( $attributes['snippetId'] ) ? absint( $attributes['snippetId'] ) : 0;
		return self::output( $id, array( 'id' => $id ), '' );
	}

	/**
	 * Output for a snippet placed by shortcode or block.
	 *
	 * @param int    $id      Snippet ID.
	 * @param array  $atts    Attributes.
	 * @param string $content Enclosed content.
	 * @return string
	 */
	private static function output( $id, array $atts, $content ) {
		if ( ! $id || self::$depth > 3 || Safe_Mode::is_active() ) {
			return '';
		}
		$data = Runtime::data();
		if ( ! isset( $data['snippets'][ $id ] ) ) {
			return '';
		}
		$snippet = $data['snippets'][ $id ];
		if ( 'shortcode' !== $snippet['location'] || ! Runtime::should_render( $snippet ) ) {
			return '';
		}

		++self::$depth;
		$output = Runtime::render(
			$snippet,
			array(
				'atts'    => $atts,
				'content' => $content,
			)
		);
		--self::$depth;

		return $output;
	}
}
