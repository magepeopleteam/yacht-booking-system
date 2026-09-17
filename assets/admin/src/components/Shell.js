import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { navigate } from '../router';
import { PRO_FEATURES } from '../proFeatures';
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
 * The rail: built-ins, then whatever add-ons registered, then a locked entry
 * for each Pro screen no add-on claimed.
 *
 * The locked entries exist precisely for the case where Pro is *not*
 * installed, so an id an add-on has actually registered drops its stand-in -
 * otherwise activating the add-on would leave two "Coupons" in the rail.
 */
function navItems() {
	const extra = extraNav();
	const claimed = new Set(extra.map((item) => item.id));
	const locked = PRO_FEATURES
		.filter((feature) => false !== feature.nav && !claimed.has(feature.id))
		.map((feature) => ({ ...feature, pro: true }));

	return [...BUILTIN_NAV, ...extra, ...locked, ...TRAILING_NAV];
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
