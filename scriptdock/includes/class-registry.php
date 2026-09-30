<?php
/**
 * Snippet types and insert locations.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Central list of snippet types and locations.
 *
 * Runtime data (hooks) is kept free of translations so it can be used before
 * `init`; labels are built only when the admin screens need them.
 */
final class Registry {

	/**
	 * Snippet type keys.
	 */
	const TYPES = array( 'php', 'html', 'css', 'js', 'universal' );

	/**
	 * Action-hook locations: location key => array( hook, context ).
	 *
	 * Context tells the runtime where the location can fire:
	 * frontend, admin or login.
	 */
	const ACTION_LOCATIONS = array(
		'site_header'                  => array( 'wp_head', 'frontend' ),
		'site_body_open'               => array( 'wp_body_open', 'frontend' ),
		'site_footer'                  => array( 'wp_footer', 'frontend' ),
		'admin_header'                 => array( 'admin_head', 'admin' ),
		'admin_footer'                 => array( 'admin_footer', 'admin' ),
		'login_header'                 => array( 'login_head', 'login' ),
		'login_footer'                 => array( 'login_footer', 'login' ),
		'wc_before_shop_loop'          => array( 'woocommerce_before_shop_loop', 'frontend' ),
		'wc_after_shop_loop'           => array( 'woocommerce_after_shop_loop', 'frontend' ),
		'wc_before_single_product'     => array( 'woocommerce_before_single_product', 'frontend' ),
		'wc_after_single_product'      => array( 'woocommerce_after_single_product', 'frontend' ),
		'wc_before_add_to_cart'        => array( 'woocommerce_before_add_to_cart_form', 'frontend' ),
		'wc_after_add_to_cart'         => array( 'woocommerce_after_add_to_cart_form', 'frontend' ),
		'wc_before_cart'               => array( 'woocommerce_before_cart', 'frontend' ),
		'wc_after_cart'                => array( 'woocommerce_after_cart', 'frontend' ),
		'wc_before_checkout'           => array( 'woocommerce_before_checkout_form', 'frontend' ),
		'wc_after_checkout'            => array( 'woocommerce_after_checkout_form', 'frontend' ),
		'wc_thankyou'                  => array( 'woocommerce_thankyou', 'frontend' ),
		'wc_account_dashboard'         => array( 'woocommerce_account_dashboard', 'frontend' ),
	);

	/**
	 * Locations where CSS and JavaScript can be loaded as files.
	 */
	const FILE_LOCATIONS = array( 'site_header', 'site_footer', 'admin_header', 'admin_footer', 'login_header', 'login_footer', 'block_editor' );

	/**
	 * Content filter locations.
	 */
	const CONTENT_LOCATIONS = array( 'before_content', 'after_content', 'before_paragraph', 'after_paragraph' );

	/**
	 * Locations that run PHP instead of printing output.
	 */
	const EXECUTE_LOCATIONS = array( 'php_everywhere', 'php_frontend', 'php_admin' );

	/**
	 * Allowed types per location.
	 *
	 * @return array
	 */
	public static function location_types() {
		$output = array( 'html', 'js', 'universal', 'php' );
		$map    = array(
			'php_everywhere'   => array( 'php' ),
			'php_frontend'     => array( 'php' ),
			'php_admin'        => array( 'php' ),
			'on_demand'        => array( 'php' ),
			'site_header'      => array( 'html', 'css', 'js', 'universal', 'php' ),
			'site_body_open'   => $output,
			'site_footer'      => array( 'html', 'css', 'js', 'universal', 'php' ),
			'before_content'   => array( 'html', 'universal', 'php' ),
			'after_content'    => array( 'html', 'universal', 'php' ),
			'before_paragraph' => array( 'html', 'universal', 'php' ),
			'after_paragraph'  => array( 'html', 'universal', 'php' ),
			'before_excerpt'   => array( 'html', 'universal', 'php' ),
			'after_excerpt'    => array( 'html', 'universal', 'php' ),
			'between_posts'    => array( 'html', 'universal', 'php' ),
			'admin_header'     => array( 'html', 'css', 'js', 'universal', 'php' ),
			'admin_footer'     => array( 'html', 'css', 'js', 'universal', 'php' ),
			'login_header'     => array( 'html', 'css', 'js', 'universal', 'php' ),
			'login_footer'     => $output,
			'block_editor'     => array( 'css', 'js' ),
			'custom_hook'      => array( 'php', 'html', 'css', 'js', 'universal' ),
			'shortcode'        => array( 'php', 'html', 'css', 'js', 'universal' ),
		);
		foreach ( array_keys( self::ACTION_LOCATIONS ) as $location ) {
			if ( 0 === strpos( $location, 'wc_' ) ) {
				$map[ $location ] = $output;
			}
		}
		return $map;
	}

	/**
	 * Whether a location key exists.
	 *
	 * @param string $location Location key.
	 * @return bool
	 */
	public static function location_exists( $location ) {
		return isset( self::location_types()[ $location ] );
	}

	/**
	 * Whether a type can be used at a location.
	 *
	 * @param string $type     Snippet type.
	 * @param string $location Location key.
	 * @return bool
	 */
	public static function type_allowed_at( $type, $location ) {
		$map = self::location_types();
		return isset( $map[ $location ] ) && in_array( $type, $map[ $location ], true );
	}

	/**
	 * Default location for a type.
	 *
	 * @param string $type Snippet type.
	 * @return string
	 */
	public static function default_location( $type ) {
		switch ( $type ) {
			case 'php':
				return 'php_everywhere';
			case 'js':
				return 'site_footer';
			default:
				return 'site_header';
		}
	}

	/**
	 * Sanitizes a type key.
	 *
	 * @param mixed $type Raw type.
	 * @return string
	 */
	public static function sanitize_type( $type ) {
		$type = is_string( $type ) ? strtolower( $type ) : '';
		return in_array( $type, self::TYPES, true ) ? $type : 'html';
	}

	/**
	 * Whether a type runs PHP.
	 *
	 * @param string $type Snippet type.
	 * @return bool
	 */
	public static function is_php_type( $type ) {
		return 'php' === $type || 'universal' === $type;
	}

	/**
	 * Type labels.
	 *
	 * @return array
	 */
	public static function type_labels() {
		return array(
			'php'       => __( 'PHP', 'scriptdock' ),
			'html'      => __( 'HTML', 'scriptdock' ),
			'css'       => __( 'CSS', 'scriptdock' ),
			'js'        => __( 'JavaScript', 'scriptdock' ),
			'universal' => __( 'Universal', 'scriptdock' ),
		);
	}

	/**
	 * Short type descriptions for the editor.
	 *
	 * @return array
	 */
	public static function type_descriptions() {
		return array(
			'php'       => __( 'PHP code that runs on the server, for example to add hooks and filters.', 'scriptdock' ),
			'html'      => __( 'Markup printed as-is, including <script> and <style> tags. Ideal for tracking codes.', 'scriptdock' ),
			'css'       => __( 'Styles, wrapped in a <style> tag or loaded as a cached file.', 'scriptdock' ),
			'js'        => __( 'JavaScript, wrapped in a <script> tag or loaded as a cached file.', 'scriptdock' ),
			'universal' => __( 'HTML with embedded <?php ?> tags, like a theme template.', 'scriptdock' ),
		);
	}

	/**
	 * Location groups for the editor.
	 *
	 * @return array
	 */
	public static function location_groups() {
		$groups = array(
			'php'     => __( 'Run PHP', 'scriptdock' ),
			'site'    => __( 'Site-wide', 'scriptdock' ),
			'content' => __( 'Page content', 'scriptdock' ),
			'archive' => __( 'Archives', 'scriptdock' ),
			'admin'   => __( 'Admin & login', 'scriptdock' ),
			'wc'      => __( 'WooCommerce', 'scriptdock' ),
			'other'   => __( 'Other', 'scriptdock' ),
		);
		if ( ! self::woocommerce_active() ) {
			unset( $groups['wc'] );
		}
		return $groups;
	}

	/**
	 * Location definitions with labels, for the admin.
	 *
	 * @return array
	 */
	public static function locations() {
		$types = self::location_types();
		$list  = array(
			'php_everywhere'           => array( 'php', __( 'Run everywhere', 'scriptdock' ), __( 'Runs on every request, front end and admin, when plugins have loaded.', 'scriptdock' ) ),
			'php_frontend'             => array( 'php', __( 'Front end only', 'scriptdock' ), __( 'Runs on the public site only.', 'scriptdock' ) ),
			'php_admin'                => array( 'php', __( 'Admin only', 'scriptdock' ), __( 'Runs in the WordPress admin only.', 'scriptdock' ) ),
			'on_demand'                => array( 'php', __( 'Run on demand', 'scriptdock' ), __( 'Never runs automatically. Use the “Run now” button for one-off tasks.', 'scriptdock' ) ),
			'site_header'              => array( 'site', __( 'Site header (<head>)', 'scriptdock' ), __( 'Printed in the <head> of every front-end page.', 'scriptdock' ) ),
			'site_body_open'           => array( 'site', __( 'After opening <body>', 'scriptdock' ), __( 'Printed right after <body>. Requires a theme that calls wp_body_open().', 'scriptdock' ) ),
			'site_footer'              => array( 'site', __( 'Site footer', 'scriptdock' ), __( 'Printed before </body> on every front-end page.', 'scriptdock' ) ),
			'before_content'           => array( 'content', __( 'Before post content', 'scriptdock' ), __( 'Inserted before the content of single posts and pages.', 'scriptdock' ) ),
			'after_content'            => array( 'content', __( 'After post content', 'scriptdock' ), __( 'Inserted after the content of single posts and pages.', 'scriptdock' ) ),
			'before_paragraph'         => array( 'content', __( 'Before paragraph #', 'scriptdock' ), __( 'Inserted before a given paragraph of single posts and pages.', 'scriptdock' ) ),
			'after_paragraph'          => array( 'content', __( 'After paragraph #', 'scriptdock' ), __( 'Inserted after a given paragraph of single posts and pages.', 'scriptdock' ) ),
			'before_excerpt'           => array( 'archive', __( 'Before excerpt', 'scriptdock' ), __( 'Inserted before post excerpts in archives.', 'scriptdock' ) ),
			'after_excerpt'            => array( 'archive', __( 'After excerpt', 'scriptdock' ), __( 'Inserted after post excerpts in archives.', 'scriptdock' ) ),
			'between_posts'            => array( 'archive', __( 'Between posts', 'scriptdock' ), __( 'Inserted between posts in blog and archive lists (classic themes).', 'scriptdock' ) ),
			'admin_header'             => array( 'admin', __( 'Admin header', 'scriptdock' ), __( 'Printed in the <head> of admin screens.', 'scriptdock' ) ),
			'admin_footer'             => array( 'admin', __( 'Admin footer', 'scriptdock' ), __( 'Printed at the bottom of admin screens.', 'scriptdock' ) ),
			'login_header'             => array( 'admin', __( 'Login page header', 'scriptdock' ), __( 'Printed in the <head> of the login page.', 'scriptdock' ) ),
			'login_footer'             => array( 'admin', __( 'Login page footer', 'scriptdock' ), __( 'Printed at the bottom of the login page.', 'scriptdock' ) ),
			'block_editor'             => array( 'admin', __( 'Block editor', 'scriptdock' ), __( 'Loads CSS or JavaScript inside the block editor.', 'scriptdock' ) ),
			'wc_before_shop_loop'      => array( 'wc', __( 'Before shop product list', 'scriptdock' ), '' ),
			'wc_after_shop_loop'       => array( 'wc', __( 'After shop product list', 'scriptdock' ), '' ),
			'wc_before_single_product' => array( 'wc', __( 'Before single product', 'scriptdock' ), '' ),
			'wc_after_single_product'  => array( 'wc', __( 'After single product', 'scriptdock' ), '' ),
			'wc_before_add_to_cart'    => array( 'wc', __( 'Before add-to-cart form', 'scriptdock' ), '' ),
			'wc_after_add_to_cart'     => array( 'wc', __( 'After add-to-cart form', 'scriptdock' ), '' ),
			'wc_before_cart'           => array( 'wc', __( 'Before cart', 'scriptdock' ), __( 'Classic cart only; the Cart block does not run this hook.', 'scriptdock' ) ),
			'wc_after_cart'            => array( 'wc', __( 'After cart', 'scriptdock' ), __( 'Classic cart only; the Cart block does not run this hook.', 'scriptdock' ) ),
			'wc_before_checkout'       => array( 'wc', __( 'Before checkout form', 'scriptdock' ), __( 'Classic checkout only; the Checkout block does not run this hook.', 'scriptdock' ) ),
			'wc_after_checkout'        => array( 'wc', __( 'After checkout form', 'scriptdock' ), __( 'Classic checkout only; the Checkout block does not run this hook.', 'scriptdock' ) ),
			'wc_thankyou'              => array( 'wc', __( 'Order received (thank-you) page', 'scriptdock' ), __( 'Ideal for conversion tracking. Order smart tags are available.', 'scriptdock' ) ),
			'wc_account_dashboard'     => array( 'wc', __( 'My Account dashboard', 'scriptdock' ), '' ),
			'custom_hook'              => array( 'other', __( 'Custom action hook', 'scriptdock' ), __( 'Runs on any action hook you name, using the snippet priority.', 'scriptdock' ) ),
			'shortcode'                => array( 'other', __( 'Shortcode or block only', 'scriptdock' ), __( 'Only output where you place the shortcode or the ScriptDock block.', 'scriptdock' ) ),
		);

		$wc        = self::woocommerce_active();
		$locations = array();
		foreach ( $list as $key => $item ) {
			if ( 'wc' === $item[0] && ! $wc ) {
				continue;
			}
			$locations[ $key ] = array(
				'group'       => $item[0],
				'label'       => $item[1],
				'description' => $item[2],
				'types'       => isset( $types[ $key ] ) ? $types[ $key ] : array(),
				'files'       => in_array( $key, self::FILE_LOCATIONS, true ),
			);
		}
		return $locations;
	}

	/**
	 * Label for a location.
	 *
	 * @param string $location Location key.
	 * @return string
	 */
	public static function location_label( $location ) {
		$locations = self::locations();
		if ( isset( $locations[ $location ] ) ) {
			return $locations[ $location ]['label'];
		}
		return 0 === strpos( $location, 'wc_' ) ? __( 'WooCommerce (inactive)', 'scriptdock' ) : $location;
	}

	/**
	 * A short placement name for the list: "Site header" rather than
	 * "Site header (<head>)", with the paragraph number filled in.
	 *
	 * @param string $location Location key.
	 * @param array  $args     Location arguments.
	 * @return string
	 */
	public static function short_label( $location, array $args = array() ) {
		$short = array(
			'site_header'    => __( 'Site header', 'scriptdock' ),
			'site_body_open' => __( 'After opening body', 'scriptdock' ),
			'shortcode'      => __( 'Shortcode or block', 'scriptdock' ),
		);
		if ( isset( $short[ $location ] ) ) {
			return $short[ $location ];
		}
		if ( 'custom_hook' === $location && ! empty( $args['hook'] ) ) {
			/* translators: %s: hook name, for example "woocommerce_thankyou". */
			return sprintf( __( 'Hook: %s', 'scriptdock' ), $args['hook'] );
		}
		$label = self::location_label( $location );
		if ( 'before_paragraph' === $location || 'after_paragraph' === $location ) {
			$label = str_replace( '#', (string) ( isset( $args['paragraph'] ) ? (int) $args['paragraph'] : 1 ), $label );
		}
		return $label;
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}
