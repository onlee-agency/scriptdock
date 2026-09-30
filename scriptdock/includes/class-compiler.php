<?php
/**
 * Runtime cache builder.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Compiles active snippets into one signed option that the front end reads
 * with a single query, instead of querying posts on every page load.
 */
final class Compiler {

	/**
	 * Option name.
	 */
	const OPTION = 'scriptdock_runtime';

	/**
	 * Payload format version.
	 */
	const FORMAT = 1;

	/**
	 * Loaded runtime data for this request.
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * Whether a rebuild is queued for the end of the request.
	 *
	 * @var bool
	 */
	private static $dirty = false;

	/**
	 * Queues a rebuild at the end of the request.
	 */
	public static function mark_dirty() {
		if ( ! self::$dirty ) {
			self::$dirty = true;
			add_action( 'shutdown', array( __CLASS__, 'rebuild_if_dirty' ), 0 );
		}
	}

	/**
	 * Runs the queued rebuild.
	 */
	public static function rebuild_if_dirty() {
		if ( self::$dirty ) {
			self::rebuild();
		}
	}

	/**
	 * Empty runtime structure.
	 *
	 * @return array
	 */
	public static function empty_data() {
		return array(
			'format'    => self::FORMAT,
			'version'   => SCRIPTDOCK_VERSION,
			'snippets'  => array(),
			'locations' => array(),
			'loader'    => array(),
			'untrusted' => array(),
		);
	}

	/**
	 * Returns the runtime data, verifying its signature.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null !== self::$data ) {
			return self::$data;
		}

		$stored = get_option( self::OPTION );
		$data   = null;
		if ( is_array( $stored ) && isset( $stored['data'], $stored['sig'] ) && Signer::verify( $stored['data'], $stored['sig'] ) ) {
			$decoded = json_decode( $stored['data'], true );
			if ( is_array( $decoded ) && isset( $decoded['format'] ) && self::FORMAT === $decoded['format'] ) {
				$data = $decoded;
			}
		}

		if ( null === $data ) {
			// Missing, from an older format, or modified outside ScriptDock: rebuild from verified snippets.
			$data = self::rebuild();
		}

		self::$data = wp_parse_args( $data, self::empty_data() );
		return self::$data;
	}

	/**
	 * Rebuilds the runtime data from the active snippets.
	 *
	 * @return array The new runtime data.
	 */
	public static function rebuild() {
		self::$dirty = false;

		$data   = self::empty_data();
		$keep   = array();
		$minify = (bool) Settings::get( 'minify_css' );
		$files  = (bool) Settings::get( 'asset_files' );

		$snippets = Snippets::query( array( 'post_status' => 'publish' ) );

		foreach ( $snippets as $snippet ) {
			if ( ! $snippet->is_trusted() ) {
				$data['untrusted'][] = $snippet->id;
				continue;
			}
			if ( $snippet->is_php() && ! Capabilities::php_enabled() ) {
				continue;
			}
			if ( 'on_demand' === $snippet->location || ! Registry::type_allowed_at( $snippet->type, $snippet->location ) ) {
				continue;
			}
			if ( 'custom_hook' === $snippet->location && empty( $snippet->location_args['hook'] ) ) {
				continue;
			}

			$code = $snippet->code;
			if ( 'css' === $snippet->type && $minify ) {
				$code = Minifier::css( $code );
			}

			$conditions = Conditions::is_active( $snippet->conditions ) ? $snippet->conditions : array();

			$entry = array(
				'id'       => $snippet->id,
				'title'    => $snippet->title,
				'type'     => $snippet->type,
				'location' => $snippet->location,
				'args'     => $snippet->location_args,
				'priority' => (int) $snippet->priority,
				'cond'     => $conditions,
				'query'    => Conditions::requires_query( $conditions ),
				'start'    => self::to_timestamp( $snippet->schedule['start'] ),
				'end'      => self::to_timestamp( $snippet->schedule['end'] ),
				'options'  => $snippet->options,
				'code'     => $code,
			);

			if ( $files && self::wants_file( $snippet ) ) {
				$file = Assets::write( $snippet->id, $snippet->type, $code );
				if ( $file ) {
					$entry['file'] = $file;
					$keep[]        = $file;
				}
			}

			$context = self::context_for( $snippet->location );
			if ( self::needs_loader( $entry ) ) {
				$data['loader'][ $context ] = true;
			}

			$data['snippets'][ $snippet->id ]         = $entry;
			$data['locations'][ $snippet->location ][] = $snippet->id;
		}

		foreach ( $data['locations'] as $location => $ids ) {
			usort(
				$ids,
				static function ( $a, $b ) use ( $data ) {
					$pa = $data['snippets'][ $a ]['priority'];
					$pb = $data['snippets'][ $b ]['priority'];
					return $pa === $pb ? $a - $b : $pa - $pb;
				}
			);
			$data['locations'][ $location ] = $ids;
		}

		Assets::cleanup( $keep );

		$json = wp_json_encode( $data );
		if ( false !== $json ) {
			// Autoloaded, so pages read it along with the other options. Very
			// large snippets make it too big for that: every request would
			// carry it, and the object cache's copy of all options could pass
			// its size limit. Then it loads with a query of its own instead.
			update_option(
				self::OPTION,
				array(
					'data' => $json,
					'sig'  => Signer::sign( $json ),
				),
				strlen( $json ) <= self::autoload_limit()
			);
		}

		self::$data = $data;

		/**
		 * Fires after the runtime cache has been rebuilt.
		 *
		 * @param array $data Runtime data.
		 */
		do_action( 'scriptdock_rebuilt', $data );

		return $data;
	}

	/**
	 * The largest runtime cache that is autoloaded: WordPress's own limit for
	 * the options it decides about, 150 KB unless the site changed it.
	 *
	 * @return int Bytes.
	 */
	private static function autoload_limit() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
		return (int) apply_filters( 'wp_max_autoloaded_option_size', 150000 );
	}

	/**
	 * Whether a snippet should be written to a static file.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return bool
	 */
	private static function wants_file( Snippet $snippet ) {
		if ( ! in_array( $snippet->type, array( 'css', 'js' ), true ) ) {
			return false;
		}
		if ( 'file' !== $snippet->options['output'] || ! in_array( $snippet->location, Registry::FILE_LOCATIONS, true ) ) {
			return false;
		}
		// Smart tags are resolved per request, so such code has to stay inline.
		if ( 'js' === $snippet->type && $snippet->options['smart_tags'] && false !== strpos( $snippet->code, '{{' ) ) {
			return false;
		}
		return '' !== trim( $snippet->code );
	}

	/**
	 * Whether a compiled entry needs the delayed-script loader.
	 *
	 * @param array $entry Compiled entry.
	 * @return bool
	 */
	private static function needs_loader( array $entry ) {
		if ( ! in_array( $entry['type'], array( 'js', 'html' ), true ) ) {
			return false;
		}
		$options = $entry['options'];
		if ( '' !== $options['consent'] || in_array( $options['strategy'], array( 'idle', 'interaction' ), true ) ) {
			return true;
		}
		// Inline scripts cannot use the defer attribute, so the loader runs them after parsing.
		return 'defer' === $options['strategy'] && empty( $entry['file'] );
	}

	/**
	 * Where a location's output ends up: frontend, admin or login.
	 *
	 * @param string $location Location key.
	 * @return string
	 */
	public static function context_for( $location ) {
		if ( isset( Registry::ACTION_LOCATIONS[ $location ] ) ) {
			return Registry::ACTION_LOCATIONS[ $location ][1];
		}
		return 'block_editor' === $location ? 'admin' : 'frontend';
	}

	/**
	 * Converts a site-timezone date string to a Unix timestamp.
	 *
	 * @param string $value Date in Y-m-d\TH:i format.
	 * @return int 0 when empty or invalid.
	 */
	public static function to_timestamp( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}
		$date = date_create_immutable_from_format( 'Y-m-d\TH:i', $value, wp_timezone() );
		return $date ? $date->getTimestamp() : 0;
	}
}
