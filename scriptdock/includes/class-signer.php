<?php
/**
 * Tamper protection.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Signs snippets with a key that lives outside the database.
 *
 * Attackers who gain database access (for example through SQL injection in
 * another plugin) often hide malware inside snippet plugins' storage. Every
 * snippet saved through ScriptDock is signed with an HMAC keyed by the site's
 * secret keys from wp-config.php. Code that changes without going through
 * ScriptDock no longer matches its signature and is not run until an
 * administrator reviews and re-saves it.
 *
 * Define SCRIPTDOCK_SIGNING_KEY in wp-config.php to use a dedicated key that
 * survives salt rotation.
 */
final class Signer {

	/**
	 * Signing key.
	 *
	 * Kept out of the database wherever the site allows it, because the
	 * signatures exist to catch code written straight into the database. That
	 * rules out wp_salt() with a scheme of our own: unless wp-config.php
	 * defines SECRET_KEY, which the standard file does not, it falls back to
	 * the secret_key option. The auth scheme reads AUTH_KEY and AUTH_SALT from
	 * wp-config.php, and only falls back to the database when they are missing.
	 *
	 * @return string
	 */
	private static function key() {
		if ( defined( 'SCRIPTDOCK_SIGNING_KEY' ) && SCRIPTDOCK_SIGNING_KEY ) {
			return (string) SCRIPTDOCK_SIGNING_KEY;
		}
		// A key of our own, derived from the login keys rather than reusing them.
		return hash_hmac( 'sha256', 'scriptdock-signing', wp_salt( 'auth' ) );
	}

	/**
	 * Where the signing key comes from.
	 *
	 * @return string "constant" for SCRIPTDOCK_SIGNING_KEY, "config" for the
	 *                security keys in wp-config.php, or "database" when
	 *                wp-config.php has none WordPress can use, so it keeps
	 *                generated ones in the database instead.
	 */
	public static function key_source() {
		if ( defined( 'SCRIPTDOCK_SIGNING_KEY' ) && SCRIPTDOCK_SIGNING_KEY ) {
			return 'constant';
		}
		return self::config_has( 'AUTH_KEY' ) && self::config_has( 'AUTH_SALT' ) ? 'config' : 'database';
	}

	/**
	 * Whether wp-config.php defines a security key that wp_salt() will use.
	 * It decides the same way: set, not the sample file's placeholder, and not
	 * the same value as another of the keys.
	 *
	 * @param string $name Constant name.
	 * @return bool
	 */
	private static function config_has( $name ) {
		if ( ! defined( $name ) || ! constant( $name ) || 'put your unique phrase here' === constant( $name ) ) {
			return false;
		}
		$uses = 0;
		foreach ( array( 'AUTH', 'SECURE_AUTH', 'LOGGED_IN', 'NONCE', 'SECRET' ) as $first ) {
			foreach ( array( 'KEY', 'SALT' ) as $second ) {
				if ( defined( "{$first}_{$second}" ) && constant( "{$first}_{$second}" ) === constant( $name ) ) {
					++$uses;
				}
			}
		}
		return 1 === $uses;
	}

	/**
	 * Whether tamper protection is on.
	 *
	 * Can only be turned off from wp-config.php, never from the database.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return ! ( defined( 'SCRIPTDOCK_DISABLE_TAMPER_PROTECTION' ) && SCRIPTDOCK_DISABLE_TAMPER_PROTECTION );
	}

	/**
	 * Signature for arbitrary data.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	public static function sign( $data ) {
		return hash_hmac( 'sha256', (string) $data, self::key() );
	}

	/**
	 * Verifies a signature.
	 *
	 * @param string $data      Data.
	 * @param string $signature Signature.
	 * @return bool
	 */
	public static function verify( $data, $signature ) {
		if ( ! self::enabled() ) {
			return true;
		}
		return is_string( $signature ) && '' !== $signature && hash_equals( self::sign( $data ), $signature );
	}

	/**
	 * The parts of a snippet that decide what code runs and where.
	 *
	 * Kept deliberately small and stable so plugin updates that add new
	 * options never invalidate existing signatures.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return string
	 */
	public static function snippet_payload( Snippet $snippet ) {
		return wp_json_encode(
			array(
				'v1',
				(int) $snippet->id,
				(string) $snippet->type,
				str_replace( "\r\n", "\n", (string) $snippet->code ),
				(string) $snippet->location,
				isset( $snippet->location_args['hook'] ) ? (string) $snippet->location_args['hook'] : '',
			)
		);
	}

	/**
	 * Signs a snippet.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return string
	 */
	public static function sign_snippet( Snippet $snippet ) {
		return self::sign( self::snippet_payload( $snippet ) );
	}

	/**
	 * Whether a snippet's stored signature matches its content.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return bool
	 */
	public static function snippet_is_valid( Snippet $snippet ) {
		return self::verify( self::snippet_payload( $snippet ), $snippet->signature );
	}
}
