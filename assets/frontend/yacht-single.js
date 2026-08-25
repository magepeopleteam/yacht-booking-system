/**
 * Yacht Booking System - single yacht template interactions.
 * Vanilla JS, no build step. Progressive enhancement only.
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia( '( prefers-reduced-motion: reduce )' ).matches;

	function initReveal() {
		var items = document.querySelectorAll( '.ybs-ys [data-ybs-reveal]' );
		if ( ! items.length ) {
			return;
		}

		if ( reduceMotion || ! ( 'IntersectionObserver' in window ) ) {
			items.forEach( function ( el ) {
				el.classList.add( 'is-revealed' );
			} );
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'is-revealed' );
						observer.unobserve( entry.target );
					}
				} );
			},
			{ threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
		);

		items.forEach( function ( el, index ) {
			el.style.transitionDelay = Math.min( index % 6, 5 ) * 70 + 'ms';
			observer.observe( el );
		} );
	}

	function animateCounters() {
		var counters = document.querySelectorAll( '.ybs-ys [data-ybs-count]' );
		if ( ! counters.length || reduceMotion || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}
					observer.unobserve( entry.target );

					var el = entry.target;
					var target = parseInt( el.getAttribute( 'data-ybs-count' ), 10 );
					if ( isNaN( target ) || target <= 0 ) {
						return;
					}

					var duration = 1200;
					var start = null;

					function step( ts ) {
						if ( ! start ) {
							start = ts;
						}
						var progress = Math.min( ( ts - start ) / duration, 1 );
						var eased = 1 - Math.pow( 1 - progress, 3 );
						el.textContent = String( Math.round( eased * target ) );
						if ( progress < 1 ) {
							window.requestAnimationFrame( step );
						}
					}

					window.requestAnimationFrame( step );
				} );
			},
			{ threshold: 0.4 }
		);

		counters.forEach( function ( el ) {
			observer.observe( el );
		} );
	}

	function initRateTabs() {
		document.querySelectorAll( '.ybs-ys [data-ybs-rates-tab]' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var wrap = tab.closest( '.ybs-ys-rates-card' );
				if ( ! wrap ) {
					return;
				}

				wrap.querySelectorAll( '[data-ybs-rates-tab]' ).forEach( function ( other ) {
					var active = other === tab;
					other.classList.toggle( 'is-active', active );
					other.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );

				wrap.querySelectorAll( '[data-ybs-rates-panel]' ).forEach( function ( panel ) {
					var active = panel.getAttribute( 'data-ybs-rates-panel' ) === tab.getAttribute( 'data-ybs-rates-tab' );
					panel.classList.toggle( 'is-active', active );
					panel.hidden = ! active;
				} );
			} );
		} );
	}

	function initFaq() {
		document.querySelectorAll( '.ybs-ys .ybs-ys-faq-item' ).forEach( function ( item ) {
			item.classList.add( 'js' );

			item.addEventListener( 'toggle', function () {
				if ( ! item.open || reduceMotion ) {
					return;
				}
				// Close siblings inside the same grid for a tidy accordion feel.
				var parent = item.parentElement;
				parent.querySelectorAll( '.ybs-ys-faq-item[open]' ).forEach( function ( other ) {
					if ( other !== item ) {
						other.open = false;
					}
				} );
			} );
		} );
	}

	function initCarousel() {
		var grid = document.querySelector( '.ybs-ys [data-ybs-carousel]' );
		if ( ! grid ) {
			return;
		}

		var dotsWrap = document.querySelector( '.ybs-ys [data-ybs-carousel-dots]' );
		var counter = document.querySelector( '.ybs-ys [data-ybs-carousel-counter]' );
		var tiles = Array.prototype.slice.call( grid.querySelectorAll( '.ybs-ys-gallery__tile' ) );
		var total = parseInt( ( counter.textContent || '' ).split( '/' ).pop(), 10 ) || tiles.length;

		if ( ! tiles.length || ! dotsWrap || ! counter ) {
			return;
		}

		var dots = tiles.map( function ( _, index ) {
			var dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.setAttribute( 'aria-label', 'Photo ' + ( index + 1 ) );
			dot.addEventListener( 'click', function () {
				tiles[ index ].scrollIntoView( {
					behavior: reduceMotion ? 'auto' : 'smooth',
					block: 'nearest',
					inline: 'center'
				} );
			} );
			dotsWrap.appendChild( dot );
			return dot;
		} );

		function activeIndex() {
			var center = grid.scrollLeft + grid.clientWidth / 2;
			var best = 0;
			var bestDist = Infinity;

			tiles.forEach( function ( tile, index ) {
				var dist = Math.abs( tile.offsetLeft + tile.offsetWidth / 2 - center );
				if ( dist < bestDist ) {
					bestDist = dist;
					best = index;
				}
			} );

			return best;
		}

		function sync() {
			var current = activeIndex();
			dots.forEach( function ( dot, index ) {
				dot.classList.toggle( 'is-active', index === current );
			} );
			counter.textContent = current + 1 + ' / ' + total;
		}

		var ticking = false;
		grid.addEventListener(
			'scroll',
			function () {
				if ( ticking ) {
					return;
				}
				ticking = true;
				window.requestAnimationFrame( function () {
					sync();
					ticking = false;
				} );
			},
			{ passive: true }
		);

		window.addEventListener( 'resize', sync, { passive: true } );

		// Tap a tile once to snap to it; second tap opens the lightbox.
		tiles.forEach( function ( tile, index ) {
			tile.addEventListener( 'click', function ( event ) {
				if ( window.matchMedia( '( max-width: 639.98px )' ).matches && activeIndex() !== index ) {
					event.preventDefault();
					event.stopPropagation();
					tile.scrollIntoView( {
						behavior: reduceMotion ? 'auto' : 'smooth',
						block: 'nearest',
						inline: 'center'
					} );
				}
			}, true );
		} );

		sync();
	}

	function initLightbox() {
		var lightbox = document.querySelector( '.ybs-ys-lightbox' );
		var tiles = document.querySelectorAll( '.ybs-ys [data-ybs-lightbox-open]' );

		if ( ! lightbox || ! tiles.length ) {
			return;
		}

		var images = [];

		tiles.forEach( function ( tile ) {
			var img = tile.querySelector( 'img' );
			if ( img ) {
				var fullSrc = tile.hasAttribute( 'data-full-src' )
					? tile.getAttribute( 'data-full-src' )
					: img.currentSrc || img.src;

				images.push( { src: fullSrc, alt: img.alt } );
			}

			tile.addEventListener( 'click', function () {
				open( parseInt( tile.getAttribute( 'data-ybs-lightbox-open' ), 10 ) || 0 );
			} );
		} );

		if ( ! images.length ) {
			return;
		}

		var stageImg = lightbox.querySelector( 'img' );
		var caption = lightbox.querySelector( 'figcaption' );
		var counter = lightbox.querySelector( '.ybs-ys-lightbox__counter' );
		var current = 0;
		var lastFocus = null;

		function show( index ) {
			current = ( index + images.length ) % images.length;
			var image = images[ current ];

			stageImg.style.animation = 'none';
			stageImg.src = image.src;
			stageImg.alt = image.alt || '';
			caption.textContent = image.alt || '';
			counter.textContent = current + 1 + ' / ' + images.length;

			// Restart the zoom-in animation on every slide change.
			void stageImg.offsetWidth;
			stageImg.style.animation = '';
		}

		function open( index ) {
			lastFocus = document.activeElement;
			lightbox.hidden = false;
			document.documentElement.style.overflow = 'hidden';
			show( index );
			lightbox.querySelector( '[data-ybs-lightbox-close]' ).focus();
		}

		function close() {
			lightbox.hidden = true;
			document.documentElement.style.overflow = '';
			if ( lastFocus ) {
				lastFocus.focus();
			}
		}

		lightbox.querySelector( '[data-ybs-lightbox-close]' ).addEventListener( 'click', close );
		lightbox.querySelector( '[data-ybs-lightbox-prev]' ).addEventListener( 'click', function () {
			show( current - 1 );
		} );
		lightbox.querySelector( '[data-ybs-lightbox-next]' ).addEventListener( 'click', function () {
			show( current + 1 );
		} );

		lightbox.addEventListener( 'click', function ( event ) {
			if ( event.target === lightbox ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( lightbox.hidden ) {
				return;
			}
			if ( 'Escape' === event.key ) {
				close();
			} else if ( 'ArrowLeft' === event.key ) {
				show( current - 1 );
			} else if ( 'ArrowRight' === event.key ) {
				show( current + 1 );
			}
		} );

		// Touch swipe.
		var touchX = null;
		lightbox.addEventListener(
			'touchstart',
			function ( event ) {
				touchX = event.touches[ 0 ].clientX;
			},
			{ passive: true }
		);
		lightbox.addEventListener(
			'touchend',
			function ( event ) {
				if ( null === touchX ) {
					return;
				}
				var delta = event.changedTouches[ 0 ].clientX - touchX;
				if ( Math.abs( delta ) > 50 ) {
					show( delta > 0 ? current - 1 : current + 1 );
				}
				touchX = null;
			},
			{ passive: true }
		);
	}

	function initStickyCard() {
		var card = document.querySelector( '.ybs-ys [data-ybs-sticky]' );
		if ( ! card ) {
			return;
		}

		var desktop = window.matchMedia( '( min-width: 1024px )' );

		function apply() {
			if ( desktop.matches ) {
				card.style.position = 'sticky';
				card.style.top = '96px';
			} else {
				card.style.position = '';
				card.style.top = '';
			}
		}

		apply();
		if ( desktop.addEventListener ) {
			desktop.addEventListener( 'change', apply );
		}

		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					card.classList.toggle(
						'is-stuck',
						desktop.matches && entry.intersectionRatio < 1 && entry.boundingClientRect.top < 0
					);
				} );
			},
			{ threshold: [ 1 ] }
		);

		observer.observe( card );
	}

	function initDock() {
		var dock = document.querySelector( '.ybs-ys [data-ybs-dock]' );
		var anchor = document.getElementById( 'ybs-book' );
		if ( ! dock || ! anchor ) {
			return;
		}

		function toggle() {
			var rect = anchor.getBoundingClientRect();
			dock.hidden = rect.top > window.innerHeight * 0.35;
		}

		window.addEventListener( 'scroll', toggle, { passive: true } );
		toggle();

		dock.querySelectorAll( 'a[href="#ybs-book"]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				anchor.scrollIntoView( { behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' } );
				var firstInput = anchor.querySelector( 'input, select' );
				if ( firstInput ) {
					setTimeout( function () {
						firstInput.focus( { preventScroll: true } );
					}, 500 );
				}
			} );
		} );
	}

	function smoothAnchors() {
		document.querySelectorAll( '.ybs-ys a[href^="#"]:not([href="#"])' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				var target = document.querySelector( link.getAttribute( 'href' ) );
				if ( ! target ) {
					return;
				}
				event.preventDefault();
				target.scrollIntoView( { behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' } );
			} );
		} );
	}

	function boot() {
		initReveal();
		animateCounters();
		initFaq();
		initRateTabs();
		initCarousel();
		initLightbox();
		initStickyCard();
		initDock();
		smoothAnchors();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
