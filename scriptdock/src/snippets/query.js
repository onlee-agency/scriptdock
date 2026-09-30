/**
 * The list's query: which snippets to show, and how it maps to the page URL
 * so a filtered view can be bookmarked and shared.
 */
import { addQueryArgs } from '@wordpress/url';

export const DEFAULT_QUERY = {
	view: 'all',
	search: '',
	type: [],
	location: [],
	tag: [],
	targeting: [],
	month: '',
	orderby: 'modified',
	order: '',
	page: 1,
};

const LISTS = [ 'type', 'location', 'tag', 'targeting' ];

/**
 * The query the page opened with.
 *
 * @param {Object} from Query from the server, in REST terms.
 * @return {Object} Query.
 */
export function initialQuery( from = {} ) {
	const query = { ...DEFAULT_QUERY };
	[ 'view', 'search', 'month', 'orderby', 'order' ].forEach( ( key ) => {
		if ( from[ key ] ) {
			query[ key ] = String( from[ key ] );
		}
	} );
	LISTS.forEach( ( key ) => {
		if ( Array.isArray( from[ key ] ) ) {
			query[ key ] =
				key === 'tag' ? from[ key ].map( Number ) : [ ...from[ key ] ];
		}
	} );
	if ( from.page ) {
		query.page = Math.max( 1, Number( from.page ) || 1 );
	}
	return query;
}

/**
 * How many filters are on (not counting the search).
 *
 * @param {Object} query Query.
 * @return {number} Count.
 */
export function filterCount( query ) {
	return (
		LISTS.reduce( ( count, key ) => count + query[ key ].length, 0 ) +
		( query.month ? 1 : 0 )
	);
}

/**
 * Whether a search or a filter narrows the list.
 *
 * @param {Object} query Query.
 * @return {boolean} Whether it does.
 */
export function isNarrowed( query ) {
	return !! query.search || filterCount( query ) > 0;
}

/**
 * The query with every filter and the search cleared; view and sort stay.
 *
 * @param {Object} query Query.
 * @return {Object} Query.
 */
export function withoutFilters( query ) {
	return {
		...query,
		search: '',
		type: [],
		location: [],
		tag: [],
		targeting: [],
		month: '',
		page: 1,
	};
}

/**
 * The page URL for a query.
 *
 * @param {string} base  The Snippets screen URL.
 * @param {Object} query Query.
 * @return {string} URL.
 */
export function queryUrl( base, query ) {
	const args = {};
	if ( query.view !== 'all' ) {
		args.view = query.view;
	}
	if ( query.search ) {
		args.s = query.search;
	}
	LISTS.forEach( ( key ) => {
		if ( query[ key ].length ) {
			args[ key ] = query[ key ].join( ',' );
		}
	} );
	if ( query.month ) {
		args.month = query.month;
	}
	if ( query.orderby !== 'modified' ) {
		args.orderby = query.orderby;
	}
	if ( query.order ) {
		args.order = query.order;
	}
	if ( query.page > 1 ) {
		args.paged = query.page;
	}
	return addQueryArgs( base, args );
}
