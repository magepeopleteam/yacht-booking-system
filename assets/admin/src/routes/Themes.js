import { __, sprintf } from '@wordpress/i18n';

const FEATURES = [
	[ 'dashicons-admin-home', __( 'Three homepage designs', 'magepeople-yacht-booking-system' ), __( 'Choose a fleet marketplace layout or a homepage built around a single yacht.', 'magepeople-yacht-booking-system' ) ],
	[ 'dashicons-edit-page', __( 'Edit with Elementor', 'magepeople-yacht-booking-system' ), __( 'Customize homepage sections, images, text and calls to action with Yachtiva widgets.', 'magepeople-yacht-booking-system' ) ],
	[ 'dashicons-search', __( 'Fleet search and yacht pages', 'magepeople-yacht-booking-system' ), __( 'Show your fleet, yacht classes and occasions alongside live search and booking forms.', 'magepeople-yacht-booking-system' ) ],
	[ 'dashicons-admin-customizer', __( 'Your brand, on every screen', 'magepeople-yacht-booking-system' ), __( 'Responsive layouts with settings for your logo, colors, typography, header and footer.', 'magepeople-yacht-booking-system' ) ],
	[ 'dashicons-download', __( 'Guided setup and demo import', 'magepeople-yacht-booking-system' ), __( 'After activation, use Yachtiva Setup to install required plugins and import sample content.', 'magepeople-yacht-booking-system' ) ],
	[ 'dashicons-tickets-alt', __( 'Built for Yacht Booking', 'magepeople-yacht-booking-system' ), __( 'Your yachts, rates, availability, bookings and payments stay managed by the booking plugin.', 'magepeople-yacht-booking-system' ) ],
];

export default function Themes() {
	const theme = window.mageyaboAdminConfig?.theme;
	if ( ! theme ) {
		return <p>{ __( 'You do not have permission to manage theme settings.', 'magepeople-yacht-booking-system' ) }</p>;
	}

	const status = theme.active
		? __( 'Active theme', 'magepeople-yacht-booking-system' )
		: theme.installed
			? __( 'Installed', 'magepeople-yacht-booking-system' )
			: theme.available
				? __( 'Included with Pro', 'magepeople-yacht-booking-system' )
				: __( 'Available with Pro', 'magepeople-yacht-booking-system' );

	return (
		<div className="ybs-themes">
			<div className="ybs-page-header">
				<div>
					<h2>{ __( 'Themes', 'magepeople-yacht-booking-system' ) }</h2>
					<p>{ __( 'Give your yacht booking website a complete charter design.', 'magepeople-yacht-booking-system' ) }</p>
				</div>
			</div>
			<section className="ybs-card ybs-theme-showcase" aria-labelledby="ybs-theme-name">
				<a className="ybs-theme-preview" href={ theme.demoUrl } target="_blank" rel="noopener noreferrer">
					<img src={ theme.screenshot } alt={ __( 'Screenshot of the Yachtiva demo website at wpyacht.com', 'magepeople-yacht-booking-system' ) } referrerPolicy="no-referrer" />
					<span>{ __( 'Preview demo website', 'magepeople-yacht-booking-system' ) }</span>
				</a>
				<div className="ybs-theme-details">
					<span className="ybs-theme-status">{ status }</span>
					<h3 id="ybs-theme-name">{ theme.name }</h3>
					<p className="ybs-hint">{ sprintf( /* translators: %s: theme author. */ __( 'By %s', 'magepeople-yacht-booking-system' ), theme.author ) }</p>
					<p>{ __( 'A yacht and boat charter theme with deep navy and gold styling, editable homepages, fleet listings and a guided setup.', 'magepeople-yacht-booking-system' ) }</p>
					<dl className="ybs-theme-specs">
						{ theme.version && <><dt>{ __( 'Version', 'magepeople-yacht-booking-system' ) }</dt><dd>{ theme.version }</dd></> }
						<dt>{ __( 'Requirements', 'magepeople-yacht-booking-system' ) }</dt><dd>{ sprintf( /* translators: 1: minimum WordPress version, 2: minimum PHP version. */ __( 'WordPress %1$s+ and PHP %2$s+', 'magepeople-yacht-booking-system' ), theme.requiresWp, theme.requiresPhp ) }</dd>
						<dt>{ __( 'Required plugins', 'magepeople-yacht-booking-system' ) }</dt><dd>{ __( 'Yacht Booking System and Elementor', 'magepeople-yacht-booking-system' ) }</dd>
						<dt>{ __( 'Optional', 'magepeople-yacht-booking-system' ) }</dt><dd>{ __( 'WooCommerce for WooCommerce checkout', 'magepeople-yacht-booking-system' ) }</dd>
					</dl>
					<div className="ybs-theme-actions">
						{ ! theme.available && <a className="ybs-btn is-primary" href={ theme.purchaseUrl } target="_blank" rel="noopener noreferrer">{ __( 'Buy Pro to unlock Yachtiva', 'magepeople-yacht-booking-system' ) }</a> }
						{ theme.available && ! theme.installed && theme.installUrl && <a className="ybs-btn is-primary" href={ theme.installUrl }>{ __( 'Install Yachtiva', 'magepeople-yacht-booking-system' ) }</a> }
						{ theme.activateUrl && <a className="ybs-btn is-primary" href={ theme.activateUrl }>{ __( 'Activate Yachtiva', 'magepeople-yacht-booking-system' ) }</a> }
						{ theme.installed && theme.themesUrl && <a className="ybs-btn" href={ theme.themesUrl }>{ __( 'Manage in Appearance → Themes', 'magepeople-yacht-booking-system' ) }</a> }
						<a className="ybs-btn" href={ theme.demoUrl } target="_blank" rel="noopener noreferrer">{ __( 'View live demo', 'magepeople-yacht-booking-system' ) }</a>
					</div>
					{ ! theme.available && <p className="ybs-hint">{ __( 'Purchase Pro, then install and activate the Pro add-on to unlock the theme installer.', 'magepeople-yacht-booking-system' ) }</p> }
					{ theme.available && ! theme.installed && <p className="ybs-hint">{ theme.installUrl ? __( 'Install the theme, then activate it using WordPress. After activation, open Yachtiva Setup to configure your homepage.', 'magepeople-yacht-booking-system' ) : __( 'Ask a site administrator with permission to install themes to install Yachtiva.', 'magepeople-yacht-booking-system' ) }</p> }
					{ theme.networkGate && <p className="ybs-notice">{ __( 'A network administrator must enable Yachtiva before you can activate it on this site.', 'magepeople-yacht-booking-system' ) }</p> }
					{ theme.error && <p className="ybs-notice is-error" role="alert">{ theme.error }</p> }
				</div>
			</section>
			<div className="ybs-pro-feature-grid">
				{ FEATURES.map( ( [ icon, title, description ] ) => (
					<div className="ybs-card ybs-pro-feature-card" key={ icon }>
						<span className={ 'dashicons ' + icon } aria-hidden="true" />
						<h3>{ title }</h3>
						<p>{ description }</p>
					</div>
				) ) }
			</div>
		</div>
	);
}
