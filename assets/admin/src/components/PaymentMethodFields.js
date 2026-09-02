import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
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

function ToggleRow({ label, checked, disabled, onChange, children }) {
	return (
		<div className={'ybs-toggle-row' + (disabled ? ' is-disabled' : '')}>
			<label className="ybs-toggle">
				<input type="checkbox" checked={checked} disabled={disabled} onChange={onChange} />
				<span className="ybs-toggle__track"><span className="ybs-toggle__thumb" /></span>
				<span className="ybs-toggle__label">{label}</span>
			</label>
			{checked && !disabled && children && <div className="ybs-toggle-row__fields">{children}</div>}
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
export default function PaymentMethodFields({ settings, onSettingsChange }) {
	const [installing, setInstalling] = useState(false);
	const wcMode = !!settings.woocommerce_enabled;

	const set = (key, value) => onSettingsChange({ ...settings, [key]: value });

	const toggleMethod = (id) => {
		const methods = settings.payment_methods.includes(id)
			? settings.payment_methods.filter((m) => m !== id)
			: [...settings.payment_methods, id];
		set('payment_methods', methods);
	};

	const toggleWooCommerce = () => {
		if (wcMode) {
			// Leaving WooCommerce mode - fall back to Offline so the site is
			// never left with zero payment methods enabled.
			onSettingsChange({
				...settings,
				woocommerce_enabled: false,
				payment_methods: ['offline'],
				default_payment_method: 'offline',
			});
		} else {
			// Entering WooCommerce mode - the native group is mutually exclusive.
			onSettingsChange({
				...settings,
				woocommerce_enabled: true,
				payment_methods: [],
				default_payment_method: 'woocommerce',
			});
		}
	};

	const installWooCommerce = () => {
		setInstalling(true);

		api.post('/settings/woocommerce/install')
			.then((data) => {
				setInstalling(false);
				onSettingsChange({ ...settings, ...data, payment_methods: [], default_payment_method: 'woocommerce' });
				toast(__('WooCommerce is installed and active. Cash on Delivery is enabled by default - turn on more methods below. Refresh the page to see it reflected everywhere in wp-admin.', 'magepeople-yacht-booking-system'));
			})
			.catch((err) => {
				setInstalling(false);
				toast(err.message, 'error');
			});
	};

	return (
		<div className="ybs-payment-methods">
			<p className="ybs-hint" style={{ marginTop: 0, marginBottom: 12 }}>
				{__('Choose either your own payment methods or WooCommerce checkout - not both.', 'magepeople-yacht-booking-system')}
			</p>

			<ToggleRow
				label={__('Offline / Manual Payment', 'magepeople-yacht-booking-system')}
				checked={settings.payment_methods.includes('offline')}
				disabled={wcMode}
				onChange={() => toggleMethod('offline')}
			>
				<Field label={__('Instructions shown to the guest', 'magepeople-yacht-booking-system')}>
					<textarea
						rows={2}
						value={settings.offline_instructions || ''}
						onChange={(e) => set('offline_instructions', e.target.value)}
					/>
				</Field>
			</ToggleRow>

			<ToggleRow
				label={__('PayPal', 'magepeople-yacht-booking-system')}
				checked={settings.payment_methods.includes('paypal')}
				disabled={wcMode}
				onChange={() => toggleMethod('paypal')}
			>
				<div className="ybs-field-row">
					<Field label={__('PayPal Email', 'magepeople-yacht-booking-system')}>
						<input type="text" value={settings.paypal_email || ''} onChange={(e) => set('paypal_email', e.target.value)} />
					</Field>
					<Field label={__('Mode', 'magepeople-yacht-booking-system')}>
						<select value={settings.paypal_mode} onChange={(e) => set('paypal_mode', e.target.value)}>
							<option value="sandbox">{__('Sandbox', 'magepeople-yacht-booking-system')}</option>
							<option value="live">{__('Live', 'magepeople-yacht-booking-system')}</option>
						</select>
					</Field>
				</div>
			</ToggleRow>

			<ToggleRow
				label={__('Stripe', 'magepeople-yacht-booking-system')}
				checked={settings.payment_methods.includes('stripe')}
				disabled={wcMode}
				onChange={() => toggleMethod('stripe')}
			>
				<div className="ybs-field-row">
					<Field label={__('Publishable Key', 'magepeople-yacht-booking-system')}>
						<input type="text" value={settings.stripe_publishable_key || ''} onChange={(e) => set('stripe_publishable_key', e.target.value)} />
					</Field>
					<Field
						label={__('Secret Key', 'magepeople-yacht-booking-system')}
						hint={settings.stripe_secret_key_set ? __('A key is already saved. Leave blank to keep it.', 'magepeople-yacht-booking-system') : ''}
					>
						<input type="password" value={settings.stripe_secret_key || ''} onChange={(e) => set('stripe_secret_key', e.target.value)} />
					</Field>
					<Field
						label={__('Webhook Secret', 'magepeople-yacht-booking-system')}
						hint={settings.stripe_webhook_secret_set ? __('A secret is already saved. Leave blank to keep it.', 'magepeople-yacht-booking-system') : ''}
					>
						<input type="password" value={settings.stripe_webhook_secret || ''} onChange={(e) => set('stripe_webhook_secret', e.target.value)} />
					</Field>
				</div>
			</ToggleRow>

			<div className="ybs-payment-group-divider">
				<span>{__('or', 'magepeople-yacht-booking-system')}</span>
			</div>

			{settings.woocommerce_active ? (
				<ToggleRow
					label={__('WooCommerce Checkout', 'magepeople-yacht-booking-system')}
					checked={wcMode}
					onChange={toggleWooCommerce}
				>
					<WooCommerceGatewayList />
				</ToggleRow>
			) : (
				<div className="ybs-toggle-row">
					<div className="ybs-toggle__label" style={{ marginBottom: 8 }}>
						{__('WooCommerce Checkout', 'magepeople-yacht-booking-system')}
					</div>
					<div className="ybs-notice is-info" style={{ margin: 0 }}>
						<p style={{ margin: '0 0 8px' }}>
							{__('Install WooCommerce to accept payments through it. Cash on Delivery will be enabled automatically once it is active - you can turn on more methods afterward.', 'magepeople-yacht-booking-system')}
						</p>
						<button type="button" className="ybs-btn is-primary" onClick={installWooCommerce} disabled={installing}>
							{installing ? __('Installing…', 'magepeople-yacht-booking-system') : __('Install & Activate WooCommerce', 'magepeople-yacht-booking-system')}
						</button>
					</div>
				</div>
			)}

			{!wcMode && (
				<Field label={__('Default Payment Method', 'magepeople-yacht-booking-system')}>
					{settings.payment_methods.length > 0 ? (
						<select value={settings.default_payment_method} onChange={(e) => set('default_payment_method', e.target.value)}>
							{settings.payment_methods.map((m) => <option key={m} value={m}>{m}</option>)}
						</select>
					) : (
						<select disabled>
							<option>{__('Enable a payment method above first', 'magepeople-yacht-booking-system')}</option>
						</select>
					)}
				</Field>
			)}
		</div>
	);
}
