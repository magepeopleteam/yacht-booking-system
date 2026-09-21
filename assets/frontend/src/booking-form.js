const SLOT_WINDOWS = {
	half_day: [ '08:00', '12:00' ],
	morning_slot: [ '08:00', '13:00' ],
	evening_slot: [ '15:00', '20:00' ],
	daily: [ '08:00', '20:00' ],
};

function computeWindow( form ) {
	const type = form.querySelector( '.ybs-bf-type' ).value;
	const date = form.querySelector( '.ybs-bf-date' ).value;

	if ( ! date ) {
		return null;
	}

	if ( 'hourly' === type ) {
		const startTime = form.querySelector( '.ybs-bf-start-time' ).value || '10:00';
		const duration = parseFloat( form.querySelector( '.ybs-bf-duration' ).value || '2' );
		const start = new Date( `${ date }T${ startTime }:00` );
		const end = new Date( start.getTime() + duration * 60 * 60 * 1000 );
		return { start: toMysql( start ), end: toMysql( end ) };
	}

	if ( 'multiday' === type ) {
		const nights = parseInt( form.querySelector( '.ybs-bf-nights' ).value || '2', 10 );
		const start = new Date( `${ date }T08:00:00` );
		const end = new Date( start.getTime() + nights * 24 * 60 * 60 * 1000 );
		return { start: toMysql( start ), end: toMysql( end ) };
	}

	const [ startTime, endTime ] = SLOT_WINDOWS[ type ] || SLOT_WINDOWS.daily;
	return { start: `${ date } ${ startTime }:00`, end: `${ date } ${ endTime }:00` };
}

function toMysql( date ) {
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad( date.getDate() ) } ${ pad( date.getHours() ) }:${ pad( date.getMinutes() ) }:00`;
}

function defaultDateValue() {
	const date = new Date();
	date.setDate( date.getDate() + 1 );

	const pad = ( n ) => String( n ).padStart( 2, '0' );
	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad( date.getDate() ) }`;
}

function debounce( fn, delay ) {
	let timer;
	return ( ...args ) => {
		clearTimeout( timer );
		timer = setTimeout( () => fn( ...args ), delay );
	};
}

function toggleFields( form ) {
	const type = form.querySelector( '.ybs-bf-type' ).value;

	form.querySelectorAll( '.ybs-bf-hourly-fields' ).forEach( ( el ) => {
		el.hidden = 'hourly' !== type;
	} );

	form.querySelectorAll( '.ybs-bf-multiday-fields' ).forEach( ( el ) => {
		el.hidden = 'multiday' !== type;
	} );
}

/**
 * One radio "card" per enabled gateway instead of a plain <select> - visually
 * selecting a payment method rather than picking it from a closed dropdown.
 * Each input keeps the shared `.ybs-bf-payment` class so the rest of this
 * file can keep asking "which one is checked" the same way it used to ask
 * "what's the select's value".
 */
function populatePaymentMethods( form ) {
	const list = form.querySelector( '[data-ybs-bf-pm-list]' );

	if ( ! list ) {
		return;
	}

	const gateways = ( window.mageyaboFrontendConfig && window.mageyaboFrontendConfig.gateways ) || {};
	// Radio inputs need a shared `name` to behave as one group - unique per
	// form instance so a page with more than one booking form (e.g. the
	// standalone [mageyabo_booking_form] shortcode plus a sidebar one) never
	// has their groups cross-select each other.
	const groupName = 'ybs-bf-payment-' + Math.random().toString( 36 ).slice( 2, 9 );

	list.innerHTML = '';

	let firstInput = null;

	Object.keys( gateways ).forEach( ( id ) => {
		if ( ! gateways[ id ] || ! gateways[ id ].enabled ) {
			return;
		}

		const card = document.createElement( 'label' );
		card.className = 'ybs-bf-pm-card';
		card.dataset.gateway = id;

		const input = document.createElement( 'input' );
		input.type = 'radio';
		input.name = groupName;
		input.value = id;
		input.className = 'ybs-bf-payment';

		const icon = document.createElement( 'span' );
		icon.className = 'ybs-bf-pm-card__icon';
		icon.setAttribute( 'aria-hidden', 'true' );

		const label = document.createElement( 'span' );
		label.className = 'ybs-bf-pm-card__label';
		label.textContent = gateways[ id ].label;

		card.append( input, icon, label );
		list.appendChild( card );

		if ( ! firstInput ) {
			firstInput = input;
		}
	} );

	if ( firstInput ) {
		firstInput.checked = true;
	}
}

function selectedPaymentMethod( form ) {
	const checked = form.querySelector( '.ybs-bf-payment:checked' );
	return checked ? checked.value : '';
}

/**
 * Lazily grabs a Stripe.js client - the script itself only loads at all when
 * the Stripe gateway is enabled (see Shortcode::register_assets()), so this
 * is never called otherwise.
 */
let stripeClientPromise = null;

function getStripeClient( publishableKey ) {
	if ( ! stripeClientPromise ) {
		stripeClientPromise = window.Stripe
			? Promise.resolve( window.Stripe( publishableKey ) )
			: Promise.reject( new Error( 'Stripe.js is not loaded.' ) );
	}

	return stripeClientPromise;
}

/**
 * Swaps the modal from "fill in your details" to Stripe's own Embedded
 * Checkout - the booking already exists (pending/unpaid) by the time this
 * runs, and `payment.client_secret` is what ties this browser session to it.
 * Stripe renders the actual card number/expiry/CVC fields inside
 * `[data-ybs-bf-stripe-mount]`; nothing left in this form needs to be
 * submitted until the guest pays inside that mounted widget.
 */
async function mountStripeCheckout( form, payment ) {
	const config = window.mageyaboFrontendConfig;
	const fields = form.querySelector( '[data-ybs-bf-modal-fields]' );
	const cardBox = form.querySelector( '[data-ybs-bf-stripe-card]' );
	const mountEl = form.querySelector( '[data-ybs-bf-stripe-mount]' );
	const errorBox = form.querySelector( '.ybs-bf-error' );

	if ( fields ) {
		fields.hidden = true;
	}

	if ( cardBox ) {
		cardBox.hidden = false;
	}

	errorBox.hidden = true;

	// Stripe's own mount() is documented against a CSS selector, not an
	// element reference - give the container a stable id (once) rather than
	// relying on element-argument support that isn't part of its documented
	// contract.
	if ( ! mountEl.id ) {
		mountEl.id = 'ybs-bf-stripe-mount-' + Math.random().toString( 36 ).slice( 2, 9 );
	}

	// initEmbeddedCheckout() is a real network round-trip (Stripe fetching
	// the session by its client secret) - a blank box until it resolves
	// would look broken rather than loading.
	const loading = document.createElement( 'div' );
	loading.className = 'ybs-bf-stripe-card__mount-loading';
	loading.textContent = ( config.i18n && config.i18n.loading ) || 'Loading…';
	mountEl.appendChild( loading );

	try {
		const stripe = await getStripeClient( payment.publishable_key );
		const checkout = await stripe.initEmbeddedCheckout( { clientSecret: payment.client_secret } );

		loading.remove();
		checkout.mount( '#' + mountEl.id );
	} catch ( e ) {
		loading.remove();

		if ( cardBox ) {
			cardBox.hidden = true;
		}

		if ( fields ) {
			fields.hidden = false;
		}

		errorBox.hidden = false;
		errorBox.textContent = ( config.i18n && config.i18n.stripeLoadError ) || 'Could not load the payment form. Please refresh and try again.';
	}
}

/**
 * The yacht's optional extras. Fetched rather than printed server-side,
 * because the form can be rendered without a yacht (the picker) and the list
 * has to follow whichever one is selected - so it reloads on every yacht
 * change, and empties itself when no yacht is chosen.
 */
async function loadAddons( form ) {
	const box = form.querySelector( '[data-ybs-bf-addons]' );

	if ( ! box ) {
		return;
	}

	const config = window.mageyaboFrontendConfig;
	const list = form.querySelector( '[data-ybs-bf-addons-list]' );
	const yachtId = currentYachtId( form );

	list.innerHTML = '';
	box.hidden = true;

	if ( ! yachtId || ! config.addonsEnabled ) {
		return;
	}

	let items = [];

	try {
		const response = await fetch( `${ config.restRoot }yachts/${ yachtId }/addons` );

		if ( ! response.ok ) {
			return;
		}

		const data = await response.json();
		items = Array.isArray( data.items ) ? data.items : [];
	} catch ( e ) {
		// A yacht with no reachable add-on list simply offers no extras -
		// never a reason to block the booking itself.
		return;
	}

	if ( ! items.length ) {
		return;
	}

	items.forEach( ( addon ) => {
		const row = document.createElement( 'label' );
		row.className = 'ybs-bf-addon';

		const checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		checkbox.className = 'ybs-bf-addon__check';
		checkbox.value = String( addon.id );

		const name = document.createElement( 'span' );
		name.className = 'ybs-bf-addon__name';
		// textContent, not innerHTML: an add-on name is operator-entered text.
		name.textContent = addon.name;

		const price = document.createElement( 'span' );
		price.className = 'ybs-bf-addon__price';
		price.textContent = `${ config.currency }${ Number( addon.price ).toFixed( 2 ) }`;

		const qty = document.createElement( 'input' );
		qty.type = 'number';
		qty.className = 'ybs-bf-addon__qty';
		qty.min = '1';
		qty.value = '1';
		qty.hidden = true;

		checkbox.addEventListener( 'change', () => {
			qty.hidden = ! checkbox.checked;
			refreshQuote( form );
		} );

		qty.addEventListener( 'change', () => refreshQuote( form ) );

		row.append( checkbox, name, price, qty );

		if ( addon.description ) {
			const description = document.createElement( 'span' );
			description.className = 'ybs-bf-addon__description';
			description.textContent = addon.description;
			row.appendChild( description );
		}

		list.appendChild( row );
	} );

	box.hidden = false;
}

/**
 * The ticked extras as the compact "id:qty,id:qty" string both the REST quote
 * and the WooCommerce hidden field take. Quantities only - the server prices
 * them from its own table.
 */
function selectedAddons( form ) {
	const pairs = [];

	form.querySelectorAll( '.ybs-bf-addon' ).forEach( ( row ) => {
		const checkbox = row.querySelector( '.ybs-bf-addon__check' );

		if ( ! checkbox || ! checkbox.checked ) {
			return;
		}

		const qtyInput = row.querySelector( '.ybs-bf-addon__qty' );
		const qty = Math.max( 1, parseInt( qtyInput ? qtyInput.value : '1', 10 ) || 1 );

		pairs.push( `${ checkbox.value }:${ qty }` );
	} );

	return pairs.join( ',' );
}

/**
 * "3:2,7:1" -> { 3: 2, 7: 1 } for the JSON booking payload.
 */
function addonsObject( form ) {
	const selection = {};

	selectedAddons( form )
		.split( ',' )
		.filter( Boolean )
		.forEach( ( pair ) => {
			const [ id, qty ] = pair.split( ':' );
			selection[ id ] = Number( qty );
		} );

	return selection;
}

/**
 * Extensions to the booking form, registered by add-on scripts.
 *
 * The form owns the charter itself - dates, guests, extras, the quote and the
 * submit. Anything beyond that is somebody else's: the Pro add-on's coupon
 * field is an extension, not a branch in here. Each one may implement any of:
 *
 *   mount( form )                  wire up its own markup and listeners
 *   quoteParams( form )            -> object, merged into the quote request
 *   submitData( form )             -> object, merged into the booking payload
 *   hiddenFields( form )           -> object, written into the WooCommerce
 *                                    form's hidden inputs by name
 *   onQuote( form, pricing )       react to a fresh quote
 *
 * Registering after the forms have already been initialised still works -
 * `mount` is called for the forms already on the page - so an add-on script
 * does not have to win a race with this one.
 */
const extensions = [];

// Registration and form initialisation can happen in either order, so both
// call mountInto(). This is what keeps whichever arrives second from wiring a
// second set of listeners onto the same field.
const mounted = new WeakMap();

function mountInto( extension, form ) {
	if ( ! extension.mount ) {
		return;
	}

	let forms = mounted.get( extension );

	if ( ! forms ) {
		forms = new WeakSet();
		mounted.set( extension, forms );
	}

	if ( forms.has( form ) ) {
		return;
	}

	forms.add( form );

	try {
		extension.mount( form );
	} catch ( e ) {
		// An extension that cannot mount costs its own field, nothing else.
	}
}

export function registerFormExtension( extension ) {
	if ( ! extension || 'object' !== typeof extension ) {
		return;
	}

	extensions.push( extension );

	document.querySelectorAll( '[data-ybs-booking-form]' ).forEach( ( form ) => {
		mountInto( extension, form );
	} );
}

/**
 * Collects one hook across every extension into a single object. An extension
 * that throws is skipped rather than taking the quote down with it - a broken
 * add-on should cost its own field, not the ability to book.
 */
function collect( hook, form ) {
	return extensions.reduce( ( carry, extension ) => {
		if ( ! extension[ hook ] ) {
			return carry;
		}

		try {
			return Object.assign( carry, extension[ hook ]( form ) || {} );
		} catch ( e ) {
			return carry;
		}
	}, {} );
}

function notify( hook, form, ...args ) {
	extensions.forEach( ( extension ) => {
		if ( ! extension[ hook ] ) {
			return;
		}

		try {
			extension[ hook ]( form, ...args );
		} catch ( e ) {
			// As above: an extension's failure is its own.
		}
	} );
}

if ( typeof window !== 'undefined' ) {
	window.mageyaboBooking = window.mageyaboBooking || {};
	window.mageyaboBooking.registerFormExtension = registerFormExtension;
	// Extensions re-price the charter after changing something of their own.
	window.mageyaboBooking.refreshQuote = ( form ) => refreshQuote( form );
}

/**
 * The quote as a small breakdown rather than one number, so a guest can see
 * what the extras and the discount did to the price - and, when the operator
 * takes deposits, what they are actually being charged today.
 */
function renderPrice( form, pricing, remaining ) {
	const config = window.mageyaboFrontendConfig;
	const i18n = config.i18n || {};
	const priceBox = form.querySelector( '.ybs-bf-price' );
	const money = ( value ) => `${ config.currency }${ Number( value ).toFixed( 2 ) }`;

	priceBox.innerHTML = '';

	// Rendered as real text rather than a CSS `content:` label, so it can be
	// translated - and so it cannot leak onto every other notice sharing the
	// same class.
	const title = document.createElement( 'span' );
	title.className = 'ybs-bf-price__title';
	title.textContent = i18n.estimatedTotal || 'Estimated total';
	priceBox.appendChild( title );

	const rows = [ [ i18n.charter || 'Charter', money( pricing.base_price + pricing.adjustment_total ) ] ];

	if ( pricing.addons_total > 0 ) {
		rows.push( [ i18n.extrasTotal || 'Extras', money( pricing.addons_total ) ] );
	}

	if ( pricing.discount_total > 0 ) {
		rows.push( [ i18n.discount || 'Discount', `-${ money( pricing.discount_total ) }` ] );
	}

	if ( pricing.tax_total > 0 ) {
		rows.push( [ i18n.tax || 'Tax', money( pricing.tax_total ) ] );
	}

	rows.forEach( ( [ label, value ] ) => {
		const row = document.createElement( 'div' );
		row.className = 'ybs-bf-price__row';

		const labelEl = document.createElement( 'span' );
		labelEl.textContent = label;

		const valueEl = document.createElement( 'span' );
		valueEl.textContent = value;

		row.append( labelEl, valueEl );
		priceBox.appendChild( row );
	} );

	const totalRow = document.createElement( 'div' );
	totalRow.className = 'ybs-bf-price__row is-total';

	const totalLabel = document.createElement( 'strong' );
	totalLabel.textContent = i18n.total || 'Total';

	const totalValue = document.createElement( 'strong' );
	totalValue.textContent = money( pricing.total );

	totalRow.append( totalLabel, totalValue );
	priceBox.appendChild( totalRow );

	if ( pricing.deposit_amount > 0 ) {
		const deposit = document.createElement( 'div' );
		deposit.className = 'ybs-bf-price__deposit';
		deposit.textContent =
			( i18n.depositDueNow || 'Pay %s deposit now' ).replace( '%s', money( pricing.deposit_amount ) ) +
			' ' +
			( i18n.depositBalance || 'Balance of %s due before departure.' ).replace( '%s', money( pricing.balance_due ) );
		priceBox.appendChild( deposit );
	}

	if ( null !== remaining && undefined !== remaining && 'shared' === currentMode( form ) ) {
		const seats = document.createElement( 'div' );
		seats.className = 'ybs-bf-price__seats';
		seats.textContent = `${ Number( remaining ) } ${ i18n.seatsLeft || 'seats left' }`;
		priceBox.appendChild( seats );
	}
}

function currentYachtId( form ) {
	const select = form.querySelector( '.ybs-bf-yacht' );
	return select ? select.value : form.dataset.yachtId;
}

function currentCapacity( form ) {
	const yachtSelect = form.querySelector( '.ybs-bf-yacht' );

	if ( yachtSelect ) {
		const option = yachtSelect.selectedOptions[ 0 ];
		return option ? parseInt( option.dataset.capacity || '0', 10 ) : 0;
	}

	return parseInt( form.dataset.capacity || '0', 10 );
}

// Keeps the guests field from ever accepting more than the yacht can
// actually take - capacity for a full charter, remaining seats for a
// shared one (refreshQuote tightens the max further once it knows that).
function clampGuests( form ) {
	const input = form.querySelector( '.ybs-bf-guests' );

	if ( ! input || '' === input.max ) {
		return;
	}

	const max = parseInt( input.max, 10 );
	const min = parseInt( input.min || '1', 10 );
	const value = parseInt( input.value, 10 );

	if ( isNaN( value ) ) {
		return;
	}

	if ( value > max ) {
		input.value = String( max );
	} else if ( value < min ) {
		input.value = String( min );
	}
}

function resetGuestsMax( form ) {
	const input = form.querySelector( '.ybs-bf-guests' );

	if ( ! input ) {
		return;
	}

	const capacity = currentCapacity( form );

	if ( capacity > 0 ) {
		input.max = String( capacity );
	} else {
		input.removeAttribute( 'max' );
	}

	clampGuests( form );
}

function currentMode( form ) {
	const modeSelect = form.querySelector( '.ybs-bf-mode' );

	if ( modeSelect ) {
		return modeSelect.value || 'full';
	}

	return form.dataset.ybsMode || 'full';
}

function updateHiddenFields( form ) {
	if ( '1' !== form.dataset.ybsWc ) {
		return;
	}

	const window_ = computeWindow( form );
	const set = ( name, value ) => {
		const input = form.querySelector( `input[name="${ name }"]` );
		if ( input ) {
			input.value = value;
		}
	};

	set( 'mageyabo_booking_type', form.querySelector( '.ybs-bf-type' ).value );
	set( 'mageyabo_booking_mode', currentMode( form ) );
	set( 'mageyabo_guest_count', form.querySelector( '.ybs-bf-guests' ).value || 1 );
	set( 'mageyabo_start_datetime', window_ ? window_.start : '' );
	set( 'mageyabo_end_datetime', window_ ? window_.end : '' );
	set( 'mageyabo_addons', selectedAddons( form ) );

	// This path submits natively, so an extension's value has to travel as a
	// hidden input rather than in a JSON body.
	Object.entries( collect( 'hiddenFields', form ) ).forEach( ( [ name, value ] ) => set( name, value ) );
}

function setSubmitEnabled( form, enabled ) {
	const button = form.querySelector( '.ybs-bf-submit' );

	if ( button ) {
		button.disabled = ! enabled;
	}
}

/**
 * Guest details, payment method, and the terms checkbox only exist inside
 * this popup (custom-payment-method forms only - a WooCommerce checkout
 * form has none of this and skips straight to "add-to-cart"). It opens on
 * "Book Now" regardless of whether the current quote is valid, so an
 * availability problem ("too close to another booking", an off-day, and so
 * on) is something the visitor actually sees explained, rather than a
 * button that's disabled for no visible reason.
 */
function openModal( form ) {
	const modal = form.querySelector( '[data-ybs-bf-modal]' );

	if ( ! modal ) {
		return;
	}

	modal.hidden = false;
	document.body.classList.add( 'ybs-bf-modal-open' );

	const firstField = modal.querySelector( '.ybs-bf-name' );

	if ( firstField ) {
		firstField.focus();
	}
}

function closeModal( form ) {
	const modal = form.querySelector( '[data-ybs-bf-modal]' );

	if ( ! modal ) {
		return;
	}

	modal.hidden = true;
	document.body.classList.remove( 'ybs-bf-modal-open' );
}

/**
 * "2026-09-20 10:00:00" / "2026-09-20 12:00:00" -> "Sun, Sep 20, 2026,
 * 10:00 AM - 12:00 PM" in the visitor's own locale/timezone - these are the
 * same MySQL-format strings computeWindow() builds for the booking payload,
 * just read back for display rather than submitted.
 */
function formatDateRange( startStr, endStr ) {
	const start = new Date( startStr.replace( ' ', 'T' ) );
	const end = endStr ? new Date( endStr.replace( ' ', 'T' ) ) : null;

	if ( isNaN( start.getTime() ) ) {
		return startStr;
	}

	const dateFmt = new Intl.DateTimeFormat( undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' } );
	const timeFmt = new Intl.DateTimeFormat( undefined, { hour: 'numeric', minute: '2-digit' } );

	if ( end && ! isNaN( end.getTime() ) ) {
		return `${ dateFmt.format( start ) }, ${ timeFmt.format( start ) } - ${ timeFmt.format( end ) }`;
	}

	return `${ dateFmt.format( start ) }, ${ timeFmt.format( start ) }`;
}

/**
 * The confirmation screen shown in place of the fields once a booking that
 * needs no further payment step (offline, or anything else that doesn't
 * hand back a redirect/client secret) actually goes through - the popup
 * stays open, just switched to "here's what you booked" instead of quietly
 * vanishing along with the rest of the form.
 */
function showBookingSuccess( form, data, payload, window_ ) {
	const config = window.mageyaboFrontendConfig;
	const i18n = config.i18n || {};
	const fields = form.querySelector( '[data-ybs-bf-modal-fields]' );
	const cardBox = form.querySelector( '[data-ybs-bf-stripe-card]' );
	const successBox = form.querySelector( '[data-ybs-bf-success]' );
	const messageEl = form.querySelector( '[data-ybs-bf-success-message]' );
	const detailsEl = form.querySelector( '[data-ybs-bf-success-details]' );
	const titleEl = form.querySelector( '.ybs-bf-modal__title' );
	const openModalButton = form.querySelector( '.ybs-bf-open-modal' );

	if ( ! successBox ) {
		return false;
	}

	if ( fields ) {
		fields.hidden = true;
	}

	if ( cardBox ) {
		cardBox.hidden = true;
	}

	if ( titleEl ) {
		titleEl.textContent = i18n.bookingConfirmedTitle || 'Booking Confirmed!';
	}

	if ( messageEl ) {
		messageEl.textContent = payload.guest.email && i18n.bookingConfirmedWithEmail
			? i18n.bookingConfirmedWithEmail.replace( '%s', payload.guest.email )
			: ( i18n.bookingConfirmed || 'Thank you - your booking request has been received.' );
	}

	if ( detailsEl ) {
		const gateways = config.gateways || {};
		const paymentLabel = ( gateways[ payload.payment_method ] && gateways[ payload.payment_method ].label ) || payload.payment_method;
		const total = data.pricing ? `${ config.currency }${ Number( data.pricing.total ).toFixed( 2 ) }` : '';

		const rows = [
			[ i18n.detailBookingId || 'Booking ID', '#' + data.booking_id ],
			[ i18n.detailDates || 'Date & Time', formatDateRange( window_.start, window_.end ) ],
			[ i18n.detailGuests || 'Guests', String( payload.guest_count ) ],
			[ i18n.detailPayment || 'Payment Method', paymentLabel ],
			[ i18n.detailTotal || 'Total', total ],
		];

		detailsEl.innerHTML = '';

		rows.forEach( ( [ label, value ] ) => {
			if ( ! value ) {
				return;
			}

			const dt = document.createElement( 'dt' );
			dt.textContent = label;
			const dd = document.createElement( 'dd' );
			dd.textContent = value;
			detailsEl.append( dt, dd );
		} );
	}

	// The same page both hosted gateways return to - so an offline booking
	// ends up somewhere it can be looked at again later, not just in a popup
	// that closes.
	if ( data.confirmation_url && detailsEl ) {
		const link = document.createElement( 'a' );
		link.className = 'ybs-bf-success__link';
		link.href = data.confirmation_url;
		link.textContent = i18n.viewBooking || 'View your booking';
		detailsEl.after( link );
	}

	successBox.hidden = false;

	// Nothing left to book on this form instance until the page is reloaded
	// (the availability check above only holds for this exact window) - swap
	// "Book Now" for a small confirmed note instead of leaving a live button
	// that would just start a second, redundant booking.
	if ( openModalButton ) {
		const confirmedNote = document.createElement( 'p' );
		confirmedNote.className = 'ybs-notice is-success ybs-bf-confirmed-note';
		confirmedNote.textContent = i18n.bookingConfirmedTitle || 'Booking Confirmed!';
		openModalButton.replaceWith( confirmedNote );
	}

	return true;
}

async function refreshQuote( form ) {
	const yachtId = currentYachtId( form );
	const priceBox = form.querySelector( '.ybs-bf-price' );
	const errorBox = form.querySelector( '.ybs-bf-error' );

	errorBox.hidden = true;

	if ( ! yachtId ) {
		priceBox.hidden = true;
		setSubmitEnabled( form, false );
		return;
	}

	const window_ = computeWindow( form );

	if ( ! window_ ) {
		priceBox.hidden = true;
		setSubmitEnabled( form, false );
		return;
	}

	const guests = form.querySelector( '.ybs-bf-guests' ).value || 1;
	const type = form.querySelector( '.ybs-bf-type' ).value;
	const config = window.mageyaboFrontendConfig;

	priceBox.hidden = false;
	priceBox.textContent = config.i18n.loading;

	try {
		const params = new URLSearchParams( {
			booking_type: type,
			start_datetime: window_.start,
			end_datetime: window_.end,
			guest_count: guests,
			booking_mode: currentMode( form ),
		} );

		const addons = selectedAddons( form );

		if ( addons ) {
			params.set( 'addons', addons );
		}

		// Whatever the extensions want asked about - a coupon code, say.
		Object.entries( collect( 'quoteParams', form ) ).forEach( ( [ key, value ] ) => {
			if ( '' !== value && null !== value && undefined !== value ) {
				params.set( key, value );
			}
		} );

		const response = await fetch( `${ config.restRoot }yachts/${ yachtId }/quote?${ params }` );
		const data = await response.json();

		if ( ! response.ok ) {
			priceBox.hidden = true;
			errorBox.hidden = false;
			errorBox.textContent = data.message || config.i18n.notAvailable;
			form.dataset.validQuote = '';
			// Slot unavailable (booked, off-day, too close to another
			// booking, ...) - keep the book button unclickable.
			setSubmitEnabled( form, false );
			return;
		}

		const remaining = data.availability && data.availability.remaining_capacity;

		if ( 'shared' === currentMode( form ) && null !== remaining && undefined !== remaining ) {
			const guestsInput = form.querySelector( '.ybs-bf-guests' );

			if ( guestsInput && Number( remaining ) > 0 ) {
				guestsInput.max = Number( remaining );
				clampGuests( form );
			}
		}

		renderPrice( form, data.pricing, remaining );
		notify( 'onQuote', form, data.pricing );

		form.dataset.validQuote = '1';
		setSubmitEnabled( form, true );
		updateHiddenFields( form );
	} catch ( e ) {
		priceBox.hidden = true;
	}
}

async function submitBooking( form ) {
	const errorBox = form.querySelector( '.ybs-bf-error' );
	const config = window.mageyaboFrontendConfig;
	errorBox.hidden = true;

	const yachtId = currentYachtId( form );
	const window_ = computeWindow( form );

	if ( ! yachtId || ! window_ ) {
		errorBox.hidden = false;
		errorBox.textContent = config.i18n.selectYacht;
		return;
	}

	if ( ! form.querySelector( '.ybs-bf-terms' ).checked ) {
		errorBox.hidden = false;
		errorBox.textContent = config.i18n.termsRequired;
		return;
	}

	// The live quote is what validates the slot - never submit against a
	// failed/unknown availability check.
	if ( '1' !== form.dataset.validQuote ) {
		errorBox.hidden = false;
		errorBox.textContent = config.i18n.notAvailable;
		return;
	}

	const payload = {
		...collect( 'submitData', form ),
		yacht_id: Number( yachtId ),
		booking_type: form.querySelector( '.ybs-bf-type' ).value,
		booking_mode: currentMode( form ),
		start_datetime: window_.start,
		end_datetime: window_.end,
		guest_count: Number( form.querySelector( '.ybs-bf-guests' ).value || 1 ),
		addons: addonsObject( form ),
		payment_method: selectedPaymentMethod( form ),
		terms_accepted: true,
		guest: {
			name: form.querySelector( '.ybs-bf-name' ).value,
			email: form.querySelector( '.ybs-bf-email' ).value,
			phone: form.querySelector( '.ybs-bf-phone' ).value,
		},
	};

	const submitButton = form.querySelector( '.ybs-bf-submit' );
	submitButton.disabled = true;

	try {
		const response = await fetch( `${ config.restRoot }bookings`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( payload ),
		} );

		const data = await response.json();

		if ( ! response.ok ) {
			errorBox.hidden = false;
			errorBox.textContent = data.message || config.i18n.notAvailable;
			submitButton.disabled = false;
			return;
		}

		// Stripe (embedded): the booking exists, but payment isn't - mount its
		// card form inline instead of redirecting anywhere.
		if ( data.payment && data.payment.client_secret ) {
			await mountStripeCheckout( form, data.payment );
			return;
		}

		// PayPal (and anything else that hands back a hosted page): there's
		// nothing left to do here but send the browser there.
		if ( data.payment && data.payment.redirect ) {
			window.location.href = data.payment.redirect;
			return;
		}

		// No further payment step (offline, or any future gateway that
		// neither redirects nor hands back a client secret) - show the
		// confirmation inside the popup itself rather than replacing the
		// whole form. Only WooCommerce-mode forms (which never reach this
		// branch - they submit natively and leave the page) lack a popup at
		// all, so the fallback below is just a safety net, not the normal path.
		if ( ! showBookingSuccess( form, data, payload, window_ ) ) {
			document.body.classList.remove( 'ybs-bf-modal-open' );
			form.innerHTML = '<div class="ybs-notice is-success">' +
				( config.i18n.bookingConfirmed || 'Thank you - your booking request has been received.' ) +
				'</div>';
		}
	} catch ( e ) {
		errorBox.hidden = false;
		errorBox.textContent = config.i18n.notAvailable;
		submitButton.disabled = false;
	}
}

export function initBookingForms() {
	document.querySelectorAll( '[data-ybs-booking-form]' ).forEach( ( form ) => {
		const wcMode = '1' === form.dataset.ybsWc;

		toggleFields( form );

		if ( ! wcMode ) {
			populatePaymentMethods( form );
		}

		const dateInput = form.querySelector( '.ybs-bf-date' );

		if ( dateInput && ! dateInput.value ) {
			dateInput.value = defaultDateValue();
		}

		resetGuestsMax( form );

		const debouncedRefresh = debounce( () => refreshQuote( form ), 400 );

		form.querySelectorAll( '.ybs-bf-mode, .ybs-bf-type, .ybs-bf-date, .ybs-bf-yacht' ).forEach( ( field ) => {
			field.addEventListener( 'change', () => {
				toggleFields( form );
				// A yacht/mode switch changes what "too many guests" means -
				// full mode caps at the yacht's capacity, so reset there
				// before refreshQuote tightens it further for shared mode.
				resetGuestsMax( form );

				// Extras belong to a yacht, so a different yacht means a
				// different list - and loadAddons() re-quotes through the
				// change handlers it wires on the new checkboxes.
				if ( field.classList.contains( 'ybs-bf-yacht' ) ) {
					loadAddons( form );
				}

				refreshQuote( form );
			} );
		} );

		// Anything an add-on rendered into this form gets wired up now, in
		// the same pass - including extensions registered before this ran.
		extensions.forEach( ( extension ) => mountInto( extension, form ) );

		loadAddons( form );

		// Number fields (start time, duration, nights, guests) update live
		// while typing/using the spinner, not just on blur - debounced so
		// rapid clicks on the stepper don't fire a quote per click.
		form.querySelectorAll( '.ybs-bf-start-time, .ybs-bf-duration, .ybs-bf-nights, .ybs-bf-guests' ).forEach( ( field ) => {
			field.addEventListener( 'input', debouncedRefresh );
			field.addEventListener( 'change', () => refreshQuote( form ) );
		} );

		// Guests specifically also gets clamped immediately as the visitor
		// types, independent of the debounced quote refresh above - typing
		// a number over the cap snaps back down right away.
		const guestsField = form.querySelector( '.ybs-bf-guests' );

		if ( guestsField ) {
			guestsField.addEventListener( 'input', () => clampGuests( form ) );
		}

		if ( wcMode ) {
			// WooCommerce checkout: the form posts add-to-cart natively; JS
			// only syncs the computed booking window into hidden fields and
			// blocks submission when the selection is incomplete.
			form.addEventListener( 'submit', ( event ) => {
				const errorBox = form.querySelector( '.ybs-bf-error' );
				const yachtId = currentYachtId( form );
				const window_ = computeWindow( form );
				// In WooCommerce mode the guest fields/terms live on the
				// checkout billing form, so only the charter window matters.
				const termsInput = form.querySelector( '.ybs-bf-terms' );
				const terms = ! termsInput || termsInput.checked;

				if ( ! yachtId || ! window_ || ! terms || '1' !== form.dataset.validQuote ) {
					event.preventDefault();
					errorBox.hidden = false;
					errorBox.textContent =
						( window.mageyaboFrontendConfig && window.mageyaboFrontendConfig.i18n.notAvailable ) || 'This slot is not available.';
					return;
				}

				updateHiddenFields( form );
			} );
		} else {
			const openModalButton = form.querySelector( '.ybs-bf-open-modal' );

			if ( openModalButton ) {
				openModalButton.addEventListener( 'click', () => openModal( form ) );
			}

			form.querySelectorAll( '[data-ybs-bf-modal-close]' ).forEach( ( el ) => {
				el.addEventListener( 'click', () => closeModal( form ) );
			} );

			const modal = form.querySelector( '[data-ybs-bf-modal]' );

			if ( modal ) {
				document.addEventListener( 'keydown', ( event ) => {
					if ( 'Escape' === event.key && ! modal.hidden ) {
						closeModal( form );
					}
				} );
			}

			form.querySelector( '.ybs-bf-submit' ).addEventListener( 'click', () => submitBooking( form ) );
		}

		// Show a price immediately with the form's own defaults, rather than
		// waiting for the visitor to touch a field first.
		if ( currentYachtId( form ) ) {
			refreshQuote( form );
		}
	} );
}
