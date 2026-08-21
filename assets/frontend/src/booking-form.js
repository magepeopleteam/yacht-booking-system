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

function toggleFields( form ) {
	const type = form.querySelector( '.ybs-bf-type' ).value;

	form.querySelectorAll( '.ybs-bf-hourly-fields' ).forEach( ( el ) => {
		el.hidden = 'hourly' !== type;
	} );

	form.querySelectorAll( '.ybs-bf-multiday-fields' ).forEach( ( el ) => {
		el.hidden = 'multiday' !== type;
	} );
}

function populatePaymentMethods( form ) {
	const select = form.querySelector( '.ybs-bf-payment' );
	const gateways = ( window.ybsFrontendConfig && window.ybsFrontendConfig.gateways ) || {};

	select.innerHTML = '';

	Object.keys( gateways ).forEach( ( id ) => {
		if ( gateways[ id ] && gateways[ id ].enabled ) {
			const option = document.createElement( 'option' );
			option.value = id;
			option.textContent = gateways[ id ].label;
			select.appendChild( option );
		}
	} );
}

function currentYachtId( form ) {
	const select = form.querySelector( '.ybs-bf-yacht' );
	return select ? select.value : form.dataset.yachtId;
}

async function refreshQuote( form ) {
	const yachtId = currentYachtId( form );
	const priceBox = form.querySelector( '.ybs-bf-price' );
	const errorBox = form.querySelector( '.ybs-bf-error' );

	errorBox.hidden = true;

	if ( ! yachtId ) {
		priceBox.hidden = true;
		return;
	}

	const window_ = computeWindow( form );

	if ( ! window_ ) {
		priceBox.hidden = true;
		return;
	}

	const guests = form.querySelector( '.ybs-bf-guests' ).value || 1;
	const type = form.querySelector( '.ybs-bf-type' ).value;
	const config = window.ybsFrontendConfig;

	priceBox.hidden = false;
	priceBox.textContent = config.i18n.loading;

	try {
		const params = new URLSearchParams( {
			booking_type: type,
			start_datetime: window_.start,
			end_datetime: window_.end,
			guest_count: guests,
		} );

		const response = await fetch( `${ config.restRoot }yachts/${ yachtId }/quote?${ params }` );
		const data = await response.json();

		if ( ! response.ok ) {
			priceBox.hidden = true;
			errorBox.hidden = false;
			errorBox.textContent = data.message || config.i18n.notAvailable;
			form.dataset.validQuote = '';
			return;
		}

		priceBox.textContent = `${ config.currency }${ Number( data.pricing.total ).toFixed( 2 ) }`;
		form.dataset.validQuote = '1';
	} catch ( e ) {
		priceBox.hidden = true;
	}
}

async function submitBooking( form ) {
	const errorBox = form.querySelector( '.ybs-bf-error' );
	const config = window.ybsFrontendConfig;
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

	const payload = {
		yacht_id: Number( yachtId ),
		booking_type: form.querySelector( '.ybs-bf-type' ).value,
		start_datetime: window_.start,
		end_datetime: window_.end,
		guest_count: Number( form.querySelector( '.ybs-bf-guests' ).value || 1 ),
		payment_method: form.querySelector( '.ybs-bf-payment' ).value,
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

		if ( data.payment && data.payment.redirect ) {
			window.location.href = data.payment.redirect;
			return;
		}

		form.innerHTML = '<div class="ybs-notice is-success">' +
			( config.i18n.bookingConfirmed || 'Thank you - your booking request has been received.' ) +
			'</div>';
	} catch ( e ) {
		errorBox.hidden = false;
		errorBox.textContent = config.i18n.notAvailable;
		submitButton.disabled = false;
	}
}

export function initBookingForms() {
	document.querySelectorAll( '[data-ybs-booking-form]' ).forEach( ( form ) => {
		toggleFields( form );
		populatePaymentMethods( form );

		form.querySelectorAll( '.ybs-bf-type, .ybs-bf-date, .ybs-bf-start-time, .ybs-bf-duration, .ybs-bf-nights, .ybs-bf-guests, .ybs-bf-yacht' ).forEach( ( field ) => {
			field.addEventListener( 'change', () => {
				toggleFields( form );
				refreshQuote( form );
			} );
		} );

		form.querySelector( '.ybs-bf-submit' ).addEventListener( 'click', () => submitBooking( form ) );
	} );
}
