<?php
/**
 * Plugin settings.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the settings option.
 */
final class Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'scriptdock_settings';

	/**
	 * Request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * The last safe mode key generate_key() made in this request.
	 *
	 * @var string|null
	 */
	private static $minted_key = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'auto_disable'        => true,
			'error_email'         => true,
			'page_post_types'     => array( 'post', 'page' ),
			'asset_files'         => true,
			'minify_css'          => true,
			'revisions'           => 20,
			'editor_theme'        => 'default',
			'admin_bar'           => true,
			'delete_on_uninstall' => false,
			'safe_mode_key'       => '',
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * A single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Saves settings (already sanitized).
	 *
	 * @param array $values Values to merge into the stored settings.
	 */
	public static function update( array $values ) {
		$settings = array_merge( self::all(), $values );
		update_option( self::OPTION, $settings );
		self::$cache = null;
	}

	/**
	 * Clears the request cache (after an external update_option call).
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Stores defaults and generates the safe mode key on first run.
	 */
	public static function ensure_defaults() {
		$stored   = get_option( self::OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = wp_parse_args( $stored, self::defaults() );
		if ( empty( $settings['safe_mode_key'] ) ) {
			$settings['safe_mode_key'] = self::generate_key();
		}
		if ( $settings !== $stored ) {
			update_option( self::OPTION, $settings );
		}
		self::$cache = null;
	}

	/**
	 * The Settings screen's URL.
	 *
	 * @param string $section Anchor to open at, without the hash.
	 * @return string
	 */
	public static function page_url( $section = '' ) {
		$url = add_query_arg( 'page', 'scriptdock-settings', admin_url( 'admin.php' ) );
		return $section ? $url . '#' . rawurlencode( $section ) : $url;
	}

	/**
	 * Creates a random URL-safe key.
	 *
	 * @return string
	 */
	public static function generate_key() {
		self::$minted_key = strtolower( wp_generate_password( 20, false, false ) );
		return self::$minted_key;
	}

	/**
	 * Sanitizes submitted settings. Used as the register_setting() callback.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$current  = self::all();
		$defaults = self::defaults();

		$post_types = array();
		if ( isset( $input['page_post_types'] ) && is_array( $input['page_post_types'] ) ) {
			foreach ( $input['page_post_types'] as $post_type ) {
				$post_type = sanitize_key( $post_type );
				if ( post_type_exists( $post_type ) ) {
					$post_types[] = $post_type;
				}
			}
		}

		$clean = array(
			'auto_disable'        => ! empty( $input['auto_disable'] ),
			'error_email'         => ! empty( $input['error_email'] ),
			'page_post_types'     => $post_types,
			'asset_files'         => ! empty( $input['asset_files'] ),
			'minify_css'          => ! empty( $input['minify_css'] ),
			'revisions'           => isset( $input['revisions'] ) ? max( 0, min( 200, (int) $input['revisions'] ) ) : $defaults['revisions'],
			'editor_theme'        => isset( $input['editor_theme'] ) && 'dark' === $input['editor_theme'] ? 'dark' : 'default',
			'admin_bar'           => ! empty( $input['admin_bar'] ),
			'delete_on_uninstall' => ! empty( $input['delete_on_uninstall'] ),
			'safe_mode_key'       => self::sanitize_key_value( $input, $current ),
		);

		self::$cache = null;
		return $clean;
	}

	/**
	 * Keeps the safe mode key unless it is being replaced by one made with
	 * generate_key() in this request. A key sent in from outside is never
	 * taken, so nobody can set the key to one they chose.
	 *
	 * @param array $input   Raw input.
	 * @param array $current Current settings.
	 * @return string
	 */
	private static function sanitize_key_value( array $input, array $current ) {
		if ( null !== self::$minted_key && isset( $input['safe_mode_key'] ) && is_string( $input['safe_mode_key'] ) && hash_equals( self::$minted_key, $input['safe_mode_key'] ) ) {
			return self::$minted_key;
		}
		return $current['safe_mode_key'] ? $current['safe_mode_key'] : self::generate_key();
	}
}
