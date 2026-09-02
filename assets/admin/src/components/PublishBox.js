import { __ } from '@wordpress/i18n';

/**
 * The always-visible "Publish" sidebar box (present on every wizard step,
 * not just Basic Info) - status, slug, and the two save actions live here
 * instead of the page header/footer, matching the classic WP editor's
 * Publish box convention.
 */
export default function PublishBox({ status, slug, title, saving, onSlugChange, onSaveDraft, onPublish, permalink }) {
	const isPublished = 'publish' === status;

	return (
		<div className="ybs-wcard ybs-publishbox">
			<div className="ybs-wcard__head">
				<h3>{__('Publish', 'magepeople-yacht-booking-system')}</h3>
			</div>
			<div className="ybs-wcard__body">
				<div className="ybs-publishbox__status">
					<span className={'ybs-badge ' + (isPublished ? 'status-paid' : 'status-pending')}>
						{isPublished ? __('Published', 'magepeople-yacht-booking-system') : __('Draft', 'magepeople-yacht-booking-system')}
					</span>
				</div>

				<div className="ybs-field">
					<label>{__('Slug', 'magepeople-yacht-booking-system')}</label>
					<input
						type="text"
						value={slug || ''}
						placeholder={(title || '').toLowerCase().replace(/\s+/g, '-')}
						onChange={(e) => onSlugChange(e.target.value)}
					/>
					{permalink && (
						<a className="ybs-hint ybs-publishbox__permalink" href={permalink} target="_blank" rel="noreferrer">
							{permalink}
						</a>
					)}
				</div>

				<div className="ybs-publishbox__actions">
					<button type="button" className="ybs-btn" onClick={onSaveDraft} disabled={saving}>
						{__('Save Draft', 'magepeople-yacht-booking-system')}
					</button>
					<button type="button" className="ybs-btn is-primary" onClick={onPublish} disabled={saving}>
						{saving
							? __('Saving…', 'magepeople-yacht-booking-system')
							: isPublished
								? __('Update', 'magepeople-yacht-booking-system')
								: __('Publish', 'magepeople-yacht-booking-system')}
					</button>
				</div>
			</div>
		</div>
	);
}
