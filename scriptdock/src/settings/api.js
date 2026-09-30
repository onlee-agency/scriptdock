/**
 * The Settings screen's REST calls.
 */
import apiFetch from '@wordpress/api-fetch';

export { act, errorMessage } from '../snippets/api';

const BASE = '/scriptdock/v1/settings';

export const fetchSettings = () => apiFetch( { path: BASE } );

export const saveSettings = ( values ) =>
	apiFetch( { path: BASE, method: 'POST', data: { values } } );

export const newSafeLink = () =>
	apiFetch( { path: `${ BASE }/safe-link`, method: 'POST' } );

/**
 * Remembers that the safe mode link was kept, which ticks the Overview
 * checklist. Nothing depends on the answer.
 *
 * @return {Promise} The request.
 */
export const safeLinkKept = () =>
	apiFetch( {
		path: '/scriptdock/v1/preferences',
		method: 'POST',
		data: { safe_link_saved: true },
	} ).catch( () => {} );

/**
 * Approves every snippet waiting for review.
 *
 * @param {Array} ids Snippet IDs.
 * @return {Promise} Bulk result.
 */
export const approveAll = ( ids ) =>
	apiFetch( {
		path: '/scriptdock/v1/snippets/bulk',
		method: 'POST',
		data: { action: 'approve', ids },
	} );
