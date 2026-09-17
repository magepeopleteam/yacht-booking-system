import { __ } from '@wordpress/i18n';

/**
 * Screens the Pro add-on contributes.
 *
 * Listed here so the rail can show them when the add-on is *not* installed -
 * which is the only time this list is used for anything. Once the add-on is
 * active it registers these ids for real through
 * `window.mageyaboAdmin.registerRoute()`, and the locked entry for that id is
 * dropped in favour of the working screen.
 *
 * `nav: false` marks a feature that is part of an existing screen rather than
 * one of its own: it gets no rail entry, only the stand-in page its locked
 * control links to, with `parent` naming the screen to go back to.
 */
export const PRO_FEATURES = [
	{
		id: 'check-in',
		label: __( 'Check-in', 'magepeople-yacht-booking-system' ),
		icon: 'dashicons-yes-alt',
		blurb: __( 'Board guests from a tablet at the quayside by scanning or typing their ticket code, see who is aboard right now, and keep a filterable history of every boarding.', 'magepeople-yacht-booking-system' ),
	},
	{
		id: 'coupons',
		label: __( 'Coupons', 'magepeople-yacht-booking-system' ),
		icon: 'dashicons-tag',
		blurb: __( 'Discount codes with percentage or fixed amounts, minimum spend, usage limits, validity dates and per-yacht restrictions - applied on the booking form and recorded against the booking.', 'magepeople-yacht-booking-system' ),
	},
	{
		id: 'emails',
		label: __( 'Emails', 'magepeople-yacht-booking-system' ),
		icon: 'dashicons-email-alt',
		blurb: __( 'Edit the wording of every email, add a new-booking alert for your team, send payment receipts and cancellation notices, and keep a log of everything that went out.', 'magepeople-yacht-booking-system' ),
	},
	{
		id: 'documents',
		label: __( 'Documents', 'magepeople-yacht-booking-system' ),
		icon: 'dashicons-media-document',
		blurb: __( 'Branded PDF vouchers and invoices for every booking, and export your bookings to CSV or PDF with whatever filters the list is showing.', 'magepeople-yacht-booking-system' ),
	},
	{
		id: 'booking-details',
		label: __( 'Booking details', 'magepeople-yacht-booking-system' ),
		icon: 'dashicons-visibility',
		nav: false,
		parent: 'bookings',
		blurb: __( 'Open any booking to see everything about it in one panel: the full price breakdown with add-ons, discount, tax, deposit and balance, the payment reference and WooCommerce order, check-in stamps, ticket and invoice downloads, and private notes for your team.', 'magepeople-yacht-booking-system' ),
	},
];

export function proFeature( id ) {
	return PRO_FEATURES.find( ( feature ) => feature.id === id );
}
