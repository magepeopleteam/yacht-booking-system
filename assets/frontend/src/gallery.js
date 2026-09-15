export function initGalleries() {
	document.querySelectorAll( '[data-ybs-gallery]' ).forEach( ( gallery ) => {
		let photos = [];

		try {
			photos = JSON.parse( gallery.dataset.photos || '[]' );
		} catch ( e ) {
			photos = [];
		}

		const lightbox = gallery.querySelector( '.ybs-yp-lightbox' );

		if ( ! photos.length || ! lightbox ) {
			return;
		}

		const lightboxImg = lightbox.querySelector( '.ybs-yp-lightbox__img' );
		const countEl = lightbox.querySelector( '.ybs-yp-lightbox__count' );
		let current = 0;

		const show = ( index ) => {
			current = ( index + photos.length ) % photos.length;
			lightboxImg.src = photos[ current ];

			if ( countEl ) {
				countEl.textContent = `${ current + 1 } / ${ photos.length }`;
			}
		};

		const open = ( index ) => {
			show( index );
			lightbox.hidden = false;
			document.body.classList.add( 'ybs-yp-lightbox-open' );
		};

		const close = () => {
			lightbox.hidden = true;
			document.body.classList.remove( 'ybs-yp-lightbox-open' );
		};

		gallery.querySelectorAll( '[data-ybs-open]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => open( parseInt( btn.dataset.index, 10 ) || 0 ) );
		} );

		const closeBtn = lightbox.querySelector( '.ybs-yp-lightbox__close' );
		const prevBtn = lightbox.querySelector( '.ybs-yp-lightbox__prev' );
		const nextBtn = lightbox.querySelector( '.ybs-yp-lightbox__next' );

		if ( closeBtn ) closeBtn.addEventListener( 'click', close );
		if ( prevBtn ) prevBtn.addEventListener( 'click', () => show( current - 1 ) );
		if ( nextBtn ) nextBtn.addEventListener( 'click', () => show( current + 1 ) );

		// Click on the dark backdrop (not the image or the nav buttons) closes it too.
		lightbox.addEventListener( 'click', ( e ) => {
			if ( e.target === lightbox ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', ( e ) => {
			if ( lightbox.hidden ) {
				return;
			}

			if ( 'Escape' === e.key ) close();
			if ( 'ArrowLeft' === e.key ) show( current - 1 );
			if ( 'ArrowRight' === e.key ) show( current + 1 );
		} );
	} );
}
