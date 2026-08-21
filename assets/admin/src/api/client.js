import apiFetch from '@wordpress/api-fetch';

const NAMESPACE = '/ybs/v1';

function withQuery( path, params ) {
	if ( ! params || ! Object.keys( params ).length ) {
		return path;
	}

	const query = new URLSearchParams();

	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			query.set( key, value );
		}
	} );

	const qs = query.toString();

	return qs ? `${ path }?${ qs }` : path;
}

export const api = {
	get: ( path, params ) => apiFetch( { path: withQuery( NAMESPACE + path, params ) } ),
	post: ( path, data ) => apiFetch( { path: NAMESPACE + path, method: 'POST', data } ),
	put: ( path, data ) => apiFetch( { path: NAMESPACE + path, method: 'PUT', data } ),
	del: ( path ) => apiFetch( { path: NAMESPACE + path, method: 'DELETE' } ),
};
