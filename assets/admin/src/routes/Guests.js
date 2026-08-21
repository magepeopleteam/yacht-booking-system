import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';

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
					<p>{ __( 'View-only. A guest is removed automatically once their only booking is cancelled.', 'yacht-booking-system' ) }</p>
				</div>
			</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }
			{ ! data && ! error && <div className="ybs-loading">{ __( 'Loading…', 'yacht-booking-system' ) }</div> }

			{ data && data.items.length === 0 && <div className="ybs-empty-state">{ __( 'No guests yet.', 'yacht-booking-system' ) }</div> }

			{ data && data.items.length > 0 && (
				<table className="ybs-table">
					<thead>
						<tr>
							{ data.columns.map( ( col ) => <th key={ col.key }>{ col.label }</th> ) }
						</tr>
					</thead>
					<tbody>
						{ data.items.map( ( guest ) => (
							<tr key={ guest.id }>
								{ data.columns.map( ( col ) => <td key={ col.key }>{ guest[ col.key ] }</td> ) }
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
