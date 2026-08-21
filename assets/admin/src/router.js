import { useState, useEffect } from '@wordpress/element';

/**
 * A minimal hash router. No precedent for react-router (or any router)
 * exists anywhere in this codebase, and pulling in a routing library for six
 * screens is not worth the dependency - hash changes plus a listener is the
 * whole feature.
 */
function currentSegments() {
	const hash = window.location.hash.replace( /^#\/?/, '' );
	return hash ? hash.split( '/' ).filter( Boolean ) : [ 'dashboard' ];
}

export function useHashRoute() {
	const [ segments, setSegments ] = useState( currentSegments() );

	useEffect( () => {
		const onHashChange = () => setSegments( currentSegments() );
		window.addEventListener( 'hashchange', onHashChange );
		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [] );

	return segments;
}

export function navigate( path ) {
	window.location.hash = '#/' + path.replace( /^\/+/, '' );
}
