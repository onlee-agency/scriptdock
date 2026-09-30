/**
 * The Site Files screen's REST calls.
 */
import apiFetch from '@wordpress/api-fetch';

export { errorMessage } from '../snippets/api';

const BASE = '/scriptdock/v1/files';

export const fetchFiles = () => apiFetch( { path: BASE } );

export const saveFiles = ( data ) =>
	apiFetch( { path: BASE, method: 'POST', data } );
