<?php
/**
 * Plain-language descriptions of where a snippet runs.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a snippet's placement, rules and schedule into words:
 *
 * - a sentence with chips: "Runs in the [site header] on [3 pages], for
 *   [logged-out] visitors, on [mobile and tablet]."
 * - short facets for the list: "3 pages · Logged out · Mobile".
 * - labelled facts for the quick view and the editor: Placement, Pages,
 *   Audience, Schedule.
 *
 * Rule sets the words cannot describe faithfully are summarised as "Custom
 * rules" rather than guessed at: several rule groups that mix pages with
 * visitors or time, several page rules that must all match, hiding for
 * anything other than pages, and rules added by other plugins.
 */
final class Targeting_Summary {

	/**
	 * Rules that choose pages or content.
	 */
	const CONTENT_RULES = array( 'page_type', 'post_type', 'post', 'post_parent', 'taxonomy_term', 'page_template', 'post_author', 'post_meta', 'url' );

	/**
	 * Rules about the visitor or the request, in the order the sentence
	 * names them: who, where from, then on what.
	 */
	const AUDIENCE_RULES = array( 'logged_in', 'user_role', 'user_meta', 'language', 'referrer', 'query_param', 'cookie', 'device', 'browser', 'os', 'wc_cart_total', 'wc_cart_contains', 'php_function' );

	/**
	 * Rules about time.
	 */
	const TIME_RULES = array( 'day_of_week', 'time_of_day', 'date' );

	/**
	 * Describes where and when a snippet runs.
	 *
	 * A sentence part is array( 'text' => string ) or a chip:
	 * array( 'chip' => string, 'step' => string, 'code' => bool ). The step
	 * names the part of the targeting it came from: placement, content,
	 * audience, schedule or rules.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return array {
	 *     @type array  $parts   Sentence parts.
	 *     @type string $text    The sentence as plain text.
	 *     @type array  $summary Short facets for the list.
	 *     @type array  $facts   Facts for the quick view: key, label, value.
	 *     @type bool   $custom  Whether the rules read "Custom rules".
	 * }
	 */
	public static function describe( Snippet $snippet ) {
		$placement = self::placement( $snippet );
		$rules     = 'on_demand' === $snippet->location ? self::no_rules() : self::rules( $snippet->conditions );
		$schedule  = 'on_demand' === $snippet->location ? null : self::schedule( $snippet->schedule );

		// The whole site is the default for placements that print into front-end pages.
		$not_pages = array_merge( Registry::EXECUTE_LOCATIONS, array( 'on_demand', 'shortcode', 'custom_hook' ) );
		if ( ! $rules['custom'] && ! $rules['content'] && 'frontend' === Compiler::context_for( $snippet->location ) && ! in_array( $snippet->location, $not_pages, true ) ) {
			$rules['content'] = array(
				self::desc(
					/* translators: %s: "every page". */
					__( 'on %s', 'scriptdock' ),
					__( 'every page', 'scriptdock' ),
					__( 'Entire site', 'scriptdock' )
				),
			);
			$rules['default_content'] = true;
		}

		$rules['you_place_it'] = 'shortcode' === $snippet->location && ! $rules['custom'] && ! $rules['content'];

		$parts = self::fill( $placement['template'], array( self::chip( $placement['chip'], 'placement', $placement['code'] ) ) );

		if ( $rules['custom'] ) {
			$parts[] = array( 'text' => ' ' );
			$parts   = array_merge(
				$parts,
				self::fill(
					/* translators: %s: "custom rules". */
					__( 'when its %s match', 'scriptdock' ),
					array( self::chip( __( 'custom rules', 'scriptdock' ), 'rules' ) )
				)
			);
		} elseif ( $rules['content'] ) {
			$parts[] = array( 'text' => ' ' );
			$parts   = array_merge( $parts, self::content_parts( $rules['content'], $rules['except'] ) );
		}

		$clauses = array_merge( $rules['audience'], $rules['time'] );
		if ( $schedule ) {
			$clauses[] = $schedule;
		}
		foreach ( $clauses as $clause ) {
			$parts[] = array( 'text' => _x( ', ', 'separator between parts of a targeting sentence', 'scriptdock' ) );
			$parts   = array_merge( $parts, self::fill( $clause['template'], self::chips( $clause ) ) );
		}
		$parts[] = array( 'text' => _x( '.', 'end of a targeting sentence', 'scriptdock' ) );

		return array(
			'parts'   => self::merge_text( $parts ),
			'text'    => self::plain( $parts ),
			'summary' => self::summary( $rules ),
			'facts'   => self::facts( $snippet, $rules, $schedule ),
			'custom'  => $rules['custom'],
		);
	}

	/**
	 * The placement clause: a template with one chip.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return array template, chip, code.
	 */
	private static function placement( Snippet $snippet ) {
		$args = $snippet->location_args;
		/* translators: %s: where the snippet is placed, e.g. "site header". */
		$in = __( 'Runs in the %s', 'scriptdock' );
		/* translators: %s: where or when the snippet runs, e.g. "before the content". */
		$at = __( 'Runs %s', 'scriptdock' );

		$phrases = array(
			'php_everywhere'           => array( $at, __( 'everywhere', 'scriptdock' ) ),
			'php_frontend'             => array( $at, __( 'on the front end', 'scriptdock' ) ),
			'php_admin'                => array( $at, __( 'in the admin', 'scriptdock' ) ),
			'on_demand'                => array( $at, __( 'only when you run it', 'scriptdock' ) ),
			'site_header'              => array( $in, __( 'site header', 'scriptdock' ) ),
			'site_body_open'           => array( $at, __( 'after the opening body tag', 'scriptdock' ) ),
			'site_footer'              => array( $in, __( 'site footer', 'scriptdock' ) ),
			'before_content'           => array( $at, __( 'before the content', 'scriptdock' ) ),
			'after_content'            => array( $at, __( 'after the content', 'scriptdock' ) ),
			'before_excerpt'           => array( $at, __( 'before excerpts', 'scriptdock' ) ),
			'after_excerpt'            => array( $at, __( 'after excerpts', 'scriptdock' ) ),
			'admin_header'             => array( $in, __( 'admin header', 'scriptdock' ) ),
			'admin_footer'             => array( $in, __( 'admin footer', 'scriptdock' ) ),
			'login_header'             => array( $in, __( 'login page header', 'scriptdock' ) ),
			'login_footer'             => array( $in, __( 'login page footer', 'scriptdock' ) ),
			'block_editor'             => array( $in, __( 'block editor', 'scriptdock' ) ),
			'wc_before_shop_loop'      => array( $at, __( 'before the shop product list', 'scriptdock' ) ),
			'wc_after_shop_loop'       => array( $at, __( 'after the shop product list', 'scriptdock' ) ),
			'wc_before_single_product' => array( $at, __( 'before single products', 'scriptdock' ) ),
			'wc_after_single_product'  => array( $at, __( 'after single products', 'scriptdock' ) ),
			'wc_before_add_to_cart'    => array( $at, __( 'before the add-to-cart form', 'scriptdock' ) ),
			'wc_after_add_to_cart'     => array( $at, __( 'after the add-to-cart form', 'scriptdock' ) ),
			'wc_before_cart'           => array( $at, __( 'before the cart', 'scriptdock' ) ),
			'wc_after_cart'            => array( $at, __( 'after the cart', 'scriptdock' ) ),
			'wc_before_checkout'       => array( $at, __( 'before the checkout form', 'scriptdock' ) ),
			'wc_after_checkout'        => array( $at, __( 'after the checkout form', 'scriptdock' ) ),
			'wc_thankyou'              => array( $at, __( 'on the order received page', 'scriptdock' ) ),
			'wc_account_dashboard'     => array( $at, __( 'on the My Account dashboard', 'scriptdock' ) ),
			'shortcode'                => array( $at, __( 'where you place its shortcode or block', 'scriptdock' ) ),
		);

		$location = $snippet->location;
		$code     = false;
		if ( isset( $phrases[ $location ] ) ) {
			list( $template, $chip ) = $phrases[ $location ];
		} elseif ( 'before_paragraph' === $location || 'after_paragraph' === $location ) {
			$number   = isset( $args['paragraph'] ) ? (int) $args['paragraph'] : 1;
			$template = $at;
			$chip     = 'before_paragraph' === $location
				/* translators: %d: paragraph number. */
				? sprintf( __( 'before paragraph %d', 'scriptdock' ), $number )
				/* translators: %d: paragraph number. */
				: sprintf( __( 'after paragraph %d', 'scriptdock' ), $number );
		} elseif ( 'between_posts' === $location ) {
			$every    = isset( $args['every'] ) ? max( 1, (int) $args['every'] ) : 3;
			$template = $at;
			/* translators: %d: number of posts between each insertion. */
			$chip = sprintf( _n( 'between posts, every %d post', 'between posts, every %d posts', $every, 'scriptdock' ), $every );
		} elseif ( 'custom_hook' === $location ) {
			/* translators: %s: action hook name. */
			$template = __( 'Runs on the %s action', 'scriptdock' );
			$chip     = isset( $args['hook'] ) && '' !== $args['hook'] ? $args['hook'] : __( '(no hook chosen)', 'scriptdock' );
			$code     = isset( $args['hook'] ) && '' !== $args['hook'];
		} else {
			/* translators: %s: placement name. */
			$template = __( 'Runs at %s', 'scriptdock' );
			$chip     = Registry::location_label( $location );
		}

		return array(
			'template' => $template,
			'chip'     => $chip,
			'code'     => $code,
		);
	}

	/**
	 * Rules with nothing chosen.
	 *
	 * @return array
	 */
	private static function no_rules() {
		return array(
			'content'         => array(),
			'except'          => false,
			'audience'        => array(),
			'time'            => array(),
			'custom'          => false,
			'default_content' => false,
		);
	}

	/**
	 * Sorts a rule set into content, audience and time clauses, or marks it
	 * custom when the clauses would not say exactly what the rules do.
	 *
	 * @param array $conditions Rule set.
	 * @return array content, except, audience, time, custom.
	 */
	private static function rules( $conditions ) {
		$result = self::no_rules();
		if ( ! Conditions::is_active( $conditions ) ) {
			return $result;
		}
		$hide   = 'hide' === $conditions['action'];
		$groups = array_values( array_filter( $conditions['groups'] ) );

		// "These pages, for these visitors" is stored as one group per page
		// choice, each carrying the same visitor and time rules. Those repeats
		// are one clause, so they are taken out before the groups are read.
		$shared = count( $groups ) > 1 ? self::shared_rules( $groups ) : array();
		if ( $shared ) {
			$groups = self::without_shared( $groups, $shared );
		}
		foreach ( $shared as $rule ) {
			if ( ! self::add_extra( $result, $rule ) ) {
				return self::custom();
			}
		}

		// Several groups: only a plain list of content choices reads right ("on A and B").
		if ( count( $groups ) > 1 ) {
			foreach ( $groups as $group ) {
				$desc = self::group_content( $group );
				if ( ! $desc || $desc['negated'] ) {
					return self::custom();
				}
				$result['content'][] = $desc;
			}
			$result['except'] = $hide;
			return $result;
		}

		$content = array();
		foreach ( $groups[0] as $rule ) {
			if ( in_array( $rule['rule'], self::CONTENT_RULES, true ) ) {
				$content[] = $rule;
				continue;
			}
			if ( ! self::add_extra( $result, $rule ) ) {
				return self::custom();
			}
		}

		// Two page rules that must both match, or hiding for visitors or times, need the rule view.
		if ( count( $content ) > 1 ) {
			$pair = self::group_content( $content );
			if ( ! $pair ) {
				return self::custom();
			}
			$content = array();
			$result['content'] = array( $pair );
			$result['except']  = $hide !== $pair['negated'];
		}
		if ( $hide && ( $result['audience'] || $result['time'] || ( ! $content && ! $result['content'] ) ) ) {
			return self::custom();
		}
		usort(
			$result['audience'],
			static function ( $a, $b ) {
				return array_search( $a['rule'], self::AUDIENCE_RULES, true ) - array_search( $b['rule'], self::AUDIENCE_RULES, true );
			}
		);
		if ( $content ) {
			$desc = self::content_rule( $content[0] );
			if ( ! $desc ) {
				return self::custom();
			}
			$result['content'] = array( $desc );
			$result['except']  = $hide !== $desc['negated'];
		}
		return $result;
	}

	/**
	 * Adds a visitor or time rule to the clauses.
	 *
	 * @param array $result Clauses, by reference.
	 * @param array $rule   Rule.
	 * @return bool False when the rule cannot be described.
	 */
	private static function add_extra( array &$result, array $rule ) {
		$is_time = in_array( $rule['rule'], self::TIME_RULES, true );
		$desc    = $is_time ? self::time_rule( $rule ) : self::audience_rule( $rule );
		if ( ! $desc ) {
			return false;
		}
		$desc['step'] = $is_time ? 'schedule' : 'audience';
		$desc['rule'] = $rule['rule'];
		$result[ $is_time ? 'time' : 'audience' ][] = $desc;
		return true;
	}

	/**
	 * The rules that appear in every group, in the first group's order.
	 *
	 * @param array $groups Rule groups.
	 * @return array
	 */
	private static function shared_rules( array $groups ) {
		$shared = array();
		foreach ( $groups[0] as $candidate ) {
			$everywhere = true;
			foreach ( array_slice( $groups, 1 ) as $group ) {
				if ( ! self::holds_rule( $group, $candidate ) ) {
					$everywhere = false;
					break;
				}
			}
			if ( $everywhere ) {
				$shared[] = $candidate;
			}
		}
		return $shared;
	}

	/**
	 * The groups with the shared rules taken out.
	 *
	 * @param array $groups Rule groups.
	 * @param array $shared Rules every group holds.
	 * @return array
	 */
	private static function without_shared( array $groups, array $shared ) {
		$rest = array();
		foreach ( $groups as $group ) {
			$kept = array();
			foreach ( $group as $rule ) {
				if ( ! self::holds_rule( $shared, $rule ) ) {
					$kept[] = $rule;
				}
			}
			if ( $kept ) {
				$rest[] = $kept;
			}
		}
		return $rest;
	}

	/**
	 * Whether a list holds this exact rule.
	 *
	 * @param array $rules Rules.
	 * @param array $rule  Rule.
	 * @return bool
	 */
	private static function holds_rule( array $rules, array $rule ) {
		foreach ( $rules as $candidate ) {
			if ( $candidate === $rule ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * What one group of content rules describes: a single rule, or a term
	 * rule paired with the page types that say where it applies.
	 *
	 * @param array $group Rules in the group.
	 * @return array|null Description, or null when it needs the rule view.
	 */
	private static function group_content( array $group ) {
		foreach ( $group as $rule ) {
			if ( ! in_array( $rule['rule'], self::CONTENT_RULES, true ) ) {
				return null;
			}
		}
		if ( 1 === count( $group ) ) {
			return self::content_rule( $group[0] );
		}
		if ( 2 !== count( $group ) ) {
			return null;
		}

		$terms = null;
		$pages = null;
		foreach ( $group as $rule ) {
			if ( 'taxonomy_term' === $rule['rule'] ) {
				$terms = $rule;
			} elseif ( 'page_type' === $rule['rule'] ) {
				$pages = $rule;
			}
		}
		if ( ! $terms || ! $pages || 'is' !== $pages['operator'] ) {
			return null;
		}
		$values  = array_map( 'strval', (array) $pages['value'] );
		$singles = array( 'singular' );
		$archive = array( 'category', 'tag', 'taxonomy' );
		$desc    = self::content_rule( $terms );
		if ( ! $desc ) {
			return null;
		}
		$name = self::term_name( $terms );
		if ( $values === $singles ) {
			/* translators: %s: category, tag or term name. */
			$desc['chip'] = sprintf( __( 'posts in %s', 'scriptdock' ), $name );
			return $desc;
		}
		if ( $values === $archive ) {
			/* translators: %s: category, tag or term name. */
			$desc['chip'] = sprintf( __( '%s archive pages', 'scriptdock' ), $name );
			return $desc;
		}
		return null;
	}

	/**
	 * What a term rule's terms are called, for the sentence.
	 *
	 * @param array $rule Term rule.
	 * @return string
	 */
	private static function term_name( array $rule ) {
		$values = (array) $rule['value'];
		if ( 1 === count( $values ) ) {
			$term = get_term( (int) $values[0] );
			return $term instanceof \WP_Term ? $term->name : '#' . (int) $values[0];
		}
		/* translators: %d: number of terms. */
		return sprintf( _n( '%d term', '%d terms', count( $values ), 'scriptdock' ), count( $values ) );
	}

	/**
	 * A rule set shown as "Custom rules".
	 *
	 * @return array
	 */
	private static function custom() {
		$result           = self::no_rules();
		$result['custom'] = true;
		return $result;
	}

	/**
	 * Describes a content rule as a noun phrase: "3 pages", "content in News".
	 *
	 * @param array $rule Rule.
	 * @return array|null Description, or null when it cannot be described.
	 */
	private static function content_rule( array $rule ) {
		$values   = (array) $rule['value'];
		$operator = $rule['operator'];
		$count    = count( $values );
		$negated  = in_array( $operator, array( 'is_not', 'not_contains', 'not_exists', 'not_equals' ), true );
		$chip     = '';
		$short    = '';

		switch ( $rule['rule'] ) {
			case 'page_type':
				$labels = Conditions::value_labels()['page_type'];
				$names  = array();
				foreach ( $values as $value ) {
					$names[] = isset( $labels[ $value ] ) ? $labels[ $value ] : $value;
				}
				if ( $count <= 2 ) {
					$chip  = implode( _x( ' and ', 'between two page types in a sentence', 'scriptdock' ), $names );
					$short = implode( _x( ', ', 'separator between page types', 'scriptdock' ), $names );
				} else {
					/* translators: %d: number of page types. */
					$chip = sprintf( _n( '%d page type', '%d page types', $count, 'scriptdock' ), $count );
				}
				break;

			case 'post_type':
				if ( 1 === $count ) {
					$type  = get_post_type_object( $values[0] );
					$short = $type ? $type->labels->name : $values[0];
					/* translators: %s: plural post type name, for example "Posts". */
					$chip = sprintf( __( 'all %s', 'scriptdock' ), $short );
				} else {
					/* translators: %d: number of post types. */
					$chip = sprintf( _n( '%d content type', '%d content types', $count, 'scriptdock' ), $count );
				}
				break;

			case 'post':
				$ids = array_map( 'intval', $values );
				if ( 2 === $count ) {
					$titles = array( self::post_title( $ids[0] ), self::post_title( $ids[1] ) );
					$chip   = implode( _x( ' and ', 'between two page titles in a sentence', 'scriptdock' ), $titles );
					$short  = implode( _x( ', ', 'separator between page titles', 'scriptdock' ), $titles );
				} else {
					$chip = self::posts_phrase( $ids );
				}
				break;

			case 'post_parent':
				if ( 1 === $count ) {
					$parent = self::post_title( (int) $values[0] );
					/* translators: %s: page title. */
					$chip = sprintf( __( 'pages under %s', 'scriptdock' ), $parent );
					/* translators: %s: page title. */
					$short = sprintf( __( 'Under %s', 'scriptdock' ), $parent );
				} else {
					/* translators: %d: number of parent pages. */
					$chip = sprintf( _n( 'pages under %d page', 'pages under %d pages', $count, 'scriptdock' ), $count );
					/* translators: %d: number of parent pages. */
					$short = sprintf( _n( 'Under %d page', 'Under %d pages', $count, 'scriptdock' ), $count );
				}
				break;

			case 'taxonomy_term':
				if ( 1 === $count ) {
					$term = get_term( (int) $values[0] );
					$name = $term instanceof \WP_Term ? $term->name : '#' . (int) $values[0];
					/* translators: %s: category, tag or term name. */
					$chip = sprintf( __( 'content in %s', 'scriptdock' ), $name );
					/* translators: %s: category, tag or term name. */
					$short = sprintf( __( 'In %s', 'scriptdock' ), $name );
				} else {
					/* translators: %d: number of terms. */
					$chip = sprintf( _n( 'content in %d term', 'content in %d terms', $count, 'scriptdock' ), $count );
					/* translators: %d: number of terms. */
					$short = sprintf( _n( 'In %d term', 'In %d terms', $count, 'scriptdock' ), $count );
				}
				break;

			case 'page_template':
				if ( 1 === $count ) {
					$names = 'default' === $values[0] ? array() : wp_get_theme()->get_page_templates( null, 'page' );
					$name  = 'default' === $values[0] ? __( 'the default template', 'scriptdock' ) : ( isset( $names[ $values[0] ] ) ? $names[ $values[0] ] : $values[0] );
					/* translators: %s: page template name. */
					$chip = sprintf( __( 'pages using %s', 'scriptdock' ), $name );
					/* translators: %s: page template name. */
					$short = sprintf( __( 'Template: %s', 'scriptdock' ), $name );
				} else {
					/* translators: %d: number of page templates. */
					$chip = sprintf( _n( 'pages using %d template', 'pages using %d templates', $count, 'scriptdock' ), $count );
					/* translators: %d: number of page templates. */
					$short = sprintf( _n( '%d template', '%d templates', $count, 'scriptdock' ), $count );
				}
				break;

			case 'post_author':
				if ( 1 === $count ) {
					$user   = get_userdata( (int) $values[0] );
					$author = $user ? $user->display_name : '#' . (int) $values[0];
					/* translators: %s: author name. */
					$chip = sprintf( __( 'content by %s', 'scriptdock' ), $author );
					/* translators: %s: author name. */
					$short = sprintf( __( 'By %s', 'scriptdock' ), $author );
				} else {
					/* translators: %d: number of authors. */
					$chip = sprintf( _n( 'content by %d author', 'content by %d authors', $count, 'scriptdock' ), $count );
					/* translators: %d: number of authors. */
					$short = sprintf( _n( 'By %d author', 'By %d authors', $count, 'scriptdock' ), $count );
				}
				break;

			case 'post_meta':
				$pair = self::pair( $rule['value'] );
				/* translators: %s: custom field name. */
				$short = sprintf( __( 'Field: %s', 'scriptdock' ), $pair['key'] );
				if ( in_array( $operator, array( 'exists', 'not_exists' ), true ) ) {
					/* translators: %s: custom field name. */
					$chip = sprintf( __( 'content with the %s field', 'scriptdock' ), $pair['key'] );
				} elseif ( 'contains' === $operator ) {
					/* translators: 1: custom field name, 2: text. */
					$chip = sprintf( __( 'content where %1$s contains “%2$s”', 'scriptdock' ), $pair['key'], $pair['value'] );
				} else {
					/* translators: 1: custom field name, 2: value. */
					$chip = sprintf( __( 'content where %1$s is “%2$s”', 'scriptdock' ), $pair['key'], $pair['value'] );
				}
				break;

			case 'url':
				$chip = self::url_phrase( $operator, $values );
				if ( 1 === $count && in_array( $operator, array( 'is', 'is_not' ), true ) ) {
					/* translators: %s: a URL path. */
					$short = sprintf( __( 'URL %s', 'scriptdock' ), (string) $values[0] );
				}
				break;

			default:
				return null;
		}

		return self::desc( '', $chip, '' !== $short ? $short : $chip ) + array( 'negated' => $negated );
	}

	/**
	 * Names a list of posts: its title when there is one, else a count.
	 *
	 * @param int[] $ids Post IDs.
	 * @return string
	 */
	private static function posts_phrase( array $ids ) {
		$count = count( $ids );
		if ( 1 === $count ) {
			return self::post_title( $ids[0] );
		}
		_prime_post_caches( $ids, false, false );
		$types = array_unique( array_filter( array_map( 'get_post_type', $ids ) ) );
		$type  = 1 === count( $types ) ? reset( $types ) : '';
		if ( 'page' === $type ) {
			/* translators: %d: number of pages. */
			return sprintf( _n( '%d page', '%d pages', $count, 'scriptdock' ), $count );
		}
		if ( 'post' === $type ) {
			/* translators: %d: number of posts. */
			return sprintf( _n( '%d post', '%d posts', $count, 'scriptdock' ), $count );
		}
		if ( 'product' === $type ) {
			/* translators: %d: number of products. */
			return sprintf( _n( '%d product', '%d products', $count, 'scriptdock' ), $count );
		}
		/* translators: %d: number of posts, pages or other items. */
		return sprintf( _n( '%d item', '%d items', $count, 'scriptdock' ), $count );
	}

	/**
	 * Title of a post, or its ID when it is gone.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	private static function post_title( $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return '#' . (int) $id;
		}
		return '' !== $post->post_title ? $post->post_title : __( '(no title)', 'scriptdock' );
	}

	/**
	 * Describes URL patterns: "URLs containing /blog".
	 *
	 * @param string   $operator Operator.
	 * @param string[] $patterns Patterns (any one matches).
	 * @return string
	 */
	private static function url_phrase( $operator, array $patterns ) {
		$count = count( $patterns );
		if ( 1 !== $count ) {
			/* translators: %d: number of URL patterns. */
			return sprintf( _n( 'URLs matching %d pattern', 'URLs matching %d patterns', $count, 'scriptdock' ), $count );
		}
		$pattern = (string) $patterns[0];
		switch ( $operator ) {
			case 'contains':
			case 'not_contains':
				/* translators: %s: part of a URL. */
				return sprintf( __( 'URLs containing %s', 'scriptdock' ), $pattern );
			case 'starts_with':
				/* translators: %s: start of a URL. */
				return sprintf( __( 'URLs starting with %s', 'scriptdock' ), $pattern );
			case 'ends_with':
				/* translators: %s: end of a URL. */
				return sprintf( __( 'URLs ending with %s', 'scriptdock' ), $pattern );
			case 'wildcard':
			case 'regex':
				/* translators: %s: URL pattern. */
				return sprintf( __( 'URLs matching %s', 'scriptdock' ), $pattern );
			default:
				/* translators: %s: a URL path. */
				return sprintf( __( 'the URL %s', 'scriptdock' ), $pattern );
		}
	}

	/**
	 * Describes a visitor or request rule as a clause.
	 *
	 * @param array $rule Rule.
	 * @return array|null Description, or null when it cannot be described.
	 */
	private static function audience_rule( array $rule ) {
		$values   = (array) $rule['value'];
		$operator = $rule['operator'];
		$count    = count( $values );
		$not      = in_array( $operator, array( 'is_not', 'not_contains', 'not_exists', 'not_equals', 'returns_false' ), true );
		$labels   = Conditions::value_labels();

		switch ( $rule['rule'] ) {
			case 'logged_in':
				$out = 'logged_out' === reset( $values );
				return self::desc(
					/* translators: %s: "logged-in" or "logged-out". */
					__( 'for %s visitors', 'scriptdock' ),
					$out ? __( 'logged-out', 'scriptdock' ) : __( 'logged-in', 'scriptdock' ),
					$labels['logged_in'][ $out ? 'logged_out' : 'logged_in' ]
				);

			case 'user_role':
				$roles = wp_roles()->roles;
				$name  = 1 === $count && isset( $roles[ $values[0] ] ) ? translate_user_role( $roles[ $values[0] ]['name'] ) : '';
				$chip  = '' !== $name
					/* translators: %s: role name, for example "Editor". */
					? sprintf( __( '%s users', 'scriptdock' ), $name )
					/* translators: %d: number of roles. */
					: sprintf( _n( 'users with %d role', 'users with %d roles', $count, 'scriptdock' ), $count );
				if ( '' !== $name ) {
					/* translators: %s: role name, for example "Editor". */
					$short = $not ? sprintf( __( 'Not role: %s', 'scriptdock' ), $name ) : sprintf( __( 'Role: %s', 'scriptdock' ), $name );
				} else {
					/* translators: %s: users, for example "users with 2 roles". */
					$short = $not ? sprintf( __( 'Not %s', 'scriptdock' ), $chip ) : $chip;
				}
				return self::desc(
					/* translators: %s: users, for example "Editor users". */
					$not ? __( 'for everyone except %s', 'scriptdock' ) : __( 'for %s', 'scriptdock' ),
					$chip,
					$short
				);

			case 'user_meta':
				$pair = self::pair( $rule['value'] );
				if ( in_array( $operator, array( 'exists', 'not_exists' ), true ) ) {
					/* translators: %s: user meta key. */
					$chip = sprintf( __( 'users with %s', 'scriptdock' ), $pair['key'] );
				} else {
					/* translators: 1: user meta key, 2: value. */
					$chip = sprintf( 'contains' === $operator ? __( 'users whose %1$s contains “%2$s”', 'scriptdock' ) : __( 'users whose %1$s is “%2$s”', 'scriptdock' ), $pair['key'], $pair['value'] );
				}
				return self::desc(
					/* translators: %s: users, for example "users with vip". */
					$not ? __( 'for everyone except %s', 'scriptdock' ) : __( 'for %s', 'scriptdock' ),
					$chip
				);

			case 'device':
				$mobile = 'mobile' === reset( $values );
				return self::desc(
					/* translators: %s: "mobile and tablet" or "desktop". */
					__( 'on %s', 'scriptdock' ),
					$mobile ? __( 'mobile and tablet', 'scriptdock' ) : __( 'desktop', 'scriptdock' ),
					$mobile ? __( 'Mobile', 'scriptdock' ) : __( 'Desktop', 'scriptdock' )
				);

			case 'browser':
			case 'os':
				$names = $labels[ $rule['rule'] ];
				$chip  = 1 === $count
					? ( isset( $names[ $values[0] ] ) ? $names[ $values[0] ] : $values[0] )
					: ( 'browser' === $rule['rule']
						/* translators: %d: number of browsers. */
						? sprintf( _n( '%d browser', '%d browsers', $count, 'scriptdock' ), $count )
						/* translators: %d: number of operating systems. */
						: sprintf( _n( '%d operating system', '%d operating systems', $count, 'scriptdock' ), $count ) );
				if ( 'browser' === $rule['rule'] ) {
					/* translators: %s: browser name. */
					$template = $not ? __( 'not in %s', 'scriptdock' ) : __( 'in %s', 'scriptdock' );
				} else {
					/* translators: %s: operating system name. */
					$template = $not ? __( 'not on %s', 'scriptdock' ) : __( 'on %s', 'scriptdock' );
				}
				/* translators: %s: browser or operating system name. */
				return self::desc( $template, $chip, $not ? sprintf( __( 'Not %s', 'scriptdock' ), $chip ) : $chip );

			case 'language':
				$chip = 1 === $count
					? self::language_name( $values[0] )
					/* translators: %d: number of languages. */
					: sprintf( _n( '%d language', '%d languages', $count, 'scriptdock' ), $count );
				return self::desc(
					/* translators: %s: language name. */
					$not ? __( 'not in %s', 'scriptdock' ) : __( 'in %s', 'scriptdock' ),
					$chip,
					/* translators: %s: language name. */
					$not ? sprintf( __( 'Not %s', 'scriptdock' ), $chip ) : $chip
				);

			case 'referrer':
				if ( 'exists' === $operator || 'not_exists' === $operator ) {
					return self::desc(
						/* translators: %s: "a referrer" or "no referrer". */
						__( 'for visitors with %s', 'scriptdock' ),
						'exists' === $operator ? __( 'a referrer', 'scriptdock' ) : __( 'no referrer', 'scriptdock' )
					);
				}
				$chip = 1 === $count
					? (string) $values[0]
					/* translators: %d: number of referrer patterns. */
					: sprintf( _n( '%d referrer pattern', '%d referrer patterns', $count, 'scriptdock' ), $count );
				return self::desc(
					/* translators: %s: referring site or pattern. */
					$not ? __( 'for visitors not from %s', 'scriptdock' ) : __( 'for visitors from %s', 'scriptdock' ),
					$chip,
					/* translators: %s: referring site or pattern. */
					$not ? sprintf( __( 'Not from %s', 'scriptdock' ), $chip ) : sprintf( __( 'From %s', 'scriptdock' ), $chip )
				);

			case 'query_param':
			case 'cookie':
				$pair   = self::pair( $rule['value'] );
				$cookie = 'cookie' === $rule['rule'];
				$chip   = in_array( $operator, array( 'exists', 'not_exists' ), true ) || '' === $pair['value']
					? ( $cookie ? $pair['key'] : '?' . $pair['key'] )
					: ( $cookie ? $pair['key'] . '=' . $pair['value'] : '?' . $pair['key'] . '=' . $pair['value'] );
				if ( 'contains' === $operator ) {
					/* translators: 1: parameter or cookie name, 2: text. */
					$chip = sprintf( __( '%1$s containing “%2$s”', 'scriptdock' ), $cookie ? $pair['key'] : '?' . $pair['key'], $pair['value'] );
				}
				if ( $cookie ) {
					/* translators: %s: cookie name, or name=value. */
					$template = $not ? __( 'without the %s cookie', 'scriptdock' ) : __( 'with the %s cookie', 'scriptdock' );
				} else {
					/* translators: %s: URL parameter, for example ?utm_source=news. */
					$template = $not ? __( 'when the URL has no %s', 'scriptdock' ) : __( 'when the URL has %s', 'scriptdock' );
				}
				/* translators: %s: URL parameter or cookie. */
				return self::desc( $template, $chip, $not ? sprintf( __( 'Not %s', 'scriptdock' ), $chip ) : $chip, true );

			case 'wc_cart_total':
				$amount = (float) reset( $values );
				$money  = function_exists( 'wc_price' ) ? html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ) : number_format_i18n( $amount, 2 );
				$phrases = array(
					/* translators: %s: amount of money. */
					'gt'  => __( 'over %s', 'scriptdock' ),
					/* translators: %s: amount of money. */
					'gte' => __( '%s or more', 'scriptdock' ),
					/* translators: %s: amount of money. */
					'lt'  => __( 'under %s', 'scriptdock' ),
					/* translators: %s: amount of money. */
					'lte' => __( '%s or less', 'scriptdock' ),
				);
				$chip = sprintf( isset( $phrases[ $operator ] ) ? $phrases[ $operator ] : $phrases['lte'], $money );
				return self::desc(
					/* translators: %s: amount condition, for example "over $50.00". */
					__( 'when the cart total is %s', 'scriptdock' ),
					$chip,
					/* translators: %s: amount condition, for example "over $50.00". */
					sprintf( __( 'Cart %s', 'scriptdock' ), $chip )
				);

			case 'wc_cart_contains':
				$chip = 1 === $count
					? self::post_title( (int) $values[0] )
					/* translators: %d: number of products. */
					: sprintf( _n( 'one of %d product', 'one of %d products', $count, 'scriptdock' ), $count );
				return self::desc(
					/* translators: %s: product name, or "one of 3 products". */
					$not ? __( 'when the cart does not contain %s', 'scriptdock' ) : __( 'when the cart contains %s', 'scriptdock' ),
					$chip,
					/* translators: %s: product name, or "one of 3 products". */
					$not ? sprintf( __( 'Cart without %s', 'scriptdock' ), $chip ) : sprintf( __( 'Cart has %s', 'scriptdock' ), $chip )
				);

			case 'php_function':
				$function = (string) reset( $values ) . '()';
				return self::desc(
					/* translators: %s: PHP function name. */
					$not ? __( 'when %s returns false', 'scriptdock' ) : __( 'when %s returns true', 'scriptdock' ),
					$function,
					$function,
					true
				);
		}
		return null;
	}

	/**
	 * Describes a time rule as a clause.
	 *
	 * @param array $rule Rule.
	 * @return array|null Description, or null when it cannot be described.
	 */
	private static function time_rule( array $rule ) {
		$operator = $rule['operator'];
		switch ( $rule['rule'] ) {
			case 'day_of_week':
				$chip = self::days_phrase( array_map( 'intval', (array) $rule['value'] ) );
				return self::desc(
					/* translators: %s: days, for example "Mon–Fri". */
					'is_not' === $operator ? __( 'except on %s', 'scriptdock' ) : __( 'on %s', 'scriptdock' ),
					$chip,
					/* translators: %s: days, for example "Mon–Fri". */
					'is_not' === $operator ? sprintf( __( 'Not %s', 'scriptdock' ), $chip ) : $chip
				);

			case 'time_of_day':
				$range = is_array( $rule['value'] ) ? $rule['value'] : array();
				$from  = isset( $range['from'] ) ? self::time_label( $range['from'] ) : '';
				$to    = isset( $range['to'] ) ? self::time_label( $range['to'] ) : '';
				if ( '' === $from || '' === $to ) {
					return null;
				}
				/* translators: 1: start time, 2: end time. */
				$chip = sprintf( _x( '%1$s–%2$s', 'time range', 'scriptdock' ), $from, $to );
				return self::desc(
					/* translators: %s: time range, for example "09:00–17:00". */
					'not_between' === $operator ? __( 'outside %s', 'scriptdock' ) : '%s',
					$chip,
					/* translators: %s: time range, for example "09:00–17:00". */
					'not_between' === $operator ? sprintf( __( 'Outside %s', 'scriptdock' ), $chip ) : $chip
				);

			case 'date':
				$timestamp = Compiler::to_timestamp( is_array( $rule['value'] ) ? (string) reset( $rule['value'] ) : (string) $rule['value'] );
				if ( ! $timestamp ) {
					return null;
				}
				$chip = self::date_label( $timestamp );
				return self::desc(
					/* translators: %s: date. */
					'before' === $operator ? __( 'before %s', 'scriptdock' ) : __( 'from %s', 'scriptdock' ),
					$chip,
					/* translators: %s: date. */
					'before' === $operator ? sprintf( __( 'Before %s', 'scriptdock' ), $chip ) : sprintf( __( 'From %s', 'scriptdock' ), $chip )
				);
		}
		return null;
	}

	/**
	 * The schedule clause: "from 1 Oct until 31 Oct".
	 *
	 * @param array $schedule Start and end, Y-m-d\TH:i in the site timezone.
	 * @return array|null Clause, or null when there is no schedule.
	 */
	private static function schedule( array $schedule ) {
		$start = Compiler::to_timestamp( isset( $schedule['start'] ) ? $schedule['start'] : '' );
		$end   = Compiler::to_timestamp( isset( $schedule['end'] ) ? $schedule['end'] : '' );
		if ( $start && $end ) {
			$chips = array( self::date_label( $start ), self::date_label( $end ) );
			return array(
				/* translators: 1: start date, 2: end date. */
				'template' => __( 'from %1$s until %2$s', 'scriptdock' ),
				'chip'     => $chips,
				/* translators: 1: start date, 2: end date. */
				'short'    => sprintf( _x( '%1$s–%2$s', 'date range', 'scriptdock' ), $chips[0], $chips[1] ),
				'step'     => 'schedule',
				'code'     => false,
			);
		}
		if ( $start ) {
			$chip = self::date_label( $start );
			/* translators: %s: start date. */
			return self::desc( __( 'from %s', 'scriptdock' ), $chip, sprintf( __( 'From %s', 'scriptdock' ), $chip ), false, 'schedule' );
		}
		if ( $end ) {
			$chip = self::date_label( $end );
			/* translators: %s: end date. */
			return self::desc( __( 'until %s', 'scriptdock' ), $chip, sprintf( __( 'Until %s', 'scriptdock' ), $chip ), false, 'schedule' );
		}
		return null;
	}

	/**
	 * "Mon–Fri", "Sat, Sun" or "Mon, Wed, Fri".
	 *
	 * @param int[] $days ISO days, 1 (Monday) to 7 (Sunday).
	 * @return string
	 */
	private static function days_phrase( array $days ) {
		global $wp_locale;
		$days = array_values( array_unique( array_filter( $days ) ) );
		sort( $days );
		$abbrev = static function ( $day ) use ( $wp_locale ) {
			return $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $day % 7 ) );
		};

		// Collapse runs of three or more days into a range.
		$runs = array();
		foreach ( $days as $day ) {
			$last = count( $runs ) - 1;
			if ( $last >= 0 && end( $runs[ $last ] ) === $day - 1 ) {
				$runs[ $last ][] = $day;
			} else {
				$runs[] = array( $day );
			}
		}
		$labels = array();
		foreach ( $runs as $run ) {
			if ( count( $run ) >= 3 ) {
				/* translators: 1: first day, 2: last day. */
				$labels[] = sprintf( _x( '%1$s–%2$s', 'day range', 'scriptdock' ), $abbrev( $run[0] ), $abbrev( end( $run ) ) );
			} else {
				foreach ( $run as $day ) {
					$labels[] = $abbrev( $day );
				}
			}
		}
		return implode( _x( ', ', 'separator between days', 'scriptdock' ), $labels );
	}

	/**
	 * A 24-hour time in the site's time format.
	 *
	 * @param string $time HH:MM.
	 * @return string Empty when invalid.
	 */
	private static function time_label( $time ) {
		if ( ! preg_match( '/^\d{2}:\d{2}$/', (string) $time ) ) {
			return '';
		}
		return (string) mysql2date( get_option( 'time_format' ), '2000-01-01 ' . $time . ':00' );
	}

	/**
	 * A short date: "1 Oct", "1 Oct 2027", with the time when it is not
	 * midnight.
	 *
	 * @param int $timestamp Timestamp.
	 * @return string
	 */
	public static function date_label( $timestamp ) {
		$format = wp_date( 'Y', $timestamp ) === wp_date( 'Y' )
			? _x( 'j M', 'short date in targeting summaries', 'scriptdock' )
			: _x( 'j M Y', 'short date with year in targeting summaries', 'scriptdock' );
		$label  = wp_date( $format, $timestamp );
		if ( '00:00' !== wp_date( 'H:i', $timestamp ) ) {
			/* translators: 1: date, 2: time. */
			$label = sprintf( _x( '%1$s at %2$s', 'date and time', 'scriptdock' ), $label, wp_date( get_option( 'time_format' ), $timestamp ) );
		}
		return $label;
	}

	/**
	 * A language's own name, without asking WordPress.org for the list.
	 *
	 * @param string $locale Locale code.
	 * @return string
	 */
	private static function language_name( $locale ) {
		if ( 'en_US' === $locale ) {
			return 'English (United States)';
		}
		$available = get_site_transient( 'available_translations' );
		return is_array( $available ) && isset( $available[ $locale ]['native_name'] ) ? $available[ $locale ]['native_name'] : (string) $locale;
	}

	/**
	 * Key and value of a pair rule.
	 *
	 * @param mixed $value Rule value.
	 * @return array key, value.
	 */
	private static function pair( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'key'   => isset( $value['key'] ) ? (string) $value['key'] : '',
			'value' => isset( $value['value'] ) ? (string) $value['value'] : '',
		);
	}

	/**
	 * A clause description.
	 *
	 * @param string $template Template with one %s for the chip.
	 * @param string $chip     Chip text inside the sentence.
	 * @param string $short    Text on its own, for the list; defaults to the chip.
	 * @param bool   $code     Whether the chip is code (a hook, parameter or function).
	 * @param string $step     Targeting step the clause belongs to.
	 * @return array
	 */
	private static function desc( $template, $chip, $short = '', $code = false, $step = '' ) {
		return array(
			'template' => $template,
			'chip'     => $chip,
			'short'    => '' !== $short ? $short : $chip,
			'step'     => $step,
			'code'     => $code,
		);
	}

	/**
	 * The chips of a clause.
	 *
	 * @param array $clause Clause description.
	 * @return array
	 */
	private static function chips( array $clause ) {
		$list = array();
		foreach ( (array) $clause['chip'] as $chip ) {
			$list[] = self::chip( $chip, $clause['step'], $clause['code'] );
		}
		return $list;
	}

	/**
	 * A chip part.
	 *
	 * @param string $label Text.
	 * @param string $step  Targeting step.
	 * @param bool   $code  Whether the text is code.
	 * @return array
	 */
	private static function chip( $label, $step, $code = false ) {
		return array(
			'chip' => (string) $label,
			'step' => $step,
			'code' => (bool) $code,
		);
	}

	/**
	 * The content clause: "on 3 pages and content in News", or "on every
	 * page except 3 pages".
	 *
	 * @param array $descs  Content descriptions (any one matches).
	 * @param bool  $except Whether the content is excluded.
	 * @return array Parts.
	 */
	private static function content_parts( array $descs, $except ) {
		$chips = array();
		foreach ( $descs as $desc ) {
			$chips[] = self::chip( $desc['chip'], 'content' );
		}
		$list = array();
		$last = count( $chips ) - 1;
		foreach ( $chips as $index => $chip ) {
			if ( $index > 0 ) {
				$list[] = array( 'text' => $index === $last ? _x( ' and ', 'last separator in a list of pages', 'scriptdock' ) : _x( ', ', 'separator in a list of pages', 'scriptdock' ) );
			}
			$list[] = $chip;
		}
		$template = $except
			/* translators: %s: pages, for example "3 pages". */
			? __( 'on every page except %s', 'scriptdock' )
			/* translators: %s: pages, for example "3 pages". */
			: __( 'on %s', 'scriptdock' );
		return self::fill( $template, array( array( 'list' => $list ) ) );
	}

	/**
	 * Splits a translated template on its placeholders and puts the chips in.
	 *
	 * Handles %s and numbered %1$s placeholders. A chip may be a list of
	 * parts ( 'list' => parts ). If a translation lost its placeholders, the
	 * chips follow the text so nothing disappears.
	 *
	 * @param string $template Template.
	 * @param array  $chips    Chip parts, in placeholder order.
	 * @return array Parts.
	 */
	private static function fill( $template, array $chips ) {
		$pieces = preg_split( '/(%(?:\d+\$)?s)/', (string) $template, -1, PREG_SPLIT_DELIM_CAPTURE );
		$parts  = array();
		$next   = 0;
		$used   = array();
		foreach ( $pieces as $piece ) {
			if ( preg_match( '/^%(?:(\d+)\$)?s$/', $piece, $match ) ) {
				$index = isset( $match[1] ) && '' !== $match[1] ? (int) $match[1] - 1 : $next++;
				if ( isset( $chips[ $index ] ) ) {
					$used[ $index ] = true;
					$parts          = array_merge( $parts, self::expand( $chips[ $index ] ) );
				}
				continue;
			}
			if ( '' !== $piece ) {
				$parts[] = array( 'text' => $piece );
			}
		}
		foreach ( $chips as $index => $chip ) {
			if ( empty( $used[ $index ] ) ) {
				$parts[] = array( 'text' => ' ' );
				$parts   = array_merge( $parts, self::expand( $chip ) );
			}
		}
		return $parts;
	}

	/**
	 * A chip, or the parts of a chip list.
	 *
	 * @param array $chip Chip or list.
	 * @return array Parts.
	 */
	private static function expand( array $chip ) {
		return isset( $chip['list'] ) ? $chip['list'] : array( $chip );
	}

	/**
	 * Joins neighbouring text parts.
	 *
	 * @param array $parts Parts.
	 * @return array
	 */
	private static function merge_text( array $parts ) {
		$merged = array();
		foreach ( $parts as $part ) {
			$last = count( $merged ) - 1;
			if ( isset( $part['text'] ) && $last >= 0 && isset( $merged[ $last ]['text'] ) ) {
				$merged[ $last ]['text'] .= $part['text'];
				continue;
			}
			$merged[] = $part;
		}
		return $merged;
	}

	/**
	 * The sentence as plain text.
	 *
	 * @param array $parts Parts.
	 * @return string
	 */
	private static function plain( array $parts ) {
		$text = '';
		foreach ( $parts as $part ) {
			$text .= isset( $part['text'] ) ? $part['text'] : $part['chip'];
		}
		return $text;
	}

	/**
	 * Short facets for the list: "3 pages", "Logged out", "Mon–Fri".
	 *
	 * @param array $rules Sorted rules.
	 * @return string[]
	 */
	private static function summary( array $rules ) {
		if ( $rules['custom'] ) {
			return array( __( 'Custom rules', 'scriptdock' ) );
		}
		$summary = ! empty( $rules['you_place_it'] ) ? array( __( 'You place it', 'scriptdock' ) ) : array();
		if ( $rules['content'] ) {
			$first = $rules['content'][0]['short'];
			$more  = count( $rules['content'] ) - 1;
			/* translators: 1: first selection, for example "3 pages", 2: number of other selections. */
			$label     = $more ? sprintf( __( '%1$s + %2$d more', 'scriptdock' ), $first, $more ) : $first;
			/* translators: %s: pages, for example "3 pages". */
			$summary[] = $rules['except'] ? sprintf( __( 'Except %s', 'scriptdock' ), $label ) : $label;
		}
		foreach ( array_merge( $rules['audience'], $rules['time'] ) as $desc ) {
			$summary[] = $desc['short'];
		}
		return $summary;
	}

	/**
	 * Facts for the quick view.
	 *
	 * @param Snippet    $snippet  Snippet.
	 * @param array      $rules    Sorted rules.
	 * @param array|null $schedule Schedule clause.
	 * @return array
	 */
	private static function facts( Snippet $snippet, array $rules, $schedule ) {
		$placement = Registry::location_label( $snippet->location );
		$args      = $snippet->location_args;
		if ( 'custom_hook' === $snippet->location && ! empty( $args['hook'] ) ) {
			$placement .= ' · ' . $args['hook'];
		} elseif ( in_array( $snippet->location, array( 'before_paragraph', 'after_paragraph' ), true ) ) {
			$placement = str_replace( '#', (string) ( isset( $args['paragraph'] ) ? (int) $args['paragraph'] : 1 ), $placement );
		} elseif ( 'between_posts' === $snippet->location ) {
			$every = isset( $args['every'] ) ? max( 1, (int) $args['every'] ) : 3;
			/* translators: %d: number of posts between each insertion. */
			$placement .= ' · ' . sprintf( _n( 'every %d post', 'every %d posts', $every, 'scriptdock' ), $every );
		}

		$facts = array(
			array(
				'key'     => 'placement',
				'label'   => __( 'Placement', 'scriptdock' ),
				'value'   => $placement,
				'default' => false,
			),
		);
		if ( 'on_demand' === $snippet->location ) {
			return $facts;
		}

		if ( $rules['custom'] ) {
			$facts[] = array(
				'key'     => 'rules',
				'label'   => __( 'Rules', 'scriptdock' ),
				'value'   => __( 'Custom rules', 'scriptdock' ),
				'default' => false,
			);
		} else {
			$content = self::summary( array_merge( $rules, array( 'audience' => array(), 'time' => array() ) ) );
			if ( $content ) {
				$facts[] = array(
					'key'     => 'content',
					'label'   => __( 'Pages', 'scriptdock' ),
					'value'   => $content[0],
					'default' => ! empty( $rules['default_content'] ) || ! empty( $rules['you_place_it'] ),
				);
			}
			$facts[] = array(
				'key'     => 'audience',
				'label'   => __( 'Audience', 'scriptdock' ),
				'value'   => $rules['audience'] ? implode( ' · ', wp_list_pluck( $rules['audience'], 'short' ) ) : __( 'Everyone', 'scriptdock' ),
				'default' => ! $rules['audience'],
			);
		}

		$when = $rules['custom'] ? array() : wp_list_pluck( $rules['time'], 'short' );
		if ( $schedule ) {
			$when[] = $schedule['short'];
		}
		$facts[] = array(
			'key'     => 'time',
			'label'   => __( 'Schedule', 'scriptdock' ),
			'value'   => $when ? implode( ' · ', $when ) : __( 'Always', 'scriptdock' ),
			'default' => ! $when,
		);
		return $facts;
	}
}
