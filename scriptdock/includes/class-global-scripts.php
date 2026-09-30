<?php
/**
 * Site-wide header, body and footer code.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * The quick "Header & Footer" screen: three boxes of code printed on every
 * front-end page. Signed like snippets.
 */
final class Global_Scripts {

	/**
	 * Option name.
	 */
	const OPTION = 'scriptdock_global';

	/**
	 * Code areas.
	 */
	const AREAS = array( 'head', 'body', 'footer' );

	/**
	 * Hooks output.
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'register_output' ) );
	}

	/**
	 * Stored data merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'head'            => '',
				'body'            => '',
				'footer'          => '',
				'head_priority'   => 10,
				'body_priority'   => 10,
				'footer_priority' => 10,
				'sig'             => '',
			)
		);
	}

	/**
	 * Saves and signs the code.
	 *
	 * @param array $input Unslashed input.
	 */
	public static function save( array $input ) {
		$data = self::get();
		foreach ( self::AREAS as $area ) {
			$data[ $area ]               = isset( $input[ $area ] ) ? str_replace( "\r\n", "\n", (string) $input[ $area ] ) : '';
			$data[ $area . '_priority' ] = isset( $input[ $area . '_priority' ] ) ? max( -9999, min( 9999, (int) $input[ $area . '_priority' ] ) ) : 10;
		}
		$data['sig'] = Signer::sign( self::payload( $data ) );
		update_option( self::OPTION, $data, true );
	}

	/**
	 * Data covered by the signature.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	private static function payload( array $data ) {
		return wp_json_encode( array( 'global-v1', (string) $data['head'], (string) $data['body'], (string) $data['footer'] ) );
	}

	/**
	 * Whether the stored code matches its signature.
	 *
	 * @param array|null $data Data, or null to load it.
	 * @return bool
	 */
	public static function is_trusted( $data = null ) {
		$data = null === $data ? self::get() : $data;
		if ( '' === trim( $data['head'] . $data['body'] . $data['footer'] ) ) {
			return true;
		}
		return Signer::verify( self::payload( $data ), $data['sig'] );
	}

	/**
	 * Attaches output once the page is known.
	 */
	public static function register_output() {
		if ( is_admin() || Safe_Mode::is_active() || Page_Scripts::globals_disabled_here() ) {
			return;
		}
		$data = self::get();
		if ( ! self::is_trusted( $data ) ) {
			return;
		}
		$hooks = array(
			'head'   => 'wp_head',
			'body'   => 'wp_body_open',
			'footer' => 'wp_footer',
		);
		foreach ( $hooks as $area => $hook ) {
			$code = $data[ $area ];
			if ( '' === trim( $code ) ) {
				continue;
			}
			add_action(
				$hook,
				static function () use ( $code ) {
					echo Smart_Tags::replace( $code, 'html' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unfiltered code written by an administrator.
				},
				(int) $data[ $area . '_priority' ]
			);
		}
	}
}
