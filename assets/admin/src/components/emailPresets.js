import { __ } from '@wordpress/i18n';

/**
 * Ready-made confirmation emails an operator can start from. Every `{tag}`
 * here is one BookingEmailer::build_tags() fills in (see EMAIL_TAGS), so a
 * preset works as soon as it is applied. Kept free of promises the plugin
 * cannot know (cancellation terms, meeting points) - operators add those.
 */
export const EMAIL_PRESETS = [
	{
		id: 'classic',
		label: __('Classic confirmation', 'magepeople-yacht-booking-system'),
		subject: __('Your {yacht_name} charter is confirmed - booking #{booking_id}', 'magepeople-yacht-booking-system'),
		body: __(
			'<p>Hi {guest_name},</p>' +
				'<p>Thank you for booking with {site_name}. Your charter aboard <strong>{yacht_name}</strong> is confirmed, and we are looking forward to welcoming you on board.</p>' +
				'<h3>Your booking</h3>' +
				'<ul>' +
				'<li><strong>Booking number:</strong> #{booking_id}</li>' +
				'<li><strong>Yacht:</strong> {yacht_name}</li>' +
				'<li><strong>Charter:</strong> {booking_type} ({booking_mode})</li>' +
				'<li><strong>Date:</strong> {start_date}</li>' +
				'<li><strong>Time:</strong> {start_time} - {end_time}</li>' +
				'<li><strong>Guests:</strong> {guest_count}</li>' +
				'<li><strong>Total:</strong> {total_price}</li>' +
				'</ul>' +
				'<h3>Before you sail</h3>' +
				'<p>Please arrive at the marina about 15 minutes before departure so the crew can welcome you and give a short safety briefing. Bring sunscreen, a hat, swimwear and soft-soled shoes.</p>' +
				'<p>If anything changes or you have a question, simply reply to this email.</p>' +
				'<p>See you on the water,<br>{site_name}<br>{site_url}</p>',
			'magepeople-yacht-booking-system'
		),
	},
	{
		id: 'celebration',
		label: __('Celebration', 'magepeople-yacht-booking-system'),
		subject: __("It's official - {yacht_name} is booked for your celebration!", 'magepeople-yacht-booking-system'),
		body: __(
			'<p>Hi {guest_name},</p>' +
				'<p>Get ready to celebrate! <strong>{yacht_name}</strong> is booked for you and your {guest_count} guests, and our crew is already looking forward to the day.</p>' +
				'<h3>The details</h3>' +
				'<ul>' +
				'<li><strong>When:</strong> {start_date}, {start_time} - {end_time}</li>' +
				'<li><strong>Charter:</strong> {booking_type}</li>' +
				'<li><strong>Booking number:</strong> #{booking_id}</li>' +
				'<li><strong>Total:</strong> {total_price}</li>' +
				'</ul>' +
				'<p>Planning something special - a cake, decorations or a surprise moment? Reply to this email and let us know, and we will help you make it happen.</p>' +
				'<p>Cheers,<br>The {site_name} team</p>',
			'magepeople-yacht-booking-system'
		),
	},
	{
		id: 'corporate',
		label: __('Corporate', 'magepeople-yacht-booking-system'),
		subject: __('Booking confirmation #{booking_id} - {yacht_name}, {start_date}', 'magepeople-yacht-booking-system'),
		body: __(
			'<p>Dear {guest_name},</p>' +
				'<p>Thank you for choosing {site_name} for your event. This email confirms your charter of <strong>{yacht_name}</strong>.</p>' +
				'<table>' +
				'<tr><td><strong>Booking reference</strong></td><td>#{booking_id}</td></tr>' +
				'<tr><td><strong>Date</strong></td><td>{start_date}</td></tr>' +
				'<tr><td><strong>Time</strong></td><td>{start_time} - {end_time}</td></tr>' +
				'<tr><td><strong>Charter type</strong></td><td>{booking_type} ({booking_mode})</td></tr>' +
				'<tr><td><strong>Number of guests</strong></td><td>{guest_count}</td></tr>' +
				'<tr><td><strong>Total</strong></td><td>{total_price}</td></tr>' +
				'</table>' +
				'<p>If you need an invoice with your company details, a guest list arrangement or catering for your group, please reply to this email and our team will take care of it.</p>' +
				'<p>Kind regards,<br>{site_name}<br>{site_url}</p>',
			'magepeople-yacht-booking-system'
		),
	},
	{
		id: 'short',
		label: __('Short & simple', 'magepeople-yacht-booking-system'),
		subject: __('Booking confirmed: {yacht_name} on {start_date}', 'magepeople-yacht-booking-system'),
		body: __(
			'<p>Hi {guest_name},</p>' +
				'<p>Your booking #{booking_id} for <strong>{yacht_name}</strong> is confirmed: {start_date}, {start_time} - {end_time}, {guest_count} guests, {total_price}.</p>' +
				'<p>Questions? Just reply to this email.</p>' +
				'<p>{site_name}</p>',
			'magepeople-yacht-booking-system'
		),
	},
];
