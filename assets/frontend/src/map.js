export function initMaps() {
	if ( ! window.L ) {
		return;
	}

	document.querySelectorAll( '.ybs-yacht-map' ).forEach( ( el ) => {
		const lat = parseFloat( el.dataset.lat );
		const lng = parseFloat( el.dataset.lng );

		if ( ! lat || ! lng ) {
			return;
		}

		const map = window.L.map( el ).setView( [ lat, lng ], 13 );

		window.L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
		} ).addTo( map );

		window.L.marker( [ lat, lng ] ).addTo( map ).bindPopup( el.dataset.name || '' );
	} );
}

export function initNewsletter() {
	document.querySelectorAll( '[data-ybs-newsletter]' ).forEach( ( root ) => {
		const form = root.querySelector( '.ybs-newsletter__form' );
		const message = root.querySelector( '.ybs-newsletter__message' );

		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const config = window.ybsFrontendConfig;
			const email = form.querySelector( '.ybs-newsletter__input' ).value;

			try {
				const response = await fetch( `${ config.restRoot }newsletter/subscribe`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { email } ),
				} );

				const data = await response.json();
				message.hidden = false;
				message.textContent = response.ok
					? config.i18n.subscribeThanks
					: data.message || config.i18n.subscribeError;

				if ( response.ok ) {
					form.reset();
				}
			} catch ( e ) {
				message.hidden = false;
				message.textContent = config.i18n.subscribeError;
			}
		} );
	} );
}
