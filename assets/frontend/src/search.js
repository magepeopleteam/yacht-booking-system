function renderCard( yacht, currency ) {
	const distance = yacht.distance_km ? `<span class="ybs-yacht-card__distance">${ yacht.distance_km } km</span>` : '';

	return `
		<a class="ybs-yacht-card" href="/?p=${ yacht.id }">
			${ yacht.thumbnail ? `<img src="${ yacht.thumbnail }" alt="${ yacht.title }" />` : '' }
			<div class="ybs-yacht-card__body">
				<h3>${ yacht.title }</h3>
				<p>${ yacht.location.name || '' } ${ distance }</p>
				<p class="ybs-yacht-card__price">${ currency }${ Number( yacht.from_price ).toFixed( 2 ) }+</p>
			</div>
		</a>
	`;
}

async function runSearch( root ) {
	const config = window.ybsFrontendConfig;
	const results = root.querySelector( '.ybs-search-results' );
	results.innerHTML = `<div class="ybs-loading">${ config.i18n.loading }</div>`;

	const params = new URLSearchParams();
	const guests = root.querySelector( '.ybs-search-guests' ).value;
	const klass = root.querySelector( '.ybs-search-class' ).value;
	const occasion = root.querySelector( '.ybs-search-occasion' ).value;
	const priceMax = root.querySelector( '.ybs-search-price-max' ).value;

	if ( guests ) params.set( 'guests', guests );
	if ( klass ) params.set( 'class', klass );
	if ( occasion ) params.set( 'occasion', occasion );
	if ( priceMax ) params.set( 'price_max', priceMax );

	if ( root.dataset.lat && root.dataset.lng ) {
		params.set( 'lat', root.dataset.lat );
		params.set( 'lng', root.dataset.lng );
		params.set( 'radius_km', '100' );
	}

	try {
		const response = await fetch( `${ config.restRoot }yachts?${ params }` );
		const data = await response.json();

		results.innerHTML = data.items.length
			? data.items.map( ( yacht ) => renderCard( yacht, config.currency ) ).join( '' )
			: `<div class="ybs-empty-state">${ config.i18n.noResults }</div>`;
	} catch ( e ) {
		results.innerHTML = `<div class="ybs-notice is-error">${ config.i18n.searchFailed }</div>`;
	}
}

export function initSearch() {
	document.querySelectorAll( '[data-ybs-search]' ).forEach( ( root ) => {
		root.querySelector( '.ybs-search-submit' ).addEventListener( 'click', () => runSearch( root ) );

		const nearMeButton = root.querySelector( '.ybs-search-nearme' );

		if ( nearMeButton && navigator.geolocation ) {
			nearMeButton.addEventListener( 'click', () => {
				navigator.geolocation.getCurrentPosition( ( position ) => {
					root.dataset.lat = position.coords.latitude;
					root.dataset.lng = position.coords.longitude;
					runSearch( root );
				} );
			} );
		}

		runSearch( root );
	} );
}
