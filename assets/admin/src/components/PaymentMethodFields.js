import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { api } from '../api/client';
import { toast } from './Toast';
import WooCommerceGatewayList from './WooCommerceGatewayList';

function Field({ label, hint, children }) {
	return (
		<div className="ybs-field">
			<label>{label}</label>
			{children}
			{hint && <p className="ybs-hint">{hint}</p>}
		</div>
	);
}

/**
 * The 3-step "how this works" strip shown above the mode switcher, so the
 * tab reads as a guided setup rather than a wall of controls.
 */
function PayIntro() {
	const steps = [
		__( 'Choose whether to use your own payment methods or WooCommerce checkout.', 'magepeople-yacht-booking-system' ),
		__( 'Enable and configure the ones you want - only the matching settings are shown.', 'magepeople-yacht-booking-system' ),
		__( "That's it - guests can now pay. You can switch anytime; the change saves instantly.", 'magepeople-yacht-booking-system' ),
	];

	return (
		<div className="ybs-pay-intro">
			<div className="ybs-pay-intro__title">
				<span className="dashicons dashicons-info-outline" />
				{__( 'How payments work here', 'magepeople-yacht-booking-system' )}
			</div>
			<ol className="ybs-pay-intro__steps">
				{steps.map( ( text, i ) => (
					<li key={ i }>
						<span className="ybs-pay-intro__n">{ i + 1 }</span>
						{ text }
					</li>
				) ) }
			</ol>
		</div>
	);
}

/**
 * One of the two mutually-exclusive flows - "Custom Payment Methods" or
 * "WooCommerce Checkout". Clicking a selectable card switches to it; the
 * unavailable one (WooCommerce not installed) shows its own unlock CTA
 * instead of just going quietly disabled.
 */
function ModeCard( { icon, title, description, active, disabled, onSelect, cta } ) {
	return (
		<div
			className={ 'ybs-mode-card' + ( active ? ' is-selected' : '' ) + ( disabled ? ' is-disabled' : '' ) }
			onClick={ disabled ? undefined : onSelect }
			role="radio"
			aria-checked={ active }
			aria-disabled={ disabled }
			tabIndex={ disabled ? -1 : 0 }
		>
			<span className={ 'ybs-mode-card__icon dashicons ' + icon } />
			<div className="ybs-mode-card__body">
				<div className="ybs-mode-card__title-row">
					<strong>{ title }</strong>
					{ active && <span className="ybs-mode-card__badge">{ __( 'Active', 'magepeople-yacht-booking-system' ) }</span> }
				</div>
				<span className="ybs-mode-card__desc">{ description }</span>
				{ cta && <div className="ybs-mode-card__cta">{ cta }</div> }
			</div>
		</div>
	);
}

/**
 * One payment method as its own row card - a quick enable/disable switch,
 * title + status pill, a "Configure" toggle that reveals its settings
 * independently of that switch (so you can review PayPal's fields before
 * turning it on, or tuck Stripe's away again without disabling it), and an
 * always-visible one-line description underneath.
 */
function ToggleRow( { label, description, checked, disabled, onChange, children } ) {
	// Collapsed by default, even for a method that's already enabled when
	// the page loads - "Configure" is the only thing that opens it, so a
	// settings screen with several methods already on doesn't load with
	// all of their fields sprawled open.
	const [ expanded, setExpanded ] = useState( false );
	const wasChecked = useRef( checked );

	// The one exception: flipping a method on just now (not on page load)
	// still surfaces its fields immediately, since that's usually exactly
	// why you turned it on.
	useEffect( () => {
		if ( checked && ! wasChecked.current ) {
			setExpanded( true );
		}
		wasChecked.current = checked;
	}, [ checked ] );

	return (
		<div className={ 'ybs-gw-card' + ( checked ? ' is-enabled' : '' ) + ( disabled ? ' is-disabled' : '' ) }>
			<div className="ybs-gw-card__head">
				<label className="ybs-toggle">
					<input type="checkbox" checked={ checked } disabled={ disabled } onChange={ onChange } />
					<span className="ybs-toggle__track"><span className="ybs-toggle__thumb" /></span>
				</label>
				<span className="ybs-gw-card__title">{ label }</span>
				<span className={ 'ybs-gw-card__badge' + ( checked ? ' is-active' : '' ) }>
					{ checked ? __( 'Enabled', 'magepeople-yacht-booking-system' ) : __( 'Disabled', 'magepeople-yacht-booking-system' ) }
				</span>
				{ children && (
					<button
						type="button"
						className="ybs-gw-card__configure"
						onClick={ () => setExpanded( ( v ) => ! v ) }
						aria-expanded={ expanded }
					>
						{ __( 'Configure', 'magepeople-yacht-booking-system' ) }
						<span className={ 'dashicons dashicons-arrow-down-alt2' + ( expanded ? ' is-open' : '' ) } />
					</button>
				) }
			</div>
			{ description && <p className="ybs-gw-card__desc">{ description }</p> }
			{ expanded && children && <div className="ybs-gw-card__body">{ children }</div> }
		</div>
	);
}

/**
 * The Offline/PayPal/Stripe/WooCommerce method list - shared by the full
 * Settings screen and the wizard's "Configure Payments" popup so there is
 * exactly one implementation of "what a payment method's config looks like".
 *
 * Two mutually-exclusive groups: native (offline/PayPal/Stripe) or
 * WooCommerce - never both, since a booking is only ever paid for one way.
 */
export default function PaymentMethodFields( { settings, onSettingsChange } ) {
	const [ installing, setInstalling ] = useState( false );
	const [ wcAccordionOpen, setWcAccordionOpen ] = useState( true );
	const wcMode = !! settings.woocommerce_enabled;

	const set = ( key, value ) => onSettingsChange( { ...settings, [ key ]: value } );

	const toggleMethod = ( id ) => {
		const methods = settings.payment_methods.includes( id )
			? settings.payment_methods.filter( ( m ) => m !== id )
			: [ ...settings.payment_methods, id ];
		set( 'payment_methods', methods );
	};

	const selectNativeMode = () => {
		if ( ! wcMode ) {
			return;
		}
		// Leaving WooCommerce mode - fall back to Offline so the site is
		// never left with zero payment methods enabled.
		onSettingsChange( {
			...settings,
			woocommerce_enabled: false,
			payment_methods: [ 'offline' ],
			default_payment_method: 'offline',
		} );
	};

	const selectWooCommerceMode = () => {
		if ( wcMode || ! settings.woocommerce_active ) {
			return;
		}
		// Entering WooCommerce mode - the native group is mutually exclusive.
		onSettingsChange( {
			...settings,
			woocommerce_enabled: true,
			payment_methods: [],
			default_payment_method: 'woocommerce',
		} );
	};

	const installWooCommerce = () => {
		setInstalling( true );

		api.post( '/settings/woocommerce/install' )
			.then( ( data ) => {
				setInstalling( false );
				onSettingsChange( { ...settings, ...data, woocommerce_enabled: true, payment_methods: [], default_payment_method: 'woocommerce' } );
				toast( __( 'WooCommerce is installed and active. Cash on Delivery is enabled by default - turn on more methods below. Refresh the page to see it reflected everywhere in wp-admin.', 'magepeople-yacht-booking-system' ) );
			} )
			.catch( ( err ) => {
				setInstalling( false );
				toast( err.message, 'error' );
			} );
	};

	return (
		<div className="ybs-payment-methods">
			<PayIntro />

			<div className="ybs-mode-head">
				<h4>{ __( 'Step 1 - Choose your payment flow', 'magepeople-yacht-booking-system' ) }</h4>
				<p>{ __( 'Pick exactly one flow to take payments. Your choice is saved with the rest of this form, and only its matching settings are shown below.', 'magepeople-yacht-booking-system' ) }</p>
			</div>

			<div className="ybs-mode-cards" role="radiogroup" aria-label={ __( 'Payment flow', 'magepeople-yacht-booking-system' ) }>
				<ModeCard
					icon="dashicons-money-alt"
					title={ __( 'Custom Payment Methods', 'magepeople-yacht-booking-system' ) }
					description={ __( 'Offline, PayPal, and Stripe - run directly by this plugin, no WooCommerce required.', 'magepeople-yacht-booking-system' ) }
					active={ ! wcMode }
					onSelect={ selectNativeMode }
				/>
				<ModeCard
					icon="dashicons-cart"
					title={ __( 'WooCommerce Checkout', 'magepeople-yacht-booking-system' ) }
					description={ __( 'Bookings go through the WooCommerce cart, checkout, and orders.', 'magepeople-yacht-booking-system' ) }
					active={ wcMode }
					disabled={ ! settings.woocommerce_active }
					onSelect={ selectWooCommerceMode }
					cta={ ! settings.woocommerce_active && (
						<button type="button" className="ybs-btn is-primary" onClick={ ( e ) => { e.stopPropagation(); installWooCommerce(); } } disabled={ installing }>
							{ installing ? __( 'Installing…', 'magepeople-yacht-booking-system' ) : __( 'Install & Activate WooCommerce', 'magepeople-yacht-booking-system' ) }
						</button>
					) }
				/>
			</div>

			<div className="ybs-mode-context">
				<span className="ybs-mode-context__dot" />
				<span className="ybs-mode-context__label">{ __( "You're configuring:", 'magepeople-yacht-booking-system' ) }</span>
				<span className={ 'dashicons ' + ( wcMode ? 'dashicons-cart' : 'dashicons-money-alt' ) } />
				<strong>{ wcMode ? __( 'WooCommerce Checkout', 'magepeople-yacht-booking-system' ) : __( 'Custom Payment Methods', 'magepeople-yacht-booking-system' ) }</strong>
			</div>

			{ wcMode ? (
				settings.woocommerce_active ? (
					<div className={ 'ybs-gw-accordion' + ( wcAccordionOpen ? ' is-open' : '' ) }>
						<button type="button" className="ybs-gw-accordion__bar" onClick={ () => setWcAccordionOpen( ( v ) => ! v ) } aria-expanded={ wcAccordionOpen }>
							{ __( 'WooCommerce Payment Methods', 'magepeople-yacht-booking-system' ) }
							<span className="dashicons dashicons-arrow-down-alt2" />
						</button>
						{ wcAccordionOpen && (
							<div className="ybs-gw-accordion__body">
								<a
									className="ybs-btn"
									href={ ( window.mageyaboAdminConfig?.adminUrl || '/wp-admin/' ) + 'admin.php?page=wc-settings&tab=checkout' }
									target="_blank"
									rel="noreferrer"
								>
									{ __( 'Open in WooCommerce', 'magepeople-yacht-booking-system' ) }
									<span className="dashicons dashicons-external" />
								</a>
								<WooCommerceGatewayList />
							</div>
						) }
					</div>
				) : (
					<div className="ybs-notice is-info">
						{ __( 'WooCommerce isn\'t active yet - use the Install & Activate button above, then reopen this tab.', 'magepeople-yacht-booking-system' ) }
					</div>
				)
			) : (
				<>
					<ToggleRow
						label={ __( 'Offline / Manual Payment', 'magepeople-yacht-booking-system' ) }
						description={ __( 'The guest arranges payment with you directly.', 'magepeople-yacht-booking-system' ) }
						checked={ settings.payment_methods.includes( 'offline' ) }
						onChange={ () => toggleMethod( 'offline' ) }
					>
						<Field label={ __( 'Instructions shown to the guest', 'magepeople-yacht-booking-system' ) }>
							<textarea
								rows={ 2 }
								value={ settings.offline_instructions || '' }
								onChange={ ( e ) => set( 'offline_instructions', e.target.value ) }
							/>
						</Field>
					</ToggleRow>

					<ToggleRow
						label={ __( 'PayPal', 'magepeople-yacht-booking-system' ) }
						description={ __( 'Accept payments via PayPal.', 'magepeople-yacht-booking-system' ) }
						checked={ settings.payment_methods.includes( 'paypal' ) }
						onChange={ () => toggleMethod( 'paypal' ) }
					>
						<div className="ybs-field-row">
							<Field label={ __( 'PayPal Email', 'magepeople-yacht-booking-system' ) }>
								<input type="text" value={ settings.paypal_email || '' } onChange={ ( e ) => set( 'paypal_email', e.target.value ) } />
							</Field>
							<Field label={ __( 'Mode', 'magepeople-yacht-booking-system' ) }>
								<select value={ settings.paypal_mode } onChange={ ( e ) => set( 'paypal_mode', e.target.value ) }>
									<option value="sandbox">{ __( 'Sandbox', 'magepeople-yacht-booking-system' ) }</option>
									<option value="live">{ __( 'Live', 'magepeople-yacht-booking-system' ) }</option>
								</select>
							</Field>
						</div>
					</ToggleRow>

					<ToggleRow
						label={ __( 'Stripe', 'magepeople-yacht-booking-system' ) }
						description={ __( 'Accept payments via credit or debit card.', 'magepeople-yacht-booking-system' ) }
						checked={ settings.payment_methods.includes( 'stripe' ) }
						onChange={ () => toggleMethod( 'stripe' ) }
					>
						<div className="ybs-field-row">
							<Field
								label={ __( 'Publishable Key', 'magepeople-yacht-booking-system' ) }
								hint={
									settings.stripe_publishable_key && ! settings.stripe_publishable_key.startsWith( 'pk_' )
										? __( 'Doesn\'t look like a Stripe publishable key (should start with "pk_") - the card form on the booking page won\'t load until this matches one from your Stripe Dashboard.', 'magepeople-yacht-booking-system' )
										: __( 'From your Stripe Dashboard → Developers → API keys. Required for the card form guests fill in - Stripe stays off on the front end without it.', 'magepeople-yacht-booking-system' )
								}
							>
								<input type="text" value={ settings.stripe_publishable_key || '' } onChange={ ( e ) => set( 'stripe_publishable_key', e.target.value ) } />
							</Field>
							<Field
								label={ __( 'Secret Key', 'magepeople-yacht-booking-system' ) }
								hint={ settings.stripe_secret_key_set ? __( 'A key is already saved. Leave blank to keep it.', 'magepeople-yacht-booking-system' ) : '' }
							>
								<input type="password" value={ settings.stripe_secret_key || '' } onChange={ ( e ) => set( 'stripe_secret_key', e.target.value ) } />
							</Field>
							<Field
								label={ __( 'Webhook Secret', 'magepeople-yacht-booking-system' ) }
								hint={ settings.stripe_webhook_secret_set ? __( 'A secret is already saved. Leave blank to keep it.', 'magepeople-yacht-booking-system' ) : '' }
							>
								<input type="password" value={ settings.stripe_webhook_secret || '' } onChange={ ( e ) => set( 'stripe_webhook_secret', e.target.value ) } />
							</Field>
						</div>
					</ToggleRow>

					<Field label={ __( 'Default Payment Method', 'magepeople-yacht-booking-system' ) }>
						{ settings.payment_methods.length > 0 ? (
							<select value={ settings.default_payment_method } onChange={ ( e ) => set( 'default_payment_method', e.target.value ) }>
								{ settings.payment_methods.map( ( m ) => <option key={ m } value={ m }>{ m }</option> ) }
							</select>
						) : (
							<select disabled>
								<option>{ __( 'Enable a payment method above first', 'magepeople-yacht-booking-system' ) }</option>
							</select>
						) }
					</Field>
				</>
			) }
		</div>
	);
}
