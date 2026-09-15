/**
 * Progressively enhances a native <select> into a modern floating listbox.
 *
 * Browsers don't let CSS touch a native <select>'s own dropdown popup (no
 * cross-browser way to style option padding, hover, radius, shadow…), so a
 * "nice modern" look for the options themselves means building the listbox
 * ourselves. The original <select> stays in the DOM - just visually hidden,
 * never removed - so every existing script that reads its `.value` or
 * listens for its `change` event (search.js) keeps working untouched;
 * clicking a custom option sets the real select's value and dispatches a
 * real `change` event rather than re-implementing what happens next.
 */
function enhanceSelect( select ) {
	if ( select.dataset.ybsEnhanced ) {
		return;
	}

	select.dataset.ybsEnhanced = '1';

	const wrap = document.createElement( 'div' );
	wrap.className = 'ybs-select';

	const trigger = document.createElement( 'button' );
	trigger.type = 'button';
	trigger.className = 'ybs-select__trigger';
	trigger.setAttribute( 'aria-haspopup', 'listbox' );
	trigger.setAttribute( 'aria-expanded', 'false' );

	const label = document.createElement( 'span' );
	label.className = 'ybs-select__label';
	const chevron = document.createElement( 'span' );
	chevron.className = 'ybs-select__chevron';
	trigger.appendChild( label );
	trigger.appendChild( chevron );

	const menu = document.createElement( 'div' );
	menu.className = 'ybs-select__menu';
	menu.setAttribute( 'role', 'listbox' );
	menu.hidden = true;

	const items = Array.from( select.options ).map( ( option ) => {
		const item = document.createElement( 'button' );
		item.type = 'button';
		item.className = 'ybs-select__option';
		item.textContent = option.textContent;
		item.dataset.value = option.value;
		item.setAttribute( 'role', 'option' );
		menu.appendChild( item );
		return item;
	} );

	const syncTrigger = () => {
		const selected = select.options[ select.selectedIndex ];
		label.textContent = selected ? selected.textContent : '';

		items.forEach( ( item ) => {
			const isActive = item.dataset.value === select.value;
			item.classList.toggle( 'is-active', isActive );
			item.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		} );
	};

	const close = () => {
		menu.hidden = true;
		wrap.classList.remove( 'is-open' );
		trigger.setAttribute( 'aria-expanded', 'false' );
	};

	const open = () => {
		menu.hidden = false;
		wrap.classList.add( 'is-open' );
		trigger.setAttribute( 'aria-expanded', 'true' );
		const active = menu.querySelector( '.ybs-select__option.is-active' ) || items[ 0 ];
		if ( active ) {
			active.classList.add( 'is-focused' );
			active.scrollIntoView( { block: 'nearest' } );
		}
	};

	const choose = ( item ) => {
		select.value = item.dataset.value;
		select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		syncTrigger();
		close();
		trigger.focus();
	};

	trigger.addEventListener( 'click', () => ( menu.hidden ? open() : close() ) );

	items.forEach( ( item ) => {
		item.addEventListener( 'click', () => choose( item ) );
		item.addEventListener( 'mouseenter', () => {
			menu.querySelectorAll( '.is-focused' ).forEach( ( el ) => el.classList.remove( 'is-focused' ) );
			item.classList.add( 'is-focused' );
		} );
	} );

	menu.addEventListener( 'keydown', ( e ) => {
		const focused = menu.querySelector( '.is-focused' );
		const index = focused ? items.indexOf( focused ) : -1;

		if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
			e.preventDefault();
			const next = items[ ( index + ( 'ArrowDown' === e.key ? 1 : -1 ) + items.length ) % items.length ];
			if ( focused ) focused.classList.remove( 'is-focused' );
			next.classList.add( 'is-focused' );
			next.scrollIntoView( { block: 'nearest' } );
		} else if ( 'Enter' === e.key || ' ' === e.key ) {
			e.preventDefault();
			if ( focused ) choose( focused );
		} else if ( 'Escape' === e.key ) {
			close();
			trigger.focus();
		}
	} );

	document.addEventListener( 'click', ( e ) => {
		if ( ! wrap.contains( e.target ) ) {
			close();
		}
	} );

	select.parentNode.insertBefore( wrap, select );
	wrap.appendChild( select );
	wrap.appendChild( trigger );
	wrap.appendChild( menu );

	syncTrigger();
}

export function initCustomSelects() {
	document.querySelectorAll( '.ybs-search-bar__control' ).forEach( ( el ) => {
		if ( 'SELECT' === el.tagName ) {
			enhanceSelect( el );
		}
	} );
}
