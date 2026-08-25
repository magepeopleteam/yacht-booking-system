import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
// Same slugs WooCommerce uses for orders - the two are kept in sync 1:1.
const STATUSES = [
	{ value: 'pending', label: __( 'Pending payment', 'yacht-booking-system' ) },
	{ value: 'processing', label: __( 'Processing', 'yacht-booking-system' ) },
	{ value: 'on-hold', label: __( 'On hold', 'yacht-booking-system' ) },
	{ value: 'completed', label: __( 'Completed', 'yacht-booking-system' ) },
	{ value: 'cancelled', label: __( 'Cancelled', 'yacht-booking-system' ) },
	{ value: 'refunded', label: __( 'Refunded', 'yacht-booking-system' ) },
	{ value: 'failed', label: __( 'Failed', 'yacht-booking-system' ) },
];

const TYPE_LABELS = {
	hourly: __( 'Hourly', 'yacht-booking-system' ),
	half_day: __( 'Half-Day', 'yacht-booking-system' ),
	morning_slot: __( 'Morning Slot', 'yacht-booking-system' ),
	evening_slot: __( 'Evening Slot', 'yacht-booking-system' ),
	daily: __( 'Full Day', 'yacht-booking-system' ),
	multiday: __( 'Multi-Day', 'yacht-booking-system' ),
};

function typeLabel( booking ) {
	const type = TYPE_LABELS[ booking.booking_type ] || booking.booking_type;

	if ( 'shared' === booking.booking_mode ) {
		return `${ type } · ${ __( 'Shared', 'yacht-booking-system' ) } (${ sprintf( __( '%d seats', 'yacht-booking-system' ), booking.guest_count ) })`;
	}

	return `${ type } · ${ __( 'Full Charter', 'yacht-booking-system' ) } (${ sprintf( __( '%d guests', 'yacht-booking-system' ), booking.guest_count ) })`;
}

export default function Bookings() {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ statusFilter, setStatusFilter ] = useState( '' );
	const [ upsellFor, setUpsellFor ] = useState( null );
	const [ deleting, setDeleting ] = useState( null );

	const load = () => {
		api.get( '/bookings', { per_page: 50, status: statusFilter || undefined } )
			.then( ( res ) => setItems( res.items ) )
			.catch( ( err ) => setError( err.message ) );
	};

	useEffect( load, [ statusFilter ] );

	const changeStatus = ( id, status ) => {
		api.post( `/bookings/${ id }/status`, { status } ).then( load );
	};

	const deleteBooking = ( id ) => {
		if ( ! window.confirm( __( 'Delete this booking permanently? This cannot be undone.', 'yacht-booking-system' ) ) ) {
			return;
		}

		setDeleting( id );

		api.del( `/bookings/${ id }` )
			.then( () => {
				setDeleting( null );
				load();
			} )
			.catch( ( err ) => {
				setDeleting( null );
				setError( err.message );
			} );
	};

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>{ __( 'Bookings', 'yacht-booking-system' ) }</h2>
					<p>{ __( 'All bookings across every yacht.', 'yacht-booking-system' ) }</p>
				</div>
				<select value={ statusFilter } onChange={ ( e ) => setStatusFilter( e.target.value ) }>
				<option value="">{ __( 'All Statuses', 'yacht-booking-system' ) }</option>
				{ STATUSES.map( ( s ) => <option key={ s.value } value={ s.value }>{ s.label }</option> ) }
			</select>
		</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }
			{ ! items && ! error && <div className="ybs-loading">{ __( 'Loading…', 'yacht-booking-system' ) }</div> }

			{ items && items.length === 0 && <div className="ybs-empty-state">{ __( 'No bookings found.', 'yacht-booking-system' ) }</div> }

			{ items && items.length > 0 && (
				<table className="ybs-table">
					<thead>
						<tr>
							<th>{ __( 'Yacht', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Guest', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Type', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Date', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Ends At', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Duration', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Total', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Order', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Status', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Actions', 'yacht-booking-system' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( booking ) => (
							<tr key={ booking.id } onClick={ () => setUpsellFor( booking.id ) } style={ { cursor: 'pointer' } }>
								<td>{ booking.yacht_name }</td>
								<td>{ booking.guest_name }<br /><small>{ booking.guest_email }</small></td>
								<td>{ typeLabel( booking ) }</td>
								<td>{ booking.start_formatted || booking.start_datetime }</td>
								<td>{ booking.end_formatted || booking.end_datetime || '—' }</td>
								<td>{ booking.duration || '—' }</td>
								<td>{ booking.currency }{ Number( booking.total_price ).toFixed( 2 ) }</td>
							<td onClick={ ( e ) => e.stopPropagation() }>
								{ booking.woo_order_id ? (
									<a href={ booking.woo_order_url } target="_blank" rel="noreferrer">
										{ __( 'Order #', 'yacht-booking-system' ) + booking.woo_order_id }
									</a>
								) : (
									<span>—</span>
								) }
							</td>
								<td onClick={ ( e ) => e.stopPropagation() }>
									<select
										className={ 'ybs-badge status-' + booking.status }
										value={ booking.status }
										onChange={ ( e ) => changeStatus( booking.id, e.target.value ) }
									>
										{ STATUSES.map( ( s ) => <option key={ s.value } value={ s.value }>{ s.label }</option> ) }
									</select>
								</td>
								<td onClick={ ( e ) => e.stopPropagation() }>
									<button
										type="button"
										className="ybs-btn is-danger"
										disabled={ deleting === booking.id }
										onClick={ () => deleteBooking( booking.id ) }
									>
										{ deleting === booking.id ? __( 'Deleting…', 'yacht-booking-system' ) : __( 'Delete', 'yacht-booking-system' ) }
									</button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ upsellFor && (
				<div className="ybs-notice is-info" style={ { marginTop: 16 } }>
					{ __( 'Upgrade to Pro to view full booking details.', 'yacht-booking-system' ) }{ ' ' }
					<button className="ybs-btn" onClick={ () => setUpsellFor( null ) }>{ __( 'Dismiss', 'yacht-booking-system' ) }</button>
				</div>
			) }
		</div>
	);
}
