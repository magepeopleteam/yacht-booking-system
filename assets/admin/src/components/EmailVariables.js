import { __ } from '@wordpress/i18n';

/**
 * Kept in sync by hand with `Ybs\Notifications\BookingEmailer::build_tags()` -
 * every tag here must have a matching replacement on the PHP side.
 */
export const EMAIL_TAGS = [
	'{guest_name}',
	'{guest_email}',
	'{guest_phone}',
	'{yacht_name}',
	'{booking_id}',
	'{booking_type}',
	'{booking_mode}',
	'{start_date}',
	'{start_time}',
	'{end_date}',
	'{end_time}',
	'{guest_count}',
	'{total_price}',
	'{status}',
	'{site_name}',
	'{site_url}',
];

/**
 * A card of clickable `{tag}` chips that insert into whichever classic
 * editor instance `editorId` names - shared by the global Settings > Email
 * tab and the per-yacht confirmation email editor in the wizard.
 */
export default function EmailVariables({ onInsert }) {
	return (
		<div className="ybs-card ybs-email-vars">
			<h3>{__('Dynamic Variables', 'magepeople-yacht-booking-system')}</h3>
			<p className="ybs-hint" style={{ marginTop: 0 }}>
				{__('Click a variable to insert it into the email body.', 'magepeople-yacht-booking-system')}
			</p>
			<div className="ybs-email-vars__list">
				{EMAIL_TAGS.map((tag) => (
					<button
						type="button"
						key={tag}
						className="ybs-email-vars__tag"
						onClick={() => onInsert(tag)}
					>
						{tag}
					</button>
				))}
			</div>
		</div>
	);
}
