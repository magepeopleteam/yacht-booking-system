import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
// Same slugs WooCommerce uses for orders - the two are kept in sync 1:1.
const STATUSES = [
	{ value: 'pending', label: __('Pending payment', 'magepeople-yacht-booking-system') },
	{ value: 'processing', label: __('Processing', 'magepeople-yacht-booking-system') },
	{ value: 'on-hold', label: __('On hold', 'magepeople-yacht-booking-system') },
	{ value: 'completed', label: __('Completed', 'magepeople-yacht-booking-system') },
	{ value: 'cancelled', label: __('Cancelled', 'magepeople-yacht-booking-system') },
	{ value: 'refunded', label: __('Refunded', 'magepeople-yacht-booking-system') },
	{ value: 'failed', label: __('Failed', 'magepeople-yacht-booking-system') },
];

const TYPE_LABELS = {
	hourly: __('Hourly', 'magepeople-yacht-booking-system'),
	half_day: __('Half-Day', 'magepeople-yacht-booking-system'),
	morning_slot: __('Morning Slot', 'magepeople-yacht-booking-system'),
	evening_slot: __('Evening Slot', 'magepeople-yacht-booking-system'),
	daily: __('Full Day', 'magepeople-yacht-booking-system'),
	multiday: __('Multi-Day', 'magepeople-yacht-booking-system'),
};

function typeLabel(booking) {
	const type = TYPE_LABELS[booking.booking_type] || booking.booking_type;

	if ('shared' === booking.booking_mode) {
		return `${type} · ${__('Shared', 'magepeople-yacht-booking-system')} (${ /* translators: %d: number of seats booked. */ sprintf(__('%d seats', 'magepeople-yacht-booking-system'), booking.guest_count)})`;
	}

	return `${type} · ${__('Full Charter', 'magepeople-yacht-booking-system')} (${sprintf(__('%d guests', 'magepeople-yacht-booking-system'), booking.guest_count)})`;
}

function money(booking, amount) {
	return `${booking.currency || ''}${Number(amount || 0).toFixed(2)}`;
}

export default function Bookings() {
	const [items, setItems] = useState(null);
	const [error, setError] = useState('');
	const [statusFilter, setStatusFilter] = useState('');
	const [detailFor, setDetailFor] = useState(null);
	const [detail, setDetail] = useState(null);
	const [detailError, setDetailError] = useState('');
	const [deleting, setDeleting] = useState(null);

	const load = () => {
		api.get('/bookings', { per_page: 50, status: statusFilter || undefined })
			.then((res) => setItems(res.items))
			.catch((err) => setError(err.message));
	};

	useEffect(load, [statusFilter]);

	const openDetail = (id) => {
		setDetailFor(id);
		setDetail(null);
		setDetailError('');
		api.get(`/bookings/${id}`)
			.then(setDetail)
			.catch((err) => setDetailError(err.message));
	};

	const closeDetail = () => {
		setDetailFor(null);
		setDetail(null);
		setDetailError('');
	};

	const changeStatus = (id, status) => {
		api.post(`/bookings/${id}/status`, { status }).then(load);
	};

	const deleteBooking = (id) => {
		if (!window.confirm(__('Delete this booking permanently? This cannot be undone.', 'magepeople-yacht-booking-system'))) {
			return;
		}

		setDeleting(id);

		api.del(`/bookings/${id}`)
			.then(() => {
				setDeleting(null);
				load();
			})
			.catch((err) => {
				setDeleting(null);
				setError(err.message);
			});
	};

	return (
		<div>
			<div className="ybs-page-header">
				<div>
					<h2>{__('Bookings', 'magepeople-yacht-booking-system')}</h2>
					<p>{__('All bookings across every yacht.', 'magepeople-yacht-booking-system')}</p>
				</div>
				<select className="ybs-select" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
					<option value="">{__('All Statuses', 'magepeople-yacht-booking-system')}</option>
					{STATUSES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
				</select>
			</div>

			{error && <div className="ybs-notice is-error">{error}</div>}
			{!items && !error && <div className="ybs-loading">{__('Loading…', 'magepeople-yacht-booking-system')}</div>}

			{items && items.length === 0 && <div className="ybs-empty-state">{__('No bookings found.', 'magepeople-yacht-booking-system')}</div>}

			{items && items.length > 0 && (
				<table className="ybs-table">
					<thead>
						<tr>
							<th>{__('Yacht', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Guest', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Type', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Date', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Ends At', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Duration', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Total', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Order', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Status', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Actions', 'magepeople-yacht-booking-system')}</th>
						</tr>
					</thead>
					<tbody>
						{items.map((booking) => (
							<tr key={booking.id} onClick={() => openDetail(booking.id)} style={{ cursor: 'pointer' }}>
								<td>{booking.yacht_name}</td>
								<td>{booking.guest_name}<br /><small>{booking.guest_email}</small></td>
								<td>{typeLabel(booking)}</td>
								<td>{booking.start_formatted || booking.start_datetime}</td>
								<td>{booking.end_formatted || booking.end_datetime || '—'}</td>
								<td>{booking.duration || '—'}</td>
								<td>{booking.currency}{Number(booking.total_price).toFixed(2)}</td>
								<td onClick={(e) => e.stopPropagation()}>
									{booking.woo_order_id ? (
										<a href={booking.woo_order_url} target="_blank" rel="noreferrer">
											{__('Order #', 'magepeople-yacht-booking-system') + booking.woo_order_id}
										</a>
									) : (
										<span>—</span>
									)}
								</td>
								<td onClick={(e) => e.stopPropagation()}>
									<select
										className={'ybs-badge status-' + booking.status}
										value={booking.status}
										onChange={(e) => changeStatus(booking.id, e.target.value)}
									>
										{STATUSES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
									</select>
								</td>
								<td onClick={(e) => e.stopPropagation()}>
									<button
										type="button"
										className="ybs-btn is-danger"
										disabled={deleting === booking.id}
										onClick={() => deleteBooking(booking.id)}
									>
										{deleting === booking.id ? __('Deleting…', 'magepeople-yacht-booking-system') : __('Delete', 'magepeople-yacht-booking-system')}
									</button>
								</td>
							</tr>
						))}
					</tbody>
				</table>
			)}

			{detailFor && (
				<div className="ybs-modal-backdrop" onClick={closeDetail}>
					<div className="ybs-modal" onClick={(e) => e.stopPropagation()}>
						<div className="ybs-modal__head">
							<h3>
								{/* translators: %d: booking id. */}
								{sprintf(__('Booking #%d', 'magepeople-yacht-booking-system'), detailFor)}
							</h3>
							<button type="button" className="ybs-btn" onClick={closeDetail}>
								{__('Close', 'magepeople-yacht-booking-system')}
							</button>
						</div>

						{detailError && <div className="ybs-notice is-error">{detailError}</div>}
						{!detail && !detailError && <div className="ybs-loading">{__('Loading…', 'magepeople-yacht-booking-system')}</div>}

						{detail && (
							<div className="ybs-modal__body">
								<h4>{__('Charter', 'magepeople-yacht-booking-system')}</h4>
								<table className="ybs-table ybs-table--kv">
									<tbody>
										<tr><th>{__('Yacht', 'magepeople-yacht-booking-system')}</th><td>{detail.yacht_name || '—'}</td></tr>
										<tr><th>{__('Type', 'magepeople-yacht-booking-system')}</th><td>{typeLabel(detail)}</td></tr>
										<tr><th>{__('Starts', 'magepeople-yacht-booking-system')}</th><td>{detail.start_formatted || detail.start_datetime}</td></tr>
										<tr><th>{__('Ends', 'magepeople-yacht-booking-system')}</th><td>{detail.end_formatted || detail.end_datetime || '—'}</td></tr>
										<tr><th>{__('Duration', 'magepeople-yacht-booking-system')}</th><td>{detail.duration || '—'}</td></tr>
										<tr><th>{__('Guests', 'magepeople-yacht-booking-system')}</th><td>{detail.guest_count}</td></tr>
									</tbody>
								</table>

								<h4>{__('Guest', 'magepeople-yacht-booking-system')}</h4>
								<table className="ybs-table ybs-table--kv">
									<tbody>
										<tr><th>{__('Name', 'magepeople-yacht-booking-system')}</th><td>{detail.guest_name || '—'}</td></tr>
										<tr><th>{__('Email', 'magepeople-yacht-booking-system')}</th><td>{detail.guest_email ? <a href={'mailto:' + detail.guest_email}>{detail.guest_email}</a> : '—'}</td></tr>
										<tr><th>{__('Phone', 'magepeople-yacht-booking-system')}</th><td>{detail.guest_phone || '—'}</td></tr>
									</tbody>
								</table>

								<h4>{__('Price breakdown', 'magepeople-yacht-booking-system')}</h4>
								<table className="ybs-table ybs-table--kv">
									<tbody>
										<tr><th>{__('Base price', 'magepeople-yacht-booking-system')}</th><td>{money(detail, detail.base_price)}</td></tr>
										{detail.addons_total > 0 && <tr><th>{__('Add-ons', 'magepeople-yacht-booking-system')}</th><td>{money(detail, detail.addons_total)}</td></tr>}
										{detail.discount_total > 0 && <tr><th>{__('Discount', 'magepeople-yacht-booking-system')}</th><td>−{money(detail, detail.discount_total)}</td></tr>}
										{detail.tax_total > 0 && <tr><th>{__('Tax', 'magepeople-yacht-booking-system')}</th><td>{money(detail, detail.tax_total)}</td></tr>}
										{detail.deposit_amount > 0 && <tr><th>{__('Deposit', 'magepeople-yacht-booking-system')}</th><td>{money(detail, detail.deposit_amount)}</td></tr>}
										<tr><th>{__('Total', 'magepeople-yacht-booking-system')}</th><td><strong>{money(detail, detail.total_price)}</strong></td></tr>
									</tbody>
								</table>

								<h4>{__('Payment', 'magepeople-yacht-booking-system')}</h4>
								<table className="ybs-table ybs-table--kv">
									<tbody>
										<tr><th>{__('Status', 'magepeople-yacht-booking-system')}</th><td><span className={'ybs-badge status-' + detail.status}>{detail.status}</span></td></tr>
										<tr><th>{__('Method', 'magepeople-yacht-booking-system')}</th><td>{detail.payment_method || '—'}</td></tr>
										<tr><th>{__('Payment state', 'magepeople-yacht-booking-system')}</th><td>{detail.payment_status || '—'}</td></tr>
										<tr><th>{__('Transaction', 'magepeople-yacht-booking-system')}</th><td>{detail.transaction_ref || '—'}</td></tr>
										{detail.woo_order_id > 0 && (
											<tr>
												<th>{__('Order', 'magepeople-yacht-booking-system')}</th>
												<td><a href={detail.woo_order_url} target="_blank" rel="noreferrer">{'#' + detail.woo_order_id}</a></td>
											</tr>
										)}
										<tr><th>{__('Booked on', 'magepeople-yacht-booking-system')}</th><td>{detail.created_formatted || detail.created_at || '—'}</td></tr>
									</tbody>
								</table>

								{detail.notes && (
									<>
										<h4>{__('Notes', 'magepeople-yacht-booking-system')}</h4>
										<p className="ybs-hint">{detail.notes}</p>
									</>
								)}
							</div>
						)}
					</div>
				</div>
			)}
		</div>
	);
}
