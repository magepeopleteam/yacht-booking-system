function escapeHtml( str ) {
	return ( str || '' ).replace( /[&<>"']/g, ( c ) => ( {
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
	} )[ c ] );
}

function renderCard( yacht, currency ) {
	const config = window.ybsFrontendConfig;

	const media = yacht.thumbnail
		? `<img src="${ yacht.thumbnail }" alt="${ escapeHtml( yacht.title ) }" loading="lazy" />`
		: '<div class="ybs-yl-card__media-placeholder"><span class="dashicons dashicons-palmtree"></span></div>';

	const photoBadge = yacht.photo_count > 0
		? `<span class="ybs-yl-card__photos">${ yacht.photo_count } ${ config.i18n.photos }</span>`
		: '';

	const classTag = yacht.classes && yacht.classes.length
		? `<span class="ybs-yl-card__class">${ escapeHtml( yacht.classes[ 0 ] ) }</span>`
		: '';

	const meta = [];
	if ( yacht.capacity ) {
		meta.push( `<div class="ybs-yl-card__meta"><span class="dashicons dashicons-groups"></span>${ yacht.capacity } ${ config.i18n.guestsLabel }</div>` );
	}
	if ( yacht.length ) {
		meta.push( `<div class="ybs-yl-card__meta"><span class="dashicons dashicons-leftright"></span>${ yacht.length } m</div>` );
	}

	const priceValue = yacht.from_price > 0
		? `${ currency }${ Number( yacht.from_price ).toLocaleString() }<small>/${ config.i18n.perHour }</small>`
		: config.i18n.contactForPricing;

	return `
		<a class="ybs-yl-card" href="${ yacht.permalink }">
			<div class="ybs-yl-card__media">
				${ media }
				${ photoBadge }
				${ classTag }
			</div>
			<div class="ybs-yl-card__body">
				<h3 class="ybs-yl-card__title">${ escapeHtml( yacht.title ) }</h3>
				${ meta.join( '' ) }
				<div class="ybs-yl-card__footer">
					<div class="ybs-yl-card__price">
						<div class="ybs-yl-card__price-label">${ config.i18n.from }</div>
						<div class="ybs-yl-card__price-value">${ priceValue }</div>
					</div>
					<span class="ybs-yl-card__book">${ config.i18n.viewYacht } <span>&rarr;</span></span>
				</div>
			</div>
		</a>
	`;
}

async function runList( root, append ) {
	const config = window.ybsFrontendConfig;
	const grid = root.querySelector( '.ybs-yl-grid' );
	const summary = root.querySelector( '.ybs-yl-summary' );
	const loadMore = root.querySelector( '.ybs-yl-loadmore' );

	if ( ! append ) {
		root.dataset.page = '1';
		grid.innerHTML = `<div class="ybs-loading">${ config.i18n.loading }</div>`;
	}

	const perPage = parseInt( root.dataset.perPage, 10 ) || 9;
	const params = new URLSearchParams();
	params.set( 'page', root.dataset.page || '1' );
	params.set( 'per_page', perPage );

	const guests = root.querySelector( '.ybs-yl-guests' );
	if ( guests && guests.dataset.value ) {
		params.set( 'guests', guests.dataset.value );
	}

	const priceTier = root.querySelector( '.ybs-yl-price' );
	if ( priceTier && priceTier.value ) {
		const [ min, max ] = priceTier.value.split( '-' );
		if ( min && '0' !== min ) {
			params.set( 'price_min', min );
		}
		if ( max ) {
			params.set( 'price_max', max );
		}
	}

	const activeTab = root.querySelector( '.ybs-yl-tab.is-active' );
	if ( activeTab && activeTab.dataset.class ) {
		params.set( 'class', activeTab.dataset.class );
	}

	try {
		const response = await fetch( `${ config.restRoot }yachts?${ params }` );
		const data = await response.json();
		const cards = data.items.map( ( yacht ) => renderCard( yacht, config.currency ) ).join( '' );

		if ( append ) {
			grid.insertAdjacentHTML( 'beforeend', cards );
		} else {
			grid.innerHTML = data.items.length ? cards : `<div class="ybs-empty-state">${ config.i18n.noResults }</div>`;
			summary.textContent = data.total
				? ( 1 === data.total ? config.i18n.yachtAvailable : config.i18n.yachtsAvailable ).replace( '%d', data.total )
				: '';
		}

		const page = parseInt( root.dataset.page, 10 ) || 1;
		loadMore.hidden = page >= data.pages;
	} catch ( e ) {
		grid.innerHTML = `<div class="ybs-notice is-error">${ config.i18n.searchFailed }</div>`;
	}
}

export function initYachtList() {
	document.querySelectorAll( '[data-ybs-yl]' ).forEach( ( root ) => {
		root.dataset.page = '1';

		const search = () => {
			root.dataset.page = '1';
			runList( root, false );
		};

		const searchBtn = root.querySelector( '.ybs-yl-search-btn' );
		if ( searchBtn ) {
			searchBtn.addEventListener( 'click', search );
		}

		root.querySelectorAll( '.ybs-yl-tab' ).forEach( ( tab ) => {
			tab.addEventListener( 'click', () => {
				root.querySelectorAll( '.ybs-yl-tab' ).forEach( ( t ) => {
					t.classList.remove( 'is-active' );
					t.setAttribute( 'aria-selected', 'false' );
				} );
				tab.classList.add( 'is-active' );
				tab.setAttribute( 'aria-selected', 'true' );
				search();
			} );
		} );

		const guests = root.querySelector( '.ybs-yl-guests' );
		root.querySelectorAll( '.ybs-yl-bar__step' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const step = parseInt( btn.dataset.step, 10 );
				const next = Math.max( 1, ( parseInt( guests.dataset.value, 10 ) || 1 ) + step );
				guests.dataset.value = next;
				guests.textContent = next;
			} );
		} );

		const grid = root.querySelector( '.ybs-yl-grid' );
		root.querySelectorAll( '.ybs-yl-view-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				root.querySelectorAll( '.ybs-yl-view-btn' ).forEach( ( b ) => {
					b.classList.remove( 'is-active' );
					b.setAttribute( 'aria-pressed', 'false' );
				} );
				btn.classList.add( 'is-active' );
				btn.setAttribute( 'aria-pressed', 'true' );
				grid.classList.toggle( 'is-list-view', 'list' === btn.dataset.view );
			} );
		} );

		const loadMore = root.querySelector( '.ybs-yl-loadmore' );
		if ( loadMore ) {
			loadMore.addEventListener( 'click', () => {
				root.dataset.page = String( ( parseInt( root.dataset.page, 10 ) || 1 ) + 1 );
				runList( root, true );
			} );
		}

		runList( root, false );
	} );
}
