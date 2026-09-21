import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import { toast } from '../components/Toast';

const BLANK = { name: '', description: '', price: 0, active: true };

/**
 * The add-on catalogue: the optional extras (catering, skipper, water toys)
 * that can be sold with a charter. Which yachts offer which is set per yacht
 * in the wizard's Pricing step, not here - this screen owns the item and its
 * price, one place, so changing a price does not mean editing every yacht.
 */
export default function Addons() {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ draft, setDraft ] = useState( BLANK );
	const [ editingId, setEditingId ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const currency = ( window.mageyaboAdminConfig && window.mageyaboAdminConfig.currency ) || '$';

	const load = () =>
		api.get( '/addons' )
			.then( setItems )
			.catch( ( err ) => setError( err.message ) );

	useEffect( () => {
		load();
	}, [] );

	const reset = () => {
		setDraft( BLANK );
		setEditingId( null );
	};

	const save = () => {
		if ( ! draft.name.trim() ) {
			toast( __( 'Please give the add-on a name.', 'magepeople-yacht-booking-system' ), 'error' );
			return;
		}

		setBusy( true );

		const request = editingId ? api.put( `/addons/${ editingId }`, draft ) : api.post( '/addons', draft );

		request
			.then( () => {
				reset();
				load();
				toast( __( 'Add-on saved.', 'magepeople-yacht-booking-system' ), 'success' );
			} )
			.catch( ( err ) => toast( err.message, 'error' ) )
			.finally( () => setBusy( false ) );
	};

	const edit = ( addon ) => {
		setEditingId( addon.id );
		setDraft( {
			name: addon.name,
			description: addon.description,
			price: addon.price,
			active: addon.active,
		} );
	};

	const remove = ( addon ) => {
		if ( ! window.confirm( __( 'Delete this add-on? Bookings that already include it keep what they were charged.', 'magepeople-yacht-booking-system' ) ) ) {
			return;
		}

		api.del( `/addons/${ addon.id }` )
			.then( () => {
				if ( editingId === addon.id ) {
					reset();
				}

				load();
			} )
			.catch( ( err ) => toast( err.message, 'error' ) );
	};

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>{ __( 'Add-ons', 'magepeople-yacht-booking-system' ) }</h2>
					<p>{ __( 'Optional extras guests can add to a charter. Assign them to a yacht in that yacht’s Pricing step.', 'magepeople-yacht-booking-system' ) }</p>
				</div>
			</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }
			{ ! items && ! error && <div className="ybs-loading">{ __( 'Loading…', 'magepeople-yacht-booking-system' ) }</div> }

			{ items && (
				<>
					<div className="ybs-card">
						<h3>{ editingId ? __( 'Edit add-on', 'magepeople-yacht-booking-system' ) : __( 'New add-on', 'magepeople-yacht-booking-system' ) }</h3>

						<div className="ybs-field-row">
							<div className="ybs-field">
								<label>{ __( 'Name', 'magepeople-yacht-booking-system' ) }</label>
								<input type="text" value={ draft.name } onChange={ ( e ) => setDraft( { ...draft, name: e.target.value } ) } />
							</div>
							<div className="ybs-field">
								<label>{ __( 'Price', 'magepeople-yacht-booking-system' ) }</label>
								<input type="number" min="0" step="0.01" value={ draft.price } onChange={ ( e ) => setDraft( { ...draft, price: e.target.value } ) } />
							</div>
						</div>

						<div className="ybs-field">
							<label>{ __( 'Description', 'magepeople-yacht-booking-system' ) }</label>
							<textarea rows={ 2 } value={ draft.description } onChange={ ( e ) => setDraft( { ...draft, description: e.target.value } ) } />
							<p className="ybs-hint">{ __( 'Shown under the add-on on the booking form.', 'magepeople-yacht-booking-system' ) }</p>
						</div>

						<div className="ybs-field">
							<label>
								<input type="checkbox" checked={ !! draft.active } onChange={ ( e ) => setDraft( { ...draft, active: e.target.checked } ) } />
								{ ' ' }
								{ __( 'Available for booking', 'magepeople-yacht-booking-system' ) }
							</label>
						</div>

						<button type="button" className="ybs-btn is-primary" disabled={ busy } onClick={ save }>
							{ editingId ? __( 'Save changes', 'magepeople-yacht-booking-system' ) : __( '+ Add add-on', 'magepeople-yacht-booking-system' ) }
						</button>
						{ editingId && (
							<>
								{ ' ' }
								<button type="button" className="ybs-btn" onClick={ reset }>
									{ __( 'Cancel', 'magepeople-yacht-booking-system' ) }
								</button>
							</>
						) }
					</div>

					{ items.length === 0 ? (
						<div className="ybs-empty-state">{ __( 'No add-ons yet.', 'magepeople-yacht-booking-system' ) }</div>
					) : (
						<table className="ybs-table">
							<thead>
								<tr>
									<th>{ __( 'Name', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Description', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Price', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Status', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Actions', 'magepeople-yacht-booking-system' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( addon ) => (
									<tr key={ addon.id }>
										<td>{ addon.name }</td>
										<td>{ addon.description || '—' }</td>
										<td>{ currency }{ Number( addon.price ).toFixed( 2 ) }</td>
										<td>
											<span className={ 'ybs-badge status-' + ( addon.active ? 'completed' : 'cancelled' ) }>
												{ addon.active ? __( 'Active', 'magepeople-yacht-booking-system' ) : __( 'Inactive', 'magepeople-yacht-booking-system' ) }
											</span>
										</td>
										<td>
											<button type="button" className="ybs-btn" onClick={ () => edit( addon ) }>
												{ __( 'Edit', 'magepeople-yacht-booking-system' ) }
											</button>
											{ ' ' }
											<button type="button" className="ybs-btn is-danger" onClick={ () => remove( addon ) }>
												{ __( 'Delete', 'magepeople-yacht-booking-system' ) }
											</button>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</>
			) }
		</div>
	);
}
