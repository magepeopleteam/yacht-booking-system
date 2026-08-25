export function initGalleries() {
	document.querySelectorAll( '[data-ybs-gallery]' ).forEach( ( gallery ) => {
		const mainImg = gallery.querySelector( '.ybs-yp-gallery__main-img' );
		const countText = gallery.querySelector( '.ybs-yp-gallery__count-text' );
		const thumbs = gallery.querySelectorAll( '.ybs-yp-gallery__thumb' );

		if ( ! mainImg || ! thumbs.length ) {
			return;
		}

		thumbs.forEach( ( thumb, index ) => {
			thumb.addEventListener( 'click', () => {
				mainImg.src = thumb.dataset.full;

				thumbs.forEach( ( t ) => t.classList.remove( 'is-active' ) );
				thumb.classList.add( 'is-active' );

				if ( countText ) {
					countText.textContent = `${ index + 1 } / ${ thumbs.length }`;
				}
			} );
		} );
	} );
}
