import { __ } from '@wordpress/i18n';

/**
 * Multiple-image picker backed by the native WP media library modal.
 * `items` is an array of `{ id, url }`; requires `wp_enqueue_media()`.
 */
export default function GalleryPicker( { items, onChange } ) {
	const open = () => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		const frame = window.wp.media( {
			title: __( 'Select Gallery Images', 'magepeople-yacht-booking-system' ),
			button: { text: __( 'Add to Gallery', 'magepeople-yacht-booking-system' ) },
			multiple: true,
			library: { type: 'image' },
		} );

		frame.on( 'select', () => {
			const selected = frame.state().get( 'selection' ).toJSON().map( ( attachment ) => ( {
				id: attachment.id,
				url: attachment.sizes?.thumbnail?.url || attachment.url,
			} ) );

			const existingIds = items.map( ( item ) => item.id );
			onChange( [ ...items, ...selected.filter( ( item ) => ! existingIds.includes( item.id ) ) ] );
		} );

		frame.open();
	};

	const remove = ( id ) => onChange( items.filter( ( item ) => item.id !== id ) );

	return (
		<div className="ybs-gallery-picker">
			<div className="ybs-gallery-picker__grid">
				{ items.map( ( item ) => (
					<div key={ item.id } className="ybs-gallery-picker__item">
						<img src={ item.url } alt="" />
						<button type="button" onClick={ () => remove( item.id ) } aria-label={ __( 'Remove image', 'magepeople-yacht-booking-system' ) }>
							<span className="dashicons dashicons-no-alt" />
						</button>
					</div>
				) ) }
			</div>
			<button type="button" className="ybs-btn" onClick={ open }>
				{ __( '+ Add Images', 'magepeople-yacht-booking-system' ) }
			</button>
		</div>
	);
}
