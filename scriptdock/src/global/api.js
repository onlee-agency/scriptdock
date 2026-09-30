/**
 * The Header & Footer screen's REST calls.
 */
import apiFetch from '@wordpress/api-fetch';

export { errorMessage } from '../snippets/api';

const BASE = '/scriptdock/v1/global';

export const fetchGlobal = () => apiFetch( { path: BASE } );

export const saveGlobal = ( data ) =>
	apiFetch( { path: BASE, method: 'POST', data } );
