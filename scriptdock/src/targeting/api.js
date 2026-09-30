/**
 * REST calls the targeting wizard makes.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const BASE = '/scriptdock/v1/targeting';

export const getOptions = () => apiFetch( { path: `${ BASE }/options` } );

export const getContent = ( query ) =>
	apiFetch( { path: addQueryArgs( `${ BASE }/content`, query ) } );

export const getTerms = ( query ) =>
	apiFetch( { path: addQueryArgs( `${ BASE }/terms`, query ) } );

export const getUsers = ( query ) =>
	apiFetch( { path: addQueryArgs( `${ BASE }/users`, query ) } );

export const estimate = ( conditions ) =>
	apiFetch( {
		path: `${ BASE }/estimate`,
		method: 'POST',
		data: { conditions },
	} );

export const testUrl = ( url, rules ) =>
	apiFetch( {
		path: `${ BASE }/test-url`,
		method: 'POST',
		data: { url, rules },
	} );

export const describe = ( data ) =>
	apiFetch( {
		path: '/scriptdock/v1/snippets/describe',
		method: 'POST',
		data,
	} );
