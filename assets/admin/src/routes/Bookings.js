import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';

const STATUSES = [ 'pending', 'confirmed', 'paid', 'completed', 'cancelled', 'no_show' ];

export default function Bookings() {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ statusFilter, setStatusFilter ] = useState( '' );
	const [ upsellFor, setUpsellFor ] = useState( null );

	const load = () => {
		api.get( '/bookings', { per_page: 50, status: statusFilter || undefined } )
			.then( ( res ) => setItems( res.items ) )
			.catch( ( err ) => setError( err.message ) );
	};

	useEffect( load, [ statusFilter ] );

	const changeStatus = ( id, status ) => {
		api.post( `/bookings/${ id }/status`, { status } ).then( load );
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
					{ STATUSES.map( ( s ) => <option key={ s } value={ s }>{ s }</option> ) }
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
							<th>{ __( 'Total', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Status', 'yacht-booking-system' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( booking ) => (
							<tr key={ booking.id } onClick={ () => setUpsellFor( booking.id ) } style={ { cursor: 'pointer' } }>
								<td>{ booking.yacht_name }</td>
								<td>{ booking.guest_name }<br /><small>{ booking.guest_email }</small></td>
								<td>{ booking.booking_type }</td>
								<td>{ booking.start_datetime }</td>
								<td>{ booking.currency }{ Number( booking.total_price ).toFixed( 2 ) }</td>
								<td onClick={ ( e ) => e.stopPropagation() }>
									<select
										className={ 'ybs-badge status-' + booking.status }
										value={ booking.status }
										onChange={ ( e ) => changeStatus( booking.id, e.target.value ) }
									>
										{ STATUSES.map( ( s ) => <option key={ s } value={ s }>{ s }</option> ) }
									</select>
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
