<?php
/**
 * Conditional logic.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates rule groups: a snippet matches when every rule in at least one
 * group matches (groups are OR, rules inside a group are AND). The action
 * then shows or hides the snippet for matching requests.
 *
 * Stored format:
 * array(
 *   'enabled' => true,
 *   'action'  => 'show' | 'hide',
 *   'groups'  => array( array( array( 'rule' => 'page_type', 'operator' => 'is', 'value' => array( 'front_page' ) ) ) ),
 * )
 */
final class Conditions {

	/**
	 * Rules that need the main query (only reliable on the front end after `wp`).
	 */
	const QUERY_RULES = array( 'page_type', 'post_type', 'post', 'post_parent', 'taxonomy_term', 'page_template', 'post_author', 'post_meta', 'wc_cart_total', 'wc_cart_contains', 'wc_cart_category', 'wc_has_ordered' );

	/**
	 * Rule keys and the operators they accept.
	 */
	const RULE_OPERATORS = array(
		'page_type'        => array( 'is', 'is_not' ),
		'post_type'        => array( 'is', 'is_not' ),
		'post'             => array( 'is', 'is_not' ),
		'post_parent'      => array( 'is', 'is_not' ),
		'taxonomy_term'    => array( 'is', 'is_not' ),
		'page_template'    => array( 'is', 'is_not' ),
		'post_author'      => array( 'is', 'is_not' ),
		'post_meta'        => array( 'exists', 'not_exists', 'equals', 'not_equals', 'contains' ),
		'url'              => array( 'is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'wildcard', 'regex' ),
		'query_param'      => array( 'exists', 'not_exists', 'equals', 'not_equals', 'contains' ),
		'referrer'         => array( 'contains', 'not_contains', 'is', 'starts_with', 'regex', 'exists', 'not_exists' ),
		'cookie'           => array( 'exists', 'not_exists', 'equals', 'not_equals', 'contains' ),
		'logged_in'        => array( 'is' ),
		'user_role'        => array( 'is', 'is_not' ),
		'user_id'          => array( 'is', 'is_not' ),
		'user_meta'        => array( 'exists', 'not_exists', 'equals', 'not_equals', 'contains' ),
		'device'           => array( 'is' ),
		'browser'          => array( 'is', 'is_not' ),
		'os'               => array( 'is', 'is_not' ),
		'day_of_week'      => array( 'is', 'is_not' ),
		'time_of_day'      => array( 'between', 'not_between' ),
		'date'             => array( 'before', 'after' ),
		'language'         => array( 'is', 'is_not' ),
		'active_theme'     => array( 'is', 'is_not' ),
		'active_plugin'    => array( 'is', 'is_not' ),
		'php_function'     => array( 'returns_true', 'returns_false' ),
		'wc_cart_total'    => array( 'between', 'gte', 'lte', 'gt', 'lt' ),
		'wc_cart_contains' => array( 'is', 'is_not' ),
		'wc_cart_category' => array( 'is', 'is_not' ),
		'wc_has_ordered'   => array( 'is_true', 'is_false' ),
	);

	/**
	 * Labels for rule values that come from a fixed list, keyed by rule.
	 *
	 * Shared by the rule editor and the plain-language summaries, so both
	 * name a value the same way.
	 *
	 * @return array Rule key => array( value => label ).
	 */
	public static function value_labels() {
		$page_types = array(
			'front_page'        => __( 'Front page', 'scriptdock' ),
			'home'              => __( 'Blog / posts page', 'scriptdock' ),
			'singular'          => __( 'Any single post or page', 'scriptdock' ),
			'single'            => __( 'Single post (any post type)', 'scriptdock' ),
			'page'              => __( 'Page', 'scriptdock' ),
			'attachment'        => __( 'Attachment', 'scriptdock' ),
			'archive'           => __( 'Any archive', 'scriptdock' ),
			'category'          => __( 'Category archive', 'scriptdock' ),
			'tag'               => __( 'Tag archive', 'scriptdock' ),
			'taxonomy'          => __( 'Custom taxonomy archive', 'scriptdock' ),
			'author'            => __( 'Author archive', 'scriptdock' ),
			'date'              => __( 'Date archive', 'scriptdock' ),
			'post_type_archive' => __( 'Post type archive', 'scriptdock' ),
			'search'            => __( 'Search results', 'scriptdock' ),
			'404'               => __( '404 page', 'scriptdock' ),
			'privacy'           => __( 'Privacy policy page', 'scriptdock' ),
		);
		if ( Registry::woocommerce_active() ) {
			$page_types += array(
				'wc_shop'             => __( 'Shop page', 'scriptdock' ),
				'wc_product'          => __( 'Single product', 'scriptdock' ),
				'wc_product_category' => __( 'Product category', 'scriptdock' ),
				'wc_product_tag'      => __( 'Product tag', 'scriptdock' ),
				'wc_cart'             => __( 'Cart', 'scriptdock' ),
				'wc_checkout'         => __( 'Checkout', 'scriptdock' ),
				'wc_order_received'   => __( 'Order received (thank you)', 'scriptdock' ),
				'wc_account'          => __( 'My account', 'scriptdock' ),
			);
		}

		return array(
			'page_type'   => $page_types,
			'logged_in'   => array(
				'logged_in'  => __( 'Logged in', 'scriptdock' ),
				'logged_out' => __( 'Logged out', 'scriptdock' ),
			),
			'device'      => array(
				'desktop' => __( 'Desktop', 'scriptdock' ),
				'mobile'  => __( 'Mobile or tablet', 'scriptdock' ),
			),
			'browser'     => array(
				'chrome'  => 'Chrome',
				'safari'  => 'Safari',
				'firefox' => 'Firefox',
				'edge'    => 'Edge',
				'opera'   => 'Opera',
				'samsung' => 'Samsung Internet',
				'ie'      => 'Internet Explorer',
				'other'   => __( 'Other', 'scriptdock' ),
			),
			'os'          => array(
				'windows'  => 'Windows',
				'macos'    => 'macOS',
				'ios'      => 'iOS / iPadOS',
				'android'  => 'Android',
				'linux'    => 'Linux',
				'chromeos' => 'ChromeOS',
				'other'    => __( 'Other', 'scriptdock' ),
			),
			'day_of_week' => array(
				'1' => __( 'Monday', 'scriptdock' ),
				'2' => __( 'Tuesday', 'scriptdock' ),
				'3' => __( 'Wednesday', 'scriptdock' ),
				'4' => __( 'Thursday', 'scriptdock' ),
				'5' => __( 'Friday', 'scriptdock' ),
				'6' => __( 'Saturday', 'scriptdock' ),
				'7' => __( 'Sunday', 'scriptdock' ),
			),
		);
	}

	/**
	 * How each operator reads in a rule row.
	 *
	 * @return array Operator key => label.
	 */
	public static function operator_labels() {
		return array(
			'is'            => __( 'is any of', 'scriptdock' ),
			'is_not'        => __( 'is none of', 'scriptdock' ),
			'contains'      => __( 'contains', 'scriptdock' ),
			'not_contains'  => __( 'does not contain', 'scriptdock' ),
			'starts_with'   => __( 'starts with', 'scriptdock' ),
			'ends_with'     => __( 'ends with', 'scriptdock' ),
			'wildcard'      => __( 'matches wildcard', 'scriptdock' ),
			'regex'         => __( 'matches regex', 'scriptdock' ),
			'exists'        => __( 'is set', 'scriptdock' ),
			'not_exists'    => __( 'is not set', 'scriptdock' ),
			'equals'        => __( 'equals', 'scriptdock' ),
			'not_equals'    => __( 'does not equal', 'scriptdock' ),
			'between'       => __( 'is between', 'scriptdock' ),
			'not_between'   => __( 'is not between', 'scriptdock' ),
			'gt'            => __( 'is more than', 'scriptdock' ),
			'gte'           => __( 'is at least', 'scriptdock' ),
			'lt'            => __( 'is less than', 'scriptdock' ),
			'lte'           => __( 'is at most', 'scriptdock' ),
			'before'        => __( 'is before', 'scriptdock' ),
			'after'         => __( 'is on or after', 'scriptdock' ),
			'returns_true'  => __( 'returns true', 'scriptdock' ),
			'returns_false' => __( 'returns false', 'scriptdock' ),
			'is_true'       => __( 'is true', 'scriptdock' ),
			'is_false'      => __( 'is false', 'scriptdock' ),
		);
	}

	/**
	 * Every condition, grouped the way the rule dropdown lists them.
	 *
	 * Three entries pick terms from one taxonomy each (Category, Tag, Product
	 * category) and one picks from any: they are the same `taxonomy_term`
	 * rule, told apart by the taxonomy their terms belong to.
	 *
	 * A condition that needs a missing plugin or permission stays listed with
	 * `locked` set to the reason, so the catalogue never looks smaller than it
	 * is. `source` names the list its values come from; `cache` marks the ones
	 * that vary per visitor, which full-page caching can flatten.
	 *
	 * @return array Groups: array( key, label, rules[] ).
	 */
	public static function catalogue() {
		$woo    = Registry::woocommerce_active();
		$no_woo = __( 'WooCommerce is not active on this site.', 'scriptdock' );
		$langs  = Targeting_Options::languages();
		$rule   = static function ( $key, $label, $control, array $extra = array() ) {
			return array_merge(
				array(
					'id'        => isset( $extra['taxonomy'] ) ? $key . ':' . $extra['taxonomy'] : $key,
					'rule'      => $key,
					'label'     => $label,
					'control'   => $control,
					'operators' => isset( self::RULE_OPERATORS[ $key ] ) ? self::RULE_OPERATORS[ $key ] : array( 'is', 'is_not' ),
					'source'    => '',
					'locked'    => '',
					'cache'     => false,
					'hint'      => '',
				),
				$extra
			);
		};

		return array(
			array(
				'key'   => 'page',
				'label' => __( 'Page', 'scriptdock' ),
				'rules' => array(
					$rule( 'post_type', __( 'Post type', 'scriptdock' ), 'chips', array( 'source' => 'post_types' ) ),
					$rule( 'post', __( 'Specific post or page', 'scriptdock' ), 'search', array( 'source' => 'content' ) ),
					$rule( 'post_parent', __( 'Post parent', 'scriptdock' ), 'search', array( 'source' => 'content' ) ),
					$rule( 'page_template', __( 'Page template', 'scriptdock' ), 'chips', array( 'source' => 'page_templates' ) ),
					$rule( 'taxonomy_term', __( 'Category', 'scriptdock' ), 'search', array( 'source' => 'terms', 'taxonomy' => 'category' ) ),
					$rule( 'taxonomy_term', __( 'Tag', 'scriptdock' ), 'search', array( 'source' => 'terms', 'taxonomy' => 'post_tag' ) ),
					$rule( 'taxonomy_term', __( 'Taxonomy term', 'scriptdock' ), 'search', array( 'source' => 'terms' ) ),
					$rule( 'post_author', __( 'Post author', 'scriptdock' ), 'search', array( 'source' => 'users' ) ),
					$rule( 'page_type', __( 'Special page', 'scriptdock' ), 'chips', array( 'source' => 'page_type' ) ),
					$rule( 'post_meta', __( 'Custom field', 'scriptdock' ), 'pair' ),
				),
			),
			array(
				'key'   => 'request',
				'label' => __( 'Request', 'scriptdock' ),
				'rules' => array(
					$rule( 'url', __( 'URL path', 'scriptdock' ), 'patterns' ),
					$rule( 'referrer', __( 'Referrer', 'scriptdock' ), 'patterns', array( 'cache' => true ) ),
					$rule( 'query_param', __( 'URL parameter', 'scriptdock' ), 'pair', array( 'cache' => true ) ),
					$rule( 'cookie', __( 'Cookie', 'scriptdock' ), 'pair', array( 'cache' => true ) ),
				),
			),
			array(
				'key'   => 'user',
				'label' => __( 'User', 'scriptdock' ),
				'rules' => array(
					$rule( 'logged_in', __( 'Login status', 'scriptdock' ), 'chips', array( 'source' => 'logged_in', 'cache' => true ) ),
					$rule( 'user_role', __( 'User role', 'scriptdock' ), 'chips', array( 'source' => 'roles', 'cache' => true ) ),
					$rule( 'user_id', __( 'Specific user', 'scriptdock' ), 'search', array( 'source' => 'users', 'cache' => true ) ),
					$rule( 'user_meta', __( 'User field', 'scriptdock' ), 'pair', array( 'cache' => true ) ),
				),
			),
			array(
				'key'   => 'device',
				'label' => __( 'Device', 'scriptdock' ),
				'rules' => array(
					$rule( 'device', __( 'Device type', 'scriptdock' ), 'chips', array( 'source' => 'device', 'cache' => true ) ),
					$rule( 'browser', __( 'Browser', 'scriptdock' ), 'chips', array( 'source' => 'browser', 'cache' => true ) ),
					$rule( 'os', __( 'Operating system', 'scriptdock' ), 'chips', array( 'source' => 'os', 'cache' => true ) ),
				),
			),
			array(
				'key'   => 'datetime',
				'label' => __( 'Date & time', 'scriptdock' ),
				'rules' => array(
					$rule( 'date', __( 'Date', 'scriptdock' ), 'date' ),
					$rule( 'day_of_week', __( 'Day of week', 'scriptdock' ), 'chips', array( 'source' => 'day_of_week' ) ),
					$rule( 'time_of_day', __( 'Time of day', 'scriptdock' ), 'time' ),
				),
			),
			array(
				'key'   => 'site',
				'label' => __( 'Site', 'scriptdock' ),
				'rules' => array(
					$rule(
						'language',
						__( 'Site language', 'scriptdock' ),
						'chips',
						array(
							'source' => 'languages',
							'locked' => count( $langs ) > 1 ? '' : __( 'This site has one language.', 'scriptdock' ),
						)
					),
					$rule( 'active_theme', __( 'Active theme', 'scriptdock' ), 'chips', array( 'source' => 'themes' ) ),
					$rule( 'active_plugin', __( 'Active plugin', 'scriptdock' ), 'chips', array( 'source' => 'plugins' ) ),
				),
			),
			array(
				'key'   => 'woocommerce',
				'label' => 'WooCommerce',
				'rules' => array(
					$rule( 'wc_cart_total', __( 'Cart total', 'scriptdock' ), 'number', array( 'locked' => $woo ? '' : $no_woo, 'cache' => true ) ),
					$rule( 'wc_cart_contains', __( 'Cart contains product', 'scriptdock' ), 'search', array( 'source' => 'products', 'locked' => $woo ? '' : $no_woo, 'cache' => true ) ),
					$rule(
						'taxonomy_term',
						__( 'Product category', 'scriptdock' ),
						'search',
						array(
							'source'   => 'terms',
							'taxonomy' => 'product_cat',
							'locked'   => $woo ? '' : $no_woo,
							'hint'     => __( 'Matches a visitor looking at a product in that category.', 'scriptdock' ),
						)
					),
					$rule(
						'wc_cart_category',
						__( 'Cart contains a product from a category', 'scriptdock' ),
						'search',
						array( 'source' => 'product_terms', 'locked' => $woo ? '' : $no_woo, 'cache' => true )
					),
					$rule( 'wc_has_ordered', __( 'Customer has ordered before', 'scriptdock' ), 'boolean', array( 'locked' => $woo ? '' : $no_woo, 'cache' => true ) ),
				),
			),
			array(
				'key'   => 'advanced',
				'label' => __( 'Advanced', 'scriptdock' ),
				'rules' => array(
					$rule(
						'php_function',
						__( 'PHP function returns true', 'scriptdock' ),
						'text',
						array( 'locked' => Capabilities::can_manage_php() ? '' : Capabilities::php_unavailable_reason() )
					),
				),
			),
		);
	}

	/**
	 * Empty rule set.
	 *
	 * @return array
	 */
	public static function empty_set() {
		return array(
			'enabled' => false,
			'action'  => 'show',
			'groups'  => array(),
		);
	}

	/**
	 * Whether a rule set is switched on and has rules.
	 *
	 * @param array $conditions Rule set.
	 * @return bool
	 */
	public static function is_active( $conditions ) {
		return is_array( $conditions ) && ! empty( $conditions['enabled'] ) && ! empty( $conditions['groups'] );
	}

	/**
	 * Whether any rule needs the main query.
	 *
	 * @param array $conditions Rule set.
	 * @return bool
	 */
	public static function requires_query( $conditions ) {
		if ( ! self::is_active( $conditions ) ) {
			return false;
		}
		foreach ( $conditions['groups'] as $group ) {
			foreach ( $group as $rule ) {
				if ( in_array( $rule['rule'], self::QUERY_RULES, true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether the current request matches a rule set.
	 *
	 * @param array $conditions Rule set (empty array means "always").
	 * @return bool
	 */
	public static function match( $conditions ) {
		if ( ! self::is_active( $conditions ) ) {
			return true;
		}

		$any = false;
		foreach ( $conditions['groups'] as $group ) {
			if ( ! $group ) {
				continue;
			}
			$all = true;
			foreach ( $group as $rule ) {
				if ( ! self::evaluate( $rule ) ) {
					$all = false;
					break;
				}
			}
			if ( $all ) {
				$any = true;
				break;
			}
		}

		return 'hide' === $conditions['action'] ? ! $any : $any;
	}

	/**
	 * Evaluates one rule.
	 *
	 * @param array $rule Rule.
	 * @return bool
	 */
	public static function evaluate( array $rule ) {
		$key      = isset( $rule['rule'] ) ? $rule['rule'] : '';
		$operator = isset( $rule['operator'] ) ? $rule['operator'] : 'is';
		$value    = isset( $rule['value'] ) ? $rule['value'] : '';

		switch ( $key ) {
			case 'page_type':
				return self::list_result( $operator, self::any( (array) $value, array( __CLASS__, 'is_page_type' ) ) );

			case 'post_type':
				$types = array_map( 'strval', (array) $value );
				$hit   = ( is_singular() && in_array( get_post_type( get_queried_object_id() ), $types, true ) ) || ( $types && is_post_type_archive( $types ) );
				return self::list_result( $operator, $hit );

			case 'post':
				$post = self::queried_post();
				return self::list_result( $operator, $post && in_array( $post->ID, array_map( 'intval', (array) $value ), true ) );

			case 'post_parent':
				$post = self::queried_post();
				$hit  = $post && (bool) array_intersect( array_map( 'intval', (array) $value ), array_map( 'intval', get_post_ancestors( $post ) ) );
				return self::list_result( $operator, $hit );

			case 'taxonomy_term':
				return self::list_result( $operator, self::matches_terms( array_map( 'intval', (array) $value ) ) );

			case 'page_template':
				$post     = self::queried_post();
				$template = $post ? get_page_template_slug( $post ) : false;
				$template = '' === $template ? 'default' : $template;
				return self::list_result( $operator, false !== $template && in_array( $template, (array) $value, true ) );

			case 'post_author':
				$authors = array_map( 'intval', (array) $value );
				$post    = self::queried_post();
				$hit     = ( $post && in_array( (int) $post->post_author, $authors, true ) ) || ( is_author() && in_array( (int) get_queried_object_id(), $authors, true ) );
				return self::list_result( $operator, $hit );

			case 'post_meta':
				$post = self::queried_post();
				$pair = self::pair( $value );
				if ( ! $post || '' === $pair['key'] ) {
					return in_array( $operator, array( 'not_exists', 'not_equals' ), true );
				}
				$exists = metadata_exists( 'post', $post->ID, $pair['key'] );
				return self::pair_result( $operator, $exists, $exists ? (string) get_post_meta( $post->ID, $pair['key'], true ) : '', $pair['value'] );

			case 'url':
				return self::text_result( $operator, self::request_path(), (array) $value );

			case 'query_param':
				$pair   = self::pair( $value );
				$exists = '' !== $pair['key'] && isset( $_GET[ $pair['key'] ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$actual = $exists && is_scalar( $_GET[ $pair['key'] ] ) ? sanitize_text_field( wp_unslash( $_GET[ $pair['key'] ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return self::pair_result( $operator, $exists, $actual, $pair['value'] );

			case 'referrer':
				$referrer = wp_get_raw_referer();
				$referrer = $referrer ? (string) $referrer : '';
				if ( 'exists' === $operator || 'not_exists' === $operator ) {
					return ( '' !== $referrer ) === ( 'exists' === $operator );
				}
				return self::text_result( $operator, $referrer, (array) $value );

			case 'cookie':
				$pair   = self::pair( $value );
				$exists = '' !== $pair['key'] && isset( $_COOKIE[ $pair['key'] ] );
				$actual = $exists && is_scalar( $_COOKIE[ $pair['key'] ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $pair['key'] ] ) ) : '';
				return self::pair_result( $operator, $exists, $actual, $pair['value'] );

			case 'logged_in':
				$want = is_array( $value ) ? reset( $value ) : $value;
				return ( 'logged_out' === $want ) ? ! is_user_logged_in() : is_user_logged_in();

			case 'user_role':
				$user = wp_get_current_user();
				$hit  = $user->exists() && (bool) array_intersect( (array) $value, (array) $user->roles );
				return self::list_result( $operator, $hit );

			case 'user_id':
				$current = get_current_user_id();
				return self::list_result( $operator, $current && in_array( $current, array_map( 'intval', (array) $value ), true ) );

			case 'user_meta':
				$user_id = get_current_user_id();
				$pair    = self::pair( $value );
				if ( ! $user_id || '' === $pair['key'] ) {
					return in_array( $operator, array( 'not_exists', 'not_equals' ), true );
				}
				$exists = metadata_exists( 'user', $user_id, $pair['key'] );
				return self::pair_result( $operator, $exists, $exists ? (string) get_user_meta( $user_id, $pair['key'], true ) : '', $pair['value'] );

			case 'device':
				$want = is_array( $value ) ? reset( $value ) : $value;
				return 'mobile' === $want ? wp_is_mobile() : ! wp_is_mobile();

			case 'browser':
				return self::list_result( $operator, in_array( self::browser(), (array) $value, true ) );

			case 'os':
				return self::list_result( $operator, in_array( self::os(), (array) $value, true ) );

			case 'day_of_week':
				return self::list_result( $operator, in_array( wp_date( 'N' ), array_map( 'strval', (array) $value ), true ) );

			case 'time_of_day':
				$range = is_array( $value ) ? $value : array();
				$from  = isset( $range['from'] ) ? (string) $range['from'] : '';
				$to    = isset( $range['to'] ) ? (string) $range['to'] : '';
				if ( ! preg_match( '/^\d{2}:\d{2}$/', $from ) || ! preg_match( '/^\d{2}:\d{2}$/', $to ) ) {
					return false;
				}
				$now = wp_date( 'H:i' );
				$in  = $from <= $to ? ( $now >= $from && $now < $to ) : ( $now >= $from || $now < $to );
				return 'not_between' === $operator ? ! $in : $in;

			case 'date':
				$timestamp = Compiler::to_timestamp( is_array( $value ) ? (string) reset( $value ) : (string) $value );
				if ( ! $timestamp ) {
					return false;
				}
				return 'before' === $operator ? time() < $timestamp : time() >= $timestamp;

			case 'language':
				return self::list_result( $operator, in_array( self::current_locale(), (array) $value, true ) );

			case 'active_theme':
				$themes = array_map( 'strval', (array) $value );
				$hit    = in_array( (string) get_stylesheet(), $themes, true ) || in_array( (string) get_template(), $themes, true );
				return self::list_result( $operator, $hit );

			case 'active_plugin':
				return self::list_result( $operator, self::any( (array) $value, array( __CLASS__, 'plugin_active' ) ) );

			case 'php_function':
				$function = is_array( $value ) ? (string) reset( $value ) : (string) $value;
				if ( ! preg_match( '/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $function ) || ! function_exists( $function ) ) {
					return false;
				}
				$result = (bool) call_user_func( $function );
				return 'returns_false' === $operator ? ! $result : $result;

			case 'wc_cart_total':
				if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
					return false;
				}
				$total = (float) WC()->cart->get_total( 'edit' );
				if ( 'between' === $operator ) {
					$range = is_array( $value ) ? $value : array();
					$from  = isset( $range['from'] ) ? (float) $range['from'] : 0;
					$to    = isset( $range['to'] ) ? (float) $range['to'] : 0;
					return $total >= $from && ( $to <= $from || $total <= $to );
				}
				$target = (float) ( is_array( $value ) ? reset( $value ) : $value );
				switch ( $operator ) {
					case 'gt':
						return $total > $target;
					case 'gte':
						return $total >= $target;
					case 'lt':
						return $total < $target;
					default:
						return $total <= $target;
				}

			case 'wc_cart_contains':
				return self::list_result( $operator, self::cart_contains( array_map( 'intval', (array) $value ) ) );

			case 'wc_cart_category':
				return self::list_result( $operator, self::cart_in_categories( array_map( 'intval', (array) $value ) ) );

			case 'wc_has_ordered':
				return ( 'is_false' === $operator ) ? ! self::has_ordered() : self::has_ordered();
		}

		/**
		 * Evaluates a custom rule added through the scriptdock_condition_rules filter.
		 *
		 * @param bool|null $result   Rule result; null when the rule is unknown.
		 * @param string    $key      Rule key.
		 * @param string    $operator Operator.
		 * @param mixed     $value    Value.
		 */
		$result = apply_filters( 'scriptdock_evaluate_condition', null, $key, $operator, $value );
		return null === $result ? false : (bool) $result;
	}

	/**
	 * Whether a plugin is switched on, by its plugin file (my-plugin/my-plugin.php).
	 *
	 * Read from the options directly: is_plugin_active() lives in wp-admin and
	 * snippets run on the front end too.
	 *
	 * @param string $plugin Plugin file.
	 * @return bool
	 */
	private static function plugin_active( $plugin ) {
		$plugin = trim( (string) $plugin );
		if ( '' === $plugin ) {
			return false;
		}
		if ( in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}
		return is_multisite() && array_key_exists( $plugin, (array) get_site_option( 'active_sitewide_plugins', array() ) );
	}

	/**
	 * Whether the cart holds a product from any of these product categories.
	 *
	 * @param array $term_ids product_cat term IDs.
	 * @return bool
	 */
	private static function cart_in_categories( array $term_ids ) {
		if ( ! $term_ids || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			if ( $product_id && has_term( $term_ids, 'product_cat', $product_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the visitor has ordered before. Guests count as no.
	 *
	 * @return bool
	 */
	private static function has_ordered() {
		$user_id = get_current_user_id();
		if ( ! $user_id || ! function_exists( 'wc_get_customer_order_count' ) ) {
			return false;
		}
		return wc_get_customer_order_count( $user_id ) > 0;
	}

	/**
	 * Applies is / is_not to a boolean hit.
	 *
	 * @param string $operator Operator.
	 * @param bool   $hit      Whether the current request matched any value.
	 * @return bool
	 */
	private static function list_result( $operator, $hit ) {
		return 'is_not' === $operator ? ! $hit : (bool) $hit;
	}

	/**
	 * Whether a callback returns true for any value.
	 *
	 * @param array    $values   Values.
	 * @param callable $callback Callback.
	 * @return bool
	 */
	private static function any( array $values, $callback ) {
		foreach ( $values as $value ) {
			if ( call_user_func( $callback, (string) $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a URL path matches URL patterns, for the wizard's URL tester.
	 *
	 * @param string $operator Operator.
	 * @param string $path     Path, with any query string.
	 * @param array  $patterns Patterns.
	 * @return bool
	 */
	public static function matches_url( $operator, $path, array $patterns ) {
		$allowed = self::RULE_OPERATORS['url'];
		return self::text_result( in_array( $operator, $allowed, true ) ? $operator : 'contains', (string) $path, $patterns );
	}

	/**
	 * Compares text against patterns (any pattern may match).
	 *
	 * @param string $operator Operator.
	 * @param string $subject  Text.
	 * @param array  $patterns Patterns.
	 * @return bool
	 */
	private static function text_result( $operator, $subject, array $patterns ) {
		$negate = in_array( $operator, array( 'is_not', 'not_contains' ), true );
		$base   = $negate ? str_replace( array( 'is_not', 'not_contains' ), array( 'is', 'contains' ), $operator ) : $operator;
		$hit    = false;

		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}
			if ( self::text_matches( $base, $subject, $pattern ) ) {
				$hit = true;
				break;
			}
		}

		return $negate ? ! $hit : $hit;
	}

	/**
	 * Single text comparison. URL paths ignore case and trailing slashes.
	 *
	 * @param string $operator Operator.
	 * @param string $subject  Text.
	 * @param string $pattern  Pattern.
	 * @return bool
	 */
	private static function text_matches( $operator, $subject, $pattern ) {
		$subject_lc = strtolower( $subject );
		$pattern_lc = strtolower( self::normalize_url_pattern( $pattern ) );

		switch ( $operator ) {
			case 'is':
				return untrailingslashit( $subject_lc ) === untrailingslashit( $pattern_lc ) || $subject_lc === $pattern_lc;
			case 'contains':
				return false !== strpos( $subject_lc, $pattern_lc );
			case 'starts_with':
				return 0 === strpos( $subject_lc, $pattern_lc );
			case 'ends_with':
				return '' !== $pattern_lc && substr( $subject_lc, -strlen( $pattern_lc ) ) === $pattern_lc;
			case 'wildcard':
				$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern_lc, '#' ) ) . '$#';
				return 1 === preg_match( $regex, $subject_lc ) || 1 === preg_match( $regex, untrailingslashit( $subject_lc ) );
			case 'regex':
				$regex = self::regex( $pattern );
				return null !== $regex && 1 === preg_match( $regex, $subject );
		}
		return false;
	}

	/**
	 * Turns a full URL pattern into a path so users can paste links.
	 *
	 * @param string $pattern Pattern.
	 * @return string
	 */
	private static function normalize_url_pattern( $pattern ) {
		if ( preg_match( '#^https?://#i', $pattern ) ) {
			$parts   = wp_parse_url( $pattern );
			$pattern = ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		}
		return $pattern;
	}

	/**
	 * Builds a safe regex from a user pattern.
	 *
	 * @param string $pattern Pattern without delimiters.
	 * @return string|null Null when invalid.
	 */
	public static function regex( $pattern ) {
		$regex = '#' . str_replace( '#', '\#', $pattern ) . '#i';
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Validating a user-supplied pattern.
		return false === @preg_match( $regex, '' ) ? null : $regex;
	}

	/**
	 * Normalizes a key/value rule value.
	 *
	 * @param mixed $value Value.
	 * @return array
	 */
	private static function pair( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'key'   => isset( $value['key'] ) ? (string) $value['key'] : '',
			'value' => isset( $value['value'] ) ? (string) $value['value'] : '',
		);
	}

	/**
	 * Evaluates exists / equals / contains operators.
	 *
	 * @param string $operator Operator.
	 * @param bool   $exists   Whether the key exists.
	 * @param string $actual   Actual value.
	 * @param string $expected Expected value.
	 * @return bool
	 */
	private static function pair_result( $operator, $exists, $actual, $expected ) {
		switch ( $operator ) {
			case 'exists':
				return $exists;
			case 'not_exists':
				return ! $exists;
			case 'equals':
				return $exists && $actual === $expected;
			case 'not_equals':
				return ! $exists || $actual !== $expected;
			case 'contains':
				return $exists && '' !== $expected && false !== stripos( $actual, $expected );
		}
		return false;
	}

	/**
	 * Whether the current request is of a given page type.
	 *
	 * @param string $type Page type key.
	 * @return bool
	 */
	public static function is_page_type( $type ) {
		switch ( $type ) {
			case 'front_page':
				return is_front_page();
			case 'home':
				return is_home();
			case 'singular':
				return is_singular();
			case 'single':
				return is_single();
			case 'page':
				return is_page();
			case 'attachment':
				return is_attachment();
			case 'archive':
				return is_archive();
			case 'category':
				return is_category();
			case 'tag':
				return is_tag();
			case 'taxonomy':
				return is_tax();
			case 'author':
				return is_author();
			case 'date':
				return is_date();
			case 'post_type_archive':
				return is_post_type_archive();
			case 'search':
				return is_search();
			case '404':
				return is_404();
			case 'privacy':
				return is_page() && (int) get_option( 'wp_page_for_privacy_policy' ) === (int) get_queried_object_id();
			case 'wc_shop':
				return function_exists( 'is_shop' ) && is_shop();
			case 'wc_product':
				return function_exists( 'is_product' ) && is_product();
			case 'wc_product_category':
				return function_exists( 'is_product_category' ) && is_product_category();
			case 'wc_product_tag':
				return function_exists( 'is_product_tag' ) && is_product_tag();
			case 'wc_cart':
				return function_exists( 'is_cart' ) && is_cart();
			case 'wc_checkout':
				return function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page();
			case 'wc_order_received':
				return function_exists( 'is_order_received_page' ) && is_order_received_page();
			case 'wc_account':
				return function_exists( 'is_account_page' ) && is_account_page();
		}
		return false;
	}

	/**
	 * The post being viewed, including a static front page or posts page.
	 *
	 * @return \WP_Post|null
	 */
	public static function queried_post() {
		if ( ! did_action( 'wp' ) && ! did_action( 'parse_query' ) ) {
			return null;
		}
		$object = get_queried_object();
		return $object instanceof \WP_Post ? $object : null;
	}

	/**
	 * Whether the current view belongs to any of the given terms.
	 *
	 * @param int[] $term_ids Term IDs.
	 * @return bool
	 */
	private static function matches_terms( array $term_ids ) {
		if ( ! $term_ids ) {
			return false;
		}
		$object = get_queried_object();
		if ( $object instanceof \WP_Term ) {
			return in_array( (int) $object->term_id, $term_ids, true );
		}
		if ( $object instanceof \WP_Post ) {
			foreach ( $term_ids as $term_id ) {
				$term = get_term( $term_id );
				if ( $term instanceof \WP_Term && has_term( $term->term_id, $term->taxonomy, $object ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether the WooCommerce cart contains any of the products.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return bool
	 */
	private static function cart_contains( array $product_ids ) {
		if ( ! $product_ids || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( in_array( (int) $item['product_id'], $product_ids, true ) || ( ! empty( $item['variation_id'] ) && in_array( (int) $item['variation_id'], $product_ids, true ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Current request path and query string, relative to the site root.
	 *
	 * @return string
	 */
	public static function request_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		// Strip the scheme and host esc_url_raw() may add.
		$uri = preg_replace( '#^https?://[^/]+#i', '', $uri );
		// Make the path relative to a WordPress install in a subdirectory.
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '/' !== $home_path && '' !== $home_path && 0 === strpos( $uri, $home_path ) ) {
			$uri = '/' . substr( $uri, strlen( $home_path ) );
		}
		return '' === $uri ? '/' : $uri;
	}

	/**
	 * Current locale, honouring Polylang and WPML.
	 *
	 * @return string
	 */
	public static function current_locale() {
		if ( function_exists( 'pll_current_language' ) ) {
			$locale = pll_current_language( 'locale' );
			if ( $locale ) {
				return (string) $locale;
			}
		}
		$wpml = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
		if ( $wpml ) {
			$languages = apply_filters( 'wpml_active_languages', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API.
			if ( is_array( $languages ) && isset( $languages[ $wpml ]['default_locale'] ) ) {
				return (string) $languages[ $wpml ]['default_locale'];
			}
		}
		return determine_locale();
	}

	/**
	 * Browser key from the user agent.
	 *
	 * @return string
	 */
	public static function browser() {
		$agent = self::user_agent();
		$map   = array(
			'edge'    => '#Edg(e|A|iOS)?/#',
			'opera'   => '#OPR/|Opera#',
			'samsung' => '#SamsungBrowser#',
			'chrome'  => '#Chrome/|CriOS/#',
			'firefox' => '#Firefox/|FxiOS/#',
			'safari'  => '#Safari/#',
			'ie'      => '#MSIE |Trident/#',
		);
		foreach ( $map as $key => $regex ) {
			if ( preg_match( $regex, $agent ) ) {
				return $key;
			}
		}
		return 'other';
	}

	/**
	 * Operating system key from the user agent.
	 *
	 * @return string
	 */
	public static function os() {
		$agent = self::user_agent();
		$map   = array(
			'ios'      => '#iPhone|iPad|iPod#',
			'android'  => '#Android#',
			'chromeos' => '#CrOS#',
			'windows'  => '#Windows#',
			'macos'    => '#Macintosh|Mac OS X#',
			'linux'    => '#Linux#',
		);
		foreach ( $map as $key => $regex ) {
			if ( preg_match( $regex, $agent ) ) {
				return $key;
			}
		}
		return 'other';
	}

	/**
	 * The request's user agent.
	 *
	 * @return string
	 */
	private static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	/**
	 * Whether a rule set contains a PHP function rule.
	 *
	 * Calling PHP functions is PHP execution, so only users allowed to edit PHP
	 * may add or keep such rules.
	 *
	 * @param array $conditions Rule set.
	 * @return bool
	 */
	public static function uses_php( $conditions ) {
		if ( ! is_array( $conditions ) || empty( $conditions['groups'] ) ) {
			return false;
		}
		foreach ( $conditions['groups'] as $group ) {
			foreach ( (array) $group as $rule ) {
				if ( isset( $rule['rule'] ) && 'php_function' === $rule['rule'] ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Sanitizes a rule set from a form or import.
	 *
	 * @param mixed $raw Raw value: array, or JSON string from the editor.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		$clean = self::empty_set();
		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		$clean['enabled'] = ! empty( $raw['enabled'] );
		$clean['action']  = isset( $raw['action'] ) && 'hide' === $raw['action'] ? 'hide' : 'show';

		$groups = isset( $raw['groups'] ) && is_array( $raw['groups'] ) ? $raw['groups'] : array();
		foreach ( array_slice( $groups, 0, 20 ) as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$rules = array();
			foreach ( array_slice( $group, 0, 30 ) as $rule ) {
				$rule = self::sanitize_rule( $rule );
				if ( $rule ) {
					$rules[] = $rule;
				}
			}
			if ( $rules ) {
				$clean['groups'][] = $rules;
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes one rule.
	 *
	 * @param mixed $rule Raw rule.
	 * @return array|null
	 */
	private static function sanitize_rule( $rule ) {
		if ( ! is_array( $rule ) || empty( $rule['rule'] ) ) {
			return null;
		}
		$key       = sanitize_key( $rule['rule'] );
		$operators = self::RULE_OPERATORS;

		/** This filter is documented in includes/admin/class-conditions-ui.php */
		$custom = apply_filters( 'scriptdock_condition_rules', array() );
		if ( ! isset( $operators[ $key ] ) && ! isset( $custom[ $key ] ) ) {
			return null;
		}

		$allowed  = isset( $operators[ $key ] ) ? $operators[ $key ] : array( 'is', 'is_not' );
		$operator = isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : $allowed[0];
		if ( ! in_array( $operator, $allowed, true ) ) {
			$operator = $allowed[0];
		}

		$value = isset( $rule['value'] ) ? $rule['value'] : '';
		if ( is_array( $value ) ) {
			$is_pair  = isset( $value['key'] ) || array_key_exists( 'value', $value );
			$is_range = isset( $value['from'] ) || isset( $value['to'] );
			if ( $is_pair ) {
				$value = array(
					'key'   => isset( $value['key'] ) ? self::clean_text( $value['key'] ) : '',
					'value' => isset( $value['value'] ) ? self::clean_text( $value['value'] ) : '',
				);
			} elseif ( $is_range ) {
				$value = array(
					'from' => isset( $value['from'] ) ? self::clean_text( $value['from'] ) : '',
					'to'   => isset( $value['to'] ) ? self::clean_text( $value['to'] ) : '',
				);
			} else {
				$value = array_values( array_filter( array_map( array( __CLASS__, 'clean_text' ), array_slice( $value, 0, 500 ) ), 'strlen' ) );
			}
		} else {
			$value = self::clean_text( $value );
			if ( in_array( $key, array( 'url', 'referrer' ), true ) ) {
				$value = array_values( array_filter( array_map( 'trim', explode( "\n", $value ) ), 'strlen' ) );
			}
		}

		if ( 'regex' === $operator ) {
			foreach ( (array) $value as $pattern ) {
				if ( null === self::regex( (string) $pattern ) ) {
					return null;
				}
			}
		}

		return array(
			'rule'     => $key,
			'operator' => $operator,
			'value'    => $value,
		);
	}

	/**
	 * Light text cleaning that keeps characters needed in URL patterns and regexes.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	private static function clean_text( $text ) {
		$text = wp_check_invalid_utf8( (string) $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		return trim( substr( $text, 0, 2000 ) );
	}
}
