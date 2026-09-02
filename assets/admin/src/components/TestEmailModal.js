import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Modal, Button } from '@wordpress/components';
import { api } from '../api/client';
import { toast } from './Toast';

/**
 * Sends a one-off preview of whatever subject/body is currently in the
 * form - including unsaved edits - with sample data standing in for the
 * dynamic tags. Shared by the global Settings > Email tab and a yacht's
 * own confirmation email editor in the wizard, since both just hand it
 * their current in-memory subject/body/sender fields.
 */
export default function TestEmailModal({ subject, body, fromName, fromEmail, onRequestClose }) {
	const [to, setTo] = useState(window.ybsAdminConfig?.adminEmail || '');
	const [sending, setSending] = useState(false);

	const send = () => {
		setSending(true);

		api.post('/settings/email/test', {
			to,
			subject,
			body,
			from_name: fromName,
			from_email: fromEmail,
		})
			.then((data) => {
				setSending(false);
				toast(data.message || __('Test email sent.', 'magepeople-yacht-booking-system'));
				onRequestClose();
			})
			.catch((err) => {
				setSending(false);
				toast(err.message, 'error');
			});
	};

	return (
		<Modal
			title={__('Send Test Email', 'magepeople-yacht-booking-system')}
			onRequestClose={onRequestClose}
			className="ybs-test-email-modal"
		>
			<p className="ybs-hint" style={{ marginTop: 0 }}>
				{__('Sends the current subject and body - including unsaved changes - with sample data in place of the dynamic tags.', 'magepeople-yacht-booking-system')}
			</p>

			<div className="ybs-field">
				<label>{__('Send To', 'magepeople-yacht-booking-system')}</label>
				<input
					type="email"
					value={to}
					onChange={(e) => setTo(e.target.value)}
					placeholder="you@example.com"
				/>
			</div>

			<div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 20 }}>
				<Button variant="tertiary" onClick={onRequestClose}>
					{__('Cancel', 'magepeople-yacht-booking-system')}
				</Button>
				<Button variant="primary" onClick={send} isBusy={sending} disabled={sending || !to}>
					{__('Send Test', 'magepeople-yacht-booking-system')}
				</Button>
			</div>
		</Modal>
	);
}
