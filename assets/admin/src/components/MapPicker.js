import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

const DEFAULT_CENTER = [ 25.276987, 55.296249 ];
const MIN_QUERY_LENGTH = 3;
const DEBOUNCE_MS = 400;

/**
 * `onChange( lat, lng, label )` - `label` is only passed when the position
 * came from a location search selection, so the caller can fill the marina
 * name field without overwriting it on every manual pin drag.
 */
export default function MapPicker( { lat, lng, onChange } ) {
	const containerRef = useRef();
	const mapRef = useRef();
	const markerRef = useRef();
	const skipNextSearch = useRef( false );
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ searching, setSearching ] = useState( false );

	useEffect( () => {
		if ( ! window.L || ! containerRef.current || mapRef.current ) {
			return;
		}

		const center = lat && lng ? [ parseFloat( lat ), parseFloat( lng ) ] : DEFAULT_CENTER;
		const map = window.L.map( containerRef.current ).setView( center, lat && lng ? 13 : 4 );

		window.L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
		} ).addTo( map );

		const marker = window.L.marker( center, { draggable: true } ).addTo( map );

		marker.on( 'dragend', () => {
			const position = marker.getLatLng();
			onChange( position.lat, position.lng );
		} );

		map.on( 'click', ( event ) => {
			marker.setLatLng( event.latlng );
			onChange( event.latlng.lat, event.latlng.lng );
		} );

		mapRef.current = map;
		markerRef.current = marker;

		return () => {
			map.remove();
			mapRef.current = null;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	useEffect( () => {
		if ( mapRef.current && markerRef.current && lat && lng ) {
			const position = [ parseFloat( lat ), parseFloat( lng ) ];
			markerRef.current.setLatLng( position );
			mapRef.current.setView( position, mapRef.current.getZoom() );
		}
	}, [ lat, lng ] );

	// Live auto-suggest: search as the admin types, debounced so every
	// keystroke doesn't fire its own request against Nominatim.
	useEffect( () => {
		if ( skipNextSearch.current ) {
			skipNextSearch.current = false;
			return;
		}

		if ( query.trim().length < MIN_QUERY_LENGTH ) {
			setResults( [] );
			setSearching( false );
			return;
		}

		let cancelled = false;
		setSearching( true );

		const timer = setTimeout( () => {
			fetch( `https://nominatim.openstreetmap.org/search?format=json&limit=6&q=${ encodeURIComponent( query ) }` )
				.then( ( res ) => res.json() )
				.then( ( data ) => {
					if ( ! cancelled ) {
						setResults( data );
						setSearching( false );
					}
				} )
				.catch( () => {
					if ( ! cancelled ) {
						setSearching( false );
					}
				} );
		}, DEBOUNCE_MS );

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ query ] );

	const selectResult = ( result ) => {
		const resultLat = parseFloat( result.lat );
		const resultLng = parseFloat( result.lon );

		if ( mapRef.current && markerRef.current ) {
			mapRef.current.setView( [ resultLat, resultLng ], 15 );
			markerRef.current.setLatLng( [ resultLat, resultLng ] );
		}

		onChange( resultLat, resultLng, result.display_name );
		setResults( [] );
		skipNextSearch.current = true;
		setQuery( result.display_name );
	};

	return (
		<div>
			<div className="ybs-map-search">
				<input
					type="text"
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
					placeholder={ __( 'Search for a marina, pier, or address…', 'yacht-booking-system' ) }
				/>
				{ searching && <span className="ybs-map-search__spinner" aria-hidden="true" /> }
			</div>

			{ results.length > 0 && (
				<ul className="ybs-map-search__results">
					{ results.map( ( result ) => (
						<li key={ result.place_id }>
							<button type="button" onClick={ () => selectResult( result ) }>
								{ result.display_name }
							</button>
						</li>
					) ) }
				</ul>
			) }

			<div ref={ containerRef } style={ { height: 280, borderRadius: 8, margin: '8px 0' } } />
			<p className="ybs-hint">
				{ __( 'Start typing to search, or click/drag the marker to set the exact departure point.', 'yacht-booking-system' ) }
			</p>
		</div>
	);
}
