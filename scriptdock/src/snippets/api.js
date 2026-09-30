/**
 * The Snippets screen's calls to the scriptdock/v1 REST API.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

const BASE = '/scriptdock/v1';

/**
 * List parameters for a query: lists as comma lists, defaults left out.
 *
 * @param {Object} query   Query.
 * @param {number} perPage Snippets per page.
 * @return {Object} Parameters.
 */
export function listParams( query, perPage ) {
	const params = {
		view: query.view,
		page: query.page,
		per_page: perPage,
	};
	if ( query.search ) {
		params.search = query.search;
	}
	[ 'type', 'location', 'tag', 'targeting' ].forEach( ( key ) => {
		if ( query[ key ].length ) {
			params[ key ] = query[ key ].join( ',' );
		}
	} );
	if ( query.month ) {
		params.month = query.month;
	}
	if ( query.orderby !== 'modified' ) {
		params.orderby = query.orderby;
	}
	if ( query.order ) {
		params.order = query.order;
	}
	return params;
}

export const fetchList = ( query, perPage, signal ) =>
	apiFetch( {
		path: addQueryArgs(
			`${ BASE }/snippets`,
			listParams( query, perPage )
		),
		signal,
	} );

export const fetchSnippet = ( id ) =>
	apiFetch( { path: `${ BASE }/snippets/${ id }` } );

export const fetchChanges = ( id ) =>
	apiFetch( { path: `${ BASE }/snippets/${ id }/changes` } );

/**
 * A one-snippet action: activate, deactivate, approve, duplicate, untrash.
 *
 * @param {number} id     Snippet ID.
 * @param {string} action Action.
 * @return {Promise} The response: { item, counts }.
 */
export const act = ( id, action ) =>
	apiFetch( {
		path: `${ BASE }/snippets/${ id }/${ action }`,
		method: 'POST',
	} );

export const trash = ( id ) =>
	apiFetch( { path: `${ BASE }/snippets/${ id }`, method: 'DELETE' } );

export const deleteForGood = ( id ) =>
	apiFetch( {
		path: addQueryArgs( `${ BASE }/snippets/${ id }`, { force: true } ),
		method: 'DELETE',
	} );

export const emptyTrash = () =>
	apiFetch( { path: `${ BASE }/snippets/trash`, method: 'DELETE' } );

export const bulk = ( action, ids, extra = {} ) =>
	apiFetch( {
		path: `${ BASE }/snippets/bulk`,
		method: 'POST',
		data: { action, ids, ...extra },
	} );

export const exportSnippets = ( ids ) =>
	apiFetch( {
		path: addQueryArgs( `${ BASE }/snippets/export`, {
			ids: ids.join( ',' ),
		} ),
	} );

export const tagsApi = {
	list: () => apiFetch( { path: `${ BASE }/tags` } ),
	create: ( name ) =>
		apiFetch( { path: `${ BASE }/tags`, method: 'POST', data: { name } } ),
	rename: ( id, name ) =>
		apiFetch( {
			path: `${ BASE }/tags/${ id }`,
			method: 'PUT',
			data: { name },
		} ),
	remove: ( id ) =>
		apiFetch( { path: `${ BASE }/tags/${ id }`, method: 'DELETE' } ),
	merge: ( id, into ) =>
		apiFetch( {
			path: `${ BASE }/tags/${ id }/merge`,
			method: 'POST',
			data: { into },
		} ),
};

export const savePreferences = ( data ) =>
	apiFetch( { path: `${ BASE }/preferences`, method: 'POST', data } );

/**
 * Saves an export as a JSON file.
 *
 * @param {string} filename File name.
 * @param {Object} data     Export payload.
 */
export function download( filename, data ) {
	const blob = new window.Blob( [ JSON.stringify( data, null, 2 ) ], {
		type: 'application/json',
	} );
	const url = window.URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.setTimeout( () => window.URL.revokeObjectURL( url ), 1000 );
}

/**
 * A readable message for a failed call.
 *
 * @param {Object} error Error from apiFetch.
 * @return {string} Message.
 */
export function errorMessage( error ) {
	if ( error && error.message ) {
		return error.message;
	}
	return __(
		'Something went wrong. Check your connection and try again.',
		'scriptdock'
	);
}

/**
 * Whether a failed call was cancelled on purpose (a newer request took
 * over).
 *
 * @param {Object} error Error.
 * @return {boolean} Whether it was an abort.
 */
export function wasAborted( error ) {
	// apiFetch passes AbortError through unchanged.
	return !! error && error.name === 'AbortError';
}
