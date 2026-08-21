import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import { navigate } from '../router';

export default function YachtsList() {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );

	const load = () => {
		api.get( '/yachts', { per_page: 50 } )
			.then( ( res ) => setItems( res.items ) )
			.catch( ( err ) => setError( err.message ) );
	};

	useEffect( load, [] );

	const remove = ( id ) => {
		if ( ! window.confirm( __( 'Delete this yacht? This cannot be undone.', 'yacht-booking-system' ) ) ) {
			return;
		}

		api.del( `/yachts/${ id }` ).then( load );
	};

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>{ __( 'Yachts', 'yacht-booking-system' ) }</h2>
					<p>{ __( 'Manage your fleet.', 'yacht-booking-system' ) }</p>
				</div>
				<button className="ybs-btn is-primary" onClick={ () => navigate( 'yachts/new' ) }>
					{ __( '+ Add New Yacht', 'yacht-booking-system' ) }
				</button>
			</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }
			{ ! items && ! error && <div className="ybs-loading">{ __( 'Loading…', 'yacht-booking-system' ) }</div> }

			{ items && items.length === 0 && (
				<div className="ybs-empty-state">
					{ __( 'No yachts yet. Add your first yacht to get started.', 'yacht-booking-system' ) }
				</div>
			) }

			{ items && items.length > 0 && (
				<table className="ybs-table">
					<thead>
						<tr>
							<th></th>
							<th>{ __( 'Name', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Capacity', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Class', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Status', 'yacht-booking-system' ) }</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( yacht ) => (
							<tr key={ yacht.id }>
								<td>
									{ yacht.thumbnail && (
										<img src={ yacht.thumbnail } alt="" style={ { width: 48, height: 32, objectFit: 'cover', borderRadius: 4 } } />
									) }
								</td>
								<td>{ yacht.title }</td>
								<td>{ yacht.capacity }</td>
								<td>{ ( yacht.classes || [] ).join( ', ' ) }</td>
								<td>
									<span className={ 'ybs-badge status-' + yacht.status }>{ yacht.status }</span>
								</td>
								<td>
									<button className="ybs-btn" onClick={ () => navigate( `yachts/${ yacht.id }/edit` ) }>
										{ __( 'Edit', 'yacht-booking-system' ) }
									</button>{ ' ' }
									<button className="ybs-btn is-danger" onClick={ () => remove( yacht.id ) }>
										{ __( 'Delete', 'yacht-booking-system' ) }
									</button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
