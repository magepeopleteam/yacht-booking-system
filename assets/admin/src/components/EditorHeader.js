import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { navigate } from '../router';

/**
 * The yacht editor's top bar, modelled on the rental plugin's modern editor:
 * back to the list on the left, the yacht's name in the middle, and on the
 * right a "View yacht" link plus a split Update/Publish button whose menu
 * holds Preview and Save as Draft. Sticks under the admin bar while the
 * steps scroll beneath it.
 *
 * There is no "Classic editor" button: yachts are edited only here (the post
 * type has no classic edit screen), so "View yacht" takes that slot.
 */
export default function EditorHeader({ title, isNew, status, permalink, saving, onPublish, onSaveDraft }) {
	const [menuOpen, setMenuOpen] = useState(false);
	const groupRef = useRef(null);
	const isPublished = 'publish' === status;

	useEffect(() => {
		if (!menuOpen) {
			return undefined;
		}

		const onDocumentClick = (event) => {
			if (groupRef.current && !groupRef.current.contains(event.target)) {
				setMenuOpen(false);
			}
		};

		const onKeyDown = (event) => {
			if ('Escape' === event.key) {
				setMenuOpen(false);
			}
		};

		document.addEventListener('mousedown', onDocumentClick);
		document.addEventListener('keydown', onKeyDown);

		return () => {
			document.removeEventListener('mousedown', onDocumentClick);
			document.removeEventListener('keydown', onKeyDown);
		};
	}, [menuOpen]);

	const heading = title || (isNew ? __('Add New Yacht', 'magepeople-yacht-booking-system') : __('(Untitled Yacht)', 'magepeople-yacht-booking-system'));

	return (
		<header className="ybs-editor-head">
			<div className="ybs-editor-head__left">
				<a
					className="ybs-editor-head__back"
					href="#/yachts"
					onClick={(event) => {
						event.preventDefault();
						navigate('yachts');
					}}
				>
					<span className="dashicons dashicons-arrow-left-alt2" aria-hidden="true" />
					{__('Back to Yachts', 'magepeople-yacht-booking-system')}
				</a>
			</div>

			<h1 className="ybs-editor-head__title">{heading}</h1>

			<div className="ybs-editor-head__right">
				{isPublished && permalink && (
					<a className="ybs-editor-head__btn is-ghost" href={permalink} target="_blank" rel="noreferrer">
						<span className="dashicons dashicons-external" aria-hidden="true" />
						{__('View yacht', 'magepeople-yacht-booking-system')}
					</a>
				)}

				<div className="ybs-editor-head__split" ref={groupRef}>
					<button type="button" className="ybs-editor-head__btn is-primary" onClick={onPublish} disabled={saving}>
						{saving
							? __('Saving…', 'magepeople-yacht-booking-system')
							: isPublished
								? __('Update', 'magepeople-yacht-booking-system')
								: __('Publish', 'magepeople-yacht-booking-system')}
					</button>
					<button
						type="button"
						className="ybs-editor-head__btn is-primary is-chevron"
						aria-label={__('More save options', 'magepeople-yacht-booking-system')}
						aria-haspopup="menu"
						aria-expanded={menuOpen}
						onClick={() => setMenuOpen((open) => !open)}
						disabled={saving}
					>
						<span className="dashicons dashicons-arrow-down-alt2" aria-hidden="true" />
					</button>

					{menuOpen && (
						<div className="ybs-editor-head__menu" role="menu">
							{permalink && (
								<a
									className="ybs-editor-head__menu-item"
									role="menuitem"
									href={permalink}
									target="_blank"
									rel="noreferrer"
									onClick={() => setMenuOpen(false)}
								>
									<span className="dashicons dashicons-visibility" aria-hidden="true" />
									{__('Preview', 'magepeople-yacht-booking-system')}
								</a>
							)}
							<button
								type="button"
								className="ybs-editor-head__menu-item"
								role="menuitem"
								onClick={() => {
									setMenuOpen(false);
									onSaveDraft();
								}}
							>
								<span className="dashicons dashicons-saved" aria-hidden="true" />
								{isPublished
									? __('Switch to Draft', 'magepeople-yacht-booking-system')
									: __('Save Draft', 'magepeople-yacht-booking-system')}
							</button>
						</div>
					)}
				</div>
			</div>
		</header>
	);
}
