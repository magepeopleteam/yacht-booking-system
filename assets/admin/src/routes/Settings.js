import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import PaymentMethodFields from '../components/PaymentMethodFields';
import ClassicEditor, { insertIntoEditor } from '../components/ClassicEditor';
import EmailVariables from '../components/EmailVariables';
import TestEmailModal from '../components/TestEmailModal';
import DateField from '../components/DateField';

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
			<h3>{ __( 'Off-Days & Basic Pricing Rules', 'magepeople-yacht-booking-system' ) }</h3>
			<p className="ybs-hint">{ __( 'Applies to all yachts unless a yacht-specific rule is added later.', 'magepeople-yacht-booking-system' ) }</p>

			{ rules && rules.length > 0 && (
				<table className="ybs-table" style={ { marginBottom: 16 } }>
					<thead>
						<tr>
							<th>{ __( 'Label', 'magepeople-yacht-booking-system' ) }</th>
							<th>{ __( 'Type', 'magepeople-yacht-booking-system' ) }</th>
							<th>{ __( 'Dates', 'magepeople-yacht-booking-system' ) }</th>
							<th>{ __( 'Effect', 'magepeople-yacht-booking-system' ) }</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{ rules.map( ( rule ) => (
							<tr key={ rule.id }>
								<td>{ rule.label }</td>
								<td>{ rule.rule_type }</td>
								<td>{ rule.date_from } { rule.date_to ? `→ ${ rule.date_to }` : '' }</td>
								<td>{ 'block' === rule.adjustment_type ? __( 'Blocked', 'magepeople-yacht-booking-system' ) : `${ rule.adjustment_type } ${ rule.adjustment_value }` }</td>
								<td><button className="ybs-btn is-danger" onClick={ () => remove( rule.id ) }>{ __( 'Remove', 'magepeople-yacht-booking-system' ) }</button></td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			<div className="ybs-field-row">
				<Field label={ __( 'Label', 'magepeople-yacht-booking-system' ) }>
					<input type="text" value={ newRule.label } onChange={ ( e ) => setNewRule( { ...newRule, label: e.target.value } ) } />
				</Field>
				<Field label={ __( 'Type', 'magepeople-yacht-booking-system' ) }>
					<select value={ newRule.rule_type } onChange={ ( e ) => setNewRule( { ...newRule, rule_type: e.target.value, adjustment_type: 'off_day' === e.target.value ? 'block' : newRule.adjustment_type } ) }>
						<option value="off_day">{ __( 'Off-Day (block)', 'magepeople-yacht-booking-system' ) }</option>
						<option value="weekday">{ __( 'Weekday/Weekend', 'magepeople-yacht-booking-system' ) }</option>
						<option value="seasonal">{ __( 'Seasonal', 'magepeople-yacht-booking-system' ) }</option>
					</select>
				</Field>
				<Field label={ __( 'From Date', 'magepeople-yacht-booking-system' ) }>
					<DateField value={ newRule.date_from } onChange={ ( v ) => setNewRule( { ...newRule, date_from: v } ) } />
				</Field>
				<Field label={ __( 'To Date', 'magepeople-yacht-booking-system' ) }>
					<DateField value={ newRule.date_to } onChange={ ( v ) => setNewRule( { ...newRule, date_to: v } ) } />
				</Field>
				{ 'off_day' !== newRule.rule_type && (
					<>
						<Field label={ __( 'Adjustment', 'magepeople-yacht-booking-system' ) }>
							<select value={ newRule.adjustment_type } onChange={ ( e ) => setNewRule( { ...newRule, adjustment_type: e.target.value } ) }>
								<option value="percent">{ __( 'Percent', 'magepeople-yacht-booking-system' ) }</option>
								<option value="fixed">{ __( 'Fixed Amount', 'magepeople-yacht-booking-system' ) }</option>
								<option value="block">{ __( 'Block', 'magepeople-yacht-booking-system' ) }</option>
							</select>
						</Field>
						<Field label={ __( 'Value', 'magepeople-yacht-booking-system' ) }>
							<input type="number" value={ newRule.adjustment_value } onChange={ ( e ) => setNewRule( { ...newRule, adjustment_value: e.target.value } ) } />
						</Field>
					</>
				) }
			</div>

			<button className="ybs-btn is-primary" onClick={ add }>{ __( '+ Add Rule', 'magepeople-yacht-booking-system' ) }</button>
		</div>
	);
}

function GeneralTab( { settings, set } ) {
	return (
		<>
			<div className="ybs-card">
				<h3>{ __( 'Currency & Tax', 'magepeople-yacht-booking-system' ) }</h3>
				<div className="ybs-field-row">
					<Field label={ __( 'Currency Code', 'magepeople-yacht-booking-system' ) }>
						<input type="text" value={ settings.currency_code } onChange={ ( e ) => set( 'currency_code', e.target.value ) } />
					</Field>
					<Field label={ __( 'Currency Symbol', 'magepeople-yacht-booking-system' ) }>
						<input type="text" value={ settings.currency_symbol } onChange={ ( e ) => set( 'currency_symbol', e.target.value ) } />
					</Field>
					<Field label={ __( 'Tax Rate (%)', 'magepeople-yacht-booking-system' ) }>
						<input type="number" value={ settings.tax_rate } onChange={ ( e ) => set( 'tax_rate', e.target.value ) } />
					</Field>
				</div>
			</div>

			<div className="ybs-card">
				<h3>{ __( 'Details Page - "Ready When You Are" CTA', 'magepeople-yacht-booking-system' ) }</h3>
				<p className="ybs-hint" style={ { marginTop: 0 } }>
					{ __( 'The booking call-to-action shown near the bottom of every yacht details page. Use {yacht_name} in the heading to insert the yacht\'s name - a yacht can override the heading/text from its own Review step.', 'magepeople-yacht-booking-system' ) }
				</p>
				<Field label={ __( 'Eyebrow', 'magepeople-yacht-booking-system' ) }>
					<input type="text" value={ settings.cta_eyebrow } onChange={ ( e ) => set( 'cta_eyebrow', e.target.value ) } />
				</Field>
				<Field label={ __( 'Heading', 'magepeople-yacht-booking-system' ) }>
					<input type="text" value={ settings.cta_heading } onChange={ ( e ) => set( 'cta_heading', e.target.value ) } />
				</Field>
				<Field label={ __( 'Text', 'magepeople-yacht-booking-system' ) }>
					<input type="text" value={ settings.cta_text } onChange={ ( e ) => set( 'cta_text', e.target.value ) } />
				</Field>
				<div className="ybs-field-row">
					<Field label={ __( 'Primary Button Label', 'magepeople-yacht-booking-system' ) }>
						<input type="text" value={ settings.cta_button_label } onChange={ ( e ) => set( 'cta_button_label', e.target.value ) } />
					</Field>
					<Field label={ __( 'Secondary Button Label', 'magepeople-yacht-booking-system' ) }>
						<input type="text" value={ settings.cta_button2_label } onChange={ ( e ) => set( 'cta_button2_label', e.target.value ) } />
					</Field>
				</div>
			</div>
		</>
	);
}

function PaymentsTab( { settings, setSettings } ) {
	return (
		<div className="ybs-card">
			<h3>{ __( 'Payment Methods', 'magepeople-yacht-booking-system' ) }</h3>
			<PaymentMethodFields settings={ settings } onSettingsChange={ setSettings } />
		</div>
	);
}

function PrivacyTab( { settings, set } ) {
	return (
		<div className="ybs-card">
			<h3>{ __( 'Data & Privacy', 'magepeople-yacht-booking-system' ) }</h3>
			<Field label={ __( 'Anonymize guest data after (months)', 'magepeople-yacht-booking-system' ) } hint={ __( '0 disables automatic anonymization.', 'magepeople-yacht-booking-system' ) }>
				<input type="number" value={ settings.retention_months } onChange={ ( e ) => set( 'retention_months', e.target.value ) } />
			</Field>
			<label style={ { display: 'block' } }>
				<input type="checkbox" checked={ !! settings.remove_data_on_uninstall } onChange={ () => set( 'remove_data_on_uninstall', ! settings.remove_data_on_uninstall ) } />{ ' ' }
				{ __( 'Remove all plugin data when uninstalled', 'magepeople-yacht-booking-system' ) }
			</label>
		</div>
	);
}

const TRIGGER_STATUSES = [
	{ value: 'pending', label: __( 'Pending Payment', 'magepeople-yacht-booking-system' ) },
	{ value: 'processing', label: __( 'Processing', 'magepeople-yacht-booking-system' ) },
	{ value: 'on-hold', label: __( 'On Hold', 'magepeople-yacht-booking-system' ) },
	{ value: 'completed', label: __( 'Completed', 'magepeople-yacht-booking-system' ) },
];

const EMAIL_EDITOR_ID = 'ybs-settings-email-body';

function EmailTab( { settings, set } ) {
	const [ showTestModal, setShowTestModal ] = useState( false );
	const triggers = settings.email_trigger_statuses || [];

	const toggleTrigger = ( value ) => {
		const next = triggers.includes( value )
			? triggers.filter( ( s ) => s !== value )
			: [ ...triggers, value ];
		set( 'email_trigger_statuses', next );
	};

	const insertTag = ( tag ) => {
		const next = insertIntoEditor( EMAIL_EDITOR_ID, tag );

		if ( null !== next ) {
			set( 'email_body', next );
		}
	};

	return (
		<>
		<div className="ybs-settings__grid">
			<div className="ybs-settings__grid-main">
				<div className="ybs-card">
					<h3>{ __( 'Sender Identity', 'magepeople-yacht-booking-system' ) }</h3>
					<div className="ybs-field-row">
						<Field label={ __( 'From Name', 'magepeople-yacht-booking-system' ) } hint={ __( 'Defaults to your site name if left blank.', 'magepeople-yacht-booking-system' ) }>
							<input type="text" value={ settings.email_from_name } onChange={ ( e ) => set( 'email_from_name', e.target.value ) } />
						</Field>
						<Field label={ __( 'From Email', 'magepeople-yacht-booking-system' ) } hint={ __( 'Defaults to the site admin email if left blank.', 'magepeople-yacht-booking-system' ) }>
							<input type="email" value={ settings.email_from_address } onChange={ ( e ) => set( 'email_from_address', e.target.value ) } />
						</Field>
					</div>
				</div>

				<div className="ybs-card">
					<div className="ybs-card__head-row">
						<h3>{ __( 'Confirmation Email', 'magepeople-yacht-booking-system' ) }</h3>
						<button type="button" className="ybs-btn" onClick={ () => setShowTestModal( true ) }>
							{ __( 'Send Test Email', 'magepeople-yacht-booking-system' ) }
						</button>
					</div>
					<p className="ybs-hint" style={ { marginTop: 0 } }>
						{ __( 'This is the default email sent for every yacht. A yacht can override it in its own edit screen (Step 4).', 'magepeople-yacht-booking-system' ) }
					</p>
					<label style={ { display: 'block', marginBottom: 16 } }>
						<input type="checkbox" checked={ !! settings.email_enabled } onChange={ () => set( 'email_enabled', ! settings.email_enabled ) } />{ ' ' }
						{ __( 'Send booking confirmation emails', 'magepeople-yacht-booking-system' ) }
					</label>
					<Field label={ __( 'Email Subject', 'magepeople-yacht-booking-system' ) }>
						<input type="text" value={ settings.email_subject } onChange={ ( e ) => set( 'email_subject', e.target.value ) } />
					</Field>
					<Field label={ __( 'Email Body', 'magepeople-yacht-booking-system' ) }>
						<ClassicEditor
							id={ EMAIL_EDITOR_ID }
							value={ settings.email_body }
							onChange={ ( value ) => set( 'email_body', value ) }
						/>
					</Field>
				</div>
			</div>

			<div className="ybs-settings__grid-side">
				<div className="ybs-card">
					<h3>{ __( 'Send Confirmation On', 'magepeople-yacht-booking-system' ) }</h3>
					<p className="ybs-hint" style={ { marginTop: 0 } }>
						{ __( 'The booking status(es) that trigger the confirmation email.', 'magepeople-yacht-booking-system' ) }
					</p>
					<div className="ybs-checkbox-list">
						{ TRIGGER_STATUSES.map( ( status ) => (
							<label className="ybs-checkbox-list__item" key={ status.value }>
								<input
									type="checkbox"
									checked={ triggers.includes( status.value ) }
									onChange={ () => toggleTrigger( status.value ) }
								/>
								{ status.label }
							</label>
						) ) }
					</div>
				</div>

				<EmailVariables onInsert={ insertTag } />
			</div>
		</div>

		{ showTestModal && (
			<TestEmailModal
				subject={ settings.email_subject }
				body={ settings.email_body }
				fromName={ settings.email_from_name }
				fromEmail={ settings.email_from_address }
				onRequestClose={ () => setShowTestModal( false ) }
			/>
		) }
		</>
	);
}

const TABS = [
	{ id: 'general', label: __( 'General', 'magepeople-yacht-booking-system' ), subtitle: __( 'Currency and tax', 'magepeople-yacht-booking-system' ), icon: 'dashicons-admin-generic' },
	{ id: 'payments', label: __( 'Payments', 'magepeople-yacht-booking-system' ), subtitle: __( 'Accepted payment methods', 'magepeople-yacht-booking-system' ), icon: 'dashicons-cart' },
	{ id: 'pricing', label: __( 'Pricing Rules', 'magepeople-yacht-booking-system' ), subtitle: __( 'Off-days and seasonal adjustments', 'magepeople-yacht-booking-system' ), icon: 'dashicons-calendar-alt' },
	{ id: 'email', label: __( 'Email', 'magepeople-yacht-booking-system' ), subtitle: __( 'Booking confirmation emails', 'magepeople-yacht-booking-system' ), icon: 'dashicons-email-alt' },
	{ id: 'privacy', label: __( 'Data & Privacy', 'magepeople-yacht-booking-system' ), subtitle: __( 'Retention and uninstall behavior', 'magepeople-yacht-booking-system' ), icon: 'dashicons-privacy' },
];

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ tab, setTab ] = useState( 'general' );
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
		return <div className="ybs-loading">{ __( 'Loading…', 'magepeople-yacht-booking-system' ) }</div>;
	}

	const activeTab = TABS.find( ( t ) => t.id === tab ) || TABS[ 0 ];

	return (
		<div className="ybs-settings">
			<aside className="ybs-settings__sidebar">
				<div className="ybs-settings__sb-header">
					<span className="ybs-settings__sb-eyebrow">{ __( 'Yacht Booking System', 'magepeople-yacht-booking-system' ) }</span>
					<span className="ybs-settings__sb-title">
						<span className="ybs-settings__sb-dot" />
						{ __( 'Settings', 'magepeople-yacht-booking-system' ) }
					</span>
				</div>
				<nav className="ybs-settings__nav">
					{ TABS.map( ( t ) => (
						<button
							type="button"
							key={ t.id }
							className={ 'ybs-settings__nav-item' + ( tab === t.id ? ' is-active' : '' ) }
							onClick={ () => setTab( t.id ) }
						>
							<span className={ 'dashicons ' + t.icon } />
							{ t.label }
						</button>
					) ) }
				</nav>
			</aside>

			<div className="ybs-settings__main">
				<div className="ybs-settings__topbar">
					<span className="ybs-settings__topbar-title">{ activeTab.label }</span>
					<span className="ybs-settings__topbar-sep">&rsaquo;</span>
					<span className="ybs-settings__topbar-sub">{ activeTab.subtitle }</span>
					<button className="ybs-btn is-primary ybs-settings__save" onClick={ save }>
						{ __( 'Save Changes', 'magepeople-yacht-booking-system' ) }
					</button>
				</div>

				<div className="ybs-settings__content">
					{ saved && <div className="ybs-notice is-success">{ __( 'Settings saved.', 'magepeople-yacht-booking-system' ) }</div> }
					{ error && <div className="ybs-notice is-error">{ error }</div> }

					{ 'general' === tab && <GeneralTab settings={ settings } set={ set } /> }
					{ 'payments' === tab && <PaymentsTab settings={ settings } setSettings={ setSettings } /> }
					{ 'pricing' === tab && <PricingRules /> }
					{ 'email' === tab && <EmailTab settings={ settings } set={ set } /> }
					{ 'privacy' === tab && <PrivacyTab settings={ settings } set={ set } /> }
				</div>
			</div>
		</div>
	);
}
