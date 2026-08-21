import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import PaymentMethodFields from '../components/PaymentMethodFields';

function Field( { label, hint, children } ) {
	return (
		<div className="ybs-field">
			<label>{ label }</label>
			{ children }
			{ hint && <p className="ybs-hint">{ hint }</p> }
		</div>
	);
}

function PricingRules() {
	const [ rules, setRules ] = useState( null );
	const [ newRule, setNewRule ] = useState( { rule_type: 'off_day', label: '', date_from: '', date_to: '', adjustment_type: 'block', adjustment_value: 0 } );

	const load = () => api.get( '/pricing-rules' ).then( setRules );

	useEffect( () => {
		load();
	}, [] );

	const add = () => {
		api.post( '/pricing-rules', newRule ).then( () => {
			setNewRule( { rule_type: 'off_day', label: '', date_from: '', date_to: '', adjustment_type: 'block', adjustment_value: 0 } );
			load();
		} );
	};

	const remove = ( id ) => api.del( `/pricing-rules/${ id }` ).then( load );

	return (
		<div className="ybs-card">
			<h3>{ __( 'Off-Days & Basic Pricing Rules', 'yacht-booking-system' ) }</h3>
			<p className="ybs-hint">{ __( 'Applies to all yachts unless a yacht-specific rule is added later.', 'yacht-booking-system' ) }</p>

			{ rules && rules.length > 0 && (
				<table className="ybs-table" style={ { marginBottom: 16 } }>
					<thead>
						<tr>
							<th>{ __( 'Label', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Type', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Dates', 'yacht-booking-system' ) }</th>
							<th>{ __( 'Effect', 'yacht-booking-system' ) }</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ rules.map( ( rule ) => (
							<tr key={ rule.id }>
								<td>{ rule.label }</td>
								<td>{ rule.rule_type }</td>
								<td>{ rule.date_from } { rule.date_to ? `→ ${ rule.date_to }` : '' }</td>
								<td>{ 'block' === rule.adjustment_type ? __( 'Blocked', 'yacht-booking-system' ) : `${ rule.adjustment_type } ${ rule.adjustment_value }` }</td>
								<td><button className="ybs-btn is-danger" onClick={ () => remove( rule.id ) }>{ __( 'Remove', 'yacht-booking-system' ) }</button></td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			<div className="ybs-field-row">
				<Field label={ __( 'Label', 'yacht-booking-system' ) }>
					<input type="text" value={ newRule.label } onChange={ ( e ) => setNewRule( { ...newRule, label: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Type', 'yacht-booking-system' ) }>
					<select value={ newRule.rule_type } onChange={ ( e ) => setNewRule( { ...newRule, rule_type: e.target.value, adjustment_type: 'off_day' === e.target.value ? 'block' : newRule.adjustment_type } ) }>
						<option value="off_day">{ __( 'Off-Day (block)', 'yacht-booking-system' ) }</option>
						<option value="weekday">{ __( 'Weekday/Weekend', 'yacht-booking-system' ) }</option>
						<option value="seasonal">{ __( 'Seasonal', 'yacht-booking-system' ) }</option>
					</select>
				</Field>
				<Field label={ __( 'From Date', 'yacht-booking-system' ) }>
					<input type="date" value={ newRule.date_from } onChange={ ( e ) => setNewRule( { ...newRule, date_from: e.target.value } ) } />
				</Field>
				<Field label={ __( 'To Date', 'yacht-booking-system' ) }>
					<input type="date" value={ newRule.date_to } onChange={ ( e ) => setNewRule( { ...newRule, date_to: e.target.value } ) } />
				</Field>
				{ 'off_day' !== newRule.rule_type && (
					<>
						<Field label={ __( 'Adjustment', 'yacht-booking-system' ) }>
							<select value={ newRule.adjustment_type } onChange={ ( e ) => setNewRule( { ...newRule, adjustment_type: e.target.value } ) }>
								<option value="percent">{ __( 'Percent', 'yacht-booking-system' ) }</option>
								<option value="fixed">{ __( 'Fixed Amount', 'yacht-booking-system' ) }</option>
								<option value="block">{ __( 'Block', 'yacht-booking-system' ) }</option>
							</select>
						</Field>
						<Field label={ __( 'Value', 'yacht-booking-system' ) }>
							<input type="number" value={ newRule.adjustment_value } onChange={ ( e ) => setNewRule( { ...newRule, adjustment_value: e.target.value } ) } />
						</Field>
					</>
				) }
			</div>

			<button className="ybs-btn is-primary" onClick={ add }>{ __( '+ Add Rule', 'yacht-booking-system' ) }</button>
		</div>
	);
}

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		api.get( '/settings' ).then( setSettings ).catch( ( err ) => setError( err.message ) );
	}, [] );

	const set = ( key, value ) => setSettings( { ...settings, [ key ]: value } );

	const save = () => {
		setError( '' );
		api.put( '/settings', settings )
			.then( ( data ) => {
				setSettings( data );
				setSaved( true );
				setTimeout( () => setSaved( false ), 2000 );
			} )
			.catch( ( err ) => setError( err.message ) );
	};

	if ( ! settings ) {
		return <div className="ybs-loading">{ __( 'Loading…', 'yacht-booking-system' ) }</div>;
	}

	return (
		<div>
			<div className="ybs-page-header">
				<h2>{ __( 'Settings', 'yacht-booking-system' ) }</h2>
				<button className="ybs-btn is-primary" onClick={ save }>{ __( 'Save Settings', 'yacht-booking-system' ) }</button>
			</div>

			{ saved && <div className="ybs-notice is-success">{ __( 'Settings saved.', 'yacht-booking-system' ) }</div> }
			{ error && <div className="ybs-notice is-error">{ error }</div> }

			<div className="ybs-card">
				<h3>{ __( 'Currency & Tax', 'yacht-booking-system' ) }</h3>
				<div className="ybs-field-row">
					<Field label={ __( 'Currency Code', 'yacht-booking-system' ) }>
						<input type="text" value={ settings.currency_code } onChange={ ( e ) => set( 'currency_code', e.target.value ) } />
					</Field>
					<Field label={ __( 'Currency Symbol', 'yacht-booking-system' ) }>
						<input type="text" value={ settings.currency_symbol } onChange={ ( e ) => set( 'currency_symbol', e.target.value ) } />
					</Field>
					<Field label={ __( 'Tax Rate (%)', 'yacht-booking-system' ) }>
						<input type="number" value={ settings.tax_rate } onChange={ ( e ) => set( 'tax_rate', e.target.value ) } />
					</Field>
				</div>
			</div>

			<div className="ybs-card">
				<h3>{ __( 'Payment Methods', 'yacht-booking-system' ) }</h3>
				<PaymentMethodFields settings={ settings } onSettingsChange={ setSettings } />
			</div>

			<PricingRules />

			<div className="ybs-card">
				<h3>{ __( 'Data & Privacy', 'yacht-booking-system' ) }</h3>
				<Field label={ __( 'Anonymize guest data after (months)', 'yacht-booking-system' ) } hint={ __( '0 disables automatic anonymization.', 'yacht-booking-system' ) }>
					<input type="number" value={ settings.retention_months } onChange={ ( e ) => set( 'retention_months', e.target.value ) } />
				</Field>
				<label style={ { display: 'block' } }>
					<input type="checkbox" checked={ !! settings.remove_data_on_uninstall } onChange={ () => set( 'remove_data_on_uninstall', ! settings.remove_data_on_uninstall ) } />{ ' ' }
					{ __( 'Remove all plugin data when uninstalled', 'yacht-booking-system' ) }
				</label>
			</div>
		</div>
	);
}
