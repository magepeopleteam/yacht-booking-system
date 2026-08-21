function pad( n ) {
	return String( n ).padStart( 2, '0' );
}

async function renderMonth( root, year, month ) {
	const config = window.ybsFrontendConfig;
	const yachtId = root.dataset.yachtId;
	const grid = root.querySelector( '.ybs-availability-calendar__grid' );
	const label = root.querySelector( '.ybs-availability-calendar__label' );
	const monthStr = `${ year }-${ pad( month + 1 ) }`;

	label.textContent = new Date( year, month, 1 ).toLocaleString( undefined, { month: 'long', year: 'numeric' } );
	grid.innerHTML = `<div class="ybs-loading">${ config.i18n.loading }</div>`;

	try {
		const response = await fetch( `${ config.restRoot }yachts/${ yachtId }/calendar?month=${ monthStr }` );
		const data = await response.json();

		const first = new Date( year, month, 1 );
		const daysInMonth = new Date( year, month + 1, 0 ).getDate();
		let html = '';

		for ( let i = 0; i < first.getDay(); i++ ) {
			html += '<div></div>';
		}

		for ( let day = 1; day <= daysInMonth; day++ ) {
			const dateStr = `${ monthStr }-${ pad( day ) }`;
			const available = data.days ? data.days[ dateStr ] : true;
			html += `<div class="ybs-availability-day ${ available ? 'is-available' : 'is-unavailable' }">${ day }</div>`;
		}

		grid.innerHTML = html;
		root.dataset.year = year;
		root.dataset.month = month;
	} catch ( e ) {
		grid.innerHTML = '';
	}
}

export function initAvailabilityCalendars() {
	document.querySelectorAll( '.ybs-availability-calendar' ).forEach( ( root ) => {
		const today = new Date();
		root.dataset.year = today.getFullYear();
		root.dataset.month = today.getMonth();

		root.querySelector( '.ybs-availability-calendar__prev' ).addEventListener( 'click', () => {
			let year = Number( root.dataset.year );
			let month = Number( root.dataset.month ) - 1;
			if ( month < 0 ) { month = 11; year -= 1; }
			renderMonth( root, year, month );
		} );

		root.querySelector( '.ybs-availability-calendar__next' ).addEventListener( 'click', () => {
			let year = Number( root.dataset.year );
			let month = Number( root.dataset.month ) + 1;
			if ( month > 11 ) { month = 0; year += 1; }
			renderMonth( root, year, month );
		} );

		renderMonth( root, today.getFullYear(), today.getMonth() );
	} );
}
