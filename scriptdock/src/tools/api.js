/**
 * The Import & Export screen's REST calls.
 */
import apiFetch from '@wordpress/api-fetch';

export { errorMessage, exportSnippets, download } from '../snippets/api';

const BASE = '/scriptdock/v1/tools';

export const fetchTools = () => apiFetch( { path: BASE } );

export const migrate = ( source, activate ) =>
	apiFetch( {
		path: `${ BASE }/migrate`,
		method: 'POST',
		data: { source, activate },
	} );

export const importFile = ( json, activate ) =>
	apiFetch( {
		path: `${ BASE }/import`,
		method: 'POST',
		data: { json, activate },
	} );
