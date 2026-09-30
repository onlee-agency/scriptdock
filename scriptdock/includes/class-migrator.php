<?php
/**
 * Imports snippets from other plugins.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the data other snippet plugins leave in the database and converts it
 * into ScriptDock snippets. The other plugin does not need to be active.
 *
 * Supported: WPCode (and Insert Headers and Footers), Code Snippets,
 * Header Footer Code Manager and Simple Custom CSS and JS.
 */
final class Migrator {

	/**
	 * Available sources with the number of snippets found.
	 *
	 * @return array key => array( label, count )
	 */
	public static function sources() {
		$sources = array(
			'wpcode'        => array( 'WPCode', self::count_wpcode() ),
			'wpcode_global' => array( __( 'WPCode / Insert Headers and Footers: global header & footer', 'scriptdock' ), count( self::read_wpcode_global() ) ),
			'code_snippets' => array( 'Code Snippets', self::count_table( 'snippets' ) ),
			'hfcm'          => array( 'Header Footer Code Manager', self::count_table( 'hfcm_scripts' ) ),
			'sccj'          => array( 'Simple Custom CSS and JS', self::count_posts( 'custom-css-js' ) ),
		);
		return array_filter(
			$sources,
			static function ( $source ) {
				return $source[1] > 0;
			}
		);
	}

	/**
	 * Converts a source's data into snippet data arrays.
	 *
	 * @param string $source Source key.
	 * @return array array( items => list of snippet data, warnings => list of messages )
	 */
	public static function read( $source ) {
		switch ( $source ) {
			case 'wpcode':
				return self::read_wpcode();
			case 'wpcode_global':
				return array(
					'items'    => self::read_wpcode_global(),
					'warnings' => array(),
				);
			case 'code_snippets':
				return self::read_code_snippets();
			case 'hfcm':
				return self::read_hfcm();
			case 'sccj':
				return self::read_sccj();
		}
		return array(
			'items'    => array(),
			'warnings' => array(),
		);
	}

	/**
	 * Runs an import.
	 *
	 * @param string $source   Source key.
	 * @param bool   $activate Keep snippets that were active in the source active.
	 * @return array Results from Import_Export::import() plus warnings.
	 */
	public static function run( $source, $activate ) {
		$data    = self::read( $source );
		$results = Import_Export::import(
			$data['items'],
			array(
				'activate' => $activate,
				'source'   => 'migrate:' . $source,
			)
		);
		$results['skipped'] = array_merge( $data['warnings'], $results['skipped'] );
		return $results;
	}

	/* ------------------------------------------------------------ Helpers */

	/**
	 * Rows in a plugin table, or 0 if it does not exist.
	 *
	 * @param string $name Unprefixed table name.
	 * @return int
	 */
	private static function count_table( $name ) {
		global $wpdb;
		$table = $wpdb->prefix . $name;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading another plugin's table.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading another plugin's table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Posts of another plugin's post type (it may not be registered).
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	private static function count_posts( $post_type ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The post type may not be registered when its plugin is inactive.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft')", $post_type ) );
	}

	/**
	 * Posts of another plugin's post type.
	 *
	 * @param string $post_type Post type.
	 * @return \WP_Post[]
	 */
	private static function posts( $post_type ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See count_posts().
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft') ORDER BY ID ASC", $post_type ) );
		return array_filter( array_map( 'get_post', array_map( 'intval', $ids ) ) );
	}

	/**
	 * Term names of a post in a taxonomy that may not be registered.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[] Term slugs.
	 */
	private static function raw_terms( $post_id, $taxonomy ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The taxonomy may not be registered when its plugin is inactive.
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.slug FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tr.object_id = %d AND tt.taxonomy = %s",
				$post_id,
				$taxonomy
			)
		);
	}

	/**
	 * Builds a single-rule-per-row condition set.
	 *
	 * @param array  $rules  Rules for one AND group.
	 * @param string $action show or hide.
	 * @return array
	 */
	private static function conditions( array $rules, $action = 'show' ) {
		if ( ! $rules ) {
			return Conditions::empty_set();
		}
		return array(
			'enabled' => true,
			'action'  => $action,
			'groups'  => array( $rules ),
		);
	}

	/**
	 * Decodes a list stored as JSON or serialized PHP.
	 *
	 * @param mixed $value Stored value.
	 * @return array
	 */
	private static function decode_list( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		$value = (string) $value;
		if ( '' === $value ) {
			return array();
		}
		$json = json_decode( $value, true );
		if ( is_array( $json ) ) {
			return $json;
		}
		if ( is_serialized( $value ) ) {
			$data = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged -- Legacy data from another plugin; objects are disallowed.
			return is_array( $data ) ? $data : array();
		}
		return array_filter( array_map( 'trim', explode( ',', $value ) ) );
	}

	/* ------------------------------------------------------------- WPCode */

	/**
	 * Number of WPCode snippets.
	 *
	 * @return int
	 */
	private static function count_wpcode() {
		return self::count_posts( 'wpcode' );
	}

	/**
	 * Reads WPCode snippets.
	 *
	 * @return array
	 */
	private static function read_wpcode() {
		$types     = array(
			'html'      => 'html',
			'text'      => 'html',
			'blocks'    => 'html',
			'css'       => 'css',
			'scss'      => 'css',
			'js'        => 'js',
			'php'       => 'php',
			'universal' => 'universal',
		);
		$locations = array(
			'everywhere'          => 'php_everywhere',
			'frontend_only'       => 'php_frontend',
			'admin_only'          => 'php_admin',
			'frontend_cl'         => 'php_frontend',
			'on_demand'           => 'on_demand',
			'site_wide_header'    => 'site_header',
			'site_wide_body'      => 'site_body_open',
			'site_wide_footer'    => 'site_footer',
			'before_post'         => 'before_content',
			'after_post'          => 'after_content',
			'before_content'      => 'before_content',
			'after_content'       => 'after_content',
			'before_paragraph'    => 'before_paragraph',
			'after_paragraph'     => 'after_paragraph',
			'before_excerpt'      => 'before_excerpt',
			'after_excerpt'       => 'after_excerpt',
			'between_posts'       => 'between_posts',
			'archive_before_post' => 'between_posts',
			'archive_after_post'  => 'between_posts',
			'admin_head'          => 'admin_header',
			'admin_footer'        => 'admin_footer',
		);

		$items    = array();
		$warnings = array();
		foreach ( self::posts( 'wpcode' ) as $post ) {
			$type_terms = self::raw_terms( $post->ID, 'wpcode_type' );
			$raw_type   = $type_terms ? $type_terms[0] : 'html';
			$type       = isset( $types[ $raw_type ] ) ? $types[ $raw_type ] : 'html';

			$auto      = get_post_meta( $post->ID, '_wpcode_auto_insert', true );
			$loc_terms = self::raw_terms( $post->ID, 'wpcode_location' );
			$location  = ( '0' !== (string) $auto && $loc_terms && isset( $locations[ $loc_terms[0] ] ) ) ? $locations[ $loc_terms[0] ] : 'shortcode';
			if ( ! Registry::type_allowed_at( $type, $location ) ) {
				$location = Registry::default_location( $type );
			}

			$number = (int) get_post_meta( $post->ID, '_wpcode_auto_insert_number', true );
			$args   = array();
			if ( in_array( $location, array( 'before_paragraph', 'after_paragraph' ), true ) ) {
				$args['paragraph'] = max( 1, $number );
			} elseif ( 'between_posts' === $location ) {
				$args['every'] = max( 1, $number );
			}

			$conditions = Conditions::empty_set();
			if ( get_post_meta( $post->ID, '_wpcode_conditional_logic_enabled', true ) ) {
				$converted  = self::convert_wpcode_rules( get_post_meta( $post->ID, '_wpcode_conditional_logic', true ) );
				$conditions = $converted['conditions'];
				if ( $converted['dropped'] ) {
					/* translators: %s: snippet title */
					$warnings[] = sprintf( __( '“%s”: some WPCode conditions have no ScriptDock equivalent and were left out. Check its conditional logic.', 'scriptdock' ), $post->post_title );
				}
			}

			$device = (string) get_post_meta( $post->ID, '_wpcode_device_type', true );
			if ( in_array( $device, array( 'mobile', 'desktop' ), true ) ) {
				$conditions = self::add_rule(
					$conditions,
					array(
						'rule'     => 'device',
						'operator' => 'is',
						'value'    => array( $device ),
					)
				);
			}

			$schedule = get_post_meta( $post->ID, '_wpcode_schedule', true );
			$schedule = is_array( $schedule ) ? $schedule : array();

			if ( 'scss' === $raw_type ) {
				/* translators: %s: snippet title */
				$warnings[] = sprintf( __( '“%s” was SCSS and was imported as plain CSS. Convert any SCSS syntax before activating it.', 'scriptdock' ), $post->post_title );
			}

			$priority = get_post_meta( $post->ID, '_wpcode_priority', true );
			$items[]  = array(
				'title'         => $post->post_title,
				'description'   => (string) get_post_meta( $post->ID, '_wpcode_note', true ),
				'code'          => $post->post_content,
				'type'          => $type,
				'location'      => $location,
				'location_args' => $args,
				'priority'      => '' === $priority ? 10 : (int) $priority,
				'conditions'    => $conditions,
				'schedule'      => array(
					'start' => isset( $schedule['start'] ) ? str_replace( ' ', 'T', substr( (string) $schedule['start'], 0, 16 ) ) : '',
					'end'   => isset( $schedule['end'] ) ? str_replace( ' ', 'T', substr( (string) $schedule['end'], 0, 16 ) ) : '',
				),
				'options'       => array_merge(
					Snippet::default_options(),
					array( 'output' => get_post_meta( $post->ID, '_wpcode_load_as_file', true ) ? 'file' : 'inline' )
				),
				'tags'          => self::raw_terms( $post->ID, 'wpcode_tags' ),
				'active'        => 'publish' === $post->post_status && 'scss' !== $raw_type,
			);
		}

		return array(
			'items'    => $items,
			'warnings' => $warnings,
		);
	}

	/**
	 * Converts WPCode conditional logic.
	 *
	 * @param mixed $rules WPCode rules.
	 * @return array array( conditions, dropped )
	 */
	private static function convert_wpcode_rules( $rules ) {
		$dropped = false;
		$set     = array(
			'enabled' => true,
			'action'  => is_array( $rules ) && isset( $rules['show'] ) && 'hide' === $rules['show'] ? 'hide' : 'show',
			'groups'  => array(),
		);
		$pages   = array(
			'is_front_page' => 'front_page',
			'is_home'       => 'home',
			'is_single'     => 'singular',
			'is_archive'    => 'archive',
			'is_search'     => 'search',
			'is_404'        => '404',
			'is_author'     => 'author',
		);

		$groups = is_array( $rules ) && isset( $rules['groups'] ) && is_array( $rules['groups'] ) ? $rules['groups'] : array();
		foreach ( $groups as $group ) {
			$converted = array();
			foreach ( (array) $group as $row ) {
				$option   = isset( $row['option'] ) ? $row['option'] : '';
				$relation = isset( $row['relation'] ) ? $row['relation'] : '=';
				$values   = isset( $row['value'] ) ? (array) $row['value'] : array();
				$is       = '!=' === $relation ? 'is_not' : 'is';
				$text_ops = array(
					'='           => 'is',
					'!='          => 'is_not',
					'contains'    => 'contains',
					'notcontains' => 'not_contains',
				);
				$rule     = null;

				switch ( $option ) {
					case 'type_of_page':
						$mapped = array();
						foreach ( $values as $value ) {
							if ( isset( $pages[ $value ] ) ) {
								$mapped[] = $pages[ $value ];
							}
						}
						$rule = $mapped ? array( 'page_type', $is, $mapped ) : null;
						break;
					case 'post_type':
						$rule = array( 'post_type', $is, $values );
						break;
					case 'post_id':
						$rule = array( 'post', $is, array_map( 'intval', $values ) );
						break;
					case 'taxonomy_term':
						$rule = array( 'taxonomy_term', $is, array_map( 'intval', $values ) );
						break;
					case 'page_template':
						$rule = array( 'page_template', $is, $values );
						break;
					case 'post_author':
						$rule = array( 'post_author', $is, array_map( 'intval', $values ) );
						break;
					case 'page_url':
					case 'referrer':
						$rule = isset( $text_ops[ $relation ] ) ? array( 'page_url' === $option ? 'url' : 'referrer', $text_ops[ $relation ], $values ) : null;
						if ( $rule && 'referrer' === $rule[0] && 'is_not' === $rule[1] ) {
							$rule[1] = 'not_contains';
						}
						break;
					case 'logged_in':
						$logged = in_array( (string) reset( $values ), array( '1', 'true', 'logged_in' ), true );
						if ( '!=' === $relation ) {
							$logged = ! $logged;
						}
						$rule = array( 'logged_in', 'is', array( $logged ? 'logged_in' : 'logged_out' ) );
						break;
					case 'user_role':
						$rule = array( 'user_role', $is, $values );
						break;
				}

				if ( $rule ) {
					$converted[] = array(
						'rule'     => $rule[0],
						'operator' => $rule[1],
						'value'    => $rule[2],
					);
				} else {
					$dropped = true;
				}
			}
			if ( $converted ) {
				$set['groups'][] = $converted;
			}
		}

		if ( ! $set['groups'] ) {
			$set = Conditions::empty_set();
		}
		return array(
			'conditions' => $set,
			'dropped'    => $dropped,
		);
	}

	/**
	 * Adds a rule to every group (or creates a group).
	 *
	 * @param array $conditions Rule set.
	 * @param array $rule       Rule.
	 * @return array
	 */
	private static function add_rule( array $conditions, array $rule ) {
		if ( empty( $conditions['groups'] ) ) {
			return self::conditions( array( $rule ) );
		}
		if ( 'hide' === $conditions['action'] ) {
			// "Hide if A" AND device X cannot be expressed in one set; keep the original rules.
			return $conditions;
		}
		foreach ( $conditions['groups'] as $index => $group ) {
			$conditions['groups'][ $index ][] = $rule;
		}
		return $conditions;
	}

	/**
	 * Global header/body/footer code from WPCode or Insert Headers and Footers.
	 *
	 * @return array
	 */
	private static function read_wpcode_global() {
		$areas = array(
			'ihaf_insert_header' => array( __( 'Header code (imported)', 'scriptdock' ), 'site_header' ),
			'ihaf_insert_body'   => array( __( 'Body code (imported)', 'scriptdock' ), 'site_body_open' ),
			'ihaf_insert_footer' => array( __( 'Footer code (imported)', 'scriptdock' ), 'site_footer' ),
		);
		$items = array();
		foreach ( $areas as $option => $area ) {
			$code = (string) get_option( $option, '' );
			if ( '' === trim( $code ) ) {
				continue;
			}
			$items[] = array(
				'title'    => $area[0],
				'code'     => $code,
				'type'     => 'html',
				'location' => $area[1],
				'options'  => array_merge( Snippet::default_options(), array( 'smart_tags' => false ) ),
				'tags'     => array( 'imported' ),
				'active'   => true,
			);
		}
		return $items;
	}

	/* ------------------------------------------------------ Code Snippets */

	/**
	 * Reads Code Snippets rows.
	 *
	 * @return array
	 */
	private static function read_code_snippets() {
		global $wpdb;
		$items    = array();
		$warnings = array();
		if ( ! self::count_table( 'snippets' ) ) {
			return compact( 'items', 'warnings' );
		}
		$table = $wpdb->prefix . 'snippets';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading another plugin's table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC', $table ), ARRAY_A );

		foreach ( (array) $rows as $row ) {
			if ( 'condition' === $row['scope'] ) {
				continue;
			}
			$item = Import_Export::from_code_snippets(
				array(
					'name'     => $row['name'],
					'desc'     => $row['description'],
					'code'     => $row['code'],
					'tags'     => array_filter( array_map( 'trim', explode( ',', (string) $row['tags'] ) ) ),
					'scope'    => $row['scope'],
					'priority' => $row['priority'],
					'active'   => (int) $row['active'] > 0,
				)
			);
			if ( ! empty( $row['condition_id'] ) ) {
				/* translators: %s: snippet title */
				$warnings[] = sprintf( __( '“%s” used a Code Snippets condition, which was not imported. Add conditional logic in ScriptDock.', 'scriptdock' ), $row['name'] );
			}
			$items[] = $item;
		}
		return compact( 'items', 'warnings' );
	}

	/* --------------------------------------------------------------- HFCM */

	/**
	 * Reads Header Footer Code Manager rows.
	 *
	 * @return array
	 */
	private static function read_hfcm() {
		global $wpdb;
		$items    = array();
		$warnings = array();
		if ( ! self::count_table( 'hfcm_scripts' ) ) {
			return compact( 'items', 'warnings' );
		}
		$table = $wpdb->prefix . 'hfcm_scripts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading another plugin's table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY script_id ASC', $table ), ARRAY_A );

		$locations = array(
			'header'         => 'site_header',
			'footer'         => 'site_footer',
			'before_content' => 'before_content',
			'after_content'  => 'after_content',
		);

		foreach ( (array) $rows as $row ) {
			$location = isset( $locations[ $row['location'] ] ) ? $locations[ $row['location'] ] : 'site_header';
			$rules    = array();
			$active   = 'active' === $row['status'];

			switch ( $row['display_on'] ) {
				case 's_pages':
					$rules[] = array( 'post', 'is', self::decode_list( $row['s_pages'] ) );
					break;
				case 's_posts':
					$rules[] = array( 'post', 'is', self::decode_list( $row['s_posts'] ) );
					break;
				case 's_categories':
					$rules[] = array( 'taxonomy_term', 'is', self::decode_list( $row['s_categories'] ) );
					break;
				case 's_tags':
					$rules[] = array( 'taxonomy_term', 'is', self::decode_list( $row['s_tags'] ) );
					break;
				case 's_custom_posts':
					$rules[] = array( 'post_type', 'is', self::decode_list( $row['s_custom_posts'] ) );
					break;
				case 's_is_home':
					$rules[] = array( 'page_type', 'is', array( 'home' ) );
					break;
				case 's_is_search':
					$rules[] = array( 'page_type', 'is', array( 'search' ) );
					break;
				case 's_is_archive':
					$rules[] = array( 'page_type', 'is', array( 'archive' ) );
					break;
				case 'manual':
					$location = 'shortcode';
					break;
				case 'latest_posts':
					$active = false;
					/* translators: %s: snippet title */
					$warnings[] = sprintf( __( '“%s” targeted “latest posts”, which ScriptDock does not support. It was imported inactive.', 'scriptdock' ), $row['name'] );
					break;
			}

			$excluded = array_merge( self::decode_list( $row['ex_pages'] ), self::decode_list( $row['ex_posts'] ) );
			if ( $excluded ) {
				$rules[] = array( 'post', 'is_not', $excluded );
			}
			if ( in_array( $row['device_type'], array( 'mobile', 'desktop' ), true ) ) {
				$rules[] = array( 'device', 'is', array( $row['device_type'] ) );
			}

			$rule_rows = array();
			foreach ( $rules as $rule ) {
				$values = array_values( array_filter( array_map( 'strval', (array) $rule[2] ), 'strlen' ) );
				if ( $values ) {
					$rule_rows[] = array(
						'rule'     => $rule[0],
						'operator' => $rule[1],
						'value'    => $values,
					);
				}
			}

			$items[] = array(
				'title'    => $row['name'],
				'code'     => html_entity_decode( (string) $row['snippet'], ENT_QUOTES, 'UTF-8' ),
				'type'     => 'html',
				'location' => $location,
				'conditions' => self::conditions( $rule_rows ),
				'options'  => array_merge( Snippet::default_options(), array( 'smart_tags' => false ) ),
				'tags'     => array( 'imported' ),
				'active'   => $active,
			);
		}
		return compact( 'items', 'warnings' );
	}

	/* ---------------------------------------------- Simple Custom CSS & JS */

	/**
	 * Reads Simple Custom CSS and JS posts.
	 *
	 * @return array
	 */
	private static function read_sccj() {
		$items    = array();
		$warnings = array();
		$places   = array(
			'frontend' => array( 'site_header', 'site_footer' ),
			'admin'    => array( 'admin_header', 'admin_footer' ),
			'login'    => array( 'login_header', 'login_footer' ),
			'block'    => array( 'block_editor', 'block_editor' ),
		);

		foreach ( self::posts( 'custom-css-js' ) as $post ) {
			$options  = get_post_meta( $post->ID, 'options', true );
			$options  = is_array( $options ) ? $options : array();
			$language = isset( $options['language'] ) ? $options['language'] : 'css';
			$type     = in_array( $language, array( 'css', 'js', 'html' ), true ) ? $language : 'css';
			$code     = $post->post_content;

			// JavaScript entered with its own <script> tags is plain HTML for us.
			if ( 'js' === $type && preg_match( '/<script\b/i', $code ) ) {
				$type = 'html';
			}
			if ( ! empty( $options['preprocessor'] ) && 'none' !== $options['preprocessor'] ) {
				/* translators: %s: snippet title */
				$warnings[] = sprintf( __( '“%s” used a CSS preprocessor and was imported as plain CSS.', 'scriptdock' ), $post->post_title );
			}

			$footer = isset( $options['type'] ) && 'footer' === $options['type'];
			$sides  = isset( $options['side'] ) ? array_filter( explode( ',', (string) $options['side'] ) ) : array( 'frontend' );
			$sides  = $sides ? $sides : array( 'frontend' );
			$before = count( $items );

			foreach ( $sides as $side ) {
				if ( ! isset( $places[ $side ] ) ) {
					continue;
				}
				$location = $places[ $side ][ $footer ? 1 : 0 ];
				if ( ! Registry::type_allowed_at( $type, $location ) ) {
					$location = $places[ $side ][0];
				}
				if ( ! Registry::type_allowed_at( $type, $location ) ) {
					continue;
				}
				$items[] = array(
					'title'    => count( $sides ) > 1 ? $post->post_title . ' (' . $side . ')' : $post->post_title,
					'code'     => $code,
					'type'     => $type,
					'location' => $location,
					'priority' => isset( $options['priority'] ) ? (int) $options['priority'] : 10,
					'options'  => array_merge(
						Snippet::default_options(),
						array(
							'output'     => isset( $options['linking'] ) && 'external' === $options['linking'] ? 'file' : 'inline',
							'smart_tags' => false,
						)
					),
					'tags'     => array( 'imported' ),
					'active'   => 'publish' === $post->post_status && 'no' !== get_post_meta( $post->ID, '_active', true ),
				);
			}

			// Otherwise more snippets arrive than the source said it had.
			$made = count( $items ) - $before;
			if ( $made > 1 ) {
				/* translators: 1: snippet title, 2: how many snippets it became. */
				$warnings[] = sprintf( __( '“%1$s” loaded in more than one area (for example the site and the admin), so it came across as %2$d snippets, one for each.', 'scriptdock' ), $post->post_title, $made );
			}
		}
		return compact( 'items', 'warnings' );
	}
}
