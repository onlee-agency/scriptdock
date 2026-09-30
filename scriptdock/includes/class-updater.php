<?php
/**
 * Self-hosted updates from GitHub releases.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Offers updates published as GitHub releases.
 *
 * Configure the repository with SCRIPTDOCK_UPDATE_REPO ("owner/repo"). Each
 * release should have a tag such as v1.2.0 and, ideally, an asset named
 * scriptdock.zip that contains the scriptdock/ folder. The plugin header's
 * Update URI keeps WordPress from looking for this plugin on WordPress.org.
 *
 * Nothing is requested from GitHub until a repository is configured.
 */
final class Updater {

	/**
	 * Cache key for the latest release.
	 */
	const CACHE = 'scriptdock_update_info';

	/**
	 * Hooks the updater.
	 */
	public static function init() {
		if ( ! self::repo() ) {
			return;
		}
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ) );
	}

	/**
	 * Configured repository.
	 *
	 * @return string owner/repo, or an empty string.
	 */
	public static function repo() {
		$repo = defined( 'SCRIPTDOCK_UPDATE_REPO' ) ? (string) SCRIPTDOCK_UPDATE_REPO : '';
		return preg_match( '#^[A-Za-z0-9-]{1,39}/[A-Za-z0-9._-]{1,100}$#', $repo ) ? $repo : '';
	}

	/**
	 * Supplies update data to WordPress.
	 *
	 * @param array|false $update      Update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( SCRIPTDOCK_FILE ) !== $plugin_file ) {
			return $update;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $update;
		}
		return array(
			'slug'         => dirname( plugin_basename( SCRIPTDOCK_FILE ) ),
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires_php' => '7.4',
		);
	}

	/**
	 * "View details" information.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action API action.
	 * @param object             $args   Arguments.
	 * @return false|object|array
	 */
	public static function info( $result, $action, $args ) {
		$slug = dirname( plugin_basename( SCRIPTDOCK_FILE ) );
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $slug !== $args->slug ) {
			return $result;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'ScriptDock',
			'slug'          => $slug,
			'version'       => $release['version'],
			'author'        => 'ScriptDock',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'requires'      => '6.3',
			'requires_php'  => '7.4',
			'last_updated'  => $release['published'],
			'sections'      => array(
				'changelog' => wpautop( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * Latest release, cached for six hours.
	 *
	 * @return array|null
	 */
	private static function latest() {
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached && self::is_own_release( $cached ) ? $cached : null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repo() . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'ScriptDock/' . SCRIPTDOCK_VERSION,
				),
			)
		);

		$body = 200 === wp_remote_retrieve_response_code( $response ) ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			// Remember the failure for an hour so every page load does not retry.
			set_site_transient( self::CACHE, array(), HOUR_IN_SECONDS );
			return null;
		}

		$package = isset( $body['zipball_url'] ) ? (string) $body['zipball_url'] : '';
		foreach ( isset( $body['assets'] ) ? (array) $body['assets'] : array() as $asset ) {
			if ( isset( $asset['name'], $asset['browser_download_url'] ) && 'scriptdock.zip' === $asset['name'] ) {
				$package = (string) $asset['browser_download_url'];
			}
		}

		$release = array(
			'version'   => ltrim( (string) $body['tag_name'], 'vV' ),
			'package'   => esc_url_raw( $package ),
			'url'       => esc_url_raw( isset( $body['html_url'] ) ? $body['html_url'] : 'https://github.com/' . self::repo() ),
			'notes'     => isset( $body['body'] ) ? (string) $body['body'] : '',
			'published' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
		);
		if ( ! self::is_own_release( $release ) ) {
			set_site_transient( self::CACHE, array(), HOUR_IN_SECONDS );
			return null;
		}
		set_site_transient( self::CACHE, $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Whether a release downloads from this plugin's own repository on
	 * GitHub, over HTTPS. Checked every time a cached release is used, not
	 * just when it is fetched: the cache sits in the database, and whatever
	 * it points at is installed as PHP.
	 *
	 * @param array $release Release.
	 * @return bool
	 */
	private static function is_own_release( array $release ) {
		if ( empty( $release['package'] ) || empty( $release['version'] ) || ! preg_match( '/^\d+\.\d+/', (string) $release['version'] ) ) {
			return false;
		}
		$parts = wp_parse_url( (string) $release['package'] );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'], $parts['path'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}
		$host = strtolower( $parts['host'] );
		$path = strtolower( $parts['path'] );
		$repo = strtolower( self::repo() );
		return ( 'github.com' === $host && 0 === strpos( $path, '/' . $repo . '/' ) )
			|| ( 'api.github.com' === $host && 0 === strpos( $path, '/repos/' . $repo . '/' ) );
	}

	/**
	 * Renames GitHub's "owner-repo-hash" folder to the plugin folder.
	 *
	 * @param string       $source        Unpacked source folder.
	 * @param string       $remote_source Parent folder.
	 * @param \WP_Upgrader $upgrader      Upgrader.
	 * @param array        $hook_extra    Extra data.
	 * @return string|\WP_Error
	 */
	public static function fix_folder( $source, $remote_source, $upgrader, $hook_extra ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wp_filesystem;
		if ( empty( $hook_extra['plugin'] ) || plugin_basename( SCRIPTDOCK_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}
		$wanted = trailingslashit( $remote_source ) . dirname( plugin_basename( SCRIPTDOCK_FILE ) ) . '/';
		if ( trailingslashit( $source ) === $wanted ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
			return $wanted;
		}
		return new \WP_Error( 'scriptdock_update_folder', __( 'The update could not be unpacked into the plugin folder.', 'scriptdock' ) );
	}

	/**
	 * Clears the release cache after updates.
	 */
	public static function clear_cache() {
		delete_site_transient( self::CACHE );
	}
}
