import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import PaymentSettingsModal from './PaymentSettingsModal';

const METHOD_LABELS = {
	offline: __('Offline', 'magepeople-yacht-booking-system'),
	paypal: __('PayPal', 'magepeople-yacht-booking-system'),
	stripe: __('Stripe', 'magepeople-yacht-booking-system'),
};

export default function PaymentSettingsCard() {
	const [open, setOpen] = useState(false);
	const [active, setActive] = useState(null);

	const loadSummary = () => {
		api.get('/settings').then((settings) => {
			if (settings.woocommerce_enabled) {
				api.get('/settings/woocommerce/gateways')
					.then((gateways) => {
						const enabled = gateways.filter((g) => g.enabled).map((g) => g.title);
						setActive(enabled.length ? enabled : [__('WooCommerce (nothing enabled yet)', 'magepeople-yacht-booking-system')]);
					})
					.catch(() => setActive([__('WooCommerce', 'magepeople-yacht-booking-system')]));
			} else {
				const enabled = settings.payment_methods.map((m) => METHOD_LABELS[m] || m);
				setActive(enabled.length ? enabled : [__('None enabled yet', 'magepeople-yacht-booking-system')]);
			}
		});
	};

	useEffect(loadSummary, []);

	const closeModal = () => {
		setOpen(false);
		loadSummary();
	};

	return (
		<div className="ybs-wcard">
			<div className="ybs-wcard__head">
				<h3>{__('Payment Settings', 'magepeople-yacht-booking-system')}</h3>
				<p>{__('Offline, PayPal, Stripe, or WooCommerce.', 'magepeople-yacht-booking-system')}</p>
			</div>
			<div className="ybs-wcard__body">
				<div className="ybs-payment-summary">
					<span className="ybs-payment-summary__label">{__('Currently active', 'magepeople-yacht-booking-system')}</span>
					<div className="ybs-payment-summary__pills">
						{active
							? active.map((label) => (
								<span className="ybs-badge status-paid" key={label}>{label}</span>
							))
							: <span className="ybs-hint">{__('Loading…', 'magepeople-yacht-booking-system')}</span>}
					</div>
				</div>

				<button
					type="button"
					className="ybs-btn is-primary"
					onClick={() => setOpen(true)}
					style={{ width: '100%', justifyContent: 'center' }}
				>
					{__('Configure Payments', 'magepeople-yacht-booking-system')}
				</button>
			</div>

			{open && <PaymentSettingsModal onRequestClose={closeModal} />}
		</div>
	);
}
