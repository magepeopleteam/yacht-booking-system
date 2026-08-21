import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';

export default function Dashboard() {
	const [ stats, setStats ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		api.get( '/reports/summary' )
			.then( setStats )
			.catch( ( err ) => setError( err.message || __( 'Failed to load stats.', 'yacht-booking-system' ) ) );
	}, [] );

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>{ __( 'Dashboard', 'yacht-booking-system' ) }</h2>
					<p>{ __( 'A quick look at today and what is coming up.', 'yacht-booking-system' ) }</p>
				</div>
			</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }

			{ ! stats && ! error && <div className="ybs-loading">{ __( 'Loading…', 'yacht-booking-system' ) }</div> }

			{ stats && (
				<div className="ybs-stat-grid">
					<div className="ybs-stat-card">
						<div className="ybs-stat-card__label">{ __( "Today's Bookings", 'yacht-booking-system' ) }</div>
						<div className="ybs-stat-card__value">{ stats.today_bookings }</div>
					</div>
					<div className="ybs-stat-card">
						<div className="ybs-stat-card__label">{ __( 'Upcoming Bookings', 'yacht-booking-system' ) }</div>
						<div className="ybs-stat-card__value">{ stats.upcoming_bookings }</div>
					</div>
					<div className="ybs-stat-card">
						<div className="ybs-stat-card__label">{ __( 'Cancelled', 'yacht-booking-system' ) }</div>
						<div className="ybs-stat-card__value">{ stats.cancelled_bookings }</div>
					</div>
					<div className="ybs-stat-card">
						<div className="ybs-stat-card__label">{ __( 'Active Yachts', 'yacht-booking-system' ) }</div>
						<div className="ybs-stat-card__value">{ stats.active_yachts }</div>
					</div>
				</div>
			) }
		</div>
	);
}
