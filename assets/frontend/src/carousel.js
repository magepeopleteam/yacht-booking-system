/**
 * A minimal, dependency-free carousel for the single-yacht "Related Yachts"
 * row: the track scroll-snaps natively (swipeable on touch with no JS at
 * all), and the prev/next buttons just nudge that same native scroll by one
 * card's width rather than reimplementing sliding/paging by hand.
 */
export function initCarousels() {
	document.querySelectorAll( '[data-ybs-carousel]' ).forEach( ( carousel ) => {
		const track = carousel.querySelector( '.ybs-yp-carousel__track' );
		const prevBtn = carousel.querySelector( '.ybs-yp-carousel__nav.is-prev' );
		const nextBtn = carousel.querySelector( '.ybs-yp-carousel__nav.is-next' );

		if ( ! track ) {
			return;
		}

		const scrollByCard = ( direction ) => {
			const card = track.querySelector( '.ybs-yp-carousel__item' );
			const step = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * .8;

			track.scrollBy( { left: direction * step, behavior: 'smooth' } );
		};

		if ( prevBtn ) prevBtn.addEventListener( 'click', () => scrollByCard( -1 ) );
		if ( nextBtn ) nextBtn.addEventListener( 'click', () => scrollByCard( 1 ) );

		const updateNav = () => {
			if ( ! prevBtn || ! nextBtn ) {
				return;
			}

			const maxScroll = track.scrollWidth - track.clientWidth;

			prevBtn.disabled = track.scrollLeft <= 4;
			nextBtn.disabled = track.scrollLeft >= maxScroll - 4;
		};

		track.addEventListener( 'scroll', updateNav, { passive: true } );
		window.addEventListener( 'resize', updateNav );
		updateNav();
	} );
}
