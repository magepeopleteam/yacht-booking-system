import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Modal, Button } from '@wordpress/components';
import { api } from '../api/client';
import { toast } from './Toast';
import PaymentMethodFields from './PaymentMethodFields';

/**
 * The wizard's "Configure Payments" popup - edits the same site-wide
 * `/settings` payment configuration as the main Settings screen (payment
 * methods aren't per-yacht), just reachable without leaving the wizard.
 */
export default function PaymentSettingsModal({ onRequestClose }) {
	const [settings, setSettings] = useState(null);
	const [saving, setSaving] = useState(false);

	useEffect(() => {
		api.get('/settings')
			.then(setSettings)
			.catch((err) => toast(err.message, 'error'));
	}, []);

	const save = () => {
		setSaving(true);

		api.put('/settings', settings)
			.then(() => {
				setSaving(false);
				toast(__('Payment settings saved.', 'magepeople-yacht-booking-system'));
				onRequestClose();
			})
			.catch((err) => {
				setSaving(false);
				toast(err.message, 'error');
			});
	};

	return (
		<Modal
			title={__('Payment Settings', 'magepeople-yacht-booking-system')}
			onRequestClose={onRequestClose}
			className="ybs-payment-modal"
		>
			{!settings && <div className="ybs-loading">{__('Loading…', 'magepeople-yacht-booking-system')}</div>}

			{settings && (
				<>
					<PaymentMethodFields settings={settings} onSettingsChange={setSettings} />

					<div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 20 }}>
						<Button variant="tertiary" onClick={onRequestClose}>
							{__('Cancel', 'magepeople-yacht-booking-system')}
						</Button>
						<Button variant="primary" onClick={save} isBusy={saving} disabled={saving}>
							{__('Save Payment Settings', 'magepeople-yacht-booking-system')}
						</Button>
					</div>
				</>
			)}
		</Modal>
	);
}
