/**
 * The editor's calls to the scriptdock/v1 REST API. Shared calls (actions,
 * trash, export, preferences) come from the list's module.
 */
import apiFetch from '@wordpress/api-fetch';

export {
	act,
	download,
	errorMessage,
	exportSnippets,
	savePreferences,
	trash,
} from '../snippets/api';

const BASE = '/scriptdock/v1';

export const fetchSnippet = ( id ) =>
	apiFetch( { path: `${ BASE }/snippets/${ id }` } );

/**
 * Saves the draft: creates the snippet when it has no ID yet.
 *
 * @param {number} id    Snippet ID, or 0 for a new one.
 * @param {Object} draft Draft fields.
 * @return {Promise} { item, notice }.
 */
export const save = ( id, draft ) =>
	apiFetch(
		id
			? {
					path: `${ BASE }/snippets/${ id }`,
					method: 'PUT',
					data: draft,
				}
			: { path: `${ BASE }/snippets`, method: 'POST', data: draft }
	);

export const fetchRevisions = ( id ) =>
	apiFetch( { path: `${ BASE }/snippets/${ id }/revisions` } );

export const fetchRevision = ( id, revision ) =>
	apiFetch( { path: `${ BASE }/snippets/${ id }/revisions/${ revision }` } );

/**
 * Puts an older version back as the snippet's code.
 *
 * @param {number} id       Snippet ID.
 * @param {number} revision Revision ID.
 * @return {Promise} { item, notice }.
 */
export const restoreRevision = ( id, revision ) =>
	apiFetch( {
		path: `${ BASE }/snippets/${ id }/revisions/${ revision }`,
		method: 'POST',
	} );

/**
 * Checks PHP syntax on the server.
 *
 * @param {string} code Code.
 * @param {string} type php or universal.
 * @return {Promise<Object|null>} { line, message }, or null when it parses.
 */
export const lintPhp = ( code, type ) =>
	apiFetch( {
		path: `${ BASE }/snippets/lint`,
		method: 'POST',
		data: { code, type },
	} )
		.then( ( response ) => response.error || null )
		.catch( () => null );

/**
 * The plain-language targeting for placement and rules not saved yet.
 *
 * @param {Object} draft Draft (type, location, location_args, conditions,
 *                       schedule are read).
 * @return {Promise<Object>} Targeting.
 */
export const describe = ( draft ) =>
	apiFetch( {
		path: `${ BASE }/snippets/describe`,
		method: 'POST',
		data: {
			type: draft.type,
			location: draft.location,
			location_args: draft.location_args,
			conditions: draft.conditions,
			schedule: draft.schedule,
		},
	} ).then( ( response ) => response.targeting );

/**
 * The new editor's URL for a snippet. While the form-based editor is the
 * default, the new one opens with next=1.
 *
 * @param {string}  url  A snippet's edit URL.
 * @param {boolean} next Whether to add next=1.
 * @return {string} URL.
 */
