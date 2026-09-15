import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

function Code({ children }) {
	return <code className="ybs-guide-code">{children}</code>;
}

/**
 * A real screenshot of the screen being described, taken from a live copy of
 * this plugin. Images ship in the plugin's own assets folder, so the URL is
 * built from the pluginUrl the PHP side already localizes onto
 * mageyaboAdminConfig for exactly this kind of purpose.
 *
 * Shown as a small, fixed-height preview (cropped to its top, where the
 * interesting part of an admin screen usually is) so a page full of these
 * stays scannable - the full screenshot is one click away in a lightbox.
 */
function Figure({ src, alt, caption }) {
	const [ isOpen, setIsOpen ] = useState( false );
	const base = ( typeof window !== 'undefined' && window.mageyaboAdminConfig && window.mageyaboAdminConfig.pluginUrl ) || '';
	const url = base + 'assets/admin/images/guide/' + src;

	return (
		<figure className="ybs-guide-figure">
			<button
				type="button"
				className="ybs-guide-figure__frame"
				onClick={() => setIsOpen( true )}
			>
				<img src={url} alt={alt} loading="lazy" />
				<span className="ybs-guide-figure__zoom" aria-hidden="true">
					<span className="dashicons dashicons-visibility"></span>
					{__( 'View full size', 'magepeople-yacht-booking-system' )}
				</span>
			</button>
			{ caption ? <figcaption>{caption}</figcaption> : null }
			{ isOpen ? (
				<div
					className="ybs-guide-lightbox"
					role="dialog"
					aria-modal="true"
					aria-label={alt}
					onClick={() => setIsOpen( false )}
				>
					<button
						type="button"
						className="ybs-guide-lightbox__close"
						aria-label={__( 'Close', 'magepeople-yacht-booking-system' )}
						onClick={() => setIsOpen( false )}
					>
						<span className="dashicons dashicons-no-alt"></span>
					</button>
					<img src={url} alt={alt} onClick={( event ) => event.stopPropagation()} />
				</div>
			) : null }
		</figure>
	);
}

function Section({ id, title, children }) {
	return (
		<div className="ybs-card ybs-guide-section" id={id}>
			<h3>{title}</h3>
			{children}
		</div>
	);
}

const TOC = [
	{ id: 'shortcodes', label: __('Shortcodes', 'magepeople-yacht-booking-system') },
	{ id: 'adding-a-yacht', label: __('Adding a Yacht', 'magepeople-yacht-booking-system') },
	{ id: 'settings-general', label: __('Settings: General', 'magepeople-yacht-booking-system') },
	{ id: 'settings-payments', label: __('Settings: Payments', 'magepeople-yacht-booking-system') },
	{ id: 'settings-pricing', label: __('Settings: Pricing Rules', 'magepeople-yacht-booking-system') },
	{ id: 'settings-email', label: __('Settings: Email', 'magepeople-yacht-booking-system') },
	{ id: 'settings-privacy', label: __('Settings: Data & Privacy', 'magepeople-yacht-booking-system') },
	{ id: 'bookings-calendar-guests', label: __('Bookings, Calendar & Guests', 'magepeople-yacht-booking-system') },
	{ id: 'troubleshooting', label: __('Troubleshooting', 'magepeople-yacht-booking-system') },
];

/**
 * The app's router treats EVERY hash change as a navigation (router.js
 * reads window.location.hash on every 'hashchange' and App.js maps
 * whatever it finds to a screen, defaulting to Dashboard for anything it
 * doesn't recognise) - a plain <a href="#shortcodes"> would change the
 * hash to `#shortcodes` and get swallowed as a "navigate to unknown
 * route" instead of a same-page anchor jump. Scrolling to the section
 * manually, without ever touching location.hash, keeps this on the User
 * Guide screen.
 */
function jumpTo( event, id ) {
	event.preventDefault();
	document.getElementById( id )?.scrollIntoView( { behavior: 'smooth', block: 'start' } );
}

export default function UserGuide() {
	return (
		<div className="ybs-guide">
			<div className="ybs-page-header">
				<div>
					<h2>{__('User Guide', 'magepeople-yacht-booking-system')}</h2>
					<p>
						{__(
							"How the pieces of this plugin fit together - the shortcodes that put a fleet on your site, and what every setting on the Settings screen actually does. Written for the person who's about to use it, not the person who built it.",
							'magepeople-yacht-booking-system'
						)}
					</p>
				</div>
			</div>

			<div className="ybs-card ybs-guide-toc">
				<h3>{__('On this page', 'magepeople-yacht-booking-system')}</h3>
				<ul>
					{TOC.map((item) => (
						<li key={item.id}>
							<a href={'#' + item.id} onClick={(event) => jumpTo(event, item.id)}>{item.label}</a>
						</li>
					))}
				</ul>
			</div>

			<div className="ybs-guide-columns">
			<Section id="shortcodes" title={__('Shortcodes', 'magepeople-yacht-booking-system')}>
				<p>
					{__(
						'Three shortcodes cover the whole front end. Add them to any page or post the normal WordPress way - paste into the block editor as a Shortcode block, or into a Classic Editor / widget text area.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<p>
					{__(
						'You don\'t have to build these pages yourself: activating the plugin creates a "Search Yacht" page with the search shortcode already on it, and importing the sample fleet (Yachts → the "Import Sample Yachts" button, or on a fresh install) creates a "Yacht List" page the same way. Both are safe to rename, move, or delete - the plugin only ever creates them once and won\'t make a duplicate.',
						'magepeople-yacht-booking-system'
					)}
				</p>

				<h4><Code>[mageyabo_yacht_search]</Code></h4>
				<p>
					{__(
						'The search widget: a Day charter / Hourly charter toggle above a Where / Dates / Guests / Price bar, with results loading in below it as a grid of yacht cards. The toggle re-prices every result off that charter type\'s own rate, so switching from Day to Hourly shows what an hourly booking of that yacht actually costs, not just its cheapest rate. This is what visitors see the moment they land on the "Search Yacht" page the plugin sets up for you.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<Figure
					src="search-page.png"
					alt={__('The [mageyabo_yacht_search] shortcode on the front end, showing the charter-type toggle, the Where/Dates/Guests/Price bar, and the results grid underneath.', 'magepeople-yacht-booking-system')}
					caption={__('The Search Yacht page, exactly as [mageyabo_yacht_search] renders it - toggle, search bar, and matching yachts below.', 'magepeople-yacht-booking-system')}
				/>
				<p>{__('Every field, and the toggle itself, can be hidden - all default to shown:', 'magepeople-yacht-booking-system')}</p>
				<table className="ybs-table">
					<thead>
						<tr>
							<th>{__('Attribute', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Hides', 'magepeople-yacht-booking-system')}</th>
						</tr>
					</thead>
					<tbody>
						<tr><td><Code>tabs="no"</Code></td><td>{__('The Day charter / Hourly charter toggle', 'magepeople-yacht-booking-system')}</td></tr>
						<tr><td><Code>where="no"</Code></td><td>{__('The location dropdown', 'magepeople-yacht-booking-system')}</td></tr>
						<tr><td><Code>dates="no"</Code></td><td>{__('The date field', 'magepeople-yacht-booking-system')}</td></tr>
						<tr><td><Code>guests="no"</Code></td><td>{__('The guest count stepper', 'magepeople-yacht-booking-system')}</td></tr>
						<tr><td><Code>price="no"</Code></td><td>{__('The price-range dropdown', 'magepeople-yacht-booking-system')}</td></tr>
					</tbody>
				</table>
				<p className="ybs-hint">
					{__('Example - a bare Dates/Guests/Price bar with no location filter or charter-type switch:', 'magepeople-yacht-booking-system')}{' '}
					<Code>[mageyabo_yacht_search tabs="no" where="no"]</Code>
				</p>

				<h4><Code>[mageyabo_yacht_list]</Code></h4>
				<p>
					{__(
						'The fleet page: class-filter tabs, a grid/list view toggle, and photo-count-badged cards with a "From" price that follows whichever charter type is selected. Good as its own page - the plugin sets one up for you, named "Yacht List" - or embedded partway down a page (a "Similar Yachts" style block) with the filter bar turned off.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<Figure
					src="yacht-list-page.png"
					alt={__('The [mageyabo_yacht_list] shortcode, showing the class-filter tabs, the grid/list toggle, and a grid of yacht cards each with a photo-count badge and starting price.', 'magepeople-yacht-booking-system')}
					caption={__('The Yacht List page - every published yacht, filterable by class, switchable between grid and list view.', 'magepeople-yacht-booking-system')}
				/>
				<table className="ybs-table">
					<thead>
						<tr>
							<th>{__('Attribute', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Default', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Does', 'magepeople-yacht-booking-system')}</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><Code>search</Code></td>
							<td><Code>yes</Code></td>
							<td>{__('Set to "no" to hide the Dates/Guests/Price bar and class tabs, and show just the grid.', 'magepeople-yacht-booking-system')}</td>
						</tr>
						<tr>
							<td><Code>per_page</Code></td>
							<td><Code>9</Code></td>
							<td>{__('How many yachts load before the visitor has to click "Load More Yachts".', 'magepeople-yacht-booking-system')}</td>
						</tr>
					</tbody>
				</table>
				<p className="ybs-hint">
					{__('Example - a bare 6-card grid, no filter bar:', 'magepeople-yacht-booking-system')}{' '}
					<Code>[mageyabo_yacht_list search="no" per_page="6"]</Code>
				</p>

				<h4><Code>[mageyabo_booking_form]</Code></h4>
				<p>
					{__(
						"The booking form on its own. You'll rarely need to place this by hand - it already appears automatically as the sticky sidebar on every yacht's own details page - but it's here for a dedicated \"Book Now\" page, or to embed a specific yacht's form somewhere else.",
						'magepeople-yacht-booking-system'
					)}
				</p>
				<table className="ybs-table">
					<thead>
						<tr>
							<th>{__('Attribute', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Does', 'magepeople-yacht-booking-system')}</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><Code>yacht_id</Code></td>
							<td>{__('Leave it out (or "0") to show a "Select a yacht" dropdown first. Set it to a yacht\'s post ID to lock the form to that one yacht - no dropdown.', 'magepeople-yacht-booking-system')}</td>
						</tr>
					</tbody>
				</table>
				<p className="ybs-hint">
					{__('Find a yacht\'s ID by opening it to edit (Yachts → click its name) and reading the number after edit=# in the address bar.', 'magepeople-yacht-booking-system')}
				</p>
				<Figure
					src="yacht-details-page.png"
					alt={__('A yacht details page, with the photo gallery, name, class badge and specs on the left and the booking form in a sticky sidebar on the right.', 'magepeople-yacht-booking-system')}
					caption={__('A single yacht\'s details page - the gallery and description sit in your theme\'s normal layout, with the booking form ([mageyabo_booking_form] itself) pinned in the sidebar.', 'magepeople-yacht-booking-system')}
				/>
			</Section>

			<Section id="adding-a-yacht" title={__('Adding a Yacht', 'magepeople-yacht-booking-system')}>
				<p>
					{__(
						'Yachts → Add New Yacht opens a 4-step wizard, shown down the left of the screen as a row of clickable steps - jump straight to any step you\'ve already reached instead of clicking Next repeatedly to get back to it. Only step 1 is required to save a draft; you can publish right away and fill in the rest later, or leave it as a draft while you gather photos and pricing.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<Figure
					src="yacht-wizard.png"
					alt={__('Step 1 of the Add New Yacht wizard: Basic Information, a departure-point map, an FAQ builder, and a sidebar with Publish, Payment Settings, Featured Image, Gallery, Related Yachts, Class and Occasion Tags cards.', 'magepeople-yacht-booking-system')}
					caption={__('Step 1 - Basic Info. The right-hand sidebar is where the featured image, gallery, and Related Yachts picker live.', 'magepeople-yacht-booking-system')}
				/>
				<table className="ybs-table">
					<thead>
						<tr>
							<th>{__('Step', 'magepeople-yacht-booking-system')}</th>
							<th>{__('Covers', 'magepeople-yacht-booking-system')}</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>{__('1. Basic Info', 'magepeople-yacht-booking-system')}</td>
							<td>{__('Name, description, build year, departure marina (searchable map), FAQ - plus, in the sidebar, Featured Image, Gallery, Related Yachts, Class, and Occasion Tags.', 'magepeople-yacht-booking-system')}</td>
						</tr>
						<tr>
							<td>{__('2. Specs & Capacity', 'magepeople-yacht-booking-system')}</td>
							<td>{__('Guest capacity, cabins, crew size, length, and the "Included in Every Charter" list guests see on the details page.', 'magepeople-yacht-booking-system')}</td>
						</tr>
						<tr>
							<td>{__('3. Pricing & Availability', 'magepeople-yacht-booking-system')}</td>
							<td>{__('Booking Mode (Full Charter / Shared by seat / Both), a price for each booking type you want to offer (leave a rate blank to turn that booking type off), the clock-time window each fixed-schedule type runs within, notice/buffer/duration rules, and off-days.', 'magepeople-yacht-booking-system')}</td>
						</tr>
						<tr>
							<td>{__('4. Review & Publish', 'magepeople-yacht-booking-system')}</td>
							<td>{__("A summary of everything above, plus an optional per-yacht override of the confirmation email and the details page's \"Book the {yacht_name} today\" call-to-action.", 'magepeople-yacht-booking-system')}</td>
						</tr>
					</tbody>
				</table>
				<p className="ybs-hint">
					{__(
						'Related Yachts (step 1 sidebar) is what fills the "Related Yachts" carousel at the bottom of a yacht\'s details page. Leave it empty and the plugin picks a few automatically from yachts sharing the same Class - set it and your picks are used instead, in the order you chose them.',
						'magepeople-yacht-booking-system'
					)}
				</p>
			</Section>

			<Section id="settings-general" title={__('Settings: General', 'magepeople-yacht-booking-system')}>
				<p>{__('The site-wide defaults every yacht inherits unless it says otherwise. Two things live here:', 'magepeople-yacht-booking-system')}</p>
				<Figure
					src="settings-general.png"
					alt={__('The Settings: General tab, showing Currency Code, Currency Symbol and Tax Rate fields, and the Ready When You Are call-to-action fields below.', 'magepeople-yacht-booking-system')}
					caption={__('Settings → General: currency/tax, and the default "Ready When You Are" booking call-to-action.', 'magepeople-yacht-booking-system')}
				/>
				<ul className="ybs-guide-list">
					<li>{__('Currency & Tax - the currency code and symbol shown on every price throughout the site (search results, list cards, the booking form, confirmation emails), and a flat tax rate added on top of a booking\'s total.', 'magepeople-yacht-booking-system')}</li>
					<li>
						{__(
							'The "Ready When You Are" call-to-action - the eyebrow, heading, body text and two button labels for the banner near the bottom of every yacht details page. Use {yacht_name} in the heading or text to insert that yacht\'s name automatically. A yacht can override this wording entirely from its own Review & Publish step; what you set here is just the fallback for yachts that don\'t bother to.',
							'magepeople-yacht-booking-system'
						)}
					</li>
				</ul>
			</Section>

			<Section id="settings-payments" title={__('Settings: Payments', 'magepeople-yacht-booking-system')}>
				<p>
					{__(
						'This is a two-step decision: which payment flow you use, then which method(s) inside that flow are switched on.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<Figure
					src="settings-payments.png"
					alt={__('The Settings: Payments tab, showing the Custom Payment Methods vs WooCommerce Checkout flow cards, a "You\'re configuring" banner, and the Offline, PayPal and Stripe method cards each with an enable toggle and a Configure link.', 'magepeople-yacht-booking-system')}
					caption={__('Settings → Payments, configured for Custom Payment Methods with Offline, PayPal and Stripe all enabled.', 'magepeople-yacht-booking-system')}
				/>

				<h4>{__('Step 1 - the flow', 'magepeople-yacht-booking-system')}</h4>
				<p>
					{__(
						'Two cards, exactly one selected at a time - click a card to switch. The "You\'re configuring:" banner underneath always confirms which one is currently active, so the fields below it are never ambiguous.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<ul className="ybs-guide-list">
					<li>
						<strong>{__('Custom Payment Methods', 'magepeople-yacht-booking-system')}</strong>
						{' - '}
						{__('Offline, PayPal, and Stripe, run directly by this plugin - no WooCommerce needed.', 'magepeople-yacht-booking-system')}
					</li>
					<li>
						<strong>{__('WooCommerce Checkout', 'magepeople-yacht-booking-system')}</strong>
						{' - '}
						{__("Bookings go through WooCommerce's own cart, checkout, and orders instead. If WooCommerce isn't active yet, this card shows its own \"Install & Activate WooCommerce\" button - clicking it installs, activates, and switches you into this flow in one step.", 'magepeople-yacht-booking-system')}
					</li>
				</ul>
				<p className="ybs-hint">
					{__(
						'These two are mutually exclusive on purpose - a booking is only ever paid for one way, so switching flows resets the other side rather than risk both trying to handle the same booking.',
						'magepeople-yacht-booking-system'
					)}
				</p>

				<h4>{__('Step 2 - the methods', 'magepeople-yacht-booking-system')}</h4>
				<p><strong>{__('If you picked Custom Payment Methods:', 'magepeople-yacht-booking-system')}</strong></p>
				<table className="ybs-table">
					<thead>
						<tr>
							<th>{__('Method', 'magepeople-yacht-booking-system')}</th>
							<th>{__('What its fields do', 'magepeople-yacht-booking-system')}</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>{__('Offline / Manual Payment', 'magepeople-yacht-booking-system')}</td>
							<td>{__('No API keys - just an "Instructions" note the guest sees at checkout (e.g. how to pay by bank transfer). Good default while you\'re setting everything else up.', 'magepeople-yacht-booking-system')}</td>
						</tr>
						<tr>
							<td>{__('PayPal', 'magepeople-yacht-booking-system')}</td>
							<td>{__('Your PayPal email, and Sandbox (testing, no real money moves) or Live.', 'magepeople-yacht-booking-system')}</td>
						</tr>
						<tr>
							<td>{__('Stripe', 'magepeople-yacht-booking-system')}</td>
							<td>{__('Publishable Key and Secret Key from your Stripe Dashboard, plus a Webhook Secret if you\'ve set up a Stripe webhook for this site. Once a key is saved, its field shows a note instead of the key itself - leave it blank to keep the saved one.', 'magepeople-yacht-booking-system')}</td>
						</tr>
					</tbody>
				</table>
				<p>
					{__(
						'Each method is a card: the switch enables/disables it, the badge shows its current state, and "Configure" opens its fields without needing it switched on first - handy for filling in Stripe keys ahead of time, then flipping it on when you\'re ready. Turning a method on also opens its fields automatically, since that\'s usually exactly why you turned it on. Whichever methods are enabled populate the "Default Payment Method" dropdown at the bottom - that\'s what a guest sees pre-selected at checkout.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<p><strong>{__('If you picked WooCommerce Checkout:', 'magepeople-yacht-booking-system')}</strong></p>
				<p>
					{__(
						'A "WooCommerce Payment Methods" panel lists every gateway WooCommerce itself knows about (Cash on Delivery, Direct Bank Transfer, plus anything a WooCommerce extension you\'ve installed adds) with the same quick switch and status badge. Flipping one here is the same as flipping it in WooCommerce\'s own Settings → Payments screen - they read and write the exact same option. For a gateway\'s own detailed settings (API keys, instructions, fees), use the "Open in WooCommerce" link, which takes you straight to WooCommerce\'s native settings for it.',
						'magepeople-yacht-booking-system'
					)}
				</p>
			</Section>

			<Section id="settings-pricing" title={__('Settings: Pricing Rules', 'magepeople-yacht-booking-system')}>
				<p>
					{__(
						'Off-days and simple across-the-fleet pricing adjustments, applied to every yacht unless a yacht-specific rule is added later. Add as many rules as you need with "+ Add Rule" - each one is a label, a type, and a from/to date range. Three types:',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<Figure
					src="settings-pricing.png"
					alt={__('The Settings: Pricing Rules tab, showing an empty rule row with Label, Type, From Date and To Date fields and an "+ Add Rule" button.', 'magepeople-yacht-booking-system')}
					caption={__('Settings → Pricing Rules: fleet-wide off-days and weekday/seasonal price adjustments.', 'magepeople-yacht-booking-system')}
				/>
				<ul className="ybs-guide-list">
					<li><strong>{__('Off-Day', 'magepeople-yacht-booking-system')}</strong>{' - '}{__('blocks a date (or date range) from being booked at all - a maintenance day, a public holiday, a private event.', 'magepeople-yacht-booking-system')}</li>
					<li><strong>{__('Weekday/Weekend', 'magepeople-yacht-booking-system')}</strong>{' - '}{__('a percent or fixed-amount adjustment for particular days of the week.', 'magepeople-yacht-booking-system')}</li>
					<li><strong>{__('Seasonal', 'magepeople-yacht-booking-system')}</strong>{' - '}{__('the same kind of adjustment, but for a date range instead of a weekday - a summer peak-season surcharge, for example.', 'magepeople-yacht-booking-system')}</li>
				</ul>
			</Section>

			<Section id="settings-email" title={__('Settings: Email', 'magepeople-yacht-booking-system')}>
				<p>
					{__(
						'Everything to do with the confirmation email a guest receives, and who it appears to come from.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<Figure
					src="settings-email.png"
					alt={__('The Settings: Email tab, showing Sender Identity fields, a Confirmation Email subject and rich-text body editor with a "Send Test Email" button, a "Send Confirmation On" checklist of booking statuses, and a Dynamic Variables panel of clickable placeholders.', 'magepeople-yacht-booking-system')}
					caption={__('Settings → Email: sender identity, the confirmation email template, and the dynamic variables it can insert.', 'magepeople-yacht-booking-system')}
				/>
				<ul className="ybs-guide-list">
					<li>{__('Sender Identity - the From name/address on confirmation emails. Leave either blank to fall back to your site name / admin email.', 'magepeople-yacht-booking-system')}</li>
					<li>{__('Confirmation Email - the default subject and body sent when a booking is confirmed. Insert booking details (guest name, dates, total price, and so on) with the variable buttons on the right; "Send Test Email" lets you preview it in your own inbox before it goes near a real guest. A yacht can override this from its own Review & Publish step.', 'magepeople-yacht-booking-system')}</li>
					<li>{__('Send Confirmation On - which booking status(es) trigger the email. Most sites want this to match whatever status their payment method marks a paid booking with.', 'magepeople-yacht-booking-system')}</li>
				</ul>
			</Section>

			<Section id="settings-privacy" title={__('Settings: Data & Privacy', 'magepeople-yacht-booking-system')}>
				<p>
					{__(
						'Two small switches that decide how long a guest\'s personal details stick around, and what happens to the plugin\'s data if it\'s ever removed.',
						'magepeople-yacht-booking-system'
					)}
				</p>
				<Figure
					src="settings-privacy.png"
					alt={__('The Settings: Data & Privacy tab, showing an "Anonymize guest data after (months)" number field set to 0, and an unchecked "Remove all plugin data when uninstalled" checkbox.', 'magepeople-yacht-booking-system')}
					caption={__('Settings → Data & Privacy: retention and uninstall behavior, both off by default.', 'magepeople-yacht-booking-system')}
				/>
				<ul className="ybs-guide-list">
					<li>{__('Anonymize guest data after (months) - guest name/email/phone on old bookings are scrubbed after this many months. Set to 0 to keep guest data indefinitely.', 'magepeople-yacht-booking-system')}</li>
					<li>{__('Remove all plugin data when uninstalled - leave this off unless you\'re sure; switching it on means deleting the plugin also deletes every yacht, booking, and guest record it created.', 'magepeople-yacht-booking-system')}</li>
				</ul>
			</Section>

			<Section id="bookings-calendar-guests" title={__('Bookings, Calendar & Guests', 'magepeople-yacht-booking-system')}>
				<p>{__('Three views of the same underlying bookings, each suited to a different question.', 'magepeople-yacht-booking-system')}</p>
				<Figure
					src="bookings-list.png"
					alt={__('The Bookings admin screen, a table listing each booking\'s yacht, guest, charter type, dates, duration, total, order and status, with a Delete action and an "All Statuses" filter.', 'magepeople-yacht-booking-system')}
					caption={__('Bookings - every booking across the whole fleet, newest first, filterable by status.', 'magepeople-yacht-booking-system')}
				/>
				<ul className="ybs-guide-list">
					<li><strong>{__('Bookings', 'magepeople-yacht-booking-system')}</strong>{' - '}{__('every booking made through any of the shortcodes above, in one flat list, with its status and payment state. "Delete" here removes the booking record itself - use it for test/duplicate bookings, not as a way to cancel a real one (change its status instead, so the guest\'s history stays intact).', 'magepeople-yacht-booking-system')}</li>
					<li><strong>{__('Calendar', 'magepeople-yacht-booking-system')}</strong>{' - '}{__('the same bookings laid out by date, per yacht - the fastest way to see what\'s free this week or spot a scheduling clash before you confirm a new request.', 'magepeople-yacht-booking-system')}</li>
					<li><strong>{__('Guests', 'magepeople-yacht-booking-system')}</strong>{' - '}{__('everyone who has ever booked, with their contact details and booking history in one place - this is the data the Data & Privacy retention setting eventually anonymizes once it\'s old enough.', 'magepeople-yacht-booking-system')}</li>
				</ul>
			</Section>

			<Section id="troubleshooting" title={__('Troubleshooting', 'magepeople-yacht-booking-system')}>
				<ul className="ybs-guide-list">
					<li>{__('"Book Now" isn\'t clickable / no price shows: check that the yacht has at least one price set for the booking type being requested (Yachts → edit the yacht → Pricing & Availability), and that a payment method is enabled (Settings → Payments).', 'magepeople-yacht-booking-system')}</li>
					<li>{__('A yacht isn\'t showing up in the search or list shortcode: make sure it\'s Published, not a Draft, and that any Class/Occasion/location filter a visitor has selected actually matches it.', 'magepeople-yacht-booking-system')}</li>
					<li>{__('Confirmation emails aren\'t arriving: use "Send Test Email" on Settings → Email first to rule out a hosting/SMTP problem, then check that "Send Confirmation On" includes the status your payment method actually sets.', 'magepeople-yacht-booking-system')}</li>
					<li>{__('Switched to WooCommerce Checkout and nothing is enabled: that\'s expected right after installing WooCommerce - open the "WooCommerce Payment Methods" panel and switch at least one gateway (Cash on Delivery is the quickest to test with) on.', 'magepeople-yacht-booking-system')}</li>
				</ul>
			</Section>
			</div>
		</div>
	);
}
