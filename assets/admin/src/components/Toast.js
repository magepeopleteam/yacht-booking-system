import { useEffect, useState } from '@wordpress/element';

let nextId = 1;
const listeners = new Set();

/**
 * Fire-and-forget toast, callable from anywhere (no provider needed) -
 * mirrors the corner-anchored, auto-dismissing toasts in the sibling
 * eventpress wizard's `showToast()`.
 *
 * @param {string} message
 * @param {'info'|'success'|'error'} type
 */
export function toast( message, type = 'info' ) {
	const item = { id: nextId++, message, type };
	listeners.forEach( ( listener ) => listener( item ) );
}

export function ToastHost() {
	const [ items, setItems ] = useState( [] );

	useEffect( () => {
		const onToast = ( item ) => {
			setItems( ( prev ) => [ ...prev, item ] );
			setTimeout( () => {
				setItems( ( prev ) => prev.filter( ( i ) => i.id !== item.id ) );
			}, 'error' === item.type ? 4200 : 2600 );
		};

		listeners.add( onToast );
		return () => listeners.delete( onToast );
	}, [] );

	if ( ! items.length ) {
		return null;
	}

	return (
		<div className="ybs-toasts">
			{ items.map( ( item ) => (
				<div key={ item.id } className={ 'ybs-toast is-' + item.type }>
					{ item.message }
				</div>
			) ) }
		</div>
	);
}
