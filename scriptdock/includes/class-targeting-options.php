<?php
/**
 * Fixed lists the targeting wizard picks values from.
 *
 * Content, terms and users are searched over REST because a site can have
 * thousands of them. Everything here is small enough to send at once.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Value lists for the condition catalogue.
 */
final class Targeting_Options {

	/**
	 * Every list, in the shape the rule editor reads: value and label.
	 *
	 * @return array List key => array of array( value, label ).
	 */
	public static function all() {
		$labels = Conditions::value_labels();

		return array(
			'post_types'     => self::post_types(),
			'page_templates' => self::page_templates(),
			'roles'          => self::roles(),
			'languages'      => self::languages(),
			'themes'         => self::themes(),
			'plugins'        => self::plugins(),
			'taxonomies'     => self::taxonomies(),
			'page_type'      => self::pairs( $labels['page_type'] ),
			'logged_in'      => self::pairs( $labels['logged_in'] ),
			'device'         => self::pairs( $labels['device'] ),
			'browser'        => self::pairs( $labels['browser'] ),
			'os'             => self::pairs( $labels['os'] ),
			'day_of_week'    => self::days(),
		);
	}

	/**
	 * Public post types, posts and pages first.
	 *
	 * @return array
	 */
	public static function post_types() {
		$list = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$list[] = array(
				'value' => $type->name,
				'label' => $type->labels->singular_name,
				'plural' => $type->labels->name,
			);
		}
		usort(
			$list,
			static function ( $a, $b ) {
				$order = array( 'page' => 0, 'post' => 1, 'product' => 2 );
				$first = isset( $order[ $a['value'] ] ) ? $order[ $a['value'] ] : 3;
				$next  = isset( $order[ $b['value'] ] ) ? $order[ $b['value'] ] : 3;
				return $first === $next ? strcasecmp( $a['label'], $b['label'] ) : $first - $next;
			}
		);
		return $list;
	}

	/**
	 * Page templates, including a block theme's custom templates.
	 *
	 * @return array
	 */
	public static function page_templates() {
		$list = array( array( 'value' => 'default', 'label' => __( 'Default template', 'scriptdock' ) ) );
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) {
			foreach ( wp_get_theme()->get_page_templates( null, $type ) as $file => $name ) {
				$list[ $file ] = array( 'value' => $file, 'label' => $name );
			}
		}
		if ( function_exists( 'get_block_templates' ) && wp_is_block_theme() ) {
			foreach ( get_block_templates( array(), 'wp_template' ) as $template ) {
				if ( ! empty( $template->is_custom ) ) {
					$list[ $template->slug ] = array( 'value' => $template->slug, 'label' => $template->title );
				}
			}
		}
		return array_values( $list );
	}

	/**
	 * User roles.
	 *
	 * @return array
	 */
	public static function roles() {
		$list = array();
		foreach ( wp_roles()->roles as $key => $role ) {
			$list[] = array( 'value' => $key, 'label' => translate_user_role( $role['name'] ) );
		}
		return $list;
	}

	/**
	 * Installed languages. One entry means the site has a single language and
	 * the condition is not worth offering.
	 *
	 * @return array
	 */
	public static function languages() {
		$list = array( 'en_US' => array( 'value' => 'en_US', 'label' => 'English (United States)' ) );
		if ( ! function_exists( 'wp_get_available_translations' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}
		$available = wp_get_available_translations();
		foreach ( get_available_languages() as $locale ) {
			$list[ $locale ] = array(
				'value' => $locale,
				'label' => isset( $available[ $locale ]['native_name'] ) ? $available[ $locale ]['native_name'] : $locale,
			);
		}
		return array_values( $list );
	}

	/**
	 * Installed themes.
	 *
	 * @return array
	 */
	public static function themes() {
		$list = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			$list[] = array( 'value' => (string) $slug, 'label' => $theme->display( 'Name', false, true ) );
		}
		return $list;
	}

	/**
	 * Installed plugins, by the file WordPress knows them as.
	 *
	 * @return array
	 */
	public static function plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$list = array();
		foreach ( get_plugins() as $file => $plugin ) {
			$list[] = array( 'value' => $file, 'label' => $plugin['Name'] );
		}
		usort(
			$list,
			static function ( $a, $b ) {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);
		return $list;
	}

	/**
	 * Taxonomies whose terms can be picked, categories and tags first.
	 *
	 * @return array
	 */
	public static function taxonomies() {
		$list = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$list[] = array(
				'value'     => $taxonomy->name,
				'label'     => $taxonomy->labels->name,
				'singular'  => $taxonomy->labels->singular_name,
				'hierarchical' => (bool) $taxonomy->hierarchical,
			);
		}
		usort(
			$list,
			static function ( $a, $b ) {
				$order = array( 'category' => 0, 'post_tag' => 1, 'product_cat' => 2 );
				$first = isset( $order[ $a['value'] ] ) ? $order[ $a['value'] ] : 3;
				$next  = isset( $order[ $b['value'] ] ) ? $order[ $b['value'] ] : 3;
				return $first === $next ? strcasecmp( $a['label'], $b['label'] ) : $first - $next;
			}
		);
		return $list;
	}

	/**
	 * Days of the week: the full name, and the short one the chips show.
	 *
	 * @return array
	 */
	public static function days() {
		global $wp_locale;
		$list = array();
		foreach ( Conditions::value_labels()['day_of_week'] as $value => $name ) {
			// WordPress counts days from Sunday; the rule counts from Monday.
			$index  = 7 === (int) $value ? 0 : (int) $value;
			$list[] = array(
				'value' => (string) $value,
				'label' => $name,
				'short' => $wp_locale ? $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $index ) ) : $name,
			);
		}
		return $list;
	}

	/**
	 * Turns a value => label map into the list shape.
	 *
	 * @param array $map Value => label.
	 * @return array
	 */
	private static function pairs( array $map ) {
		$list = array();
		foreach ( $map as $value => $label ) {
			$list[] = array( 'value' => (string) $value, 'label' => $label );
		}
		return $list;
	}
}
