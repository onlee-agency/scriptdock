<?php
/**
 * Per-page scripts.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Code added to a single post or page from its editor screen, plus the
 * option to switch off site-wide snippets on that page.
 *
 * Stored in one post meta entry and signed like snippets, so code injected
 * straight into the database is ignored.
 */
final class Page_Scripts {

	/**
	 * Meta key.
	 */
	const META = '_scriptdock_page';

	/**
	 * Code fields.
	 */
	const FIELDS = array( 'head', 'body', 'footer', 'before', 'after', 'css', 'js' );

	/**
	 * Request cache of raw data per post.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * True while this class is writing the meta itself, so the signing hook
	 * leaves its own work alone.
	 *
	 * @var bool
	 */
	private static $writing = false;

	/**
	 * Hooks front-end output.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 12 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_body' ), 12 );
		add_action( 'wp_footer', array( __CLASS__, 'print_footer' ), 12 );
		// After footer scripts (priority 20), so page JavaScript can use enqueued libraries.
		add_action( 'wp_footer', array( __CLASS__, 'print_js' ), 21 );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 21 );

		// The block editor saves page code with the post, through this meta.
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'added_post_meta', array( __CLASS__, 'sign_saved' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'sign_saved' ), 10, 4 );
	}

	/**
	 * Post types that get the page scripts box.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return array_values( array_filter( (array) Settings::get( 'page_post_types' ), 'post_type_exists' ) );
	}

	/**
	 * Post types where the block editor panel can read and write the code.
	 *
	 * The panel edits the post's own meta so that page code saves when the post
	 * does. WordPress only exposes meta on post types that support custom
	 * fields, so anything else keeps the classic box instead.
	 *
	 * @return string[]
	 */
	public static function rest_post_types() {
		return array_values(
			array_filter(
				self::post_types(),
				static function ( $type ) {
					return post_type_supports( $type, 'custom-fields' );
				}
			)
		);
	}

	/**
	 * Registers the meta so the block editor can read and write it.
	 */
	public static function register_meta() {
		foreach ( self::rest_post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META,
				array(
					'single'            => true,
					'type'              => 'object',
					'description'       => __( 'Code added to this page only, and site-wide snippets switched off on it.', 'scriptdock' ),
					'show_in_rest'      => array( 'schema' => self::meta_schema() ),
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
					'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
				)
			);
		}
	}

	/**
	 * Shape of the meta over REST.
	 *
	 * @return array
	 */
	private static function meta_schema() {
		$properties = array();
		foreach ( self::FIELDS as $field ) {
			$properties[ $field ] = array( 'type' => 'string' );
		}
		$properties['disable']     = array(
			'type'  => 'array',
			'items' => array( 'type' => 'integer' ),
		);
		$properties['disable_all'] = array( 'type' => 'boolean' );
		// Written by the plugin after every save; anything sent here is ignored.
		$properties['sig']         = array( 'type' => 'string' );

		return array(
			'type'                 => 'object',
			// Only in the editor's answers, never in public reads of the post.
			'context'              => array( 'edit' ),
			'properties'           => $properties,
			'additionalProperties' => false,
		);
	}

	/**
	 * Who may read and write the meta: the same people who may manage
	 * ScriptDock, and only on posts they may edit.
	 *
	 * @param bool   $allowed  Whether the edit is allowed so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function can_edit_meta( $allowed, $meta_key, $post_id, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filter signature.
		return user_can( $user_id, Capabilities::MANAGE ) && user_can( $user_id, 'edit_post', $post_id );
	}

	/**
	 * Puts saved meta into the stored shape. The signature is added afterwards,
	 * in sign_saved(), which is the only place that knows the post ID.
	 *
	 * @param mixed $value Meta value.
	 * @return array
	 */
	public static function sanitize_meta( $value ) {
		return self::normalize( is_array( $value ) ? $value : array() );
	}

	/**
	 * Signs page code saved outside save(), which is how the block editor
	 * saves it: with the post, through the meta.
	 *
	 * Signing approves the code, so it only happens for someone allowed to
	 * edit page code on this post. Anything else that writes the meta — another
	 * plugin copying it to a new post, an import, a feature acting for an
	 * editor — leaves it unsigned, and the code waits for review like any
	 * other change made outside ScriptDock.
	 *
	 * @param int    $meta_id  Meta row ID.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $value    Saved value.
	 */
	public static function sign_saved( $meta_id, $post_id, $meta_key, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Hook signature.
		if ( self::META !== $meta_key || self::$writing ) {
			return;
		}
		if ( ! self::can_edit_meta( false, $meta_key, $post_id, get_current_user_id() ) ) {
			unset( self::$cache[ (int) $post_id ] );
			return;
		}
		self::save( $post_id, is_array( $value ) ? $value : array() );
	}

	/**
	 * Empty data set.
	 *
	 * @return array
	 */
	public static function empty_data() {
		$data                = array_fill_keys( self::FIELDS, '' );
		$data['disable']     = array();
		$data['disable_all'] = false;
		$data['sig']         = '';
		return $data;
	}

	/**
	 * Any input, in the stored shape. Missing keys come back empty.
	 *
	 * Earlier builds stored "disable" as either the string "all" or a list
	 * of IDs; both are still read here, so pages saved before this shape
	 * existed keep working.
	 *
	 * @param array $input Raw data.
	 * @return array
	 */
	private static function normalize( array $input ) {
		$data = self::empty_data();
		foreach ( self::FIELDS as $field ) {
			$data[ $field ] = isset( $input[ $field ] ) ? str_replace( "\r\n", "\n", (string) $input[ $field ] ) : '';
		}

		$disable = isset( $input['disable'] ) ? $input['disable'] : array();
		if ( 'all' === $disable || ! empty( $input['disable_all'] ) ) {
			$data['disable_all'] = true;
		}
		if ( is_array( $disable ) ) {
			$data['disable'] = array_values( array_unique( array_filter( array_map( 'absint', $disable ) ) ) );
		}

		$data['sig'] = isset( $input['sig'] ) ? (string) $input['sig'] : '';
		return $data;
	}

	/**
	 * Stored data for a post, without signature checks.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! isset( self::$cache[ $post_id ] ) ) {
			$stored                  = get_post_meta( $post_id, self::META, true );
			self::$cache[ $post_id ] = self::normalize( is_array( $stored ) ? $stored : array() );
		}
		return self::$cache[ $post_id ];
	}

	/**
	 * Whether a post has any page code.
	 *
	 * @param array $data Page data.
	 * @return bool
	 */
	public static function has_code( array $data ) {
		foreach ( self::FIELDS as $field ) {
			if ( '' !== trim( (string) $data[ $field ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether stored code matches its signature.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Page data.
	 * @return bool
	 */
	public static function is_trusted( $post_id, array $data ) {
		if ( ! self::has_code( $data ) ) {
			return true;
		}
		return Signer::verify( self::payload( $post_id, $data ), $data['sig'] );
	}

	/**
	 * Saves page data and signs it.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $input   Unslashed input.
	 * @param bool  $sign    Whether to sign the code, which approves it.
	 */
	public static function save( $post_id, array $input, $sign = true ) {
		$data = self::normalize( $input );

		unset( self::$cache[ (int) $post_id ] );

		self::$writing = true;
		if ( ! self::has_code( $data ) && ! $data['disable_all'] && ! $data['disable'] ) {
			delete_post_meta( $post_id, self::META );
		} else {
			$data['sig'] = $sign && self::has_code( $data ) ? Signer::sign( self::payload( $post_id, $data ) ) : '';
			update_post_meta( $post_id, self::META, wp_slash( $data ) );
		}
		self::$writing = false;
	}

	/**
	 * Data that the signature covers.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Page data.
	 * @return string
	 */
	private static function payload( $post_id, array $data ) {
		$parts = array( 'page-v1', (int) $post_id );
		foreach ( self::FIELDS as $field ) {
			$parts[] = str_replace( "\r\n", "\n", (string) $data[ $field ] );
		}
		return wp_json_encode( $parts );
	}

	/**
	 * ID of the post being viewed, when page scripts apply to it.
	 *
	 * @return int
	 */
	public static function current_post_id() {
		if ( is_admin() || ! did_action( 'wp' ) ) {
			return 0;
		}
		$post = Conditions::queried_post();
		if ( ! $post || ! in_array( $post->post_type, self::post_types(), true ) ) {
			return 0;
		}
		return (int) $post->ID;
	}

	/**
	 * Verified code for the current page.
	 *
	 * @return array|null
	 */
	private static function current_code() {
		if ( Safe_Mode::is_active() ) {
			return null;
		}
		$post_id = self::current_post_id();
		if ( ! $post_id ) {
			return null;
		}
		$data = self::get( $post_id );
		if ( ! self::has_code( $data ) || ! self::is_trusted( $post_id, $data ) ) {
			return null;
		}
		return $data;
	}

	/**
	 * Whether a site-wide snippet is switched off on the current page.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool
	 */
	public static function is_disabled_here( $snippet_id ) {
		$post_id = self::current_post_id();
		if ( ! $post_id ) {
			return false;
		}
		$data = self::get( $post_id );
		return $data['disable_all'] || in_array( (int) $snippet_id, $data['disable'], true );
	}

	/**
	 * Whether all site-wide code (snippets and the global header/footer) is off here.
	 *
	 * @return bool
	 */
	public static function globals_disabled_here() {
		$post_id = self::current_post_id();
		return $post_id && self::get( $post_id )['disable_all'];
	}

	/**
	 * Prints head code and page CSS.
	 */
	public static function print_head() {
		$data = self::current_code();
		if ( ! $data ) {
			return;
		}
		if ( '' !== trim( $data['css'] ) ) {
			echo '<style id="scriptdock-page-css">' . $data['css'] . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unfiltered code written by an administrator.
		}
		if ( '' !== trim( $data['head'] ) ) {
			echo Smart_Tags::replace( $data['head'], 'html' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unfiltered code written by an administrator.
		}
	}

	/**
	 * Prints code after the opening body tag.
	 */
	public static function print_body() {
		$data = self::current_code();
		if ( $data && '' !== trim( $data['body'] ) ) {
			echo Smart_Tags::replace( $data['body'], 'html' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unfiltered code written by an administrator.
		}
	}

	/**
	 * Prints footer code.
	 */
	public static function print_footer() {
		$data = self::current_code();
		if ( $data && '' !== trim( $data['footer'] ) ) {
			echo Smart_Tags::replace( $data['footer'], 'html' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Unfiltered code written by an administrator.
		}
	}

	/**
	 * Prints page JavaScript.
	 */
	public static function print_js() {
		$data = self::current_code();
		if ( $data && '' !== trim( $data['js'] ) ) {
			wp_print_inline_script_tag( Smart_Tags::replace( $data['js'], 'js' ), array( 'id' => 'scriptdock-page-js' ) );
		}
	}

	/**
	 * Adds content before and after the post content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		if ( (int) get_the_ID() !== self::current_post_id() ) {
			return $content;
		}
		$data = self::current_code();
		if ( ! $data ) {
			return $content;
		}
		$before = '' !== trim( $data['before'] ) ? Smart_Tags::replace( $data['before'], 'html' ) : '';
		$after  = '' !== trim( $data['after'] ) ? Smart_Tags::replace( $data['after'], 'html' ) : '';
		return $before . $content . $after;
	}
}
