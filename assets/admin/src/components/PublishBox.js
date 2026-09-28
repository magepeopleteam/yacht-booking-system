import { __ } from '@wordpress/i18n';

/**
 * The always-visible "Publish" sidebar box (present on every wizard step,
 * not just Basic Info): the yacht's status and its slug. Saving lives in the
 * editor's top bar (EditorHeader) - Update/Publish and Save Draft are there.
 */
export default function PublishBox({ status, slug, title, onSlugChange, permalink }) {
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
			</div>
		</div>
	);
}
