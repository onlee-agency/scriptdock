<?php
/**
 * Built-in snippet library.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Ready-made snippets that ship with the plugin. Nothing is downloaded: the
 * templates live in library/templates.php and library/code/.
 *
 * Templates can ask for values (for example a GA4 measurement ID). Each value
 * must match a strict pattern before it is placed into the code, so a value
 * can never break out of the string it is inserted into.
 */
final class Library {

	/**
	 * Categories.
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'tracking'    => __( 'Analytics & pixels', 'scriptdock' ),
			'performance' => __( 'Performance', 'scriptdock' ),
			'security'    => __( 'Security', 'scriptdock' ),
			'admin'       => __( 'Admin', 'scriptdock' ),
			'content'     => __( 'Content & design', 'scriptdock' ),
			'login'       => __( 'Login page', 'scriptdock' ),
			'woocommerce' => __( 'WooCommerce', 'scriptdock' ),
		);
	}

	/**
	 * All templates.
	 *
	 * @return array
	 */
	public static function templates() {
		$templates = require SCRIPTDOCK_DIR . 'library/templates.php';

		/**
		 * Filters the snippet library.
		 *
		 * @param array $templates Templates keyed by ID.
		 */
		return (array) apply_filters( 'scriptdock_library_templates', $templates );
	}

	/**
	 * One template.
	 *
	 * @param string $id Template ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$templates = self::templates();
		return isset( $templates[ $id ] ) ? $templates[ $id ] : null;
	}

	/**
	 * The snippets a template creates (most create one).
	 *
	 * @param array $template Template.
	 * @return array
	 */
	public static function parts( array $template ) {
		if ( ! empty( $template['snippets'] ) ) {
			return $template['snippets'];
		}
		return array( $template );
	}

	/**
	 * Code for a part.
	 *
	 * @param array $part Template part.
	 * @return string
	 */
	public static function code( array $part ) {
		if ( isset( $part['code'] ) ) {
			return (string) $part['code'];
		}
		$path = SCRIPTDOCK_DIR . 'library/code/' . basename( (string) $part['file'] );
		return is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file bundled with the plugin.
	}

	/**
	 * Creates snippets from a template.
	 *
	 * @param string $id       Template ID.
	 * @param array  $values   Field values.
	 * @param bool   $activate Whether to activate the snippets.
	 * @return int[]|\WP_Error Created snippet IDs.
	 */
	public static function create( $id, array $values, $activate = false ) {
		$template = self::get( $id );
		if ( ! $template ) {
			return new \WP_Error( 'scriptdock_template', __( 'Template not found.', 'scriptdock' ) );
		}

		// Check every part before saving any, so a refusal leaves nothing half added.
		foreach ( self::parts( $template ) as $part ) {
			$uses_php = Registry::is_php_type( $part['type'] ) || ( isset( $part['conditions'] ) && Conditions::uses_php( $part['conditions'] ) );
			if ( $uses_php && ! Capabilities::can_manage_php() ) {
				return new \WP_Error( 'scriptdock_forbidden', Capabilities::php_unavailable_reason() );
			}
		}

		$clean = array();
		foreach ( isset( $template['fields'] ) ? $template['fields'] : array() as $key => $field ) {
			$value = isset( $values[ $key ] ) ? trim( (string) $values[ $key ] ) : '';
			if ( '' === $value && isset( $field['default'] ) ) {
				$value = (string) $field['default'];
			}
			// Values are pasted into code, so a field without its own pattern
			// takes only letters, digits, spaces and - _ .
			$pattern = ! empty( $field['pattern'] ) ? $field['pattern'] : '^[A-Za-z0-9 _.\-]*$';
			if ( ! preg_match( '/' . str_replace( '/', '\/', $pattern ) . '/', $value ) ) {
				/* translators: %s: field label */
				return new \WP_Error( 'scriptdock_field', sprintf( __( '%s is not in the expected format.', 'scriptdock' ), $field['label'] ) );
			}
			$clean[ $key ] = $value;
		}

		$ids = array();
		foreach ( self::parts( $template ) as $part ) {
			$is_php = Registry::is_php_type( $part['type'] );
			$code   = self::code( $part );
			foreach ( $clean as $key => $value ) {
				$code = str_replace( '%%' . $key . '%%', $value, $code );
			}

			$snippet = new Snippet();
			$snippet->fill(
				array(
					'title'         => isset( $part['title'] ) ? $part['title'] : $template['title'],
					'description'   => isset( $part['description'] ) ? $part['description'] : $template['description'],
					'type'          => $part['type'],
					'code'          => $code,
					'location'      => $part['location'],
					'location_args' => isset( $part['location_args'] ) ? $part['location_args'] : array(),
					'priority'      => isset( $part['priority'] ) ? $part['priority'] : 10,
					'conditions'    => isset( $part['conditions'] ) ? $part['conditions'] : Conditions::empty_set(),
					'options'       => isset( $part['options'] ) ? array_merge( Snippet::default_options(), $part['options'] ) : Snippet::default_options(),
					'tags'          => isset( $template['tags'] ) ? $template['tags'] : array(),
					'source'        => 'library:' . $id,
					'active'        => false,
				)
			);

			$saved = $snippet->save();
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			if ( $activate && ! ( $is_php && $snippet->lint() ) ) {
				$snippet->active = true;
				$snippet->save();
			}
			$ids[] = $snippet->id;
		}

		Compiler::mark_dirty();
		return $ids;
	}
}
