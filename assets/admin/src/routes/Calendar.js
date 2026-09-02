import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';

const TYPE_LABELS = {
	hourly: __( 'Hourly', 'magepeople-yacht-booking-system' ),
	half_day: __( 'Half-Day', 'magepeople-yacht-booking-system' ),
	morning_slot: __( 'Morning Slot', 'magepeople-yacht-booking-system' ),
	evening_slot: __( 'Evening Slot', 'magepeople-yacht-booking-system' ),
	daily: __( 'Full Day', 'magepeople-yacht-booking-system' ),
	multiday: __( 'Multi-Day', 'magepeople-yacht-booking-system' ),
};

function typeLabel( booking ) {
	const type = TYPE_LABELS[ booking.booking_type ] || booking.booking_type;
	const mode = 'shared' === booking.booking_mode
		? /* translators: %d: number of seats booked. */ sprintf( __( 'Shared (%d seats)', 'magepeople-yacht-booking-system' ), booking.guest_count )
		: /* translators: %d: number of guests. */ sprintf( __( 'Full Charter (%d guests)', 'magepeople-yacht-booking-system' ), booking.guest_count );

	return `${ type } · ${ mode }`;
}

function BookingItem( { booking } ) {
	return (
		<div className="ybs-cal-item" tabIndex={ 0 }>
			<div className={ 'ybs-badge status-' + booking.status } style={ { display: 'block', marginTop: 4, textAlign: 'left' } }>
				{ booking.yacht_name }
			</div>

			<div className="ybs-cal-tooltip">
				<strong>{ booking.yacht_name }</strong>
				<div className="ybs-cal-tooltip__row"><span>{ __( 'Guest', 'magepeople-yacht-booking-system' ) }</span><span>{ booking.guest_name || '—' }</span></div>
				<div className="ybs-cal-tooltip__row"><span>{ __( 'Email', 'magepeople-yacht-booking-system' ) }</span><span>{ booking.guest_email || '—' }</span></div>
				<div className="ybs-cal-tooltip__row"><span>{ __( 'Phone', 'magepeople-yacht-booking-system' ) }</span><span>{ booking.guest_phone || '—' }</span></div>
				<div className="ybs-cal-tooltip__row"><span>{ __( 'Time', 'magepeople-yacht-booking-system' ) }</span><span>{ booking.start_formatted } – { booking.end_formatted }</span></div>
				<div className="ybs-cal-tooltip__row"><span>{ __( 'Type', 'magepeople-yacht-booking-system' ) }</span><span>{ typeLabel( booking ) }</span></div>
				<div className="ybs-cal-tooltip__row"><span>{ __( 'Duration', 'magepeople-yacht-booking-system' ) }</span><span>{ booking.duration || '—' }</span></div>
				<div className="ybs-cal-tooltip__row"><span>{ __( 'Total', 'magepeople-yacht-booking-system' ) }</span><span>{ booking.currency }{ Number( booking.total_price ).toFixed( 2 ) }</span></div>
				<div className="ybs-cal-tooltip__row"><span>{ __( 'Status', 'magepeople-yacht-booking-system' ) }</span><span className={ 'ybs-badge status-' + booking.status }>{ booking.status }</span></div>
			</div>
		</div>
	);
}

function monthBounds( year, month ) {
	const last = new Date( year, month + 1, 0 );
	// Build the date string from local date parts rather than
	// `Date#toISOString()`, which converts to UTC first and silently shifts
	// the range back a day in any timezone ahead of UTC - cutting off
	// bookings near the end of the month.
	const fmt = ( y, m, d ) => `${ y }-${ String( m + 1 ).padStart( 2, '0' ) }-${ String( d ).padStart( 2, '0' ) }`;
	return { from: fmt( year, month, 1 ), to: fmt( year, month, last.getDate() ), daysInMonth: last.getDate(), startWeekday: new Date( year, month, 1 ).getDay() };
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
			<div key={ dateStr } className="ybs-card" style={ { padding: 8, minHeight: 90, position: 'relative' } }>
				<strong>{ day }</strong>
				{ dayBookings.slice( 0, 3 ).map( ( booking ) => <BookingItem key={ booking.id } booking={ booking } /> ) }
				{ dayBookings.length > 3 && <div className="ybs-hint">+{ dayBookings.length - 3 } more</div> }
			</div>
		);
	}

	return (
		<div>
			<div className="ybs-page-header">
				<h2>{ __( 'Calendar', 'magepeople-yacht-booking-system' ) }</h2>
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
