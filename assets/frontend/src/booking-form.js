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

	set( 'ybs_booking_type', form.querySelector( '.ybs-bf-type' ).value );
	set( 'ybs_booking_mode', currentMode( form ) );
	set( 'ybs_guest_count', form.querySelector( '.ybs-bf-guests' ).value || 1 );
	set( 'ybs_start_datetime', window_ ? window_.start : '' );
	set( 'ybs_end_datetime', window_ ? window_.end : '' );
}

function setSubmitEnabled( form, enabled ) {
	const button = form.querySelector( '.ybs-bf-submit' );

	if ( button ) {
		button.disabled = ! enabled;
	}
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
	const config = window.ybsFrontendConfig;

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

		let label = `${ config.currency }${ Number( data.pricing.total ).toFixed( 2 ) }`;
		const remaining = data.availability && data.availability.remaining_capacity;

		if ( 'shared' === currentMode( form ) && null !== remaining && undefined !== remaining ) {
			label += ` · ${ Number( remaining ) } ${ config.i18n.seatsLeft }`;

			const guestsInput = form.querySelector( '.ybs-bf-guests' );

			if ( guestsInput && Number( remaining ) > 0 ) {
				guestsInput.max = Number( remaining );
				clampGuests( form );
			}
		}

		priceBox.textContent = label;
		form.dataset.validQuote = '1';
		setSubmitEnabled( form, true );
		updateHiddenFields( form );
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

	// The live quote is what validates the slot - never submit against a
	// failed/unknown availability check.
	if ( '1' !== form.dataset.validQuote ) {
		errorBox.hidden = false;
		errorBox.textContent = config.i18n.notAvailable;
		return;
	}

	const payload = {
		yacht_id: Number( yachtId ),
		booking_type: form.querySelector( '.ybs-bf-type' ).value,
		booking_mode: currentMode( form ),
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
				refreshQuote( form );
			} );
		} );

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
						( window.ybsFrontendConfig && window.ybsFrontendConfig.i18n.notAvailable ) || 'This slot is not available.';
					return;
				}

				updateHiddenFields( form );
			} );
		} else {
			form.querySelector( '.ybs-bf-submit' ).addEventListener( 'click', () => submitBooking( form ) );
		}

		// Show a price immediately with the form's own defaults, rather than
		// waiting for the visitor to touch a field first.
		if ( currentYachtId( form ) ) {
			refreshQuote( form );
		}
	} );
}
