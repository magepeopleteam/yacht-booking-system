/** Build REST URLs for both pretty and plain WordPress permalinks. */
export function restUrl( path, params ) {
	const url = new URL( window.mageyaboFrontendConfig.restRoot, window.location.href );
	const endpoint = path.replace( /^\/+/, '' );
	if ( url.searchParams.has( 'rest_route' ) ) {
		const route = url.searchParams.get( 'rest_route' ).replace( /\/?$/, '/' );
		url.searchParams.set( 'rest_route', route + endpoint );
	} else {
		url.pathname = url.pathname.replace( /\/?$/, '/' ) + endpoint;
	}
	new URLSearchParams( params ).forEach( ( value, key ) => url.searchParams.set( key, value ) );
	return url.toString();
}
