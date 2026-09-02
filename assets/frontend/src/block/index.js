import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType('magepeople-yacht-booking-system/booking-form', {
	title: __('Yacht Booking Form', 'magepeople-yacht-booking-system'),
	description: __('A yacht charter booking form.', 'magepeople-yacht-booking-system'),
	icon: 'palmtree',
	category: 'widgets',
	attributes: {
		yachtId: { type: 'number', default: 0 },
	},
	edit: ({ attributes, setAttributes }) => {
		const blockProps = useBlockProps();

		return (
			<div {...blockProps}>
				<InspectorControls>
					<PanelBody title={__('Booking Form Settings', 'magepeople-yacht-booking-system')}>
						<TextControl
							label={__('Yacht ID (leave 0 to let visitors choose)', 'magepeople-yacht-booking-system')}
							type="number"
							value={attributes.yachtId}
							onChange={(value) => setAttributes({ yachtId: Number(value) || 0 })}
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block="magepeople-yacht-booking-system/booking-form" attributes={attributes} />
			</div>
		);
	},
	save: () => null,
});
