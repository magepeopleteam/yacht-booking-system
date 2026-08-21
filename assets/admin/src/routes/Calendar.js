import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';

function monthBounds( year, month ) {
	const first = new Date( year, month, 1 );
	const last = new Date( year, month + 1, 0 );
	const fmt = ( d ) => d.toISOString().slice( 0, 10 );
	return { from: fmt( first ), to: fmt( last ), daysInMonth: last.getDate(), startWeekday: first.getDay() };
}

export default function Calendar() {
	const today = new Date();
	const [ year, setYear ] = useState( today.getFullYear() );
	const [ month, setMonth ] = useState( today.getMonth() );
	const [ bookings, setBookings ] = useState( [] );
	const [ error, setError ] = useState( '' );

	const bounds = monthBounds( year, month );

	useEffect( () => {
		api.get( '/bookings', { per_page: 100, date_from: bounds.from, date_to: bounds.to } )
			.then( ( res ) => setBookings( res.items ) )
			.catch( ( err ) => setError( err.message ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ year, month ] );

	const byDay = {};
	bookings.forEach( ( booking ) => {
		const day = booking.start_datetime.slice( 0, 10 );
		byDay[ day ] = byDay[ day ] || [];
		byDay[ day ].push( booking );
	} );

	const cells = [];
	for ( let i = 0; i < bounds.startWeekday; i++ ) {
		cells.push( <div key={ 'blank-' + i } /> );
	}
	for ( let day = 1; day <= bounds.daysInMonth; day++ ) {
		const dateStr = `${ year }-${ String( month + 1 ).padStart( 2, '0' ) }-${ String( day ).padStart( 2, '0' ) }`;
		const dayBookings = byDay[ dateStr ] || [];

		cells.push(
			<div key={ dateStr } className="ybs-card" style={ { padding: 8, minHeight: 90 } }>
				<strong>{ day }</strong>
				{ dayBookings.slice( 0, 3 ).map( ( booking ) => (
					<div key={ booking.id } className={ 'ybs-badge status-' + booking.status } style={ { display: 'block', marginTop: 4, textAlign: 'left' } }>
						{ booking.yacht_name }
					</div>
				) ) }
				{ dayBookings.length > 3 && <div className="ybs-hint">+{ dayBookings.length - 3 } more</div> }
			</div>
		);
	}

	return (
		<div>
			<div className="ybs-page-header">
				<h2>{ __( 'Calendar', 'yacht-booking-system' ) }</h2>
				<div>
					<button className="ybs-btn" onClick={ () => setMonth( ( m ) => ( m === 0 ? ( setYear( year - 1 ), 11 ) : m - 1 ) ) }>←</button>{ ' ' }
					<strong>{ new Date( year, month, 1 ).toLocaleString( undefined, { month: 'long', year: 'numeric' } ) }</strong>{ ' ' }
					<button className="ybs-btn" onClick={ () => setMonth( ( m ) => ( m === 11 ? ( setYear( year + 1 ), 0 ) : m + 1 ) ) }>→</button>
				</div>
			</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }

			<div style={ { display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 8 } }>
				{ cells }
			</div>
		</div>
	);
}
