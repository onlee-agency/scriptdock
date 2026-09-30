<?php
/**
 * Runs and prints snippets.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the compiled runtime data once per request and attaches every
 * active snippet to its location.
 */
final class Runtime {

	/**
	 * Runtime data.
	 *
	 * @var array
	 */
	private static $data = array();

	/**
	 * Snippet being saved or run in this request; skipped at boot so its old
	 * version does not collide with the test run.
	 *
	 * @var int
	 */
	private static $skip_id = 0;

	/**
	 * IDs of snippets that ran or printed in this request.
	 *
	 * @var array
	 */
	private static $rendered = array();

	/**
	 * Last error from run_source(), for test runs.
	 *
	 * @var array|null
	 */
	public static $last_error = null;

	/**
	 * Re-entrancy guard for content filters.
	 *
	 * @var bool
	 */
	private static $in_content = false;

	/**
	 * Hooks the boot routine.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 1 );
	}

	/**
	 * Attaches snippets to their locations.
	 */
	public static function boot() {
		if ( Safe_Mode::is_active() ) {
			return;
		}

		self::$skip_id = self::rest_snippet_id();
		self::$data    = Compiler::get();

		foreach ( self::$data['locations'] as $location => $ids ) {
			self::register( (string) $location, (array) $ids );
		}

		$loader = self::$data['loader'];
		if ( ! empty( $loader['frontend'] ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_loader' ) );
		}
		if ( ! empty( $loader['admin'] ) ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_loader' ) );
		}
		if ( ! empty( $loader['login'] ) ) {
			add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_loader' ) );
		}
	}

	/**
	 * Registers one location.
	 *
	 * @param string $location Location key.
	 * @param int[]  $ids      Snippet IDs, sorted by priority.
	 */
	private static function register( $location, array $ids ) {
		if ( in_array( $location, Registry::EXECUTE_LOCATIONS, true ) ) {
			foreach ( $ids as $id ) {
				self::boot_php( self::snippet( $id ) );
			}
			return;
		}

		if ( in_array( $location, Registry::CONTENT_LOCATIONS, true ) ) {
			if ( ! has_filter( 'the_content', array( __CLASS__, 'filter_content' ) ) ) {
				/**
				 * Filters the priority of the content insertion filter.
				 *
				 * @param int $priority Priority. Default 20, after shortcodes and paragraphs are processed.
				 */
				add_filter( 'the_content', array( __CLASS__, 'filter_content' ), (int) apply_filters( 'scriptdock_content_priority', 20 ) );
			}
			return;
		}

		switch ( $location ) {
			case 'before_excerpt':
			case 'after_excerpt':
				if ( ! has_filter( 'the_excerpt', array( __CLASS__, 'filter_excerpt' ) ) ) {
					add_filter( 'the_excerpt', array( __CLASS__, 'filter_excerpt' ), 20 );
				}
				return;

			case 'between_posts':
				add_action( 'the_post', array( __CLASS__, 'between_posts' ), 10, 2 );
				return;

			case 'custom_hook':
				foreach ( $ids as $id ) {
					self::register_custom_hook( self::snippet( $id ) );
				}
				return;

			case 'block_editor':
				add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor' ) );
				add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_block_content' ) );
				return;

			case 'shortcode':
				return;
		}

		if ( ! isset( Registry::ACTION_LOCATIONS[ $location ] ) ) {
			return;
		}

		$hook        = Registry::ACTION_LOCATIONS[ $location ][0];
		$by_priority = array();
		foreach ( $ids as $id ) {
			$snippet = self::snippet( $id );
			if ( self::uses_enqueue( $snippet ) ) {
				self::register_file( $snippet );
				continue;
			}
			$by_priority[ $snippet['priority'] ][] = $id;
		}

		foreach ( $by_priority as $priority => $group ) {
			add_action(
				$hook,
				static function ( ...$args ) use ( $group ) {
					self::output_group( $group, $args );
					return isset( $args[0] ) ? $args[0] : null;
				},
				(int) $priority,
				10
			);
		}
	}

	/**
	 * A compiled snippet.
	 *
	 * @param int $id Snippet ID.
	 * @return array
	 */
	private static function snippet( $id ) {
		return self::$data['snippets'][ $id ];
	}

	/**
	 * Runs a PHP snippet at boot, or defers it to `wp` when its conditions
	 * need to know which page is being viewed.
	 *
	 * @param array $snippet Compiled snippet.
	 */
	private static function boot_php( array $snippet ) {
		if ( (int) $snippet['id'] === self::$skip_id ) {
			return;
		}
		if ( 'php_frontend' === $snippet['location'] && is_admin() ) {
			return;
		}
		if ( 'php_admin' === $snippet['location'] && ! is_admin() ) {
			return;
		}

		if ( $snippet['query'] && ! is_admin() ) {
			add_action(
				'wp',
				static function () use ( $snippet ) {
					if ( self::should_render( $snippet ) ) {
						self::execute( $snippet );
					}
				},
				(int) $snippet['priority']
			);
			return;
		}

		if ( self::should_render( $snippet ) ) {
			self::execute( $snippet );
		}
	}

	/**
	 * Attaches a snippet to a user-defined action.
	 *
	 * The callback returns its first argument, so hooking a filter by mistake
	 * does not wipe the filtered value.
	 *
	 * @param array $snippet Compiled snippet.
	 */
	private static function register_custom_hook( array $snippet ) {
		if ( (int) $snippet['id'] === self::$skip_id || empty( $snippet['args']['hook'] ) ) {
			return;
		}
		add_action(
			$snippet['args']['hook'],
			static function ( ...$args ) use ( $snippet ) {
				if ( self::should_render( $snippet ) ) {
					if ( 'php' === $snippet['type'] ) {
						self::execute( $snippet, array( 'hook_args' => $args ) );
					} else {
						echo self::render( $snippet, array( 'hook_args' => $args ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unfiltered snippet code written by an administrator.
					}
				}
				return isset( $args[0] ) ? $args[0] : null;
			},
			(int) $snippet['priority'],
			10
		);
	}

	/**
	 * Prints a group of snippets at an action hook.
	 *
	 * @param int[] $ids  Snippet IDs.
	 * @param array $args Hook arguments.
	 */
	private static function output_group( array $ids, array $args ) {
		foreach ( $ids as $id ) {
			$snippet = self::snippet( $id );
			if ( self::should_render( $snippet ) ) {
				echo self::render( $snippet, array( 'hook_args' => $args ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unfiltered snippet code written by an administrator.
			}
		}
	}

	/**
	 * Whether the current request should get a snippet.
	 *
	 * @param array $snippet Compiled snippet.
	 * @return bool
	 */
	public static function should_render( array $snippet ) {
		if ( (int) $snippet['id'] === self::$skip_id ) {
			return false;
		}
		$now = time();
		if ( ! empty( $snippet['start'] ) && $now < $snippet['start'] ) {
			return false;
		}
		if ( ! empty( $snippet['end'] ) && $now >= $snippet['end'] ) {
			return false;
		}
		if ( ! empty( $snippet['options']['test_mode'] ) && ! Capabilities::can_manage() ) {
			return false;
		}
		if ( Page_Scripts::is_disabled_here( (int) $snippet['id'] ) ) {
			return false;
		}
		return Conditions::match( $snippet['cond'] );
	}

	/**
	 * Output of a snippet.
	 *
	 * @param array $snippet Compiled snippet.
	 * @param array $vars    Context: atts, content, hook_args.
	 * @return string
	 */
	public static function render( array $snippet, array $vars = array() ) {
		$options = $snippet['options'];
		$id      = (int) $snippet['id'];

		switch ( $snippet['type'] ) {
			case 'html':
				self::$rendered[ $id ] = true;
				$html                  = $snippet['code'];
				if ( ! empty( $options['smart_tags'] ) ) {
					$html = Smart_Tags::replace( $html, 'html', $vars );
				}
				if ( ! empty( $options['shortcodes'] ) ) {
					$html = do_shortcode( $html );
				}
				if ( self::is_managed( $snippet ) ) {
					$html = self::manage_scripts_in_html( $html, $options );
				}
				return $html . "\n";

			case 'css':
				self::$rendered[ $id ] = true;
				return '<style id="scriptdock-css-' . $id . '">' . $snippet['code'] . "</style>\n";

			case 'js':
				self::$rendered[ $id ] = true;
				return self::script_tag( $snippet, $vars );

			case 'php':
			case 'universal':
				return self::execute( $snippet, $vars, true );
		}
		return '';
	}

	/**
	 * Runs a PHP or Universal snippet.
	 *
	 * @param array $snippet Compiled snippet.
	 * @param array $vars    Variables for the snippet.
	 * @param bool  $capture Whether to capture and return output.
	 * @return string Captured output.
	 */
	public static function execute( array $snippet, array $vars = array(), $capture = false ) {
		if ( ! Capabilities::php_enabled() ) {
			return '';
		}
		self::$rendered[ (int) $snippet['id'] ] = true;
		$source                                 = 'php' === $snippet['type'] ? '<?php ' . $snippet['code'] : $snippet['code'];
		return self::run_source( (int) $snippet['id'], $source, $vars, $capture );
	}

	/**
	 * Includes PHP source through the snippet stream wrapper.
	 *
	 * @param int    $id      Snippet ID (names the virtual file).
	 * @param string $source  Full source, starting in HTML mode.
	 * @param array  $vars    Variables for the snippet.
	 * @param bool   $capture Whether to capture and return output.
	 * @return string Captured output; empty when the snippet threw an error.
	 */
	public static function run_source( $id, $source, array $vars = array(), $capture = false ) {
		self::$last_error = null;
		$path             = Stream::path_for( $id, $source );
		$level            = ob_get_level();

		if ( $capture ) {
			ob_start();
		}

		$previous = Error_Handler::begin( $id );
		try {
			self::include_file( $path, $vars );
		} catch ( \Throwable $error ) {
			self::$last_error = Error_Handler::handle_throwable( $id, $error );
		}
		Error_Handler::end( $previous );

		if ( ! $capture ) {
			return '';
		}

		// Fold any buffers the snippet left open into ours.
		while ( ob_get_level() > $level + 1 ) {
			ob_end_flush();
		}
		$output = ob_get_level() > $level ? (string) ob_get_clean() : '';
		return null === self::$last_error ? $output : '';
	}

	/**
	 * Includes a snippet with a small, predictable set of variables.
	 *
	 * Available to snippets: $atts (shortcode attributes), $content (enclosed
	 * shortcode content) and $hook_args (arguments of the hook being run).
	 *
	 * @param string $scriptdock_file Stream path.
	 * @param array  $scriptdock_vars Variables.
	 */
	private static function include_file( $scriptdock_file, array $scriptdock_vars ) {
		$atts      = isset( $scriptdock_vars['atts'] ) ? $scriptdock_vars['atts'] : array();
		$content   = isset( $scriptdock_vars['content'] ) ? $scriptdock_vars['content'] : '';
		$hook_args = isset( $scriptdock_vars['hook_args'] ) ? $scriptdock_vars['hook_args'] : array();
		unset( $scriptdock_vars );
		include $scriptdock_file;
	}

	/**
	 * Builds a script tag for a JavaScript snippet.
	 *
	 * @param array $snippet Compiled snippet.
	 * @param array $vars    Context for smart tags.
	 * @return string
	 */
	private static function script_tag( array $snippet, array $vars ) {
		$options    = $snippet['options'];
		$attributes = array( 'id' => 'scriptdock-js-' . (int) $snippet['id'] );
		$code       = $snippet['code'];

		if ( ! empty( $options['smart_tags'] ) ) {
			$code = Smart_Tags::replace( $code, 'js', $vars );
		}

		if ( self::is_managed( $snippet ) ) {
			$attributes['type'] = 'text/plain';
			$attributes         = array_merge( $attributes, self::managed_attributes( $options ) );
		}

		if ( ! empty( $snippet['file'] ) && Assets::exists( $snippet['file'] ) ) {
			$attributes['src'] = Assets::url( $snippet['file'] );
			return wp_get_script_tag( $attributes );
		}

		return wp_get_inline_script_tag( $code, $attributes );
	}

	/**
	 * Whether the delayed-script loader handles a snippet.
	 *
	 * @param array $snippet Compiled snippet.
	 * @return bool
	 */
	private static function is_managed( array $snippet ) {
		$options = $snippet['options'];
		if ( ! in_array( $snippet['type'], array( 'js', 'html' ), true ) ) {
			return false;
		}
		if ( '' !== $options['consent'] || in_array( $options['strategy'], array( 'idle', 'interaction' ), true ) ) {
			return true;
		}
		return 'defer' === $options['strategy'] && ( 'html' === $snippet['type'] || empty( $snippet['file'] ) );
	}

	/**
	 * Data attributes read by the loader.
	 *
	 * @param array $options Snippet options.
	 * @return array
	 */
	private static function managed_attributes( array $options ) {
		$delay = array(
			'defer'       => 'dom',
			'idle'        => 'idle',
			'interaction' => 'interaction',
		);
		$attributes = array();
		if ( isset( $delay[ $options['strategy'] ] ) ) {
			$attributes['data-scriptdock-delay'] = $delay[ $options['strategy'] ];
		}
		if ( '' !== $options['consent'] ) {
			$attributes['data-scriptdock-consent'] = $options['consent'];
		}
		if ( ! $attributes ) {
			$attributes['data-scriptdock-delay'] = 'dom';
		}
		return $attributes;
	}

	/**
	 * Hands the <script> tags inside an HTML snippet to the loader.
	 *
	 * @param string $html    HTML.
	 * @param array  $options Snippet options.
	 * @return string
	 */
	private static function manage_scripts_in_html( $html, array $options ) {
		if ( false === stripos( $html, '<script' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$processor = new \WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( 'SCRIPT' ) ) {
			$type = $processor->get_attribute( 'type' );
			$type = is_string( $type ) ? strtolower( trim( $type ) ) : '';
			// Leave data blocks such as JSON-LD alone.
			if ( '' !== $type && ! in_array( $type, array( 'text/javascript', 'application/javascript', 'module' ), true ) ) {
				continue;
			}
			if ( '' !== $type ) {
				$processor->set_attribute( 'data-scriptdock-type', $type );
			}
			$processor->set_attribute( 'type', 'text/plain' );
			foreach ( self::managed_attributes( $options ) as $name => $value ) {
				$processor->set_attribute( $name, $value );
			}
		}
		return $processor->get_updated_html();
	}

	/**
	 * Whether a snippet is loaded through the core enqueue APIs.
	 *
	 * @param array $snippet Compiled snippet.
	 * @return bool
	 */
	private static function uses_enqueue( array $snippet ) {
		if ( empty( $snippet['file'] ) ) {
			return false;
		}
		return 'css' === $snippet['type'] || ! self::is_managed( $snippet );
	}

	/**
	 * Hooks a CSS or JavaScript file into the right enqueue action.
	 *
	 * @param array $snippet Compiled snippet.
	 */
	private static function register_file( array $snippet ) {
		$location = $snippet['location'];
		$context  = Compiler::context_for( $location );
		$footer   = in_array( $location, array( 'site_footer', 'admin_footer', 'login_footer' ), true );
		$hooks    = array(
			'frontend' => 'wp_enqueue_scripts',
			'admin'    => 'admin_enqueue_scripts',
			'login'    => 'login_enqueue_scripts',
		);
		$hook     = $hooks[ $context ];
		$priority = (int) $snippet['priority'];

		// Styles enqueued during the footer are printed there as "late" styles.
		if ( 'css' === $snippet['type'] && $footer ) {
			$footer_hooks = array(
				'frontend' => 'wp_footer',
				'admin'    => 'admin_footer',
				'login'    => 'login_footer',
			);
			$hook         = $footer_hooks[ $context ];
			$priority     = 1;
		}

		add_action(
			$hook,
			static function () use ( $snippet, $footer ) {
				self::enqueue_file( $snippet, $footer );
			},
			$priority
		);
	}

	/**
	 * Enqueues a snippet file, falling back to inline output if it is missing.
	 *
	 * @param array $snippet   Compiled snippet.
	 * @param bool  $in_footer Whether scripts go in the footer.
	 */
	private static function enqueue_file( array $snippet, $in_footer ) {
		if ( ! self::should_render( $snippet ) ) {
			return;
		}
		self::$rendered[ (int) $snippet['id'] ] = true;
		$handle                                 = 'scriptdock-' . (int) $snippet['id'];
		$exists                                 = Assets::exists( $snippet['file'] );

		if ( 'css' === $snippet['type'] ) {
			if ( $exists ) {
				wp_enqueue_style( $handle, Assets::url( $snippet['file'] ), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The file name contains a content hash.
			} else {
				wp_register_style( $handle, false, array(), SCRIPTDOCK_VERSION );
				wp_enqueue_style( $handle );
				wp_add_inline_style( $handle, $snippet['code'] );
				Compiler::mark_dirty();
			}
			return;
		}

		$args = array( 'in_footer' => $in_footer );
		if ( in_array( $snippet['options']['strategy'], array( 'defer', 'async' ), true ) ) {
			$args['strategy'] = $snippet['options']['strategy'];
		}
		if ( $exists ) {
			wp_enqueue_script( $handle, Assets::url( $snippet['file'] ), array(), null, $args ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The file name contains a content hash.
		} else {
			wp_register_script( $handle, false, array(), SCRIPTDOCK_VERSION, array( 'in_footer' => $in_footer ) );
			wp_enqueue_script( $handle );
			wp_add_inline_script( $handle, $snippet['code'] );
			Compiler::mark_dirty();
		}
	}

	/**
	 * Loads block editor JavaScript and editor UI styles.
	 */
	public static function enqueue_block_editor() {
		foreach ( self::$data['locations']['block_editor'] as $id ) {
			$snippet = self::snippet( $id );
			if ( 'js' === $snippet['type'] ) {
				self::enqueue_editor_asset( $snippet );
			}
		}
	}

	/**
	 * Loads CSS inside the block editor canvas (admin only).
	 */
	public static function enqueue_block_content() {
		if ( ! is_admin() ) {
			return;
		}
		foreach ( self::$data['locations']['block_editor'] as $id ) {
			$snippet = self::snippet( $id );
			if ( 'css' === $snippet['type'] ) {
				self::enqueue_editor_asset( $snippet );
			}
		}
	}

	/**
	 * Enqueues one block editor asset.
	 *
	 * @param array $snippet Compiled snippet.
	 */
	private static function enqueue_editor_asset( array $snippet ) {
		if ( ! self::should_render( $snippet ) ) {
			return;
		}
		$handle = 'scriptdock-editor-' . (int) $snippet['id'];
		$file   = ! empty( $snippet['file'] ) && Assets::exists( $snippet['file'] ) ? Assets::url( $snippet['file'] ) : false;
		if ( 'css' === $snippet['type'] ) {
			wp_register_style( $handle, $file, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The file name contains a content hash.
			wp_enqueue_style( $handle );
			if ( ! $file ) {
				wp_add_inline_style( $handle, $snippet['code'] );
			}
			return;
		}
		wp_register_script( $handle, $file, array( 'wp-blocks', 'wp-dom-ready' ), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The file name contains a content hash.
		wp_enqueue_script( $handle );
		if ( ! $file ) {
			wp_add_inline_script( $handle, $snippet['code'] );
		}
	}

	/**
	 * Enqueues the delayed-script loader.
	 */
	public static function enqueue_loader() {
		wp_enqueue_script( 'scriptdock-loader', SCRIPTDOCK_URL . 'assets/js/loader.js', array(), Plugin::asset_version( 'js/loader.js' ), array( 'in_footer' => true ) );
		// A consent plugin is installed, so code waiting for consent must keep
		// waiting even if the plugin's own script arrives after ours.
		if ( function_exists( 'wp_has_consent' ) ) {
			wp_add_inline_script( 'scriptdock-loader', 'window.scriptdockConsentApi = true;', 'before' );
		}
	}

	/**
	 * Whether a the_content call is the main content of a single view.
	 *
	 * @return bool
	 */
	private static function is_main_content() {
		if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}
		if ( doing_filter( 'get_the_excerpt' ) || doing_filter( 'wp_trim_excerpt' ) ) {
			return false;
		}
		return (int) get_the_ID() === (int) get_queried_object_id();
	}

	/**
	 * Inserts content snippets.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( self::$in_content || ! self::is_main_content() ) {
			return $content;
		}
		self::$in_content = true;

		$before = '';
		$after  = '';
		foreach ( Registry::CONTENT_LOCATIONS as $location ) {
			if ( empty( self::$data['locations'][ $location ] ) ) {
				continue;
			}
			foreach ( self::$data['locations'][ $location ] as $id ) {
				$snippet = self::snippet( $id );
				if ( ! self::should_render( $snippet ) ) {
					continue;
				}
				$html = self::render( $snippet );
				if ( '' === trim( $html ) ) {
					continue;
				}
				switch ( $location ) {
					case 'before_content':
						$before .= $html;
						break;
					case 'after_content':
						$after .= $html;
						break;
					default:
						$number  = isset( $snippet['args']['paragraph'] ) ? (int) $snippet['args']['paragraph'] : 1;
						$content = self::insert_at_paragraph( $content, $html, $number, 'after_paragraph' === $location );
				}
			}
		}

		self::$in_content = false;
		return $before . $content . $after;
	}

	/**
	 * Inserts HTML before or after the Nth paragraph.
	 *
	 * Falls back to the end of the content when there are fewer paragraphs.
	 *
	 * @param string $content Content.
	 * @param string $html    HTML to insert.
	 * @param int    $number  Paragraph number, starting at 1.
	 * @param bool   $after   Insert after (true) or before (false).
	 * @return string
	 */
	public static function insert_at_paragraph( $content, $html, $number, $after ) {
		$number  = max( 1, (int) $number );
		$pattern = $after ? '#</p>#i' : '#<p(?:\s[^>]*)?>#i';
		if ( preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) && count( $matches[0] ) >= $number ) {
			$match    = $matches[0][ $number - 1 ];
			$position = $after ? $match[1] + strlen( $match[0] ) : $match[1];
			return substr( $content, 0, $position ) . $html . substr( $content, $position );
		}
		return $content . $html;
	}

	/**
	 * Inserts excerpt snippets.
	 *
	 * @param string $excerpt Excerpt HTML.
	 * @return string
	 */
	public static function filter_excerpt( $excerpt ) {
		if ( self::$in_content || is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() ) {
			return $excerpt;
		}
		self::$in_content = true;
		foreach ( array( 'before_excerpt', 'after_excerpt' ) as $location ) {
			if ( empty( self::$data['locations'][ $location ] ) ) {
				continue;
			}
			foreach ( self::$data['locations'][ $location ] as $id ) {
				$snippet = self::snippet( $id );
				if ( self::should_render( $snippet ) ) {
					$html    = self::render( $snippet );
					$excerpt = 'before_excerpt' === $location ? $html . $excerpt : $excerpt . $html;
				}
			}
		}
		self::$in_content = false;
		return $excerpt;
	}

	/**
	 * Prints snippets between posts of the main archive loop.
	 *
	 * @param \WP_Post  $post  Current post.
	 * @param \WP_Query $query Query.
	 */
	public static function between_posts( $post, $query ) {
		if ( is_admin() || is_feed() || ! $query instanceof \WP_Query || ! $query->is_main_query() || $query->is_singular() ) {
			return;
		}
		// Block themes render the loop into a string, so echoing here would land in the wrong place.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return;
		}
		$index = (int) $query->current_post;
		if ( $index < 1 ) {
			return;
		}
		foreach ( self::$data['locations']['between_posts'] as $id ) {
			$snippet = self::snippet( $id );
			$every   = isset( $snippet['args']['every'] ) ? max( 1, (int) $snippet['args']['every'] ) : 3;
			if ( 0 === $index % $every && self::should_render( $snippet ) ) {
				echo self::render( $snippet ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unfiltered snippet code written by an administrator.
			}
		}
	}

	/**
	 * Compiled data, for other components.
	 *
	 * @return array
	 */
	public static function data() {
		return self::$data ? self::$data : Compiler::get();
	}

	/**
	 * IDs of snippets that ran or printed in this request.
	 *
	 * @return int[]
	 */
	public static function rendered_ids() {
		return array_keys( self::$rendered );
	}

	/**
	 * ID of the snippet a ScriptDock REST request is about, such as
	 * /scriptdock/v1/snippets/12/deactivate. Skipping it at boot means a
	 * snippet that breaks every request can still be switched off, saved or
	 * trashed from the list.
	 *
	 * The route is read from the URL because REST routing happens long after
	 * PHP snippets boot. Like the check above, it only ever holds a snippet
	 * back, and only for a request to that snippet's own endpoints.
	 *
	 * @return int
	 */
	private static function rest_snippet_id() {
		$route = '';
		if ( isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path   = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
			$prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/';
			$at     = strpos( $path, $prefix );
			$route  = false === $at ? '' : '/' . substr( $path, $at + strlen( $prefix ) );
		}
		return preg_match( '#^/scriptdock/v1/snippets/(\d+)(?:/|$)#', $route, $match ) ? (int) $match[1] : 0;
	}
}
