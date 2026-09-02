import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { navigate } from '../router';
import { ToastHost } from './Toast';

const BUILTIN_NAV = [
	{ id: 'dashboard', label: __( 'Dashboard', 'magepeople-yacht-booking-system' ), icon: 'dashicons-chart-area' },
	{ id: 'yachts', label: __( 'Yachts', 'magepeople-yacht-booking-system' ), icon: 'dashicons-palmtree' },
	{ id: 'bookings', label: __( 'Bookings', 'magepeople-yacht-booking-system' ), icon: 'dashicons-tickets-alt' },
	{ id: 'calendar', label: __( 'Calendar', 'magepeople-yacht-booking-system' ), icon: 'dashicons-calendar-alt' },
	{ id: 'guests', label: __( 'Guests', 'magepeople-yacht-booking-system' ), icon: 'dashicons-groups' },
	{ id: 'settings', label: __( 'Settings', 'magepeople-yacht-booking-system' ), icon: 'dashicons-admin-generic' },
];

function extraNav() {
	const config = window.ybsAdminConfig || {};
	return Array.isArray( config.extraRoutes ) ? config.extraRoutes : [];
}

export default function Shell( { active, children } ) {
	const [ open, setOpen ] = useState( false );
	const navItems = [ ...BUILTIN_NAV, ...extraNav() ];

	return (
		<div className="ybs-shell">
			<div className="ybs-shell-scrim" hidden={ ! open } onClick={ () => setOpen( false ) } />

			<nav className={ 'ybs-shell-rail' + ( open ? ' is-open' : '' ) }>
				<div className="ybs-shell-rail__top">
					<span className="ybs-shell-rail__mark">
						<span className="dashicons dashicons-palmtree" />
					</span>
					<span className="ybs-shell-rail__brand-text">
						{ __( 'Yacht Booking', 'magepeople-yacht-booking-system' ) }
					</span>
				</div>

				<ul className="ybs-shell-rail__menu">
					{ navItems.map( ( item ) => (
						<li key={ item.id } className={ active === item.id ? 'is-active' : '' }>
							<a
								href={ '#/' + item.id }
								onClick={ ( event ) => {
									event.preventDefault();
									navigate( item.id );
									setOpen( false );
								} }
							>
								<span className={ 'dashicons ' + item.icon } />
								<span className="ybs-shell-rail__label">{ item.label }</span>
							</a>
						</li>
					) ) }
				</ul>

				<a className="ybs-shell-rail__back" href={ window.ybsAdminConfig?.adminUrl || '#' }>
					<span className="dashicons dashicons-arrow-left-alt2" />
					{ __( 'Back to WordPress', 'magepeople-yacht-booking-system' ) }
				</a>
			</nav>

			<div className="ybs-shell-main">
				<header className="ybs-shell-topbar">
					<button
						type="button"
						className="ybs-shell__burger"
						onClick={ () => setOpen( ( value ) => ! value ) }
						aria-label={ __( 'Toggle navigation', 'magepeople-yacht-booking-system' ) }
					>
						<span className="dashicons dashicons-menu-alt2" />
					</button>
					<span className="ybs-shell__brand-mini">{ __( 'Yacht Booking System', 'magepeople-yacht-booking-system' ) }</span>
				</header>

				<main className="ybs-shell__content">{ children }</main>
			</div>

			<ToastHost />
		</div>
	);
}
