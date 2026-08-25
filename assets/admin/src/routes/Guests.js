import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';

// Attendee-list style: one flat row per booking with every detail on it.
const TYPE_LABELS = {
	hourly: __( 'Hourly', 'yacht-booking-system' ),
	half_day: __( 'Half-Day', 'yacht-booking-system' ),
	morning_slot: __( 'Morning Slot', 'yacht-booking-system' ),
	evening_slot: __( 'Evening Slot', 'yacht-booking-system' ),
	daily: __( 'Full Day', 'yacht-booking-system' ),
	multiday: __( 'Multi-Day', 'yacht-booking-system' ),
};

const STATUS_LABELS = {
	pending: __( 'Pending payment', 'yacht-booking-system' ),
	processing: __( 'Processing', 'yacht-booking-system' ),
	'on-hold': __( 'On hold', 'yacht-booking-system' ),
	completed: __( 'Completed', 'yacht-booking-system' ),
	cancelled: __( 'Cancelled', 'yacht-booking-system' ),
	refunded: __( 'Refunded', 'yacht-booking-system' ),
	failed: __( 'Failed', 'yacht-booking-system' ),
};

function formatDateTime( value ) {
	return value || '—';
}

export default function Guests() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		api.get( '/guests', { per_page: 50 } )
			.then( setData )
			.catch( ( err ) => setError( err.message ) );
	}, [] );

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>{ __( 'Guests', 'yacht-booking-system' ) }</h2>
					<p>{ __( 'Guest bookings appear here once their order reaches Processing or Completed.', 'yacht-booking-system' ) }</p>
				</div>
			</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }
			{ ! data && ! error && <div className="ybs-loading">{ __( 'Loading…', 'yacht-booking-system' ) }</div> }

			{ data && data.items.length === 0 && <div className="ybs-empty-state">{ __( 'No guests found.', 'yacht-booking-system' ) }</div> }

			{ data && data.items.length > 0 && (
				<table className="ybs-table">
					<thead>
						<tr>
							<th>{ __( 'SI.', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Order No', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Yacht', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Booking', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Full Name', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Email', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Phone', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Charter Datetime', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Total', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Order Status', 'yacht-booking-system' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ data.items.map( ( row, index ) => (
							<tr key={ row.id }>
								<td>{ index + 1 }</td>
								<td>
									{ row.order_id ? (
										<a href={ row.order_url } target="_blank" rel="noreferrer">
											#{ row.order_id }
										</a>
									) : (
										<span>—</span>
									) }
								</td>
								<td><strong>{ row.yacht_name || '—' }</strong></td>
								<td>
									{ TYPE_LABELS[ row.booking_type ] || row.booking_type }
									{ 'shared' === row.booking_mode && (
										<small> · { sprintf( __( '%d seats', 'yacht-booking-system' ), row.guest_count ) }</small>
									) }
									{ 'full' === row.booking_mode && (
										<small> · { sprintf( __( '%d guests', 'yacht-booking-system' ), row.guest_count ) }</small>
									) }
								</td>
								<td>{ row.name || '—' }</td>
								<td>{ row.email || '—' }</td>
								<td>{ row.phone || '—' }</td>
								<td>{ formatDateTime( row.start_formatted || row.start_datetime ) }</td>
								<td>{ row.currency }{ Number( row.total_price ).toFixed( 2 ) }</td>
								<td>
									<span className={ 'ybs-badge status-' + row.status }>
										{ STATUS_LABELS[ row.status ] || row.status }
									</span>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ data && (
				<p style={ { marginTop: 12, color: '#64748b' } }>
					{ sprintf(
						/* translators: %d: total number of guest bookings */
						__( '%d guest booking(s) found.', 'yacht-booking-system' ),
						data.total
					) }
				</p>
			) }
		</div>
	);
}
