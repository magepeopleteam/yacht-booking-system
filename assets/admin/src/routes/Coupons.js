import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import { toast } from '../components/Toast';
import DateField from '../components/DateField';

const BLANK = {
	code: '',
	description: '',
	discount_type: 'percent',
	amount: 10,
	min_spend: 0,
	usage_limit: 0,
	starts_on: '',
	expires_on: '',
	yacht_ids: [],
	active: true,
};

/**
 * Built-in discount codes. Guests enter a code in the booking drawer; the
 * discount is checked and applied live on the quote, and the code is saved
 * on the booking. Only shown when the Pro add-on is not providing coupons of
 * its own - with Pro active its screen takes this slot instead.
 */
export default function Coupons() {
	const [ items, setItems ] = useState( null );
	const [ yachts, setYachts ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ draft, setDraft ] = useState( BLANK );
	const [ editingId, setEditingId ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const currency = ( window.mageyaboAdminConfig && window.mageyaboAdminConfig.currency ) || '$';

	const load = () =>
		api.get( '/coupons' )
			.then( setItems )
			.catch( ( err ) => setError( err.message ) );

	useEffect( () => {
		load();
		api.get( '/yachts', { per_page: 100 } )
			.then( ( res ) => setYachts( res.items || [] ) )
			.catch( () => setYachts( [] ) );
	}, [] );

	const set = ( key, value ) => setDraft( { ...draft, [ key ]: value } );

	const reset = () => {
		setDraft( BLANK );
		setEditingId( null );
	};

	const save = () => {
		if ( ! draft.code.trim() ) {
			toast( __( 'Please enter a coupon code.', 'magepeople-yacht-booking-system' ), 'error' );
			return;
		}

		setBusy( true );

		const request = editingId ? api.put( `/coupons/${ editingId }`, draft ) : api.post( '/coupons', draft );

		request
			.then( () => {
				reset();
				load();
				toast( __( 'Coupon saved.', 'magepeople-yacht-booking-system' ), 'success' );
			} )
			.catch( ( err ) => toast( err.message, 'error' ) )
			.finally( () => setBusy( false ) );
	};

	const edit = ( coupon ) => {
		setEditingId( coupon.id );
		setDraft( {
			code: coupon.code,
			description: coupon.description,
			discount_type: coupon.discount_type,
			amount: coupon.amount,
			min_spend: coupon.min_spend,
			usage_limit: coupon.usage_limit,
			starts_on: coupon.starts_on,
			expires_on: coupon.expires_on,
			yacht_ids: coupon.yacht_ids,
			active: coupon.active,
		} );
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	};

	const toggleActive = ( coupon ) => {
		api.put( `/coupons/${ coupon.id }`, { active: ! coupon.active } )
			.then( load )
			.catch( ( err ) => toast( err.message, 'error' ) );
	};

	const remove = ( coupon ) => {
		if ( ! window.confirm( __( 'Delete this coupon? Bookings that already used it keep their discount.', 'magepeople-yacht-booking-system' ) ) ) {
			return;
		}

		api.del( `/coupons/${ coupon.id }` )
			.then( () => {
				if ( editingId === coupon.id ) {
					reset();
				}

				load();
			} )
			.catch( ( err ) => toast( err.message, 'error' ) );
	};

	const toggleYacht = ( id ) => {
		const ids = draft.yacht_ids.includes( id ) ? draft.yacht_ids.filter( ( x ) => x !== id ) : [ ...draft.yacht_ids, id ];
		set( 'yacht_ids', ids );
	};

	const describeDiscount = ( coupon ) =>
		'percent' === coupon.discount_type
			? `${ Number( coupon.amount ) }%`
			: `${ currency }${ Number( coupon.amount ).toFixed( 2 ) }`;

	const describeUsage = ( coupon ) =>
		coupon.usage_limit > 0
			? sprintf(
				/* translators: 1: times used, 2: usage limit. */
				__( '%1$d of %2$d', 'magepeople-yacht-booking-system' ),
				coupon.used_count,
				coupon.usage_limit
			)
			: sprintf(
				/* translators: %d: times used. */
				__( '%d (no limit)', 'magepeople-yacht-booking-system' ),
				coupon.used_count
			);

	const today = new Date().toISOString().slice( 0, 10 );

	const statusOf = ( coupon ) => {
		if ( ! coupon.active ) {
			return [ 'cancelled', __( 'Inactive', 'magepeople-yacht-booking-system' ) ];
		}

		if ( coupon.expires_on && coupon.expires_on < today ) {
			return [ 'cancelled', __( 'Expired', 'magepeople-yacht-booking-system' ) ];
		}

		if ( coupon.usage_limit > 0 && coupon.used_count >= coupon.usage_limit ) {
			return [ 'cancelled', __( 'Used up', 'magepeople-yacht-booking-system' ) ];
		}

		if ( coupon.starts_on && coupon.starts_on > today ) {
			return [ 'pending', __( 'Scheduled', 'magepeople-yacht-booking-system' ) ];
		}

		return [ 'completed', __( 'Active', 'magepeople-yacht-booking-system' ) ];
	};

	const yachtNames = ( ids ) =>
		ids.length
			? ids.map( ( id ) => ( yachts.find( ( y ) => y.id === id ) || {} ).title || `#${ id }` ).join( ', ' )
			: __( 'All yachts', 'magepeople-yacht-booking-system' );

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>{ __( 'Coupons', 'magepeople-yacht-booking-system' ) }</h2>
					<p>{ __( 'Discount codes guests can enter in the booking drawer. The discount is applied to the charter and extras, before tax.', 'magepeople-yacht-booking-system' ) }</p>
				</div>
			</div>

			{ error && <div className="ybs-notice is-error">{ error }</div> }
			{ ! items && ! error && <div className="ybs-loading">{ __( 'Loading…', 'magepeople-yacht-booking-system' ) }</div> }

			{ items && (
				<>
					<div className="ybs-card">
						<h3>{ editingId ? __( 'Edit coupon', 'magepeople-yacht-booking-system' ) : __( 'New coupon', 'magepeople-yacht-booking-system' ) }</h3>

						<div className="ybs-field-row">
							<div className="ybs-field">
								<label htmlFor="ybs-coupon-code">{ __( 'Code', 'magepeople-yacht-booking-system' ) }</label>
								<input
									id="ybs-coupon-code"
									type="text"
									value={ draft.code }
									placeholder="SUMMER10"
									style={ { textTransform: 'uppercase' } }
									onChange={ ( e ) => set( 'code', e.target.value.toUpperCase().replace( /\s+/g, '' ) ) }
								/>
								<p className="ybs-hint">{ __( 'Letters, numbers, dashes or underscores. Guests can type it in any case.', 'magepeople-yacht-booking-system' ) }</p>
							</div>
							<div className="ybs-field">
								<label htmlFor="ybs-coupon-description">{ __( 'Internal note', 'magepeople-yacht-booking-system' ) }</label>
								<input id="ybs-coupon-description" type="text" value={ draft.description } onChange={ ( e ) => set( 'description', e.target.value ) } />
								<p className="ybs-hint">{ __( 'Only you see this, e.g. "Newsletter, June".', 'magepeople-yacht-booking-system' ) }</p>
							</div>
						</div>

						<div className="ybs-field-row">
							<div className="ybs-field">
								<label htmlFor="ybs-coupon-type">{ __( 'Discount type', 'magepeople-yacht-booking-system' ) }</label>
								<select id="ybs-coupon-type" value={ draft.discount_type } onChange={ ( e ) => set( 'discount_type', e.target.value ) }>
									<option value="percent">{ __( 'Percentage off', 'magepeople-yacht-booking-system' ) }</option>
									<option value="fixed">{ __( 'Fixed amount off', 'magepeople-yacht-booking-system' ) }</option>
								</select>
							</div>
							<div className="ybs-field">
								<label htmlFor="ybs-coupon-amount">
									{ 'percent' === draft.discount_type ? __( 'Discount (%)', 'magepeople-yacht-booking-system' ) : sprintf(
										/* translators: %s: currency symbol. */
										__( 'Discount (%s)', 'magepeople-yacht-booking-system' ),
										currency
									) }
								</label>
								<input
									id="ybs-coupon-amount"
									type="number"
									min="0"
									max={ 'percent' === draft.discount_type ? 100 : undefined }
									step="0.01"
									value={ draft.amount }
									onChange={ ( e ) => set( 'amount', e.target.value ) }
								/>
							</div>
						</div>

						<div className="ybs-field-row">
							<div className="ybs-field">
								<label htmlFor="ybs-coupon-min">{ __( 'Minimum spend', 'magepeople-yacht-booking-system' ) }</label>
								<input id="ybs-coupon-min" type="number" min="0" step="0.01" value={ draft.min_spend } onChange={ ( e ) => set( 'min_spend', e.target.value ) } />
								<p className="ybs-hint">{ __( 'Charter plus extras. 0 for no minimum.', 'magepeople-yacht-booking-system' ) }</p>
							</div>
							<div className="ybs-field">
								<label htmlFor="ybs-coupon-limit">{ __( 'Usage limit', 'magepeople-yacht-booking-system' ) }</label>
								<input id="ybs-coupon-limit" type="number" min="0" step="1" value={ draft.usage_limit } onChange={ ( e ) => set( 'usage_limit', e.target.value ) } />
								<p className="ybs-hint">{ __( 'Total bookings that can use it. 0 for unlimited.', 'magepeople-yacht-booking-system' ) }</p>
							</div>
						</div>

						<div className="ybs-field-row">
							<div className="ybs-field">
								<label>{ __( 'Valid from', 'magepeople-yacht-booking-system' ) }</label>
								<DateField value={ draft.starts_on } onChange={ ( v ) => set( 'starts_on', v ) } />
								<p className="ybs-hint">{ __( 'Leave empty to start now.', 'magepeople-yacht-booking-system' ) }</p>
							</div>
							<div className="ybs-field">
								<label>{ __( 'Expires on', 'magepeople-yacht-booking-system' ) }</label>
								<DateField value={ draft.expires_on } onChange={ ( v ) => set( 'expires_on', v ) } />
								<p className="ybs-hint">{ __( 'Last day it can be used. Leave empty for no expiry.', 'magepeople-yacht-booking-system' ) }</p>
							</div>
						</div>

						{ yachts.length > 0 && (
							<fieldset className="ybs-field">
								<legend>{ __( 'Limit to yachts', 'magepeople-yacht-booking-system' ) }</legend>
								<p className="ybs-hint">{ __( 'Tick none to allow every yacht.', 'magepeople-yacht-booking-system' ) }</p>
								<div className="ybs-coupon-yachts">
									{ yachts.map( ( yacht ) => (
										<label key={ yacht.id }>
											<input type="checkbox" checked={ draft.yacht_ids.includes( yacht.id ) } onChange={ () => toggleYacht( yacht.id ) } />
											{ ' ' }
											{ yacht.title }
										</label>
									) ) }
								</div>
							</fieldset>
						) }

						<div className="ybs-field">
							<label>
								<input type="checkbox" checked={ !! draft.active } onChange={ ( e ) => set( 'active', e.target.checked ) } />
								{ ' ' }
								{ __( 'Active - guests can use this code', 'magepeople-yacht-booking-system' ) }
							</label>
						</div>

						<button type="button" className="ybs-btn is-primary" disabled={ busy } onClick={ save }>
							{ editingId ? __( 'Save changes', 'magepeople-yacht-booking-system' ) : __( '+ Add coupon', 'magepeople-yacht-booking-system' ) }
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
						<div className="ybs-empty-state">{ __( 'No coupons yet. The coupon field only appears in the booking drawer once at least one coupon is active.', 'magepeople-yacht-booking-system' ) }</div>
					) : (
						<table className="ybs-table">
							<thead>
								<tr>
									<th>{ __( 'Code', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Discount', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Used', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Valid', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Yachts', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Status', 'magepeople-yacht-booking-system' ) }</th>
									<th>{ __( 'Actions', 'magepeople-yacht-booking-system' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( coupon ) => {
									const [ statusClass, statusLabel ] = statusOf( coupon );

									return (
										<tr key={ coupon.id }>
											<td>
												<strong>{ coupon.code }</strong>
												{ coupon.description && <div className="ybs-hint">{ coupon.description }</div> }
											</td>
											<td>
												{ describeDiscount( coupon ) }
												{ coupon.min_spend > 0 && (
													<div className="ybs-hint">
														{ sprintf(
															/* translators: %s: minimum spend. */
															__( 'Min. %s', 'magepeople-yacht-booking-system' ),
															`${ currency }${ Number( coupon.min_spend ).toFixed( 2 ) }`
														) }
													</div>
												) }
											</td>
											<td>{ describeUsage( coupon ) }</td>
											<td>{ [ coupon.starts_on, coupon.expires_on ].some( Boolean ) ? `${ coupon.starts_on || '…' } → ${ coupon.expires_on || '…' }` : __( 'Always', 'magepeople-yacht-booking-system' ) }</td>
											<td>{ yachtNames( coupon.yacht_ids ) }</td>
											<td>
												<span className={ 'ybs-badge status-' + statusClass }>{ statusLabel }</span>
											</td>
											<td>
												<button type="button" className="ybs-btn" onClick={ () => edit( coupon ) }>
													{ __( 'Edit', 'magepeople-yacht-booking-system' ) }
												</button>
												{ ' ' }
												<button type="button" className="ybs-btn" onClick={ () => toggleActive( coupon ) }>
													{ coupon.active ? __( 'Deactivate', 'magepeople-yacht-booking-system' ) : __( 'Activate', 'magepeople-yacht-booking-system' ) }
												</button>
												{ ' ' }
												<button type="button" className="ybs-btn is-danger" onClick={ () => remove( coupon ) }>
													{ __( 'Delete', 'magepeople-yacht-booking-system' ) }
												</button>
											</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					) }
				</>
			) }
		</div>
	);
}
