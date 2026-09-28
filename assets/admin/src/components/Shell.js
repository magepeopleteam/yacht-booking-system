import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { navigate } from '../router';
import { navFeatures } from '../proFeatures';
import { ToastHost } from './Toast';

const BUILTIN_NAV = [
	{ id: 'dashboard', label: __('Dashboard', 'magepeople-yacht-booking-system'), icon: 'dashicons-chart-area' },
	{ id: 'yachts', label: __('Yachts', 'magepeople-yacht-booking-system'), icon: 'dashicons-palmtree' },
	{ id: 'bookings', label: __('Bookings', 'magepeople-yacht-booking-system'), icon: 'dashicons-tickets-alt' },
	{ id: 'calendar', label: __('Calendar', 'magepeople-yacht-booking-system'), icon: 'dashicons-calendar-alt' },
	{ id: 'guests', label: __('Guests', 'magepeople-yacht-booking-system'), icon: 'dashicons-groups' },
	{ id: 'addons', label: __('Add-ons', 'magepeople-yacht-booking-system'), icon: 'dashicons-cart' },
];

// Kept apart from the list above so add-on screens slot in between the two,
// rather than after Settings and the User Guide.
const TRAILING_NAV = [
	{ id: 'settings', label: __('Settings', 'magepeople-yacht-booking-system'), icon: 'dashicons-admin-generic' },
	{ id: 'user-guide', label: __('User Guide', 'magepeople-yacht-booking-system'), icon: 'dashicons-book-alt' },
];

function extraNav() {
	const config = window.mageyaboAdminConfig || {};
	return Array.isArray(config.extraRoutes) ? config.extraRoutes : [];
}

/**
 * The rail: built-ins, then whatever add-ons registered, then a single
 * "Pro Features" entry if any Pro screen is still unclaimed.
 *
 * That one entry stands in for every locked screen at once - the details of
 * which screens those are, and their descriptions, live on the page it opens
 * (see routes/ProFeatures.js) rather than as one rail row per feature. Once
 * an add-on registers all of them, the entry drops on its own.
 */
function navItems() {
	const extra = extraNav();
	const proFeaturesEntry = navFeatures().length
		? [{
			id: 'pro-features',
			label: __('Pro Features', 'magepeople-yacht-booking-system'),
			icon: 'dashicons-star-filled',
			pro: true,
		}]
		: [];

	return [...BUILTIN_NAV, ...extra, ...proFeaturesEntry, ...TRAILING_NAV];
}

export default function Shell({ active, children }) {
	const [open, setOpen] = useState(false);
	const items = navItems();

	return (
		<div className="ybs-shell">
			<div className="ybs-shell-scrim" hidden={!open} onClick={() => setOpen(false)} />

			<nav className={'ybs-shell-rail' + (open ? ' is-open' : '')}>
				<div className="ybs-shell-rail__top">
					<span className="ybs-shell-rail__mark">
						<span className="dashicons dashicons-palmtree" />
					</span>
					<span className="ybs-shell-rail__brand-text">
						{__('Yacht Booking', 'magepeople-yacht-booking-system')}
					</span>
				</div>

				<ul className="ybs-shell-rail__menu">
					{items.map((item) => (
						<li
							key={item.id}
							className={
								(active === item.id ? 'is-active' : '') + (item.pro ? ' is-pro' : '')
							}
						>
							<a
								href={'#/' + item.id}
								onClick={(event) => {
									event.preventDefault();
									navigate(item.id);
									setOpen(false);
								}}
							>
								<span className={'dashicons ' + item.icon} />
								<span className="ybs-shell-rail__label">{item.label}</span>
								{item.pro && (
									<span className="ybs-shell-rail__pro">
										{__('Pro', 'magepeople-yacht-booking-system')}
									</span>
								)}
							</a>
						</li>
					))}
				</ul>

				<a className="ybs-shell-rail__back" href={window.mageyaboAdminConfig?.adminUrl || '#'}>
					<span className="dashicons dashicons-arrow-left-alt2" />
					{__('Back to WordPress', 'magepeople-yacht-booking-system')}
				</a>
			</nav>

			<div className="ybs-shell-main">
				<button
					type="button"
					className="ybs-shell__burger"
					onClick={() => setOpen((value) => !value)}
					aria-label={__('Toggle navigation', 'magepeople-yacht-booking-system')}
					aria-expanded={open}
				>
					<span className="dashicons dashicons-menu-alt2" />
				</button>

				<main className="ybs-shell__content">{children}</main>
			</div>

			<ToastHost />
		</div>
	);
}
