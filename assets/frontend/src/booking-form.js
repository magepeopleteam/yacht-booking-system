const SLOT_WINDOWS = {
	half_day: [ '08:00', '12:00' ],
	morning_slot: [ '08:00', '13:00' ],
	evening_slot: [ '15:00', '20:00' ],
	daily: [ '08:00', '20:00' ],
};

function computeWindow( form ) {
	const type = find( form, '.ybs-bf-type' ).value;
	const date = find( form, '.ybs-bf-date' ).value;

	if ( ! type || ! date ) {
		return null;
	}

	if ( 'hourly' === type ) {
		const startTime = find( form, '.ybs-bf-start-time' ).value || '10:00';
		const duration = parseFloat( find( form, '.ybs-bf-duration' ).value || '2' );
		const start = new Date( `${ date }T${ startTime }:00` );
		const end = new Date( start.getTime() + duration * 60 * 60 * 1000 );
		return { start: toMysql( start ), end: toMysql( end ) };
	}

	if ( 'multiday' === type ) {
		const nights = parseInt( find( form, '.ybs-bf-nights' ).value || '2', 10 );
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

/* ---- Minimum notice: never default to a date the yacht cannot take ---- */

const serverClockOffset = ( () => {
	const serverTime = window.mageyaboFrontendConfig && Number( window.mageyaboFrontendConfig.serverTime );
	return serverTime ? serverTime * 1000 - Date.now() : 0;
} )();

function minNoticeHours( form ) {
	const picker = form.querySelector( '.ybs-bf-yacht' );
	const source = picker && picker.value && picker.selectedOptions[ 0 ] ? picker.selectedOptions[ 0 ].dataset : form.dataset;

	return Math.max( 0, parseInt( source.minNoticeHours || '0', 10 ) || 0 );
}

/**
 * The earliest start the server will accept, in the same "Y-m-d H:i:s"
 * wall-clock form computeWindow() produces - the server reads that string as
 * UTC and compares it with time() + notice, so this does exactly the same.
 * Five minutes of slack covers the gap between choosing and submitting.
 */
function earliestStart( form ) {
	const earliest = new Date( Date.now() + serverClockOffset + ( minNoticeHours( form ) * 60 + 5 ) * 60 * 1000 );
	const pad = ( n ) => String( n ).padStart( 2, '0' );

	return `${ earliest.getUTCFullYear() }-${ pad( earliest.getUTCMonth() + 1 ) }-${ pad( earliest.getUTCDate() ) } ${ pad( earliest.getUTCHours() ) }:${ pad( earliest.getUTCMinutes() ) }:00`;
}

/**
 * Keeps the date on one the yacht can actually take: the date field cannot
 * go earlier than the notice period allows, and when the chosen date (with
 * the current charter type's start time) is too soon, it moves forward to
 * the first day that is not. `force` starts the search from tomorrow - the
 * form's long-standing default - rather than from the current value.
 */
function ensureBookableDate( form, force = false ) {
	const dateInput = form.querySelector( '.ybs-bf-date' );

	if ( ! dateInput ) {
		return;
	}

	const earliest = earliestStart( form );
	dateInput.min = earliest.slice( 0, 10 );

	const current = computeWindow( form );

	if ( ! force && current && current.start >= earliest ) {
		return;
	}

	const day = new Date( `${ ( force || ! dateInput.value ? defaultDateValue() : dateInput.value ) }T12:00:00` );
	const pad = ( n ) => String( n ).padStart( 2, '0' );

	for ( let i = 0; i < 90; i++ ) {
		dateInput.value = `${ day.getFullYear() }-${ pad( day.getMonth() + 1 ) }-${ pad( day.getDate() ) }`;

		const window_ = computeWindow( form );

		if ( window_ && window_.start >= earliest ) {
			return;
		}

		day.setDate( day.getDate() + 1 );
	}
}

/**
 * When the form's own default (not something the visitor chose) turns out to
 * be taken - another booking, its turnaround buffer, an off day - ask the
 * server for the first window that is free for this yacht, charter type,
 * duration, guests and mode, and select it. Tried once per combination of
 * those, so an unbookable yacht cannot loop.
 *
 * @return {Promise<boolean>} whether a free window was selected.
 */
async function selectNextAvailable( form ) {
	const config = window.mageyaboFrontendConfig || {};
	const yachtId = currentYachtId( form );
	const typeSelect = form.querySelector( '.ybs-bf-type' );
	const dateInput = form.querySelector( '.ybs-bf-date' );

	if ( ! yachtId || ! typeSelect || ! dateInput || form.dataset.userPickedTime ) {
		return false;
	}

	const params = new URLSearchParams( {
		booking_type: typeSelect.value,
		booking_mode: currentMode( form ),
		guest_count: ( form.querySelector( '.ybs-bf-guests' ) || {} ).value || 1,
		duration: ( form.querySelector( '.ybs-bf-duration' ) || {} ).value || 2,
		nights: ( form.querySelector( '.ybs-bf-nights' ) || {} ).value || 2,
		from: dateInput.min || '',
	} );

	const key = `${ yachtId }|${ params }`;

	if ( form.dataset.autoSlotKey === key ) {
		return false;
	}

	form.dataset.autoSlotKey = key;

	try {
		const response = await fetch( `${ config.restRoot }yachts/${ yachtId }/next-available?${ params }` );

		if ( ! response.ok ) {
			return false;
		}

		const slot = await response.json();

		if ( ! slot || ! slot.start_datetime || form.dataset.userPickedTime ) {
			return false;
		}

		dateInput.value = slot.start_datetime.slice( 0, 10 );

		const startTime = form.querySelector( '.ybs-bf-start-time' );

		if ( startTime && 'hourly' === typeSelect.value ) {
			startTime.value = slot.start_datetime.slice( 11, 16 );
		}

		return true;
	} catch ( e ) {
		return false;
	}
}

function defaultDateValue() {
	const date = new Date();
	date.setDate( date.getDate() + 1 );

	const pad = ( n ) => String( n ).padStart( 2, '0' );
	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad( date.getDate() ) }`;
}

/**
 * The booking drawer is moved to <body> the first time it opens (see
 * openModal()), so a fixed panel is never trapped inside a transformed or
 * sticky ancestor. Everything that used to be looked up inside the form is
 * looked up in the form first and then in its drawer.
 */
function drawerOf( form ) {
	if ( ! form._ybsDrawer ) {
		form._ybsDrawer = form.querySelector( '[data-ybs-bf-modal]' );
	}

	return form._ybsDrawer;
}

function find( form, selector ) {
	const inForm = form.querySelector( selector );

	if ( inForm ) {
		return inForm;
	}

	const drawer = drawerOf( form );

	return drawer && ! form.contains( drawer ) ? drawer.querySelector( selector ) : null;
}

function findAll( form, selector ) {
	const matches = Array.from( form.querySelectorAll( selector ) );
	const drawer = drawerOf( form );

	if ( drawer && ! form.contains( drawer ) ) {
		matches.push( ...drawer.querySelectorAll( selector ) );
	}

	return matches;
}

function money( value ) {
	const config = window.mageyaboFrontendConfig || {};
	return `${ config.currency || '$' }${ Number( value || 0 ).toFixed( 2 ) }`;
}

function debounce( fn, delay ) {
	let timer;
	return ( ...args ) => {
		clearTimeout( timer );
		timer = setTimeout( () => fn( ...args ), delay );
	};
}

function toggleFields( form ) {
	const type = find( form, '.ybs-bf-type' ).value;

	findAll( form, '.ybs-bf-hourly-fields' ).forEach( ( el ) => {
		el.hidden = 'hourly' !== type;
	} );

	findAll( form, '.ybs-bf-multiday-fields' ).forEach( ( el ) => {
		el.hidden = 'multiday' !== type;
	} );
}

/**
 * Keep the type picker aligned with prices configured for the current yacht
 * and sales mode. The server remains authoritative; this simply prevents a
 * visitor choosing an option that the pricing engine must reject.
 */
function syncBookingTypes( form ) {
	const yachtSelect = find( form, '.ybs-bf-yacht' );
	const source = yachtSelect ? yachtSelect.selectedOptions[ 0 ] : form;
	const typeSelect = find( form, '.ybs-bf-type' );

	if ( ! source || ! typeSelect ) {
		return;
	}

	const dataKey = 'shared' === currentMode( form ) ? 'bookingTypesShared' : 'bookingTypesFull';

	// Older/custom markup without availability metadata retains its existing
	// behaviour instead of having every option hidden.
	if ( undefined === source.dataset[ dataKey ] ) {
		return;
	}

	const available = source.dataset[ dataKey ].split( ',' ).filter( Boolean );
	let firstAvailable = '';

	Array.from( typeSelect.options ).forEach( ( option ) => {
		const enabled = available.includes( option.value );

		option.hidden = ! enabled;
		option.disabled = ! enabled;

		if ( enabled && ! firstAvailable ) {
			firstAvailable = option.value;
		}
	} );

	if ( ! available.includes( typeSelect.value ) ) {
		typeSelect.value = firstAvailable;
	}
}

/**
 * One radio "card" per enabled gateway instead of a plain <select> - visually
 * selecting a payment method rather than picking it from a closed dropdown.
 * Each input keeps the shared `.ybs-bf-payment` class so the rest of this
 * file can keep asking "which one is checked" the same way it used to ask
 * "what's the select's value".
 */
function populatePaymentMethods( form ) {
	const list = find( form, '[data-ybs-bf-pm-list]' );

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
	const checked = find( form, '.ybs-bf-payment:checked' );
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
	const fields = find( form, '[data-ybs-bf-modal-fields]' );
	const cardBox = find( form, '[data-ybs-bf-stripe-card]' );
	const mountEl = find( form, '[data-ybs-bf-stripe-mount]' );
	const errorBox = find( form, '.ybs-bf-error' );

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
	const box = find( form, '[data-ybs-bf-addons]' );

	if ( ! box ) {
		return;
	}

	const config = window.mageyaboFrontendConfig;
	const list = find( form, '[data-ybs-bf-addons-list]' );
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

	findAll( form, '.ybs-bf-addon' ).forEach( ( row ) => {
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
	const priceBox = find( form, '.ybs-bf-price' );
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
	const select = find( form, '.ybs-bf-yacht' );
	return select ? select.value : form.dataset.yachtId;
}

function currentCapacity( form ) {
	const yachtSelect = find( form, '.ybs-bf-yacht' );

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
	const input = find( form, '.ybs-bf-guests' );

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
	const input = find( form, '.ybs-bf-guests' );

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
	const modeSelect = find( form, '.ybs-bf-mode' );

	if ( modeSelect ) {
		return modeSelect.value || 'full';
	}

	const yachtSelect = find( form, '.ybs-bf-yacht' );
	const selectedMode = yachtSelect && yachtSelect.selectedOptions[ 0 ]
		? yachtSelect.selectedOptions[ 0 ].dataset.ybsMode
		: '';

	if ( 'shared' === selectedMode ) {
		return 'shared';
	}

	return form.dataset.ybsMode || 'full';
}

function updateHiddenFields( form ) {
	if ( '1' !== form.dataset.ybsWc ) {
		return;
	}

	const window_ = computeWindow( form );
	const set = ( name, value ) => {
		const input = find( form, `input[name="${ name }"]` );
		if ( input ) {
			input.value = value;
		}
	};

	set( 'mageyabo_booking_type', find( form, '.ybs-bf-type' ).value );
	set( 'mageyabo_booking_mode', currentMode( form ) );
	set( 'mageyabo_guest_count', find( form, '.ybs-bf-guests' ).value || 1 );
	set( 'mageyabo_start_datetime', window_ ? window_.start : '' );
	set( 'mageyabo_end_datetime', window_ ? window_.end : '' );
	set( 'mageyabo_addons', selectedAddons( form ) );

	// This path submits natively, so an extension's value has to travel as a
	// hidden input rather than in a JSON body.
	Object.entries( collect( 'hiddenFields', form ) ).forEach( ( [ name, value ] ) => set( name, value ) );
}

function setSubmitEnabled( form, enabled ) {
	const button = find( form, '.ybs-bf-submit' );

	if ( button ) {
		button.disabled = ! enabled;
	}
}

/**
 * "Book Now" slides the booking drawer in from the right: the summary of
 * what is being booked, then guest details, coupon, payment and terms. It
 * opens whether or not the current quote is valid, so an availability
 * problem is something the visitor sees explained in the drawer rather than
 * a button that is disabled for no visible reason.
 */
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Moves the drawer's progress strip (Summary · Checkout · Confirmed) to
 * `step`: the steps before it are ticked, it is the current one.
 */
function setStep( form, step ) {
	const drawer = drawerOf( form );
	const steps = drawer && drawer.querySelector( '[data-ybs-bf-steps]' );

	if ( ! steps ) {
		return;
	}

	drawer.dataset.step = String( step );

	steps.querySelectorAll( '[data-step]' ).forEach( ( item ) => {
		const n = parseInt( item.dataset.step, 10 );
		item.classList.toggle( 'is-done', n < step || 3 === step );
		item.classList.toggle( 'is-current', n === step );

		if ( n === step ) {
			item.setAttribute( 'aria-current', 'step' );
		} else {
			item.removeAttribute( 'aria-current' );
		}
	} );
}

function openModal( form ) {
	const drawer = drawerOf( form );

	if ( ! drawer ) {
		return;
	}

	if ( drawer.parentElement !== document.body ) {
		document.body.appendChild( drawer );
	}

	form._ybsLastFocus = document.activeElement;

	// Carry the page's accent across: gold on a yacht page (its
	// `--ys-gold`), the booking form's own primary everywhere else.
	const styles = window.getComputedStyle( form );
	const accent = ( styles.getPropertyValue( '--ys-gold-strong' ) || styles.getPropertyValue( '--ybs-primary' ) ).trim();

	if ( accent ) {
		drawer.style.setProperty( '--ybs-drawer-accent', accent );
		// Focus rings and the selected payment card follow it too.
		drawer.style.setProperty( '--ybs-primary', accent );
		drawer.style.setProperty( '--ybs-primary-soft', `color-mix(in srgb, ${ accent } 16%, transparent)` );
	}

	renderSummary( form );

	// A WooCommerce drawer opens on its summary (checkout follows in the
	// frame); the native one has the details form right under it.
	if ( '3' !== drawer.dataset.step ) {
		setStep( form, drawer.classList.contains( 'is-wc' ) && ! drawer.classList.contains( 'is-checkout' ) ? 1 : 2 );
	}

	// When the charter cannot be booked as chosen, say why inside the drawer
	// too - not just on the page behind it.
	const drawerError = drawer.querySelector( '[data-ybs-bf-drawer-error]' );
	const pageError = form.querySelector( '.ybs-bf-error' );

	if ( drawerError && pageError && pageError !== drawerError && '1' !== form.dataset.validQuote && ! pageError.hidden && pageError.textContent ) {
		drawerError.textContent = pageError.textContent;
		drawerError.hidden = false;
	}

	drawer.hidden = false;
	document.body.classList.add( 'ybs-bf-modal-open' );

	// One frame with the drawer displayed but off-canvas, so the slide-in
	// transition has a start state to animate from.
	window.requestAnimationFrame( () => {
		window.requestAnimationFrame( () => drawer.classList.add( 'is-open' ) );
	} );

	const firstField = drawer.querySelector( '.ybs-bf-name' );
	const panel = drawer.querySelector( '.ybs-bf-drawer__panel' );
	const target = firstField && ! firstField.closest( '[hidden]' ) ? firstField : panel;

	if ( target ) {
		target.focus( { preventScroll: true } );
	}
}

function closeModal( form ) {
	const drawer = drawerOf( form );

	if ( ! drawer || drawer.hidden ) {
		return;
	}

	drawer.classList.remove( 'is-open' );
	document.body.classList.remove( 'ybs-bf-modal-open' );

	const reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	window.setTimeout( () => {
		if ( ! drawer.classList.contains( 'is-open' ) ) {
			drawer.hidden = true;
		}
	}, reduceMotion ? 0 : 320 );

	if ( form._ybsLastFocus && document.contains( form._ybsLastFocus ) ) {
		form._ybsLastFocus.focus( { preventScroll: true } );
	}
}

/**
 * Keeps Tab inside the open drawer, so keyboard users cannot wander into the
 * page behind the backdrop.
 */
function trapFocus( form, event ) {
	const drawer = drawerOf( form );

	if ( ! drawer || drawer.hidden || 'Tab' !== event.key ) {
		return;
	}

	const focusable = Array.from( drawer.querySelectorAll( FOCUSABLE ) ).filter( ( el ) => ! el.closest( '[hidden]' ) && el.offsetParent !== null );

	if ( ! focusable.length ) {
		return;
	}

	const first = focusable[ 0 ];
	const last = focusable[ focusable.length - 1 ];

	if ( event.shiftKey && ( document.activeElement === first || ! drawer.contains( document.activeElement ) ) ) {
		event.preventDefault();
		last.focus();
	} else if ( ! event.shiftKey && document.activeElement === last ) {
		event.preventDefault();
		first.focus();
	}
}

/**
 * The yacht currently being booked: the form's own when it is on a yacht's
 * page, otherwise the picker's selected option.
 */
function summaryYacht( form ) {
	const picker = find( form, '.ybs-bf-yacht' );
	const source = picker && picker.selectedOptions[ 0 ] && picker.value ? picker.selectedOptions[ 0 ].dataset : form.dataset;

	return {
		name: source.yachtName || '',
		thumb: source.yachtThumb || '',
		location: source.yachtLocation || '',
	};
}

function selectedLabel( select ) {
	return select && select.selectedOptions[ 0 ] ? select.selectedOptions[ 0 ].textContent.trim() : '';
}

/**
 * The drawer's top half: which yacht, when, what kind of charter, how many
 * guests, which extras - then the live price breakdown from the last quote,
 * mirrored into the sticky footer total.
 */
function renderSummary( form ) {
	const drawer = drawerOf( form );

	if ( ! drawer ) {
		return;
	}

	const config = window.mageyaboFrontendConfig || {};
	const i18n = config.i18n || {};
	const pricing = form._ybsQuote || null;

	const yachtBox = drawer.querySelector( '[data-ybs-bf-summary-yacht]' );
	const details = drawer.querySelector( '[data-ybs-bf-summary-details]' );
	const priceBox = drawer.querySelector( '[data-ybs-bf-summary-price]' );
	const totalEl = drawer.querySelector( '[data-ybs-bf-drawer-total]' );
	const totalLabel = drawer.querySelector( '[data-ybs-bf-drawer-total-label]' );

	if ( yachtBox ) {
		const yacht = summaryYacht( form );
		yachtBox.innerHTML = '';
		yachtBox.hidden = ! yacht.name;

		const badge = document.createElement( 'span' );
		badge.className = 'ybs-bf-summary__badge';
		badge.setAttribute( 'aria-hidden', 'true' );
		yachtBox.appendChild( badge );

		const text = document.createElement( 'div' );
		const name = document.createElement( 'strong' );
		name.className = 'ybs-bf-summary__name';
		name.textContent = yacht.name;
		text.appendChild( name );

		if ( yacht.location ) {
			const location = document.createElement( 'span' );
			location.className = 'ybs-bf-summary__location';
			location.textContent = yacht.location;
			text.appendChild( location );
		}

		yachtBox.appendChild( text );
	}

	if ( details ) {
		const window_ = computeWindow( form );
		const typeSelect = find( form, '.ybs-bf-type' );
		let charter = selectedLabel( typeSelect );

		if ( typeSelect && 'hourly' === typeSelect.value ) {
			const hours = parseFloat( ( find( form, '.ybs-bf-duration' ) || {} ).value || '0' );
			charter = hours ? `${ charter } · ${ hours } h` : charter;
		} else if ( typeSelect && 'multiday' === typeSelect.value ) {
			const nights = parseInt( ( find( form, '.ybs-bf-nights' ) || {} ).value || '0', 10 );
			charter = nights ? `${ charter } · ${ nights }` : charter;
		}

		const extras = [];

		findAll( form, '.ybs-bf-addon' ).forEach( ( row ) => {
			const checkbox = row.querySelector( '.ybs-bf-addon__check' );

			if ( checkbox && checkbox.checked ) {
				const qty = parseInt( ( row.querySelector( '.ybs-bf-addon__qty' ) || {} ).value || '1', 10 ) || 1;
				const name = ( row.querySelector( '.ybs-bf-addon__name' ) || {} ).textContent || '';
				extras.push( qty > 1 ? `${ name } × ${ qty }` : name );
			}
		} );

		const rows = [
			[ i18n.detailDates || 'Date & Time', window_ ? formatDateRange( window_.start, window_.end ) : '', 'date' ],
			[ i18n.detailCharter || 'Charter', charter, 'charter' ],
			[ i18n.detailBooking || 'Booking', find( form, '.ybs-bf-mode' ) ? selectedLabel( find( form, '.ybs-bf-mode' ) ) : '', 'booking' ],
			[ i18n.detailGuests || 'Guests', String( ( find( form, '.ybs-bf-guests' ) || {} ).value || 1 ), 'guests' ],
			[ i18n.detailExtras || 'Extras', extras.join( ', ' ), 'extras' ],
		];

		details.innerHTML = '';

		rows.forEach( ( [ label, value, key ] ) => {
			if ( ! value ) {
				return;
			}

			const dt = document.createElement( 'dt' );
			dt.className = 'is-' + key;
			dt.textContent = label;
			const dd = document.createElement( 'dd' );
			dd.textContent = value;
			details.append( dt, dd );
		} );
	}

	if ( priceBox ) {
		priceBox.innerHTML = '';

		if ( pricing ) {
			const coupon = pricing.coupon;
			const lines = [ [ i18n.charter || 'Charter', money( pricing.base_price + pricing.adjustment_total ) ] ];

			if ( pricing.addons_total > 0 ) {
				lines.push( [ i18n.extrasTotal || 'Extras', money( pricing.addons_total ) ] );
			}

			if ( pricing.discount_total > 0 ) {
				const label = coupon && coupon.code
					? ( i18n.couponDiscount || 'Discount (%s)' ).replace( '%s', coupon.code )
					: ( i18n.discount || 'Discount' );
				lines.push( [ label, `-${ money( pricing.discount_total ) }`, 'is-discount' ] );
			}

			if ( pricing.tax_total > 0 ) {
				lines.push( [ i18n.tax || 'Tax', money( pricing.tax_total ) ] );
			}

			lines.push( [ i18n.total || 'Total', money( pricing.total ), 'is-total' ] );

			if ( pricing.deposit_amount > 0 ) {
				lines.push( [ i18n.dueNow || 'Due now', money( pricing.deposit_amount ), 'is-due' ] );
			}

			lines.forEach( ( [ label, value, modifier ] ) => {
				const row = document.createElement( 'div' );
				row.className = 'ybs-bf-summary__row' + ( modifier ? ' ' + modifier : '' );
				const labelEl = document.createElement( 'span' );
				labelEl.textContent = label;
				const valueEl = document.createElement( 'span' );
				valueEl.textContent = value;
				row.append( labelEl, valueEl );
				priceBox.appendChild( row );
			} );
		}

		priceBox.hidden = ! pricing;
	}

	if ( totalEl ) {
		const due = pricing && pricing.deposit_amount > 0;
		totalEl.textContent = pricing ? money( due ? pricing.deposit_amount : pricing.total ) : '—';

		if ( totalLabel ) {
			totalLabel.textContent = due ? ( i18n.dueNow || 'Due now' ) : ( i18n.total || 'Total' );
		}
	}
}

/* ---- Coupon code (built-in discount codes, see CouponService.php) ---- */

/**
 * The built-in coupon box only - never the Pro add-on's field, which uses the
 * same classes but is wired by the add-on itself. Grabbing Pro's box here
 * (which has no Remove button) threw during setup and left the whole form
 * without a quote or a working Confirm button.
 */
function couponBox( form ) {
	return find( form, '[data-ybs-bf-coupon="builtin"]' );
}

function setCouponMessage( form, text, isError ) {
	const box = couponBox( form );
	const message = box && box.querySelector( '[data-ybs-bf-coupon-message]' );

	if ( ! message ) {
		return;
	}

	message.textContent = text || '';
	message.hidden = ! text;
	message.classList.toggle( 'is-error', !! isError );
}

function showCouponEntry( form, showEntry ) {
	const box = couponBox( form );

	if ( ! box ) {
		return;
	}

	box.querySelector( '[data-ybs-bf-coupon-entry]' ).hidden = ! showEntry;
	box.querySelector( '[data-ybs-bf-coupon-applied]' ).hidden = showEntry;
}

/**
 * Reads the quote's verdict on the code in play: accepted (show it as an
 * applied tag with the saving) or refused (drop it, say why). A code that
 * stops qualifying later - fewer guests pushing the total under its minimum
 * spend, say - is dropped the same way on the next quote.
 */
function handleCouponResult( form, pricing ) {
	const box = couponBox( form );

	if ( ! box || ! form.dataset.couponCode ) {
		return;
	}

	const i18n = ( window.mageyaboFrontendConfig || {} ).i18n || {};
	const applyButton = box.querySelector( '.ybs-bf-coupon-apply' );
	applyButton.disabled = false;

	if ( pricing && pricing.coupon ) {
		box.querySelector( '[data-ybs-bf-coupon-code]' ).textContent = pricing.coupon.code;
		box.querySelector( '[data-ybs-bf-coupon-saving]' ).textContent = ( i18n.couponSaved || 'You save %s' ).replace( '%s', money( pricing.coupon.discount ) );
		showCouponEntry( form, false );
		setCouponMessage( form, '' );
	} else {
		delete form.dataset.couponCode;
		showCouponEntry( form, true );
		setCouponMessage( form, ( pricing && pricing.coupon_error ) || i18n.notAvailable, true );
	}

	delete form.dataset.couponPending;
}

function applyCoupon( form ) {
	const box = couponBox( form );
	const i18n = ( window.mageyaboFrontendConfig || {} ).i18n || {};
	const input = box.querySelector( '.ybs-bf-coupon-input' );
	const code = input.value.trim().toUpperCase();

	if ( ! code ) {
		setCouponMessage( form, i18n.couponEmpty || 'Enter a coupon code first.', true );
		input.focus();
		return;
	}

	input.value = code;
	form.dataset.couponCode = code;
	form.dataset.couponPending = '1';
	box.querySelector( '.ybs-bf-coupon-apply' ).disabled = true;
	setCouponMessage( form, i18n.couponChecking || 'Checking code…', false );

	refreshQuote( form );
}

function removeCoupon( form, announce = true ) {
	const box = couponBox( form );
	const i18n = ( window.mageyaboFrontendConfig || {} ).i18n || {};

	delete form.dataset.couponCode;
	delete form.dataset.couponPending;

	if ( box ) {
		box.querySelector( '.ybs-bf-coupon-input' ).value = '';
		box.querySelector( '.ybs-bf-coupon-apply' ).disabled = false;
		showCouponEntry( form, true );
		setCouponMessage( form, announce ? i18n.couponRemoved || 'Coupon removed.' : '', false );
	}

	refreshQuote( form );
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

const DOWNLOAD_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';

/**
 * The same download buttons mageyabo_booking_documents_html() prints on the
 * confirmation page, built from the `documents` the booking route returns.
 */
function documentButtons( documents, i18n ) {
	const box = document.createElement( 'div' );
	box.className = 'ybs-docs';

	const label = document.createElement( 'span' );
	label.className = 'ybs-docs__label';
	label.textContent = i18n.yourDocuments || 'Your documents';

	const buttons = document.createElement( 'div' );
	buttons.className = 'ybs-docs__buttons';

	documents.forEach( ( doc ) => {
		if ( ! doc || ! doc.url ) {
			return;
		}

		const a = document.createElement( 'a' );
		a.className = 'ybs-docs__button is-' + String( doc.id || '' ).replace( /[^a-z0-9_-]/gi, '' );
		a.href = doc.url;
		a.target = '_blank';
		a.rel = 'noopener';
		a.innerHTML = DOWNLOAD_ICON;

		const text = document.createElement( 'span' );
		text.textContent = doc.label;
		a.append( text );
		buttons.append( a );
	} );

	box.append( label, buttons );

	return box;
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
	const drawer = drawerOf( form );
	const fields = find( form, '[data-ybs-bf-modal-fields]' );
	const cardBox = find( form, '[data-ybs-bf-stripe-card]' );
	const successBox = find( form, '[data-ybs-bf-success]' );
	const messageEl = find( form, '[data-ybs-bf-success-message]' );
	const detailsEl = find( form, '[data-ybs-bf-success-details]' );
	const yachtEl = find( form, '[data-ybs-bf-success-yacht]' );
	const totalEl = find( form, '[data-ybs-bf-success-total]' );
	const extrasEl = find( form, '[data-ybs-bf-success-extras]' );
	const actionsEl = find( form, '[data-ybs-bf-success-actions]' );
	const titleEl = find( form, '.ybs-bf-modal__title' );
	const openModalButton = find( form, '.ybs-bf-open-modal' );

	if ( ! successBox ) {
		return false;
	}

	[ fields, cardBox, find( form, '[data-ybs-bf-summary]' ), find( form, '[data-ybs-bf-drawer-footer]' ) ].forEach( ( el ) => {
		if ( el ) {
			el.hidden = true;
		}
	} );

	if ( drawer ) {
		drawer.classList.add( 'is-success' );
	}

	setStep( form, 3 );

	if ( titleEl ) {
		titleEl.textContent = i18n.bookingConfirmedTitle || 'Booking Confirmed!';
	}

	if ( messageEl ) {
		messageEl.textContent = payload.guest.email && i18n.bookingConfirmedWithEmail
			? i18n.bookingConfirmedWithEmail.replace( '%s', payload.guest.email )
			: ( i18n.bookingConfirmed || 'Thank you - your booking request has been received.' );
	}

	// The ticket's head: the same yacht card the summary showed.
	const summaryYachtEl = find( form, '[data-ybs-bf-summary-yacht]' );

	if ( yachtEl && summaryYachtEl && ! summaryYachtEl.hidden && summaryYachtEl.childElementCount ) {
		yachtEl.innerHTML = summaryYachtEl.innerHTML;

		const reference = document.createElement( 'span' );
		reference.className = 'ybs-bf-success__ref';
		reference.textContent = data.reference || '#' + data.booking_id;
		yachtEl.appendChild( reference );
		yachtEl.hidden = false;
	}

	if ( detailsEl ) {
		const gateways = config.gateways || {};
		const paymentLabel = ( gateways[ payload.payment_method ] && gateways[ payload.payment_method ].label ) || payload.payment_method;

		const rows = [
			[ i18n.detailBookingId || 'Booking ID', yachtEl && ! yachtEl.hidden ? '' : ( data.reference || '#' + data.booking_id ), 'ref' ],
			[ i18n.detailDates || 'Date & Time', formatDateRange( window_.start, window_.end ), 'date' ],
			[ i18n.detailGuests || 'Guests', String( payload.guest_count ), 'guests' ],
			[ i18n.detailPayment || 'Payment Method', paymentLabel, 'payment' ],
		];

		detailsEl.innerHTML = '';

		rows.forEach( ( [ label, value, key ] ) => {
			if ( ! value ) {
				return;
			}

			const dt = document.createElement( 'dt' );
			dt.className = 'is-' + key;
			dt.textContent = label;
			const dd = document.createElement( 'dd' );
			dd.textContent = value;
			detailsEl.append( dt, dd );
		} );
	}

	if ( totalEl && data.pricing ) {
		totalEl.innerHTML = '';
		const label = document.createElement( 'span' );
		label.textContent = i18n.detailTotal || 'Total';
		const value = document.createElement( 'strong' );
		value.textContent = `${ config.currency }${ Number( data.pricing.total ).toFixed( 2 ) }`;
		totalEl.append( label, value );
		totalEl.hidden = false;
	}

	// Ticket / invoice downloads, when an add-on provides them.
	if ( extrasEl ) {
		extrasEl.innerHTML = '';

		if ( Array.isArray( data.documents ) && data.documents.length ) {
			extrasEl.appendChild( documentButtons( data.documents, i18n ) );
		}
	}

	// The same page both hosted gateways return to - so an offline booking
	// ends up somewhere it can be looked at again later, not just in a popup
	// that closes.
	if ( data.confirmation_url && actionsEl && ! actionsEl.querySelector( '.ybs-bf-success__link' ) ) {
		const link = document.createElement( 'a' );
		link.className = 'ybs-btn ybs-bf-success__link';
		link.href = data.confirmation_url;
		link.textContent = i18n.viewBooking || 'View your booking';
		actionsEl.prepend( link );
	}

	successBox.hidden = false;

	const body = find( form, '.ybs-bf-drawer__body' );

	if ( body ) {
		body.scrollTop = 0;
	}

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
	const requestId = String( ( parseInt( form.dataset.quoteRequestId || '0', 10 ) || 0 ) + 1 );
	form.dataset.quoteRequestId = requestId;
	form.dataset.validQuote = '';
	setSubmitEnabled( form, false );

	const yachtId = currentYachtId( form );
	const priceBox = find( form, '.ybs-bf-price' );
	const errorBox = find( form, '.ybs-bf-error' );

	errorBox.hidden = true;

	if ( ! yachtId ) {
		priceBox.hidden = true;
		setSubmitEnabled( form, false );
		form._ybsQuote = null;
		return;
	}

	const window_ = computeWindow( form );

	if ( ! window_ ) {
		priceBox.hidden = true;
		setSubmitEnabled( form, false );
		form._ybsQuote = null;
		return;
	}

	const guests = find( form, '.ybs-bf-guests' ).value || 1;
	const type = find( form, '.ybs-bf-type' ).value;
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

		if ( form.dataset.couponCode ) {
			params.set( 'coupon_code', form.dataset.couponCode );
		}

		// Whatever the extensions want asked about - a coupon code, say.
		Object.entries( collect( 'quoteParams', form ) ).forEach( ( [ key, value ] ) => {
			if ( '' !== value && null !== value && undefined !== value ) {
				params.set( key, value );
			}
		} );

		const response = await fetch( `${ config.restRoot }yachts/${ yachtId }/quote?${ params }` );
		const data = await response.json();

		// A slower response for a previous selection must never validate or
		// invalidate the newer selection currently visible in the form.
		if ( requestId !== form.dataset.quoteRequestId ) {
			return;
		}

		if ( ! response.ok ) {
			// Our own default is taken: quietly move to the next free window
			// instead of greeting the visitor with an error.
			if ( 409 === response.status && ! form.dataset.userPickedTime ) {
				priceBox.textContent = config.i18n.findingSlot || 'Finding the next available time…';

				if ( await selectNextAvailable( form ) ) {
					refreshQuote( form );
					return;
				}

				if ( requestId !== form.dataset.quoteRequestId ) {
					return;
				}
			}

			priceBox.hidden = true;
			errorBox.hidden = false;
			errorBox.textContent = data.message || config.i18n.notAvailable;
			form.dataset.validQuote = '';
			form._ybsQuote = null;
			renderSummary( form );

			if ( form.dataset.couponPending ) {
				delete form.dataset.couponPending;
				setCouponMessage( form, '' );
				const applyButton = find( form, '.ybs-bf-coupon-apply' );

				if ( applyButton ) {
					applyButton.disabled = false;
				}
			}

			// Slot unavailable (booked, off-day, too close to another
			// booking, ...) - keep the book button unclickable.
			setSubmitEnabled( form, false );
			return;
		}

		const remaining = data.availability && data.availability.remaining_capacity;

		if ( 'shared' === currentMode( form ) && null !== remaining && undefined !== remaining ) {
			const guestsInput = find( form, '.ybs-bf-guests' );

			if ( guestsInput && Number( remaining ) > 0 ) {
				guestsInput.max = Number( remaining );
				clampGuests( form );
			}
		}

		form._ybsQuote = data.pricing;
		handleCouponResult( form, data.pricing );
		renderPrice( form, data.pricing, remaining );
		renderSummary( form );
		notify( 'onQuote', form, data.pricing );

		form.dataset.validQuote = '1';
		setSubmitEnabled( form, true );
		updateHiddenFields( form );
	} catch ( e ) {
		if ( requestId !== form.dataset.quoteRequestId ) {
			return;
		}

		priceBox.hidden = true;
	}
}

async function submitBooking( form ) {
	const errorBox = find( form, '.ybs-bf-error' );
	const config = window.mageyaboFrontendConfig;
	errorBox.hidden = true;

	const yachtId = currentYachtId( form );
	const window_ = computeWindow( form );

	if ( ! yachtId || ! window_ ) {
		errorBox.hidden = false;
		errorBox.textContent = config.i18n.selectYacht;
		return;
	}

	const detailInputs = [ '.ybs-bf-name', '.ybs-bf-email', '.ybs-bf-phone' ].map( ( selector ) => find( form, selector ) ).filter( Boolean );
	const invalid = detailInputs.find( ( input ) => ! input.value.trim() || ! input.checkValidity() );

	if ( invalid ) {
		errorBox.hidden = false;
		errorBox.textContent = config.i18n.invalidDetails || 'Please enter your name, a valid email address and a phone number.';
		detailInputs.forEach( ( input ) => input.setAttribute( 'aria-invalid', ! input.value.trim() || ! input.checkValidity() ? 'true' : 'false' ) );
		invalid.focus();
		return;
	}

	if ( ! find( form, '.ybs-bf-terms' ).checked ) {
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
		booking_type: find( form, '.ybs-bf-type' ).value,
		booking_mode: currentMode( form ),
		start_datetime: window_.start,
		end_datetime: window_.end,
		guest_count: Number( find( form, '.ybs-bf-guests' ).value || 1 ),
		addons: addonsObject( form ),
		payment_method: selectedPaymentMethod( form ),
		terms_accepted: true,
		...( form.dataset.couponCode ? { coupon_code: form.dataset.couponCode } : {} ),
		guest: {
			name: find( form, '.ybs-bf-name' ).value,
			email: find( form, '.ybs-bf-email' ).value,
			phone: find( form, '.ybs-bf-phone' ).value,
		},
	};

	const submitButton = find( form, '.ybs-bf-submit' );
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

			// The code stopped qualifying between the quote and the click -
			// drop it and re-quote, so the total on screen is the real one.
			if ( 'mageyabo_coupon_rejected' === data.code ) {
				removeCoupon( form, false );
				setCouponMessage( form, data.message, true );
			}

			return;
		}

		// Nothing left to confirm - hide the pinned total and button.
		const footer = find( form, '[data-ybs-bf-drawer-footer]' );

		if ( footer ) {
			footer.hidden = true;
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

/* ---- WooCommerce checkout inside the drawer (see WooCommerceDrawer.php) ---- */

const wcForms = [];

function drawerTitle( form ) {
	const title = find( form, '.ybs-bf-drawer__title' );

	if ( title && ! title.dataset.defaultTitle ) {
		title.dataset.defaultTitle = title.textContent;
	}

	return title;
}

/**
 * Swaps the drawer between its two WooCommerce stages: the booking summary
 * with "Confirm Booking", and the embedded checkout that follows it.
 */
function setCheckoutStage( form, checkoutUrl ) {
	const drawer = drawerOf( form );
	const i18n = ( window.mageyaboFrontendConfig || {} ).i18n || {};
	const showCheckout = !! checkoutUrl;
	const stage = drawer.querySelector( '[data-ybs-bf-checkout]' );
	const frame = drawer.querySelector( '[data-ybs-bf-checkout-frame]' );
	const loading = drawer.querySelector( '[data-ybs-bf-checkout-loading]' );
	const title = drawerTitle( form );

	drawer.classList.toggle( 'is-checkout', showCheckout );
	setStep( form, showCheckout ? 2 : 1 );
	stage.hidden = ! showCheckout;
	drawer.querySelector( '[data-ybs-bf-back]' ).hidden = ! showCheckout;
	drawer.querySelector( '[data-ybs-bf-summary]' ).hidden = showCheckout;
	drawer.querySelector( '[data-ybs-bf-drawer-footer]' ).hidden = showCheckout;
	drawer.querySelector( '[data-ybs-bf-drawer-error]' ).hidden = true;

	const note = drawer.querySelector( '[data-ybs-bf-wc-note]' );

	if ( note ) {
		note.hidden = showCheckout;
	}

	if ( title ) {
		title.textContent = showCheckout ? i18n.checkoutTitle || 'Checkout' : title.dataset.defaultTitle;
	}

	if ( showCheckout ) {
		loading.hidden = false;
		frame.onload = () => {
			if ( 'about:blank' !== frame.getAttribute( 'src' ) ) {
				loading.hidden = true;
			}
		};
		frame.src = checkoutUrl;
	} else {
		frame.onload = null;
		frame.src = 'about:blank';
	}
}

/**
 * "Confirm Booking" on a WooCommerce form: add the charter to the cart
 * (the server validates and prices it exactly as the classic add-to-cart
 * post would) and load the checkout into the drawer - or show WooCommerce's
 * own reason for refusing it.
 */
async function confirmWooCommerceBooking( form ) {
	const config = window.mageyaboFrontendConfig || {};
	const i18n = config.i18n || {};
	const drawer = drawerOf( form );
	const errorBox = drawer.querySelector( '[data-ybs-bf-drawer-error]' );
	const button = drawer.querySelector( '.ybs-bf-wc-confirm' );
	const showError = ( message ) => {
		errorBox.textContent = message;
		errorBox.hidden = false;
	};

	errorBox.hidden = true;

	if ( ! currentYachtId( form ) || ! computeWindow( form ) || '1' !== form.dataset.validQuote ) {
		showError( i18n.notAvailable || 'Not available for the selected time.' );
		return;
	}

	if ( ! config.wcAddToCartUrl ) {
		showError( i18n.addToCartFailed || 'The booking could not be added to your cart. Please try again.' );
		return;
	}

	updateHiddenFields( form );

	const data = new window.FormData( form );
	data.set( 'product_id', form.dataset.wcProductId || '' );
	data.set( 'quantity', '1' );

	const label = button.textContent;
	button.disabled = true;
	button.textContent = i18n.addingToCart || 'Preparing checkout…';

	try {
		const response = await fetch( config.wcAddToCartUrl, { method: 'POST', body: data, credentials: 'same-origin' } );
		const json = await response.json();

		if ( ! json || ! json.success || ! json.data || ! json.data.checkout_url ) {
			const messages = json && json.data && json.data.messages;
			showError( Array.isArray( messages ) && messages.length ? messages.join( ' ' ) : i18n.addToCartFailed || 'The booking could not be added to your cart. Please try again.' );
			return;
		}

		setCheckoutStage( form, json.data.checkout_url );
	} catch ( e ) {
		showError( i18n.addToCartFailed || 'The booking could not be added to your cart. Please try again.' );
	} finally {
		button.disabled = false;
		button.textContent = label;
	}
}

/**
 * The embedded thank-you page reports the finished order; the drawer title
 * says so and "Book Now" on the page becomes a confirmed note, as in the
 * native flow.
 */
function onEmbeddedOrderReceived( event ) {
	if ( event.origin !== window.location.origin || ! event.data || 'mageyabo:order-received' !== event.data.type ) {
		return;
	}

	const form = wcForms.find( ( candidate ) => {
		const frame = drawerOf( candidate ) && drawerOf( candidate ).querySelector( '[data-ybs-bf-checkout-frame]' );
		return frame && frame.contentWindow === event.source;
	} );

	if ( ! form ) {
		return;
	}

	const i18n = ( window.mageyaboFrontendConfig || {} ).i18n || {};
	const drawer = drawerOf( form );
	const title = drawerTitle( form );

	drawer.querySelector( '[data-ybs-bf-back]' ).hidden = true;
	setStep( form, 3 );

	if ( title ) {
		title.textContent = i18n.bookingConfirmedTitle || 'Booking Confirmed!';
	}

	const openButton = find( form, '.ybs-bf-open-modal' );

	if ( openButton ) {
		const note = document.createElement( 'p' );
		note.className = 'ybs-notice is-success ybs-bf-confirmed-note';
		note.textContent = i18n.bookingConfirmedTitle || 'Booking Confirmed!';
		openButton.replaceWith( note );
	}
}

export function initBookingForms() {
	window.addEventListener( 'message', onEmbeddedOrderReceived );

	document.querySelectorAll( '[data-ybs-booking-form]' ).forEach( ( form ) => {
		const wcMode = '1' === form.dataset.ybsWc;

		syncBookingTypes( form );
		toggleFields( form );

		if ( ! wcMode ) {
			populatePaymentMethods( form );
		}

		const dateInput = find( form, '.ybs-bf-date' );

		if ( dateInput ) {
			ensureBookableDate( form, ! dateInput.value );
		}

		resetGuestsMax( form );

		// Once the visitor picks a date or start time themselves, it is
		// theirs: never moved to "the next available" behind their back.
		form.querySelectorAll( '.ybs-bf-date, .ybs-bf-start-time' ).forEach( ( field ) => {
			const markPicked = ( event ) => {
				if ( event.isTrusted ) {
					form.dataset.userPickedTime = '1';
				}
			};

			field.addEventListener( 'input', markPicked );
			field.addEventListener( 'change', markPicked );
		} );

		const debouncedRefresh = debounce( () => refreshQuote( form ), 400 );

		findAll( form, '.ybs-bf-mode, .ybs-bf-type, .ybs-bf-date, .ybs-bf-yacht' ).forEach( ( field ) => {
			field.addEventListener( 'change', () => {
				if ( field.classList.contains( 'ybs-bf-mode' ) || field.classList.contains( 'ybs-bf-yacht' ) ) {
					syncBookingTypes( form );
				}

				toggleFields( form );

				// A different yacht, charter type or mode can change the notice
				// period or the start time - move the date on if it is now too
				// soon. A date the visitor picked themselves is left alone.
				if ( ! field.classList.contains( 'ybs-bf-date' ) ) {
					ensureBookableDate( form );
				}

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
		findAll( form, '.ybs-bf-start-time, .ybs-bf-duration, .ybs-bf-nights, .ybs-bf-guests' ).forEach( ( field ) => {
			field.addEventListener( 'input', debouncedRefresh );
			field.addEventListener( 'change', () => refreshQuote( form ) );
		} );

		// Guests specifically also gets clamped immediately as the visitor
		// types, independent of the debounced quote refresh above - typing
		// a number over the cap snaps back down right away.
		const guestsField = find( form, '.ybs-bf-guests' );

		if ( guestsField ) {
			guestsField.addEventListener( 'input', () => clampGuests( form ) );
		}

		// The drawer - opening, closing, Escape, keeping focus inside - is the
		// same for both checkout paths.
		const openModalButton = find( form, '.ybs-bf-open-modal' );

		if ( openModalButton ) {
			openModalButton.addEventListener( 'click', () => openModal( form ) );
		}

		findAll( form, '[data-ybs-bf-modal-close]' ).forEach( ( el ) => {
			el.addEventListener( 'click', () => closeModal( form ) );
		} );

		const modal = drawerOf( form );

		if ( modal ) {
			document.addEventListener( 'keydown', ( event ) => {
				if ( modal.hidden ) {
					return;
				}

				if ( 'Escape' === event.key ) {
					closeModal( form );
				} else {
					trapFocus( form, event );
				}
			} );
		}

		if ( wcMode ) {
			wcForms.push( form );

			// Enter in a field would post the form natively; the drawer is the
			// way through now.
			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				openModal( form );
			} );

			find( form, '.ybs-bf-wc-confirm' ).addEventListener( 'click', () => confirmWooCommerceBooking( form ) );
			find( form, '[data-ybs-bf-back]' ).addEventListener( 'click', () => setCheckoutStage( form, '' ) );
		} else {
			const coupon = couponBox( form );
			const couponInput = coupon && coupon.querySelector( '.ybs-bf-coupon-input' );
			const couponApply = coupon && coupon.querySelector( '.ybs-bf-coupon-apply' );
			const couponRemove = coupon && coupon.querySelector( '.ybs-bf-coupon-remove' );

			const couponToggle = coupon && coupon.querySelector( '.ybs-bf-coupon__toggle' );
			const couponPanel = coupon && coupon.querySelector( '.ybs-bf-coupon__panel' );

			// "Have a coupon code?" opens the field; the line goes away once used.
			if ( couponToggle && couponPanel ) {
				couponToggle.addEventListener( 'click', () => {
					couponPanel.hidden = false;
					coupon.classList.remove( 'is-collapsed' );
					couponToggle.setAttribute( 'aria-expanded', 'true' );
					couponToggle.hidden = true;

					if ( couponInput ) {
						couponInput.focus();
					}
				} );
			}

			if ( couponApply ) {
				couponApply.addEventListener( 'click', () => applyCoupon( form ) );
			}

			if ( couponRemove ) {
				couponRemove.addEventListener( 'click', () => removeCoupon( form ) );
			}

			if ( couponInput ) {
				couponInput.addEventListener( 'keydown', ( event ) => {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
						applyCoupon( form );
					}
				} );
			}

			findAll( form, '.ybs-bf-name, .ybs-bf-email, .ybs-bf-phone' ).forEach( ( input ) => {
				input.addEventListener( 'input', () => input.removeAttribute( 'aria-invalid' ) );
			} );

			find( form, '.ybs-bf-submit' ).addEventListener( 'click', () => submitBooking( form ) );
		}

		// Show a price immediately with the form's own defaults, rather than
		// waiting for the visitor to touch a field first.
		if ( currentYachtId( form ) ) {
			refreshQuote( form );
		}
	} );
}
