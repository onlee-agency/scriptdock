<?php
/**
 * Smart tags: dynamic values inside HTML and JavaScript snippets.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces {{tag}} placeholders with values for the current request.
 *
 * Syntax: {{tag}}, {{tag:argument}} and an optional modifier:
 * {{post_title|json}}. Values are escaped for the snippet's context unless a
 * modifier says otherwise:
 * - html (default in HTML snippets): esc_html
 * - js   (default in JavaScript snippets): safe inside a JS string literal
 * - json: a complete JSON value, including quotes
 * - url:  rawurlencode
 * - attr: esc_attr
 * - raw:  unescaped (not available for values visitors or authors control)
 *
 * Unknown tags are left untouched, so templates from other tools keep working.
 */
final class Smart_Tags {

	/**
	 * Tags whose values can come from authors or visitors; never output raw.
	 * The page address counts: a visitor can type anything into it.
	 */
	const UNTRUSTED = array( 'attr', 'post_meta', 'post_title', 'post_excerpt', 'term_name', 'post_author', 'post_categories', 'post_tags', 'page_url', 'page_path', 'wc_order_email' );

	/**
	 * Request context (shortcode attributes and similar).
	 *
	 * @var array
	 */
	private static $vars = array();

	/**
	 * Replaces tags in content.
	 *
	 * @param string $content Content.
	 * @param string $context html or js.
	 * @param array  $vars    Extra variables, such as shortcode attributes under 'atts'.
	 * @return string
	 */
	public static function replace( $content, $context = 'html', array $vars = array() ) {
		$content = (string) $content;
		if ( false === strpos( $content, '{{' ) ) {
			return $content;
		}
		self::$vars = $vars;
		$result     = preg_replace_callback(
			'/\{\{\s*([a-z0-9_]+)(?::([A-Za-z0-9_\-\.]+))?\s*(?:\|\s*([a-z]+)\s*)?\}\}/',
			static function ( $matches ) use ( $context ) {
				$tag      = $matches[1];
				$argument = isset( $matches[2] ) ? $matches[2] : '';
				$modifier = isset( $matches[3] ) ? $matches[3] : '';
				$value    = self::value( $tag, $argument );
				if ( null === $value ) {
					return $matches[0];
				}
				return self::escape( $value, $modifier ? $modifier : $context, in_array( $tag, self::UNTRUSTED, true ) );
			},
			$content
		);
		self::$vars = array();
		return null === $result ? $content : $result;
	}

	/**
	 * Escapes a value.
	 *
	 * @param mixed  $value     Value.
	 * @param string $modifier  Escaping mode.
	 * @param bool   $untrusted Whether raw output is refused.
	 * @return string
	 */
	private static function escape( $value, $modifier, $untrusted ) {
		if ( 'json' === $modifier ) {
			return (string) wp_json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}
		$value = (string) $value;
		switch ( $modifier ) {
			case 'raw':
				return $untrusted ? esc_html( $value ) : $value;
			case 'url':
				return rawurlencode( $value );
			case 'attr':
				return esc_attr( $value );
			case 'js':
				return substr( (string) wp_json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 1, -1 );
			default:
				return esc_html( $value );
		}
	}

	/**
	 * Value for a tag, or null when the tag is unknown or unavailable.
	 *
	 * @param string $tag      Tag.
	 * @param string $argument Argument after the colon.
	 * @return mixed
	 */
	private static function value( $tag, $argument ) {
		$post = Conditions::queried_post();

		switch ( $tag ) {
			case 'site_name':
				return get_bloginfo( 'name' );
			case 'site_url':
				return home_url( '/' );
			case 'page_url':
				return self::current_url();
			case 'page_path':
				return Conditions::request_path();
			case 'page_title':
				return wp_get_document_title();
			case 'post_id':
				return $post ? $post->ID : '';
			case 'post_title':
				return $post ? get_the_title( $post ) : '';
			case 'post_type':
				return $post ? $post->post_type : '';
			case 'post_excerpt':
				return $post ? wp_strip_all_tags( get_the_excerpt( $post ) ) : '';
			case 'post_date':
				return $post ? get_the_date( 'c', $post ) : '';
			case 'post_modified':
				return $post ? get_the_modified_date( 'c', $post ) : '';
			case 'post_author':
				return $post ? get_the_author_meta( 'display_name', (int) $post->post_author ) : '';
			case 'post_categories':
				return $post ? wp_list_pluck( (array) get_the_category( $post->ID ), 'name' ) : array();
			case 'post_tags':
				$tags = $post ? get_the_tags( $post->ID ) : array();
				return is_array( $tags ) ? wp_list_pluck( $tags, 'name' ) : array();
			case 'post_meta':
				if ( ! $post || '' === $argument || is_protected_meta( $argument, 'post' ) ) {
					return '';
				}
				$meta = get_post_meta( $post->ID, $argument, true );
				return is_scalar( $meta ) ? (string) $meta : '';
			case 'term_name':
				$term = get_queried_object();
				return $term instanceof \WP_Term ? $term->name : '';
			case 'page_type':
				return self::page_type();
			case 'user_id':
				return get_current_user_id();
			case 'user_role':
				$user = wp_get_current_user();
				return $user->exists() && $user->roles ? reset( $user->roles ) : 'guest';
			case 'user_logged_in':
				return is_user_logged_in() ? 'true' : 'false';
			case 'language':
				return Conditions::current_locale();
			case 'date':
				return wp_date( get_option( 'date_format' ) );
			case 'time':
				return wp_date( get_option( 'time_format' ) );
			case 'year':
				return wp_date( 'Y' );
			case 'timestamp':
				return time();
			case 'attr':
				$atts = isset( self::$vars['atts'] ) && is_array( self::$vars['atts'] ) ? self::$vars['atts'] : array();
				$key  = strtolower( $argument );
				return isset( $atts[ $key ] ) && is_scalar( $atts[ $key ] ) ? (string) $atts[ $key ] : '';
		}

		if ( 0 === strpos( $tag, 'wc_' ) ) {
			return self::woocommerce_value( $tag );
		}

		/**
		 * Filters the value of a custom smart tag.
		 *
		 * Return null to leave the tag untouched.
		 *
		 * @param mixed  $value    Value.
		 * @param string $tag      Tag name.
		 * @param string $argument Argument after the colon.
		 */
		return apply_filters( 'scriptdock_smart_tag_value', null, $tag, $argument );
	}

	/**
	 * WooCommerce values.
	 *
	 * @param string $tag Tag.
	 * @return mixed
	 */
	private static function woocommerce_value( $tag ) {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		if ( 0 === strpos( $tag, 'wc_order_' ) ) {
			$order = self::current_order();
			if ( ! $order ) {
				return '';
			}
			switch ( $tag ) {
				case 'wc_order_id':
					return $order->get_id();
				case 'wc_order_number':
					return $order->get_order_number();
				case 'wc_order_total':
					return wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
				case 'wc_order_subtotal':
					return wc_format_decimal( $order->get_subtotal(), wc_get_price_decimals() );
				case 'wc_order_tax':
					return wc_format_decimal( $order->get_total_tax(), wc_get_price_decimals() );
				case 'wc_order_shipping':
					return wc_format_decimal( $order->get_shipping_total(), wc_get_price_decimals() );
				case 'wc_order_currency':
					return $order->get_currency();
				case 'wc_order_email':
					return $order->get_billing_email();
				case 'wc_order_coupons':
					return $order->get_coupon_codes();
				case 'wc_order_payment_method':
					return $order->get_payment_method_title();
				case 'wc_order_items':
					$items = array();
					foreach ( $order->get_items() as $item ) {
						if ( ! $item instanceof \WC_Order_Item_Product ) {
							continue;
						}
						$product = $item->get_product();
						$items[] = array(
							'id'       => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
							'sku'      => $product ? $product->get_sku() : '',
							'name'     => $item->get_name(),
							'quantity' => $item->get_quantity(),
							'price'    => (float) wc_format_decimal( $order->get_item_total( $item, false, false ), wc_get_price_decimals() ),
						);
					}
					return $items;
				case 'wc_order_item_ids':
					$ids = array();
					foreach ( $order->get_items() as $item ) {
						if ( $item instanceof \WC_Order_Item_Product ) {
							$ids[] = (string) ( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() );
						}
					}
					return $ids;
			}
			return null;
		}

		if ( 0 === strpos( $tag, 'wc_product_' ) ) {
			$post    = Conditions::queried_post();
			$product = $post && 'product' === $post->post_type ? wc_get_product( $post->ID ) : null;
			if ( ! $product ) {
				return '';
			}
			switch ( $tag ) {
				case 'wc_product_id':
					return $product->get_id();
				case 'wc_product_name':
					return $product->get_name();
				case 'wc_product_sku':
					return $product->get_sku();
				case 'wc_product_price':
					return wc_format_decimal( $product->get_price(), wc_get_price_decimals() );
			}
			return null;
		}

		switch ( $tag ) {
			case 'wc_currency':
				return get_woocommerce_currency();
			case 'wc_cart_total':
				return WC()->cart ? wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() ) : '';
			case 'wc_cart_count':
				return WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		}
		return null;
	}

	/**
	 * The order on the order-received page, verified by its order key.
	 *
	 * Without the key check anyone could read another customer's order
	 * details by changing the order ID in the URL.
	 *
	 * @return \WC_Order|null
	 */
	private static function current_order() {
		static $order = false;
		if ( false !== $order ) {
			return $order;
		}
		$order = null;
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return null;
		}
		$order_id = absint( get_query_var( 'order-received' ) );
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$candidate = $order_id ? wc_get_order( $order_id ) : null;
		if ( $candidate instanceof \WC_Order && '' !== $key && hash_equals( $candidate->get_order_key(), $key ) ) {
			$order = $candidate;
		}
		return $order;
	}

	/**
	 * Current page URL.
	 *
	 * @return string
	 */
	private static function current_url() {
		$home = wp_parse_url( home_url() );
		if ( empty( $home['host'] ) ) {
			return home_url( '/' );
		}
		$base = ( is_ssl() ? 'https' : 'http' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$uri  = preg_replace( '#^https?://[^/]+#i', '', $uri );
		return esc_url_raw( $base . $uri );
	}

	/**
	 * Simple page type label.
	 *
	 * @return string
	 */
	private static function page_type() {
		foreach ( array( 'front_page', 'home', 'page', 'single', 'attachment', 'category', 'tag', 'taxonomy', 'author', 'date', 'post_type_archive', 'search', '404' ) as $type ) {
			if ( Conditions::is_page_type( $type ) ) {
				return $type;
			}
		}
		return is_admin() ? 'admin' : 'other';
	}

	/**
	 * Tag reference for the editor.
	 *
	 * @return array Groups of tag => description.
	 */
	public static function reference() {
		$groups = array(
			__( 'Site & page', 'scriptdock' ) => array(
				'site_name'  => __( 'Site title', 'scriptdock' ),
				'site_url'   => __( 'Home page URL', 'scriptdock' ),
				'page_url'   => __( 'Current page URL', 'scriptdock' ),
				'page_path'  => __( 'Current path and query string', 'scriptdock' ),
				'page_title' => __( 'Document title', 'scriptdock' ),
				'page_type'  => __( 'Page type, e.g. single, page, category', 'scriptdock' ),
				'language'   => __( 'Current locale, e.g. en_US', 'scriptdock' ),
			),
			__( 'Post', 'scriptdock' )        => array(
				'post_id'         => __( 'Post ID', 'scriptdock' ),
				'post_title'      => __( 'Post title', 'scriptdock' ),
				'post_type'       => __( 'Post type', 'scriptdock' ),
				'post_excerpt'    => __( 'Excerpt (plain text)', 'scriptdock' ),
				'post_date'       => __( 'Publish date (ISO 8601)', 'scriptdock' ),
				'post_modified'   => __( 'Last modified date (ISO 8601)', 'scriptdock' ),
				'post_author'     => __( 'Author display name', 'scriptdock' ),
				'post_categories' => __( 'Category names, comma separated', 'scriptdock' ),
				'post_tags'       => __( 'Tag names, comma separated', 'scriptdock' ),
				'post_meta:KEY'   => __( 'A custom field value (public keys only)', 'scriptdock' ),
				'term_name'       => __( 'Term name on category/tag archives', 'scriptdock' ),
			),
			__( 'User & time', 'scriptdock' ) => array(
				'user_id'        => __( 'Current user ID (0 for visitors)', 'scriptdock' ),
				'user_role'      => __( 'First role of the current user, or guest', 'scriptdock' ),
				'user_logged_in' => __( 'true or false', 'scriptdock' ),
				'date'           => __( 'Today, in the site date format', 'scriptdock' ),
				'time'           => __( 'Now, in the site time format', 'scriptdock' ),
				'year'           => __( 'Current year', 'scriptdock' ),
				'timestamp'      => __( 'Unix timestamp', 'scriptdock' ),
				'attr:NAME'      => __( 'Shortcode attribute, e.g. [scriptdock id="1" color="red"]', 'scriptdock' ),
			),
		);

		if ( Registry::woocommerce_active() ) {
			$groups[ __( 'WooCommerce', 'scriptdock' ) ] = self::woocommerce_reference();
		}

		return $groups;
	}

	/**
	 * WooCommerce tags. Listed in the editor even without WooCommerce, marked
	 * unavailable, so people know they exist.
	 *
	 * @return array Tag => description.
	 */
	public static function woocommerce_reference() {
		return array(
			'wc_order_id'             => __( 'Order ID (order-received page)', 'scriptdock' ),
			'wc_order_number'         => __( 'Order number', 'scriptdock' ),
			'wc_order_total'          => __( 'Order total', 'scriptdock' ),
			'wc_order_subtotal'       => __( 'Order subtotal', 'scriptdock' ),
			'wc_order_tax'            => __( 'Order tax', 'scriptdock' ),
			'wc_order_shipping'       => __( 'Order shipping', 'scriptdock' ),
			'wc_order_currency'       => __( 'Order currency code', 'scriptdock' ),
			'wc_order_email'          => __( 'Billing email', 'scriptdock' ),
			'wc_order_coupons'        => __( 'Coupon codes', 'scriptdock' ),
			'wc_order_payment_method' => __( 'Payment method', 'scriptdock' ),
			'wc_order_items|json'     => __( 'Line items as JSON (id, sku, name, quantity, price)', 'scriptdock' ),
			'wc_order_item_ids|json'  => __( 'Product IDs as a JSON array', 'scriptdock' ),
			'wc_product_id'           => __( 'Product ID (product page)', 'scriptdock' ),
			'wc_product_name'         => __( 'Product name', 'scriptdock' ),
			'wc_product_sku'          => __( 'Product SKU', 'scriptdock' ),
			'wc_product_price'        => __( 'Product price', 'scriptdock' ),
			'wc_cart_total'           => __( 'Cart total', 'scriptdock' ),
			'wc_cart_count'           => __( 'Items in cart', 'scriptdock' ),
			'wc_currency'             => __( 'Store currency code', 'scriptdock' ),
		);
	}
}
