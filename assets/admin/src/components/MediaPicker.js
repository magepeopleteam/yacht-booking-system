import { __ } from '@wordpress/i18n';

/**
 * Opens the native WP media library modal for a single image - requires the
 * host page to have called `wp_enqueue_media()` (see Admin\Menu::enqueue).
 */
export default function MediaPicker( { id, url, onChange } ) {
	const open = () => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		const frame = window.wp.media( {
			title: __( 'Select Featured Image', 'yacht-booking-system' ),
			button: { text: __( 'Use this image', 'yacht-booking-system' ) },
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			onChange( attachment.id, attachment.sizes?.medium?.url || attachment.url );
		} );

		frame.open();
	};

	return (
		<div className="ybs-media-picker">
			{ url ? (
				<div className="ybs-media-picker__preview">
					<img src={ url } alt="" />
				</div>
			) : (
				<div className="ybs-media-picker__empty">
					<span className="dashicons dashicons-format-image" />
				</div>
			) }

			<div className="ybs-media-picker__actions">
				<button type="button" className="ybs-btn" onClick={ open }>
					{ id ? __( 'Replace Image', 'yacht-booking-system' ) : __( 'Select Image', 'yacht-booking-system' ) }
				</button>
				{ id ? (
					<button type="button" className="ybs-btn is-danger" onClick={ () => onChange( 0, '' ) }>
						{ __( 'Remove', 'yacht-booking-system' ) }
					</button>
				) : null }
			</div>
		</div>
	);
}
