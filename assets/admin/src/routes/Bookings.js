import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { api } from '../api/client';
import { getSlot } from '../registry';
import { navigate } from '../router';
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

export default function Bookings() {
	const [items, setItems] = useState(null);
	const [error, setError] = useState('');
	const [statusFilter, setStatusFilter] = useState('');
	const [deleting, setDeleting] = useState(null);
	const [detailId, setDetailId] = useState(null);

	// The details panel is a Pro feature. Without the add-on the eye button is a
	// locked teaser and a click on the row does nothing.
	const DetailsPanel = getSlot('booking-details');

	// Check-in stamps only ever arrive on a row from the Pro add-on. Without them
	// the column stays, locked, rather than showing a dash for every booking.
	const hasCheckIn = (items || []).some((booking) => 'checked_in_at' in booking);

	const load = () => {
		api.get('/bookings', { per_page: 50, status: statusFilter || undefined })
			.then((res) => setItems(res.items))
			.catch((err) => setError(err.message));
	};

	useEffect(load, [statusFilter]);

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
							<th>{__('Booking No', 'magepeople-yacht-booking-system')}</th>
							<th>
								{hasCheckIn ? __('Check-in', 'magepeople-yacht-booking-system') : (
									<a
										className="ybs-th-locked"
										href="#/check-in"
										title={__('Check-in - available in Pro', 'magepeople-yacht-booking-system')}
									>
										{__('Check-in', 'magepeople-yacht-booking-system')}
										<span className="ybs-pro-tag">{__('Pro', 'magepeople-yacht-booking-system')}</span>
									</a>
								)}
							</th>
							<th>{__('Status', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Actions', 'magepeople-yacht-booking-system')}</th>
						</tr>
					</thead>
					<tbody>
						{items.map((booking) => (
							<tr
								key={booking.id}
								className={DetailsPanel ? 'ybs-row-clickable' : undefined}
								onClick={DetailsPanel ? () => setDetailId(booking.id) : undefined}
							>
								<td>{booking.yacht_name}</td>
								<td>{booking.guest_name}<br /><small>{booking.guest_email}</small></td>
								<td>{typeLabel(booking)}</td>
								<td>{booking.start_formatted || booking.start_datetime}</td>
								<td>{booking.end_formatted || booking.end_datetime || '—'}</td>
								<td>{booking.duration || '—'}</td>
								<td>{booking.currency}{Number(booking.total_price).toFixed(2)}</td>
								{/* The booking's own reference always; the WooCommerce
								    order number only when there is one. */}
								<td onClick={(e) => e.stopPropagation()}>
									<strong>{booking.reference}</strong>
									{booking.woo_order_id ? (
										<>
											<br />
											<a href={booking.woo_order_url} target="_blank" rel="noreferrer">
												<small>{sprintf(__('Order #%d', 'magepeople-yacht-booking-system'), booking.woo_order_id)}</small>
											</a>
										</>
									) : null}
								</td>
								<td className={hasCheckIn ? undefined : 'ybs-td-locked'}>
									{booking.checked_out_at
										? __('Ashore', 'magepeople-yacht-booking-system')
										: booking.checked_in_at
											? __('Aboard', 'magepeople-yacht-booking-system')
											: '—'}
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
								{/*
								 * Icon-only actions. Each carries its own aria-label
								 * naming the booking it acts on, so the control is
								 * still identifiable to a screen reader and in a
								 * tooltip - an unlabelled icon button is just a
								 * shape.
								 */}
								<td onClick={(e) => e.stopPropagation()}>
									<div className="ybs-row-actions">
										{DetailsPanel ? (
											<button
												type="button"
												className="ybs-icon-btn"
												title={__('View details', 'magepeople-yacht-booking-system')}
												aria-label={sprintf(
													/* translators: %s: booking reference, e.g. YB-000123. */
													__('View details for booking %s', 'magepeople-yacht-booking-system'),
													booking.reference
												)}
												onClick={() => setDetailId(booking.id)}
											>
												<span className="dashicons dashicons-visibility" aria-hidden="true" />
											</button>
										) : (
											<button
												type="button"
												className="ybs-icon-btn is-locked"
												title={__('View details - available in Pro', 'magepeople-yacht-booking-system')}
												aria-label={sprintf(
													/* translators: %s: booking reference, e.g. YB-000123. */
													__('View details for booking %s - available in Pro', 'magepeople-yacht-booking-system'),
													booking.reference
												)}
												onClick={() => navigate('booking-details')}
											>
												<span className="dashicons dashicons-visibility" aria-hidden="true" />
												<span className="ybs-icon-btn__pro" aria-hidden="true">
													{__('Pro', 'magepeople-yacht-booking-system')}
												</span>
											</button>
										)}
										{/*
										  * Actions an add-on attached to the row - the Pro add-on's
										  * ticket and invoice PDFs.
										  */}
										{(booking.row_actions || []).map((action) => (
											<a
												key={action.id}
												className="ybs-icon-btn"
												href={action.url}
												target={action.target || undefined}
												rel={action.target ? 'noopener noreferrer' : undefined}
												title={action.label}
												aria-label={sprintf(
													/* translators: 1: action, e.g. "Download ticket", 2: booking reference. */
													__('%1$s for booking %2$s', 'magepeople-yacht-booking-system'),
													action.label,
													booking.reference
												)}
											>
												<span className={'dashicons ' + (action.icon || 'dashicons-admin-links')} aria-hidden="true" />
											</a>
										))}
										<button
											type="button"
											className="ybs-icon-btn is-danger"
											disabled={deleting === booking.id}
											title={__('Delete booking', 'magepeople-yacht-booking-system')}
											aria-label={sprintf(
												/* translators: %s: booking reference, e.g. YB-000123. */
												__('Delete booking %s', 'magepeople-yacht-booking-system'),
												booking.reference
											)}
											onClick={() => deleteBooking(booking.id)}
										>
											<span
												className={
													'dashicons ' +
													(deleting === booking.id ? 'dashicons-update ybs-spin' : 'dashicons-trash')
												}
												aria-hidden="true"
											/>
										</button>
									</div>
								</td>
							</tr>
						))}
					</tbody>
				</table>
			)}

			{detailId && DetailsPanel && (
				<DetailsPanel
					bookingId={detailId}
					onClose={() => setDetailId(null)}
					onChanged={load}
				/>
			)}
		</div>
	);
}
