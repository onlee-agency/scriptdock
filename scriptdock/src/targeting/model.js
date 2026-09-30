/**
 * The wizard's draft, and how it turns into the rule set a snippet stores.
 *
 * Stored rules are groups of rules: the groups are OR, the rules inside a
 * group are AND. The wizard reads as "this content, for these visitors, at
 * these times", so a draft compiles like this:
 *
 *   Entire site        one group of the visitor and time rules, if any.
 *   Only on selected   one group per piece of content picked, each carrying
 *                      the visitor and time rules, which is how "any of these
 *                      pages AND logged out" comes out as OR-of-AND.
 *   Everywhere except  one group: every content rule turned around (is none
 *                      of), AND the visitor and time rules.
 *
 * Rules that were not written here — by the old editor, by an import, or in
 * the Advanced rules view — are kept exactly as they are and shown as
 * "Custom rules", never rewritten.
 */

export const CONTENT_RULES = [
	'post',
	'post_parent',
	'post_type',
	'taxonomy_term',
	'page_type',
	'url',
	'page_template',
	'post_author',
];

const NEGATED = {
	is: 'is_not',
	is_not: 'is',
	contains: 'not_contains',
	not_contains: 'contains',
};

// Special pages that mean "a single post or page", for the term rule's
// "apply to" choice.
const SINGULAR = [ 'singular' ];
const ARCHIVES = [ 'category', 'tag', 'taxonomy' ];

/**
 * An untouched draft.
 *
 * @return {Object} Draft.
 */
export function emptyDraft() {
	return {
		location: 'site_header',
		locationArgs: {},
		priority: 10,
		scope: 'all',
		content: {
			posts: [],
			children: [],
			types: [],
			terms: [],
			termsApply: 'both',
			special: [],
			urls: [],
		},
		audience: {
			loggedIn: '',
			roles: [],
			users: [],
			devices: [],
			browsers: [],
			os: [],
			languages: [],
			referrer: { operator: 'contains', values: [] },
			params: [],
			cookies: [],
			postMeta: [],
			userMeta: [],
			php: '',
			phpOperator: 'returns_true',
			wc: {
				total: { operator: 'between', from: '', to: '', amount: '' },
				products: [],
				categories: [],
				ordered: '',
			},
		},
		time: { days: [], from: '', to: '' },
		schedule: { start: '', end: '' },
		custom: null,
	};
}

/**
 * Reads a snippet into a draft. Rules the wizard cannot draw are kept in
 * `custom`.
 *
 * @param {Object} snippet Snippet from the REST layer.
 * @return {Object} Draft.
 */
export function toDraft( snippet ) {
	const draft = emptyDraft();
	draft.location =
		snippet.placement?.key || snippet.location || 'site_header';
	draft.locationArgs = { ...( snippet.location_args || {} ) };
	draft.priority = Number.isFinite( snippet.priority )
		? snippet.priority
		: 10;
	draft.schedule = {
		start: snippet.schedule?.start || '',
		end: snippet.schedule?.end || '',
	};

	const parsed = parseConditions( snippet.conditions );
	if ( parsed ) {
		draft.scope = parsed.scope;
		draft.content = { ...draft.content, ...parsed.content };
		draft.audience = { ...draft.audience, ...parsed.audience };
		draft.time = { ...draft.time, ...parsed.time };
	} else {
		draft.custom = normalize( snippet.conditions );
	}
	return draft;
}

/**
 * The rule set a draft saves.
 *
 * @param {Object} draft Draft.
 * @return {Object} Conditions.
 */
export function toConditions( draft ) {
	if ( draft.custom ) {
		return normalize( draft.custom );
	}

	const extra = [
		...audienceRules( draft.audience ),
		...timeRules( draft.time ),
	];
	let groups = [];

	if ( draft.scope === 'only' ) {
		const alternatives = contentAlternatives( draft.content );
		groups = alternatives.length
			? alternatives.map( ( alternative ) => [
					...alternative,
					...extra,
				] )
			: wrap( extra );
	} else if ( draft.scope === 'except' ) {
		const flat = contentAlternatives( draft.content, true )
			.flat()
			.map( turnAround );
		groups = flat.length || extra.length ? [ [ ...flat, ...extra ] ] : [];
	} else {
		groups = wrap( extra );
	}

	return {
		enabled: groups.length > 0,
		action: 'show',
		groups,
	};
}

/**
 * Whether two drafts describe the same targeting.
 *
 * @param {Object} a Draft.
 * @param {Object} b Draft.
 * @return {boolean} True when nothing changed.
 */
export function sameTargeting( a, b ) {
	return (
		JSON.stringify( [
			a.location,
			a.locationArgs,
			a.priority,
			a.schedule,
			toConditions( a ),
		] ) ===
		JSON.stringify( [
			b.location,
			b.locationArgs,
			b.priority,
			b.schedule,
			toConditions( b ),
		] )
	);
}

/**
 * How many things a draft narrows by, per step, for the stepper's dots.
 *
 * @param {Object} draft Draft.
 * @return {Object} { content, audience, schedule }.
 */
export function counts( draft ) {
	const { content, audience, time, schedule } = draft;
	const contentCount =
		draft.scope === 'all'
			? 0
			: content.posts.length +
				content.types.length +
				content.terms.length +
				content.special.length +
				content.urls.filter( ( row ) => row.value ).length;
	const audienceCount =
		( audience.loggedIn ? 1 : 0 ) +
		audience.roles.length +
		audience.users.length +
		audience.devices.length +
		audience.browsers.length +
		audience.os.length +
		audience.languages.length +
		audience.referrer.values.length +
		audience.params.length +
		audience.cookies.length +
		audience.postMeta.length +
		audience.userMeta.length +
		( audience.php ? 1 : 0 ) +
		wcCount( audience.wc );
	const scheduleCount =
		( schedule.start || schedule.end ? 1 : 0 ) +
		( time.days.length ? 1 : 0 ) +
		( time.from && time.to ? 1 : 0 );

	return {
		content: contentCount,
		audience: audienceCount,
		schedule: scheduleCount,
	};
}

/**
 * How many WooCommerce conditions a draft carries.
 *
 * @param {Object} wc WooCommerce part of the audience.
 * @return {number} Count.
 */
function wcCount( wc ) {
	const total =
		wc.total.operator === 'between'
			? wc.total.from !== '' || wc.total.to !== ''
			: wc.total.amount !== '';
	return (
		( total ? 1 : 0 ) +
		wc.products.length +
		wc.categories.length +
		( wc.ordered ? 1 : 0 )
	);
}

/**
 * Turns a rule around, for "everywhere except".
 *
 * @param {Object} rule Rule.
 * @return {Object} Rule with the opposite operator.
 */
function turnAround( rule ) {
	return { ...rule, operator: NEGATED[ rule.operator ] || 'is_not' };
}

/**
 * Wraps rules in a single group, or nothing when there are none.
 *
 * @param {Array} rules Rules.
 * @return {Array} Groups.
 */
function wrap( rules ) {
	return rules.length ? [ rules ] : [];
}

/**
 * The content picked, as alternatives: each entry is a set of rules that must
 * all match, and any entry matching is enough.
 *
 * @param {Object}  content Content selection.
 * @param {boolean} flatten Whether the caller ANDs everything (except mode),
 *                          where the term rule's "apply to" cannot be kept.
 * @return {Array} Alternatives.
 */
function contentAlternatives( content, flatten = false ) {
	const out = [];
	if ( content.posts.length ) {
		out.push( [ makeRule( 'post', 'is', content.posts ) ] );
	}
	if ( content.children.length ) {
		out.push( [ makeRule( 'post_parent', 'is', content.children ) ] );
	}
	if ( content.types.length ) {
		out.push( [ makeRule( 'post_type', 'is', content.types ) ] );
	}
	if ( content.terms.length ) {
		const terms = makeRule( 'taxonomy_term', 'is', content.terms );
		if ( flatten || content.termsApply === 'both' ) {
			out.push( [ terms ] );
		} else {
			out.push( [
				terms,
				makeRule(
					'page_type',
					'is',
					content.termsApply === 'posts' ? SINGULAR : ARCHIVES
				),
			] );
		}
	}
	if ( content.special.length ) {
		out.push( [ makeRule( 'page_type', 'is', content.special ) ] );
	}
	content.urls
		.filter( ( row ) => row.value.trim() !== '' )
		.forEach( ( row ) => {
			out.push( [
				makeRule( 'url', row.operator, [ row.value.trim() ] ),
			] );
		} );

	return out;
}

/**
 * The visitor rules a draft carries.
 *
 * @param {Object} audience Audience part of the draft.
 * @return {Array} Rules.
 */
function audienceRules( audience ) {
	const out = [];
	if ( audience.loggedIn ) {
		out.push( makeRule( 'logged_in', 'is', [ audience.loggedIn ] ) );
	}
	if ( audience.roles.length ) {
		out.push( makeRule( 'user_role', 'is', audience.roles ) );
	}
	if ( audience.users.length ) {
		out.push( makeRule( 'user_id', 'is', audience.users ) );
	}
	if ( audience.devices.length === 1 ) {
		out.push( makeRule( 'device', 'is', audience.devices ) );
	}
	if ( audience.browsers.length ) {
		out.push( makeRule( 'browser', 'is', audience.browsers ) );
	}
	if ( audience.os.length ) {
		out.push( makeRule( 'os', 'is', audience.os ) );
	}
	if ( audience.languages.length ) {
		out.push( makeRule( 'language', 'is', audience.languages ) );
	}
	if ( audience.referrer.values.length ) {
		out.push(
			makeRule(
				'referrer',
				audience.referrer.operator,
				audience.referrer.values
			)
		);
	}
	audience.params.forEach( ( pair ) => {
		if ( pair.key ) {
			out.push( pairRule( 'query_param', pair ) );
		}
	} );
	audience.cookies.forEach( ( pair ) => {
		if ( pair.key ) {
			out.push( pairRule( 'cookie', pair ) );
		}
	} );
	audience.postMeta.forEach( ( pair ) => {
		if ( pair.key ) {
			out.push( pairRule( 'post_meta', pair ) );
		}
	} );
	audience.userMeta.forEach( ( pair ) => {
		if ( pair.key ) {
			out.push( pairRule( 'user_meta', pair ) );
		}
	} );
	if ( audience.php ) {
		out.push(
			makeRule( 'php_function', audience.phpOperator, [ audience.php ] )
		);
	}

	const { wc } = audience;
	if ( wc.total.operator === 'between' ) {
		if ( wc.total.from !== '' || wc.total.to !== '' ) {
			out.push( {
				rule: 'wc_cart_total',
				operator: 'between',
				value: {
					from: String( wc.total.from ),
					to: String( wc.total.to ),
				},
			} );
		}
	} else if ( wc.total.amount !== '' ) {
		out.push(
			makeRule( 'wc_cart_total', wc.total.operator, [
				String( wc.total.amount ),
			] )
		);
	}
	if ( wc.products.length ) {
		out.push( makeRule( 'wc_cart_contains', 'is', wc.products ) );
	}
	if ( wc.categories.length ) {
		out.push( makeRule( 'wc_cart_category', 'is', wc.categories ) );
	}
	if ( wc.ordered ) {
		out.push( { rule: 'wc_has_ordered', operator: wc.ordered, value: '' } );
	}
	return out;
}

/**
 * The day and time rules a draft carries.
 *
 * @param {Object} time Time part of the draft.
 * @return {Array} Rules.
 */
function timeRules( time ) {
	const out = [];
	if ( time.days.length && time.days.length < 7 ) {
		out.push( makeRule( 'day_of_week', 'is', time.days ) );
	}
	if ( time.from && time.to ) {
		out.push( {
			rule: 'time_of_day',
			operator: 'between',
			value: { from: time.from, to: time.to },
		} );
	}
	return out;
}

/**
 * Builds a rule with a list of values.
 *
 * @param {string} key      Rule key.
 * @param {string} operator Operator.
 * @param {Array}  values   Values.
 * @return {Object} Rule.
 */
function makeRule( key, operator, values ) {
	return { rule: key, operator, value: values.map( String ) };
}

/**
 * Builds a key-and-value rule.
 *
 * @param {string} key  Rule key.
 * @param {Object} pair { key, value, operator }.
 * @return {Object} Rule.
 */
function pairRule( key, pair ) {
	return {
		rule: key,
		operator: pair.operator || 'equals',
		value: { key: pair.key, value: pair.value || '' },
	};
}

/**
 * A rule set in its stored shape.
 *
 * @param {Object} conditions Rule set.
 * @return {Object} Rule set.
 */
function normalize( conditions ) {
	return {
		enabled: !! conditions?.enabled,
		action: conditions?.action === 'hide' ? 'hide' : 'show',
		groups: Array.isArray( conditions?.groups ) ? conditions.groups : [],
	};
}

/**
 * Reads a stored rule set back into the wizard's parts, or returns null when
 * the rules do not fit the wizard and belong in Advanced rules.
 *
 * @param {Object} conditions Rule set.
 * @return {Object|null} { scope, content, audience, time } or null.
 */
export function parseConditions( conditions ) {
	const set = normalize( conditions );
	const blank = emptyDraft();

	if ( ! set.enabled || ! set.groups.length ) {
		return {
			scope: 'all',
			content: blank.content,
			audience: blank.audience,
			time: blank.time,
		};
	}

	// The old editor wrote "hide on these pages" as its own action.
	if ( set.action === 'hide' ) {
		if ( set.groups.length !== 1 ) {
			return null;
		}
		const content = readContent( set.groups[ 0 ], false );
		return content
			? {
					scope: 'except',
					content,
					audience: blank.audience,
					time: blank.time,
				}
			: null;
	}

	// Rules repeated in every group are the visitor and time rules.
	const shared = set.groups.reduce(
		( kept, group ) =>
			kept.filter( ( candidate ) =>
				group.some( ( other ) => same( candidate, other ) )
			),
		set.groups[ 0 ].slice()
	);
	const extras = readExtras( shared );
	if ( ! extras ) {
		return null;
	}

	const rest = set.groups.map( ( group ) =>
		group.filter(
			( item ) => ! shared.some( ( kept ) => same( kept, item ) )
		)
	);

	// Everything was shared: one group, no content of its own.
	if ( rest.every( ( group ) => group.length === 0 ) ) {
		if ( set.groups.length > 1 ) {
			return null;
		}
		return {
			scope: 'all',
			content: blank.content,
			audience: extras.audience,
			time: extras.time,
		};
	}

	// A single group of turned-around content rules is "everywhere except".
	if ( rest.length === 1 && rest[ 0 ].every( isTurnedAround ) ) {
		const content = readContent( rest[ 0 ].map( turnAround ), false );
		return content
			? {
					scope: 'except',
					content,
					audience: extras.audience,
					time: extras.time,
				}
			: null;
	}

	// Otherwise every group holds one alternative of the content picked.
	const content = readContent( rest.flat(), true, rest );
	return content
		? {
				scope: 'only',
				content,
				audience: extras.audience,
				time: extras.time,
			}
		: null;
}

/**
 * Whether two rules are the same rule.
 *
 * @param {Object} a Rule.
 * @param {Object} b Rule.
 * @return {boolean} True when they match.
 */
function same( a, b ) {
	return JSON.stringify( a ) === JSON.stringify( b );
}

/**
 * Whether a rule reads as "is none of".
 *
 * @param {Object} item Rule.
 * @return {boolean} True when turned around.
 */
function isTurnedAround( item ) {
	return (
		CONTENT_RULES.includes( item.rule ) &&
		[ 'is_not', 'not_contains' ].includes( item.operator )
	);
}

/**
 * Reads content rules into the picker's selection.
 *
 * @param {Array}   rules    Content rules.
 * @param {boolean} strict   Whether non-content rules make it custom.
 * @param {Array}   [groups] The groups the rules came from, for the term
 *                           rule's "apply to" pair.
 * @return {Object|null} Selection, or null when the rules do not fit.
 */
function readContent( rules, strict, groups ) {
	const content = emptyDraft().content;
	let ok = true;

	// A group of exactly a term rule and a page type rule is the "apply to"
	// choice, not two separate picks.
	const handled = new Set();
	( groups || [] ).forEach( ( group ) => {
		if ( group.length === 2 ) {
			const terms = group.find(
				( item ) => item.rule === 'taxonomy_term'
			);
			const pages = group.find( ( item ) => item.rule === 'page_type' );
			if ( terms && pages ) {
				const values = ( pages.value || [] ).map( String );
				const posts = values.join() === SINGULAR.join();
				const archives = values.join() === ARCHIVES.join();
				if ( posts || archives ) {
					content.termsApply = posts ? 'posts' : 'archives';
					content.terms = merge( content.terms, terms.value, Number );
					group.forEach( ( item ) => handled.add( item ) );
				}
			}
		} else if ( group.length > 1 ) {
			ok = false;
		}
	} );

	rules.forEach( ( item ) => {
		if ( handled.has( item ) ) {
			return;
		}
		switch ( item.rule ) {
			case 'post':
				content.posts = merge( content.posts, item.value, Number );
				break;
			case 'post_parent':
				content.children = merge(
					content.children,
					item.value,
					Number
				);
				break;
			case 'post_type':
				content.types = merge( content.types, item.value, String );
				break;
			case 'taxonomy_term':
				content.terms = merge( content.terms, item.value, Number );
				break;
			case 'page_type':
				content.special = merge( content.special, item.value, String );
				break;
			case 'url':
				( item.value || [] ).forEach( ( value ) => {
					content.urls.push( {
						operator: item.operator || 'contains',
						value: String( value ),
					} );
				} );
				break;
			default:
				ok = false;
		}
	} );

	if ( ! ok && strict ) {
		return null;
	}
	return ok ? content : null;
}

/**
 * Reads the visitor and time rules.
 *
 * @param {Array} rules Rules shared by every group.
 * @return {Object|null} { audience, time } or null when one is unknown.
 */
function readExtras( rules ) {
	const { audience, time } = emptyDraft();
	let ok = true;

	rules.forEach( ( item ) => {
		const values = Array.isArray( item.value )
			? item.value.map( String )
			: [ String( item.value ) ];
		switch ( item.rule ) {
			case 'logged_in':
				audience.loggedIn = values[ 0 ] || '';
				break;
			case 'user_role':
				audience.roles = values;
				break;
			case 'user_id':
				audience.users = values.map( Number );
				break;
			case 'device':
				audience.devices = values;
				break;
			case 'browser':
				audience.browsers = values;
				break;
			case 'os':
				audience.os = values;
				break;
			case 'language':
				audience.languages = values;
				break;
			case 'referrer':
				audience.referrer = {
					operator: item.operator || 'contains',
					values,
				};
				break;
			case 'query_param':
				audience.params.push( readPair( item ) );
				break;
			case 'cookie':
				audience.cookies.push( readPair( item ) );
				break;
			case 'post_meta':
				audience.postMeta.push( readPair( item ) );
				break;
			case 'user_meta':
				audience.userMeta.push( readPair( item ) );
				break;
			case 'php_function':
				audience.php = values[ 0 ] || '';
				audience.phpOperator = item.operator || 'returns_true';
				break;
			case 'wc_cart_total':
				audience.wc.total =
					item.operator === 'between'
						? {
								operator: 'between',
								from: item.value?.from ?? '',
								to: item.value?.to ?? '',
								amount: '',
							}
						: {
								operator: item.operator,
								from: '',
								to: '',
								amount: values[ 0 ] || '',
							};
				break;
			case 'wc_cart_contains':
				audience.wc.products = values.map( Number );
				break;
			case 'wc_cart_category':
				audience.wc.categories = values.map( Number );
				break;
			case 'wc_has_ordered':
				audience.wc.ordered = item.operator || 'is_true';
				break;
			case 'day_of_week':
				time.days = values;
				break;
			case 'time_of_day':
				if ( item.operator !== 'between' ) {
					ok = false;
					break;
				}
				time.from = item.value?.from || '';
				time.to = item.value?.to || '';
				break;
			default:
				ok = false;
		}
	} );

	return ok ? { audience, time } : null;
}

/**
 * Reads a key-and-value rule.
 *
 * @param {Object} item Rule.
 * @return {Object} { key, value, operator }.
 */
function readPair( item ) {
	return {
		key: item.value?.key || '',
		value: item.value?.value || '',
		operator: item.operator || 'equals',
	};
}

/**
 * Adds values to a list without repeating any.
 *
 * @param {Array}    list  Current values.
 * @param {Array}    added New values.
 * @param {Function} cast  Number or String.
 * @return {Array} The list.
 */
function merge( list, added, cast ) {
	const next = list.slice();
	( added || [] ).forEach( ( value ) => {
		const clean = cast( value );
		if ( ! next.includes( clean ) ) {
			next.push( clean );
		}
	} );
	return next;
}
