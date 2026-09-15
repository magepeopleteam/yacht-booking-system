function escapeHtml( str ) {
	return ( str || '' ).replace( /[&<>"']/g, ( c ) => ( {
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
	} )[ c ] );
}

// Matches the toggle's data-charter-type values to the i18n string naming
// the rate that charter type's price actually is (see
// YachtsController::CHARTER_TYPE_PRICE_KEYS for the same mapping server-side).
const CHARTER_PERIOD_KEYS = {
	weekly: 'perWeek',
	day: 'perDay',
	hourly: 'perHour',
};

function renderCard( yacht, currency, charterType ) {
	const config = window.mageyaboFrontendConfig;

	const media = yacht.thumbnail
		? `<img src="${ yacht.thumbnail }" alt="${ escapeHtml( yacht.title ) }" loading="lazy" />`
		: '<div class="ybs-yacht-card__media-placeholder"><span class="dashicons dashicons-palmtree"></span></div>';

	const photoBadge = yacht.photo_count > 0
		? `<span class="ybs-yacht-card__photos"><span class="dashicons dashicons-camera"></span>${ yacht.photo_count }</span>`
		: '';

	const classTag = yacht.classes && yacht.classes.length
		? `<span class="ybs-yacht-card__class">${ escapeHtml( yacht.classes[ 0 ] ) }</span>`
		: '';

	const meta = [];
	if ( yacht.capacity ) {
		meta.push( `<span class="ybs-yacht-card__meta-item"><span class="dashicons dashicons-groups"></span>${ yacht.capacity } ${ config.i18n.guestsLabel }</span>` );
	}
	if ( yacht.length ) {
		meta.push( `<span class="ybs-yacht-card__meta-item"><span class="dashicons dashicons-leftright"></span>${ yacht.length } m</span>` );
	}

	const locationBits = [];
	if ( yacht.location && yacht.location.name ) locationBits.push( escapeHtml( yacht.location.name ) );
	if ( yacht.distance_km ) locationBits.push( `${ yacht.distance_km } km` );

	const periodKey = CHARTER_PERIOD_KEYS[ charterType ];
	const period = periodKey ? config.i18n[ periodKey ] : '';

	const priceValue = yacht.from_price > 0
		? `${ currency }${ Number( yacht.from_price ).toLocaleString() }<small>${ period ? '/' + period : '+' }</small>`
		: config.i18n.contactForPricing;

	return `
		<a class="ybs-yacht-card" href="${ yacht.permalink }">
			<div class="ybs-yacht-card__media">
				${ media }
				${ photoBadge }
				${ classTag }
			</div>
			<div class="ybs-yacht-card__body">
				<h3 class="ybs-yacht-card__title">${ escapeHtml( yacht.title ) }</h3>
				${ locationBits.length ? `<p class="ybs-yacht-card__location"><span class="dashicons dashicons-location"></span>${ locationBits.join( ' · ' ) }</p>` : '' }
				${ meta.length ? `<div class="ybs-yacht-card__meta">${ meta.join( '' ) }</div>` : '' }
				<div class="ybs-yacht-card__footer">
					<div class="ybs-yacht-card__price">
						<span class="ybs-yacht-card__price-label">${ config.i18n.from }</span>
						<span class="ybs-yacht-card__price-value">${ priceValue }</span>
					</div>
					<span class="ybs-yacht-card__book">${ config.i18n.viewYacht } <span>&rarr;</span></span>
				</div>
			</div>
		</a>
	`;
}

async function runSearch( root ) {
	const config = window.mageyaboFrontendConfig;
	const results = root.querySelector( '.ybs-search-results' );
	results.innerHTML = `<div class="ybs-loading">${ config.i18n.loading }</div>`;

	const params = new URLSearchParams();

	const where = root.querySelector( '.ybs-search-where' );
	if ( where && where.value ) params.set( 'location', where.value );

	const guests = root.querySelector( '.ybs-search-guests' );
	if ( guests && guests.dataset.value ) params.set( 'guests', guests.dataset.value );

	const priceTier = root.querySelector( '.ybs-search-price' );
	if ( priceTier && priceTier.value ) {
		const [ min, max ] = priceTier.value.split( '-' );
		if ( min && '0' !== min ) params.set( 'price_min', min );
		if ( max ) params.set( 'price_max', max );
	}

	const activeCharter = root.querySelector( '.ybs-search-toggle__btn.is-active' );
	const charterType = activeCharter ? activeCharter.dataset.charterType : '';
	if ( charterType ) {
		params.set( 'charter_type', charterType );
	}

	try {
		const response = await fetch( `${ config.restRoot }yachts?${ params }` );
		const data = await response.json();

		results.innerHTML = data.items.length
			? data.items.map( ( yacht ) => renderCard( yacht, config.currency, charterType ) ).join( '' )
			: `<div class="ybs-empty-state">${ config.i18n.noResults }</div>`;
	} catch ( e ) {
		results.innerHTML = `<div class="ybs-notice is-error">${ config.i18n.searchFailed }</div>`;
	}
}

export function initSearch() {
	document.querySelectorAll( '[data-ybs-search]' ).forEach( ( root ) => {
		const search = () => runSearch( root );

		// Each of these can be hidden per-shortcode (`[mageyabo_yacht_search
		// where="no" tabs="no" ...]`), so nothing here can assume a given
		// field or the toggle bar actually exists in this instance.
		const searchBtn = root.querySelector( '.ybs-search-btn' );
		if ( searchBtn ) searchBtn.addEventListener( 'click', search );

		const whereField = root.querySelector( '.ybs-search-where' );
		if ( whereField ) whereField.addEventListener( 'change', search );

		const priceField = root.querySelector( '.ybs-search-price' );
		if ( priceField ) priceField.addEventListener( 'change', search );

		root.querySelectorAll( '.ybs-search-toggle__btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				root.querySelectorAll( '.ybs-search-toggle__btn' ).forEach( ( b ) => {
					b.classList.remove( 'is-active' );
					b.setAttribute( 'aria-selected', 'false' );
				} );
				btn.classList.add( 'is-active' );
				btn.setAttribute( 'aria-selected', 'true' );
				search();
			} );
		} );

		const guests = root.querySelector( '.ybs-search-guests' );
		root.querySelectorAll( '.ybs-search-bar__step' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const step = parseInt( btn.dataset.step, 10 );
				const next = Math.max( 1, ( parseInt( guests.dataset.value, 10 ) || 1 ) + step );
				guests.dataset.value = next;
				guests.textContent = next;
				search();
			} );
		} );

		search();
	} );
}
