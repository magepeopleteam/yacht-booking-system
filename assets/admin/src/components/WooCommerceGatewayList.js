import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import { toast } from './Toast';

/**
 * The actual list of registered WooCommerce payment gateways (COD, Bank
 * Transfer, and anything a WooCommerce extension adds) with a quick
 * enable/disable toggle per gateway - reads/writes WooCommerce's own
 * `woocommerce_{id}_settings` option, so it stays in sync with WooCommerce's
 * own Settings > Payments screen automatically.
 */
export default function WooCommerceGatewayList() {
	const [gateways, setGateways] = useState(null);
	const [busyId, setBusyId] = useState('');

	const load = () => {
		api.get('/settings/woocommerce/gateways')
			.then(setGateways)
			.catch((err) => toast(err.message, 'error'));
	};

	useEffect(() => {
		load();
	}, []);

	const toggle = (gateway) => {
		setBusyId(gateway.id);

		api.post(`/settings/woocommerce/gateways/${gateway.id}/toggle`, { enabled: !gateway.enabled })
			.then((updated) => {
				setGateways(updated);
				setBusyId('');
			})
			.catch((err) => {
				setBusyId('');
				toast(err.message, 'error');
			});
	};

	if (!gateways) {
		return <p className="ybs-hint">{__('Loading payment methods…', 'magepeople-yacht-booking-system')}</p>;
	}

	if (!gateways.length) {
		return <p className="ybs-hint">{__('WooCommerce has no registered payment gateways yet.', 'magepeople-yacht-booking-system')}</p>;
	}

	return (
		<div className="ybs-wc-gateways">
			{gateways.map((gateway) => (
				<div className={'ybs-gw-card' + (gateway.enabled ? ' is-enabled' : '')} key={gateway.id}>
					<div className="ybs-gw-card__head">
						<label className="ybs-toggle">
							<input
								type="checkbox"
								checked={gateway.enabled}
								disabled={busyId === gateway.id}
								onChange={() => toggle(gateway)}
							/>
							<span className="ybs-toggle__track"><span className="ybs-toggle__thumb" /></span>
						</label>
						<span className="ybs-gw-card__title">{gateway.title}</span>
						<span className={'ybs-gw-card__badge' + (gateway.enabled ? ' is-active' : '')}>
							{gateway.enabled ? __('Enabled', 'magepeople-yacht-booking-system') : __('Disabled', 'magepeople-yacht-booking-system')}
						</span>
					</div>
					{gateway.description && <p className="ybs-gw-card__desc">{gateway.description}</p>}
				</div>
			))}
		</div>
	);
}
