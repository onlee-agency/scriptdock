<?php
/**
 * Snippet model.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * A single snippet: loading, validating and saving.
 */
final class Snippet {

	/**
	 * Meta keys.
	 */
	const META_TYPE       = '_scriptdock_type';
	const META_LOCATION   = '_scriptdock_location';
	const META_ARGS       = '_scriptdock_location_args';
	const META_PRIORITY   = '_scriptdock_priority';
	const META_CONDITIONS = '_scriptdock_conditions';
	const META_SCHEDULE   = '_scriptdock_schedule';
	const META_OPTIONS    = '_scriptdock_options';
	const META_SIGNATURE  = '_scriptdock_signature';
	const META_ERROR      = '_scriptdock_last_error';
	const META_SOURCE     = '_scriptdock_source';

	/**
	 * On a revision: when someone approved that version after it changed
	 * outside ScriptDock.
	 */
	const META_APPROVED = '_scriptdock_approved';

	/**
	 * Consent categories from the WP Consent API.
	 */
	const CONSENT_CATEGORIES = array( 'functional', 'preferences', 'statistics', 'statistics-anonymous', 'marketing' );

	/**
	 * Script loading strategies.
	 */
	const STRATEGIES = array( 'default', 'defer', 'async', 'idle', 'interaction' );

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Title.
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Code.
	 *
	 * @var string
	 */
	public $code = '';

	/**
	 * Description / notes.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Type: php, html, css, js or universal.
	 *
	 * @var string
	 */
	public $type = 'html';

	/**
	 * Whether the snippet is active.
	 *
	 * @var bool
	 */
	public $active = false;

	/**
	 * Location key.
	 *
	 * @var string
	 */
	public $location = 'site_header';

	/**
	 * Location arguments (paragraph number, hook name, post interval).
	 *
	 * @var array
	 */
	public $location_args = array();

	/**
	 * Priority. Lower runs first; also used as the hook priority.
	 *
	 * @var int
	 */
	public $priority = 10;

	/**
	 * Conditional logic.
	 *
	 * @var array
	 */
	public $conditions = array();

	/**
	 * Schedule: start and end in the site's timezone, Y-m-d\TH:i.
	 *
	 * @var array
	 */
	public $schedule = array(
		'start' => '',
		'end'   => '',
	);

	/**
	 * Output options.
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Tag names.
	 *
	 * @var string[]
	 */
	public $tags = array();

	/**
	 * Stored signature.
	 *
	 * @var string
	 */
	public $signature = '';

	/**
	 * Last recorded error.
	 *
	 * @var array
	 */
	public $last_error = array();

	/**
	 * Where the snippet came from (library template, import...).
	 *
	 * @var string
	 */
	public $source = '';

	/**
	 * Last modified time (GMT, MySQL format).
	 *
	 * @var string
	 */
	public $modified_gmt = '';

	/**
	 * Sets defaults.
	 */
	public function __construct() {
		$this->conditions = Conditions::empty_set();
		$this->options    = self::default_options();
	}

	/**
	 * Default output options.
	 *
	 * @return array
	 */
	public static function default_options() {
		return array(
			'output'     => 'inline',
			'strategy'   => 'default',
			'consent'    => '',
			'smart_tags' => true,
			'shortcodes' => false,
			'test_mode'  => false,
		);
	}

	/**
	 * Loads a snippet by ID.
	 *
	 * @param int $id Post ID.
	 * @return Snippet|null
	 */
	public static function get( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || Post_Type::NAME !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		return self::from_post( $post );
	}

	/**
	 * Builds a snippet from its post.
	 *
	 * @param \WP_Post $post Post.
	 * @return Snippet
	 */
	public static function from_post( \WP_Post $post ) {
		$snippet               = new self();
		$snippet->id           = (int) $post->ID;
		$snippet->title        = $post->post_title;
		$snippet->code         = $post->post_content;
		$snippet->description  = $post->post_excerpt;
		$snippet->active       = 'publish' === $post->post_status;
		// New drafts store no GMT dates, so derive it from the local date.
		$modified              = get_post_timestamp( $post, 'modified' );
		$snippet->modified_gmt = $modified ? gmdate( 'Y-m-d H:i:s', $modified ) : $post->post_modified_gmt;
		$snippet->type         = Registry::sanitize_type( get_post_meta( $post->ID, self::META_TYPE, true ) );

		$location          = (string) get_post_meta( $post->ID, self::META_LOCATION, true );
		$snippet->location = Registry::location_exists( $location ) ? $location : Registry::default_location( $snippet->type );

		$args                   = get_post_meta( $post->ID, self::META_ARGS, true );
		$snippet->location_args = is_array( $args ) ? $args : array();

		$priority          = get_post_meta( $post->ID, self::META_PRIORITY, true );
		$snippet->priority = '' === $priority ? 10 : (int) $priority;

		$conditions          = get_post_meta( $post->ID, self::META_CONDITIONS, true );
		$snippet->conditions = is_array( $conditions ) ? wp_parse_args( $conditions, Conditions::empty_set() ) : Conditions::empty_set();

		$schedule          = get_post_meta( $post->ID, self::META_SCHEDULE, true );
		$snippet->schedule = is_array( $schedule ) ? wp_parse_args( $schedule, $snippet->schedule ) : $snippet->schedule;

		$options          = get_post_meta( $post->ID, self::META_OPTIONS, true );
		$snippet->options = is_array( $options ) ? wp_parse_args( $options, self::default_options() ) : self::default_options();

		$snippet->signature = (string) get_post_meta( $post->ID, self::META_SIGNATURE, true );
		$snippet->source    = (string) get_post_meta( $post->ID, self::META_SOURCE, true );

		$error               = get_post_meta( $post->ID, self::META_ERROR, true );
		$snippet->last_error = is_array( $error ) ? $error : array();

		$terms         = get_the_terms( $post->ID, Post_Type::TAXONOMY );
		$snippet->tags = is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();

		return $snippet;
	}

	/**
	 * Fills the snippet from untrusted data (form submission or import file).
	 *
	 * Code is stored exactly as given: it is meant to be unfiltered and only
	 * users allowed to publish unfiltered code can reach this.
	 *
	 * @param array $data Raw, unslashed data.
	 */
	public function fill( array $data ) {
		if ( isset( $data['title'] ) ) {
			$this->title = self::clean_line( $data['title'] );
		}
		if ( isset( $data['code'] ) ) {
			$this->code = (string) $data['code'];
		}
		if ( isset( $data['description'] ) ) {
			$this->description = self::clean_text( $data['description'] );
		}
		if ( isset( $data['type'] ) ) {
			$this->type = Registry::sanitize_type( $data['type'] );
		}
		if ( isset( $data['active'] ) ) {
			$this->active = (bool) $data['active'];
		}
		if ( isset( $data['location'] ) ) {
			$this->location = sanitize_key( (string) $data['location'] );
		}
		if ( ! Registry::type_allowed_at( $this->type, $this->location ) ) {
			$this->location = Registry::default_location( $this->type );
		}
		if ( isset( $data['location_args'] ) && is_array( $data['location_args'] ) ) {
			$this->location_args = self::sanitize_location_args( $data['location_args'] );
		}
		if ( isset( $data['priority'] ) ) {
			$this->priority = max( -9999, min( 9999, (int) $data['priority'] ) );
		}
		if ( isset( $data['conditions'] ) ) {
			$this->conditions = Conditions::sanitize( $data['conditions'] );
		}
		if ( isset( $data['schedule'] ) && is_array( $data['schedule'] ) ) {
			$this->schedule = self::sanitize_schedule( $data['schedule'] );
		}
		if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
			$this->options = self::sanitize_options( $data['options'] );
		}
		if ( isset( $data['tags'] ) ) {
			$tags       = is_array( $data['tags'] ) ? $data['tags'] : explode( ',', (string) $data['tags'] );
			$this->tags = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $tags ) ) ) );
		}
		if ( isset( $data['source'] ) ) {
			$this->source = sanitize_text_field( (string) $data['source'] );
		}
		$this->normalize_code();
	}

	/**
	 * A title as typed, on one line. Titles often name tags ("Add <meta> tag
	 * for Google"), so nothing that looks like HTML or a percent-encoded
	 * character is removed the way sanitize_text_field() would: every place
	 * a title is shown escapes it. Only invalid UTF-8, control characters and
	 * line breaks go.
	 *
	 * @param mixed $text Raw title.
	 * @return string
	 */
	public static function clean_line( $text ) {
		$text = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', wp_check_invalid_utf8( (string) $text ) );
		return trim( preg_replace( '/ {2,}/', ' ', $text ) );
	}

	/**
	 * Notes as typed, line breaks kept. See clean_line().
	 *
	 * @param mixed $text Raw notes.
	 * @return string
	 */
	public static function clean_text( $text ) {
		$text = wp_check_invalid_utf8( str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) );
		return trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $text ) );
	}

	/**
	 * Removes a leading <?php tag from PHP snippets; the runner adds its own.
	 */
	public function normalize_code() {
		$this->code = str_replace( "\r\n", "\n", $this->code );
		if ( 'php' === $this->type ) {
			$this->code = preg_replace( '/^\s*<\?php\s*/i', '', $this->code, 1 );
			$this->code = preg_replace( '/\?>\s*$/', '', rtrim( $this->code ), 1 );
		}
	}

	/**
	 * Sanitizes location arguments.
	 *
	 * @param array $args Raw arguments.
	 * @return array
	 */
	public static function sanitize_location_args( array $args ) {
		$clean = array();
		if ( isset( $args['paragraph'] ) ) {
			$clean['paragraph'] = max( 1, min( 999, (int) $args['paragraph'] ) );
		}
		if ( isset( $args['every'] ) ) {
			$clean['every'] = max( 1, min( 999, (int) $args['every'] ) );
		}
		if ( isset( $args['hook'] ) ) {
			$clean['hook'] = substr( preg_replace( '/[^A-Za-z0-9_\-\.\/:\[\]]/', '', (string) $args['hook'] ), 0, 190 );
		}
		return $clean;
	}

	/**
	 * Sanitizes schedule data.
	 *
	 * @param array $schedule Raw schedule.
	 * @return array
	 */
	public static function sanitize_schedule( array $schedule ) {
		$clean = array(
			'start' => '',
			'end'   => '',
		);
		foreach ( array( 'start', 'end' ) as $key ) {
			$value = isset( $schedule[ $key ] ) ? trim( (string) $schedule[ $key ] ) : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $value ) ) {
				$clean[ $key ] = str_replace( ' ', 'T', $value );
			}
		}
		return $clean;
	}

	/**
	 * Sanitizes output options.
	 *
	 * @param array $options Raw options.
	 * @return array
	 */
	public static function sanitize_options( array $options ) {
		$defaults = self::default_options();
		return array(
			'output'     => isset( $options['output'] ) && 'file' === $options['output'] ? 'file' : 'inline',
			'strategy'   => isset( $options['strategy'] ) && in_array( $options['strategy'], self::STRATEGIES, true ) ? $options['strategy'] : $defaults['strategy'],
			'consent'    => isset( $options['consent'] ) && in_array( $options['consent'], self::CONSENT_CATEGORIES, true ) ? $options['consent'] : '',
			'smart_tags' => ! empty( $options['smart_tags'] ),
			'shortcodes' => ! empty( $options['shortcodes'] ),
			'test_mode'  => ! empty( $options['test_mode'] ),
		);
	}

	/**
	 * Checks PHP syntax without running the code.
	 *
	 * @return array|null Null when valid, otherwise array( message, line ).
	 */
	public function lint() {
		if ( ! Registry::is_php_type( $this->type ) || '' === trim( $this->code ) ) {
			return null;
		}
		$source = $this->code;
		// Keep line numbers matching the editor whether or not the opening tag was typed.
		if ( 'php' === $this->type && ! preg_match( '/^\s*<\?php/i', $source ) ) {
			$source = '<?php ' . $source;
		}
		try {
			token_get_all( $source, TOKEN_PARSE );
		} catch ( \ParseError $error ) {
			return array(
				'message' => $error->getMessage(),
				'line'    => $error->getLine(),
			);
		}
		return null;
	}

	/**
	 * Saves the snippet and re-signs it.
	 *
	 * @param bool $sign Whether to sign the code, which approves it. Pass
	 *                   false to save other changes to a snippet whose code
	 *                   changed outside ScriptDock without approving that code.
	 * @return int|\WP_Error Post ID or error.
	 */
	public function save( $sign = true ) {
		$postarr = array(
			'post_type'    => Post_Type::NAME,
			'post_title'   => '' !== $this->title ? $this->title : __( 'Untitled snippet', 'scriptdock' ),
			'post_content' => $this->code,
			'post_excerpt' => $this->description,
			'post_status'  => $this->active ? 'publish' : 'draft',
		);
		if ( $this->id ) {
			$postarr['ID'] = $this->id;
		}

		$is_new  = ! $this->id;
		$restore = self::suspend_content_filters();
		$result  = Snippets::writing(
			static function () use ( $is_new, $postarr ) {
				return $is_new ? wp_insert_post( wp_slash( $postarr ), true ) : wp_update_post( wp_slash( $postarr ), true );
			}
		);
		// Core only stores revisions on updates; keep the first version too.
		if ( $is_new && ! is_wp_error( $result ) && '' !== $this->code ) {
			wp_save_post_revision( (int) $result );
		}
		$restore();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->id = (int) $result;

		update_post_meta( $this->id, self::META_TYPE, $this->type );
		update_post_meta( $this->id, self::META_LOCATION, $this->location );
		update_post_meta( $this->id, self::META_ARGS, wp_slash( $this->location_args ) );
		update_post_meta( $this->id, self::META_PRIORITY, (int) $this->priority );
		update_post_meta( $this->id, self::META_CONDITIONS, wp_slash( $this->conditions ) );
		update_post_meta( $this->id, self::META_SCHEDULE, wp_slash( $this->schedule ) );
		update_post_meta( $this->id, self::META_OPTIONS, wp_slash( $this->options ) );
		if ( '' !== $this->source ) {
			update_post_meta( $this->id, self::META_SOURCE, wp_slash( $this->source ) );
		}
		wp_set_object_terms( $this->id, $this->tags, Post_Type::TAXONOMY );

		if ( $sign ) {
			$this->signature = Signer::sign_snippet( $this );
			update_post_meta( $this->id, self::META_SIGNATURE, $this->signature );
		}
		self::touch_editor( $this->id );

		Compiler::mark_dirty();
		return $this->id;
	}

	/**
	 * Records who changed a snippet last, the way core's editor does.
	 *
	 * @param int $id Snippet ID.
	 */
	public static function touch_editor( $id ) {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_post_meta( (int) $id, '_edit_last', $user_id );
		}
	}

	/**
	 * Labels for the consent categories.
	 *
	 * @return array Category => label.
	 */
	public static function consent_labels() {
		return array(
			'statistics'           => __( 'Statistics', 'scriptdock' ),
			'statistics-anonymous' => __( 'Anonymous statistics', 'scriptdock' ),
			'marketing'            => __( 'Marketing', 'scriptdock' ),
			'preferences'          => __( 'Preferences', 'scriptdock' ),
			'functional'           => __( 'Functional', 'scriptdock' ),
		);
	}

	/**
	 * Clears the stored error.
	 */
	public function clear_error() {
		$this->last_error = array();
		delete_post_meta( $this->id, self::META_ERROR );
		Notices::remove( $this->id );
	}

	/**
	 * Whether the stored code still matches its signature.
	 *
	 * @return bool
	 */
	public function is_trusted() {
		return Signer::snippet_is_valid( $this );
	}

	/**
	 * Whether the snippet runs PHP.
	 *
	 * @return bool
	 */
	public function is_php() {
		return Registry::is_php_type( $this->type );
	}

	/**
	 * Portable representation for export.
	 *
	 * @return array
	 */
	public function to_export() {
		return array(
			'title'         => $this->title,
			'description'   => $this->description,
			'type'          => $this->type,
			'code'          => $this->code,
			'active'        => $this->active,
			'location'      => $this->location,
			'location_args' => $this->location_args,
			'priority'      => $this->priority,
			'conditions'    => $this->conditions,
			'schedule'      => $this->schedule,
			'options'       => $this->options,
			'tags'          => $this->tags,
		);
	}

	/**
	 * Temporarily removes filters that would rewrite code on save.
	 *
	 * Core and plugins filter post content on save (kses, rel="noopener"
	 * rewriting, tag balancing). Snippet code must be stored byte for byte.
	 *
	 * @return callable Restores the filters.
	 */
	public static function suspend_content_filters() {
		global $wp_filter;
		$saved = array();
		foreach ( array( 'pre_post_content', 'content_save_pre', 'content_filtered_save_pre' ) as $hook ) {
			if ( isset( $wp_filter[ $hook ] ) && $wp_filter[ $hook ] instanceof \WP_Hook ) {
				$saved[ $hook ] = $wp_filter[ $hook ]->callbacks;
				remove_all_filters( $hook );
			}
		}
		return static function () use ( $saved ) {
			foreach ( $saved as $hook => $callbacks ) {
				foreach ( $callbacks as $priority => $items ) {
					foreach ( $items as $item ) {
						add_filter( $hook, $item['function'], $priority, $item['accepted_args'] );
					}
				}
			}
		};
	}
}
