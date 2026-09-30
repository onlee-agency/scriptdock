/**
 * The Library screen's REST calls.
 */
import apiFetch from '@wordpress/api-fetch';

export { errorMessage } from '../snippets/api';

const BASE = '/scriptdock/v1/library';

export const fetchLibrary = () => apiFetch( { path: BASE } );

/**
 * Adds a template to the site.
 *
 * @param {string} id   Template ID.
 * @param {Object} data { values, activate, consent }.
 * @return {Promise} { created, notice }.
 */
export const addTemplate = ( id, data ) =>
	apiFetch( { path: `${ BASE }/${ id }`, method: 'POST', data } );
